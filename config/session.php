<?php
// Cette fonction doit être appelée au début de chaque page nécessitant une session
function initSession() {
    // Configuration sécurisée des sessions
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // En production, utilisez HTTPS et activez secure cookies
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Anti-fixation de session
    session_start();
    
    // Régénérer l'ID de session toutes les 30 minutes
    if (!isset($_SESSION['last_regeneration']) || 
        time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fonction pour vérifier si l'utilisateur a un rôle spécifique
function hasRole($role) {
    if (!isLoggedIn() || !isset($_SESSION['user_roles'])) {
        return false;
    }
    
    return in_array($role, $_SESSION['user_roles']);
}

// Fonction pour rediriger si l'utilisateur n'est pas connecté
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Fonction pour rediriger si l'utilisateur n'a pas le rôle requis
function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role)) {
        header('Location: unauthorized.php');
        exit;
    }
}

// Fonction pour sécuriser les sorties (protection contre les XSS)
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Fonction pour générer un jeton CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier un jeton CSRF
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF token invalide");
    }
    
    return true;
}