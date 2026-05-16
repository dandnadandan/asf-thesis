<?php
/**
 * Secure File Viewer for Admin
 * Handles viewing of uploaded files with proper security checks
 */

require_once '../includes/session_manager.php';

// Check if session is still valid
if (!isLoggedIn()) {
    http_response_code(401);
    die("Unauthorized access");
}

// Require admin role
requireRole(['admin', 'administrator'], null);

// Get file path from URL parameter
$file_path = $_GET['file'] ?? '';

if (empty($file_path)) {
    http_response_code(400);
    die("No file specified");
}

// Security: Only allow files from specific directories
$allowed_directories = [
    'uploads/business_registration/',
    'uploads/tax_filing/',
    'uploads/accounting/',
    'uploads/payroll/',
    'uploads/financial_statements/',
    'uploads/documents/'
];

$file_path = urldecode($file_path);
// Use absolute path for file operations
$full_path = dirname(__DIR__) . '/' . $file_path;

// Check if file path is in allowed directories
$allowed = false;
foreach ($allowed_directories as $allowed_dir) {
    if (strpos($file_path, $allowed_dir) === 0) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    http_response_code(403);
    die("Access denied to this file location");
}

// Check if file exists
if (!file_exists($full_path)) {
    http_response_code(404);
    die("File not found: " . htmlspecialchars($full_path));
}

// Check if it's a file (not a directory)
if (!is_file($full_path)) {
    http_response_code(400);
    die("Invalid file");
}

// Get file info
$file_info = pathinfo($full_path);
$file_extension = strtolower($file_info['extension'] ?? '');
$file_name = $file_info['basename'];

// Allowed file types for viewing
$allowed_extensions = [
    'pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', // Images and PDFs
    'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv' // Documents
];

if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(415);
    die("File type not supported for viewing");
}

// Set appropriate headers based on file type
$mime_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'webp' => 'image/webp',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt' => 'text/plain',
    'csv' => 'text/csv'
];

$mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=3600'); // Cache for 1 hour

// For PDFs and images, serve directly
if (in_array($file_extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
    readfile($full_path);
    exit;
}

// For other file types, create a simple HTML viewer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Viewer - <?php echo htmlspecialchars($file_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .file-viewer {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        .file-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="file-viewer">
        <div class="file-content">
            <div class="text-center mb-4">
                <h4><i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($file_name); ?></h4>
                <p class="text-muted">File Type: <?php echo strtoupper($file_extension); ?></p>
            </div>
            
            <?php if ($file_extension === 'txt' || $file_extension === 'csv'): ?>
                <div class="alert alert-info">
                    <h5><i class="bi bi-info-circle"></i> Text File Content</h5>
                    <pre style="white-space: pre-wrap; max-height: 500px; overflow-y: auto; background: #f8f9fa; padding: 1rem; border-radius: 4px;"><?php echo htmlspecialchars(file_get_contents($full_path)); ?></pre>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> File Type Not Supported for Preview</h5>
                    <p>This file type (<?php echo strtoupper($file_extension); ?>) cannot be previewed in the browser.</p>
                    <a href="<?php echo htmlspecialchars($file_path); ?>" download class="btn btn-primary">
                        <i class="bi bi-download"></i> Download File
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="javascript:window.close()" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Close
                </a>
                <a href="<?php echo htmlspecialchars($file_path); ?>" download class="btn btn-success">
                    <i class="bi bi-download"></i> Download
                </a>
            </div>
        </div>
    </div>
</body>
</html>
