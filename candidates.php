<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(405, ['success' => false, 'error' => 'Method not allowed']);
}

$db = new Database();
$pdo = $db->connect();

if (!$pdo) {
    sendJson(500, ['success' => false, 'error' => 'Database connection failed']);
}

if (empty($_SESSION['candidate_id'])) {
    sendJson(401, ['success' => false, 'error' => 'Not authenticated']);
}

$authenticatedCandidate = loadCandidateRegistration($pdo, $_SESSION['candidate_id']);

if (!$authenticatedCandidate) {
    sendJson(404, ['success' => false, 'error' => 'Candidate not found']);
}

if (($authenticatedCandidate['verification_status'] ?? '') !== 'verified') {
    sendJson(403, ['success' => false, 'error' => 'Your candidacy has not been approved yet.']);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'get_stats':
            handleGetStats();
            break;

        case 'get_activity':
            handleGetActivity();
            break;

        case 'get_vote_results':
            handleGetVoteResults();
            break;

        case 'get_analytics':
            handleGetAnalytics();
            break;

        default:
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
    }
} catch (Throwable $e) {
    error_log('Candidate dashboard API error: ' . $e->getMessage());
    sendJson(500, ['success' => false, 'error' => 'Server error']);
}

function sendJson($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function getJsonInput() {
    static $decoded = null;

    if ($decoded !== null) {
        return $decoded;
    }

    $rawInput = file_get_contents('php://input');
    $parsed = json_decode($rawInput, true);
    $decoded = is_array($parsed) ? $parsed : [];

    return $decoded;
}

function loadCandidateRegistration(PDO $pdo, $candidateId) {
    if (!tableExists($pdo, 'candidate_registrations')) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT candidate_id, full_name, email, phone, date_of_birth, party,
               has_bsc_degree, good_conduct, campaign_vision, experience,
               verification_status, payment_status, created_at
        FROM candidate_registrations
        WHERE candidate_id = ?
        LIMIT 1
    ");
    $stmt->execute([$candidateId]);

    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$candidate) {
        return null;
    }

    if (!empty($candidate['date_of_birth'])) {
        try {
            $dob = new DateTime($candidate['date_of_birth']);
            $today = new DateTime();
            $candidate['age'] = $today->diff($dob)->y;
        } catch (Throwable $e) {
            $candidate['age'] = null;
        }
    } else {
        $candidate['age'] = null;
    }

    return $candidate;
}

