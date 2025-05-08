<?php
/**
 * API pour gérer les préférences utilisateur
 * Endpoints:
 * - GET: Récupérer les préférences d'un utilisateur et les données du formulaire
 * - POST: Enregistrer les préférences d'un utilisateur
 */

// Inclure le fichier de configuration
require_once __DIR__ . '/../includes/config.php';

// Headers pour API REST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Traitement selon la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Initialiser le système de recommandation
    $recommender = new RecommendationSystem($db_config);

    switch ($method) {
        case 'GET':
            // Validation de l'input
            if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Un ID utilisateur valide est requis']);
                exit;
            }

            $user_id = (int)$_GET['user_id'];
            
            // Récupérer les données
            $form_data = $recommender->getPreferenceFormData();
            $user_preferences = $recommender->getUserPreferences($user_id);
            
            // Réponse
            echo json_encode([
                'success' => true,
                'data' => [
                    'form_data' => $form_data,
                    'user_preferences' => $user_preferences
                ],
                'timestamp' => time()
            ]);
            break;
            
        case 'POST':
            // Lire et valider l'input JSON
            $json = file_get_contents('php://input');
            $input = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'Données JSON invalides']);
                exit;
            }
            
            if (!isset($input['user_id']) || !is_numeric($input['user_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Un ID utilisateur valide est requis']);
                exit;
            }
            
            $user_id = (int)$input['user_id'];
            
            // Valider les préférences
            $preferences = [
                'genres' => is_array($input['genres'] ?? null) ? $input['genres'] : [],
                'categories' => is_array($input['categories'] ?? null) ? $input['categories'] : [],
                'authors' => is_array($input['authors'] ?? null) ? $input['authors'] : []
            ];
            
            // Enregistrement
            $success = $recommender->saveUserPreferences($user_id, $preferences);
            
            if ($success) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Préférences enregistrées',
                    'timestamp' => time()
                ]);
            } else {
                throw new RuntimeException('Échec de l\'enregistrement');
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur serveur',
        'details' => DEBUG_MODE ? $e->getMessage() : null
    ]);
}