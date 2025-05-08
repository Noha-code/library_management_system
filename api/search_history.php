<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $conn = connect_db($db_config);
    
    if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
        throw new Exception("ID utilisateur invalide");
    }

    $user_id = (int)$_GET['user_id'];
    
    $stmt = $conn->prepare("
        SELECT search_query, search_date 
        FROM search_history 
        WHERE user_id = :user_id 
        ORDER BY search_date DESC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}