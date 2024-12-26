<?php
include 'db_conn.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Please log in to continue']));
}

// Check if required parameters are present
if (!isset($_POST['quotationId']) || !isset($_POST['action'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required parameters']));
}

$quotationId = $_POST['quotationId'];
$action = $_POST['action'];
$userId = $_SESSION['user_id'];

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Start transaction
    $conn->begin_transaction();

    // First, verify the quotation belongs to this user
    $check_query = "SELECT q.*, a.jobid, j.employerId 
                   FROM quotations q 
                   JOIN applications a ON q.applications_id = a.applications_id 
                   JOIN jobs j ON a.jobid = j.jobs_id
                   WHERE q.quotations_id = ? AND a.userid = ?";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $quotationId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Quotation not found or unauthorized");
    }

    $quotation = $result->fetch_assoc();
    $jobId = $quotation['jobid'];
    $employerId = $quotation['employerId'];

    // Update quotation status
    $newStatus = ($action === 'accept') ? 'Approved' : 'Rejected';
    $update_query = "UPDATE quotations SET status = ? WHERE quotations_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $newStatus, $quotationId);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating quotation status");
    }

    if ($action === 'accept') {
        // Update job status
        $update_job = "UPDATE jobs SET status = 'In Progress' WHERE jobs_id = ?";
        $stmt = $conn->prepare($update_job);
        $stmt->bind_param("i", $jobId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating job status");
        }

        // Update application status
        $update_app = "UPDATE applications SET status = 'Approved' WHERE jobid = ?";
        $stmt = $conn->prepare($update_app);
        $stmt->bind_param("i", $jobId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating application status");
        }

        // Create notification for employer
        $notification_message = "Your quotation has been accepted for job ID: " . $jobId;
        $insert_notif = "INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                        VALUES (?, ?, 'quotation_accepted', 0, NOW())";
        $stmt = $conn->prepare($insert_notif);
        $stmt->bind_param("is", $employerId, $notification_message);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating notification");
        }
    } else {
        // Create notification for employer about rejection
        $notification_message = "Your quotation has been rejected for job ID: " . $jobId;
        $insert_notif = "INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                        VALUES (?, ?, 'quotation_rejected', 0, NOW())";
        $stmt = $conn->prepare($insert_notif);
        $stmt->bind_param("is", $employerId, $notification_message);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating notification");
        }
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $action === 'accept' ? 'Job accepted successfully' : 'Quotation rejected successfully',
        'jobId' => $jobId
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
} 