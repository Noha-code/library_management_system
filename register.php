<?php
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
$success = false;

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security error, please try again.";
    } else {
        // Get and sanitize data
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validate data
        if (empty($username)) {
            $errors[] = "Username is required.";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "Username must be between 3 and 50 characters.";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email.";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }
        
        if (empty($firstName)) {
            $errors[] = "First name is required.";
        }
        
        if (empty($lastName)) {
            $errors[] = "Last name is required.";
        }
        
        // If no errors, proceed with registration
        if (empty($errors)) {
            $user = new User();
            $result = $user->register($username, $email, $password, $firstName, $lastName, $address, $phone);
            
            if ($result['success']) {
                $success = true;
                // Redirect to login page after 3 seconds
                header("refresh:3;url=login.php");
            } else {
                $errors[] = $result['message'];
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
    <title>Register - Library Management System</title>
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
            background-color: rgba(33, 33, 33, 0.9);
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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
            max-width: 650px;
            width: 100%;
            margin: 0 auto;
            background-color: rgba(255, 255, 255, 0.95);
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
        
        .required:after {
            content: "*";
            color: #e74c3c;
            margin-left: 4px;
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
        
        .register-button {
            padding: 0;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .register-text {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 3px;
            position: relative;
        }
        
        .register-text:after {
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
        
        .btn-primary:hover .register-text:after {
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
        
        .form-text {
            color: #6c757d;
            font-size: 0.8rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="books.php"><i class="fas fa-book"></i> Books</a></li>
            <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
            <li class="login-btn"><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Create an Account</h2>
            
            <?php 
            // Show error messages
            if (!empty($errors)) {
                echo '<div class="alert alert-danger">';
                foreach ($errors as $error) {
                    echo '<p class="mb-0">' . htmlspecialchars($error) . '</p>';
                }
                echo '</div>';
            }
            
            // Show success message
            if ($success) {
                echo '<div class="alert alert-success">
                    <p class="mb-0"><i class="fas fa-check-circle mr-2"></i> Registration successful! You will be redirected to the login page...</p>
                </div>';
            }
            ?>
            
            <?php if (!$success) { ?>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="username" class="required"><i class="fas fa-user"></i> Username</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="form-control input-with-icon" id="username" name="username" 
                                    placeholder="Create a username" required
                                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email" class="required"><i class="fas fa-envelope"></i> Email</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" class="form-control input-with-icon" id="email" name="email" 
                                    placeholder="Your email address" required
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password" class="required"><i class="fas fa-lock"></i> Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" class="form-control input-with-icon" id="password" name="password" 
                                    placeholder="Create a password" required>
                            </div>
                            <small class="form-text text-muted"><i class="fas fa-info-circle"></i> At least 8 characters</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="confirm_password" class="required"><i class="fas fa-lock"></i> Confirm Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" class="form-control input-with-icon" id="confirm_password" name="confirm_password" 
                                    placeholder="Confirm your password" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="first_name" class="required"><i class="fas fa-user-circle"></i> First Name</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user-circle input-icon"></i>
                                <input type="text" class="form-control input-with-icon" id="first_name" name="first_name" 
                                    placeholder="Your first name" required
                                    value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="last_name" class="required"><i class="fas fa-user-circle"></i> Last Name</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user-circle input-icon"></i>
                                <input type="text" class="form-control input-with-icon" id="last_name" name="last_name" 
                                    placeholder="Your last name" required
                                    value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-map-marker-alt input-icon"></i>
                        <textarea class="form-control input-with-icon" id="address" name="address" rows="2" 
                            placeholder="Your address (optional)"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone"><i class="fas fa-phone"></i> Phone</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" class="form-control input-with-icon" id="phone" name="phone" 
                            placeholder="Your phone number (optional)"
                            value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block btn-lg register-button">
                        <span class="register-text">REGISTER</span>
                    </button>
                </div>
                
                <div class="text-center">
                    <p>Already registered? <a href="login.php">Login here</a></p>
                </div>
            </form>
            <?php } ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
