<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    private $conn;
    private $jwt_secret;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->jwt_secret = JWT_SECRET;
    }

    public function register($username, $email, $password, $full_name, $user_type) {
        try {
            // Check if username or email already exists
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, email, password, full_name, user_type)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$username, $email, $hashed_password, $full_name, $user_type]);
            
            return [
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => $this->conn->lastInsertId()
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    public function login($username, $password) {
        try {
            // Get user by username
            $stmt = $this->conn->prepare("
                SELECT id, username, email, password, full_name, user_type
                FROM users
                WHERE username = ?
            ");
            
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            // Generate JWT token
            $token = $this->generateToken($user);

            return [
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'user_type' => $user['user_type']
                ]
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    public function verifyToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->jwt_secret, 'HS256'));
            return ['success' => true, 'user' => $decoded];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Invalid token'];
        }
    }

    private function generateToken($user) {
        $payload = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours expiration
        ];

        return JWT::encode($payload, $this->jwt_secret, 'HS256');
    }

    public function updateLanguagePreference($user_id, $language) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE users
                SET language_preference = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$language, $user_id]);
            
            return [
                'success' => true,
                'message' => 'Language preference updated successfully'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to update language preference'];
        }
    }
}
?> 