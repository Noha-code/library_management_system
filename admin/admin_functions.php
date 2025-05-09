<?php
require_once '../config/database.php';

function getAllUsersWithRole($roleName) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT u.*, r.name as role FROM users u 
                            JOIN user_roles ur ON u.id = ur.user_id 
                            JOIN roles r ON ur.role_id = r.id 
                            WHERE r.name = ?");
    $stmt->execute([$roleName]);
    return $stmt->fetchAll();
}

function getUserById($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT u.*, ur.role_id FROM users u
                           JOIN user_roles ur ON u.id = ur.user_id
                           WHERE u.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function updateUser($id, $data) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, address = ?, phone = ? WHERE id = ?");
    $stmt->execute([
        $data['username'], $data['email'], $data['first_name'], $data['last_name'], $data['address'], $data['phone'], $id
    ]);

    $stmt = $pdo->prepare("UPDATE user_roles SET role_id = ? WHERE user_id = ?");
    $stmt->execute([$data['role_id'], $id]);
}

function deleteUser($id) {
    $pdo = getDBConnection();
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
}

function createUser($data) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO users (username, email, first_name, last_name, address, phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['username'], $data['email'], $data['first_name'], $data['last_name'], $data['address'], $data['phone']
    ]);

    $userId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
    $stmt->execute([$userId, $data['role_id']]);
}

function getAllRoles() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM roles");
    return $stmt->fetchAll();
}
