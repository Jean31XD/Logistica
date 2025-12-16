<?php
/**
 * Minimal test endpoint - NO dependencies
 * If this doesn't work, the problem is web server/routing
 */

// Log that script started
error_log("test_endpoint.php: Script started");

// Set headers
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

// Create simple response
$response = [
    'success' => true,
    'message' => 'Test endpoint works',
    'timestamp' => time(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
];

// Output JSON
echo json_encode($response);

// Log completion
error_log("test_endpoint.php: Response sent");
exit;
