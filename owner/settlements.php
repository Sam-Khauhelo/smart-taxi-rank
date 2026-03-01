<?php
// owner/settlements.php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('owner');

// Set page title
$page_title = 'Settlements';

// Start output buffering
ob_start();

$owner_id = $_SESSION['owner_id'];

// Get date range from request or default to current year
$year = $_GET['year'] ?? date('Y');
$status_filter = $_GET['status'] ?? 'all';

try {
    // Get all available years for dropdown
    $years_sql = "SELECT DISTINCT YEAR(period_start) as year 
                  FROM owner_settlements 
                  WHERE owner_id = ?
                  UNION SELECT DISTINCT YEAR(period_end) as year 
                  FROM owner_settlements 
                  WHERE owner_id = ?
                  ORDER BY year DESC";
    $stmt = $pdo->prepare($years_sql);
    $stmt->execute([$owner_id, $owner_id]);
    $available_years = $stmt->fetchAll();
    
    if (empty($available_years)) {
        // Add current year if no settlements
        $available_years = [['year' => date('Y')]];
    }
    
    // Get settlements based on filters
    $settlements_sql = "SELECT * FROM owner_settlements 
                        WHERE owner_id = :owner_id";
    
    if ($status_filter != 'all') {
        $settlements_sql .= " AND status = :status";
    }
    
    $settlements_sql .= " AND YEAR(period_start) = :year 
                         ORDER BY period_start DESC, id DESC";
    
    $stmt = $pdo->prepare($settlements_sql);
    $params = [
        ':owner_id' => $owner_id,
        ':year' => $year
    ];
    
    if ($status_filter != 'all') {
        $params[':status'] = $status_filter;
    }
    
    $stmt->execute($params);
    $settlements = $stmt->fetchAll();
    
    // Get summary statistics
    $summary_sql = "SELECT 
                        COUNT(*) as total_settlements,
                        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_total,
                        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_total,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
                        MIN(amount) as min_amount,
                        MAX(amount) as max_amount,
                        AVG(amount) as avg_amount
                    FROM owner_settlements 
                    WHERE owner_id = :owner_id AND YEAR(period_start) = :year";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute([
        ':owner_id' => $owner_id,
        ':year' => $year
    ]);
    $summary = $summary_stmt->fetch();
    
    // Get monthly breakdown for chart
    $monthly_sql = "SELECT 
                        MONTH(period_start) as month,
                        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid,
                        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending
                    FROM owner_settlements 
                    WHERE owner_id = :owner_id AND YEAR(period_start) = :year
                    GROUP BY MONTH(period_start)
                    ORDER BY month";
    
    $monthly_stmt = $pdo->prepare($monthly_sql);
    $monthly_stmt->execute([
        ':owner_id' => $owner_id,
        ':year' => $year
    ]);
    $monthly = $monthly_stmt->fetchAll();
    
    // Get pending settlements summary
    $pending_summary_sql = "SELECT 
                                COUNT(*) as count,
                                SUM(amount) as total,
                                MIN(period_end) as oldest,
                                MAX(period_end) as newest
                            FROM owner_settlements 
                            WHERE owner_id = ? AND status = 'pending'";
    $stmt = $pdo->prepare($pending_summary_sql);
    $stmt->execute([$owner_id]);
    $pending_summary = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Settlements error: " . $e->getMessage());
    $settlements = [];
    $summary = [];
    $monthly = [];
    $pending_summary = [];
    $available_years = [['year' => date('Y')]];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-cash-stack"></i> Settlements</h2>
        <div>
            <button class="btn btn-primary" onclick="requestSettlement()">
                <i class="bi bi-send"></i> Request Settlement
            </button>
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="bi bi-file-excel"></i> Export
            </button>
        </div>
    </div>

    <!-- Pending Summary Alert -->
    <?php if (($pending_summary['count'] ?? 0) > 0): ?>
        <div class="alert alert-warning mb-4">
            <div class="row">
                <div class="col-md-8">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Pending Settlements:</strong> You have 
                    <strong><?= $pending_summary['count'] ?></strong> pending settlement(s) totaling 
                    <strong>R <?= number_format($pending_summary['total'] ?? 0, 2) ?></strong>
                </div>
                <div class="col-md-4 text-end">
                    <small>
                        Oldest: <?= $pending_summary['oldest'] ? date('d M Y', strtotime($pending_summary['oldest'])) : 'N/A' ?><br>
                        Newest: <?= $pending_summary['newest'] ? date('d M Y', strtotime($pending_summary['newest'])) : 'N/A' ?>
                    </small>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Settlements</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <?php foreach ($available_years as $y): ?>
                            <option value="<?= $y['year'] ?>" <?= $year == $y['year'] ? 'selected' : '' ?>>
                                <?= $y['year'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Settlements</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending Only</option>
                        <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>Paid Only</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Total Settlements</h6>
                    <h3 class="mb-0"><?= number_format($summary['total_settlements'] ?? 0) ?></h3>
                    <small>For year <?= $year ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Pending Amount</h6>
                    <h3 class="mb-0">R <?= number_format($summary['pending_total'] ?? 0, 2) ?></h3>
                    <small><?= $summary['pending_count'] ?? 0 ?> pending settlements</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Paid Amount</h6>
                    <h3 class="mb-0">R <?= number_format($summary['paid_total'] ?? 0, 2) ?></h3>
                    <small><?= $summary['paid_count'] ?? 0 ?> paid settlements</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-white-50">Average Amount</h6>
                    <h3 class="mb-0">R <?= number_format($summary['avg_amount'] ?? 0, 2) ?></h3>
                    <small>Min: R <?= number_format($summary['min_amount'] ?? 0, 2) ?> | Max: R <?= number_format($summary['max_amount'] ?? 0, 2) ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Chart -->
    <?php if (!empty($monthly)): ?>
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Monthly Settlement Summary - <?= $year ?></h5>
        </div>
        <div class="card-body">
            <canvas id="monthlyChart" height="100"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Settlements Table -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list"></i> Settlement History</h5>
            <span class="badge bg-info">Total: R <?= number_format(($summary['paid_total'] ?? 0) + ($summary['pending_total'] ?? 0), 2) ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="settlementsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Period</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Requested Date</th>
                            <th>Paid Date</th>
                            <th>Days to Pay</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($settlements)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-cash-stack fs-1 text-muted d-block mb-3"></i>
                                    <h5 class="text-muted">No Settlements Found</h5>
                                    <p class="text-muted">No settlement records for <?= $year ?> with the selected filters.</p>
                                    <button class="btn btn-primary" onclick="requestSettlement()">
                                        <i class="bi bi-send"></i> Request Your First Settlement
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($settlements as $index => $settlement): 
                                $days_to_pay = $settlement['paid_at'] ? ceil((strtotime($settlement['paid_at']) - strtotime($settlement['created_at'])) / (60 * 60 * 24)) : null;
                            ?>
                            <tr class="<?= $settlement['status'] == 'pending' ? 'table-warning' : '' ?>">
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= date('d M Y', strtotime($settlement['period_start'])) ?></strong><br>
                                    <small>to</small><br>
                                    <strong><?= date('d M Y', strtotime($settlement['period_end'])) ?></strong>
                                </td>
                                <td>
                                    <span class="fs-5 fw-bold <?= $settlement['status'] == 'paid' ? 'text-success' : 'text-warning' ?>">
                                        R <?= number_format($settlement['amount'], 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($settlement['status'] == 'pending'): ?>
                                        <span class="badge bg-warning fs-6">⏳ Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-success fs-6">✅ Paid</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($settlement['created_at'])) ?></td>
                                <td>
                                    <?= $settlement['paid_at'] ? date('d M Y', strtotime($settlement['paid_at'])) : '-' ?>
                                </td>
                                <td>
                                    <?php if ($days_to_pay): ?>
                                        <span class="badge bg-info"><?= $days_to_pay ?> days</span>
                                    <?php elseif ($settlement['status'] == 'pending'): ?>
                                        <?php 
                                        $days_waiting = ceil((time() - strtotime($settlement['created_at'])) / (60 * 60 * 24));
                                        ?>
                                        <span class="badge bg-warning">Waiting <?= $days_waiting ?> days</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info" onclick="viewSettlement(<?= $settlement['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="downloadStatement(<?= $settlement['id'] ?>)">
                                            <i class="bi bi-download"></i>
                                        </button>
                                        <?php if ($settlement['status'] == 'pending'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="cancelRequest(<?= $settlement['id'] ?>)">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-muted">
            <i class="bi bi-info-circle"></i> Settlements are processed weekly. Pending settlements will be paid within 7-14 working days.
        </div>
    </div>

    <!-- Settlement Info Cards -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-question-circle"></i> How Settlements Work</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Settlements are calculated weekly</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Based on completed trips (Mon - Sun)</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Association levy is deducted automatically</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Payments made via EFT to your registered bank</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Statement available for each settlement</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-bank"></i> Your Banking Details</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get owner's banking details
                    $bank_sql = "SELECT o.bank_name, o.account_number, o.branch_code 
                                FROM owners o WHERE o.id = ?";
                    $stmt = $pdo->prepare($bank_sql);
                    $stmt->execute([$owner_id]);
                    $bank = $stmt->fetch();
                    ?>
                    <?php if ($bank && $bank['bank_name']): ?>
                        <p><strong>Bank:</strong> <?= htmlspecialchars($bank['bank_name']) ?></p>
                        <p><strong>Account:</strong> <?= htmlspecialchars($bank['account_number']) ?></p>
                        <p><strong>Branch Code:</strong> <?= htmlspecialchars($bank['branch_code']) ?></p>
                        <button class="btn btn-sm btn-warning" onclick="updateBankDetails()">
                            <i class="bi bi-pencil"></i> Update Details
                        </button>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> No banking details on file.
                            <button class="btn btn-sm btn-primary mt-2" onclick="updateBankDetails()">
                                Add Banking Details
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Settlement Statistics</h5>
                </div>
                <div class="card-body">
                    <canvas id="statsChart" height="150"></canvas>
                    <div class="text-center mt-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Pending</small>
                                <h5 class="text-warning">R <?= number_format($summary['pending_total'] ?? 0, 2) ?></h5>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Paid</small>
                                <h5 class="text-success">R <?= number_format($summary['paid_total'] ?? 0, 2) ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Settlement Modal -->
<div class="modal fade" id="requestSettlementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-send"></i> Request Settlement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_settlement.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="request">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Settlements are calculated based on completed trips.
                        You can request settlement for any completed period.
                    </div>
                    
                    <div class="mb-3">
                        <label for="period_start" class="form-label">Period Start Date *</label>
                        <input type="date" class="form-control" id="period_start" name="period_start" 
                               value="<?= date('Y-m-d', strtotime('last monday')) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="period_end" class="form-label">Period End Date *</label>
                        <input type="date" class="form-control" id="period_end" name="period_end" 
                               value="<?= date('Y-m-d', strtotime('last sunday')) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="estimated_amount" class="form-label">Estimated Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">R</span>
                            <input type="text" class="form-control" id="estimated_amount" readonly>
                        </div>
                        <small class="text-muted">This is an estimate based on your trips in this period</small>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="confirm" name="confirm" required>
                        <label class="form-check-label" for="confirm">
                            I confirm that all trips in this period are completed and accurate
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Settlement Modal -->
<div class="modal fade" id="viewSettlementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-file-text"></i> Settlement Statement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="settlementStatement">
                <!-- Loaded via AJAX -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printStatement()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button type="button" class="btn btn-success" onclick="downloadPDF()">
                    <i class="bi bi-file-pdf"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Update Bank Details Modal -->
<div class="modal fade" id="bankDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-bank"></i> Update Banking Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/process_settlement.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_bank">
                    
                    <div class="mb-3">
                        <label for="bank_name" class="form-label">Bank Name *</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                               value="<?= htmlspecialchars($bank['bank_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Account Number *</label>
                        <input type="text" class="form-control" id="account_number" name="account_number" 
                               value="<?= htmlspecialchars($bank['account_number'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="branch_code" class="form-label">Branch Code *</label>
                        <input type="text" class="form-control" id="branch_code" name="branch_code" 
                               value="<?= htmlspecialchars($bank['branch_code'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="confirm_bank" name="confirm_bank" required>
                        <label class="form-check-label" for="confirm_bank">
                            I confirm these banking details are correct
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Calculate estimated amount when dates change
document.getElementById('period_start')?.addEventListener('change', calculateEstimate);
document.getElementById('period_end')?.addEventListener('change', calculateEstimate);

function calculateEstimate() {
    const start = document.getElementById('period_start').value;
    const end = document.getElementById('period_end').value;
    
    if (start && end) {
        fetch(`calculate_estimate.php?start=${start}&end=${end}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('estimated_amount').value = data.amount.toFixed(2);
            });
    }
}

// Request settlement
function requestSettlement() {
    new bootstrap.Modal(document.getElementById('requestSettlementModal')).show();
    setTimeout(calculateEstimate, 500);
}

// View settlement
function viewSettlement(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewSettlementModal'));
    
    fetch(`get_settlement.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('settlementStatement').innerHTML = html;
            modal.show();
        });
}

// Download statement
function downloadStatement(id) {
    window.location.href = `download_statement.php?id=${id}`;
}

// Cancel request
function cancelRequest(id) {
    if (confirm('Are you sure you want to cancel this settlement request?')) {
        window.location.href = `process/process_settlement.php?action=cancel&id=${id}`;
    }
}

// Update bank details
function updateBankDetails() {
    new bootstrap.Modal(document.getElementById('bankDetailsModal')).show();
}

// Print statement
function printStatement() {
    const printContent = document.getElementById('settlementStatement').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}

// Download PDF
function downloadPDF() {
    // Placeholder for PDF download
    alert('PDF download feature coming soon!');
}

// Export to Excel
function exportToExcel() {
    const year = document.getElementById('year').value;
    const status = document.getElementById('status').value;
    window.location.href = `export/export_settlements.php?year=${year}&status=${status}`;
}

<?php if (!empty($monthly)): ?>
// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
if (monthlyCtx) {
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Paid',
                data: [<?php 
                    $paid_data = array_fill(0, 12, 0);
                    foreach ($monthly as $m) {
                        $paid_data[$m['month'] - 1] = $m['paid'];
                    }
                    echo implode(',', $paid_data);
                ?>],
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
            }, {
                label: 'Pending',
                data: [<?php 
                    $pending_data = array_fill(0, 12, 0);
                    foreach ($monthly as $m) {
                        $pending_data[$m['month'] - 1] = $m['pending'];
                    }
                    echo implode(',', $pending_data);
                ?>],
                backgroundColor: 'rgba(255, 193, 7, 0.7)',
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount (R)'
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Stats Pie Chart
const statsCtx = document.getElementById('statsChart')?.getContext('2d');
if (statsCtx) {
    new Chart(statsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Paid'],
            datasets: [{
                data: [
                    <?= $summary['pending_total'] ?? 0 ?>,
                    <?= $summary['paid_total'] ?? 0 ?>
                ],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(40, 167, 69, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Auto-refresh pending status every 60 seconds
setTimeout(function() {
    window.location.reload();
}, 60000);
</script>

<style>
.table td {
    vertical-align: middle;
}
.card {
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.badge {
    font-size: 12px;
    padding: 6px 10px;
}
.alert {
    border-left: 4px solid;
}
.table-warning {
    background-color: #fff3cd;
}
</style>

<?php
// Get the content
$content = ob_get_clean();

// Include the owner layout
require_once '../layouts/owner_layout.php';
?>