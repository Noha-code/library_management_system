<?php
require_once '../config/database.php';
require_once 'admin_functions.php';

$id = $_GET['id'] ?? null;

if ($id) {
    deleteUser($id);
}

header("Location: admin_accounts.php");
exit;
