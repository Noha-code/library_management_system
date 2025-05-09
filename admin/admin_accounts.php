<?php
require_once '../config/database.php';
require_once 'admin_functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$roles = ['User', 'Librarian', 'Admin'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Accounts</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        h2 { background-color: #f2f2f2; padding: 10px; }
        .actions a { margin-right: 10px; }
    </style>
</head>
<body>
    <h1>Admin - Manage Accounts</h1>
    <a href="add_account.php">Add New Account</a>

    <?php foreach ($roles as $role): ?>
        <h2><?= htmlspecialchars($role) ?> Accounts</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Full Name</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $users = getAllUsersWithRole($role);
                if ($users):
                    foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                            <td><?= htmlspecialchars($user['address']) ?></td>
                            <td><?= htmlspecialchars($user['phone']) ?></td>
                            <td class="actions">
                                <a href="edit_account.php?id=<?= $user['id'] ?>">Edit</a>
                                <a href="delete_account.php?id=<?= $user['id'] ?>" onclick="return confirm('Are you sure you want to delete this account?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr><td colspan="7">No <?= htmlspecialchars($role) ?> accounts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</body>
</html>
