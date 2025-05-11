<?php
// Inclure les fichiers nécessaires
require_once 'config/session.php'; // Ajustez le chemin selon l'emplacement réel du fichier
require_once 'connexion.php';

// Initialiser la session de manière cohérente (cette fonction contient déjà session_start())
initSession();

// Vérifier si l'utilisateur est connecté avec la fonction du fichier session.php
if (!isLoggedIn()) {
    // Rediriger vers la page de connexion avec un message
    $_SESSION['error'] = "Veuillez vous connecter pour réserver un livre.";
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Variable pour suivre si une transaction est active
$transaction_active = false;

try {
    // Récupérer l'ID utilisateur de manière cohérente
    $id_utilisateur = $_SESSION['user_id']; // Utiliser la même clé que dans le reste de l'application
    
    $pdo = getDBConnection(); // Connexion sécurisée
    
    // Vérifier si l'ID du livre est bien transmis et valide
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("Erreur : ID de livre invalide.");
    }
    
    $id_livre = intval($_GET['id']);
    
    // Commencer une transaction pour les opérations de SELECT FOR UPDATE
    $pdo->beginTransaction();
    $transaction_active = true;
    
    // Verrouiller la ligne du livre pour éviter une modification concurrente
    $stmt = $pdo->prepare("SELECT quantite FROM livres WHERE id_livre = ? FOR UPDATE");
    $stmt->execute([$id_livre]);
    $livre = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$livre) {
        throw new Exception("Erreur : Livre introuvable.");
    }
    
    if ($livre['quantite'] <= 0) {
        $pdo->commit();
        $transaction_active = false;
        header("Location: user_liste_livres.php?error=livre_indisponible");
        exit;
    }
    
    // Vérifier si l'utilisateur a déjà réservé ce livre
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE id_utilisateur = ? AND id_livre = ?");
    $stmt->execute([$id_utilisateur, $id_livre]);
    $dejaReserve = $stmt->fetchColumn();
    
    if ($dejaReserve > 0) {
        $pdo->commit();
        $transaction_active = false;
        header("Location: user_liste_livres.php?error=deja_reserve");
        exit;
    }
    
    // Vérifier si l'utilisateur a déjà 2 réservations actives
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE id_utilisateur = ? AND etat = 'active'");
    $stmt->execute([$id_utilisateur]);
    $reservationsEnCours = $stmt->fetchColumn();
    
    if ($reservationsEnCours >= 2) {
        $pdo->commit();
        $transaction_active = false;
        header("Location: user_liste_livres.php?error=max_reservations");
        exit;
    }
    
    // Insérer la réservation
    $stmt = $pdo->prepare("INSERT INTO reservations (id_utilisateur, id_livre, date_reservation, etat) VALUES (?, ?, NOW(), 'active')");
    if (!$stmt->execute([$id_utilisateur, $id_livre])) {
        throw new Exception("Erreur lors de l'insertion de la réservation.");
    }
    
    // Mettre à jour le stock du livre
    $stmt = $pdo->prepare("UPDATE livres SET quantite = quantite - 1 WHERE id_livre = ? AND quantite > 0");
    if (!$stmt->execute([$id_livre])) {
        throw new Exception("Erreur lors de la mise à jour du stock.");
    }
    
    $pdo->commit(); // Valider la transaction
    $transaction_active = false;
    
    // Redirection avec message de succès
    $_SESSION['message'] = "Réservation effectuée avec succès !";
    header("Location: user_liste_livres.php?success=reservation_ok");
    exit;
} catch (Exception $e) {
    // Annuler la transaction seulement si elle est active
    if ($transaction_active && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = "Une erreur est survenue : " . htmlspecialchars($e->getMessage());
    header("Location: user_liste_livres.php?error=transaction_fail");
    exit;
}
?>