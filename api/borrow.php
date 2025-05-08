<?php
/**
 * API pour gérer les emprunts de livres
 * Endpoints:
 * - GET: Obtenir les emprunts d'un utilisateur
 * - POST: Emprunter un livre
 * - PUT: Retourner un livre emprunté
 */

// Inclure le fichier de configuration
require_once '../config.php';

// Headers pour API REST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Obtenir la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Établir la connexion à la base de données
$conn = connect_db($db_config);

/**
 * Fonction sécurisée pour mettre à jour le système de recommandation
 * @param int $user_id ID de l'utilisateur
 * @return bool Succès ou échec de l'opération
 */
function updateRecommendationModel(int $user_id): bool {
    try {
        global $db_config;
        
        // Validation stricte de l'ID utilisateur
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        if ($user_id === false || $user_id <= 0) {
            error_log("Tentative de mise à jour du modèle avec un ID utilisateur invalide");
            return false;
        }
        
        $recommender = new RecommendationSystem($db_config);
        
        // Vérifier que les chemins sont accessibles via des méthodes
if (!method_exists($recommender, 'getPythonPath') || !method_exists($recommender, 'getScriptPath') ||
empty($recommender->getPythonPath()) || empty($recommender->getScriptPath())) {
error_log("Chemins Python ou script non accessibles dans le système de recommandation");
return false;
}

// Échapper correctement les arguments pour éviter les injections de commandes
$pythonPath = escapeshellcmd($recommender->getPythonPath());
$scriptPath = escapeshellcmd($recommender->getScriptPath());
$escapedUserId = escapeshellarg($user_id);

        // Construction sécurisée de la commande
        $command = "{$pythonPath} {$scriptPath} --action=update_model --user_id={$escapedUserId}";
        
        // Exécution en arrière-plan pour ne pas bloquer l'API
        if (PHP_OS === 'WINNT') {
            pclose(popen("start /B {$command}", "r"));
        } else {
            exec("{$command} > /dev/null 2>&1 &");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour du modèle: " . $e->getMessage());
        return false;
    }
}

/**
 * Fonction utilitaire pour répondre avec une erreur
 * @param int $code Code HTTP
 * @param string $message Message d'erreur
 */
function respondWithError(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Fonction pour valider un ID
 * @param mixed $id Valeur à valider
 * @return int ID validé
 */
function validateId($id): int {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        respondWithError(400, "L'identifiant fourni est invalide");
    }
    return $id;
}

/**
 * Sanitize et valide les entrées JSON
 * @return array Données d'entrée validées
 */
function getJsonInput(): array {
    $json = file_get_contents('php://input');
    if (empty($json)) {
        respondWithError(400, "Aucune donnée fournie");
    }
    
    $input = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respondWithError(400, "Format JSON invalide: " . json_last_error_msg());
    }
    
    return $input;
}

