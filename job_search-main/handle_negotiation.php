<?php
include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "Unauthorized access";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['userType']) || !isset($_POST['userId'])) {
        die("Unauthorized access");
    }

    $userType = $_POST['userType'];
    $userId = $_POST['userId'];

    // Verify that the posted user info matches the session
    if ($userId != $_SESSION['user_id'] || $userType != $_SESSION['userType']) {
        die("Invalid user credentials");
    }

    $applicationId = $_POST['applicationId'];
    $jobId = $_POST['jobId'];
    $price = $_POST['price'];
    $description = $_POST['description'];
    $validUntil = $_POST['validUntil'];
    $fromUserId = $_SESSION['user_id'];

    // Add authorization check
    error_log("User Type: " . $userType);
    error_log("User ID: " . $userId);
    error_log("Application ID: " . $applicationId);

    // Start transaction
    $conn->begin_transaction();

    try {
        if ($userType === 'employer') {
            $authQuery = "SELECT 1 FROM applications a 
                         JOIN jobs j ON a.jobid = j.jobs_id 
                         WHERE a.applications_id = ? AND j.employerId = ?";
            error_log("Employer Auth Query: " . $authQuery);
            $authStmt = $conn->prepare($authQuery);
            if (!$authStmt) {
                throw new Exception("Error preparing auth statement: " . $conn->error);
            }
            $authStmt->bind_param("ii", $applicationId, $userId);
        } else {
            $authQuery = "SELECT 1 FROM applications a 
                         WHERE a.applications_id = ? AND a.userId = ?";
            error_log("Jobseeker Auth Query: " . $authQuery);
            $authStmt = $conn->prepare($authQuery);
            if (!$authStmt) {
                throw new Exception("Error preparing auth statement: " . $conn->error);
            }
            $authStmt->bind_param("ii", $applicationId, $userId);
        }

        if (!$authStmt->execute()) {
            throw new Exception("Error executing auth statement: " . $authStmt->error);
        }
        
        $authResult = $authStmt->get_result();

        if ($authResult->num_rows === 0) {
            throw new Exception("Unauthorized access");
        }

        $authStmt->close();

        // Add at the start of the try block
        error_log("Application ID: " . $applicationId);
        error_log("Job ID: " . $jobId);
        error_log("Price: " . $price);
        error_log("Description: " . $description);
        error_log("Valid Until: " . $validUntil);

        // Get the existing quotation ID
        $getQuotationQuery = "SELECT quotations_id 
                             FROM quotations 
                             WHERE applications_id = ? 
                             ORDER BY DateCreated DESC 
                             LIMIT 1";
        
        $stmt = $conn->prepare($getQuotationQuery);
        if (!$stmt) {
            throw new Exception("Error preparing getQuotationQuery statement: " . $conn->error);
        }
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $quotation = $result->fetch_assoc();

        if (!$quotation) {
            throw new Exception("Quotation not found");
        }

        // After getting the quotation
        error_log("Quotation found: " . print_r($quotation, true));

        // Update the existing quotation
        $updateQuery = "UPDATE quotations 
                       SET price = ?,
                           description = ?,
                           valid_until = ?,
                           status = 'Negotiation'
                       WHERE quotations_id = ?";

        // Before preparing the update query
        error_log("Update Query: " . $updateQuery);

        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception("Error preparing updateQuery statement: " . $conn->error);
        }
        $stmt->bind_param("dssi", 
            $price, 
            $description, 
            $validUntil,
            $quotation['quotations_id']
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update quotation");
        }

        // Update application status
        $updateAppQuery = "UPDATE applications 
                          SET status = 'Negotiation' 
                          WHERE applications_id = ?";
        
        $stmt = $conn->prepare($updateAppQuery);
        $stmt->bind_param("i", $applicationId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update application status");
        }

        // Add notification for jobseeker
        $notifQuery = "INSERT INTO notifications (user_id, from_user_id, type, message, job_id, created_at) 
                      SELECT 
                          CASE 
                              WHEN ? = 'employer' THEN a.userId
                              ELSE j.employerId
                          END as userId,
                          ?,
                          'negotiation',
                          CASE 
                              WHEN ? = 'employer' THEN 'The employer has made a counter-offer for your application'
                              ELSE 'The jobseeker has made a counter-offer for their application'
                          END as message,
                          a.jobid,
                          NOW()
                      FROM applications a
                      JOIN jobs j ON a.jobid = j.jobs_id
                      WHERE a.applications_id = ?";

        $stmt = $conn->prepare($notifQuery);
        if (!$stmt) {
            throw new Exception("Error preparing notification query: " . $conn->error);
        }
        $stmt->bind_param("sisi", $userType, $fromUserId, $userType, $applicationId);

        if (!$stmt->execute()) {
            error_log("Notification Error: " . $stmt->error);
            throw new Exception("Failed to create notification: " . $stmt->error);
        }

        $conn->commit();
        echo "success";

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in handle_negotiation.php: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }

    $conn->close();
} else {
    echo "Invalid request method";
}
?> 