<?php
include 'db_conn.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quotationId = $_POST['quotationId'];
    $applicationId = $_POST['applicationId'];
    $jobId = $_POST['jobId'];
    $price = $_POST['price'];
    $description = $_POST['description'];
    $validUntil = $_POST['validUntil'];
    $userType = $_POST['userType'];
    $userId = $_POST['userId'];

    try {
        $conn->begin_transaction();

        // First get the employer_id and jobseeker_id
        $getIdsQuery = "SELECT j.employerId, a.userId as jobseeker_id 
                        FROM applications a 
                        JOIN jobs j ON a.jobid = j.jobs_id 
                        WHERE a.applications_id = ?";
        $stmt = $conn->prepare($getIdsQuery);
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = $result->fetch_assoc();

        // First, insert into quotations table if quotationId is empty
        if (empty($quotationId)) {
            $insertQuotationQuery = "INSERT INTO quotations (applications_id, price, description, valid_until, status) 
                                    VALUES (?, ?, ?, ?, 'Pending')";
            $stmt = $conn->prepare($insertQuotationQuery);
            $stmt->bind_param("idss", $applicationId, $price, $description, $validUntil);
            $stmt->execute();
            $quotationId = $conn->insert_id;
        }

        // Insert into negotiations table
        $negotiationQuery = "INSERT INTO negotiations (quotation_id, employer_id, jobseeker_id, price, description, valid_until, created_by, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($negotiationQuery);
        $stmt->bind_param("iiidssi", $quotationId, $ids['employerId'], $ids['jobseeker_id'], $price, $description, $validUntil, $userId);
        $stmt->execute();

        // Update application status
        $updateApplicationQuery = "UPDATE applications 
                                 SET status = 'negotiation' 
                                 WHERE applications_id = ?";
        $stmt = $conn->prepare($updateApplicationQuery);
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();

        $conn->commit();
        echo "success";
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in handle_negotiation.php: " . $e->getMessage());
        echo "Error processing negotiation: " . $e->getMessage();
    }
} else {
    echo "Invalid request method";
}
?> 