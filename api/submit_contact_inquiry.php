<?php
/**
 * Submit Contact Inquiry API
 * Handles contact form submissions from the landing page
 */

header('Content-Type: application/json');

// Set CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['name']) || empty($input['email']) || empty($input['subject']) || empty($input['message'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required'
        ]);
        exit;
    }
    
    // Sanitize inputs
    $name = trim($input['name']);
    $email = trim($input['email']);
    $subject = trim($input['subject']);
    $message = trim($input['message']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }
    
    // Validate field lengths
    if (strlen($name) > 255 || strlen($email) > 255 || strlen($subject) > 500) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'One or more fields exceed maximum length'
        ]);
        exit;
    }
    
    // Get client information
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Insert inquiry into database
    $query = "INSERT INTO contact_inquiries 
              (name, email, subject, message, ip_address, user_agent, status) 
              VALUES (:name, :email, :subject, :message, :ip_address, :user_agent, 'new')";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':subject', $subject);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->bindParam(':user_agent', $user_agent);
    
    if ($stmt->execute()) {
        $inquiry_id = $conn->lastInsertId();
        
        // Optional: Send notification email to admin
        // sendAdminNotification($name, $email, $subject, $message);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for contacting us! We will get back to you soon.',
            'inquiry_id' => $inquiry_id
        ]);
    } else {
        throw new Exception('Failed to save inquiry');
    }
    
} catch (Exception $e) {
    error_log("Contact Inquiry Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while submitting your inquiry. Please try again later.'
    ]);
}
?>

