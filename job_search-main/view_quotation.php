<?php
include 'db_conn.php';
session_start();

// Add debug logging for session
error_log("Session contents: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session");
    header("Location: index.php");
    exit();
}

// Define user type variables first
$userType = 'jobseeker'; // This is always the jobseeker view
error_log("User Type set to: " . $userType);

$isEmployer = false; // This is always false in this view

// Check if quotation ID is provided
if (!isset($_GET['quotation_id']) || !isset($_GET['job_id'])) {
    header("Location: jobseekerlanding.php");
    exit();
}

$quotationId = $_GET['quotation_id'];
$jobId = $_GET['job_id'];

// First define the query
$query = "SELECT 
    q.*, 
    COALESCE(
        (SELECT n2.price 
         FROM negotiations n2 
         WHERE n2.quotation_id = q.quotations_id 
         ORDER BY n2.created_at DESC 
         LIMIT 1),
        q.price
    ) as current_price,
    q.valid_until,
    q.jobseeker_approval,
    q.employer_approval,
    a.proposal,
    a.applicationDate,
    a.status as application_status,
    j.title as job_title,
    j.description as job_description,
    j.location,
    j.payType,
    j.price as job_price,
    j.duration,
    j.employerId,
    u.username as employer_name,
    ui.contactNo as employer_contact,
    ui.email as employer_email,
    ui.location as employer_address,
    ui.fname as employer_firstname,
    ui.lname as employer_lastname
FROM quotations q
JOIN applications a ON q.applications_id = a.applications_id
JOIN jobs j ON a.jobid = j.jobs_id
JOIN users u ON j.employerId = u.users_id
LEFT JOIN user_info ui ON j.employerId = ui.userid
WHERE q.quotations_id = ? AND j.jobs_id = ?";

// Then add the debugging logs
error_log("Quotation ID: " . $quotationId);
error_log("Job ID: " . $jobId);

// Debug query with actual values
$debug_query = str_replace('?', $quotationId, str_replace('?', $jobId, $query));
error_log("Debug query: " . $debug_query);

// After the query definition and before prepare statement, add error logging
if ($conn->error) {
    error_log("Database connection error: " . $conn->error);
    die("Connection error: " . $conn->error);
}

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("ii", $quotationId, $jobId);
$stmt->execute();
$result = $stmt->get_result();
$quotation = $result->fetch_assoc();

if (!$quotation) {
    header("Location: jobseekerlanding.php");
    exit();
}

// Add this after fetching the quotation
error_log("Employer Contact: " . $quotation['employer_contact']);
error_log("Employer Email: " . $quotation['employer_email']);
error_log("Employer Address: " . $quotation['employer_address']);
error_log("Employer First Name: " . $quotation['employer_firstname']);
error_log("Employer Last Name: " . $quotation['employer_lastname']);

// Also let's verify the employer ID is correct
error_log("Employer ID: " . $quotation['employerId']);

// Add this after fetching the quotation
error_log("Quotation Status: " . $quotation['status']);

// Add this after getting the quotation
error_log("Debug - User Type: " . $userType);
error_log("Debug - Is Employer: " . ($isEmployer ? 'true' : 'false'));
error_log("Debug - Quotation Status: " . $quotation['status']);

// Replace the existing history query with this version
$historyQuery = "SELECT DISTINCT
    n.negotiation_id,
    n.price,
    n.description,
    n.valid_until,
    n.status,
    n.created_at,
    CASE 
        WHEN n.created_by = j.employerId THEN CONCAT(ui_emp.fname, ' ', ui_emp.lname)
        ELSE CONCAT(ui_js.fname, ' ', ui_js.lname)
    END as offered_by,
    CASE 
        WHEN n.created_by = j.employerId THEN 'Employer'
        ELSE 'Jobseeker'
    END as user_type
FROM negotiations n
JOIN quotations q ON n.quotation_id = q.quotations_id
JOIN applications a ON q.applications_id = a.applications_id
JOIN jobs j ON a.jobid = j.jobs_id
LEFT JOIN user_info ui_emp ON j.employerId = ui_emp.userid
LEFT JOIN user_info ui_js ON a.userId = ui_js.userid
WHERE q.quotations_id = ?
GROUP BY n.negotiation_id
ORDER BY n.created_at DESC";

