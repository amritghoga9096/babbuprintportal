<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

function ensure_instant_farmer_pdf_schema($conn)
{
    if (!$conn) {
        return;
    }

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `instant_farmer_pdf_requests` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(80) NOT NULL,
        `username` VARCHAR(50) NOT NULL,
        `aadhaar_no` VARCHAR(20) DEFAULT NULL,
        `state_code` VARCHAR(10) DEFAULT NULL,
        `order_id` VARCHAR(80) DEFAULT NULL,
        `farmer_name` VARCHAR(255) DEFAULT NULL,
        `farmer_id` VARCHAR(80) DEFAULT NULL,
        `file_name` VARCHAR(255) DEFAULT NULL,
        `download_url` TEXT DEFAULT NULL,
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
        KEY `idx_aadhaar` (`aadhaar_no`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function instant_farmer_pdf_fee($conn)
{
    $queries = [
        "SELECT price FROM pricing WHERE service_name='instant_farmer_pdf_fee' LIMIT 1",
        "SELECT price FROM pricing WHERE service_name='dbt_farmer_fee' LIMIT 1",
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

ensure_instant_farmer_pdf_schema($ahk_conn);

$resdata = [];
$aadhaar = '';
$state = 'MH';
$pageMessage = '';
$alertScript = '';
$balance = (float) ($udata['balance'] ?? 0);
$mobile = (string) ($udata['phone'] ?? '');
$fee = instant_farmer_pdf_fee($ahk_conn);
$downloadUrl = '';
$lastFarmerRecordId = 0;

if (isset($_POST['submit_farmer_pdf'])) {
    $aadhaar = preg_replace('/[^0-9]/', '', (string) ($_POST['aadhaar'] ?? ''));
    $state = strtoupper(trim((string) ($_POST['state'] ?? 'MH')));

    if (!preg_match('/^[0-9]{12}$/', $aadhaar)) {
        $alertScript = "<script>Swal.fire({title:'Invalid Aadhaar',text:'Please enter a valid 12 digit Aadhaar number',icon:'warning',confirmButtonText:'OK'});</script>";
    } elseif ($state !== '' && !preg_match('/^[A-Z]{2,3}$/', $state)) {
        $alertScript = "<script>Swal.fire({title:'Invalid State',text:'Please enter a valid state code like MH, UP, MP',icon:'warning',confirmButtonText:'OK'});</script>";
    } elseif ($balance < $fee) {
        $alertScript = "<script>Swal.fire({title:'Balance Low! Recharge Now',text:'Insufficient Balance',icon:'warning',confirmButtonText:'OK'}).then((result)=>{ if(result.isConfirmed) window.location='wallet.php'; });</script>";
    } else {
        $apiBase = function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in';
        $apiUrl = $apiBase . '/api-proxy.php?' . http_build_query([
            'slug' => 'instant-farmer-pdf',
            'aadhaar' => $aadhaar,
            'state' => $state,
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
                $farmerName = trim((string) ($data['name'] ?? ''));
                $farmerId = trim((string) ($data['farmer_id'] ?? ''));
                $fileName = trim((string) ($data['filename'] ?? $data['file_name'] ?? ''));
                $orderId = trim((string) ($data['order_id'] ?? $data['request_id'] ?? ''));
                $hasBase64 = trim((string) ($data['pdf_base64'] ?? '')) !== '';
                $isSuccess = (
                    $status === true ||
                    $status === 1 ||
                    in_array($normalizedStatus, ['true', 'success', 'completed', 'ok'], true) ||
                    (($downloadUrl !== '' || $hasBase64) && empty($data['error']) && empty($data['message']))
                );

                if ($isSuccess && ($downloadUrl !== '' || $hasBase64)) {
                    $newBalance = $balance - $fee;
                    $applicationNo = 'FARM' . rand(100000, 999999);
                    $safeApplicationNo = mysqli_real_escape_string($ahk_conn, $applicationNo);
                    $safeAadhaar = mysqli_real_escape_string($ahk_conn, $aadhaar);
                    $safeState = mysqli_real_escape_string($ahk_conn, $state);
                    $safeOrderId = mysqli_real_escape_string($ahk_conn, $orderId);
                    $safeFarmerName = mysqli_real_escape_string($ahk_conn, $farmerName);
                    $safeFarmerId = mysqli_real_escape_string($ahk_conn, $farmerId);
                    $safeFileName = mysqli_real_escape_string($ahk_conn, $fileName);
                    $safeDownloadUrl = mysqli_real_escape_string($ahk_conn, $downloadUrl);
                    $safeResponse = mysqli_real_escape_string($ahk_conn, $response);
                    $safeStatus = mysqli_real_escape_string($ahk_conn, (string) ($data['status'] ?? ($data['success'] ?? 'success')));
                    $safeMessage = mysqli_real_escape_string($ahk_conn, $message);
                    $safeMobile = mysqli_real_escape_string($ahk_conn, $mobile);

                    $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='{$safeMobile}'");

                    if ($walletUpdated) {
                        $timestamp = date('d/m/Y g:i:s');
                        mysqli_query($ahk_conn, "INSERT INTO instant_farmer_pdf_requests(application_no, username, aadhaar_no, state_code, order_id, farmer_name, farmer_id, file_name, download_url, result_json, status_text, fee, old_balance, new_balance, message, date) VALUES ('{$safeApplicationNo}','{$safeMobile}','{$safeAadhaar}','{$safeState}','{$safeOrderId}','{$safeFarmerName}','{$safeFarmerId}','{$safeFileName}','{$safeDownloadUrl}','{$safeResponse}','{$safeStatus}',{$fee},{$balance},{$newBalance},'{$safeMessage}','{$timestamp}')");
                        $lastFarmerRecordId = (int)mysqli_insert_id($ahk_conn);
                        mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('{$safeMobile}','{$fee}','{$newBalance}','Instant Farmer PDF - {$safeAadhaar}','1','Debit')");

                        $resdata = $data;
                        $balance = $newBalance;
                        $pageMessage = $message;
                        $alertScript = "<script>Swal.fire({title:'Success!',text:'Farmer PDF fetched successfully.',icon:'success',confirmButtonText:'OK'});</script>";
                    } else {
                        $pageMessage = 'Data mil gaya, lekin wallet se balance deduct nahi ho paya.';
                        $alertScript = '<script>Swal.fire({title:"Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
                    }
                } else {
                    $pageMessage = $message !== '' ? $message : 'Farmer information not found. Please check Aadhaar and State.';
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
    .exam-card { background: rgba(255,255,255,0.98); border: none; border-radius: 2rem; box-shadow: 0 25px 45px -12px rgba(0,0,0,0.2); }
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
                                <i class="bi bi-file-earmark-pdf text-primary fs-2"></i>
                                <span>Instant Farmer PDF</span>
                            </h3>
                            <p class="text-muted mt-1 mb-0">Fetch Farmer PDF using PariPrint reseller API with direct download</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="instant_farmer_pdf_list" class="btn btn-success rounded-pill px-3">
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
                                    <i class="bi bi-tag-fill"></i> Rs. <?php echo number_format($fee, 2); ?> / PDF
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
                                    <label class="form-label fw-semibold mb-2"><i class="bi bi-upc-scan"></i> Aadhaar Number</label>
                                    <input type="text" name="aadhaar" class="form-control form-input" placeholder="Enter 12 Digit Aadhaar Number" maxlength="12" required value="<?php echo htmlspecialchars($aadhaar); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2"><i class="bi bi-geo"></i> State Code</label>
                                    <input type="text" name="state" class="form-control form-input" placeholder="EX: MH" maxlength="3" value="<?php echo htmlspecialchars($state); ?>">
                                </div>
                                <button type="submit" name="submit_farmer_pdf" class="btn submit-btn w-100 text-white" <?php echo ($balance < $fee) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-search me-2"></i>
                                    <?php echo ($balance >= $fee) ? 'Get PDF & Pay Rs. ' . number_format($fee, 2) : 'Insufficient Balance'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 col-md-6 col-12 mb-4">
                    <div class="details-card p-4 p-lg-5 h-100">
                        <h4 class="fw-bold mb-3">Farmer PDF Result</h4>
                        <?php if (!empty($resdata)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mini-table align-middle mb-0">
                                    <tbody>
                                        <tr><th style="width:35%;">Name</th><td class="text-break"><?php echo htmlspecialchars((string) ($resdata['name'] ?? '-')); ?></td></tr>
                                        <tr><th>Name Local</th><td class="text-break"><?php echo htmlspecialchars((string) ($resdata['name_local'] ?? '-')); ?></td></tr>
                                        <tr><th>Farmer ID</th><td><?php echo htmlspecialchars((string) ($resdata['farmer_id'] ?? '-')); ?></td></tr>
                                        <tr><th>Order ID</th><td><?php echo htmlspecialchars((string) ($resdata['order_id'] ?? '-')); ?></td></tr>
                                        <tr><th>File Name</th><td class="text-break"><?php echo htmlspecialchars((string) ($resdata['filename'] ?? $resdata['file_name'] ?? '-')); ?></td></tr>
                                        <tr><th>Download</th><td>
                                            <?php if (!empty($lastFarmerRecordId) && $lastFarmerRecordId > 0): ?>
                                                <button type="button"
                                                        class="btn btn-success btn-sm farmer-pdf-download-btn"
                                                        data-local-url="farmer_pdf_download.php?id=<?php echo (int)$lastFarmerRecordId; ?>"
                                                        data-fallback-url="<?php echo htmlspecialchars($downloadUrl); ?>"
                                                        data-record-id="<?php echo (int)$lastFarmerRecordId; ?>"
                                                        data-filename="<?php echo htmlspecialchars((string)($resdata['filename'] ?? $resdata['file_name'] ?? 'FarmerCard.pdf')); ?>">
                                                    <i class="bi bi-download"></i> Download PDF
                                                </button>
                                            <?php elseif (!empty($resdata['pdf_base64'])): ?>
                                                <a href="data:application/pdf;base64,<?php echo htmlspecialchars((string)$resdata['pdf_base64']); ?>" download="<?php echo htmlspecialchars((string)($resdata['filename'] ?? 'FarmerCard.pdf')); ?>" class="btn btn-success btn-sm"><i class="bi bi-download"></i> Download PDF</a>
                                            <?php else: ?>
                                                <span class="text-muted">Download link not available</span>
                                            <?php endif; ?>
                                        </td></tr>
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
                                <h5 class="text-muted mt-3">Enter Aadhaar Number</h5>
                                <p class="text-muted mb-0">Farmer details aur PDF download yahan show hoga.</p>
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
    var aadhaarInput = document.querySelector('input[name="aadhaar"]');
    var stateInput = document.querySelector('input[name="state"]');
    if (aadhaarInput) {
        aadhaarInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);
        });
    }
    if (stateInput) {
        stateInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
        });
    }
});

(function () {
    function triggerBlobDownload(url, fallbackFilename, fallbackUrl) {
        var fb = fallbackUrl || url;
        return fetch(url, { method: 'GET', credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                var disp = response.headers.get('content-disposition') || '';
                var name = fallbackFilename || 'download';
                var m = /filename\*=UTF-8''([^;]+)/i.exec(disp) || /filename="?([^";]+)"?/i.exec(disp);
                if (m && m[1]) { try { name = decodeURIComponent(m[1].replace(/["']/g, '')); } catch (_) { name = m[1].replace(/["']/g, ''); } }
                var ct = (response.headers.get('content-type') || '').toLowerCase();
                if (ct.includes('application/pdf') || ct.includes('octet-stream') || ct.includes('application/x-download') || ct.includes('application/force-download') || (name && /\.(pdf|zip|png|jpe?g|docx?|xlsx?|csv|txt)$/i.test(name))) {
                    return response.blob().then(function (blob) {
                        var obj = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = obj;
                        a.download = name;
                        a.rel = 'noopener';
                        document.body.appendChild(a);
                        a.click();
                        setTimeout(function () {
                            try { URL.revokeObjectURL(obj); } catch (_) { }
                            try { if (a.parentNode) a.parentNode.removeChild(a); } catch (_) { }
                        }, 1400);
                        return true;
                    });
                }
                window.location.href = fb;
                return true;
            })
            .catch(function () {
                window.location.href = fb;
                return true;
            });
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('.farmer-pdf-download-btn');
        if (!btn) return;
        event.preventDefault();
        var url = btn.getAttribute('data-local-url') || '';
        var fb = btn.getAttribute('data-fallback-url') || url;
        var rid = btn.getAttribute('data-record-id') || '';
        var fn = btn.getAttribute('data-filename') || ('farmer-pdf-' + (rid || Date.now()) + '.pdf');
        if (!url) return;
        var orig = btn.innerHTML;
        try { btn.disabled = true; } catch (_) { }
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Preparing...';
        triggerBlobDownload(url, fn, fb).then(function () {
            try { btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Downloading...'; } catch (_) { }
            setTimeout(function () { try { btn.innerHTML = orig; btn.disabled = false; } catch (_) { } }, 1600);
        }).catch(function () {
            try { btn.innerHTML = orig; btn.disabled = false; } catch (_) { }
        });
    });
})();
</script>
<?php echo $alertScript; ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include('userFooter.php'); ?>
