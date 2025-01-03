<?php
include 'db_conn.php';
session_start();

// Check if user is logged in and is an employer
if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'employer') {
    header("Location: index.php");
    exit();
}

// Check if quotation ID is provided
if (!isset($_GET['quotation_id']) || !isset($_GET['job_id'])) {
    header("Location: employerlanding.php");
    exit();
}

$quotationId = $_GET['quotation_id'];
$jobId = $_GET['job_id'];

// Query to get quotation details
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
    u.username as jobseeker_name,
    ui.contactNo as jobseeker_contact,
    ui.email as jobseeker_email,
    ui.location as jobseeker_address,
    ui.fname as jobseeker_firstname,
    ui.lname as jobseeker_lastname
FROM quotations q
JOIN applications a ON q.applications_id = a.applications_id
JOIN jobs j ON a.jobid = j.jobs_id
JOIN users u ON a.userId = u.users_id
LEFT JOIN user_info ui ON a.userId = ui.userid
WHERE q.quotations_id = ? AND j.jobs_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $quotationId, $jobId);
$stmt->execute();
$result = $stmt->get_result();
$quotation = $result->fetch_assoc();

if (!$quotation) {
    header("Location: employerlanding.php");
    exit();
}

// After the existing query, add this new query to get quotation history
$historyQuery = "SELECT DISTINCT
    n.negotiation_id,
    n.price,
    n.description,
    n.valid_until,
    n.status,
    n.created_at,
    n.created_by,
    CASE 
        WHEN n.created_by = j.employerId THEN CONCAT(ui_emp.fname, ' ', ui_emp.lname)
        ELSE CONCAT(ui_js.fname, ' ', ui_js.lname)
    END as offered_by,
    CASE 
        WHEN n.created_by = j.employerId THEN 'Employer'
        ELSE 'Jobseeker'
    END as user_type,
    q.jobseeker_approval,
    q.employer_approval,
    q.status as quotation_status
FROM negotiations n
JOIN quotations q ON n.quotation_id = q.quotations_id
JOIN applications a ON q.applications_id = a.applications_id
JOIN jobs j ON a.jobid = j.jobs_id
LEFT JOIN user_info ui_emp ON j.employerId = ui_emp.userid
LEFT JOIN user_info ui_js ON a.userId = ui_js.userid
WHERE q.quotations_id = ?
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

