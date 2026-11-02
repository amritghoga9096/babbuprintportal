
<?php include('header.php'); ?>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Mobile Link With Otp</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="vote_mob_link_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Voter Mobile Link List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <!-- End Breadcrumb -->

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
            <style>
                .hidden {
                    display: none;
                }
            </style>
        </head>

        <body>
<?php
// Get captcha details from the API
// $details = file_get_contents("https://" . htmlspecialchars($flexsecureapiurl) . "/serviceApi/V1/voterCaptcha.php");
$json = json_decode($details, TRUE);
$captcha_img = $json['captcha'];
$captchaTxnId = $json['id'];

$api_key =   urlencode($flexsecure_key); // Replace with actual API key

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['find']) && !empty($_POST['epic_no'])) {
        $epic_no = $_POST['epic_no'];
        $mobile = $_POST['mobile'];

        // Prepare the API URL
        $apiUrl = "https://" . htmlspecialchars($flexsecureapiurl) . "/panel/serviceapi/voter/voter_mob_link.php?apikey=$api_key&epic=$epic_no&mobile=$mobile&type=otp";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute cURL request
        $response = curl_exec($ch);
        
        // Check for cURL errors
        if ($response === false) {
            echo '<div class="alert alert-danger" role="alert">
                    cURL request failed: ' . curl_error($ch) . '
                  </div>';
        } else {
            $result = json_decode($response, true);
            if ($result) {
                $applicantFullName = $result['name'] ?? 'Unknown';
                $mobileNumber = $mobile;
                $stateName = $result['state'] ?? 'Unknown';
                $statuss = $result['status'] ?? 'unknown';
                $messages = $result['message'] ?? 'No message provided';
                $errors = $result['error'] ?? null;

                if ($statuss == 'success') {
                    $epicNumber = $epic_no;

                    echo '<div id="success-alert" class="alert alert-success" role="alert">
                            Status: ' . $statuss . '
                          </div>';
                } else {
                    echo '<div class="alert alert-danger" role="alert">
                            ' . $statuss . ' - ' . $messages . '
                          </div>';
                    echo '<script>
                            setTimeout(function () {
                                window.location = "' . $redirectUrl . '";
                            }, 5000);
                          </script>';
                }
            }
        }

        // Close cURL session
        curl_close($ch);
    }
}?>

 <?php
  $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='v_mobile_link' "));
  $fee = $price['price'];
  $balance=$udata['balance'];
  $mobile = $udata['phone'];
  $debit_fee =  $balance - $fee;
  if($balance>$fee){
      
if (isset($_POST['submit_mobile']) && !empty($_POST['new_mobile'])) {
    $newMobileNumber = $_POST['new_mobile'];
    $epic = $_POST['epic'];
    $place = $_POST['place'];
    $ptp = $_POST['otp'];

    $captchap = $_POST['captcha'];
    $captchaid = $_POST['captchaTxnId'];

    // Prepare the API URL
    $apiUrl = "https://" . htmlspecialchars($flexsecureapiurl) . "/panel/serviceapi/voter/voter_mob_link.php?apikey=$api_key&epic=$epic&mobile=$newMobileNumber&type=otpv&otp=$ptp&cid=123";

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if ($response === false) {
        echo '<div class="alert alert-danger" role="alert">
                cURL request failed: ' . curl_error($ch) . '
              </div>';
    } else {
        $data = json_decode($response, true);
        $status = $data['status'] ?? 'unknown';
        $message = ($data['message'] ?? '') . ($data['msg'] ?? '');
        $refId = $data['refId'] ?? null;
        $rerror = $data['error'] ?? null;

        $alertType = $status == "Success" ? 'success' : 'error';
        $redirectUrl = $status == "Success" ? "" : "' . $redirectUrl . '";

        // Debited code and save card summary 
        if (!empty($refId)) {
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$mobile'");
            $new_balance = $balance - $fee;
            date_default_timezone_set("Asia/Kolkata");
            $timestamp = date('d/m/Y g:i:s');
            $cardinst = "INSERT INTO m_link(epic_no, mobile_no, response, userid, date) VALUES ('$epic','$newMobileNumber','$message','$mobile','$timestamp')";
            $updatehistory = mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$mobile','$fee','$debit_fee','$epic Voter Mobile Link','1','Debit')");
            $res = mysqli_query($ahk_conn, $cardinst);   
        }

        echo "<script>
                Swal.fire({
                    title: '$rerror!',
                    text: 'Mobile Number Link $status Your Reference No: $refId - $message',
                    icon: '$alertType',
                    confirmButtonText: 'OK'
                });
              </script>";

        echo '<script>
                setTimeout(function () {
                    window.location = "";
                }, 5000);
              </script>';
    }

    // Close cURL session
    curl_close($ch);
}  }else{
     $msg="<script>
        Swal.fire({
          title:'Balance Low ! Recharge Now',
          text:  'Warning!',
          icon: 'warning',
          confirmButtonText: 'OK'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location = 'wallet.php';
          }
        });
      </script>";
}
        ?>

		   <div class="container-fluid pt-4 px-4">
                <div class="row">
                    <!-- Left Side: Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                               <h3 class="card-title text-dark text-center"><strong>Voter Mobile No Link Instant</strong></h3>
                            </div>
                            <div class="card-body">
                                <a href="vot_org_instant" class="btn btn-dark w-100 mb-3">Voter Oringnal PDF Download Here</a>
                            <h6 > <?php echo $msg; ?></h6>
          <!-- Form 1: Enter EPIC number -->
         
             <form id="epic_form" class="user <?php echo ($epicNumber) ? 'hidden' : ''; ?>" method="POST">
               <div class="form-group mb-3">
                     <label for="epic_no">Enter Epic Number<span class="text-danger">*</span></label>
                     <input id="epic_no" name="epic_no" class="form-control border-success" type="text" value="<?php echo $epicNumber; ?>" required <?php echo ($epicNumber) ? 'disabled' : ''; ?>>
                 </div>
   <br>
   <div class="form-group mb-3">
                     <label for="epic_no">Enter New Mobile Number<span class="text-danger">*</span></label>
                     <input id="epic_no" name="mobile" class="form-control border-success" type="text" value="<?php echo $mobile; ?>" required <?php echo ($epicNumber) ? 'disabled' : ''; ?>>
                 </div><br>
                 
                 <button type="submit" name="find" class="btn btn-primary w-100" <?php echo ($epicNumber) ? 'disabled' : ''; ?>>
                     <i class="fa fa-check-circle"></i>Verify
                 </button>
                 <div class="col-12 ml-2 d-flex justify-content-center">
    <div class="fee-container text-center">
        <h5 class="text-warning">Application Fee: 
            <?php  
                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='v_mobile_link'")); 
                echo "₹" . $price['price'];
            ?>
        </h5>
    </div>
