<?php
require_once __DIR__ . '/config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    // Create a new user
    public function register($username, $email, $password, $firstName, $lastName, $address = null, $phone = null) {
        try {
            if ($this->getUserByEmail($email) || $this->getUserByUsername($username)) {
                return [
                    'success' => false,
                    'message' => 'This email or username already exists'
                ];
            }
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, address, phone)
                VALUES (:username, :email, :password, :first_name, :last_name, :address, :phone)
            ");
            
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':address' => $address,
                ':phone' => $phone
            ]);
            
            $userId = $this->db->lastInsertId();
            $this->assignRole($userId, 'user');
            
            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'Registration successful'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error during registration: ' . $e->getMessage()
            ];
        }
    }
    
    // Authenticate a user
    public function login($identity, $password) {
        try {
            // Check if input is an email, otherwise treat as username
            $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
            
            if ($isEmail) {
                $stmt = $this->db->prepare("
                    SELECT u.*, GROUP_CONCAT(r.name) AS roles
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    WHERE u.email = :identity
                    GROUP BY u.id
                ");
            } else {
                $stmt = $this->db->prepare("
                    SELECT u.*, GROUP_CONCAT(r.name) AS roles
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    WHERE u.username = :identity
                    GROUP BY u.id
                ");
            }
            
            $stmt->execute([':identity' => $identity]);
            $user = $stmt->fetch();
            
            $this->logLoginAttempt($identity, $user['id'] ?? null, $_SERVER['REMOTE_ADDR'], false);
            
            if (!$user || !password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Email/username or password incorrect'
                ];
            }
            
            if ($user['status'] !== 'active') {
                return [
                    'success' => false,
                    'message' => 'This account is ' . $user['status'] . '. Please contact the administrator.'
                ];
            }
            
            $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);
            
            $this->logLoginAttempt($identity, $user['id'], $_SERVER['REMOTE_ADDR'], true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['user_roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'roles' => $_SESSION['user_roles']
                ]
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error during login: ' . $e->getMessage()
            ];
        }
    }
    
    // Log login attempts
    private function logLoginAttempt($identity, $userId, $ipAddress, $success) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (user_id, email, ip_address, success, attempt_time, user_agent)
            VALUES (:user_id, :email, :ip_address, :success, NOW(), :user_agent)
        ");
    
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $identity,
            ':ip_address' => $ipAddress,
            ':success' => $success ? 1 : 0,
            ':user_agent' => $userAgent
        ]);
    }
    
    // Logout
    public function logout() {
        session_unset();
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Logout successful'
        ];
    }
    
    // Get user by ID
    public function getUserById($id) {
        $stmt = $this->db->prepare("
            SELECT u.*, GROUP_CONCAT(r.name) AS roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = :id
            GROUP BY u.id
        ");
        
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
        }
        
        return $user;
    }
    
    // Get user by email
    public function getUserByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        
        return $stmt->fetch();
    }
    
    // Get user by username
    public function getUserByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        
        return $stmt->fetch();
    }
    
    // Assign role to user
    public function assignRole($userId, $roleName) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = :name");
        $stmt->execute([':name' => $roleName]);
        $role = $stmt->fetch();
        
        if (!$role) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO user_roles (user_id, role_id)
            VALUES (:user_id, :role_id)
        ");
        
        return $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $role['id']
        ]);
    }
    
    // Update user profile
    public function updateProfile($userId, $data) {
        $allowedFields = ['first_name', 'last_name', 'address', 'phone'];
        $fields = [];
        $params = [':id' => $userId];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    // Change password
    public function changePassword($userId, $currentPassword, $newPassword) {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect'
            ];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
        
        if ($stmt->execute([':password' => $hashedPassword, ':id' => $userId])) {
            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error changing password'
            ];
        }
    }
    /**
 * Delete a user account from the database
 * 
 * @param int $userId The ID of the user to delete
 * @return bool True if deletion was successful, false otherwise
 */
public function deleteAccount($userId) {
    try {
        // Verify user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        
        if (!$stmt->fetch()) {
            // User not found or not active
            return false;
        }
        
        // Begin transaction
        $this->db->beginTransaction();
        
        // First, handle foreign key constraints
        // Delete user's borrowed books records (if you have such a table)
        $stmt = $this->db->prepare("DELETE FROM borrowed_books WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Delete user's reservations (if you have such a table)
        $stmt = $this->db->prepare("DELETE FROM reservations WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Delete other related records as needed...
        
        // Finally, delete the user
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Commit transaction
        $this->db->commit();
        
        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        
        // Log the error
        // error_log('Delete account error: ' . $e->getMessage());
        
        return false;
    }
}

}
?>
