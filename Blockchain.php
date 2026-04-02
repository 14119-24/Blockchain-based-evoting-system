<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Cryptography.php';

class Blockchain {
    private $db;
    private $crypto;
    private $difficulty = 4;

    public function __construct() {
        $this->db = (new Database())->connect();
        $this->crypto = new Cryptography();
        $this->ensureTables();
    }

    public function createGenesisBlock($election_id) {
        $genesis_data = json_encode([
            "message" => "Genesis Block for Election " . $election_id,
            "timestamp" => time(),
            "election_id" => $election_id
        ]);

        $block = [
            "index" => 0,
            "timestamp" => time(),
            "data" => $genesis_data,
            "transactions" => [],
            "previous_hash" => "0",
            "nonce" => 0,
            "merkle_root" => $this->crypto->hash($genesis_data)
        ];

        $block["hash"] = $this->calculateHash($block);

        return $this->saveBlock($block) ? $block : false;
    }

    public function addBlock($transactions, $election_id) {
        $last_block = $this->getLastBlock();

        if (!$last_block) {
            $this->createGenesisBlock($election_id);
            $last_block = $this->getLastBlock();
        }

        $new_block = [
            "index" => (int) ($last_block["index"] ?? 0) + 1,
            "timestamp" => time(),
            "transactions" => $transactions,
            "previous_hash" => $last_block["hash"] ?? "0",
            "nonce" => 0,
            "merkle_root" => $this->buildMerkleTree($transactions)
        ];

        $new_block = $this->mineBlock($new_block);

        if ($this->saveBlock($new_block)) {
            foreach ($transactions as $transaction) {
                $this->saveTransaction($transaction, $new_block["hash"], $new_block["timestamp"]);
            }
            return $new_block;
        }

        return false;
    }

    public function validateChain() {
        $blocks = $this->getAllBlocks();

        if (count($blocks) <= 1) {
            return true;
        }

        for ($i = 1; $i < count($blocks); $i++) {
            $current_block = $blocks[$i];
            $previous_block = $blocks[$i - 1];

            if ($current_block["hash"] !== $this->calculateHash($current_block)) {
                return false;
            }

            if ($current_block["previous_hash"] !== $previous_block["hash"]) {
                return false;
            }

            if (substr($current_block["hash"], 0, $this->difficulty) !== str_repeat("0", $this->difficulty)) {
                return false;
            }
        }

        return true;
    }

    public function getLatestBlockHash() {
        $lastBlock = $this->getLastBlock();
        return $lastBlock["hash"] ?? null;
    }

