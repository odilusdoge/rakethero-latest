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

if (!$quotationId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    if ($action === 'accept') {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update quotation status
            $updateQuotationQuery = "UPDATE quotations SET status = 'Accepted' WHERE quotations_id = ?";
            $stmt = $conn->prepare($updateQuotationQuery);
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();

            // Get necessary information for transaction
            $getInfoQuery = "SELECT 
                q.price as amount,
                j.employerId,
                j.jobs_id,
                j.duration,
                a.userid as jobseeker_id
            FROM quotations q
            JOIN applications a ON q.applications_id = a.applications_id
            JOIN jobs j ON a.jobid = j.jobs_id
            WHERE q.quotations_id = ?";
            
            $stmt = $conn->prepare($getInfoQuery);
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $info = $result->fetch_assoc();

            // Insert into transactions
            $insertTransactionQuery = "INSERT INTO transactions 
                (employerId, jobId, jobseeker_id, quotation_id, amount, duration, status, transaction_date) 
            VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())";
            
            $stmt = $conn->prepare($insertTransactionQuery);
            $stmt->bind_param("iiiiis", 
                $info['employerId'],
                $info['jobs_id'],
                $info['jobseeker_id'],
                $quotationId,
                $info['amount'],
                $info['duration']
            );
            $stmt->execute();

            // Update application status
            $updateApplicationQuery = "UPDATE applications a 
                JOIN quotations q ON a.applications_id = q.applications_id 
                SET a.status = 'Completed' 
                WHERE q.quotations_id = ?";
            $stmt = $conn->prepare($updateApplicationQuery);
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();

            // Update job status to closed
            $updateJobQuery = "UPDATE jobs j 
                JOIN applications a ON j.jobs_id = a.jobid 
                JOIN quotations q ON a.applications_id = q.applications_id 
                SET j.status = 'Closed' 
                WHERE q.quotations_id = ?";
            $stmt = $conn->prepare($updateJobQuery);
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();

            // Commit transaction
            $conn->commit();

            $response = array('success' => true, 'message' => 'Quotation accepted successfully');
            echo json_encode($response);
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $response = array('success' => false, 'message' => 'Error: ' . $e->getMessage());
            echo json_encode($response);
        }
    } else if ($action === 'reject') {
        // ... existing reject logic ...
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Error in handle_quotation_response.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 