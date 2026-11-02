<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

function ensure_instant_challan_schema($conn)
{
    if (!$conn) {
        return;
    }

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `instant_challan_requests` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(80) NOT NULL,
        `username` VARCHAR(50) NOT NULL,
        `vehicle_number` VARCHAR(30) DEFAULT NULL,
        `order_id` VARCHAR(80) DEFAULT NULL,
        `owner_name` VARCHAR(255) DEFAULT NULL,
        `total_challans` INT NOT NULL DEFAULT 0,
        `pending_challans` INT NOT NULL DEFAULT 0,
        `paid_challans` INT NOT NULL DEFAULT 0,
        `download_url` TEXT DEFAULT NULL,
        `result_json` LONGTEXT DEFAULT NULL,
        `status_text` VARCHAR(50) DEFAULT NULL,
        `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `old_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `new_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `message` TEXT DEFAULT NULL,
        `date` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_application_no` (`application_no`),
        KEY `idx_username` (`username`),
        KEY `idx_vehicle_number` (`vehicle_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function instant_challan_fee($conn)
{
    $queries = [
        "SELECT price FROM pricing WHERE service_name='challan_details_fee' LIMIT 1",
        "SELECT price FROM pricing WHERE service_name='challan_fee' LIMIT 1",
        "SELECT price FROM pricing WHERE service_name='rc_info_fee' LIMIT 1",
        "SELECT price FROM pricing WHERE service_name='Aadhaar_To_Pan_find' LIMIT 1",
    ];

    foreach ($queries as $sql) {
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($row && isset($row['price'])) {
            return (float) $row['price'];
        }
    }

    return 20.0;
}

ensure_instant_challan_schema($ahk_conn);

$resdata = [];
$vehicleNumber = '';
$pageMessage = '';
$alertScript = '';
$balance = (float) ($udata['balance'] ?? 0);
$mobile = (string) ($udata['phone'] ?? '');
$fee = instant_challan_fee($ahk_conn);
$downloadUrl = '';

if (isset($_POST['submit_challan_details'])) {
    $vehicleNumber = strtoupper(trim((string) ($_POST['vehicle_number'] ?? '')));
    $vehicleNumber = preg_replace('/[^A-Z0-9]/', '', $vehicleNumber);

    if ($vehicleNumber === '' || strlen($vehicleNumber) < 8) {
        $alertScript = "<script>Swal.fire({title:'Invalid Vehicle Number',text:'Please enter a valid vehicle number',icon:'warning',confirmButtonText:'OK'});</script>";
    } elseif ($balance < $fee) {
        $alertScript = "<script>Swal.fire({title:'Balance Low! Recharge Now',text:'Insufficient Balance',icon:'warning',confirmButtonText:'OK'}).then((result)=>{ if(result.isConfirmed) window.location='wallet.php'; });</script>";
    } else {
        $apiBase = function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in';
        $apiUrl = $apiBase . '/api-proxy.php?' . http_build_query([
            'slug' => 'challan-details',
            'vehicle_number' => $vehicleNumber,
            'api_key' => $pariprint_api_key,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
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
                $downloadUrl = trim((string) ($data['download_url'] ?? $data['download'] ?? $data['download_link'] ?? $data['pdf_url'] ?? ''));
                $summary = isset($data['challan_summary']) && is_array($data['challan_summary']) ? $data['challan_summary'] : [];
                $vehicleInfo = isset($data['vehicle_info']) && is_array($data['vehicle_info']) ? $data['vehicle_info'] : [];
                $ownerName = trim((string) ($vehicleInfo['owner'] ?? ''));
                $orderId = trim((string) ($data['order_id'] ?? $data['request_id'] ?? ''));
                $totalChallans = (int) ($summary['total_challans'] ?? 0);
                $pendingChallans = (int) ($summary['pending'] ?? 0);
                $paidChallans = (int) ($summary['paid'] ?? 0);
                $isSuccess = (
                    $status === true ||
                    $status === 1 ||
                    in_array($normalizedStatus, ['true', 'success', 'completed', 'ok'], true) ||
                    !empty($data['vehicle_info']) ||
                    isset($data['challan_summary'])
                );

                if ($isSuccess) {
                    $newBalance = $balance - $fee;
                    $applicationNo = 'CHLN' . rand(100000, 999999);
                    $safeApplicationNo = mysqli_real_escape_string($ahk_conn, $applicationNo);
                    $safeVehicle = mysqli_real_escape_string($ahk_conn, $vehicleNumber);
                    $safeOrderId = mysqli_real_escape_string($ahk_conn, $orderId);
                    $safeOwnerName = mysqli_real_escape_string($ahk_conn, $ownerName);
                    $safeDownloadUrl = mysqli_real_escape_string($ahk_conn, $downloadUrl);
                    $safeResponse = mysqli_real_escape_string($ahk_conn, $response);
                    $safeStatus = mysqli_real_escape_string($ahk_conn, (string) ($data['status'] ?? ($data['success'] ?? 'success')));
                    $safeMessage = mysqli_real_escape_string($ahk_conn, $message);
                    $safeMobile = mysqli_real_escape_string($ahk_conn, $mobile);

                    $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='{$safeMobile}'");

                    if ($walletUpdated) {
                        $timestamp = date('d/m/Y g:i:s');
                        mysqli_query($ahk_conn, "INSERT INTO instant_challan_requests(application_no, username, vehicle_number, order_id, owner_name, total_challans, pending_challans, paid_challans, download_url, result_json, status_text, fee, old_balance, new_balance, message, date) VALUES ('{$safeApplicationNo}','{$safeMobile}','{$safeVehicle}','{$safeOrderId}','{$safeOwnerName}',{$totalChallans},{$pendingChallans},{$paidChallans},'{$safeDownloadUrl}','{$safeResponse}','{$safeStatus}',{$fee},{$balance},{$newBalance},'{$safeMessage}','{$timestamp}')");
                        mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('{$safeMobile}','{$fee}','{$newBalance}','Instant Challan Details - {$safeVehicle}','1','Debit')");

                        $resdata = $data;
                        $balance = $newBalance;
                        $pageMessage = $message;
                        $alertScript = "<script>Swal.fire({title:'Success!',text:'Challan details fetched successfully.',icon:'success',confirmButtonText:'OK'});</script>";
                    } else {
                        $pageMessage = 'Data mil gaya, lekin wallet se balance deduct nahi ho paya.';
                        $alertScript = '<script>Swal.fire({title:"Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
                    }
                } else {
                    $pageMessage = $message !== '' ? $message : 'No challan data found.';
                    $alertScript = '<script>Swal.fire({title:"Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
                }
            }
        }
    }
}

$vehicleInfo = isset($resdata['vehicle_info']) && is_array($resdata['vehicle_info']) ? $resdata['vehicle_info'] : [];
$vehicleDetails = isset($resdata['vehicle_details']) && is_array($resdata['vehicle_details']) ? $resdata['vehicle_details'] : [];
$challanSummary = isset($resdata['challan_summary']) && is_array($resdata['challan_summary']) ? $resdata['challan_summary'] : [];
$challans = isset($resdata['challans']) && is_array($resdata['challans']) ? $resdata['challans'] : [];
?>
<style>
    :root { --primary: #4361ee; --primary-dark: #3a56d4; --success: #10b981; }
    body { background: linear-gradient(135deg, #f5f7fc 0%, #eef2f9 100%); }
    .service-card { background: rgba(255,255,255,0.98); border: none; border-radius: 2rem; box-shadow: 0 25px 45px -12px rgba(0,0,0,0.2); }
    .fee-chip { background: linear-gradient(135deg, #ff9a3c, #ff6a00); color: white; font-weight: 700; border-radius: 60px; padding: 0.5rem 1.8rem; font-size: 1.3rem; box-shadow: 0 8px 18px rgba(255,106,0,0.3); }
    .form-input { border-radius: 60px; padding: 0.9rem 1.5rem; border: 2px solid #e2e8f0; transition: all 0.2s; font-size: 1rem; }
    .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(67,97,238,0.15); outline: none; }
    .submit-btn { background: linear-gradient(90deg, var(--primary), var(--primary-dark)); border: none; padding: 0.9rem; font-weight: 700; font-size: 1.1rem; border-radius: 60px; transition: all 0.3s; }
    .submit-btn:hover { transform: scale(1.02); box-shadow: 0 10px 25px rgba(67,97,238,0.4); color: white; }
    .balance-card { background: white; border-radius: 1.5rem; border-left: 5px solid var(--success); }
    .credit-footer { background: linear-gradient(135deg, #1a1a2e, #16213e); color: #cbd5e1; border-radius: 1rem; padding: 0.8rem 1.5rem; margin-top: 2rem; }
    .details-card { background: #fff; border-radius: 1.5rem; box-shadow: 0 12px 28px rgba(15,23,42,0.08); }
    .mini-table td, .mini-table th { font-size: 14px; vertical-align: middle; }
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
                                <span>Instant Challan Details</span>
                            </h3>
                            <p class="text-muted mt-1 mb-0">Fetch vehicle challan details using PariPrint reseller API</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="instant_challan_details_list" class="btn btn-success rounded-pill px-3">
                                <i class="bi bi-list-ul"></i> My Requests
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-5 col-12 mb-4">
                    <div class="card service-card mb-4">
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
                            </div>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2"><i class="bi bi-car-front"></i> Vehicle Number</label>
                                    <input type="text" name="vehicle_number" class="form-control form-input" placeholder="Enter Vehicle Number" maxlength="15" required value="<?php echo htmlspecialchars($vehicleNumber); ?>">
                                </div>
                                <button type="submit" name="submit_challan_details" class="btn submit-btn w-100 text-white" <?php echo ($balance < $fee) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-search me-2"></i>
                                    <?php echo ($balance >= $fee) ? 'Search & Pay Rs. ' . number_format($fee, 2) : 'Insufficient Balance'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8 col-md-7 col-12 mb-4">
                    <div class="details-card p-4 p-lg-5 h-100">
                        <h4 class="fw-bold mb-3">Challan Details Result</h4>
                        <?php if (!empty($resdata)): ?>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4"><div class="alert alert-light border mb-0"><strong>Total:</strong> <?php echo (int) ($challanSummary['total_challans'] ?? 0); ?></div></div>
                                <div class="col-md-4"><div class="alert alert-light border mb-0"><strong>Pending:</strong> <?php echo (int) ($challanSummary['pending'] ?? 0); ?></div></div>
                                <div class="col-md-4"><div class="alert alert-light border mb-0"><strong>Paid:</strong> <?php echo (int) ($challanSummary['paid'] ?? 0); ?></div></div>
                            </div>

                            <h6 class="fw-bold mt-3">Vehicle Info</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered table-striped mini-table align-middle mb-0">
                                    <tbody>
                                        <tr><th style="width:35%;">RC Number</th><td><?php echo htmlspecialchars((string) ($vehicleInfo['rc_number'] ?? $vehicleNumber)); ?></td></tr>
                                        <tr><th>Owner</th><td class="text-break"><?php echo htmlspecialchars((string) ($vehicleInfo['owner'] ?? '-')); ?></td></tr>
                                        <tr><th>RTO</th><td class="text-break"><?php echo htmlspecialchars((string) ($vehicleInfo['rto'] ?? '-')); ?></td></tr>
                                        <tr><th>Registration Date</th><td><?php echo htmlspecialchars((string) ($vehicleInfo['reg_date'] ?? '-')); ?></td></tr>
                                        <tr><th>RC Status</th><td><?php echo htmlspecialchars((string) ($vehicleInfo['rc_status'] ?? '-')); ?></td></tr>
                                        <tr><th>RC Expiry</th><td><?php echo htmlspecialchars((string) ($vehicleInfo['rc_expiry'] ?? '-')); ?></td></tr>
                                        <tr><th>Financer</th><td class="text-break"><?php echo htmlspecialchars((string) ($vehicleInfo['financer'] ?? '-')); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <h6 class="fw-bold mt-3">Vehicle Details</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered table-striped mini-table align-middle mb-0">
                                    <tbody>
                                        <tr><th style="width:35%;">Make</th><td class="text-break"><?php echo htmlspecialchars((string) ($vehicleDetails['make'] ?? '-')); ?></td></tr>
                                        <tr><th>Model</th><td class="text-break"><?php echo htmlspecialchars((string) ($vehicleDetails['model'] ?? '-')); ?></td></tr>
                                        <tr><th>Fuel</th><td><?php echo htmlspecialchars((string) ($vehicleDetails['fuel'] ?? '-')); ?></td></tr>
                                        <tr><th>Class</th><td class="text-break"><?php echo htmlspecialchars((string) ($vehicleDetails['class'] ?? '-')); ?></td></tr>
                                        <tr><th>Color</th><td><?php echo htmlspecialchars((string) ($vehicleDetails['color'] ?? '-')); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <h6 class="fw-bold mt-3">Challan List</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mini-table align-middle mb-0">
                                    <thead class="table-dark text-center">
                                        <tr>
                                            <th>#</th>
                                            <th>Challan No</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Offence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($challans)): ?>
                                            <?php foreach ($challans as $index => $challan): ?>
                                                <tr>
                                                    <td class="text-center"><?php echo $index + 1; ?></td>
                                                    <td class="text-break"><?php echo htmlspecialchars((string) ($challan['challan_no'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string) ($challan['amount'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string) ($challan['date'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars((string) ($challan['status'] ?? '-')); ?></td>
                                                    <td class="text-break"><?php echo htmlspecialchars((string) ($challan['offence'] ?? '-')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No challans found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <?php if ($downloadUrl !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($downloadUrl); ?>" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Open PariPrint Record</a>
                                <?php endif; ?>
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
                                <h5 class="text-muted mt-3">Enter Vehicle Number</h5>
                                <p class="text-muted mb-0">Vehicle info, challan summary aur challan list yahan full detail me show hoga.</p>
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
    var vehicleInput = document.querySelector('input[name="vehicle_number"]');
    if (vehicleInput) {
        vehicleInput.addEventListener('input', function () {
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
