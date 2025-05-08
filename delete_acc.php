<?php
// Include required files
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'user.php';

// Initialize session
initSession();

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check if confirmation was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        header('Location: profile.php?error=csrf');
        exit;
    }
    
    // Get user ID from session
    $user_id = $_SESSION['user_id'];
    
    try {
        // Create user object
        $user = new User();
        
        // Delete the user account
        $result = $user->deleteAccount($user_id);
        
        if ($result) {
            // Clean up session data
            $_SESSION = [];
            
            // Delete session cookie if it exists
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy the session
            session_destroy();
            
            // Redirect to register page with success message
            header('Location: register.php?message=account_deleted');
            exit;
        } else {
            // Deletion failed
            header('Location: profile.php?error=delete_failed');
            exit;
        }
    } catch (Exception $e) {
        // Log error (in production, you'd want to log this to a file)
        // error_log('Delete account error: ' . $e->getMessage());
        
        // Redirect with error
        header('Location: profile.php?error=system');
        exit;
    }
} else {
    // If not POST request, redirect to profile
    header('Location: profile.php');
    exit;
}
?>