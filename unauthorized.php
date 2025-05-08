<?php
// Include required files
require_once 'config/session.php';

// Initialize session
initSession();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Library Management System</title>
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
        
        .unauthorized-container {
            max-width: 500px;
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
            text-align: center;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .unauthorized-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 2rem;
            position: relative;
            margin-bottom: 30px;
        }
        
        .unauthorized-title:after {
            content: "";
            position: absolute;
            width: 60px;
            height: 3px;
            background-color: var(--accent);
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .unauthorized-icon {
            font-size: 5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .unauthorized-message {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 30px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: var(--transition);
            margin-top: 10px;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
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
    </style>
</head>
<body>

    <div class="container">
        <div class="unauthorized-container">
            <div class="unauthorized-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h2 class="unauthorized-title">Unauthorized Access</h2>
            <div class="unauthorized-message">
                <p>Oops! You don't have permission to access this page.</p>
                <p>Please make sure you have the correct credentials or contact an administrator if you believe this is an error.</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>