// Traitement selon la méthode HTTP
try {
    switch ($method) {
        case 'GET':
            // Récupérer les emprunts d'un utilisateur
            if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
                respondWithError(400, "L'ID utilisateur est requis");
            }
            
            $user_id = validateId($_GET['user_id']);
            $status = isset($_GET['status']) ? filter_var($_GET['status'], FILTER_SANITIZE_STRING) : null;
            
            // Validation du statut si fourni
            if ($status && !in_array($status, ['active', 'returned'])) {
                respondWithError(400, "Statut invalide. Valeurs acceptées: 'active', 'returned'");
            }
            
            $query = "
                SELECT e.id, e.livre_id, e.user_id, e.date_emprunt, e.date_retour_prevue, 
                       e.date_retour_reelle, e.statut,
                       l.titre, l.auteur, l.annee_parution, l.image, c.nom_categorie, g.nom_genre
                FROM emprunts e
                JOIN livres l ON e.livre_id = l.id_livre
                LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
                LEFT JOIN genre g ON l.id_genre = g.id_genre
                WHERE e.user_id = :user_id
            ";
            
            if ($status) {
                $query .= " AND e.statut = :status";
            }
            
            $query .= " ORDER BY e.date_emprunt DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($status) {
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            $emprunts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'user_id' => $user_id,
                'count' => count($emprunts),
                'emprunts' => $emprunts
            ]);
            break;
            
        case 'POST':
            // Emprunter un livre
            $input = getJsonInput();
            
            if (!isset($input['user_id']) || !isset($input['livre_id'])) {
                respondWithError(400, "L'ID utilisateur et l'ID livre sont requis");
            }
            
            $user_id = validateId($input['user_id']);
            $livre_id = validateId($input['livre_id']);
            
            // Vérifier si le livre est disponible dans une transaction
            $conn->beginTransaction();
            
            try {
                // Vérifier si le livre est disponible avec un verrou pour éviter les conditions de concurrence
                $check_query = "
                    SELECT COUNT(*) as count 
                    FROM emprunts 
                    WHERE livre_id = :livre_id AND statut = 'active'
                    FOR UPDATE
                ";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bindParam(':livre_id', $livre_id, PDO::PARAM_INT);
                $check_stmt->execute();
                $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $conn->rollBack();
                    respondWithError(400, "Ce livre est déjà emprunté");
                }
                
                // Calculer la date de retour prévue (par défaut 2 semaines)
                $duree_pret = isset($input['duree_pret']) ? 
                              filter_var($input['duree_pret'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]) : 14;
                
                if ($duree_pret === false) {
                    $conn->rollBack();
                    respondWithError(400, "La durée du prêt doit être entre 1 et 60 jours");
                }
                
                $date_emprunt = date('Y-m-d H:i:s');
                $date_retour_prevue = date('Y-m-d H:i:s', strtotime($date_emprunt . " + {$duree_pret} days"));
                
                // Enregistrer l'emprunt
                $insert_query = "
                    INSERT INTO emprunts (user_id, livre_id, date_emprunt, date_retour_prevue, statut)
                    VALUES (:user_id, :livre_id, :date_emprunt, :date_retour_prevue, 'active')
                ";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':livre_id', $livre_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':date_emprunt', $date_emprunt);
                $insert_stmt->bindParam(':date_retour_prevue', $date_retour_prevue);
                
                if (!$insert_stmt->execute()) {
                    $conn->rollBack();
                    respondWithError(500, "Erreur lors de l'enregistrement de l'emprunt");
                }
                
                $emprunt_id = $conn->lastInsertId();
                
                // Récupérer les détails du livre emprunté
                $book_query = "
                    SELECT l.id_livre, l.titre, l.auteur, l.annee_parution, l.image,
                           c.nom_categorie, g.nom_genre
                    FROM livres l
                    LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
                    LEFT JOIN genre g ON l.id_genre = g.id_genre
                    WHERE l.id_livre = :livre_id
                ";
                $book_stmt = $conn->prepare($book_query);
                $book_stmt->bindParam(':livre_id', $livre_id, PDO::PARAM_INT);
                $book_stmt->execute();
                $book = $book_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$book) {
                    $conn->rollBack();
                    respondWithError(404, "Livre non trouvé");
                }
                
                $conn->commit();
                
                // Mettre à jour le modèle de recommandation en arrière-plan
                updateRecommendationModel($user_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Livre emprunté avec succès',
                    'emprunt_id' => $emprunt_id,
                    'details' => [
                        'user_id' => $user_id,
                        'livre' => $book,
                        'date_emprunt' => $date_emprunt,
                        'date_retour_prevue' => $date_retour_prevue
                    ]
                ]);
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Erreur PDO lors de l'emprunt: " . $e->getMessage());
                respondWithError(500, "Une erreur est survenue lors de l'emprunt");
            }
            break;
            
        case 'PUT':
            // Retourner un livre
            $input = getJsonInput();
            
            if (!isset($input['emprunt_id'])) {
                respondWithError(400, "L'ID de l'emprunt est requis");
            }
            
            $emprunt_id = validateId($input['emprunt_id']);
            $date_retour = date('Y-m-d H:i:s');
            
            // Transaction pour s'assurer que toute l'opération est atomique
            $conn->beginTransaction();
            
            try {
                // Vérifier si l'emprunt existe et est actif
                $check_query = "
                    SELECT user_id, livre_id, statut FROM emprunts WHERE id = :emprunt_id FOR UPDATE
                ";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bindParam(':emprunt_id', $emprunt_id, PDO::PARAM_INT);
                $check_stmt->execute();
                $emprunt = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$emprunt) {
                    $conn->rollBack();
                    respondWithError(404, "Emprunt non trouvé");
                }
                
                if ($emprunt['statut'] !== 'active') {
                    $conn->rollBack();
                    respondWithError(400, "Ce livre a déjà été retourné");
                }
                
                // Mettre à jour l'emprunt
                $update_query = "
                    UPDATE emprunts 
                    SET date_retour_reelle = :date_retour, statut = 'returned' 
                    WHERE id = :emprunt_id
                ";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':date_retour', $date_retour);
                $update_stmt->bindParam(':emprunt_id', $emprunt_id, PDO::PARAM_INT);
                
                if (!$update_stmt->execute()) {
                    $conn->rollBack();
                    respondWithError(500, "Erreur lors de l'enregistrement du retour");
                }
                
                $conn->commit();
                
                // Mettre à jour le modèle de recommandation en arrière-plan
                updateRecommendationModel($emprunt['user_id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Livre retourné avec succès',
                    'details' => [
                        'emprunt_id' => $emprunt_id,
                        'user_id' => $emprunt['user_id'],
                        'livre_id' => $emprunt['livre_id'],
                        'date_retour' => $date_retour
                    ]
                ]);
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Erreur PDO lors du retour: " . $e->getMessage());
                respondWithError(500, "Une erreur est survenue lors du retour du livre");
            }
            break;
            
        default:
            respondWithError(405, "Méthode non autorisée");
            break;
    }
} catch (Exception $e) {
    // Log l'erreur et renvoie une réponse générique pour éviter de divulguer des informations sensibles
    error_log("Erreur dans l'API d'emprunt: " . $e->getMessage());
    respondWithError(500, "Une erreur interne est survenue");
}
?>