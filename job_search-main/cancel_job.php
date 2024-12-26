<?php
include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "Error: Not logged in";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $applicationId = $_POST['applicationId'];
    $quotationId = $_POST['quotationId'];
    
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete quotation first (due to foreign key constraints)
        $deleteQuotation = $conn->prepare("DELETE FROM quotations WHERE quotations_id = ? AND applications_id = ?");
        if (!$deleteQuotation) {
            throw new Exception("Error preparing quotation deletion: " . $conn->error);
        }
        $deleteQuotation->bind_param("ii", $quotationId, $applicationId);
        if (!$deleteQuotation->execute()) {
            throw new Exception("Error deleting quotation: " . $deleteQuotation->error);
        }

        // Then delete the application
        $deleteApplication = $conn->prepare("DELETE FROM applications WHERE applications_id = ?");
        if (!$deleteApplication) {
            throw new Exception("Error preparing application deletion: " . $conn->error);
        }
        $deleteApplication->bind_param("i", $applicationId);
        if (!$deleteApplication->execute()) {
            throw new Exception("Error deleting application: " . $deleteApplication->error);
        }

        // If we get here, commit the transaction
        $conn->commit();
        echo "Job cancelled successfully";
    } catch (Exception $e) {
        // If there's an error, rollback the changes
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }

    $conn->close();
} else {
    echo "Invalid request method";
}
?> 