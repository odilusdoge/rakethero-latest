<?php
include 'db_conn.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $applicationId = $_POST['applicationId'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $validUntil = $_POST['validUntil'];
    $jobId = $_POST['jobId'];

    // Debug output
    error_log("Received data: " . print_r($_POST, true));

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // First, verify the table structure
    $tableCheck = $conn->query("DESCRIBE quotations");
    if ($tableCheck) {
        error_log("Table structure: " . print_r($tableCheck->fetch_all(), true));
    } else {
        error_log("Error checking table structure: " . $conn->error);
    }

    // Insert quotation with job_id
    $query = "INSERT INTO quotations (applications_id, price, description, status, valid_until, jobid) 
              VALUES (?, ?, ?, 'Pending', ?, ?)";
    
    // Debug the query
    error_log("Preparing query: " . $query);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Error preparing statement: " . $conn->error);
    }

    // Debug the parameters
    error_log("Binding parameters: applicationId=$applicationId, amount=$amount, description=$description, validUntil=$validUntil, jobId=$jobId");

    $stmt->bind_param("idssi", $applicationId, $amount, $description, $validUntil, $jobId);
    
    if ($stmt->execute()) {
        echo "Quotation sent successfully";
        error_log("Quotation inserted successfully");

        // After successfully creating the quotation, update the application status
        $updateApplicationQuery = "UPDATE applications 
            SET status = 'Accepted' 
            WHERE applications_id = ?";

        $stmt = $conn->prepare($updateApplicationQuery);
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
    } else {
        $error = "Error sending quotation: " . $stmt->error;
        error_log($error);
        echo $error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Invalid request method";
}
?> 