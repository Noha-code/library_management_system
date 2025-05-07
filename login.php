<?php
// Enable error reporting for development (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'user.php';

// Initialize session
initSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
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

                if (is_array($result) && !empty($result['success'])) {
                    $redirect = $_GET['redirect'] ?? 'index.php';
                    header("Location: " . $redirect);
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
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 40px;
            padding-bottom: 40px;
        }
        .form-container {
            max-width: 400px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .form-title {
            text-align: center;
            margin-bottom: 30px;
            color: #343a40;
        }
        .form-group label {
            font-weight: 500;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
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
                    <label for="identity">Email or Username</label>
                    <input type="text" class="form-control" id="identity" name="identity" required
                           value="<?= isset($_POST['identity']) ? htmlspecialchars($_POST['identity']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
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