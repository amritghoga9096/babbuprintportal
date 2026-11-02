<?php 
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Fixed Fee
define('PAN_FEE', 40);

 $balance = $udata['balance'];
 $mobile = $udata['phone'];

if (isset($_POST['submit_pan']) && !empty($_POST['aadhaar_number'])) {
    
    if ($balance < PAN_FEE) {
        echo "<script>
            Swal.fire({
                title:'Balance Low! Recharge Now',
                text:'Insufficient Balance',
                icon:'warning',
                confirmButtonText:'OK'
            }).then((result) => { if(result.isConfirmed) window.location='wallet.php'; });
        </script>";
    } else {
        $aadhaar_number = trim($_POST['aadhaar_number']);
        if (!preg_match('/^\d{12}$/', $aadhaar_number)) {
            echo "<script>
                Swal.fire({
                    title:'Invalid Aadhaar',
                    text:'Please enter a valid 12 digit Aadhaar number',
                    icon:'warning',
                    confirmButtonText:'OK'
                });
            </script>";
        } else {
            $apiUrl = $pariprint_instant_pan_endpoint . "?" . http_build_query([
                'slug' => 'instant-pan-find',
                'aadhaar_number' => $aadhaar_number,
                'api_key' => $pariprint_api_key
            ]);
        
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CUSTOMREQUEST => "GET",
            ));
        
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
        
            if ($response === false || !empty($curlError)) {
                echo '<div class="alert alert-danger">API Connection Failed: ' . htmlspecialchars($curlError) . '</div>';
            } else {
                $data = json_decode($response, true);
                $status = $data['status'] ?? false;
                $message = $data['message'] ?? 'No response message';
                $panNumber = strtoupper(trim($data['pan_number'] ?? ($data['full_pan_number'] ?? ($data['mask_pan'] ?? ''))));
                $isSuccess = (
                    $status === true ||
                    $status === 1 ||
                    $status === 'true' ||
                    $status === 'SUCCESS' ||
                    $status === 'success' ||
                    (isset($data['status_code']) && (string) $data['status_code'] === '100') ||
                    (isset($data['response_code']) && (string) $data['response_code'] === '200')
                );

                if ($isSuccess && $panNumber !== '') {
                    $new_balance = $balance - PAN_FEE;
                    $application_no = 'INSPAN' . rand(100000, 999999);
                    $safe_application_no = mysqli_real_escape_string($ahk_conn, $application_no);
                    $safe_aadhaar_number = mysqli_real_escape_string($ahk_conn, $aadhaar_number);
                    $safe_pan_number = mysqli_real_escape_string($ahk_conn, $panNumber);
                    $safe_message = mysqli_real_escape_string($ahk_conn, $message);
                    $safe_response = mysqli_real_escape_string($ahk_conn, $response);
                    $js_pan_number = json_encode($panNumber);
                    $js_message = json_encode($message);
                    $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - " . PAN_FEE . " WHERE phone='$mobile'");

                    if ($walletUpdated) {
                        date_default_timezone_set("Asia/Kolkata");
                        $timestamp = date('d/m/Y g:i:s');

                        mysqli_query($ahk_conn, "INSERT INTO instantpan_find(
                            application_no,
                            username,
                            aadhaar_no,
                            pan_no,
                            status,
                            status_code,
                            fee,
                            old_balance,
                            new_balance,
                            message,
                            date
                        ) VALUES (
                            '$safe_application_no',
                            '$mobile',
                            '$safe_aadhaar_number',
                            '$safe_pan_number',
                            '100',
                            '$safe_response',
                            '" . PAN_FEE . "',
                            '$balance',
                            '$new_balance',
                            '$safe_message',
                            '$timestamp'
                        )");

                        mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type)
                            VALUES ('$mobile','" . PAN_FEE . "','$new_balance','Instant Pan Find - $safe_aadhaar_number','1','Debit')");

                        echo "<script>
                            const successPanNumber = $js_pan_number;
                            const successMessage = $js_message;
                            Swal.fire({
                                title:'Success!',
                                html:'PAN Number: <b>' + successPanNumber + '</b><br>' + successMessage,
                                icon:'success',
                                confirmButtonText:'Go to PAN List'
                            }).then(() => { window.location='instant_pan_list.php'; });
                        </script>";
                    } else {
                        echo "<script>
                            Swal.fire({
                                title:'Error',
                                text:'PAN mil gaya, lekin wallet se balance deduct nahi ho paya.',
                                icon:'error',
                                confirmButtonText:'OK'
                            });
                        </script>";
                    }
                } else {
                    $js_message = json_encode($message ?: 'Invalid API response');
                    echo "<script>
                        const errorMessage = $js_message;
                        Swal.fire({
                            title:'Error',
                            text:errorMessage,
                            icon:'error',
                            confirmButtonText:'OK'
                        });
                    </script>";
                }
            }
        }
    }
}
?>

