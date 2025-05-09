<?php
require_once '../config/database.php';
require_once 'admin_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    createUser($_POST);
    header("Location: admin_accounts.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h1>Add New Account</h1>
    <form method="post">
        <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>First Name</label>
            <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Address</label>
            <input type="text" name="address" class="form-control">
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control">
        </div>
        <div class="mb-3">
            <label>Role</label>
            <select name="role_id" class="form-control" required>
                <?php foreach (getAllRoles() as $role): ?>
                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-success">Create Account</button>
    </form>
</div>
</body>
</html>
