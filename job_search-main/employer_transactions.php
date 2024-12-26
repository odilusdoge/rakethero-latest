<?php
include 'db_conn.php';
session_start();

// Check if user is logged in and is an employer
if (!isset($_SESSION['user_id']) || $_SESSION['userType'] !== 'employer') {
    header("Location: index.php");
    exit();
}

$employerId = $_SESSION['user_id'];

// Query to get all accepted jobs/quotations with jobseeker details
$query = "SELECT DISTINCT
    t.transactions_id,
    t.amount as quoted_price,
    t.transaction_date as applicationDate,
    t.status as transaction_status,
    t.quotation_id,
    j.jobs_id as jobId,
    j.title as job_title,
    j.description as job_description,
    j.location as job_location,
    j.price as original_price,
    u.username as jobseeker_username,
    u.users_id as jobseeker_id,
    ui.fname as jobseeker_firstname,
    ui.lname as jobseeker_lastname,
    ui.contactNo as jobseeker_contact,
    ui.email as jobseeker_email,
    ui.location as jobseeker_address,
    a.proposal as jobseeker_proposal,
    q.quotations_id,
    q.description as quotation_description
FROM 
    transactions t
JOIN 
    jobs j ON t.jobId = j.jobs_id
JOIN 
    users u ON t.jobseeker_id = u.users_id
LEFT JOIN 
    user_info ui ON t.jobseeker_id = ui.userid
JOIN 
    quotations q ON t.quotation_id = q.quotations_id
JOIN 
    applications a ON q.applications_id = a.applications_id
WHERE 
    t.employerId = ?
ORDER BY 
    t.transaction_date DESC";

// Add error logging
error_log("Preparing query for employer ID: " . $employerId);

// Prepare statement with error handling
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Error preparing statement: " . $conn->error);
}

// Bind parameter with error handling
if (!$stmt->bind_param("i", $employerId)) {
    error_log("Binding parameters failed: " . $stmt->error);
    die("Error binding parameters: " . $stmt->error);
}

// Execute with error handling
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("Error executing statement: " . $stmt->error);
}

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - RaketHero</title>
     <!-- Favicons -->
     <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon.ico">
        <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon.ico">
        <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
        <link rel="manifest" href="assets/img/favicons/manifest.json">
        <meta name="msapplication-TileImage" content="assets/img/favicons/favicon.ico">
        <meta name="theme-color" content="#ffffff">

    <link href="assets/css/theme.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <style>
        .transaction-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .transaction-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .transaction-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .transaction-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .transaction-details {
            margin-top: 15px;
        }

        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            width: 150px;
            display: inline-block;
        }

        .jobseeker-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .proposal-section {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #e9ecef;
        }

        .stars {
            font-size: 24px;
        }

        .stars .bi-star:hover,
        .stars .bi-star.active {
            color: #FFD700 !important; /* Golden yellow */
        }

        .rating-star {
            transition: color 0.2s;
            cursor: pointer;
        }

        .rating-star:hover {
            color: #FFD700 !important; /* Golden yellow */
        }

        .text-warning {
            color: #FFD700 !important; /* Golden yellow - override Bootstrap's warning color */
        }

        /* Add hover effect for unrated stars */
        .rate-jobseeker .stars i:hover ~ i {
            color: inherit !important;
        }

        /* Make stars slightly bigger and add spacing */
        .stars i {
            font-size: 28px;
            margin-right: 5px;
        }

        /* Add hover animation */
        .stars i:hover {
            transform: scale(1.1);
            transition: transform 0.2s;
        }
    </style>
