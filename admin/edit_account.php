<?php
require_once '../config/database.php';
require_once 'admin_functions.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid ID");
}

$user = getUserById($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    updateUser($id, $_POST);
    header("Location: admin_accounts.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h1>Edit Account</h1>
    <form method="post">
        <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>">
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
        </div>
        <div class="mb-3">
            <label>First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>">
        </div>
        <div class="mb-3">
            <label>Last Name</label>
            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>">
        </div>
        <div class="mb-3">
            <label>Address</label>
            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address']) ?>">
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
        </div>
        <div class="mb-3">
            <label>Role</label>
            <select name="role_id" class="form-control">
                <?php foreach (getAllRoles() as $role): ?>
                    <option value="<?= $role['id'] ?>" <?= $role['id'] == $user['role_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary">Save Changes</button>
    </form>
</div>
</body>
</html>
