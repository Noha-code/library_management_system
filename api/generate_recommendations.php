<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../recommandations.php';

header('Content-Type: application/json');

try {
    // Récupérer l'ID utilisateur
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = (int)($input['user_id'] ?? 0);

    if ($user_id <= 0) {
        throw new Exception("ID utilisateur invalide");
    }

    // Initialiser le système de recommandation
    $recommender = new RecommendationSystem($db_config);
    
    // Générer les recommandations
    $recommendations = $recommender->getRecommendations($user_id, 10);
    
    // Formater la réponse
    echo json_encode([
        'success' => true,
        'books' => $recommendations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}