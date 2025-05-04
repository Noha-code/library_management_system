<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'user.php';

initSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security error, please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) {
            $errors[] = "Please enter your email or username.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }

        if (empty($password)) {
            $errors[] = "Please enter your password.";
        }

        if (empty($errors)) {
            $user = new User();
            $result = $user->login($email, $password);

            if ($result['success']) {
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                header("Location: " . $redirect);
                exit;
            } else {
                $errors[] = "User info not found.";
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

            <?php
            if (!empty($errors)) {
                echo '<div class="alert alert-danger">';
                foreach ($errors as $error) {
                    echo '<p class="mb-0">' . htmlspecialchars($error) . '</p>';
                }
                echo '</div>';
            }
            ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="email">Email or Username</label>
                    <input type="text" class="form-control" id="email" name="email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
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