<!-- Same CSS as LL Exam -->
<style>
    :root { --primary: #4361ee; --primary-dark: #3a56d4; --success: #10b981; }
    body { background: linear-gradient(135deg, #f5f7fc 0%, #eef2f9 100%); }
    .exam-card {
        background: rgba(255,255,255,0.98); border: none; border-radius: 2rem;
        box-shadow: 0 25px 45px -12px rgba(0,0,0,0.2);
    }
    .fee-chip {
        background: linear-gradient(135deg, #ff9a3c, #ff6a00); color: white; font-weight: 700;
        border-radius: 60px; padding: 0.5rem 1.8rem; font-size: 1.3rem;
        box-shadow: 0 8px 18px rgba(255,106,0,0.3);
    }
    .form-input {
        border-radius: 60px; padding: 0.9rem 1.5rem; border: 2px solid #e2e8f0;
        transition: all 0.2s; font-size: 1rem;
    }
    .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(67,97,238,0.15); outline: none; }
    .submit-btn {
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        border: none; padding: 0.9rem; font-weight: 700; font-size: 1.1rem;
        border-radius: 60px; transition: all 0.3s;
    }
    .submit-btn:hover { transform: scale(1.02); box-shadow: 0 10px 25px rgba(67,97,238,0.4); color: white;}
    .balance-card { background: white; border-radius: 1.5rem; border-left: 5px solid var(--success); }
    .credit-footer {
        background: linear-gradient(135deg, #1a1a2e, #16213e); color: #cbd5e1;
        border-radius: 1rem; padding: 0.8rem 1.5rem; margin-top: 2rem;
    }
</style>

<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-12">
                    
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                        <div>
                            <h3 class="fw-bold mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-person-badge text-primary fs-2"></i> 
                                <span>Instant Pan Find</span>
                            </h3>
                            <p class="text-muted mt-1">Find PAN instantly by Aadhaar number</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="instant_pan_list.php" class="btn btn-success rounded-pill px-3">
                                <i class="bi bi-list-ul"></i> My Requests
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>

                    <div class="card exam-card mb-4">
                        <div class="card-body p-4 p-lg-5">
                            <div class="text-center mb-4">
                                <div class="fee-chip">
                                    <i class="bi bi-tag-fill"></i> ₹<?php echo PAN_FEE; ?> / Search
                                </div>
                                <div class="mt-3 d-flex justify-content-center gap-3 flex-wrap">
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                                        <i class="bi bi-lightning-charge-fill"></i> Instant Delivery
                                    </span>
                                    <span class="badge bg-success text-white px-3 py-2 rounded-pill">
                                        <i class="bi bi-check-circle-fill"></i> 100% Secure
                                    </span>
                                </div>
                            </div>
                            
                            <div class="balance-card p-3 mb-4 d-flex justify-content-between align-items-center shadow-sm">
                                <div>
                                    <i class="bi bi-wallet2 fs-4 text-success"></i>
                                    <span class="fw-semibold ms-2">Available Balance</span>
                                </div>
                                <div class="fs-4 fw-bold">₹<?php echo number_format($balance, 2); ?></div>
                                <a href="wallet.php" class="btn btn-sm btn-outline-secondary rounded-pill">Recharge</a>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="bi bi-upc-scan"></i> Aadhaar Number
                                    </label>
                                    <input type="text" 
                                           name="aadhaar_number" 
                                           class="form-control form-input" 
                                           placeholder="Enter 12 Digit Aadhaar Number" 
                                           maxlength="12" pattern="\d{12}" required>
                                </div>
                                
                                <button type="submit" name="submit_pan" class="btn submit-btn w-100 text-white" <?php echo ($balance < PAN_FEE) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-search me-2"></i>
                                    <?php echo ($balance >= PAN_FEE) ? 'Search & Pay ₹'.PAN_FEE : 'Insufficient Balance'; ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="credit-footer text-center">
                        <div class="d-flex flex-wrap justify-content-center gap-4 align-items-center">
                            <span><i class="bi bi-cpu"></i> Powered by <strong>goodprintportal.in</strong></span>
                            <span class="text-muted">|</span>
                            <span><i class="bi bi-code-slash"></i> Developed by <strong class="text-warning">Lala Web Developer</strong></span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include('userFooter.php'); ?>
