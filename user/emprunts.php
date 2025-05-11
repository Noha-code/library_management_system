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
$sql_user = "SELECT first_name, last_name 
             FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user's borrowing history - FIXED QUERY
$sql_emprunts = "SELECT e.id, e.date_emprunt, e.date_retour, e.date_limite, 
                l.titre, l.auteur, l.image 
                FROM emprunts e
                JOIN livres l ON e.livre_id = l.id_livre
                WHERE e.utilisateur_id = ?
                ORDER BY e.date_emprunt DESC";

$stmt = $conn->prepare($sql_emprunts);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_emprunts = $stmt->get_result();

// Count active loans (where date_retour is NULL)
$sql_active = "SELECT COUNT(*) as active_count 
               FROM emprunts 
               WHERE utilisateur_id = ? AND date_retour IS NULL";
$stmt = $conn->prepare($sql_active);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_result = $stmt->get_result();
$active_count = $active_result->fetch_assoc()['active_count'];

// Check for overdue books
$sql_overdue = "SELECT COUNT(*) as overdue_count 
                FROM emprunts 
                WHERE utilisateur_id = ? 
                AND date_retour IS NULL 
                AND date_limite < CURDATE()";
$stmt = $conn->prepare($sql_overdue);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$overdue_result = $stmt->get_result();
$overdue_count = $overdue_result->fetch_assoc()['overdue_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowed Books - Library</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .loan-history {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #f5f5f5;
            border-radius: 8px;
            padding: 20px;
            flex: 1;
            margin: 0 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-card.warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .book-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .book-image {
            height: 200px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
        }
        
        .book-image img {
            max-height: 180px;
            max-width: 80%;
            object-fit: contain;
        }
        
        .book-info {
            padding: 15px;
        }
        
        .book-info h3 {
            margin-top: 0;
            font-size: 18px;
        }
        
        .book-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status.returned {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status.active {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status.overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            background-color: #f1f1f1;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
        }
        
        .tab.active {
            background-color: #4CAF50;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state p {
            margin-bottom: 20px;
        }
        
        .cta-button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .cta-button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <ul>
                <li><a href="recherche_livres.php" class="btn">Search Books</a></li>
                <li><a href="index.php">Home</a></li>
                <li><a href="profil.php">My Account</a></li>
                <li><a href="user_liste_livres.php">Catalogue</a></li>
                <li><a href="indexrecom.html">Recommendations</a></li>
                <li><a href="/about.php">About us</a></li>
                <li><a href="faq.html">Help</a></li>
                <li><a href="logout.php" class="btn">Logout</a></li>
            </ul>
        </nav>
    </header>

    <section class="hero" style="padding: 40px 0; background-color: #f9f9f9;">
        <h1>My Borrowed Books</h1>
        <p>Track your current and past book loans</p>
    </section>

    <section class="loan-history">
        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Borrowed Books</h3>
                <p style="font-size: 32px; font-weight: bold;"><?= $result_emprunts->num_rows ?></p>
            </div>
            <div class="stat-card">
                <h3>Currently Borrowed</h3>
                <p style="font-size: 32px; font-weight: bold;"><?= $active_count ?></p>
            </div>
            <?php if($overdue_count > 0): ?>
            <div class="stat-card warning">
                <h3>Overdue Books</h3>
                <p style="font-size: 32px; font-weight: bold;"><?= $overdue_count ?></p>
                <p>Please return these books as soon as possible</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="showTab('all')">All Loans</div>
            <div class="tab" onclick="showTab('active')">Active Loans</div>
            <div class="tab" onclick="showTab('returned')">Returned Books</div>
            <?php if($overdue_count > 0): ?>
            <div class="tab" onclick="showTab('overdue')">Overdue</div>
            <?php endif; ?>
        </div>

        <?php if ($result_emprunts->num_rows > 0): ?>
            <div class="books-grid" id="all-loans">
                <?php while ($emprunt = $result_emprunts->fetch_assoc()): ?>
                    <?php 
                        $is_returned = !empty($emprunt['date_retour']);
                        $is_overdue = !$is_returned && strtotime($emprunt['date_limite']) < time();
                        
                        if ($is_returned) {
                            $status_class = "returned";
                            $status_text = "Returned on " . date('M d, Y', strtotime($emprunt['date_retour']));
                        } elseif ($is_overdue) {
                            $status_class = "overdue";
                            $status_text = "Overdue since " . date('M d, Y', strtotime($emprunt['date_limite']));
                        } else {
                            $status_class = "active";
                            $status_text = "Due by " . date('M d, Y', strtotime($emprunt['date_limite']));
                        }
                    ?>
                    <div class="book-card <?= $status_class ?>" 
                         data-status="<?= $is_returned ? 'returned' : ($is_overdue ? 'overdue' : 'active') ?>">
                        <div class="book-image">
                            <?php if (!empty($emprunt['image'])): ?>
                                <img src="<?= htmlspecialchars($emprunt['image']) ?>" alt="<?= htmlspecialchars($emprunt['titre']) ?>">
                            <?php else: ?>
                                <div style="width: 120px; height: 180px; background-color: #e0e0e0; display: flex; align-items: center; justify-content: center;">
                                    <span>No Image</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="book-info">
                            <h3><?= htmlspecialchars($emprunt['titre']) ?></h3>
                            <p>By <?= htmlspecialchars($emprunt['auteur']) ?></p>
                            <div class="book-meta">
                                <span>Borrowed: <?= date('M d, Y', strtotime($emprunt['date_emprunt'])) ?></span>
                                <span class="status <?= $status_class ?>"><?= $status_text ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h2>You haven't borrowed any books yet</h2>
                <p>Explore our collection and find your next great read!</p>
                <a href="recherche_livres.php" class="cta-button">Browse Books</a>
            </div>
        <?php endif; ?>
    </section>

    <footer>
        <p>&copy; 2025 Online Library System</p>
    </footer>

    <script>
        function showTab(tabName) {
            // Update active tab styling
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter books based on selected tab
            const books = document.querySelectorAll('.book-card');
            books.forEach(book => {
                if (tabName === 'all' || book.dataset.status === tabName) {
                    book.style.display = 'block';
                } else {
                    book.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>