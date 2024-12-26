<?php
include 'db_conn.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$quotationId = $_POST['quotationId'] ?? null;
$action = $_POST['action'] ?? null;
$jobId = $_POST['jobId'] ?? null;
$userType = $_POST['userType'] ?? ''; // Get from POST data

if (!$quotationId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate user type
if (!in_array($userType, ['employer', 'jobseeker'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid user type']);
    exit;
}

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->begin_transaction();

    if ($action === 'accept') {
        // Initialize the query variable
        $updateQuotationQuery = "";
        
        // Update the quotation based on user type
        if ($userType === 'jobseeker') {
            $updateQuotationQuery = "UPDATE quotations 
                                   SET jobseeker_approval = 1 
                                   WHERE quotations_id = ?";
        } else if ($userType === 'employer') {
            $updateQuotationQuery = "UPDATE quotations 
                                   SET employer_approval = 1 
                                   WHERE quotations_id = ?";
        }

        // Check if query is defined
        if (empty($updateQuotationQuery)) {
            throw new Exception("Invalid user type for approval");
        }

        $stmt = $conn->prepare($updateQuotationQuery);
        $stmt->bind_param("i", $quotationId);
        $stmt->execute();

        // Check if both parties have approved
        $checkApprovalsQuery = "SELECT jobseeker_approval, employer_approval 
                              FROM quotations 
                              WHERE quotations_id = ?";
        $stmt = $conn->prepare($checkApprovalsQuery);
        $stmt->bind_param("i", $quotationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $approvals = $result->fetch_assoc();

        if ($approvals['jobseeker_approval'] == 1 && $approvals['employer_approval'] == 1) {
            // Both parties have approved - finalize the quotation
            $finalizeQuery = "UPDATE quotations 
                            SET status = 'accepted' 
                            WHERE quotations_id = ?";
            $stmt = $conn->prepare($finalizeQuery);
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();

            // Update application status
            $updateApplicationQuery = "UPDATE applications a 
                                    JOIN quotations q ON a.applications_id = q.applications_id 
                                    SET a.status = 'accepted' 
                                    WHERE q.quotations_id = ?";
            $stmt = $conn->prepare($updateApplicationQuery);
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();

            // Update job status
            $updateJobQuery = "UPDATE jobs j 
                             JOIN applications a ON j.jobs_id = a.jobid 
                             JOIN quotations q ON a.applications_id = q.applications_id 
                             SET j.status = 'Closed' 
                             WHERE q.quotations_id = ?";
            $stmt = $conn->prepare($updateJobQuery);
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();

            $message = "Both parties have accepted the quotation. The job has been finalized.";
        } else {
            $message = "Your acceptance has been recorded. Waiting for the other party's approval.";
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => $message]);
    } else if ($action === 'reject') {
        // Update quotation status
        $updateQuotationQuery = "UPDATE quotations 
                               SET status = 'rejected' 
                               WHERE quotations_id = ?";
        $stmt = $conn->prepare($updateQuotationQuery);
        $stmt->bind_param("i", $quotationId);
        $stmt->execute();

        // Update application status
        $updateApplicationQuery = "UPDATE applications a 
                                JOIN quotations q ON a.applications_id = q.applications_id 
                                SET a.status = 'rejected' 
                                WHERE q.quotations_id = ?";
        $stmt = $conn->prepare($updateApplicationQuery);
        $stmt->bind_param("i", $quotationId);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Quotation has been rejected."]);
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 