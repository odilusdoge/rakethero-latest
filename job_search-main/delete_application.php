<?php
include 'db_conn.php';
session_start();

// Debug logging
error_log("Delete application request received");
error_log("POST data: " . print_r($_POST, true));
error_log("Session user ID: " . $_SESSION['user_id']);

if (!isset($_SESSION['user_id'])) {
    die("Please log in to delete applications");
}

if (!isset($_POST['applicationId'])) {
    die("Application ID is required");
}

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $applicationId = $_POST['applicationId'];
    $userId = $_SESSION['user_id'];

    error_log("Attempting to delete application ID: $applicationId for user ID: $userId");

    // First check if the application exists and belongs to the user
    $check_query = "SELECT applications_id, jobid FROM applications WHERE applications_id = ? AND userid = ?";
    $stmt = $conn->prepare($check_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $applicationId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("Application not found or unauthorized. App ID: $applicationId, User ID: $userId");
        die("Application not found or you don't have permission to delete it");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete related quotations first (if any)
        $delete_quotations = "DELETE FROM quotations WHERE applications_id = ?";
        $stmt = $conn->prepare($delete_quotations);
        if (!$stmt) {
            throw new Exception("Prepare failed for quotations delete: " . $conn->error);
        }
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();

        // Then delete the application
        $delete_query = "DELETE FROM applications WHERE applications_id = ? AND userid = ?";
        $stmt = $conn->prepare($delete_query);
        if (!$stmt) {
            throw new Exception("Prepare failed for application delete: " . $conn->error);
        }

        $stmt->bind_param("ii", $applicationId, $userId);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo "Application deleted successfully";
        } else {
            throw new Exception("Error deleting application: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in delete_application.php: " . $e->getMessage());
    die($e->getMessage());
}
?> 