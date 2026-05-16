<?php
/**
 * User Class for ASF Surveillance System
 * Handles user authentication, role management, and CRUD operations
 */

require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "user_accounts";
    
    // User properties
    public $id;
    public $username;
    public $email;
    public $password_hash;
    public $first_name;
    public $last_name;
    public $user_role;
    public $company_name;
    public $phone;
    public $address;
    public $region;
    public $province;
    public $city_municipality;
    public $barangay;
    public $city;
    public $state;
    public $postal_code;
    public $country;
    public $profile_image;
    public $is_active;
    public $is_verified;
    public $email_verified_at;
    public $last_login_at;
    public $created_at;
    public $updated_at;
    
    // Constructor
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Create new user
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                (username, email, password_hash, first_name, last_name, user_role, 
                 company_name, phone, address, region, province, city_municipality, barangay, country)
                VALUES (:username, :email, :password_hash, :first_name, :last_name, :user_role,
                        :company_name, :phone, :address, :region, :province, :city_municipality, :barangay, :country)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind parameters
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password_hash", $this->password_hash);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":user_role", $this->user_role);
        $stmt->bindParam(":company_name", $this->company_name);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":region", $this->region);
        $stmt->bindParam(":province", $this->province);
        $stmt->bindParam(":city_municipality", $this->city_municipality);
        $stmt->bindParam(":barangay", $this->barangay);
        $stmt->bindParam(":country", $this->country);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
    
    // Read user by ID
    public function readById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->assignProperties($row);
            return true;
        }
        return false;
    }
    
    // Read user by username
    public function readByUsername($username) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->assignProperties($row);
            return true;
        }
        return false;
    }
    
    // Read user by email
    public function readByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->assignProperties($row);
            return true;
        }
        return false;
    }
    
    // Update user
    public function update() {
        // Only update columns that definitely exist in user_accounts table
        $query = "UPDATE " . $this->table_name . "
                SET username = :username, 
                    email = :email, 
                    first_name = :first_name, 
                    last_name = :last_name, 
                    company_name = :company_name,
                    phone = :phone, 
                    address = :address,
                    profile_image = :profile_image
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind only existing parameters
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":company_name", $this->company_name);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":profile_image", $this->profile_image);
        
        return $stmt->execute();
    }
    
    // Update password
    public function updatePassword($new_password_hash) {
        $query = "UPDATE " . $this->table_name . " SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":password_hash", $new_password_hash);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }
    
    // Update last login
    public function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . " SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }
    
    // Delete user (soft delete)
    public function delete() {
        $query = "UPDATE " . $this->table_name . " SET is_active = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }
    
    // Get all users (for admin)
    public function getAllUsers($limit = 100, $offset = 0) {
        $query = "SELECT id, username, email, first_name, last_name, user_role, 
                         company_name, is_active, is_verified, created_at, last_login_at
                  FROM " . $this->table_name . "
                  ORDER BY created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get users by role
    public function getUsersByRole($role, $limit = 100) {
        $query = "SELECT id, username, email, first_name, last_name, company_name, 
                         is_active, is_verified, created_at, last_login_at
                  FROM " . $this->table_name . "
                  WHERE user_role = :role AND is_active = 1
                  ORDER BY created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":role", $role);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Check if user has permission
    public function hasPermission($permission_name) {
        $query = "SELECT COUNT(*) as count FROM role_permissions rp
                  JOIN user_permissions up ON rp.permission_id = up.id
                  WHERE rp.user_role = :user_role AND up.permission_name = :permission_name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_role", $this->user_role);
        $stmt->bindParam(":permission_name", $permission_name);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] > 0;
    }
    
    // Get user permissions
    public function getPermissions() {
        $query = "SELECT up.permission_name, up.description FROM role_permissions rp
                  JOIN user_permissions up ON rp.permission_id = up.id
                  WHERE rp.user_role = :user_role
                  ORDER BY up.permission_name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_role", $this->user_role);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Verify password
    public function verifyPassword($password) {
        return password_verify($password, $this->password_hash);
    }
    
    // Hash password
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    // Check if username exists
    public function usernameExists($username, $exclude_id = null) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE username = :username AND is_active = 1";
        
        if($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        
        if($exclude_id) {
            $stmt->bindParam(":exclude_id", $exclude_id);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] > 0;
    }
    
    // Check if email exists
    public function emailExists($email, $exclude_id = null) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE email = :email AND is_active = 1";
        
        if($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        
        if($exclude_id) {
            $stmt->bindParam(":exclude_id", $exclude_id);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] > 0;
    }
    
    // Assign properties from database row
    private function assignProperties($row) {
        $this->id = $row['id'];
        $this->username = $row['username'];
        $this->email = $row['email'];
        $this->password_hash = $row['password_hash'];
        $this->first_name = $row['first_name'];
        $this->last_name = $row['last_name'];
        $this->user_role = $row['user_role'];
        $this->company_name = $row['company_name'] ?? null;
        $this->phone = $row['phone'] ?? null;
        $this->address = $row['address'] ?? null;
        $this->city = $row['city'] ?? null;
        $this->state = $row['state'] ?? null;
        $this->region = $row['region'] ?? null;
        $this->province = $row['province'] ?? null;
        $this->city_municipality = $row['city_municipality'] ?? null;
        $this->barangay = $row['barangay'] ?? null;
        $this->postal_code = $row['postal_code'] ?? null;
        $this->country = $row['country'] ?? 'USA';
        $this->profile_image = $row['profile_image'] ?? null;
        $this->is_active = $row['is_active'];
        $this->is_verified = $row['is_verified'];
        $this->email_verified_at = $row['email_verified_at'] ?? null;
        $this->last_login_at = $row['last_login_at'] ?? null;
        $this->created_at = $row['created_at'];
        $this->updated_at = $row['updated_at'];
    }
    
    // Get full name
    public function getFullName() {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    // Check if user is administrator
    public function isAdministrator() {
        return $this->user_role === 'administrator';
    }
    
    // Check if user is client
    public function isClient() {
        return $this->user_role === 'client';
    }
}
?>
