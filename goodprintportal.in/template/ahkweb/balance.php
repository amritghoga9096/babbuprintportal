<?php
session_start();
include('../template/ahkweb/header.php'); // must define $ahk_conn & $flexsecure_upikey

$phone = $_SESSION['phone'] ?? 'guest';
$email = $_SESSION['email'] ?? 'test@example.com';
$user_token = $flexsecure_upikey2 ?? '';
$qrImageBase64 = '';
$amount = '';
$order_id = '';
$qr_status = false;
$gatewayUsed = '0';
$successGifUrl = "https://$flexsecure_upiUrl/api2/img/success-gif.gif";

function call_qr_api($url, $post_fields) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $api_response = curl_exec($ch);
    curl_close($ch);
    return json_decode($api_response, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['amount'])) {
    $amount = mysqli_real_escape_string($ahk_conn, $_POST['amount']);

    if (is_numeric($amount) && $amount >= 100 && $amount <= 1000) {
        $order_id = '261' . rand(1000000000, 9999999999);
        

        // ✅ Generate proper absolute redirect URL (no &amp;)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $redirect_url = sprintf(
            '%s://%s/Flex4uPoint4in1/secureresponse2.php?order_id=%s&customer_mobile=%s',
            $protocol,
            $_SERVER['HTTP_HOST'],
            rawurlencode($order_id),
            rawurlencode($phone)
        );

        if (strlen($user_token) !== 32) {
            echo "<script>alert('Invalid API key.');</script>";
        } else {
            $post_fields = [
                'user_token'       => $user_token,
                'amount'           => $amount,
                'order_id'         => $order_id,
                'byteTransactionId'         => $order_id,
                'customer_mobile'  => $phone,
                'email'            => $email,
                'redirect_url'     => $redirect_url
            ];

            // Try gateways
            $resData = call_qr_api("https://$flexsecure_upiUrl/api2/create-orderpopup.php", $post_fields);

            if (empty($resData) || (isset($resData['status']) && $resData['status'] !== true)) {
                $resData = call_qr_api("https://$flexsecure_upiUrl/api2/create-orderpopup2.php", $post_fields);
                $gatewayUsed = '2';
            }

            if (empty($resData) || (isset($resData['status']) && $resData['status'] !== true)) {
                $resData = call_qr_api("https://$flexsecure_upiUrl/api2/create-orderpopup3.php", $post_fields);
                $gatewayUsed = '3';
            }

            if (!empty($resData['status']) && $resData['status'] === true && !empty($resData['result']['payment_url'])) {
                $qr_status = true;
                $qrImageBase64 = $resData['result']['payment_url'];

                // ✅ Log transaction
                $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallet (phone, amount, txn_id, email, status) VALUES (?, ?, ?, ?, 'pending')");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "siss", $phone, $amount, $order_id, $email);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } else {
                $errorMsg = $resData['message'] ?? 'QR generation failed.';
                echo "<script>alert('QR API failed: " . addslashes($errorMsg) . "');</script>";
            }
        }
    } else {
        echo "<script>alert('Amount should be between ₹100 and ₹1000');</script>";
    }
}
?>

<?php if ($qr_status): ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.swal2-container { z-index: 10000 !important; }
.swal2-popup { z-index: 10001 !important; }
.swal2-container.swal2-top-end { z-index: 10000 !important; }
</style>

<script>
const qrImageBase64 = "<?php echo addslashes($qrImageBase64); ?>";
const amount = "<?php echo addslashes($amount); ?>";
const redirect_url = "<?php echo addslashes($redirect_url); ?>"; // ✅ FIXED (no &amp;)
const order_id = "<?php echo addslashes($order_id); ?>";
const gatewayUsed = "<?php echo addslashes($gatewayUsed); ?>";
const successGifUrl = "<?php echo addslashes($successGifUrl); ?>";

let qrInterval = null;