// Display the history in HTML
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
        /* Copy the styles from view_quotation.php */
        .quotation-card {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .detail-row {
            margin-bottom: 20px;
        }

        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-badge.pending { background-color: #fff3cd; color: #856404; }
        .status-badge.accepted { background-color: #d4edda; color: #155724; }
        .status-badge.rejected { background-color: #f8d7da; color: #721c24; }
        .status-badge.negotiation { background-color: #cce5ff; color: #004085; }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .proposal-text {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        /* Add to existing styles */
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

        .quotation-history {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .quotation-history h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .quotation-history .table {
            background-color: white;
        }

        .quotation-history .table th {
            background-color: #f1f1f1;
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
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3 backdrop">
            <div class="container">
                <a class="navbar-brand" href="employerlanding.php">
                    <span class="text-info">Raket</span><span class="text-warning">Hero</span>
                </a>
                <button type="button" class="btn-close" onclick="window.location.href='employerlanding.php'"></button>
            </div>
        </nav>

        <div class="container mt-5 pt-5">
            <div class="back-button">
                <a href="employerlanding.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <div class="quotation-card">
                <h2 class="section-title">Quotation Details</h2>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="detail-row">
                            <div class="detail-label">Job Title</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($quotation['job_title']); ?></div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Jobseeker Details</div>
                            <div class="proposal-text">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($quotation['jobseeker_firstname'] . ' ' . $quotation['jobseeker_lastname']); ?></p>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($quotation['jobseeker_contact']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($quotation['jobseeker_email']); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($quotation['jobseeker_address']); ?></p>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Price Details</div>
                            <div class="proposal-text">
                                <p><strong>Original Job Price:</strong> PHP <?php echo number_format($quotation['job_price'], 2); ?></p>
                                <p><strong>Current Quoted Price:</strong> PHP <?php echo number_format($quotation['current_price'], 2); ?></p>
                            </div>
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
                            <div class="detail-label">Jobseeker's Proposal</div>
                            <div class="proposal-text">
                                <?php echo nl2br(htmlspecialchars($quotation['proposal'])); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Quotation Description</div>
                            <div class="proposal-text">
                                <?php echo nl2br(htmlspecialchars($quotation['description'])); ?>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-label">Valid Until</div>
                            <div><?php echo date('F j, Y', strtotime($quotation['valid_until'])); ?></div>
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
                                                    <?php if ($history['quotation_status'] === 'accepted'): ?>
                                                        <span class="status-badge accepted">Accepted by both parties</span>
                                                    <?php elseif ($history['jobseeker_approval'] && !$history['employer_approval']): ?>
                                                        <span class="status-badge pending">Accepted by Jobseeker</span>
                                                    <?php elseif (!$history['jobseeker_approval'] && $history['employer_approval']): ?>
                                                        <span class="status-badge pending">Accepted by Employer</span>
                                                    <?php else: ?>
                                                        <span class="status-badge <?php echo strtolower($history['status']); ?>">
                                                            <?php echo htmlspecialchars($history['status']); ?>
                                                        </span>
                                                    <?php endif; ?>
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

                        <!-- Add debug info -->
                        <div class="alert alert-info" style="margin-bottom: 10px;">
                            Debug Info:
                            <ul>
                                <li>Status: "<?php echo $quotation['status']; ?>"</li>
                                <li>User Type: <?php echo $_SESSION['userType']; ?></li>
                                <li>Is Pending: <?php echo (strtolower($quotation['status']) === 'pending') ? 'Yes' : 'No'; ?></li>
                                <li>Is Negotiation: <?php echo (strtolower($quotation['status']) === 'negotiation') ? 'Yes' : 'No'; ?></li>
                                <li>Employer Approval: <?php echo $quotation['employer_approval'] ? 'Yes' : 'No'; ?></li>
                            </ul>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <?php if (strtolower($quotation['status']) === 'pending' || strtolower($quotation['status']) === 'negotiation'): ?>
                                <?php if (!$quotation['employer_approval']): ?>
                                    <button class="btn btn-success btn-sm" onclick="handleQuotation('accept')">
                                        Accept Quotation
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        Waiting for jobseeker's approval
                                    </div>
                                <?php endif; ?>
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
            </div>
        </div>
    </main>

    <!-- Negotiate Modal -->
    <div class="modal fade" id="negotiateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Make Counter Offer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount (PHP)</label>
                        <input type="number" class="form-control" id="counterOfferAmount" value="<?php echo $quotation['price']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="counterOfferDescription" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valid Until</label>
                        <input type="date" class="form-control" id="counterOfferValidUntil" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitCounterOffer()">Send Counter Offer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showNegotiateModal() {
            // Set minimum date for validUntil to today
            document.getElementById('counterOfferValidUntil').min = new Date().toISOString().split('T')[0];
            document.getElementById('counterOfferValidUntil').value = new Date().toISOString().split('T')[0];
            
            const modal = new bootstrap.Modal(document.getElementById('negotiateModal'));
            modal.show();
        }

        function handleQuotation(action) {
            if (!confirm('Are you sure you want to ' + action + ' this quotation?')) {
                return;
            }

            fetch('handle_quotation_response.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `quotationId=<?php echo $quotationId; ?>&action=${action}&jobId=<?php echo $jobId; ?>&userType=employer`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.href = 'employerlanding.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing request');
            });
        }

        function submitCounterOffer() {
            const amount = document.getElementById('counterOfferAmount').value;
            const description = document.getElementById('counterOfferDescription').value;
            const validUntil = document.getElementById('counterOfferValidUntil').value;

            if (!amount || !description || !validUntil) {
                alert('Please fill in all fields');
                return;
            }

            fetch('handle_negotiation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `quotationId=<?php echo $quotationId; ?>&applicationId=<?php echo $quotation['applications_id']; ?>&jobId=<?php echo $jobId; ?>&price=${amount}&description=${encodeURIComponent(description)}&validUntil=${validUntil}&update=true&userType=<?php echo $_SESSION['userType']; ?>&userId=<?php echo $_SESSION['user_id']; ?>`
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success')) {
                    alert('Counter offer sent successfully!');
                    // Close the modal first
                    const modal = bootstrap.Modal.getInstance(document.getElementById('negotiateModal'));
                    modal.hide();
                    
                    // Reload only the necessary parts
                    fetch(window.location.href)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            // Update negotiation history
                            document.querySelector('.negotiation-history').innerHTML = 
                                doc.querySelector('.negotiation-history').innerHTML;
                            
                            // Update current price
                            document.querySelector('.detail-label + div:contains("Current Quoted Price")').innerHTML = 
                                doc.querySelector('.detail-label + div:contains("Current Quoted Price")').innerHTML;
                            
                            // Update status badge
                            document.querySelector('.status-badge').outerHTML = 
                                doc.querySelector('.status-badge').outerHTML;
                            
                            // Ensure buttons are still visible
                            document.querySelector('.mt-4.d-flex.gap-2').innerHTML = 
                                doc.querySelector('.mt-4.d-flex.gap-2').innerHTML;
                        });
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