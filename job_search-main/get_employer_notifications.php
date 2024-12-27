<?php
session_start();
include 'db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    $query = "SELECT n.*, 
              j.title as job_title, 
              u.username as jobseeker_name,
              n.created_at,
              n.is_read,
              n.type,
              n.message
        FROM notifications n 
        LEFT JOIN jobs j ON j.jobs_id = n.job_id
        LEFT JOIN users u ON u.users_id = n.from_user_id
        WHERE n.user_id = ? 
        AND j.employerId = ?
        AND n.type IN ('new_application', 'new_quotation', 'counter_offer_received',
                      'quotation_declined', 'quotation_accepted', 'new_employer_rating')
        ORDER BY n.created_at DESC 
        LIMIT 10";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notification = [
            'id' => $row['notifications_id'],
            'message' => $row['message'],
            'created_at' => date('M j, Y g:i A', strtotime($row['created_at'])),
            'is_read' => (bool)$row['is_read'],
            'type' => $row['type'],
            'job_title' => $row['job_title'],
            'jobseeker_name' => $row['jobseeker_name']
        ];
        $notifications[] = $notification;
    }

    echo json_encode(['success' => true, 'notifications' => $notifications]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 