<?php
/**
 * API pour enregistrer les préférences utilisateur
 * Ce fichier reçoit les données AJAX envoyées par la fonction savePreferences()
 * et les enregistre dans la table user_preferences de la base de données library
 * Les données sensibles sont chiffrées avant stockage
 */

// Inclure le fichier de configuration
require_once __DIR__ . '/../includes/config.php';

// Headers pour API REST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Vérifier que la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

try {
    // Récupérer et valider les données
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Un ID utilisateur valide est requis']);
        exit;
    }
    
    // Connexion à la base de données
    // Utiliser les paramètres de connexion définis dans config.php
    $conn = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']}", 
        $db_config['user'], 
        $db_config['password'],
        $db_config['options']
    );
    
    // Récupérer les données du formulaire
    $genres = isset($_POST['genres']) ? $_POST['genres'] : [];
    $authors = isset($_POST['authors']) ? $_POST['authors'] : '';
    $frequency = isset($_POST['frequency']) ? $_POST['frequency'] : '';
    
    // Traiter les genres (s'ils sont envoyés sous forme de tableau)
    if (is_array($genres)) {
        $genres_str = implode(',', $genres);
    } else {
        $genres_str = $genres;
    }
    
    // Chiffrer les valeurs sensibles avant stockage
    $encrypted_genres = encrypt_data($genres_str);
    $encrypted_authors = encrypt_data($authors);
    $encrypted_frequency = encrypt_data($frequency);
    
    // Vérifier si une préférence existe déjà pour cet utilisateur et ce type
    $stmt = $conn->prepare("SELECT id FROM user_preferences WHERE user_id = ? AND preference_type = ?");
    
    // Enregistrer les genres
    $stmt->execute([$user_id, 'genre']);
    $genre_pref = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($genre_pref) {
        // Mettre à jour
        $update = $conn->prepare("UPDATE user_preferences SET preference_value = ?, created_at = NOW(), is_encrypted = 1 WHERE id = ?");
        $update->execute([$encrypted_genres, $genre_pref['id']]);
    } else {
        // Insérer
        $insert = $conn->prepare("INSERT INTO user_preferences (user_id, preference_type, preference_value, created_at, is_encrypted) VALUES (?, ?, ?, NOW(), 1)");
        $insert->execute([$user_id, 'genre', $encrypted_genres]);
    }
    
    // Enregistrer les auteurs
    $stmt->execute([$user_id, 'author']);
    $author_pref = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($author_pref) {
        $update = $conn->prepare("UPDATE user_preferences SET preference_value = ?, created_at = NOW(), is_encrypted = 1 WHERE id = ?");
        $update->execute([$encrypted_authors, $author_pref['id']]);
    } else {
        $insert = $conn->prepare("INSERT INTO user_preferences (user_id, preference_type, preference_value, created_at, is_encrypted) VALUES (?, ?, ?, NOW(), 1)");
        $insert->execute([$user_id, 'author', $encrypted_authors]);
    }
    
    // Enregistrer la fréquence de lecture
    $stmt->execute([$user_id, 'frequency']);
    $freq_pref = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($freq_pref) {
        $update = $conn->prepare("UPDATE user_preferences SET preference_value = ?, created_at = NOW(), is_encrypted = 1 WHERE id = ?");
        $update->execute([$encrypted_frequency, $freq_pref['id']]);
    } else {
        $insert = $conn->prepare("INSERT INTO user_preferences (user_id, preference_type, preference_value, created_at, is_encrypted) VALUES (?, ?, ?, NOW(), 1)");
        $insert->execute([$user_id, 'frequency', $encrypted_frequency]);
    }
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Préférences enregistrées avec succès (chiffrées)',
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    // Gérer les erreurs
    http_response_code(500);
    echo json_encode([
        'error' => 'Une erreur est survenue lors de l\'enregistrement des préférences',
        'details' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : null
    ]);
}
?>