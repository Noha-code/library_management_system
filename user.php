<?php
require_once __DIR__ . '/config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    // Créer un nouvel utilisateur
    public function register($username, $email, $password, $firstName, $lastName, $address = null, $phone = null) {
        try {
            if ($this->getUserByEmail($email) || $this->getUserByUsername($username)) {
                return [
                    'success' => false,
                    'message' => 'Cet email ou nom d\'utilisateur existe déjà'
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
                'message' => 'Inscription réussie'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage()
            ];
        }
    }
    
    // Authentifier un utilisateur
    public function login($email, $password) {
        try {
            $isEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
            $field = $isEmail ? 'email' : 'username';
            
            $stmt = $this->db->prepare("
                SELECT u.*, GROUP_CONCAT(r.name) AS roles
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                WHERE u.$field = :identity
                GROUP BY u.id
            ");
            
            $stmt->execute([':identity' => $email]);
            $user = $stmt->fetch();
            
            $this->logLoginAttempt($email, $user['id'] ?? null, $_SERVER['REMOTE_ADDR'], false);
            
            if (!$user || !password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Email/nom d\'utilisateur ou mot de passe incorrect'
                ];
            }
            
            if ($user['status'] !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Ce compte est ' . $user['status'] . '. Veuillez contacter l\'administrateur.'
                ];
            }
            
            $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);
            
            $this->logLoginAttempt($email, $user['id'], $_SERVER['REMOTE_ADDR'], true);
            
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
                'message' => 'Erreur lors de la connexion: ' . $e->getMessage()
            ];
        }
    }
    // private function
    private function logLoginAttempt($email, $userId, $ipAddress, $success) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (user_id, email, ip_address, success, attempt_time, user_agent)
            VALUES (:user_id, :email, :ip_address, :success, NOW(), :user_agent)
        ");
    
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':ip_address' => $ipAddress,
            ':success' => $success ? 1 : 0,
            ':user_agent' => $userAgent
        ]);
    }
    
    // Déconnexion
    public function logout() {
        session_unset();
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Déconnexion réussie'
        ];
    }
    
    // Récupérer un utilisateur par son ID
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
    
    // Récupérer un utilisateur par son email
    public function getUserByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        
        return $stmt->fetch();
    }
    
    // Récupérer un utilisateur par son nom d'utilisateur
    public function getUserByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        
        return $stmt->fetch();
    }
    
    // Attribuer un rôle à un utilisateur
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
    
    // Mettre à jour le profil de l'utilisateur
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
    
    // Changer le mot de passe
    public function changePassword($userId, $currentPassword, $newPassword) {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Mot de passe actuel incorrect'
            ];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
        
        if ($stmt->execute([':password' => $hashedPassword, ':id' => $userId])) {
            return [
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification du mot de passe'
            ];
        }
    }
}
?>
