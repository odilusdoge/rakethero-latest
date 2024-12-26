<?php
include 'db_conn.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE user_id = ? 
    AND is_read = 0 
    AND type IN ('proposal_accepted', 'proposal_rejected', 'application_rejected')
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
    exit;
}

$stmt->bind_param("i", $user_id);
$result = $stmt->execute();

if ($result) {
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
}

$stmt->close();
$conn->close();
?> 