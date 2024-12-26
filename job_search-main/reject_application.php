<?php
session_start();
include 'db_conn.php';
include 'notification_helper.php';

// Check if user is logged in and is an employer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['userType']) || $_SESSION['userType'] !== 'employer') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['applicationId'])) {
    $applicationId = intval($_POST['applicationId']);
    $employerId = $_SESSION['user_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
   
        $verifyStmt = $conn->prepare("
            SELECT j.employerId, a.jobId, a.userId 
            FROM applications a
            JOIN jobs j ON a.jobId = j.jobs_id
            WHERE a.applications_id = ? AND j.employerId = ?
        ");
        $verifyStmt->bind_param("ii", $applicationId, $employerId);
        $verifyStmt->execute();
        $result = $verifyStmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Application not found or unauthorized');
        }

        $applicationData = $result->fetch_assoc();
        $jobId = $applicationData['jobId'];
        $applicantId = $applicationData['userId'];


        $deleteQuotationStmt = $conn->prepare("
            DELETE FROM quotations 
            WHERE applications_id = ?
        ");
        $deleteQuotationStmt->bind_param("i", $applicationId);
        $deleteQuotationStmt->execute();

        // Delete the application
        $deleteAppStmt = $conn->prepare("
            DELETE FROM applications 
            WHERE applications_id = ?
        ");
        $deleteAppStmt->bind_param("i", $applicationId);
        $deleteAppStmt->execute();

        // Update job status back to Open
        $updateJobStmt = $conn->prepare("
            UPDATE jobs 
            SET status = 'Open' 
            WHERE jobs_id = ?
        ");
        $updateJobStmt->bind_param("i", $jobId);
        $updateJobStmt->execute();

        // Add notification for the applicant
        $success = createNotification(
            $conn,
            $applicantId,
            $employerId,
            $jobId,
            'application_rejected',
            'Your job application has been rejected'
        );

        if (!$success) {
            throw new Exception("Failed to create notification");
        }

        // Commit transaction
        $conn->commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Application rejected and removed successfully']);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error rejecting application: ' . $e->getMessage()]);
    }

    // Close all prepared statements
    if (isset($verifyStmt)) $verifyStmt->close();
    if (isset($deleteQuotationStmt)) $deleteQuotationStmt->close();
    if (isset($deleteAppStmt)) $deleteAppStmt->close();
    if (isset($updateJobStmt)) $updateJobStmt->close();

} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>