<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    die("Not authorized");
}

if (!isset($_POST['applicationId']) || !isset($_POST['status'])) {
    die("Invalid parameters");
}

$applicationId = intval($_POST['applicationId']);
$status = $_POST['status'];

// Validate status
if (!in_array($status, ['Accepted', 'Rejected'])) {
    die("Invalid status");
}

// Start transaction
$conn->begin_transaction();

try {
    // Update application status
    $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE applications_id = ?");
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("si", $status, $applicationId);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating application: " . $stmt->error);
    }

    // Get the jobseeker's user ID and job details
    $getUserStmt = $conn->prepare("
        SELECT a.userId, j.title, j.jobs_id
        FROM applications a 
        JOIN jobs j ON a.jobId = j.jobs_id 
        WHERE a.applications_id = ?
    ");
    $getUserStmt->bind_param("i", $applicationId);
    $getUserStmt->execute();
    $data = $getUserStmt->get_result()->fetch_assoc();
    
    if ($data) {
        // Create notification for jobseeker
        $userId = $data['userId'];
        $jobTitle = $data['title'];
        $message = "Your application for '$jobTitle' has been $status";
        
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, type, is_read, created_at) 
            VALUES (?, ?, ?, 0, NOW())
        ");
        $type = "proposal_" . strtolower($status);
        $notifStmt->bind_param("iss", $userId, $message, $type);
        $notifStmt->execute();

        if ($status === 'Rejected') {
            // Delete the rejected application
            $deleteStmt = $conn->prepare("DELETE FROM applications WHERE applications_id = ?");
            $deleteStmt->bind_param("i", $applicationId);
            if (!$deleteStmt->execute()) {
                throw new Exception("Error deleting application: " . $deleteStmt->error);
            }
        }
    }

    // If everything is successful, commit the transaction
    $conn->commit();
    echo "Application has been $status successfully";

} catch (Exception $e) {
    // If there's an error, rollback the changes
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}

// Close all statements
if (isset($stmt)) $stmt->close();
if (isset($getUserStmt)) $getUserStmt->close();
if (isset($notifStmt)) $notifStmt->close();
if (isset($deleteStmt)) $deleteStmt->close();
$conn->close();
?> 