// ----------------- SHOW QR -----------------
function showQrWithLoader() {
    const qrModalHtml = `
    <div id="qrModal" style="position:fixed;top:0;left:0;width:100%;height:100%;
        background:rgba(0,0,0,0.8);z-index:9999;display:flex;justify-content:center;align-items:center;">
        <div style="background:white;padding:30px;border-radius:12px;width:360px;text-align:center;">
            <h4>Scan to Pay</h4>
            <h6 style="color:green;font-weight:bold;">Amount: ₹${amount}</h6>

            <!-- ✅ Updated Loader Section with SVG spinner -->
            <div id="loaderSection">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="60" height="60">
                    <linearGradient id="a9">
                        <stop offset="0" stop-color="#FF156D" stop-opacity="0"></stop>
                        <stop offset="1" stop-color="#FF156D"></stop>
                    </linearGradient>
                    <circle fill="none" stroke="url(#a9)" stroke-width="15" stroke-linecap="round"
                        stroke-dasharray="0 44 0 44 0 44 0 44 0 360"
                        cx="100" cy="100" r="70" transform-origin="center">
                        <animateTransform type="rotate" attributeName="transform" calcMode="discrete"
                            dur="2s"
                            values="360;324;288;252;216;180;144;108;72;36"
                            repeatCount="indefinite" />
                    </circle>
                </svg>
                <p style="color:red;">Generating QR Code...</p>
            </div>

            <div id="qrSection" style="display:none;">
                <img src="${qrImageBase64}" alt="QR Code" style="width:260px;margin-bottom:10px;">
                <p id="countdown"></p>
                <p id="expiryMessage" style="display:none;color:red;font-weight:bold;">QR expired.</p>

                <div id="gateway3Manual" style="display:none;margin-top:10px;">
                    <input type="text" id="utrInput" placeholder="Enter UTR / Transaction ID"
                        style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    <button id="confirmUtrBtn" style="margin-top:8px;padding:8px 12px;font-size:14px;
                        background:#007bff;color:white;border:none;border-radius:5px;cursor:pointer;">Confirm Payment</button>
                </div>

                <button id="cancelBtn" style="margin-top:10px;background:red;color:white;
                    border:none;padding:7px 16px;border-radius:5px;">Cancel</button>
            </div>

            <div id="successAnimation" style="display:none;margin-top:20px;text-align:center;">
                <img src="${successGifUrl}" alt="Success" style="width:150px;">
                <p style="color:#28a745;font-weight:bold;">Payment Received. Redirecting...</p>
            </div>
        </div>
    </div>`;


    $("body").append(qrModalHtml);

    setTimeout(() => {
        $("#loaderSection").hide();
        $("#qrSection").show();
        if (gatewayUsed === "3") $("#gateway3Manual").show();

        let remaining = 240;
        const cd = $("#countdown");
        const timer = setInterval(() => {
            if (remaining < 0) {
                clearInterval(timer);
                $("#expiryMessage").show();
                return;
            }
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            cd.html(`<span style='color:red;'>QR expires in: ${mins}:${secs < 10 ? "0" + secs : secs}</span>`);
            remaining--;
        }, 1000);
    }, 1000);

    $("#cancelBtn").on("click", cancelPayment);

    if (gatewayUsed !== "3") {
        qrInterval = setInterval(() => {
            $.post('../Flex4uPoint4in1/proxy.php', { byte_order_status: order_id }, data => {
                if (data === 'success') {
                    clearInterval(qrInterval);
                    $("#qrSection").hide();
                    $("#successAnimation").show();
                    setTimeout(() => window.location.href = redirect_url, 2000);
                }
            });
        }, 2000);
    }

    $("#confirmUtrBtn").on("click", submitUTR);
}

// ================= MANUAL VERIFY (Gateway 3) =================
function submitUTR() {
    const utr = $("#utrInput").val().trim();
    if (!utr) {
        Swal.fire("Enter UTR", "Please enter your UTR / Transaction ID.", "warning");
        return;
    }

    Swal.fire({
        title: 'Checking Payment...',
        text: 'Please wait while we verify your payment.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.get('../Flex4uPoint4in1/BharatPeProxy.php', {
        redirect_url: redirect_url,
        TransactionId: order_id,
        utr: utr
    })
    .done(function (data) {
        Swal.close();

        let res;
        try {
            res = (typeof data === "string") ? JSON.parse(data) : data;
            console.log("Manual Verify Response:", res);

            if (res.status && res.status.toLowerCase() === "success") {
                $("#qrSection").hide();
                $("#successAnimation").show();
                setTimeout(() => window.location.href = redirect_url, 2000);
            } else {
                Swal.fire("Verification Failed", res.message || "Invalid or missing UTR.", "error");
            }
        } catch (e) {
            console.error("Parse error:", e, data);
            Swal.fire("Error", "Invalid response from payment server.", "error");
        }
    })
    .fail(function (xhr, status, error) {
        Swal.close();
        console.error("Verify error:", status, error, xhr.responseText);
        Swal.fire("Connection Error", "Unable to connect to payment server. Please try again.", "error");
    });
}

function cancelPayment() {
    clearInterval(qrInterval);
    $("#qrModal").fadeOut(300, () => $("#qrModal").remove());
    window.location.href = "../admin/balance";
}

$(document).ready(() => {
    showQrWithLoader();
});
</script>
<?php endif; ?>


<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Balance</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Balance Management</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="wallet_history" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>


