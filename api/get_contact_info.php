<?php
/**
 * Get Contact Information API
 * Retrieves contact information for display on the landing page
 */

header('Content-Type: application/json');

// Set CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Fetch active contact information
    $query = "SELECT email, phone, address, facebook_url, twitter_url, linkedin_url, instagram_url 
              FROM contact_information 
              WHERE is_active = 1 
              ORDER BY id DESC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $contact_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($contact_info) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $contact_info
        ]);
    } else {
        // Return default values if no contact info in database
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'email' => 'fkeepers2013@gmail.com',
                'phone' => '09178852769',
                'address' => '0534 Lot 7 Blk 10 Richwood Park Village, Brgy San Jose, Mahogany Street, San Pablo City, Laguna 4000',
                'facebook_url' => 'https://www.facebook.com/share/19VeYZniBW/',
                'twitter_url' => null,
                'linkedin_url' => null,
                'instagram_url' => null
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get Contact Info Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving contact information.'
    ]);
}
?>

