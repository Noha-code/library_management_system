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
$sql_user = "SELECT username, email, first_name, last_name, address, phone, created_at, last_login, status 
             FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get borrowing history
$sql_borrowings = "SELECT e.id, e.date_emprunt, e.date_retour, e.date_limite, 
                  l.titre, l.auteur, l.id_livre
                  FROM emprunts e
                  JOIN livres l ON e.livre_id = l.id_livre
                  WHERE e.utilisateur_id = ?
                  ORDER BY e.date_emprunt DESC";
$stmt = $conn->prepare($sql_borrowings);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrowings = $stmt->get_result();

// Get active reservations
$sql_reservations = "SELECT r.id, r.date_reservation, r.statut,
                    l.titre, l.auteur, l.id_livre
                    FROM reservations r
                    JOIN livres l ON r.livre_id = l.id_livre
                    WHERE r.utilisateur_id = ? AND r.statut = 'active'
                    ORDER BY r.date_reservation DESC";
$stmt = $conn->prepare($sql_reservations);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservations = $stmt->get_result();

$conn->close();

// Helper function to determine borrowing status
function getBorrowingStatus($date_retour, $date_limite) {
    if ($date_retour) {
        return "Returned on " . date('M d, Y', strtotime($date_retour));
    } else {
        $today = new DateTime();
        $limit = new DateTime($date_limite);
        
        if ($today > $limit) {
            return "Overdue - Due on " . date('M d, Y', strtotime($date_limite));
        } else {
            return "Due on " . date('M d, Y', strtotime($date_limite));
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile - Library</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .profile-container {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .profile-info {
            flex: 1;
            background-color: #f5f5f5;
            padding: 1.5rem;
            border-radius: 8px;
        }
        .borrow-history {
            flex: 2;
        }
        .book-item {
            border-bottom: 1px solid #eee;
            padding: 0.8rem 0;
        }
        .book-status {
            font-size: 0.9rem;
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            margin-left: 0.5rem;
        }
        .active {
            background-color: #d4edda;
            color: #155724;
        }
        .returned {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        .reserved {
            background-color: #fff3cd;
            color: #856404;
        }
        .tab-container {
            margin: 1rem 0;
        }
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .tab-button {
            padding: 0.5rem 1rem;
            background-color: #e9ecef;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .tab-button.active {
            background-color: #007bff;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li><a href="index.php" class="btn">Home</a></li>
                <li><a href="recherche_livres.php" class="btn">Browse Books</a></li>
                <li><a href="logout.php" class="btn">Logout</a></li>
            </ul>
        </nav>
    </header>

    <section class="hero">
        <h1>Welcome, <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
    </section>

    <div class="container">
        <div class="profile-container">
            <div class="profile-info">
                <h2>Profile Information</h2>
                <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong>Name:</strong> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
                <?php if ($user['address']): ?>
                    <p><strong>Address:</strong> <?= htmlspecialchars($user['address']) ?></p>
                <?php endif; ?>
                <?php if ($user['phone']): ?>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone']) ?></p>
                <?php endif; ?>
                <p><strong>Member since:</strong> <?= date('F d, Y', strtotime($user['created_at'])) ?></p>
                <p><strong>Account status:</strong> 
                    <span class="book-status <?= strtolower($user['status']) ?>">
                        <?= ucfirst(htmlspecialchars($user['status'])) ?>
                    </span>
                </p>
                <?php if ($user['last_login']): ?>
                    <p><strong>Last login:</strong> <?= date('F d, Y H:i', strtotime($user['last_login'])) ?></p>
                <?php endif; ?>
                <p> <a href="edit_profile.php" class="btn" ">Edit Profile</a></p>
                <p> <a  class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir accéder à la page de suppression de compte?');" href="delete_acc.php">Delete your account</a></p>
            </div>

            <div class="borrow-history">
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-button active" onclick="openTab(event, 'borrowings')">Borrowing History</button>
                        <button class="tab-button" onclick="openTab(event, 'reservations')">Active Reservations</button>
                    </div>

                    <div id="borrowings" class="tab-content active">
                        <h2>Borrowing History</h2>
                        <?php if ($borrowings->num_rows > 0): ?>
                            <ul class="book-list">
                                <?php while ($book = $borrowings->fetch_assoc()): 
                                    $status = getBorrowingStatus($book['date_retour'], $book['date_limite']);
                                    $status_class = strpos($status, 'Returned') !== false ? 'returned' : 
                                                  (strpos($status, 'Overdue') !== false ? 'overdue' : 'active');
                                ?>
                                    <li class="book-item">
                                        <div>
                                            <strong><?= htmlspecialchars($book['titre']) ?></strong> 
                                            by <?= htmlspecialchars($book['auteur']) ?>
                                            <span class="book-status <?= $status_class ?>"><?= $status ?></span>
                                        </div>
                                        <div>Borrowed on: <?= date('M d, Y', strtotime($book['date_emprunt'])) ?></div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p>You haven't borrowed any books yet.</p>
                        <?php endif; ?>
                    </div>

                    <div id="reservations" class="tab-content">
                        <h2>Active Reservations</h2>
                        <?php if ($reservations->num_rows > 0): ?>
                            <ul class="book-list">
                                <?php while ($reservation = $reservations->fetch_assoc()): ?>
                                    <li class="book-item">
                                        <div>
                                            <strong><?= htmlspecialchars($reservation['titre']) ?></strong> 
                                            by <?= htmlspecialchars($reservation['auteur']) ?>
                                            <span class="book-status reserved">Reserved</span>
                                        </div>
                                        <div>Reserved on: <?= date('M d, Y', strtotime($reservation['date_reservation'])) ?></div>
                                        <div>
                                            <a href="cancel_reservation.php?id=<?= $reservation['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to cancel this reservation?');" 
                                               class="btn">Cancel Reservation</a>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p>You don't have any active reservations.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Online Library System</p>
    </footer>

    <script>
        function openTab(evt, tabName) {
            // Hide all tab content
            var tabcontent = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }

            // Remove "active" class from all tab buttons
            var tabbuttons = document.getElementsByClassName("tab-button");
            for (var i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].classList.remove("active");
            }

            // Show the specific tab content and add "active" class to the button
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>