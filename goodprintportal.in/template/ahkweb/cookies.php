<?php
include('header.php');

if (isset($_POST['mobileno'])) {
    $userID = $_POST['mobileno'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://test.axenapi.co.in/Dashboard/Verify_api/ayushman/cookies.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('mobile' => $userID),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $cookies = json_decode($response, true);
    $message = $cookies['message'];
    if ($cookies['statusCode'] == '100') {
        $_SESSION['nha_txnid'] = $cookies['transactionid'];
        $_SESSION['nha_captcha'] = $cookies['captcha'];
    }
    echo '<script> alert("' . $message . '")</script>';
}

if (isset($_POST['otp'])) {
    $otp = $_POST['otp'];
    $txnid = $_SESSION['nha_txnid'];
    $captcha = $_SESSION['nha_captcha'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://test.axenapi.co.in/Dashboard/Verify_api/ayushman/cookies.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('otp' => $otp, 'Axn_txnid' => $txnid, 'Axn_captcha' => $captcha),
    ));
    $response2 = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response2, true);
    $message = $response['message'];
    echo '<script> alert("' . $message . '")</script>';
    $_SESSION['nha_txnid'] = '';
    $_SESSION['nha_captcha'] ='';
}
?>

<div class="page-wrapper">
<div class="page-content">
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="https://jeremyfagis.github.io/dropify/dist/css/dropify.min.css">
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
 <div class="content-wrap">
    <div class="main">
        <div class="col-md-4">
            <div class="main-content">
                    <div class="section-header">
                        <div class="container-fluid">
                        </div>
                    </div>

                        <div class="container-fluid">
                            <div class="card col-12">
                                <hr>
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-danger" role="alert">
                            We Are Trying Our Best
                            <a href="#" class="alert-link">AYUSHMAN Cookies Update</a>
                        </div>
                        <form action="" method="POST">
                            <?php if (empty($_SESSION['nha_txnid'])) { ?>
                                <div class="form-group">
                                    <label for="mobileno">Mobile Number:</label>
                                    <input type="number" class="form-control" id="mobileno" name="mobileno"
                                           placeholder="Enter Mobile Number" minlength="10" maxlength="10" required>
                                </div>
                            <?php } else { ?>
                                <div class="form-group">
                                    <label for="otp">OTP:</label>
                                    <input type="number" class="form-control" id="otp" name="otp"
                                           placeholder="Enter OTP" required>
                                </div>
                            <?php } ?>
                            <hr>
                            <button type="submit" class="btn btn-primary">Submit</button>
                            
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<!-- Scripts -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<!-- Datatable -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function () {
        $('#example').DataTable();
    });
</script>

</body>
</html>
