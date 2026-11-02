<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

function ensure_pan_to_gst_schema($conn)
{
    if (!$conn) {
        return;
    }

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pan_to_gst_requests` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(80) NOT NULL,
        `username` VARCHAR(50) NOT NULL,
        `pan_no` VARCHAR(20) DEFAULT NULL,
        `total_gst` INT NOT NULL DEFAULT 0,
        `result_json` LONGTEXT DEFAULT NULL,
        `status_text` VARCHAR(50) DEFAULT NULL,
        `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `old_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `new_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `message` TEXT DEFAULT NULL,
        `date` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `application_no` (`application_no`),
        KEY `idx_username` (`username`),
        KEY `idx_pan_no` (`pan_no`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function pan_to_gst_fee($conn)
{
    $queries = [
        "SELECT price FROM pricing WHERE service_name='pan_to_gst_fee' LIMIT 1",
        "SELECT price FROM pricing WHERE service_name='Aadhaar_To_Pan_find' LIMIT 1",
    ];

    foreach ($queries as $sql) {
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($row && isset($row['price'])) {
            return (float) $row['price'];
        }
    }

    return 100.0;
}

ensure_pan_to_gst_schema($ahk_conn);

$resdata = [];
$gstRows = [];
$pan = '';
$pageMessage = '';
$alertScript = '';
$balance = (float) ($udata['balance'] ?? 0);
$mobile = (string) ($udata['phone'] ?? '');
$fee = pan_to_gst_fee($ahk_conn);

if (isset($_POST['submit_pan_to_gst'])) {
    $pan = strtoupper(trim((string) ($_POST['pan'] ?? '')));

    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
        $alertScript = "<script>Swal.fire({title:'Invalid PAN',text:'Please enter a valid PAN number',icon:'warning',confirmButtonText:'OK'});</script>";
    } elseif ($balance < $fee) {
        $alertScript = "<script>Swal.fire({title:'Balance Low! Recharge Now',text:'Insufficient Balance',icon:'warning',confirmButtonText:'OK'}).then((result)=>{ if(result.isConfirmed) window.location='wallet.php'; });</script>";
    } else {
        $apiBase = function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in';
        $apiUrl = $apiBase . '/api-proxy.php?' . http_build_query([
            'slug' => 'pan-to-gst',
            'pan' => $pan,
            'api_key' => $pariprint_api_key,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $pageMessage = 'API Connection Failed: ' . $curlError;
            $alertScript = '<script>Swal.fire({title:"Connection Failed",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
        } else {
            $data = json_decode($response, true);
            if (!is_array($data)) {
                $pageMessage = 'Invalid API response.';
                $alertScript = '<script>Swal.fire({title:"Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
            } else {
                $status = isset($data['status']) ? $data['status'] : ($data['success'] ?? false);
                $message = (string) ($data['message'] ?? 'No response message');
                $normalizedStatus = strtolower(trim((string) $status));
                $isSuccess = ($status === true || $status === 1 || in_array($normalizedStatus, ['true', 'success', 'completed', 'ok'], true));
                $legalPan = strtoupper(trim((string) ($data['pan'] ?? $pan)));
                $totalGst = (int) ($data['total_gst'] ?? 0);
                $gstRows = (isset($data['results']) && is_array($data['results'])) ? $data['results'] : [];

                if ($isSuccess) {
                    $newBalance = $balance - $fee;
                    $applicationNo = 'P2GST' . rand(100000, 999999);
                    $safeApplicationNo = mysqli_real_escape_string($ahk_conn, $applicationNo);
                    $safePan = mysqli_real_escape_string($ahk_conn, $legalPan);
                    $safeMessage = mysqli_real_escape_string($ahk_conn, $message);
                    $safeResponse = mysqli_real_escape_string($ahk_conn, $response);
                    $safeStatus = mysqli_real_escape_string($ahk_conn, (string) ($data['status'] ?? ($data['success'] ?? 'success')));
                    $safeMobile = mysqli_real_escape_string($ahk_conn, $mobile);

                    $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='{$safeMobile}'");

                    if ($walletUpdated) {
                        $timestamp = date('d/m/Y g:i:s');
                        mysqli_query($ahk_conn, "INSERT INTO pan_to_gst_requests(application_no, username, pan_no, total_gst, result_json, status_text, fee, old_balance, new_balance, message, date) VALUES ('{$safeApplicationNo}','{$safeMobile}','{$safePan}',{$totalGst},'{$safeResponse}','{$safeStatus}',{$fee},{$balance},{$newBalance},'{$safeMessage}','{$timestamp}')");
                        mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('{$safeMobile}','{$fee}','{$newBalance}','PAN to GST - {$safePan}','1','Debit')");

                        $resdata = $data;
                        $pageMessage = $message;
                        $balance = $newBalance;
                        $alertScript = "<script>Swal.fire({title:'Success!',text:'PAN to GST details fetched successfully.',icon:'success',confirmButtonText:'OK'});</script>";
                    } else {
                        $pageMessage = 'Data mil gaya, lekin wallet se balance deduct nahi ho paya.';
                        $alertScript = '<script>Swal.fire({title:"Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
                    }
                } else {
                    $pageMessage = $message !== '' ? $message : 'No data found.';
                    $alertScript = '<script>Swal.fire({title:"Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
                }
            }
        }
    }
}
?>
<style>
    :root { --primary: #4361ee; --primary-dark: #3a56d4; --success: #10b981; }
    body { background: linear-gradient(135deg, #f5f7fc 0%, #eef2f9 100%); }
    .exam-card {
        background: rgba(255,255,255,0.98);
        border: none;
        border-radius: 2rem;
        box-shadow: 0 25px 45px -12px rgba(0,0,0,0.2);
    }
    .fee-chip {
        background: linear-gradient(135deg, #ff9a3c, #ff6a00);
        color: white;
        font-weight: 700;
        border-radius: 60px;
        padding: 0.5rem 1.8rem;
        font-size: 1.3rem;
        box-shadow: 0 8px 18px rgba(255,106,0,0.3);
    }
    .form-input {
        border-radius: 60px;
        padding: 0.9rem 1.5rem;
        border: 2px solid #e2e8f0;
        transition: all 0.2s;
        font-size: 1rem;
    }
    .form-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
        outline: none;
    }
    .submit-btn {
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        border: none;
        padding: 0.9rem;
        font-weight: 700;
        font-size: 1.1rem;
        border-radius: 60px;
        transition: all 0.3s;
    }
    .submit-btn:hover {
        transform: scale(1.02);
        box-shadow: 0 10px 25px rgba(67,97,238,0.4);
        color: white;
    }
    .balance-card {
        background: white;
        border-radius: 1.5rem;
        border-left: 5px solid var(--success);
    }
    .credit-footer {
        background: linear-gradient(135deg, #1a1a2e, #16213e);
        color: #cbd5e1;
        border-radius: 1rem;
        padding: 0.8rem 1.5rem;
        margin-top: 2rem;
    }
    .details-card {
        background: #fff;
        border-radius: 1.5rem;
        box-shadow: 0 12px 28px rgba(15,23,42,0.08);
    }
    .mini-table td,
    .mini-table th {
        font-size: 14px;
        vertical-align: middle;
    }
</style>
<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-12">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                        <div>
                            <h3 class="fw-bold mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-receipt text-primary fs-2"></i>
                                <span>PAN to GST Details</span>
                            </h3>
                            <p class="text-muted mt-1 mb-0">Fetch GSTIN list linked with PAN using PariPrint reseller API</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="pan_to_gst_list" class="btn btn-success rounded-pill px-3">
                                <i class="bi bi-list-ul"></i> My Requests
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 col-md-6 col-12 mb-4">
                    <div class="card exam-card mb-4">
                        <div class="card-body p-4 p-lg-5">
                            <div class="text-center mb-4">
                                <div class="fee-chip">
                                    <i class="bi bi-tag-fill"></i> Rs. <?php echo number_format($fee, 2); ?> / Search
                                </div>
                                <div class="mt-3 d-flex justify-content-center gap-3 flex-wrap">
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-lightning-charge-fill"></i> Instant Delivery</span>
                                    <span class="badge bg-success text-white px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill"></i> PariPrint API</span>
                                </div>
                            </div>
                            <div class="balance-card p-3 mb-4 d-flex justify-content-between align-items-center shadow-sm">
                                <div>
                                    <i class="bi bi-wallet2 fs-4 text-success"></i>
                                    <span class="fw-semibold ms-2">Available Balance</span>
                                </div>
                                <div class="fs-4 fw-bold">Rs. <?php echo number_format($balance, 2); ?></div>
                                <a href="wallet.php" class="btn btn-sm btn-outline-secondary rounded-pill">Recharge</a>
                            </div>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-upc-scan"></i> PAN Number
                                    </label>
                                    <input type="text" name="pan" class="form-control form-input" placeholder="Enter PAN Number" maxlength="10" required value="<?php echo htmlspecialchars($pan); ?>">
                                </div>
                                <button type="submit" name="submit_pan_to_gst" class="btn submit-btn w-100 text-white" <?php echo ($balance < $fee) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-search me-2"></i>
                                    <?php echo ($balance >= $fee) ? 'Search & Pay Rs. ' . number_format($fee, 2) : 'Insufficient Balance'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 col-md-6 col-12 mb-4">
                    <div class="details-card p-4 p-lg-5 h-100">
                        <h4 class="fw-bold mb-3">GST Details Result</h4>
                        <?php if (!empty($resdata) && !empty($gstRows)): ?>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="alert alert-light border mb-0"><strong>PAN:</strong> <?php echo htmlspecialchars((string) ($resdata['pan'] ?? $pan)); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-light border mb-0"><strong>Total GST:</strong> <?php echo (int) ($resdata['total_gst'] ?? 0); ?></div>
                                </div>
                                <?php if ($pageMessage !== ''): ?>
                                    <div class="col-12"><div class="alert alert-success mb-0"><?php echo htmlspecialchars($pageMessage); ?></div></div>
                                <?php endif; ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mini-table align-middle">
                                    <thead class="table-dark text-center">
                                        <tr>
                                            <th>#</th>
                                            <th>GSTIN</th>
                                            <th>Status</th>
                                            <th>State</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gstRows as $index => $row): ?>
                                            <tr>
                                                <td class="text-center"><?php echo $index + 1; ?></td>
                                                <td class="text-break"><?php echo htmlspecialchars((string) ($row['gstin'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['status'] ?? '-')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($row['state'] ?? '-')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <details class="mt-3">
                                <summary class="text-muted">Raw API Response</summary>
                                <pre class="small mb-0 mt-2 bg-light p-3 rounded"><?php echo htmlspecialchars(json_encode($resdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                            </details>
                        <?php elseif ($pageMessage !== ''): ?>
                            <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($pageMessage); ?></div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-file-earmark-text fs-1 text-primary"></i>
                                <h5 class="text-muted mt-3">Enter PAN Number</h5>
                                <p class="text-muted mb-0">GST linked details yahan full table format me show hongi.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container pb-4">
    <div class="credit-footer text-center">
        <div class="d-flex flex-wrap justify-content-center gap-4 align-items-center">
            <span><i class="bi bi-cpu"></i> Powered by <strong>goodprintportal.in</strong></span>
            <span class="text-muted">|</span>
            <span><i class="bi bi-code-slash"></i> Developed by <strong class="text-warning">Lala Web Developer</strong></span>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.querySelector('input[name="pan"]');
    if (input) {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
        });
    }
});
</script>
<?php echo $alertScript; ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include('userFooter.php'); ?>
