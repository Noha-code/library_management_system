<?php
// Connexion à la base de données
$conn = new mysqli("localhost", "biblio_user", "password", "library");

// Vérifier la connexion
if ($conn->connect_error) {
    die(json_encode(["error" => "Connexion échouée: " . $conn->connect_error]));
}

// Récupérer l'ID du livre
$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;

if ($book_id > 0) {
    // Préparer et exécuter la requête
    $stmt = $conn->prepare("SELECT description FROM livres WHERE id_livre = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->bind_result($description);
    
    if ($stmt->fetch()) {
        // Retourner la description
        echo json_encode(["description" => $description]);
    } else {
        echo json_encode(["description" => "Description non disponible"]);
    }
    
    $stmt->close();
} else {
    echo json_encode(["error" => "ID de livre non valide"]);
}

$conn->close();
?>