</div>

<style>
    .fee-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 15px;
        max-width: 300px;
        width: 100%;
        margin-top: 10px;
        margin-bottom: 10px;
    }

    .fee-container h5 {
        color: #ff9800; /* Bright yellow/orange color for emphasis */
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }
</style>

             </form>


            <!-- Form 2: Displayed upon successful first API response -->
            <form id="additional_fields_form" class="user <?php echo ($epicNumber) ? '' : 'hidden'; ?>" method="POST">
              <div class="form-group mb-3">
                    <label for="">Full Name<span class="text-danger">*</span></label>
                    <input id="" name="" class="form-control" type="" readonly value="<?php echo $applicantFullName; ?>" required>
                </div> 
            <div class="form-group mb-3">
                    <label for="new_mobile">New Mobile Number<span class="text-danger">*</span></label>
                    <input id="epic" name="epic" class="form-control" type="hidden" value="<?php echo $epicNumber; ?>" required>
                    <input id="new_mobile" name="new_mobile" class="form-control border-primary" type="number" placeholder="ENTER NEW MOBILE NUMBER" value="<?php echo $mobileNumber; ?>" required>
                                   
                </div>
           <div class="form-group mb-3">
                    <label for="otp">OTP<span class="text-danger">*</span></label>
                    <input id="otp" name="otp" class="form-control border-primary" type="number" placeholder="ENTER 6 DIGIT OTP" value="" required>
                </div>
                <!--<div class="form-group">-->
                    <!--<label for="place">Ca<span class="text-danger">*</span></label>-->
                <!--    <img src="data:application/image;base64,<?php echo $captcha_img; ?>"  class="border-primary mt-2" alt="captchaa" />-->
                <!--</div>-->
           <div class="form-group mb-3">
                    <!--<label for="captcha">Enter Captcha<span class="text-danger">*</span></label>-->
                   
                </div>
<br>
               <button type="submit" name="submit_mobile" class="btn btn-primary w-100"><i class="fa fa-check-circle"></i>Submit</button>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('getOTPButton').addEventListener('click', function() {
    var newMobile = document.getElementById('new_mobile').value;
    // Make API call with API key
    fetch(`https://" . htmlspecialchars($flexsecureapiurl) . "/panel/serviceapi/voter/voter_mob_link.php?nmobile=${newMobile}`)
    .then(response => {
        return response.json();
    })
    .then(data => {
        console.log('API response:', data);
        if (data.error) {
            // Display error message in alert
            alert(data.error);
        } else if (data.message) {
            // Display message from API in alert
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while sending OTP. Please try again later.');
    });
});
</script>
    <!-- Add your footer content here -->

    <script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
    <script src="../template/ahkweb/assets/js/app.js"></script>
<script>
    // Hide the success and error alerts after 3 seconds
    setTimeout(function () {
        document.getElementById('success-alert').style.display = 'none';
        // document.getElementById('error-alert').style.display = 'none';
    }, 3000);
</script>

</body>
</html>
