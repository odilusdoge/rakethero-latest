<?php
session_start();
include 'db_conn.php';

// Prevent any HTML output before JSON response
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

try {
    $query = "UPDATE notifications 
              SET is_read = 1 
              WHERE user_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Mark read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit;
?> 