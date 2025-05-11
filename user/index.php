<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$db = 'library';
$user = 'root';
$password = ''; // Default for XAMPP

$conn = new mysqli($host, $user, $password, $db);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Get user information
$sql_user = "SELECT  first_name, last_name 
             FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Home - Library</title>
    <link rel="stylesheet" href="style1.css">
</head>
<body>
    <header>
        <nav>
            <ul>
                <li><a href="recherche_livres.php" class="btn">Search Books</a></li>
                <li><a href="profil.php">My Account</a></li>
                <li><a href="emprunts.php">My borrowed books</a></li>
                <li><a href="user_liste_livres.php">catalogue</a></li>
                <li><a href="indexrecom.html">Recommendations</a></li>
                <li><a href="about.php">About us</a></li>
                <li><a href="faq.html">help</a></li>
                <li><a href="logout.php" class="btn">Logout</a></li>
            </ul>
        </nav>
    </header>

    <section class="hero">
        <h1>Welcome, <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
        <p>Explore our vast collection and manage your account easily.</p>
    </section>

    

    <footer>
        <p>&copy; 2025 Online Library System</p>
    </footer>
</body>
</html>
