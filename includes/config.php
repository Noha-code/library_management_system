<?php
// config.php - Fichier de configuration central sécurisé

// 1. Protection contre l'accès direct
if (!defined('IN_APP')) {
    define('IN_APP', true);
}

// 2. Configuration de la base de données
$db_config = [
    'host' => '127.0.0.1', // Plus sécurisé que 'localhost'
    'user' => 'biblio_user', // Utilisateur dédié
    'password' => 'votre_mot_de_passe_complexe', // À changer en production
    'dbname' => 'library',
    'port' => 3306,
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]
];

// 3. Chemins absolus
define('APP_ROOT', dirname(__DIR__));
define('PYTHON_PATH', '/usr/bin/python3'); // Vérifiez le chemin avec 'which python3'
define('RECOMMENDATION_SCRIPT', APP_ROOT.'/recomman_surprise.py');

// 4. Sécurité
define('API_SECRET', 'votre_cle_secrete_'.date('Ym')); // Change mensuellement

// 5. Clés de chiffrement pour les données utilisateur
// ATTENTION: En production, utilisez une méthode sécurisée pour gérer ces clés
define('ENCRYPTION_KEY', hash('sha256', 'votre_clé_secrète_très_complexe')); // Clé principale
define('ENCRYPTION_IV', substr(hash('sha256', 'iv_personnalisé_sécurisé'), 0, 16)); // Vecteur d'initialisation
define('ENCRYPTION_METHOD', 'AES-256-CBC'); // Méthode de chiffrement sécurisée

// 6. Debug
define('DEBUG_MODE', true); // false en production
error_reporting(DEBUG_MODE ? E_ALL : 0);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');

// 7. Inclusion des dépendances
require_once __DIR__.'/recommandations.php'; // Charge la classe RecommendationSystem fusionnée

// 8. Fonctions utilitaires pour le chiffrement
/**
 * Chiffre une chaîne de caractères
 * @param string $data Données à chiffrer
 * @return string Données chiffrées (encodées en base64)
 */
function encrypt_data($data) {
    if (empty($data)) return '';
    $encrypted = openssl_encrypt(
        $data,
        ENCRYPTION_METHOD,
        ENCRYPTION_KEY,
        0,
        ENCRYPTION_IV
    );
    return $encrypted;
}

/**
 * Déchiffre une chaîne de caractères
 * @param string $data Données chiffrées (encodées en base64)
 * @return string Données déchiffrées
 */
function decrypt_data($data) {
    if (empty($data)) return '';
    $decrypted = openssl_decrypt(
        $data,
        ENCRYPTION_METHOD,
        ENCRYPTION_KEY,
        0,
        ENCRYPTION_IV
    );
    return $decrypted;
}

// 9. Vérification finale (optionnel)
if (!class_exists('RecommendationSystem')) {
    die('Erreur: La classe RecommendationSystem n\'a pas été chargée correctement');
}