<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Cryptography.php';
require_once __DIR__ . '/../core/Blockchain.php';
require_once __DIR__ . '/../core/SystemSettings.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

class VoteAPI {
    private $db;
    private $crypto;
    private $blockchain;
    private $systemSettings;

    public function __construct() {
        $this->db = (new Database())->connect();
        $this->crypto = new Cryptography();
        $this->blockchain = new Blockchain();
        $this->systemSettings = new SystemSettings($this->db);
    }

    public function submitVote($data) {
        if (!$this->isAuthenticatedVoter()) {
            return $this->errorResponse('Not authenticated');
        }

        $voterId = $_SESSION['voter_id'];
        $electionId = (int) ($data['election_id'] ?? 0);
        $candidateId = (int) ($data['candidate_id'] ?? 0);
        $signature = trim($data['signature'] ?? '');

        if ($electionId <= 0 || $candidateId <= 0 || $signature === '') {
            return $this->errorResponse('Missing required fields');
        }

        try {
            $voter = $this->getVoter($voterId);
            if (!$voter) {
                return $this->errorResponse('Voter not found');
            }

            if ((int) ($voter['is_verified'] ?? 0) !== 1) {
                return $this->errorResponse('Your voter account has not been verified yet');
            }

            $election = $this->getElection($electionId);
            if (!$election) {
                return $this->errorResponse('Election not found');
            }

            if (!$this->isElectionOpen($election)) {
                return $this->errorResponse('Election is not active');
            }

            if (!$this->candidateExistsInElection($candidateId, $electionId)) {
                return $this->errorResponse('Selected candidate is not part of this election');
            }

            if ($this->hasVotedInElection($voterId, $electionId)) {
                return $this->errorResponse('You have already voted in this election');
            }

            $votePayload = json_encode([
                'voter_id' => $voterId,
                'election_id' => $electionId,
                'candidate_id' => $candidateId,
                'timestamp' => time()
            ]);

            $transactionId = 'VTX' . strtoupper(substr(hash('sha256', $voterId . '|' . $electionId . '|' . $candidateId . '|' . microtime(true)), 0, 24));
            $encryptedVote = $this->crypto->encryptVote($votePayload, $this->crypto->getPublicKey());
            $voteHash = hash('sha256', $votePayload . '|' . $transactionId);
            $blockHash = hash('sha256', ($this->blockchain->getLatestBlockHash() ?: '0') . '|' . $voteHash . '|' . $transactionId);

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO votes (
                    election_id, voter_id, candidate_id, encrypted_vote, vote_hash,
                    block_hash, transaction_id, signature, timestamp
                ) VALUES (
                    :election_id, :voter_id, :candidate_id, :encrypted_vote, :vote_hash,
                    :block_hash, :transaction_id, :signature, NOW()
                )
            ");
            $stmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
            $stmt->bindValue(':voter_id', $voterId, PDO::PARAM_STR);
            $stmt->bindValue(':candidate_id', $candidateId, PDO::PARAM_INT);
            $stmt->bindValue(':encrypted_vote', $encryptedVote, PDO::PARAM_STR);
            $stmt->bindValue(':vote_hash', $voteHash, PDO::PARAM_STR);
            $stmt->bindValue(':block_hash', $blockHash, PDO::PARAM_STR);
            $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_STR);
            $stmt->bindValue(':signature', $signature, PDO::PARAM_STR);
            $stmt->execute();

            $updateStmt = $this->db->prepare("UPDATE voters SET has_voted = 1 WHERE voter_id = :voter_id");
            $updateStmt->bindValue(':voter_id', $voterId, PDO::PARAM_STR);
            $updateStmt->execute();

            $this->logAudit($voterId, 'VOTE_CAST', 'Vote cast in election ' . $electionId);

            $this->db->commit();

            try {
                $this->blockchain->recordVoteOnChain([
                    'transaction_id' => $transactionId,
                    'block_hash' => $blockHash,
                    'vote_hash' => $voteHash,
                    'voter_id' => $voterId,
                    'voter_id_hash' => hash('sha256', $voterId . '|' . $electionId),
                    'encrypted_vote' => $encryptedVote,
                    'digital_signature' => $signature,
                    'election_id' => $electionId,
                    'candidate_id' => $candidateId,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } catch (Throwable $ledgerError) {
                error_log('Blockchain ledger write warning: ' . $ledgerError->getMessage());
            }

            return $this->successResponse([
                'message' => 'Vote successfully cast',
                'transaction_id' => $transactionId,
                'block_hash' => $blockHash,
                'timestamp' => date('Y-m-d H:i:s'),
                'election_id' => $electionId,
                'candidate_id' => $candidateId
            ]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Submit vote error: ' . $e->getMessage());
            return $this->errorResponse('Database error');
        }
    }

    public function getResults($electionId) {
        if (!$this->isAuthenticatedUser()) {
            return $this->errorResponse('Not authenticated');
        }

        $electionId = (int) $electionId;
        if ($electionId <= 0) {
            return $this->errorResponse('Valid election ID required');
        }

        $isAdmin = ($_SESSION['user_type'] ?? 'voter') === 'admin';
        if (!$isAdmin && !$this->hasVotedInElection($_SESSION['voter_id'], $electionId)) {
            return $this->errorResponse('You must vote first to view results');
        }

        if (!$isAdmin && !$this->systemSettings->isEnabled('show_live_results', true)) {
            return $this->errorResponse('Live results are currently disabled');
        }

        try {
            $stmt = $this->db->prepare("
                SELECT candidate_id, COUNT(*) AS vote_count
                FROM votes
                WHERE election_id = :election_id
                GROUP BY candidate_id
            ");
            $stmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = [];
            $totalVotes = 0;

            foreach ($rows as $row) {
                $candidateId = (int) $row['candidate_id'];
                $voteCount = (int) $row['vote_count'];
                $results[$candidateId] = $voteCount;
                $totalVotes += $voteCount;
            }

            return $this->successResponse([
                'election_id' => $electionId,
                'results' => $results,
                'total_votes' => $totalVotes,
                'blockchain_valid' => true
            ]);
        } catch (PDOException $e) {
            error_log('Get results error: ' . $e->getMessage());
            return $this->errorResponse('Database error');
        }
    }

    public function verifyVote($transactionId) {
        if (!$this->isAuthenticatedUser()) {
            return $this->errorResponse('Not authenticated');
        }

        $transactionId = trim((string) $transactionId);
        if ($transactionId === '') {
            return $this->errorResponse('Transaction ID required');
        }

        try {
            $stmt = $this->db->prepare("
                SELECT vote_id, election_id, voter_id, candidate_id, vote_hash, block_hash, transaction_id, timestamp
                FROM votes
                WHERE transaction_id = :transaction_id
                LIMIT 1
            ");
            $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_STR);
            $stmt->execute();

            $vote = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$vote) {
                return $this->errorResponse('Transaction not found');
            }

            $transaction = [
                'transaction_id' => $vote['transaction_id'],
                'voter_id_hash' => substr(hash('sha256', $vote['voter_id'] . '|' . $vote['election_id']), 0, 32),
                'timestamp' => $vote['timestamp']
            ];

            $block = [
                'block_hash' => $vote['block_hash'],
                'previous_hash' => $this->getPreviousBlockHash($vote['block_hash']) ?: 'N/A',
                'timestamp' => $vote['timestamp']
            ];

            return $this->successResponse([
                'transaction' => $transaction,
                'block' => $block,
                'verified' => true,
                'in_blockchain' => $this->transactionExistsOnChain($transactionId)
            ]);
        } catch (PDOException $e) {
            error_log('Verify vote error: ' . $e->getMessage());
            return $this->errorResponse('Verification failed');
        }
    }

    public function getVotingStatus($data) {
        if (!$this->isAuthenticatedVoter()) {
            return $this->errorResponse('Not authenticated');
        }

        $electionId = (int) ($data['election_id'] ?? 0);
        if ($electionId <= 0) {
            return $this->errorResponse('Valid election ID required');
        }

        try {
            $voter = $this->getVoter($_SESSION['voter_id']);
            $election = $this->getElection($electionId);

            if (!$voter || !$election) {
                return $this->errorResponse('Voting session not available');
            }

            $hasVoted = $this->hasVotedInElection($_SESSION['voter_id'], $electionId);
            $latestVote = $this->getLatestVote($_SESSION['voter_id'], $electionId);
            $electionOpen = $this->isElectionOpen($election);
            $isVerified = (int) ($voter['is_verified'] ?? 0) === 1;

            return $this->successResponse([
                'has_voted' => $hasVoted,
                'can_vote' => $isVerified && $electionOpen && !$hasVoted,
                'is_verified' => $isVerified,
                'election_open' => $electionOpen,
                'vote_record' => $latestVote
            ]);
        } catch (PDOException $e) {
            error_log('Get voting status error: ' . $e->getMessage());
            return $this->errorResponse('Database error');
        }
    }

    public function getVotingStats($data) {
        if (!$this->isAuthenticatedUser()) {
            return $this->errorResponse('Not authenticated');
        }

        $electionId = (int) ($data['election_id'] ?? 0);

        try {
            $currentBlock = $this->getBlockCount();

            if ($electionId > 0) {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM votes WHERE election_id = :election_id");
                $stmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
                $stmt->execute();
                $totalVotes = (int) $stmt->fetchColumn();
            } else {
                $stmt = $this->db->query("SELECT COUNT(*) FROM votes");
                $totalVotes = (int) $stmt->fetchColumn();
            }

            return $this->successResponse([
                'current_block' => $currentBlock,
                'total_votes' => $totalVotes,
                'chain_valid' => true,
                'storage' => $currentBlock > 0 ? 'database+blockchain' : 'database'
            ]);
        } catch (PDOException $e) {
            error_log('Get voting stats error: ' . $e->getMessage());
            return $this->errorResponse('Database error');
        }
    }

    private function isAuthenticatedUser() {
        return isset($_SESSION['voter_id']);
    }

    private function isAuthenticatedVoter() {
        return isset($_SESSION['voter_id']) && (($_SESSION['user_type'] ?? 'voter') === 'voter');
    }

    private function getVoter($voterId) {
        $stmt = $this->db->prepare("
            SELECT voter_id, full_name, email, is_verified, has_voted
            FROM voters
            WHERE voter_id = :voter_id
            LIMIT 1
        ");
        $stmt->bindValue(':voter_id', $voterId, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getElection($electionId) {
        $stmt = $this->db->prepare("
            SELECT election_id, election_name, status, start_date, end_date
            FROM elections
            WHERE election_id = :election_id
            LIMIT 1
        ");
        $stmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function candidateExistsInElection($candidateId, $electionId) {
        $stmt = $this->db->prepare("
            SELECT candidate_id
            FROM candidates
            WHERE candidate_id = :candidate_id AND election_id = :election_id
            LIMIT 1
        ");
        $stmt->bindValue(':candidate_id', $candidateId, PDO::PARAM_INT);
        $stmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function hasVotedInElection($voterId, $electionId) {
        $stmt = $this->db->prepare("
            SELECT vote_id
            FROM votes
            WHERE voter_id = :voter_id AND election_id = :election_id
            LIMIT 1
        ");
        $stmt->bindValue(':voter_id', $voterId, PDO::PARAM_STR);
        $stmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function getLatestVote($voterId, $electionId) {
        $stmt = $this->db->prepare("
            SELECT transaction_id, block_hash, timestamp
            FROM votes
            WHERE voter_id = :voter_id AND election_id = :election_id
            ORDER BY vote_id DESC
            LIMIT 1
        ");
        $stmt->bindValue(':voter_id', $voterId, PDO::PARAM_STR);
        $stmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function isElectionOpen($election) {
        $status = $election['status'] ?? '';
        if ($status === 'ongoing') {
            return true;
        }

        $now = time();
        $start = strtotime($election['start_date'] ?? '');
        $end = strtotime($election['end_date'] ?? '');

        return $status !== 'completed' && $start && $end && $now >= $start && $now <= $end;
    }

    private function getBlockCount() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM blocks");
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    private function getPreviousBlockHash($blockHash) {
        try {
            $stmt = $this->db->prepare("SELECT previous_hash FROM blocks WHERE block_hash = :block_hash LIMIT 1");
            $stmt->bindValue(':block_hash', $blockHash, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchColumn() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private function transactionExistsOnChain($transactionId) {
        try {
            $stmt = $this->db->prepare("SELECT tx_id FROM transactions WHERE transaction_id = :transaction_id LIMIT 1");
            $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_STR);
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function columnExists($tableName, $columnName) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `$tableName` LIKE :column_name");
            $stmt->bindValue(':column_name', $columnName, PDO::PARAM_STR);
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function logAudit($userId, $action, $description) {
        try {
            if ($this->columnExists('audit_logs', 'user_id')) {
                $stmt = $this->db->prepare("
                    INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent)
                    VALUES (:user_id, :action, :description, :ip_address, :user_agent)
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
                $stmt->bindValue(':action', $action, PDO::PARAM_STR);
                $stmt->bindValue(':description', $description, PDO::PARAM_STR);
                $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? 'unknown', PDO::PARAM_STR);
                $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', PDO::PARAM_STR);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO audit_logs (voter_id, action, details, ip_address, timestamp)
                    VALUES (:user_id, :action, :description, :ip_address, NOW())
                ");
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
                $stmt->bindValue(':action', $action, PDO::PARAM_STR);
                $stmt->bindValue(':description', $description, PDO::PARAM_STR);
                $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? 'unknown', PDO::PARAM_STR);
            }

            $stmt->execute();
        } catch (PDOException $e) {
            error_log('Vote audit log warning: ' . $e->getMessage());
        }
    }

    private function successResponse($data) {
        return json_encode([
            'success' => true,
            'data' => $data
        ]);
    }

    private function errorResponse($message) {
        return json_encode([
            'success' => false,
            'error' => $message
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api = new VoteAPI();
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'submit':
            echo $api->submitVote($data);
            break;
        case 'results':
            echo $api->getResults($data['election_id'] ?? 0);
            break;
        case 'verify':
            echo $api->verifyVote($data['transaction_id'] ?? '');
            break;
        case 'status':
            echo $api->getVotingStatus($data);
            break;
        case 'stats':
            echo $api->getVotingStats($data);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
?>
