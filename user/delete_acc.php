<?php
require_once 'connexion.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['id'];
$error_message = '';
$success_message = '';

// Traiter la suppression si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    
    // Vérifie si le mot de passe est fourni pour confirmation
    if (!isset($_POST['password']) || empty($_POST['password'])) {
        $error_message = "Veuillez entrer votre mot de passe pour confirmer la suppression.";
    } else {
        try {
            $pdo = getDBConnection();
            
            // Vérifier le mot de passe de l'utilisateur
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($_POST['password'], $user['password'])) {
                $error_message = "Mot de passe incorrect. La suppression du compte a échoué.";
            } else {
                // Vérifier si l'utilisateur a des emprunts en cours
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM emprunts WHERE utilisateur_id = ? AND date_retour IS NULL");
                $stmt->execute([$user_id]);
                $emprunts_en_cours = $stmt->fetchColumn();
                
                if ($emprunts_en_cours > 0) {
                    $error_message = "Vous avez des emprunts en cours. Veuillez les retourner avant de supprimer votre compte.";
                } else {
                    // Début de la transaction pour assurer l'intégrité des données
                    $pdo->beginTransaction();
                    
                    try {
                        // Supprimer les préférences utilisateur
                        $stmt = $pdo->prepare("DELETE FROM user_preferences WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Supprimer l'historique de recherche
                        $stmt = $pdo->prepare("DELETE FROM search_history WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Supprimer les réservations actives
                        $stmt = $pdo->prepare("DELETE FROM reservations WHERE utilisateur_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Supprimer les tentatives de connexion
                        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Supprimer les rôles utilisateur
                        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Anonymiser les emprunts passés au lieu de les supprimer (conservation des données)
                        $stmt = $pdo->prepare("UPDATE emprunts SET utilisateur_id = 0 WHERE utilisateur_id = ? AND date_retour IS NOT NULL");
                        $stmt->execute([$user_id]);
                        
                        // Enfin, supprimer l'utilisateur
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Valider la transaction
                        $pdo->commit();
                        
                        // Déconnexion de l'utilisateur
                        session_unset();
                        session_destroy();
                        
                        // Rediriger vers la page d'accueil avec message de succès
                        header("Location: index.php?message=account_deleted");
                        exit();
                        
                    } catch (Exception $e) {
                        // Annuler la transaction en cas d'erreur
                        $pdo->rollBack();
                        $error_message = "Une erreur est survenue lors de la suppression du compte: " . $e->getMessage();
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppression de compte</title>
    <link rel="stylesheet" href="style1.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include('header.php'); ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h2 class="text-center">Supprimer mon compte</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                        <?php endif; ?>
                        
                        <div class="alert alert-warning">
                            <strong>Attention !</strong> Cette action est irréversible et entraînera la suppression définitive de votre compte.
                        </div>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="password" class="form-label">Entrez votre mot de passe pour confirmer</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="confirm_delete" class="btn btn-danger">Confirmer la suppression</button>
                                <a href="profil.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('footer.php'); ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>