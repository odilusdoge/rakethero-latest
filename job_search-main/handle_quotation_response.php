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

            // Get necessary information for transaction
            $getInfoQuery = "SELECT 
                j.jobs_id, 
                j.employerId,
                a.userId as jobseeker_id,
                j.duration,
                COALESCE(
                    (SELECT n.price 
                     FROM negotiations n 
                     WHERE n.quotation_id = q.quotations_id 
                     ORDER BY n.created_at DESC 
                     LIMIT 1),
                    q.price
                ) as final_amount
                FROM quotations q
                JOIN applications a ON q.applications_id = a.applications_id
                JOIN jobs j ON a.jobid = j.jobs_id
                WHERE q.quotations_id = ?";
            
            $stmt = $conn->prepare($getInfoQuery);
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();
            $jobInfo = $stmt->get_result()->fetch_assoc();

            // Insert into transactions
            $insertTransactionQuery = "INSERT INTO transactions 
                (employerId, jobId, duration, status, quotation_id, jobseeker_id, amount, transaction_date) 
                VALUES (?, ?, ?, 'completed', ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertTransactionQuery);
            error_log("Inserting transaction with data: " . print_r($jobInfo, true));
            $stmt->bind_param("iisidi", 
                $jobInfo['employerId'],
                $jobInfo['jobs_id'],
                $jobInfo['duration'],
                $quotationId,
                $jobInfo['jobseeker_id'],
                $jobInfo['final_amount']
            );
            if (!$stmt->execute()) {
                error_log("Error inserting transaction: " . $stmt->error);
                throw new Exception("Error creating transaction record");
            }
            $transactionId = $conn->insert_id;
            error_log("Created transaction with ID: " . $transactionId);

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