</head>
<body>
    <main class="main" id="top">
        <div class="container mt-5 pt-5">
            <div class="mb-4">
                <a href="employerlanding.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <h2 class="mb-4">Transaction History</h2>

            <?php if ($result->num_rows > 0): ?>
                <?php while($transaction = $result->fetch_assoc()): ?>
                    <div class="transaction-card">
                        <div class="transaction-header">
                            <div class="transaction-title"><?php echo htmlspecialchars($transaction['job_title']); ?></div>
                            <div class="transaction-date">
                                Accepted on: <?php echo date('F j, Y', strtotime($transaction['applicationDate'])); ?>
                            </div>
                        </div>

                        <div class="transaction-details">
                            <p><span class="detail-label">Original Price:</span> PHP <?php echo number_format($transaction['original_price'], 2); ?></p>
                            <p><span class="detail-label">Accepted Price:</span> PHP <?php echo number_format($transaction['quoted_price'], 2); ?></p>
                            <p><span class="detail-label">Job Location:</span> <?php echo htmlspecialchars($transaction['job_location']); ?></p>
                            
                            <div class="jobseeker-info">
                                <h5 class="mb-3">Job Seeker Information</h5>
                                <p><span class="detail-label">Name:</span> <?php echo htmlspecialchars($transaction['jobseeker_firstname'] . ' ' . $transaction['jobseeker_lastname']); ?></p>
                                <p><span class="detail-label">Contact No:</span> <?php echo htmlspecialchars($transaction['jobseeker_contact']); ?></p>
                                <p><span class="detail-label">Email:</span> <?php echo htmlspecialchars($transaction['jobseeker_email']); ?></p>
                                <p><span class="detail-label">Address:</span> <?php echo htmlspecialchars($transaction['jobseeker_address']); ?></p>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-info" onclick="viewQuotationHistory(<?php echo $transaction['jobId']; ?>, '<?php echo htmlspecialchars($transaction['job_title']); ?>')">
                                    <i class="bi bi-clock-history"></i> View Quotation History
                                </button>
                            </div>

                            <div class="proposal-section">
                                <h5 class="mb-3">Job Seeker's Proposal</h5>
                                <p><?php echo nl2br(htmlspecialchars($transaction['jobseeker_proposal'])); ?></p>
                            </div>

                            <div class="rating-section mt-4">
                                <?php
                                // Check if jobseeker rating exists
                                $jobseekerRatingQuery = "SELECT r.rating, r.comment 
                                                        FROM user_ratings r 
                                                        WHERE r.transaction_id = ? 
                                                        AND r.rater_id = ?";
                                $ratingStmt = $conn->prepare($jobseekerRatingQuery);
                                $ratingStmt->bind_param("ii", $transaction['transactions_id'], $_SESSION['user_id']);
                                $ratingStmt->execute();
                                $ratingResult = $ratingStmt->get_result();
                                $rating = $ratingResult->fetch_assoc();
                                
                                if ($rating): ?>
                                    <div class="existing-rating">
                                        <h5 class="mb-3">Your Rating for this Jobseeker</h5>
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star-fill <?php echo $i <= $rating['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if (!empty($rating['comment'])): ?>
                                            <p class="mt-2"><strong>Your Feedback:</strong> <?php echo htmlspecialchars($rating['comment']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="rate-jobseeker">
                                        <h5 class="mb-3">Rate this Jobseeker</h5>
                                        <div class="stars mb-3" id="stars-<?php echo $transaction['transactions_id']; ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star rating-star-<?php echo $transaction['transactions_id']; ?>" 
                                                   data-rating="<?php echo $i; ?>" 
                                                   data-transaction="<?php echo $transaction['transactions_id']; ?>"
                                                   onclick="setRating(this, <?php echo $transaction['transactions_id']; ?>)" 
                                                   style="cursor: pointer;"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="mb-3">
                                            <textarea class="form-control" id="feedback-<?php echo $transaction['transactions_id']; ?>" 
                                                placeholder="Write your feedback about the jobseeker..." rows="3"></textarea>
                                        </div>
                                        <button class="btn btn-primary" 
                                            onclick="submitJobseekerRating(<?php echo $transaction['transactions_id']; ?>, <?php echo $transaction['jobseeker_id']; ?>)">
                                            Rate Jobseeker
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    No transaction history found.
                </div>
            <?php endif; ?>

            <!-- Quotation History Modal -->
            <div class="modal fade" id="quotationHistoryModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Quotation History for <span id="quotationJobTitle"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>From</th>
                                            <th>Amount</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody id="quotationHistoryBody">
                                        <!-- Quotation history will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const ratings = {};

    function setRating(star, transactionId) {
        const rating = parseInt(star.dataset.rating);
        ratings[transactionId] = rating;
        
        const stars = document.getElementsByClassName(`rating-star-${transactionId}`);
        
        Array.from(stars).forEach((s, index) => {
            if (index < rating) {
                s.classList.remove('bi-star');
                s.classList.add('bi-star-fill', 'text-warning');
            } else {
                s.classList.remove('bi-star-fill', 'text-warning');
                s.classList.add('bi-star');
            }
        });
    }

    function submitJobseekerRating(transactionId, jobseekerId) {
        if (!ratings[transactionId]) {
            alert('Please select a rating');
            return;
        }

        const feedback = document.getElementById(`feedback-${transactionId}`).value;

        fetch('submit_jobseeker_rating.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `transactionId=${transactionId}&jobseekerId=${jobseekerId}&rating=${ratings[transactionId]}&comment=${encodeURIComponent(feedback)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Thank you for rating the jobseeker!');
                location.reload();
            } else {
                alert(data.message || 'Error submitting rating');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting rating');
        });
    }

    function viewQuotationHistory(jobId, jobTitle) {
        // Update modal title
        document.getElementById('quotationJobTitle').textContent = jobTitle;
        
        // Show loading state
        const tbody = document.getElementById('quotationHistoryBody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Loading...</td></tr>';
        
        // Fetch quotation history
        fetch('get_quotation_history.php?jobId=' + jobId)
            .then(response => response.json())
            .then(response => {
                tbody.innerHTML = ''; // Clear loading state
                
                if (response.error) {
                    throw new Error(response.message || 'Error loading quotation history');
                }

                const data = response.data || [];
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center">No quotation history found</td></tr>';
                    return;
                }

                data.forEach(quote => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${new Date(quote.DateCreated).toLocaleDateString()}</td>
                        <td>${quote.from_name}</td>
                        <td>PHP ${parseFloat(quote.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>
                            <span class="badge bg-${quote.quote_type === 'Counter Offer' ? 'warning' : 'info'}">
                                ${quote.quote_type}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-${
                                quote.status === 'accepted' ? 'success' :
                                quote.status === 'rejected' ? 'danger' :
                                quote.status === 'negotiation' ? 'warning' : 'secondary'
                            }">
                                ${quote.status.charAt(0).toUpperCase() + quote.status.slice(1)}
                            </span>
                        </td>
                        <td>${quote.description}</td>
                    `;
                    tbody.appendChild(row);
                });
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('quotationHistoryModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Error loading quotation history');
                // Close the modal if it's open
                const modal = bootstrap.Modal.getInstance(document.getElementById('quotationHistoryModal'));
                if (modal) {
                    modal.hide();
                }
            });
    }
    </script>
</body>
</html> 