function getTableColumns(PDO $pdo, $tableName) {
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$tableName`");
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[$column['Field']] = $column['Type'];
        }
        $cache[$tableName] = $columns;
    } catch (Throwable $e) {
        $cache[$tableName] = [];
    }

    return $cache[$tableName];
}

function tableExists(PDO $pdo, $tableName) {
    return !empty(getTableColumns($pdo, $tableName));
}

function tableHasColumn(PDO $pdo, $tableName, $columnName) {
    $columns = getTableColumns($pdo, $tableName);
    return isset($columns[$columnName]);
}

function buildPlaceholders($count) {
    return implode(', ', array_fill(0, max(0, (int) $count), '?'));
}

function isStringColumnType($columnType) {
    return is_string($columnType) && preg_match('/char|text|enum|set/i', $columnType);
}

function resolveElectionCandidates(PDO $pdo, array $candidate) {
    $candidateColumns = getTableColumns($pdo, 'candidates');
    if (empty($candidateColumns) || !isset($candidateColumns['candidate_id'])) {
        return [];
    }

    $conditions = [];
    $params = [];

    if (isset($candidateColumns['candidate_name']) && !empty($candidate['full_name'])) {
        $conditions[] = 'candidate_name = ?';
        $params[] = $candidate['full_name'];
    }

    if (isset($candidateColumns['full_name']) && !empty($candidate['full_name'])) {
        $conditions[] = 'full_name = ?';
        $params[] = $candidate['full_name'];
    }

    if (!empty($candidate['candidate_id']) && isStringColumnType($candidateColumns['candidate_id'])) {
        $conditions[] = 'candidate_id = ?';
        $params[] = $candidate['candidate_id'];
    }

    if (empty($conditions)) {
        return [];
    }

    $nameExpression = isset($candidateColumns['candidate_name']) ? 'candidate_name' : 'full_name';
    $selectElectionId = isset($candidateColumns['election_id']) ? ', election_id' : '';
    $selectParty = isset($candidateColumns['party']) ? ', party' : ", '' AS party";
    $sql = "SELECT candidate_id{$selectElectionId}, {$nameExpression} AS candidate_name{$selectParty}
            FROM candidates
            WHERE (" . implode(' OR ', $conditions) . ")";

    if (isset($candidateColumns['party']) && !empty($candidate['party'])) {
        $sql .= " AND (party = ? OR party IS NULL OR party = '')";
        $params[] = $candidate['party'];
    }

    if (isset($candidateColumns['created_at'])) {
        $sql .= ' ORDER BY created_at DESC';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function resolveVoteCandidateIds(PDO $pdo, array $candidate) {
    $voteColumns = getTableColumns($pdo, 'votes');
    if (empty($voteColumns) || !isset($voteColumns['candidate_id'])) {
        return [];
    }

    $candidateIds = [];
    foreach (resolveElectionCandidates($pdo, $candidate) as $row) {
        $candidateIds[] = $row['candidate_id'];
    }

    if (empty($candidateIds) && isStringColumnType($voteColumns['candidate_id']) && !empty($candidate['candidate_id'])) {
        $candidateIds[] = $candidate['candidate_id'];
    }

    return array_values(array_unique($candidateIds));
}

function getCandidateVoteTotal(PDO $pdo, array $candidateIds) {
    if (empty($candidateIds) || !tableExists($pdo, 'votes')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM votes WHERE candidate_id IN (' . buildPlaceholders(count($candidateIds)) . ')'
    );
    $stmt->execute($candidateIds);

    return (int) $stmt->fetchColumn();
}

function getTotalVotesCast(PDO $pdo) {
    if (!tableExists($pdo, 'votes')) {
        return 0;
    }

    return (int) $pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn();
}

function getVerifiedVoterCount(PDO $pdo) {
    if (!tableExists($pdo, 'voters')) {
        return 0;
    }

    if (tableHasColumn($pdo, 'voters', 'is_verified')) {
        $hasUserType = tableHasColumn($pdo, 'voters', 'user_type');
        $sql = 'SELECT COUNT(*) FROM voters WHERE is_verified = 1';
        if ($hasUserType) {
            $sql .= " AND (user_type IS NULL OR user_type <> 'admin')";
        }
        return (int) $pdo->query($sql)->fetchColumn();
    }

    if (tableHasColumn($pdo, 'voters', 'registration_status')) {
        return (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE registration_status = 'verified'")->fetchColumn();
    }

    return (int) $pdo->query('SELECT COUNT(*) FROM voters')->fetchColumn();
}

function getBlockchainBlockCount(PDO $pdo) {
    if (tableExists($pdo, 'blockchain_blocks')) {
        return (int) $pdo->query('SELECT COUNT(*) FROM blockchain_blocks')->fetchColumn();
    }

    if (tableExists($pdo, 'blocks')) {
        return (int) $pdo->query('SELECT COUNT(*) FROM blocks')->fetchColumn();
    }

    return 0;
}

function getVotesByCandidate(PDO $pdo) {
    $candidateColumns = getTableColumns($pdo, 'candidates');
    $voteColumns = getTableColumns($pdo, 'votes');

    if (empty($candidateColumns) || empty($voteColumns) || !isset($candidateColumns['candidate_id']) || !isset($voteColumns['candidate_id'])) {
        return [];
    }

    $nameColumn = isset($candidateColumns['candidate_name']) ? 'candidate_name' : (isset($candidateColumns['full_name']) ? 'full_name' : null);
    if (!$nameColumn) {
        return [];
    }

    $selectParty = isset($candidateColumns['party']) ? 'c.party' : "''";
    $groupBy = ['c.' . $nameColumn];
    if (isset($candidateColumns['party'])) {
        $groupBy[] = 'c.party';
    }

    $joinConditions = ['v.candidate_id = c.candidate_id'];
    if (isset($candidateColumns['election_id']) && isset($voteColumns['election_id'])) {
        $joinConditions[] = 'v.election_id = c.election_id';
    }

    $where = [];
    if (isset($candidateColumns['verification_status'])) {
        $where[] = "c.verification_status = 'verified'";
    }

    $sql = "
        SELECT c.{$nameColumn} AS candidate_name,
               {$selectParty} AS party,
               COUNT(v.candidate_id) AS vote_count
        FROM candidates c
        LEFT JOIN votes v
            ON " . implode(' AND ', $joinConditions);

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= '
        GROUP BY ' . implode(', ', $groupBy) . '
        ORDER BY vote_count DESC, candidate_name ASC';

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['vote_count'] = (int) ($row['vote_count'] ?? 0);
    }
    unset($row);

    return $rows;
}

function getCandidateActivity(PDO $pdo, $candidateId) {
    $columns = getTableColumns($pdo, 'candidate_activity_log');
    if (empty($columns)) {
        return [];
    }

    $typeColumn = isset($columns['activity_type']) ? 'activity_type' : (isset($columns['action']) ? 'action' : null);
    $timeColumn = isset($columns['created_at']) ? 'created_at' : (isset($columns['timestamp']) ? 'timestamp' : null);
    if (!$typeColumn || !$timeColumn) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT {$typeColumn} AS type, description, {$timeColumn} AS timestamp
        FROM candidate_activity_log
        WHERE candidate_id = ?
        ORDER BY {$timeColumn} DESC
        LIMIT 10
    ");
    $stmt->execute([$candidateId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVoteTrend(PDO $pdo, array $candidateIds) {
    if (empty($candidateIds) || !tableExists($pdo, 'votes') || !tableHasColumn($pdo, 'votes', 'timestamp')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT DATE(timestamp) AS date, COUNT(*) AS votes
        FROM votes
        WHERE candidate_id IN (" . buildPlaceholders(count($candidateIds)) . ")
        GROUP BY DATE(timestamp)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute($candidateIds);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['votes'] = (int) ($row['votes'] ?? 0);
    }
    unset($row);

    return $rows;
}

function getPeakVotingTimes(PDO $pdo, array $candidateIds) {
    if (empty($candidateIds) || !tableExists($pdo, 'votes') || !tableHasColumn($pdo, 'votes', 'timestamp')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT HOUR(timestamp) AS hour, COUNT(*) AS votes
        FROM votes
        WHERE candidate_id IN (" . buildPlaceholders(count($candidateIds)) . ")
        GROUP BY HOUR(timestamp)
        ORDER BY hour ASC
    ");
    $stmt->execute($candidateIds);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['hour'] = (int) ($row['hour'] ?? 0);
        $row['votes'] = (int) ($row['votes'] ?? 0);
    }
    unset($row);

    return $rows;
}

function getGeographicBreakdown(PDO $pdo, array $candidateIds, $totalVotes) {
    if (empty($candidateIds) || !tableExists($pdo, 'votes') || !tableExists($pdo, 'voters') || !tableHasColumn($pdo, 'votes', 'voter_id')) {
        return [];
    }

    $regionColumns = [];
    if (tableHasColumn($pdo, 'voters', 'city')) {
        $regionColumns[] = "NULLIF(TRIM(voters.city), '')";
    }
    if (tableHasColumn($pdo, 'voters', 'state')) {
        $regionColumns[] = "NULLIF(TRIM(voters.state), '')";
    }

    if (empty($regionColumns)) {
        return [];
    }

    $regionExpression = 'COALESCE(' . implode(', ', $regionColumns) . ", 'Unknown')";
    $stmt = $pdo->prepare("
        SELECT {$regionExpression} AS region, COUNT(*) AS votes
        FROM votes
        INNER JOIN voters ON voters.voter_id = votes.voter_id
        WHERE votes.candidate_id IN (" . buildPlaceholders(count($candidateIds)) . ")
        GROUP BY region
        ORDER BY votes DESC, region ASC
        LIMIT 5
    ");
    $stmt->execute($candidateIds);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['votes'] = (int) ($row['votes'] ?? 0);
        $row['percentage'] = $totalVotes > 0 ? round(($row['votes'] / $totalVotes) * 100, 1) : 0;
    }
    unset($row);

    return $rows;
}

function getDemographicBreakdown(PDO $pdo, array $candidateIds, $totalVotes) {
    if (empty($candidateIds) || !tableExists($pdo, 'votes') || !tableExists($pdo, 'voters') || !tableHasColumn($pdo, 'voters', 'dob')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT voters.dob
        FROM votes
        INNER JOIN voters ON voters.voter_id = votes.voter_id
        WHERE votes.candidate_id IN (" . buildPlaceholders(count($candidateIds)) . ")
          AND voters.dob IS NOT NULL
    ");
    $stmt->execute($candidateIds);

    $buckets = [
        '18-25' => 0,
        '26-35' => 0,
        '36-45' => 0,
        '46-60' => 0,
        '60+' => 0,
    ];
    $today = new DateTime();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        try {
            $dob = new DateTime($row['dob']);
            $age = $today->diff($dob)->y;
        } catch (Throwable $e) {
            continue;
        }

        if ($age <= 25) {
            $buckets['18-25']++;
        } elseif ($age <= 35) {
            $buckets['26-35']++;
        } elseif ($age <= 45) {
            $buckets['36-45']++;
        } elseif ($age <= 60) {
            $buckets['46-60']++;
        } else {
            $buckets['60+']++;
        }
    }

    $rows = [];
    foreach ($buckets as $label => $count) {
        if ($count === 0) {
            continue;
        }

        $rows[] = [
            'age_group' => $label,
            'count' => $count,
            'percentage' => $totalVotes > 0 ? round(($count / $totalVotes) * 100, 1) : 0,
        ];
    }

    return $rows;
}

function handleGetStats() {
    global $pdo, $authenticatedCandidate;

    $candidateIds = resolveVoteCandidateIds($pdo, $authenticatedCandidate);
    $totalVotes = getCandidateVoteTotal($pdo, $candidateIds);

    sendJson(200, [
        'success' => true,
        'data' => [
            'total_votes' => $totalVotes,
            'total_voters' => getVerifiedVoterCount($pdo),
            'votes_cast' => getTotalVotesCast($pdo),
            'blockchain_blocks' => getBlockchainBlockCount($pdo),
            'votes_by_candidate' => getVotesByCandidate($pdo),
        ],
    ]);
}

function handleGetActivity() {
    global $pdo, $authenticatedCandidate;

    sendJson(200, [
        'success' => true,
        'data' => getCandidateActivity($pdo, $authenticatedCandidate['candidate_id']),
    ]);
}

function handleGetVoteResults() {
    global $pdo, $authenticatedCandidate;

    $candidateIds = resolveVoteCandidateIds($pdo, $authenticatedCandidate);
    $ownTotalVotes = getCandidateVoteTotal($pdo, $candidateIds);
    $candidateRows = getVotesByCandidate($pdo);

    if (empty($candidateRows)) {
        $candidateRows[] = [
            'candidate_name' => $authenticatedCandidate['full_name'],
            'party' => $authenticatedCandidate['party'] ?? '',
            'vote_count' => $ownTotalVotes,
        ];
    }

    $results = [];
    $totalVotes = 0;

    foreach ($candidateRows as $row) {
        $votes = (int) ($row['vote_count'] ?? 0);
        $totalVotes += $votes;
        $results[] = [
            'name' => $row['candidate_name'] ?? 'Unknown',
            'party' => !empty($row['party']) ? $row['party'] : 'Independent',
            'votes' => $votes,
        ];
    }

    sendJson(200, [
        'success' => true,
        'data' => [
            'candidates' => $results,
            'total_votes' => $totalVotes,
        ],
    ]);
}

function handleGetAnalytics() {
    global $pdo, $authenticatedCandidate;

    $candidateIds = resolveVoteCandidateIds($pdo, $authenticatedCandidate);
    $totalVotes = getCandidateVoteTotal($pdo, $candidateIds);
    $totalSystemVotes = getTotalVotesCast($pdo);

    sendJson(200, [
        'success' => true,
        'data' => [
            'vote_trend' => getVoteTrend($pdo, $candidateIds),
            'peak_times' => getPeakVotingTimes($pdo, $candidateIds),
            'geographic' => getGeographicBreakdown($pdo, $candidateIds, $totalVotes),
            'demographics' => getDemographicBreakdown($pdo, $candidateIds, $totalVotes),
            'total_votes' => $totalVotes,
            'total_system_votes' => $totalSystemVotes,
            'percentage' => $totalSystemVotes > 0 ? round(($totalVotes / $totalSystemVotes) * 100, 1) : 0,
        ],
    ]);
}
?>
