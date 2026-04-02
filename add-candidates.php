<?php
header('Content-Type: application/json');

require_once(__DIR__ . '/../config/database.php');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get or create election
    $query = "SELECT id FROM elections LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        $insertElection = "INSERT INTO elections (election_name, start_date, end_date, status) 
                          VALUES (:name, :start, :end, :status)";
        $stmt = $db->prepare($insertElection);
        $stmt->execute([
            ':name' => 'General Election 2024',
            ':start' => date('Y-m-d H:i:s'),
            ':end' => date('Y-m-d H:i:s', strtotime('+7 days')),
            ':status' => 'ongoing'
        ]);
        $electionId = $db->lastInsertId();
    } else {
        $election = $stmt->fetch(PDO::FETCH_ASSOC);
        $electionId = $election['id'];
    }
    
    // Try inserting candidates with only the columns we know exist
    $insertQuery = "INSERT INTO candidates (election_id, full_name, party, party_symbol) 
                   VALUES (:election_id, :full_name, :party, :party_symbol)";
    
    $stmt = $db->prepare($insertQuery);
    $stmt->execute([
        ':election_id' => $electionId,
        ':full_name' => 'John Smith',
        ':party' => 'Progress Party',
        ':party_symbol' => '🌲'
    ]);
    
    // Insert second candidate
    $stmt = $db->prepare($insertQuery);
    $stmt->execute([
        ':election_id' => $electionId,
        ':full_name' => 'Jane Doe',
        ':party' => 'Unity Party',
        ':party_symbol' => '🤝'
    ]);
    
    // Insert third candidate
    $stmt = $db->prepare($insertQuery);
    $stmt->execute([
        ':election_id' => $electionId,
        ':full_name' => 'Bob Johnson',
        ':party' => 'Future Party',
        ':party_symbol' => '🚀'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => '3 candidates added successfully',
        'election_id' => $electionId,
        'candidates_added' => 3,
        'candidates' => [
            ['full_name' => 'John Smith', 'party' => 'Progress Party'],
            ['full_name' => 'Jane Doe', 'party' => 'Unity Party'],
            ['full_name' => 'Bob Johnson', 'party' => 'Future Party']
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
