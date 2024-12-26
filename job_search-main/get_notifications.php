<?php
include 'db_conn.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get unread count
$stmt = $conn->prepare("
    SELECT COUNT(*) as unread_count
    FROM notifications 
    WHERE user_id = ? 
    AND is_read = 0
    AND type IN ('new_application', 'new_proposal')
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unreadCount = $result->fetch_assoc()['unread_count'];

// Get recent notifications
$stmt = $conn->prepare("
    SELECT n.*, 
           j.title as job_title, 
           ui.fname as applicant_fname,
           ui.lname as applicant_lname
    FROM notifications n
    LEFT JOIN jobs j ON j.jobs_id = n.job_id
    LEFT JOIN user_info ui ON ui.userid = n.from_user_id
    WHERE n.user_id = ?
    AND n.type IN ('new_application', 'new_proposal')
    ORDER BY n.created_at DESC
    LIMIT 10
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'type' => $row['type'],
        'message' => $row['message'],
        'job_title' => $row['job_title'],
        'employer_name' => $row['applicant_fname'] . ' ' . $row['applicant_lname'],
        'created_at' => $row['created_at'],
        'is_read' => $row['is_read']
    ];
}

echo json_encode([
    'success' => true,
    'unreadCount' => $unreadCount,
    'notifications' => $notifications
]);
?> 