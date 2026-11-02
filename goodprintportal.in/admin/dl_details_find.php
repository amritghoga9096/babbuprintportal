<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fetch fee from database
 $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='Driving_Licence_Find'"));
 $fee = $price['price'] ?? 0;

// --- API CONFIGURATION (Dynamic) ---
// Ensure these variables are defined in your config or header file
// $auth_url   = "https://apiadda.in/"; 
// $api_key    = "...";
// $secret_key = "...";

 $apiData = []; 
 $errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['dlNo']) && !empty($_POST['dob'])) {
    $dlNo = mysqli_real_escape_string($ahk_conn, $_POST['dlNo']);
    $dob = mysqli_real_escape_string($ahk_conn, $_POST['dob']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        // Construct Dynamic URL
        $url = $auth_url . "api_service_v2/dl_dob_verify.php?" . http_build_query([
            'dl_number'  => $dlNo,
            'dob'        => $dob,
            'api_key'    => $api_key,
            'secret_key' => $secret_key
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ]);

        $response = curl_exec($curl);
        $curl_err = curl_error($curl);
        curl_close($curl);

        if (!$response) {
            $errore = $curl_err ?: 'Empty response from DL API.';
            echo "<script>$(function(){ Swal.fire('Error', '$errore', 'error'); }); setTimeout(() => { window.location=''; }, 3000);</script>";
        } else {
            $resdata = json_decode($response, true);
            
            if (isset($resdata['success']) && $resdata['success'] === true && isset($resdata['data'])) {
                // Deduct wallet balance
                mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid,amount,balance,purpose,status,type) VALUES ('$appliedby','$fee','$debit_fee','DL Details Find $dlNo','1','Debit')");
                
                $apiData = $resdata['data'];

            } else {
                $errorMsg = $resdata['message'] ?? 'DL Data Not Found or API Error';
                echo "<script>$(function(){ Swal.fire('Error', '" . addslashes($errorMsg) . "', 'error'); }); setTimeout(() => { window.location=''; }, 3000);</script>";
            }
        }
    } else {
        echo "<script>$(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    }
}
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">DL Details Find</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <!-- Form Section -->
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-dark text-center"><strong>DL Details Find</strong></h3>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" class="row g-3">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="dlNo">Enter Driving License Number</label>
                                        <input name="dlNo" type="text" id="dlNo" placeholder="Enter DL Number" class="form-control" required>
                                    </div>

                                    <div class="form-group mt-3">
                                        <label for="dob">Enter Date of Birth</label>
                                        <input name="dob" type="text" id="dob" class="form-control" placeholder="DD-MM-YYYY" maxlength="10" required>
                                    </div>

                                    <script>
                                    const dobInput = document.getElementById('dob');
                                    dobInput.addEventListener('input', function() {
                                        let v = this.value.replace(/\D/g,'');
                                        if(v.length>2 && v.length<=4) v = v.slice(0,2)+'-'+v.slice(2);
                                        else if(v.length>4) v = v.slice(0,2)+'-'+v.slice(2,4)+'-'+v.slice(4,8);
                                        this.value = v;
                                    });
                                    </script>

                                    <hr>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <input class="form-control" value="Fee ₹ <?php echo htmlspecialchars($fee); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <button class="btn btn-success" type="submit"><i class="fa fa-check-circle"></i> Submit</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Response Section -->
                <?php if(!empty($apiData)) { ?>
                    <div class="col-lg-7">
                        <div class="card shadow-sm" style="background-color:#FCF3CF;" id="printSection">
                            <div class="card-body">
                                <h5 class="mb-4 text-center"><strong>DL Details</strong></h5>

                                <!-- Helper function to clean image data -->
                                <?php
                                function getImageSrc($val) {
                                    if (empty($val)) return '';
                                    // Fix escaped slashes \/ which break images
                                    $val = str_replace('\/', '/', $val); 
                                    $val = trim($val);
                                    // Ensure prefix exists (API usually sends it, but just in case)
                                    if (strpos($val, 'data:image') === false) {
                                        return 'data:image/jpeg;base64,' . $val;
                                    }
                                    return $val;
                                }
                                ?>

                                <!-- Photo & Signature Flex Section -->
                                <div class="d-flex justify-content-center gap-4 mb-4 flex-wrap">
                                    <div class="text-center">
                                        <?php 
                                        $photoSrc = getImageSrc($apiData['photo_base64'] ?? '');
                                        if(!empty($photoSrc)): 
                                        ?>
                                            <div class="mb-2"><strong>Photo</strong></div>
                                            <div class="border rounded shadow-sm p-2" style="width:150px; height:150px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                                <img src="<?php echo $photoSrc; ?>" style="max-width:100%; max-height:100%;">
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted">Photo not available</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-center">
                                        <?php 
                                        $signSrc = getImageSrc($apiData['signature_base64'] ?? '');
                                        if(!empty($signSrc)): 
                                        ?>
                                            <div class="mb-2"><strong>Signature</strong></div>
                                            <div class="border rounded shadow-sm p-2" style="width:150px; height:150px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                                <img src="<?php echo $signSrc; ?>" style="max-width:100%; max-height:100%;">
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted">Signature not available</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- DL Details Table -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <tbody>
                                        <?php
                                        function printVal($val) {
                                            if (is_array($val)) {
                                                if (isset($val[0]) && is_string($val[0])) {
                                                    return htmlspecialchars(implode(', ', $val));
                                                }
                                                return htmlspecialchars(json_encode($val));
                                            }
                                            return htmlspecialchars($val);
                                        }

                                        $displayFields = [
                                            'dl_number' => 'DL Number',
                                            'issue_date' => 'Issue Date',
                                            'date_of_birth' => 'Date of Birth',
                                            'name' => 'Name',
                                            'father_name' => "Father's Name",
                                            'gender' => 'Gender',
                                            'mobile_number' => 'Mobile Number',
                                            'age' => 'Age'
                                        ];

                                        foreach($displayFields as $key => $label){
                                            if(!empty($apiData[$key])){
                                                echo "<tr><th>".htmlspecialchars($label)."</th><td>".printVal($apiData[$key])."</td></tr>";
                                            }
                                        }
                                        
                                        if(!empty($apiData['address']) && is_array($apiData['address'])){
                                            echo "<tr><th>Address</th><td>".htmlspecialchars(implode(', ', $apiData['address']))."</td></tr>";
                                        }

                                        if(!empty($apiData['vehicle_classes']) && is_array($apiData['vehicle_classes'])){
                                            $classes = array_column($apiData['vehicle_classes'], 'class');
                                            echo "<tr><th>Vehicle Classes</th><td>".htmlspecialchars(implode(', ', $classes))."</td></tr>";
                                        }

                                        if(!empty($apiData['validity'])){
                                            $val = $apiData['validity'];
                                            $status = $val['status'] ?? 'N/A';
                                            $nonTr = $val['non_transport'] ?? 'N/A';
                                            echo "<tr><th>Validity (Non-Transport)</th><td>".htmlspecialchars($nonTr)." <span class='badge bg-".($status=='valid'?'success':'danger')."'>".htmlspecialchars($status)."</span></td></tr>";
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</html>