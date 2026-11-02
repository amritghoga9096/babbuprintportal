<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

function ensure_gst_to_details_schema($conn)
{
    if (!$conn) {
        return;
    }

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `gst_to_details_requests` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(80) NOT NULL,
        `username` VARCHAR(50) NOT NULL,
        `gstin` VARCHAR(20) DEFAULT NULL,
        `legal_name` VARCHAR(255) DEFAULT NULL,
        `status_text` VARCHAR(50) DEFAULT NULL,
        `result_json` LONGTEXT DEFAULT NULL,
        `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `old_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `new_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `message` TEXT DEFAULT NULL,
        `date` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `application_no` (`application_no`),
        KEY `idx_username` (`username`),
        KEY `idx_gstin` (`gstin`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function gst_to_details_fee($conn)
{
    $queries = [
        "SELECT price FROM pricing WHERE service_name='gst_to_details_fee' LIMIT 1",
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

ensure_gst_to_details_schema($ahk_conn);

$resdata = [];
$gstin = '';
$pageMessage = '';
$alertScript = '';
$balance = (float) ($udata['balance'] ?? 0);
$mobile = (string) ($udata['phone'] ?? '');
$fee = gst_to_details_fee($ahk_conn);

if (isset($_POST['submit_gst_to_details'])) {
    $gstin = strtoupper(trim((string) ($_POST['gstin'] ?? '')));

    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]{3}$/', $gstin)) {
        $alertScript = "<script>Swal.fire({title:'Invalid GSTIN',text:'Please enter a valid GSTIN number',icon:'warning',confirmButtonText:'OK'});</script>";
    } elseif ($balance < $fee) {
        $alertScript = "<script>Swal.fire({title:'Balance Low! Recharge Now',text:'Insufficient Balance',icon:'warning',confirmButtonText:'OK'}).then((result)=>{ if(result.isConfirmed) window.location='wallet.php'; });</script>";
    } else {
        $apiBase = function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in';
        $apiUrl = $apiBase . '/api-proxy.php?' . http_build_query([
            'slug' => 'gst-to-details',
            'gstin' => $gstin,
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
                $legalGstin = strtoupper(trim((string) ($data['gstin'] ?? $gstin)));
                $legalName = trim((string) ($data['legal_name'] ?? ''));
                $liveStatus = trim((string) ($data['status'] ?? ''));

                if ($isSuccess && $legalGstin !== '') {
                    $newBalance = $balance - $fee;
                    $applicationNo = 'GSTDET' . rand(100000, 999999);
                    $safeApplicationNo = mysqli_real_escape_string($ahk_conn, $applicationNo);
                    $safeGstin = mysqli_real_escape_string($ahk_conn, $legalGstin);
                    $safeLegalName = mysqli_real_escape_string($ahk_conn, $legalName);
                    $safeMessage = mysqli_real_escape_string($ahk_conn, $message);
                    $safeResponse = mysqli_real_escape_string($ahk_conn, $response);
                    $safeStatus = mysqli_real_escape_string($ahk_conn, $liveStatus !== '' ? $liveStatus : (string) ($data['success'] ?? 'success'));
                    $safeMobile = mysqli_real_escape_string($ahk_conn, $mobile);

                    $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='{$safeMobile}'");

                    if ($walletUpdated) {
                        $timestamp = date('d/m/Y g:i:s');
                        mysqli_query($ahk_conn, "INSERT INTO gst_to_details_requests(application_no, username, gstin, legal_name, status_text, result_json, fee, old_balance, new_balance, message, date) VALUES ('{$safeApplicationNo}','{$safeMobile}','{$safeGstin}','{$safeLegalName}','{$safeStatus}','{$safeResponse}',{$fee},{$balance},{$newBalance},'{$safeMessage}','{$timestamp}')");
                        mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('{$safeMobile}','{$fee}','{$newBalance}','GST to Details - {$safeGstin}','1','Debit')");

                        $resdata = $data;
                        $pageMessage = $message;
                        $balance = $newBalance;
                        $alertScript = "<script>Swal.fire({title:'Success!',text:'GST details fetched successfully.',icon:'success',confirmButtonText:'OK'});</script>";
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
                                <i class="bi bi-building text-primary fs-2"></i>
                                <span>GST to Details</span>
                            </h3>
                            <p class="text-muted mt-1 mb-0">Fetch GST legal name, status, constitution and jurisdiction using PariPrint reseller API</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="gst_to_details_list" class="btn btn-success rounded-pill px-3">
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
                                        <i class="bi bi-upc-scan"></i> GSTIN Number
                                    </label>
                                    <input type="text" name="gstin" class="form-control form-input" placeholder="Enter GSTIN Number" maxlength="15" required value="<?php echo htmlspecialchars($gstin); ?>">
                                </div>
                                <button type="submit" name="submit_gst_to_details" class="btn submit-btn w-100 text-white" <?php echo ($balance < $fee) ? 'disabled' : ''; ?>>
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
                        <?php if (!empty($resdata)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mini-table align-middle mb-0">
                                    <tbody>
                                        <tr><th style="width:35%;">GSTIN</th><td class="text-break"><?php echo htmlspecialchars((string) ($resdata['gstin'] ?? $gstin)); ?></td></tr>
                                        <tr><th>Legal Name</th><td class="text-break"><?php echo htmlspecialchars((string) ($resdata['legal_name'] ?? '-')); ?></td></tr>
                                        <tr><th>Status</th><td><?php echo htmlspecialchars((string) ($resdata['status'] ?? '-')); ?></td></tr>
                                        <tr><th>Constitution</th><td><?php echo htmlspecialchars((string) ($resdata['constitution'] ?? '-')); ?></td></tr>
                                        <tr><th>Tax Payer Type</th><td><?php echo htmlspecialchars((string) ($resdata['tax_payer_type'] ?? '-')); ?></td></tr>
                                        <tr><th>Registration Date</th><td><?php echo htmlspecialchars((string) ($resdata['registration_date'] ?? '-')); ?></td></tr>
                                        <tr><th>State Jurisdiction</th><td class="text-break"><?php echo htmlspecialchars((string) ($resdata['state_jurisdiction'] ?? '-')); ?></td></tr>
                                        <tr><th>Primary Address</th><td class="text-break"><?php echo htmlspecialchars((string) ($resdata['primary_address'] ?? '-')); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($pageMessage !== ''): ?>
                                <div class="alert alert-success mt-3 mb-0"><?php echo htmlspecialchars($pageMessage); ?></div>
                            <?php endif; ?>
                            <details class="mt-3">
                                <summary class="text-muted">Raw API Response</summary>
                                <pre class="small mb-0 mt-2 bg-light p-3 rounded"><?php echo htmlspecialchars(json_encode($resdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                            </details>
                        <?php elseif ($pageMessage !== ''): ?>
                            <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($pageMessage); ?></div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-file-earmark-text fs-1 text-primary"></i>
                                <h5 class="text-muted mt-3">Enter GSTIN Number</h5>
                                <p class="text-muted mb-0">Legal name, constitution, status aur jurisdiction yahan full detail me show hoga.</p>
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
    var input = document.querySelector('input[name="gstin"]');
    if (input) {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15);
        });
    }
});
</script>
<?php echo $alertScript; ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include('userFooter.php'); ?>