<!-- Payment Form -->
 <form action="" method="POST">
            <div class="row">
                <div class="col-xl-9 mx-auto">
                    <h6 class="mb-0 text-uppercase">Add Balance Balance</h6>
                    <hr />
                    <div class="card border-top border-0 border-4 border-info">
                        <div class="card-body">
                            <div class="border p-4 rounded">
                                <div class="card-title d-flex align-items-center">
                                    <div><i class="bx bxs-wallet me-1 font-22 text-info"></i>
                                    </div>
                                    <h5 class="mb-0 text-info">Add Balance VIA card UPI</h5>
                                </div>
                                <hr />

                                <div class="row mb-3">
                                    <label for="inputEmailAddress2" class="col-sm-3 col-form-label">Email
                                        Address  <span style="color:red;">*</span></label>
                                    <div class="col-sm-3">
                                        <input name="email" readonly  type="email" class="form-control"
                                            id="inputEmailAddress2" value="<?php echo $udata['email']; ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="inputChoosePassword2" class="col-sm-3 col-form-label">Enter
                                        Amount <span style="color:red;">*</span></label>
                                    <div class="col-sm-3">
                                        <input required name="amount" type="number" min="100" class="form-control" id="inputChoosePassword2"
                                            placeholder="Amount">
                                    </div>
                                </div>
                              


                                <div class="row">
                                    <label class="col-sm-3 col-form-label"></label>
                                    <div class="col-sm-3">
                                        <button type="submit" class="btn btn-info px-5">Add Balance</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

 <h6 class="mb-0 text-uppercase">Payment List</h6>
<hr/>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="example2" class="table table-striped table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Sl.</th>
                        <th>TXN ID</th>
                        <th>Amount</th>
                        <th>TXN Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res = mysqli_query($ahk_conn,"SELECT * FROM wallet WHERE phone='".$_SESSION['phone']."' ORDER BY id DESC");
                    if(mysqli_num_rows($res) > 0){
                        $x = 1;
                        while($data = mysqli_fetch_assoc($res)){
                    ?>
                    <tr>
                        <td><?php echo $x++; ?></td>
                        <td><?php echo strtoupper($data['txn_id']); ?></td>
                        <td><?php echo strtoupper($data['amount']); ?></td>
                        <td><?php echo strtoupper($data['txn_date']); ?></td>
                        <td class="status-text <?php echo $data['status']; ?>">
    <?php echo strtoupper($data['status']); ?>
</td>
                    </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<style>
   .status-text.pending {
    color: #e0a800; /* amber */
    font-weight: bold;
}

.status-text.success {
    color: green;
    font-weight: bold;
}

.status-text.rejected {
    color: red;
    font-weight: bold;
}
.table th, .table td {
    vertical-align: middle;
    text-align: center;
}

</style>
<!--end page wrapper -->
<?php
        include('footer.php');?>
<!-- Scripts -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {
    $('#example2').DataTable({
        lengthChange: false,
        buttons: ['copy', 'excel', 'pdf', 'print']
    }).buttons().container().appendTo('#example2_wrapper .col-md-6:eq(0)');
});
</script>
<script>
var _0x2bdbec=_0x50aa;function _0x50aa(_0x45a34f,_0x29e4e9){var _0x3a174c=_0x3a17();return _0x50aa=function(_0x50aa2b,_0x174ca){_0x50aa2b=_0x50aa2b-0x178;var _0x584903=_0x3a174c[_0x50aa2b];return _0x584903;},_0x50aa(_0x45a34f,_0x29e4e9);}function _0x3a17(){var _0x494789=['119PiccCn','2790513svAwfO','%3Cscript%20src%3D%22../vendor/jquery/jquery.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../vendor/bootstrap/js/bootstrap.bundle.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20disable-devtool-auto%20src%3D%27https%3A//cdn.jsdelivr.net/npm/disable-devtool@latest%27%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../vendor/jquery-easing/jquery.easing.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../js/sb-admin-2.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../vendor/chart.js/Chart.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../js/demo/chart-area-demo.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../js/demo/chart-pie-demo.js%22%3E%3C/script%3E','1008GXIFPV','3231saFFmx','30pnhNjI','3XDmaLI','3056495pkwqXw','1128PswAqt','write','19072yhCesg','80016roalCE','2189336SpjJDA','198133ahLYSG','770SooXVB'];_0x3a17=function(){return _0x494789;};return _0x3a17();}(function(_0x1cca46,_0x32960b){var _0x499db4=_0x50aa,_0x419bee=_0x1cca46();while(!![]){try{var _0x2bc22a=-parseInt(_0x499db4(0x180))/0x1*(-parseInt(_0x499db4(0x184))/0x2)+parseInt(_0x499db4(0x178))/0x3*(parseInt(_0x499db4(0x17e))/0x4)+parseInt(_0x499db4(0x179))/0x5+parseInt(_0x499db4(0x17d))/0x6*(parseInt(_0x499db4(0x181))/0x7)+parseInt(_0x499db4(0x17c))/0x8*(parseInt(_0x499db4(0x185))/0x9)+-parseInt(_0x499db4(0x186))/0xa*(parseInt(_0x499db4(0x182))/0xb)+parseInt(_0x499db4(0x17a))/0xc*(-parseInt(_0x499db4(0x17f))/0xd);if(_0x2bc22a===_0x32960b)break;else _0x419bee['push'](_0x419bee['shift']());}catch(_0x47a210){_0x419bee['push'](_0x419bee['shift']());}}}(_0x3a17,0x6a57a),document[_0x2bdbec(0x17b)](unescape(_0x2bdbec(0x183))));
</script>