    public function recordVoteOnChain(array $voteRecord) {
        $this->ensureTables();

        $transactionId = trim((string) ($voteRecord['transaction_id'] ?? ''));
        if ($transactionId === '') {
            return false;
        }

        if ($this->transactionExists($transactionId)) {
            return true;
        }

        $blockHash = trim((string) ($voteRecord['block_hash'] ?? ''));
        if ($blockHash === '') {
            $blockHash = hash('sha256', ($this->getLatestBlockHash() ?: '0') . '|' . $transactionId);
        }

        $previousHash = $this->getLatestBlockHash() ?: '0';
        $timestamp = $this->normalizeTimestamp($voteRecord['timestamp'] ?? null);

        if (!$this->blockExists($blockHash)) {
            $stmt = $this->db->prepare("
                INSERT INTO blocks (
                    block_hash, previous_hash, merkle_root, nonce, transactions_count, timestamp
                ) VALUES (
                    :block_hash, :previous_hash, :merkle_root, :nonce, :transactions_count, :timestamp
                )
            ");
            $stmt->bindValue(':block_hash', $blockHash, PDO::PARAM_STR);
            $stmt->bindValue(':previous_hash', $previousHash, PDO::PARAM_STR);
            $stmt->bindValue(':merkle_root', $voteRecord['vote_hash'] ?? hash('sha256', $transactionId), PDO::PARAM_STR);
            $stmt->bindValue(':nonce', 0, PDO::PARAM_INT);
            $stmt->bindValue(':transactions_count', 1, PDO::PARAM_INT);
            $stmt->bindValue(':timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->execute();
        }

        $stmt = $this->db->prepare("
            INSERT INTO transactions (
                transaction_id, block_hash, voter_id_hash, encrypted_vote, digital_signature,
                election_id, candidate_id, timestamp
            ) VALUES (
                :transaction_id, :block_hash, :voter_id_hash, :encrypted_vote, :digital_signature,
                :election_id, :candidate_id, :timestamp
            )
        ");
        $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_STR);
        $stmt->bindValue(':block_hash', $blockHash, PDO::PARAM_STR);
        $stmt->bindValue(':voter_id_hash', $voteRecord['voter_id_hash'] ?? hash('sha256', ($voteRecord['voter_id'] ?? '') . '|' . ($voteRecord['election_id'] ?? '')), PDO::PARAM_STR);
        $stmt->bindValue(':encrypted_vote', $voteRecord['encrypted_vote'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':digital_signature', $voteRecord['digital_signature'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':election_id', (int) ($voteRecord['election_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':candidate_id', (int) ($voteRecord['candidate_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->execute();

        return true;
    }

    public function backfillVotesToChain() {
        $this->ensureTables();

        try {
            $stmt = $this->db->query("
                SELECT vote_id, election_id, voter_id, candidate_id, encrypted_vote, vote_hash,
                       block_hash, transaction_id, signature, timestamp
                FROM votes
                WHERE transaction_id IS NOT NULL
                ORDER BY vote_id ASC
            ");

            $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = 0;

            foreach ($votes as $vote) {
                if ($this->transactionExists($vote['transaction_id'])) {
                    continue;
                }

                $blockHash = $vote['block_hash'] ?: hash('sha256', ($this->getLatestBlockHash() ?: '0') . '|' . $vote['transaction_id']);

                $this->recordVoteOnChain([
                    'transaction_id' => $vote['transaction_id'],
                    'block_hash' => $blockHash,
                    'vote_hash' => $vote['vote_hash'],
                    'voter_id' => $vote['voter_id'],
                    'voter_id_hash' => hash('sha256', $vote['voter_id'] . '|' . $vote['election_id']),
                    'encrypted_vote' => $vote['encrypted_vote'],
                    'digital_signature' => $vote['signature'],
                    'election_id' => $vote['election_id'],
                    'candidate_id' => $vote['candidate_id'],
                    'timestamp' => $vote['timestamp']
                ]);
                $count++;
            }

            return $count;
        } catch (PDOException $e) {
            error_log("Blockchain vote backfill error: " . $e->getMessage());
            return 0;
        }
    }

    private function mineBlock($block) {
        while (true) {
            $block["nonce"]++;
            $hash = $this->calculateHash($block);

            if (substr($hash, 0, $this->difficulty) === str_repeat("0", $this->difficulty)) {
                $block["hash"] = $hash;
                return $block;
            }
        }
    }

    private function calculateHash($block) {
        $block_string = json_encode([
            $block["index"],
            $block["timestamp"],
            $block["merkle_root"],
            $block["previous_hash"],
            $block["nonce"]
        ]);

        return hash("sha256", $block_string);
    }

    private function buildMerkleTree($transactions) {
        if (count($transactions) === 0) {
            return $this->crypto->hash("empty");
        }

        $hashes = [];
        foreach ($transactions as $tx) {
            $hashes[] = $this->crypto->hash(json_encode($tx));
        }

        while (count($hashes) > 1) {
            $new_level = [];
            for ($i = 0; $i < count($hashes); $i += 2) {
                if ($i + 1 < count($hashes)) {
                    $new_level[] = $this->crypto->hash($hashes[$i] . $hashes[$i + 1]);
                } else {
                    $new_level[] = $this->crypto->hash($hashes[$i] . $hashes[$i]);
                }
            }
            $hashes = $new_level;
        }

        return $hashes[0];
    }

    private function saveBlock($block) {
        try {
            $query = "
                INSERT INTO blocks (
                    block_hash, previous_hash, merkle_root, nonce, transactions_count, timestamp
                ) VALUES (
                    :hash, :prev_hash, :merkle_root, :nonce, :tx_count, :timestamp
                )
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":hash", $block["hash"]);
            $stmt->bindValue(":prev_hash", $block["previous_hash"]);
            $stmt->bindValue(":merkle_root", $block["merkle_root"]);
            $stmt->bindValue(":nonce", $block["nonce"], PDO::PARAM_INT);
            $stmt->bindValue(":tx_count", count($block["transactions"] ?? []), PDO::PARAM_INT);
            $stmt->bindValue(":timestamp", $this->normalizeTimestamp($block["timestamp"] ?? null), PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Save block error: " . $e->getMessage());
            return false;
        }
    }

    private function saveTransaction($transaction, $block_hash, $timestamp = null) {
        try {
            $query = "
                INSERT INTO transactions (
                    transaction_id, block_hash, voter_id_hash, encrypted_vote, digital_signature,
                    election_id, candidate_id, timestamp
                ) VALUES (
                    :tx_id, :block_hash, :voter_hash, :encrypted_vote, :signature,
                    :election_id, :candidate_id, :timestamp
                )
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":tx_id", $transaction["transaction_id"]);
            $stmt->bindValue(":block_hash", $block_hash);
            $stmt->bindValue(":voter_hash", $transaction["voter_id_hash"] ?? '');
            $stmt->bindValue(":encrypted_vote", $transaction["encrypted_vote"] ?? '');
            $stmt->bindValue(":signature", $transaction["digital_signature"] ?? '');
            $stmt->bindValue(":election_id", (int) ($transaction["election_id"] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(":candidate_id", (int) ($transaction["candidate_id"] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(":timestamp", $this->normalizeTimestamp($timestamp), PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Save transaction error: " . $e->getMessage());
            return false;
        }
    }

    private function getLastBlock() {
        try {
            $query = "
                SELECT block_id, block_hash, previous_hash, merkle_root, nonce, transactions_count, timestamp
                FROM blocks
                ORDER BY block_id DESC
                LIMIT 1
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->normalizeBlockRow($row) : false;
        } catch (PDOException $e) {
            error_log("Get last block error: " . $e->getMessage());
            return false;
        }
    }

    private function getAllBlocks() {
        try {
            $query = "
                SELECT block_id, block_hash, previous_hash, merkle_root, nonce, transactions_count, timestamp
                FROM blocks
                ORDER BY block_id ASC
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map([$this, 'normalizeBlockRow'], $rows);
        } catch (PDOException $e) {
            error_log("Get all blocks error: " . $e->getMessage());
            return [];
        }
    }

    private function normalizeBlockRow($row) {
        return [
            "index" => (int) ($row["block_id"] ?? 0),
            "block_id" => (int) ($row["block_id"] ?? 0),
            "hash" => $row["block_hash"] ?? '',
            "block_hash" => $row["block_hash"] ?? '',
            "previous_hash" => $row["previous_hash"] ?? '0',
            "merkle_root" => $row["merkle_root"] ?? $this->crypto->hash('empty'),
            "nonce" => (int) ($row["nonce"] ?? 0),
            "transactions_count" => (int) ($row["transactions_count"] ?? 0),
            "timestamp" => strtotime($row["timestamp"] ?? 'now')
        ];
    }

    private function tableExists($tableName) {
        try {
            $stmt = $this->db->prepare("SHOW TABLES LIKE :table_name");
            $stmt->bindValue(':table_name', $tableName, PDO::PARAM_STR);
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function ensureTables() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS blocks (
                block_id INT AUTO_INCREMENT PRIMARY KEY,
                block_hash VARCHAR(255) NOT NULL UNIQUE,
                previous_hash VARCHAR(255) DEFAULT NULL,
                merkle_root VARCHAR(255) DEFAULT NULL,
                nonce INT DEFAULT 0,
                transactions_count INT DEFAULT 0,
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_block_hash (block_hash),
                INDEX idx_block_timestamp (timestamp)
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                tx_id INT AUTO_INCREMENT PRIMARY KEY,
                transaction_id VARCHAR(255) NOT NULL UNIQUE,
                block_hash VARCHAR(255) NOT NULL,
                voter_id_hash VARCHAR(255) DEFAULT NULL,
                encrypted_vote LONGTEXT,
                digital_signature LONGTEXT,
                election_id INT DEFAULT NULL,
                candidate_id INT DEFAULT NULL,
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_transaction_id (transaction_id),
                INDEX idx_transaction_block_hash (block_hash),
                INDEX idx_transaction_timestamp (timestamp),
                INDEX idx_transaction_election (election_id)
            )
        ");
    }

    private function normalizeTimestamp($timestamp) {
        if (empty($timestamp)) {
            return date('Y-m-d H:i:s');
        }

        if (is_numeric($timestamp)) {
            return date('Y-m-d H:i:s', (int) $timestamp);
        }

        $parsed = strtotime((string) $timestamp);
        if ($parsed === false) {
            return date('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', $parsed);
    }

    private function blockExists($blockHash) {
        $stmt = $this->db->prepare("SELECT block_id FROM blocks WHERE block_hash = :block_hash LIMIT 1");
        $stmt->bindValue(':block_hash', $blockHash, PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function transactionExists($transactionId) {
        $stmt = $this->db->prepare("SELECT tx_id FROM transactions WHERE transaction_id = :transaction_id LIMIT 1");
        $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
?>
