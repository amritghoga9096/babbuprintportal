<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

$fireapi_url = 'fireapi.info';
$fireapi_key = 'FAa4f640d684943cc747d218e1d2ca3b4eaf07dc302ff124758be9c9a1700afe';

$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='rc_pdf_fireapi' "));
$fee = !empty($price['price']) ? $price['price'] : 50;
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<?php
    
if ($_POST['rc_number']) {
    $rc_number = $_POST['rc_number'];
    $appliedby = $udata['phone'];
    $application_no = "fireapi_" . rand(100000, 999999);
    $debit_fee =  $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
         
        
         $url = 'https://'.$fireapi_url.'/serviceApi/V1/rc_verification.php';
$data = [
    'rc_number' => $rc_number,
    'apiKey' => $fireapi_key,
    'order_id' => $application_no
];

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($data),
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
));


        $response = curl_exec($curl);
        $curl_err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $resdata = json_decode($response, true) ?? [];
        $pdf = $resdata['pdf_base64'] ?? '';
        $message = $resdata['message'] ?? 'Unknown error';

        if ($curl_err || !$response) {
            $debug_msg = "cURL Error: " . htmlspecialchars($curl_err . " | HTTP: " . $httpcode . " | Raw: " . substr($response, 0, 300), ENT_QUOTES);
            echo "<script>$(function(){ Swal.fire('cURL Failed', '".$debug_msg."', 'error') }); setTimeout(() => { window.location=''; }, 10000);</script>";
        } elseif ($resdata['StatusCode'] !== 100) {
            $debug_details = "Status: " . htmlspecialchars(($resdata['StatusCode'] ?? 'N/A') . " | Msg: " . $message . " | Raw: " . substr($response, 0, 300), ENT_QUOTES);
            echo "<script>$(function(){ Swal.fire('".htmlspecialchars($message, ENT_QUOTES)."', '".$debug_details."', 'error') }); setTimeout(() => { window.location=''; }, 10000);</script>";
     
     
         } elseif ($resdata['StatusCode'] === 100) {
              date_default_timezone_set('Asia/Kolkata');
                $timestamp = date("Y-m-d H:i:s");
             $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
             
             
           $database = mysqli_query($ahk_conn, "INSERT INTO `rc_pdf_fireapi`(`username`, `rc_vehical_no`,`status`, `pdf`, `date`) VALUES ('{$udata['phone']}','{$resdata['rc_number']}','{$resdata['message']}','{$resdata['pdf_base64']}','$timestamp')");
           
           
            $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','RC Vehical PDF','1','Debit')");
     echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
     
     
        <script>
            Swal.fire({ icon: 'success', title: 'Success', text: 'Download Successfully', timer: 2000 });
        </script>";
            
        } 
    } else {
        echo "<script>$(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    }
}
?>
<div class="content-wrap">
    <div class="main">
        <div class="col-md-12">
            <div class="main-content">
<div class="page-wrapper">
    <div class="page-content">
        <div class="mobile-menu-overlay"></div>
        <div class="main-container">
            <div class="col-lg-12">
                <div class="card" style="margin-left: 10px; padding-left: 30px; padding-top: 12px; box-shadow: 1px 5px 5px 5px;">
                    <div class="stat-widget-two">
                        <div class="stat-content">
                            <div class="stat-text">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-lg-4 col-md-6 col-sm-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="alert alert-danger" role="alert">
                                                        <a href="" class="alert-link">RC PDF (Fire API)</a>
                                                    </div>
                                                    <form action="" method="POST" class="row g-3">
                                                        <div class="card-body">
                                                            <div class="col-md-12">
                                                                <div class="form-group">
                                                                    <label for="rc_number">Enter Vehical Number</label>
                                                                    <input name="rc_number" type="text" id="rc_number"  placeholder="Enter Vehical Number" class="form-control" required>
                                                                </div>
                                                                <hr>
                                                               
                                                                <div class="row mt-3">
                                                                  <div class="col-md-6">
                                                                       <input class="form-control" value="Fee ₹ <?php echo $fee; ?>" readonly>
                                                                   </div>
                                                                   <div class="col-md-6 text-end">
                                                                       <button class="btn btn-success" name="submit" id="submit"><i class="fa fa-check-circle"></i> Submit</button>
                                                                   </div>
                                                               </div>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($resdata['StatusCode'] === 100) { ?>
                                            <div class="col-lg-8 col-md-6 col-sm-6">
                                                <div class="card" style="background-color: #FCF3CF;" id="printSection">
                                                    <div class="card-body">
                                                        <h5 class="mb-3">RC Vehical Details (Fire API)</h5>
                                                        <table class="table table-bordered table-striped">
                                                            <tbody>
                                                                <tr>
                                                                    <th>Vehical Number</th>
                                                                    <td><?php echo htmlspecialchars($resdata['rc_number']); ?></td>
                                                                </tr>
                                                                
                                                                <tr>
                                                                   <th>VIEW PDF</th>
                                                                   <td>
                                                                       <a href="<?php echo htmlspecialchars($resdata['pdf_base64']); ?>" 
                                                                          download="<?php echo htmlspecialchars($resdata['rc_number']); ?>.pdf" 
                                                                          class="btn btn-sm btn-success">
                                                                           Download PDF
                                                                       </a>
                                                                   </td>
                                                               </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <br><br><br><br>
            </div>
        </div>
    </div>
</div>

<?php include('../template/ahkweb/footer.php'); ?>



<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</html>
