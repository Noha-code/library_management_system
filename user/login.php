<?php
// Enable error reporting for development (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'user.php';

// Initialize session properly - CORRECTION: N'utiliser qu'une seule méthode
initSession(); // Cette fonction contient déjà session_start()

// Redirect if already logged in
if (isLoggedIn()) {
    // Redirect based on role
    if (in_array('admin', $_SESSION['user_roles'])) {
        header('Location: admin/index.php');
    } elseif (in_array('librarian', $_SESSION['user_roles'])) {
        header('Location: librarian/index.php');
    } else {
        header('Location: user/index.php');
    }
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security error, please try again.";
    } else {
        // Get and sanitize input
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate inputs
        if (empty($identity)) {
            $errors[] = "Please enter your email or username.";
        }

        if (empty($password)) {
            $errors[] = "Please enter your password.";
        }

        // Attempt login
        if (empty($errors)) {
            try {
                $user = new User();
                $result = $user->login($identity, $password);

                if (is_array($result) && !empty($result['success']) && isset($result['user_id'])) {
                    // CORRECTION: Utiliser une seule clé cohérente pour l'ID utilisateur
                    $_SESSION['user_id'] = intval($result['user_id']); // S'assurer que c'est bien un entier
                    
                    // Vérifier que l'ID est bien enregistré en session
                    if (!isset($_SESSION['user_id'])) {
                        die("Erreur : L'ID utilisateur n'est pas enregistré en session.");
                    }
                    
                    // Check user roles and redirect accordingly
                    if (in_array('admin', $_SESSION['user_roles'])) {
                        header('Location: admin/index.php');
                    } elseif (in_array('librarian', $_SESSION['user_roles'])) {
                        header('Location: librarian/index.php');
                    } else {
                        // Default to user role
                        header('Location: user/index.php');
                    }
                    exit;
                } else {
                    $errors[] = $result['message'] ?? "Login information is incorrect.";
                }
            } catch (Exception $e) {
                $errors[] = "An error occurred. Please try again later.";
                // You could log this: error_log($e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Library Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&family=Libre+Baskerville:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary: #512b58;
            --primary-hover: #3a1f40;
            --accent: #f4b083;
            --accent-hover: #f09c6b;
            --dark: #2c2c2c;
            --light: #f8f9fa;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            margin: 0;
            --main-font: 'Nunito', 'Libre Baskerville', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            font-family: var(--main-font);
            background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), 
                              url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            padding-top: 0;
            padding-bottom: 40px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        nav {
            background-color: #3a1f40;
            padding: 15px 20px;
            box-shadow: 0 2px 10px #3a1f40;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        nav ul {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0;
            padding: 0;
            max-width: 1200px;
            margin: 0 auto;
        }

        nav ul li {
            margin: 0 15px;
            transition: var(--transition);
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
            padding: 8px 0;
            position: relative;
        }
        
        nav ul li a:after {
            content: "";
            position: absolute;
            width: 0;
            height: 2px;
            background: var(--accent);
            bottom: 0;
            left: 0;
            transition: var(--transition);
        }
        
        nav ul li a:hover:after {
            width: 100%;
        }

        .login-btn a {
            background-color: var(--accent);
            color: var(--dark);
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            transition: var(--transition);
            font-weight: 700;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
        }
        
        .login-btn a:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .login-btn a i {
            margin-right: 8px;
        }
        
        .container {
            flex: 1;
            display: flex;
            align-items: center;
        }
        
        .form-container {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 40px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary);
            font-weight: 700;
            font-size: 2rem;
            position: relative;
        }
        
        .form-title:after {
            content: "";
            position: absolute;
            width: 60px;
            height: 3px;
            background-color: var(--accent);
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            border: none;
            border-radius: 8px;
            padding: 12px 15px;
            background-color: rgba(243, 243, 243, 0.8);
            transition: var(--transition);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(81, 43, 88, 0.2);
            background-color: white;
        }
        
        .alert {
            margin-bottom: 20px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border: none;
            border-radius: 8px;
            padding: 16px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .login-button {
            padding: 0;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-text {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 3px;
            position: relative;
        }
        
        .login-text:after {
            content: "";
            position: absolute;
            width: 100%;
            height: 2px;
            background-color: rgba(255, 255, 255, 0.3);
            bottom: -4px;
            left: 0;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .btn-primary:hover .login-text:after {
            transform: scaleX(1);
        }
        
        a {
            color: var(--primary);
            transition: var(--transition);
            font-weight: 500;
        }
        
        a:hover {
            color: var(--primary-hover);
            text-decoration: none;
        }
        
        .input-icon-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            left: 15px;
            color: #999;
        }
        
        .input-with-icon {
            padding-left: 40px;
        }
        
        .text-center p {
            margin-top: 15px;
            color: #555;
        }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="faq.html"><i class="fas fa-book"></i> help</a></li>
            <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
            <li class="login-btn"><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Login</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '') ?>">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="identity"><i class="fas fa-user"></i> Email or Username</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control input-with-icon" id="identity" name="identity" required
                               placeholder="Enter your email or username"
                               value="<?= isset($_POST['identity']) ? htmlspecialchars($_POST['identity']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control input-with-icon" id="password" name="password" required
                               placeholder="Enter your password">
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block btn-lg login-button">
                        <span class="login-text">LOGIN</span>
                    </button>
                </div>

                <div class="text-center">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>