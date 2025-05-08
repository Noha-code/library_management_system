<?php
// Paramètres de connexion à la base de données
$host = 'localhost';      // Généralement 'localhost' pour un serveur local
$dbname = 'library';      // Le nom de votre base de données tel que montré dans phpMyAdmin
$user = 'root'; // Votre nom d'utilisateur MySQL
$pass = '';   // Votre mot de passe MySQL
$charset = 'utf8mb4';     // Pour le support complet des caractères

try {
    // Création de la connexion PDO avec les options appropriées
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // En cas d'erreur, afficher un message et arrêter le script
    die('Erreur de connexion à la base de données : ' . $e->getMessage());
}
?>