$historyStmt = $conn->prepare($historyQuery);
if (!$historyStmt) {
    error_log("Query preparation failed: " . $conn->error);
    $quotationHistory = array();
} else {
    $historyStmt->bind_param("i", $quotationId);
    if (!$historyStmt->execute()) {
        error_log("Query execution failed: " . $historyStmt->error);
        $quotationHistory = array();
    } else {
        $quotationHistory = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Details - RaketHero</title>
    
    <!-- Include your existing stylesheets -->
    <link href="assets/css/theme.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <style>
        .quotation-card {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-badge.pending { background-color: #fff3cd; color: #856404; }
        .status-badge.accepted { background-color: #d4edda; color: #155724; }
        .status-badge.rejected { background-color: #f8d7da; color: #721c24; }

        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .detail-row {
            margin-bottom: 15px;
        }

        .detail-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .back-button {
            margin-bottom: 20px;
        }

        .employer-details {
            margin-top: 10px;
        }

        .employer-details p {
            margin-bottom: 8px;
        }

        .employer-details p:last-child {
            margin-bottom: 0;
        }

        .btn-success, .btn-danger, .btn-secondary {
            min-width: 140px;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        .employer-details strong {
            color: #2c3e50;
            display: inline-block;
            width: 100px;
        }

        .btn-sm {
            padding: 0.25rem 1rem;
            font-size: 0.875rem;
            min-width: 100px;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        .modal-content {
            border-radius: 10px;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .contact-info p {
            margin-bottom: 0.5rem;
        }

        .contact-info strong {
            display: inline-block;
            width: 120px;
            color: #2c3e50;
        }

        .negotiation-item {
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .negotiation-item:hover {
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .negotiation-item strong {
            color: #2c3e50;
        }
        
        .negotiation-item small {
            color: #6c757d;
        }

        .negotiation-history {
            margin-top: 10px;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
        }

        .negotiation-item {
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .negotiation-item:hover {
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .status-badge.negotiation {
            background-color: #cce5ff;
            color: #004085;
        }

        .negotiation-item .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
    </style>
</head>
<body>
    <main class="main" id="top">
        <!-- Navbar (you can include your existing navbar here) -->
        
        <div class="container mt-5 pt-5">
            <div class="back-button">
                <a href="jobseekerlanding.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Jobs
                </a>
            </div>

            <div class="quotation-card">
                <h2 class="section-title">Quotation Details</h2>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="detail-row">
                            <div class="detail-label">Job Title</div>
                            <div><?php echo htmlspecialchars($quotation['job_title']); ?></div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Employer</div>
                            <div><?php 
                                $fullName = trim(htmlspecialchars($quotation['employer_firstname'] . ' ' . $quotation['employer_lastname']));
                                echo $fullName;
                            ?></div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Location</div>
                            <div><?php echo htmlspecialchars($quotation['location']); ?></div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Original Job Price</div>
                            <div>PHP <?php echo number_format($quotation['job_price'], 2); ?></div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Quoted Price</div>
                            <div>PHP <?php echo number_format($quotation['price'], 2); ?></div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Status</div>
                            <div>
                                <span class="status-badge <?php echo strtolower($quotation['status']); ?>">
                                    <?php echo htmlspecialchars($quotation['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Valid Until</div>
                            <div><?php 
                                if (!empty($quotation['valid_until'])) {
                                    echo date('F j, Y', strtotime($quotation['valid_until']));
                                } else {
                                    echo "Not specified";
                                }
                            ?></div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Application Date</div>
                            <div><?php echo date('F j, Y', strtotime($quotation['applicationDate'])); ?></div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Employer Contact Details</div>
                            <div class="employer-details p-3 bg-light rounded">
                                <p><strong>Contact No:</strong> <?php echo htmlspecialchars($quotation['employer_contact']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($quotation['employer_email']); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($quotation['employer_address']); ?></p>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Your Proposal</div>
                            <div class="proposal-text p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($quotation['proposal'])); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Quotation Description</div>
                            <div class="proposal-text p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($quotation['description'])); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Negotiation History</div>
                            <div class="negotiation-history">
                                <?php if (empty($quotationHistory)): ?>
                                    <p class="text-muted">No previous negotiations.</p>
                                <?php else: ?>
                                    <?php foreach ($quotationHistory as $history): ?>
                                        <div class="negotiation-item mb-3 p-3" style="border-left: 3px solid #007bff; background-color: #f8f9fa;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong>
                                                    <?php 
                                                        echo htmlspecialchars($history['offered_by'] ?: 'Unknown User');
                                                        echo ' (' . htmlspecialchars($history['user_type']) . ')';
                                                    ?>
                                                </strong>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($history['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="mt-2">
                                                <p class="mb-1">
                                                    <strong>Offered Price:</strong> 
                                                    PHP <?php echo number_format($history['price'], 2); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Valid Until:</strong> 
                                                    <?php echo date('M j, Y', strtotime($history['valid_until'])); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Status:</strong> 
                                                    <span class="status-badge <?php echo strtolower($history['status']); ?>">
                                                        <?php echo htmlspecialchars($history['status']); ?>
                                                    </span>
                                                </p>
                                                <?php if (!empty($history['description'])): ?>
                                                    <p class="mb-0">
                                                        <strong>Message:</strong><br>
                                                        <?php echo nl2br(htmlspecialchars($history['description'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add this before the buttons section to \\ -->
                <div style="display:none">
                    Debug Info:
                    <pre>
                    User Type: <?php echo $userType; ?>
                    Is Employer: <?php echo $isEmployer ? 'true' : 'false'; ?>
                    Quotation Status: <?php echo $quotation['status']; ?>
                    </pre>
                </div>

                <div class="alert alert-info" style="margin-bottom: 10px;">
                    Debug Info:
                    <ul>
                        <li>Status: "<?php echo $quotation['status']; ?>"</li>
                        <li>User Type: <?php echo $userType; ?></li>
                        <li>Session User Type: <?php echo $_SESSION['userType']; ?></li>
                        <li>Session User ID: <?php echo $_SESSION['user_id']; ?></li>
                        <li>Is Pending: <?php echo ($quotation['status'] === 'pending' || $quotation['status'] === 'Pending') ? 'Yes' : 'No'; ?></li>
                        <li>Is Negotiation: <?php echo ($quotation['status'] === 'negotiation' || $quotation['status'] === 'Negotiation') ? 'Yes' : 'No'; ?></li>
                    </ul>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <?php if (strtolower($quotation['status']) === 'pending' || strtolower($quotation['status']) === 'negotiation'): ?>
                        <!-- Accept button with approval check -->
                        <?php if (!$quotation['jobseeker_approval']): ?>
                            <button class="btn btn-success btn-sm" onclick="handleQuotation('accept')">
                                Accept Quotation
                            </button>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Waiting for employer's approval
                            </div>
                        <?php endif; ?>
                        
                        <!-- Always show Negotiate and Reject buttons -->
                        <button class="btn btn-warning btn-sm" onclick="showNegotiateModal()">
                            Negotiate
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="handleQuotation('reject')">
                            Reject
                        </button>
                    <?php elseif (strtolower($quotation['status']) === 'accepted'): ?>
                        <div class="alert alert-success">
                            This quotation has been accepted by both parties.
                        </div>
                    <?php elseif (strtolower($quotation['status']) === 'rejected'): ?>
                        <div class="alert alert-danger">
                            This quotation has been rejected.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Contact Information Modal -->
    <div class="modal fade" id="employerContactModal" tabindex="-1" aria-labelledby="employerContactModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="employerContactModalLabel">Job Accepted Successfully!</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-4">Please contact the employer using the following information:</p>
                    <div class="contact-info">
                        <p><strong>Employer Name:</strong> <span id="modalEmployerName"></span></p>
                        <p><strong>Contact No:</strong> <span id="modalEmployerContact"></span></p>
                        <p><strong>Email:</strong> <span id="modalEmployerEmail"></span></p>
                        <p><strong>Address:</strong> <span id="modalEmployerAddress"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="closeModalAndRedirect()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Negotiate Quotation Modal -->
    <div class="modal fade" id="negotiateQuotationModal" tabindex="-1" aria-labelledby="negotiateModalLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="negotiateModalLabel">Negotiate Quotation for <?php echo htmlspecialchars($quotation['job_title']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quotationAmount" class="form-label">Amount (PHP)</label>
                        <input type="number" 
                               class="form-control" 
                               id="quotationAmount" 
                               name="quotationAmount"
                               value="<?php echo $quotation['price']; ?>" 
                               required
                               aria-label="Enter quotation amount"
                               placeholder="Enter amount in PHP"
                               min="0"
                               step="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="quotationDescription" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="quotationDescription" 
                                  name="quotationDescription"
                                  rows="4" 
                                  required
                                  aria-label="Enter quotation description"
                                  placeholder="Enter detailed description of your quotation"><?php echo htmlspecialchars($quotation['description']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="quotationValidUntil" class="form-label">Valid Until</label>
                        <input type="date" 
                               class="form-control" 
                               id="quotationValidUntil" 
                               name="quotationValidUntil"
                               required
                               aria-label="Select validity date"
                               title="Select the date until which this quotation is valid">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" 
                            class="btn btn-secondary" 
                            data-bs-dismiss="modal"
                            title="Cancel negotiation"
                            aria-label="Cancel">
                        Cancel
                    </button>
                    <button type="button" 
                            class="btn btn-primary" 
                            onclick="submitNegotiation('<?php echo $quotation['applications_id']; ?>', '<?php echo $jobId; ?>')"
                            title="Send counter offer"
                            aria-label="Send counter offer">
                        Send Counter Offer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showEmployerContactModal(employerName, employerContact, employerEmail, employerAddress) {
            document.getElementById('modalEmployerName').textContent = employerName;
            document.getElementById('modalEmployerContact').textContent = employerContact;
            document.getElementById('modalEmployerEmail').textContent = employerEmail;
            document.getElementById('modalEmployerAddress').textContent = employerAddress;
            
            const modal = new bootstrap.Modal(document.getElementById('employerContactModal'));
            modal.show();
        }

        function closeModalAndRedirect() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('employerContactModal'));
            modal.hide();
            window.location.href = 'jobseekerlanding.php';
        }

        function handleQuotation(action) {
            if (!confirm('Are you sure you want to ' + action + ' this quotation?')) {
                return;
            }

            // Debug log
            console.log('User Type:', 'jobseeker');  // Always jobseeker in this view
            console.log('Action:', action);

            fetch('handle_quotation_response.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `quotationId=<?php echo $quotationId; ?>&action=${action}&jobId=<?php echo $jobId; ?>&userType=jobseeker`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing request');
            });
        }

        function cancelJob(applicationId) {
            if (confirm('Are you sure you want to cancel this job?')) {
                fetch('delete_application.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `applicationId=${applicationId}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('success')) {
                        alert('Job cancelled successfully');
                        window.location.href = 'jobseekerlanding.php';
                    } else {
                        alert(data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error canceling job');
                });
            }
        }

        function showNegotiateModal() {
            const modal = new bootstrap.Modal(document.getElementById('negotiateQuotationModal'));
            modal.show();
        }

        function submitNegotiation(applicationId, jobId) {
            // Add authorization check
            const userType = '<?php echo $userType; ?>';
            const userId = '<?php echo $_SESSION['user_id']; ?>';
            
            const amount = document.getElementById('quotationAmount').value;
            const description = document.getElementById('quotationDescription').value;
            const validUntil = document.getElementById('quotationValidUntil').value;

            if (!amount || !description || !validUntil) {
                alert('Please fill in all fields');
                return;
            }

            fetch('handle_negotiation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `quotationId=<?php echo $quotationId; ?>&applicationId=${applicationId}&jobId=${jobId}&price=${amount}&description=${encodeURIComponent(description)}&validUntil=${validUntil}&update=true&userType=${userType}&userId=${userId}`
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success')) {
                    alert('Counter offer updated successfully!');
                    window.location.reload(); // Reload the page to show updated history
                } else {
                    alert(data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending counter offer');
            });
        }
    </script>
</body>
</html> 