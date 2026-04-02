<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Cryptography.php';
require_once __DIR__ . '/../core/Blockchain.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/AdminAuth.php';
require_once __DIR__ . '/../core/SystemSettings.php';

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

class AdminAPI {
    private $db;
    private $crypto;
    private $blockchain;
    private $systemSettings;
    
    public function __construct() {
        $this->db = (new Database())->connect();
        $this->crypto = new Cryptography();
        $this->blockchain = new Blockchain();
        $this->systemSettings = new SystemSettings($this->db);
        $this->blockchain->backfillVotesToChain();
        AdminAuth::ensureDefaultAdminAccount($this->db);
    }

    private function isAdminAuthorized() {
        return AdminAuth::isSessionAdmin($this->db);
    }

    private function tableExists($tableName) {
        try {
            $stmt = $this->db->prepare("SHOW TABLES LIKE :table_name");
            $stmt->bindParam(":table_name", $tableName);
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function columnExists($tableName, $columnName) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `$tableName` LIKE :column_name");
            $stmt->bindParam(":column_name", $columnName);
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function getElectionDraftStatus() {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM elections LIKE 'status'");
            $column = $stmt->fetch(PDO::FETCH_ASSOC);
            $type = $column['Type'] ?? '';

            if (stripos($type, "'upcoming'") !== false) {
                return 'upcoming';
            }
        } catch (PDOException $e) {
            // Fall back to pending below.
        }

        return 'pending';
    }
    
    // Create new election
    public function createElection($data) {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        // Validate input
        $validation = Validator::validateElection($data);
        if (!$validation['is_valid']) {
            return $this->errorResponse("Validation failed", $validation['errors']);
        }
        
        $data = Validator::sanitize($data);
        
        try {
            $draftStatus = $this->getElectionDraftStatus();
            $electionName = $data['election_name'];
            $description = $data['description'] ?? '';
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];

            $query = "INSERT INTO elections (election_name, description, start_date, end_date, status) 
                     VALUES (:name, :description, :start_date, :end_date, :status)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":name", $electionName);
            $stmt->bindParam(":description", $description);
            $stmt->bindParam(":start_date", $startDate);
            $stmt->bindParam(":end_date", $endDate);
            $stmt->bindValue(":status", $draftStatus);
            
            if ($stmt->execute()) {
                $election_id = $this->db->lastInsertId();
                
                // Best-effort blockchain bootstrap: election creation should still succeed
                // even if blockchain tables are not available in this deployment.
                try {
                    $blockchain = new Blockchain();
                    $blockchain->createGenesisBlock($election_id);
                } catch (Throwable $blockchainError) {
                    error_log("Create election blockchain bootstrap error: " . $blockchainError->getMessage());
                }
                
                $this->logAudit("CREATE_ELECTION", "Created election: " . $data['election_name']);
                
                return $this->successResponse([
                    "message" => "Election created successfully",
                    "election_id" => $election_id
                ]);
            }
            
            return $this->errorResponse("Failed to create election");
            
        } catch(PDOException $e) {
            error_log("Create election error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Add candidate to election
    public function addCandidate($data) {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        $validation = Validator::validateCandidate($data);
        if (!$validation['is_valid']) {
            return $this->errorResponse("Validation failed", $validation['errors']);
        }
        
        $data = Validator::sanitize($data);
        
        try {
            $electionId = $data['election_id'];
            $candidateName = $data['candidate_name'];
            $party = $data['party'] ?? '';
            $symbol = $data['symbol'] ?? '';
            $query = "INSERT INTO candidates (election_id, candidate_name, party, symbol) 
                     VALUES (:election_id, :name, :party, :symbol)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":election_id", $electionId);
            $stmt->bindParam(":name", $candidateName);
            $stmt->bindParam(":party", $party);
            $stmt->bindParam(":symbol", $symbol);
            
            if ($stmt->execute()) {
                $candidate_id = $this->db->lastInsertId();
                
                $this->logAudit("ADD_CANDIDATE", 
                    "Added candidate: " . $data['candidate_name'] . " to election " . $data['election_id']);
                
                return $this->successResponse([
                    "message" => "Candidate added successfully",
                    "candidate_id" => $candidate_id
                ]);
            }
            
            return $this->errorResponse("Failed to add candidate");
            
        } catch(PDOException $e) {
            error_log("Add candidate error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Get candidates for a specific election
    public function getElectionCandidates($data) {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            return $this->errorResponse("Valid election ID required");
        }
        
        try {
            $query = "SELECT * FROM candidates WHERE election_id = :election_id ORDER BY candidate_name";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":election_id", $data['election_id']);
            $stmt->execute();
            
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->successResponse([
                "candidates" => $candidates,
                "count" => count($candidates)
            ]);
            
        } catch(PDOException $e) {
            error_log("Get election candidates error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Remove candidate from election
    public function removeCandidate($data) {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        if (!isset($data['candidate_id']) || !is_numeric($data['candidate_id'])) {
            return $this->errorResponse("Valid candidate ID required");
        }
        
        try {
            $query = "DELETE FROM candidates WHERE candidate_id = :candidate_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":candidate_id", $data['candidate_id']);
            
            if ($stmt->execute()) {
                $this->logAudit("REMOVE_CANDIDATE", "Removed candidate ID: " . $data['candidate_id']);
                
                return $this->successResponse([
                    "message" => "Candidate removed successfully"
                ]);
            }
            
            return $this->errorResponse("Failed to remove candidate");
            
        } catch(PDOException $e) {
            error_log("Remove candidate error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Get all elections
    public function getElections($data) {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        try {
            $status = $data['status'] ?? null;
            
            $query = "SELECT * FROM elections";
            if (!empty($status) && $status !== 'all') {
                $query .= " WHERE status = :status";
            }
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($query);
            if (!empty($status) && $status !== 'all') {
                $stmt->bindParam(":status", $status);
            }
            $stmt->execute();
            
            $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get candidate count for each election
            foreach ($elections as &$election) {
                $query = "SELECT COUNT(*) as candidate_count FROM candidates WHERE election_id = :election_id";
                $stmt2 = $this->db->prepare($query);
                $stmt2->bindValue(":election_id", $election['election_id']);
                $stmt2->execute();
                $count = $stmt2->fetch(PDO::FETCH_ASSOC);
                $election['candidate_count'] = $count['candidate_count'];

                $election['vote_count'] = 0;

                if ($this->tableExists('votes')) {
                    $voteQuery = "SELECT COUNT(*) as vote_count FROM votes WHERE election_id = :election_id";
                    $voteStmt = $this->db->prepare($voteQuery);
                    $voteStmt->bindValue(":election_id", $election['election_id']);
                    $voteStmt->execute();
                    $voteCount = $voteStmt->fetch(PDO::FETCH_ASSOC);
                    $election['vote_count'] = (int) ($voteCount['vote_count'] ?? 0);
                }
            }
            
            return $this->successResponse([
                "elections" => $elections,
                "count" => count($elections)
            ]);
            
        } catch(PDOException $e) {
            error_log("Get elections error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Get all voters
    public function getVoters($data) {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        try {
            $search = $data['search'] ?? '';
            
            if (!empty($search)) {
                // Search by voter_id, full_name, or email
                $query = "SELECT voter_id, full_name, email, created_at as registration_date, is_verified, has_voted 
                         FROM voters 
                         WHERE voter_id LIKE :search OR full_name LIKE :search OR email LIKE :search 
                         ORDER BY created_at DESC";

                $stmt = $this->db->prepare($query);
                $searchTerm = "%$search%";
                $stmt->bindParam(":search", $searchTerm);
            } else {
                // Get all voters
                $query = "SELECT voter_id, full_name, email, created_at as registration_date, is_verified, has_voted 
                         FROM voters ORDER BY created_at DESC";
                
                $stmt = $this->db->prepare($query);
            }
            
            $stmt->execute();
            
            $voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->successResponse([
                "voters" => $voters,
                "count" => count($voters)
            ]);
            
        } catch(PDOException $e) {
            error_log("Get voters error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getPublicElections($data) {
        try {
            $status = $data['status'] ?? 'ongoing';
            $candidateNameColumn = $this->columnExists('candidates', 'candidate_name') ? 'candidate_name' : 'full_name';

            $query = "SELECT * FROM elections";
            $params = [];

            if (!empty($status) && $status !== 'all') {
                $query .= " WHERE status = :status";
                $params[':status'] = $status;
            }

            $query .= " ORDER BY start_date ASC, created_at DESC";

            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($elections as &$election) {
                // Get candidate count
                $countStmt = $this->db->prepare("SELECT COUNT(*) as candidate_count FROM candidates WHERE election_id = :election_id");
                $countStmt->bindValue(":election_id", $election['election_id']);
                $countStmt->execute();
                $election['candidate_count'] = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['candidate_count'] ?? 0);
                
                // Get vote count for this election
                $election['vote_count'] = 0;
                if ($this->tableExists('votes')) {
                    $voteStmt = $this->db->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE election_id = :election_id");
                    $voteStmt->bindValue(":election_id", $election['election_id']);
                    $voteStmt->execute();
                    $election['vote_count'] = (int) ($voteStmt->fetch(PDO::FETCH_ASSOC)['vote_count'] ?? 0);
                }
                
                // Get vote results by candidate
                $resultsStmt = $this->db->prepare("
                    SELECT c.candidate_id, c.{$candidateNameColumn} as candidate_name, COUNT(v.vote_id) as votes
                    FROM candidates c
                    LEFT JOIN votes v ON v.candidate_id = c.candidate_id AND v.election_id = :election_id
                    WHERE c.election_id = :election_id
                    GROUP BY c.candidate_id, c.{$candidateNameColumn}
                    ORDER BY votes DESC, c.{$candidateNameColumn} ASC
                ");
                $resultsStmt->bindValue(":election_id", $election['election_id']);
                $resultsStmt->execute();
                $election['results'] = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($election);

            return $this->successResponse([
                "elections" => $elections,
                "count" => count($elections)
            ]);
        } catch (PDOException $e) {
            error_log("Get public elections error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function verifyVoter($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (empty($data['voter_id'])) {
            return $this->errorResponse("Voter ID required");
        }

        try {
            $query = "UPDATE voters SET is_verified = 1 WHERE voter_id = :voter_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":voter_id", $data['voter_id']);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return $this->errorResponse("Voter not found");
            }

            $this->logAudit("VERIFY_VOTER", "Verified voter: " . $data['voter_id']);

            return $this->successResponse([
                "message" => "Voter verified successfully",
                "voter_id" => $data['voter_id']
            ]);
        } catch (PDOException $e) {
            error_log("Verify voter error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getPublicElectionCandidates($data) {
        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            return $this->errorResponse("Valid election ID required");
        }

        try {
            $query = "SELECT candidate_id, election_id, candidate_name, party, symbol, created_at
                      FROM candidates
                      WHERE election_id = :election_id
                      ORDER BY candidate_name ASC";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":election_id", $data['election_id']);
            $stmt->execute();

            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                "candidates" => $candidates,
                "count" => count($candidates)
            ]);
        } catch (PDOException $e) {
            error_log("Get public election candidates error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getElectionDetails($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            return $this->errorResponse("Valid election ID required");
        }

        try {
            $query = "SELECT * FROM elections WHERE election_id = :election_id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":election_id", $data['election_id']);
            $stmt->execute();

            $election = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$election) {
                return $this->errorResponse("Election not found");
            }

            $countStmt = $this->db->prepare("SELECT COUNT(*) as candidate_count FROM candidates WHERE election_id = :election_id");
            $countStmt->bindValue(":election_id", $data['election_id']);
            $countStmt->execute();
            $election['candidate_count'] = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['candidate_count'] ?? 0);

            $election['vote_count'] = 0;
            if ($this->tableExists('votes')) {
                $voteStmt = $this->db->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE election_id = :election_id");
                $voteStmt->bindValue(":election_id", $data['election_id']);
                $voteStmt->execute();
                $election['vote_count'] = (int) ($voteStmt->fetch(PDO::FETCH_ASSOC)['vote_count'] ?? 0);
            }

            return $this->successResponse([
                "election" => $election
            ]);
        } catch (PDOException $e) {
            error_log("Get election details error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getElectionResults($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            return $this->errorResponse("Valid election ID required");
        }

        try {
            $electionId = (int) $data['election_id'];

            $electionStmt = $this->db->prepare("
                SELECT election_id, election_name, description, status, start_date, end_date
                FROM elections
                WHERE election_id = :election_id
                LIMIT 1
            ");
            $electionStmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
            $electionStmt->execute();
            $election = $electionStmt->fetch(PDO::FETCH_ASSOC);

            if (!$election) {
                return $this->errorResponse("Election not found");
            }

            $resultsStmt = $this->db->prepare("
                SELECT
                    c.candidate_id,
                    c.candidate_name,
                    c.party,
                    c.symbol,
                    COUNT(v.vote_id) AS vote_count
                FROM candidates c
                LEFT JOIN votes v
                    ON v.candidate_id = c.candidate_id
                    AND v.election_id = c.election_id
                WHERE c.election_id = :election_id
                GROUP BY c.candidate_id, c.candidate_name, c.party, c.symbol
                ORDER BY vote_count DESC, c.candidate_name ASC
            ");
            $resultsStmt->bindValue(':election_id', $electionId, PDO::PARAM_INT);
            $resultsStmt->execute();
            $candidateResults = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

            $totalVotes = 0;
            foreach ($candidateResults as &$candidate) {
                $candidate['vote_count'] = (int) ($candidate['vote_count'] ?? 0);
                $totalVotes += $candidate['vote_count'];
            }
            unset($candidate);

            foreach ($candidateResults as &$candidate) {
                $candidate['percentage'] = $totalVotes > 0
                    ? round(($candidate['vote_count'] / $totalVotes) * 100, 1)
                    : 0;
            }
            unset($candidate);

            $verifiedVoterStmt = $this->db->query("
                SELECT COUNT(*)
                FROM voters
                WHERE is_verified = 1
                  AND (user_type IS NULL OR user_type <> 'admin')
            ");
            $verifiedVoters = (int) $verifiedVoterStmt->fetchColumn();

            $winner = null;
            if (!empty($candidateResults)) {
                $winner = $candidateResults[0];
            }

            return $this->successResponse([
                "election" => $election,
                "results" => $candidateResults,
                "total_votes" => $totalVotes,
                "candidate_count" => count($candidateResults),
                "verified_voters" => $verifiedVoters,
                "turnout_percentage" => $verifiedVoters > 0
                    ? round(($totalVotes / $verifiedVoters) * 100, 1)
                    : 0,
                "winner" => $winner
            ]);
        } catch (PDOException $e) {
            error_log("Get election results error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getVoterDetails($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (empty($data['voter_id'])) {
            return $this->errorResponse("Voter ID required");
        }

        try {
            $query = "SELECT voter_id, full_name, email, phone, dob, address, city, state, zip, created_at as registration_date, is_verified, has_voted, user_type
                      FROM voters
                      WHERE voter_id = :voter_id
                      LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":voter_id", $data['voter_id']);
            $stmt->execute();

            $voter = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$voter) {
                return $this->errorResponse("Voter not found");
            }

            return $this->successResponse([
                "voter" => $voter
            ]);
        } catch (PDOException $e) {
            error_log("Get voter details error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Start election
    public function startElection($data) {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            return $this->errorResponse("Valid election ID required");
        }
        
        try {
            $query = "UPDATE elections SET status = 'ongoing' WHERE election_id = :election_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":election_id", $data['election_id']);
            
            if ($stmt->execute()) {
                $this->logAudit("START_ELECTION", "Started election ID: " . $data['election_id']);
                
                return $this->successResponse([
                    "message" => "Election started successfully"
                ]);
            }
            
            return $this->errorResponse("Failed to start election");
            
        } catch(PDOException $e) {
            error_log("Start election error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // End election
    public function endElection($data) {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            return $this->errorResponse("Valid election ID required");
        }
        
        try {
            $query = "UPDATE elections SET status = 'completed', end_date = NOW() 
                     WHERE election_id = :election_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":election_id", $data['election_id']);
            
            if ($stmt->execute()) {
                $this->logAudit("END_ELECTION", "Ended election ID: " . $data['election_id']);
                
                return $this->successResponse([
                    "message" => "Election ended successfully"
                ]);
            }
            
            return $this->errorResponse("Failed to end election");
            
        } catch(PDOException $e) {
            error_log("End election error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function deleteElection($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            return $this->errorResponse("Valid election ID required");
        }

        try {
            $this->db->beginTransaction();

            $deleteCandidates = $this->db->prepare("DELETE FROM candidates WHERE election_id = :election_id");
            $deleteCandidates->bindValue(":election_id", $data['election_id']);
            $deleteCandidates->execute();

            if ($this->tableExists('votes')) {
                $deleteVotes = $this->db->prepare("DELETE FROM votes WHERE election_id = :election_id");
                $deleteVotes->bindValue(":election_id", $data['election_id']);
                $deleteVotes->execute();
            }

            $deleteElection = $this->db->prepare("DELETE FROM elections WHERE election_id = :election_id");
            $deleteElection->bindValue(":election_id", $data['election_id']);
            $deleteElection->execute();

            if ($deleteElection->rowCount() === 0) {
                $this->db->rollBack();
                return $this->errorResponse("Election not found");
            }

            $this->db->commit();
            $this->logAudit("DELETE_ELECTION", "Deleted election ID: " . $data['election_id']);

            return $this->successResponse([
                "message" => "Election deleted successfully"
            ]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Delete election error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function deleteVoter($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (empty($data['voter_id'])) {
            return $this->errorResponse("Voter ID required");
        }

        try {
            $query = "DELETE FROM voters WHERE voter_id = :voter_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":voter_id", $data['voter_id']);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return $this->errorResponse("Voter not found");
            }

            $this->logAudit("DELETE_VOTER", "Deleted voter: " . $data['voter_id']);

            return $this->successResponse([
                "message" => "Voter deleted successfully"
            ]);
        } catch (PDOException $e) {
            error_log("Delete voter error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getBlocks($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (!$this->tableExists('blocks')) {
            return $this->successResponse([
                "blocks" => [],
                "count" => 0,
                "available" => false,
                "message" => "Blockchain blocks table is not available"
            ]);
        }

        try {
            $limit = min(max((int) ($data['limit'] ?? 20), 1), 100);
            $stmt = $this->db->prepare("
                SELECT block_id, block_hash, previous_hash, merkle_root, nonce, transactions_count, timestamp
                FROM blocks
                ORDER BY block_id DESC
                LIMIT :limit
            ");
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->execute();

            $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                "blocks" => $blocks,
                "count" => count($blocks),
                "available" => true
            ]);
        } catch (PDOException $e) {
            error_log("Get blocks error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getBlockDetails($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (!$this->tableExists('blocks')) {
            return $this->errorResponse("Blockchain data is not available");
        }

        if (empty($data['block_hash'])) {
            return $this->errorResponse("Block hash required");
        }

        try {
            $stmt = $this->db->prepare("
                SELECT block_id, block_hash, previous_hash, merkle_root, nonce, transactions_count, timestamp
                FROM blocks
                WHERE block_hash = :block_hash
                LIMIT 1
            ");
            $stmt->bindValue(":block_hash", $data['block_hash']);
            $stmt->execute();

            $block = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$block) {
                return $this->errorResponse("Block not found");
            }

            $transactions = [];
            if ($this->tableExists('transactions')) {
                $txStmt = $this->db->prepare("
                    SELECT transaction_id, block_hash, voter_id_hash, encrypted_vote, digital_signature, timestamp
                    FROM transactions
                    WHERE block_hash = :block_hash
                    ORDER BY timestamp DESC
                ");
                $txStmt->bindValue(":block_hash", $data['block_hash']);
                $txStmt->execute();
                $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $block['transactions'] = $transactions;

            return $this->successResponse([
                "block" => $block
            ]);
        } catch (PDOException $e) {
            error_log("Get block details error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getTransactions($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        if (!$this->tableExists('transactions')) {
            return $this->successResponse([
                "transactions" => [],
                "count" => 0,
                "available" => false,
                "message" => "Transactions table is not available"
            ]);
        }

        try {
            $limit = min(max((int) ($data['limit'] ?? 50), 1), 200);
            $stmt = $this->db->prepare("
                SELECT transaction_id, block_hash, voter_id_hash, encrypted_vote, digital_signature, timestamp
                FROM transactions
                ORDER BY timestamp DESC
                LIMIT :limit
            ");
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->execute();

            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                "transactions" => $transactions,
                "count" => count($transactions),
                "available" => true
            ]);
        } catch (PDOException $e) {
            error_log("Get transactions error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Get blockchain statistics
    public function getBlockchainStats() {
        // Check admin privileges
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        try {
            if (!$this->tableExists('blocks') || !$this->tableExists('transactions')) {
                return $this->successResponse([
                    "blockchain_stats" => [
                        "total_blocks" => 0,
                        "total_transactions" => 0,
                        "latest_block" => null,
                        "daily_stats" => [],
                        "available" => false,
                        "message" => "Blockchain tables are not available in the current database"
                    ]
                ]);
            }

            // Get total blocks
            $query = "SELECT COUNT(*) as total_blocks FROM blocks";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $blocks = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get total transactions
            $query = "SELECT COUNT(*) as total_transactions FROM transactions";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $transactions = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get latest block
            $query = "SELECT * FROM blocks ORDER BY block_id DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $latest_block = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get transactions per day
            $query = "SELECT DATE(timestamp) as date, COUNT(*) as count 
                     FROM transactions 
                     GROUP BY DATE(timestamp) 
                     ORDER BY date DESC 
                     LIMIT 7";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->successResponse([
                "blockchain_stats" => [
                    "total_blocks" => $blocks['total_blocks'],
                    "total_transactions" => $transactions['total_transactions'],
                    "latest_block" => $latest_block,
                    "daily_stats" => $daily_stats
                ]
            ]);
            
        } catch(PDOException $e) {
            error_log("Get blockchain stats error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Get audit logs
    public function getAuditLogs($data) {
        // Check admin privileges - only super admins can view audit logs
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        try {
            if (!$this->tableExists('audit_logs')) {
                return $this->successResponse([
                    "logs" => [],
                    "count" => 0,
                    "available" => false,
                    "message" => "Audit logs table is not available"
                ]);
            }

            $limit = min($data['limit'] ?? 100, 1000);
            $offset = $data['offset'] ?? 0;

            $timeColumn = $this->columnExists('audit_logs', 'timestamp') ? 'timestamp' : 'created_at';
            $userColumn = $this->columnExists('audit_logs', 'user_id') ? 'user_id' : 'voter_id';
            $detailsColumn = $this->columnExists('audit_logs', 'description') ? 'description' : 'details';

            $query = "SELECT
                        {$timeColumn} AS log_time,
                        {$userColumn} AS actor_id,
                        action,
                        {$detailsColumn} AS log_description,
                        ip_address
                      FROM audit_logs
                      ORDER BY {$timeColumn} DESC
                      LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
            $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->successResponse([
                "logs" => $logs,
                "count" => count($logs),
                "available" => true
            ]);
            
        } catch(PDOException $e) {
            error_log("Get audit logs error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }

    public function getSystemSettings() {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        try {
            return $this->successResponse([
                "settings" => $this->systemSettings->getAll()
            ]);
        } catch (Throwable $e) {
            error_log("Get system settings error: " . $e->getMessage());
            return $this->errorResponse("Failed to load system settings");
        }
    }

    public function saveSystemSettings($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        try {
            $settings = $this->systemSettings->save($data, $_SESSION['admin_username'] ?? ($_SESSION['email'] ?? 'admin'));
            $this->logAudit("SAVE_SETTINGS", "Updated system settings");

            return $this->successResponse([
                "message" => "System settings saved successfully",
                "settings" => $settings
            ]);
        } catch (Throwable $e) {
            error_log("Save system settings error: " . $e->getMessage());
            return $this->errorResponse("Failed to save system settings");
        }
    }

    public function getSupportRequests($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        try {
            $limit = min(max((int) ($data['limit'] ?? 25), 1), 100);
            $stmt = $this->db->prepare("
                SELECT request_id, subject, category, priority, message, contact_email, created_by, status, created_at, updated_at
                FROM support_requests
                ORDER BY updated_at DESC, request_id DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                "requests" => $requests,
                "count" => count($requests)
            ]);
        } catch (PDOException $e) {
            error_log("Get support requests error: " . $e->getMessage());
            return $this->errorResponse("Failed to load support requests");
        }
    }

    public function createSupportRequest($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $category = trim((string) ($data['category'] ?? 'general'));
        $priority = trim((string) ($data['priority'] ?? 'medium'));
        $contactEmail = trim((string) ($data['contact_email'] ?? ''));

        if ($subject === '' || $message === '') {
            return $this->errorResponse("Subject and message are required");
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO support_requests (
                    subject, category, priority, message, contact_email, created_by, status
                ) VALUES (
                    :subject, :category, :priority, :message, :contact_email, :created_by, 'open'
                )
            ");
            $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindValue(':category', $category ?: 'general', PDO::PARAM_STR);
            $stmt->bindValue(':priority', $priority ?: 'medium', PDO::PARAM_STR);
            $stmt->bindValue(':message', $message, PDO::PARAM_STR);
            $stmt->bindValue(':contact_email', $contactEmail, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $_SESSION['admin_username'] ?? ($_SESSION['email'] ?? 'admin'), PDO::PARAM_STR);
            $stmt->execute();

            $requestId = (int) $this->db->lastInsertId();
            $this->logAudit("CREATE_SUPPORT_REQUEST", "Created support request #{$requestId}");

            return $this->successResponse([
                "message" => "Support request submitted successfully",
                "request_id" => $requestId
            ]);
        } catch (PDOException $e) {
            error_log("Create support request error: " . $e->getMessage());
            return $this->errorResponse("Failed to submit support request");
        }
    }

    public function updateSupportRequestStatus($data) {
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }

        $requestId = (int) ($data['request_id'] ?? 0);
        $status = trim((string) ($data['status'] ?? ''));
        $allowedStatuses = ['open', 'in_progress', 'resolved'];

        if ($requestId <= 0 || !in_array($status, $allowedStatuses, true)) {
            return $this->errorResponse("Valid support request and status are required");
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE support_requests
                SET status = :status, updated_at = NOW()
                WHERE request_id = :request_id
            ");
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return $this->errorResponse("Support request not found");
            }

            $this->logAudit("UPDATE_SUPPORT_REQUEST", "Updated support request #{$requestId} to {$status}");

            return $this->successResponse([
                "message" => "Support request updated successfully",
                "request_id" => $requestId,
                "status" => $status
            ]);
        } catch (PDOException $e) {
            error_log("Update support request error: " . $e->getMessage());
            return $this->errorResponse("Failed to update support request");
        }
    }
    
    // Reset voter voting status (for testing)
    public function resetVoterStatus($data) {
        // Check admin privileges (admin only)
        if (!$this->isAdminAuthorized()) {
            return $this->errorResponse("Unauthorized");
        }
        
        if (!isset($data['voter_id']) || empty($data['voter_id'])) {
            return $this->errorResponse("Voter ID required");
        }
        
        try {
            $query = "UPDATE voters SET has_voted = 0 WHERE voter_id = :voter_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":voter_id", $data['voter_id']);
            
            if ($stmt->execute()) {
                $this->logAudit("RESET_VOTER", "Reset voting status for: " . $data['voter_id']);
                
                return $this->successResponse([
                    "message" => "Voter status reset successfully"
                ]);
            }
            
            return $this->errorResponse("Failed to reset voter status");
            
        } catch(PDOException $e) {
            error_log("Reset voter status error: " . $e->getMessage());
            return $this->errorResponse("Database error");
        }
    }
    
    // Helper methods
    private function logAudit($action, $description) {
        try {
            $adminUsername = $_SESSION['admin_username'] ?? ($_SESSION['email'] ?? 'system');

            if ($this->columnExists('audit_logs', 'user_id')) {
                $query = "INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) 
                         VALUES (:user_id, :action, :description, :ip, :agent)";

                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":user_id", $adminUsername);
                $stmt->bindParam(":action", $action);
                $stmt->bindParam(":description", $description);
                $stmt->bindValue(":ip", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
                $stmt->bindValue(":agent", $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
            } else {
                $query = "INSERT INTO audit_logs (voter_id, action, details, ip_address, timestamp)
                         VALUES (:user_id, :action, :description, :ip, NOW())";

                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":user_id", $adminUsername);
                $stmt->bindParam(":action", $action);
                $stmt->bindParam(":description", $description);
                $stmt->bindValue(":ip", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            }

            $stmt->execute();
        } catch(PDOException $e) {
            // Silent fail for audit logs
        }
    }
    
    private function successResponse($data) {
        return json_encode([
            "success" => true,
            "data" => $data
        ], JSON_PRETTY_PRINT);
    }
    
    private function errorResponse($message, $errors = []) {
        $response = [
            "success" => false,
            "error" => $message
        ];
        
        if (!empty($errors)) {
            $response["errors"] = $errors;
        }
        
        return json_encode($response, JSON_PRETTY_PRINT);
    }
}

// Handle API request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $api = new AdminAPI();
    $data = json_decode(file_get_contents("php://input"), true) ?: [];
    
    $action = $_GET["action"] ?? "";
    
    switch ($action) {
        case "create_election":
            echo $api->createElection($data);
            break;
        case "add_candidate":
            echo $api->addCandidate($data);
            break;
        case "get_election_candidates":
            echo $api->getElectionCandidates($data);
            break;
        case "remove_candidate":
            echo $api->removeCandidate($data);
            break;
        case "get_elections":
            echo $api->getElections($data);
            break;
        case "get_public_elections":
            echo $api->getPublicElections($data);
            break;
        case "get_public_election_candidates":
            echo $api->getPublicElectionCandidates($data);
            break;
        case "get_election_details":
            echo $api->getElectionDetails($data);
            break;
        case "get_election_results":
            echo $api->getElectionResults($data);
            break;
        case "get_voters":
            echo $api->getVoters($data);
            break;
        case "verify_voter":
            echo $api->verifyVoter($data);
            break;
        case "get_voter_details":
            echo $api->getVoterDetails($data);
            break;
        case "start_election":
            echo $api->startElection($data);
            break;
        case "end_election":
            echo $api->endElection($data);
            break;
        case "delete_election":
            echo $api->deleteElection($data);
            break;
        case "delete_voter":
            echo $api->deleteVoter($data);
            break;
        case "get_blockchain_stats":
            echo $api->getBlockchainStats();
            break;
        case "get_blocks":
            echo $api->getBlocks($data);
            break;
        case "get_block_details":
            echo $api->getBlockDetails($data);
            break;
        case "get_transactions":
            echo $api->getTransactions($data);
            break;
        case "get_system_settings":
            echo $api->getSystemSettings();
            break;
        case "save_system_settings":
            echo $api->saveSystemSettings($data);
            break;
        case "get_support_requests":
            echo $api->getSupportRequests($data);
            break;
        case "create_support_request":
            echo $api->createSupportRequest($data);
            break;
        case "update_support_request_status":
            echo $api->updateSupportRequestStatus($data);
            break;
        case "get_audit_logs":
            echo $api->getAuditLogs($data);
            break;
        case "reset_voter_status":
            echo $api->resetVoterStatus($data);
            break;
        default:
            echo json_encode([
                "success" => false, 
                "error" => "Invalid action"
            ], JSON_PRETTY_PRINT);
    }
}
?>
