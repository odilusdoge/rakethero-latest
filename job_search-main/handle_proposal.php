<?php
include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'employer') {
    echo "Unauthorized access";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = $_POST['applicationId'];
    $action = $_POST['action'];
    
    $conn->begin_transaction();

    try {
        // Update application status
        $newStatus = ($action === 'accept') ? 'accepted' : 'declined';
        $updateQuery = "UPDATE applications SET status = ? WHERE applications_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("si", $newStatus, $applicationId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating application status");
        }

        // If accepted, create notification for jobseeker
        if ($action === 'accept') {
            $notifQuery = "INSERT INTO notifications (user_id, from_user_id, type, message, job_id, created_at)
                          SELECT 
                              a.userId,
                              j.employerId,
                              'proposal_accepted',
                              'Your proposal has been accepted. You can now send a counter offer.',
                              a.jobid,
                              NOW()
                          FROM applications a
                          JOIN jobs j ON a.jobid = j.jobs_id
                          WHERE a.applications_id = ?";
            
            $stmt = $conn->prepare($notifQuery);
            $stmt->bind_param("i", $applicationId);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating notification");
            }
        }

        $conn->commit();
        echo "success";
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}
?> 