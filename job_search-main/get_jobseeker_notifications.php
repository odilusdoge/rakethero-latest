<?php
session_start();
include 'db_conn.php';

// Prevent any HTML output before JSON response
ob_clean();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    // Get unread count first
    $countQuery = "SELECT COUNT(*) as count 
                   FROM notifications 
                   WHERE user_id = ? 
                   AND is_read = 0";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $unreadCount = $stmt->get_result()->fetch_assoc()['count'];

    // Get notifications
    $query = "SELECT n.*, 
              j.title as job_title, 
              u.username as employer_name,
              n.created_at,
              n.is_read,
              n.type,
              n.message
        FROM notifications n 
        LEFT JOIN jobs j ON j.jobs_id = n.job_id
        LEFT JOIN users u ON u.users_id = n.from_user_id
        WHERE n.user_id = ? 
        AND n.type IN ('proposal_accepted', 'proposal_rejected', 'application_rejected', 
                      'job_cancelled', 'employer_counter_offer', 'new_jobseeker_rating')
        ORDER BY n.created_at DESC 
        LIMIT 10";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['notifications_id'],
            'message' => $row['message'],
            'created_at' => date('M j, Y g:i A', strtotime($row['created_at'])),
            'is_read' => (bool)$row['is_read'],
            'type' => $row['type'],
            'job_title' => $row['job_title'],
            'employer_name' => $row['employer_name']
        ];
    }

    echo json_encode([
        'success' => true, 
        'notifications' => $notifications,
        'unreadCount' => $unreadCount
    ]);

} catch (Exception $e) {
    error_log("Notification error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Error loading notifications'
    ]);
}

// Make sure nothing else is output
exit; 