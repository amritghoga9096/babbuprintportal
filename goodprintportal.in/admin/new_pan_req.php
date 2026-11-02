<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
date_default_timezone_set('Asia/Kolkata');
$allowRegistration = "No";
if ($allowRegistration !== 'yes') {
    echo '
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: "error",
            title: "⚠️ UTI Server is Down",
            text: "The UTI server is currently unavailable. Please try again later.",
            confirmButtonText: "Go Back"
        }).then(() => {
            history.back(); // go back to previous page
        });
    });
</script>

    ';
    exit; // stop executing the rest of the page
}
$resdata = [];

if (isset($_POST['check']) && $_POST['check'] === "pan") {
    // Generate application number and gather user info
    $application_no = "NewPanReqFLX" . rand(100000, 999999);
    $username = $udata['phone'];
    $appliedby = $udata['phone'];
    $clint_id = $clint;

    // Sanitize inputs
    $fullname = strtoupper(trim(mysqli_real_escape_string($ahk_conn, $_POST['fullname'])));
    $dateofbirth = trim(mysqli_real_escape_string($ahk_conn, $_POST['dateofbirth']));
    $gender = strtoupper(trim(mysqli_real_escape_string($ahk_conn, $_POST['gender'])));
    $fathername = strtoupper(trim(mysqli_real_escape_string($ahk_conn, $_POST['fathername'])));
    $fulladdress = strtoupper(trim(mysqli_real_escape_string($ahk_conn, $_POST['fulladdress'])));
    $email = strtolower(trim(mysqli_real_escape_string($ahk_conn, $_POST['email'])));
    $mobileNumber = trim(mysqli_real_escape_string($ahk_conn, $_POST['mobileNumber']));
    $aadhaarNumber = trim(mysqli_real_escape_string($ahk_conn, $_POST['aadhaarNumber']));

    // Upload images function (same as before)
    function uploadImage($file, $uploadDir, $domainPath)
    {
        if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpeg', 'jpg', 'png'];
            if (!in_array($ext, $allowedTypes))
                return '';
            if (!file_exists($uploadDir))
                mkdir($uploadDir, 0755, true);
            $newFileName = uniqid('np_', true) . '.' . $ext;
            $destination = $uploadDir . $newFileName;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                return rtrim($domainPath, '/') . '/' . $newFileName;
            }
        }
        return '';
    }

    // Setup paths
    $uploadDir = realpath(__DIR__ . '/../admin/uploads/NewPanRequest/') . '/';
    $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $domainPath = $domain . "/admin/uploads/NewPanRequest";

    $aadhaarimg = uploadImage($_FILES['aadhaarimg'], $uploadDir, $domainPath);
    $photoimg = uploadImage($_FILES['photoimg'], $uploadDir, $domainPath);
    $signimg = uploadImage($_FILES['signimg'], $uploadDir, $domainPath);

    if (!$aadhaarimg || !$photoimg || !$signimg) {
        echo "<script>
        $(function(){
            Swal.fire({
                title: 'Upload Error',
                text: 'One or more images failed to upload or invalid file type.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'new_pan_req'; // Redirect to new_pan_req page
            });
        });
    </script>";
        exit;
    }


    // Get fee and user balance
    $price_query = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='new_pan_request'");
    if (!$price_query) {
        echo "<script>$(function(){ Swal.fire('Error', 'Pricing info fetch failed.', 'error') });</script>";
        exit;
    }
    $price_row = mysqli_fetch_assoc($price_query);
    $fee = floatval($price_row['price']);
    $wallet_amount = floatval($udata['balance']);
    $debit_fee = $wallet_amount - $fee;

    if ($wallet_amount < $fee) {
        echo "<script>
            $(function(){ Swal.fire('Insufficient Wallet Balance', 'Please Recharge', 'error') });
            setTimeout(() => { window.location='wallet'; }, 1500);
        </script>";
        exit;
    }

    // Prepare API URL
    $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v2/NewPanRequest/Apply.php?apiKey=" . urlencode($flexsecure_key) .
        "&fullname=" . urlencode($fullname) .
        "&dateofbirth=" . urlencode($dateofbirth) .
        "&gender=" . urlencode($gender) .
        "&fathername=" . urlencode($fathername) .
        "&fulladdress=" . urlencode($fulladdress) .
        "&email=" . urlencode($email) .
        "&mobileNumber=" . urlencode($mobileNumber) .
        "&aadhaarimg=" . urlencode($aadhaarimg) .
        "&photoimg=" . urlencode($photoimg) .
        "&aadhaarNumber=" . urlencode($aadhaarNumber) .
        "&signimg=" . urlencode($signimg);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
    ]);
    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        $curl_error = curl_error($curl);
        curl_close($curl);
        echo "<script>$(function(){ Swal.fire('Curl Error', '" . addslashes($curl_error) . "', 'error') });</script>";
        exit;
    }
    curl_close($curl);

    $resdata = json_decode($response, true);

    if (isset($resdata['status']) && $resdata['status'] == "100") {
        $statusMessage = $resdata['statusMessage'];
        $message_code = $resdata['status']; // keep as string to bind as varchar

        // Deduct balance
        $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
        if (!$debit) {
            echo "<script>$(function(){ Swal.fire('Error', 'Failed to debit balance.', 'error') });</script>";
            exit;
        }

        // Insert wallet history
        $wallet_insert = mysqli_query($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$appliedby', '$fee', '$debit_fee', 'Pan Request (New PAN)', '1', 'Debit')");
        if (!$wallet_insert) {
            echo "<script>$(function(){ Swal.fire('Error', 'Failed to insert wallet history.', 'error') });</script>";
            exit;
        }

        // Prepare insert query with correct column order and types
        // Prepare statement
        $stmt = mysqli_prepare($ahk_conn, "
    INSERT INTO new_pan_request 
    (application_no, fullname, dateofbirth, aadhaarNumber, gender, fathername, fulladdress, email, mobileNumber, aadhaarimg, photoimg, signimg, status, status_code, fee, old_balance, new_balance, message, username, clint_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

        if (!$stmt) {
            die("Prepare failed: " . mysqli_error($ahk_conn));
        }

        // Bind parameters
// note: status = 'pending' or other string, fee, old_balance, new_balance = decimal as double, others string
        $status = 'pending'; // or set dynamically
        $message = $statusMessage; // from API response or other message
// variables are assumed already sanitized
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssssssssdddsss",
            $application_no,
            $fullname,
            $dateofbirth,
            $aadhaarNumber,
            $gender,
            $fathername,
            $fulladdress,
            $email,
            $mobileNumber,
            $aadhaarimg,
            $photoimg,
            $signimg,
            $status,
            $message_code,
            $fee,
            $old_balance,
            $new_balance,
            $message,
            $username,
            $clint_id
        );

        $exec = mysqli_stmt_execute($stmt);
        if (!$exec) {
            die("Execute failed: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // Success message or redirect here

        if ($exec) {
            mysqli_stmt_close($stmt);
            $statusMsgJs = addslashes($statusMessage);
            echo "<script>
                $(document).ready(function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: '$statusMsgJs',
                        timer: 5000,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        window.location.href = 'new_pan_req_list';
                    });
                });
            </script>";
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>$(function(){ Swal.fire('Insert Failed', '$error', 'error') });</script>";
        }

    } else {
        $message = isset($resdata['error']) ? $resdata['error'] : "Unknown API Error";
        echo "<script>$(function(){ Swal.fire('API Error', '" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "', 'error') });</script>";
    }
}
?>

<!-- HTML Form (unchanged, but included for completeness) -->

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div
            class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">UTI Online PAN Application
                        </li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="new_pan_req_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> New PAN Apply List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row justify-content-center">
                <!-- Left Side: Form -->
                <div class="col-lg-7 mb-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h3 class="card-title text-center text-dark mb-4">
                                <strong>Online PAN Application (UTI)</strong>
                            </h3>

                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="check" value="pan">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Full Name *</label>
                                            <input type="text" name="fullname" class="form-control" required
                                                placeholder="Raju Kumar">
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Date of Birth *</label>
                                            <input type="date" name="dateofbirth" id="dob" class="form-control"
                                                required>
                                            <small id="ageMessage" class="mt-1 d-block"></small>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Gender *</label>
                                            <select name="gender" class="form-control" required>
                                                <option value="">Select</option>
                                                <option value="MALE">Male</option>
                                                <option value="FEMALE">Female</option>
                                                <option value="OTHER">Other</option>
                                            </select>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Father's Name *</label>
                                            <input type="text" name="fathername" class="form-control" required
                                                placeholder="Rajesh Kumar">
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Full Address *</label>
                                            <textarea name="fulladdress" class="form-control" rows="3" required
                                                placeholder="123, MG Road, Near Central Park, Bangalore - 560001, Karnataka, India"></textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label>Email *</label>
                                            <input type="email" name="email" class="form-control" required
                                                placeholder="example@example.com">
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Mobile Number *</label>
                                            <input type="text" name="mobileNumber" class="form-control" required
                                                placeholder="1234567890" pattern="\d{10}" maxlength="10" minlength="10"
                                                title="Enter exactly 10 digits" inputmode="numeric">
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Aadhaar Number *</label>
                                            <input type="text" name="aadhaarNumber" id="aadhaarNumber"
                                                class="form-control" required pattern="\d{12}" maxlength="12"
                                                placeholder="Enter 12-digit Aadhaar number"
                                                title="Please enter exactly 12 digits" inputmode="numeric">
                                            <small id="aadhaarStatus" class="mt-1 d-block"></small>
                                        </div>






                                        <div class="form-group mb-3">
                                            <label>Aadhaar Image *</label>
                                            <input type="file" name="aadhaarimg" accept="image/*" class="form-control"
                                                required>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Photo Image *</label>
                                            <input type="file" name="photoimg" accept="image/*" class="form-control"
                                                required>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label>Signature Image *</label>
                                            <input type="file" name="signimg" accept="image/*" class="form-control"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-3">
                                    <button type="submit" class="btn btn-primary px-5">Submit Request</button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <h5 class="text-warning">
                                    Application Fee:
                                    <?php
                                    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='new_pan_request'"));
                                    echo "₹" . $price['price'];
                                    ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Process & Image -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <img src="../admin/uploads/service_icom/utisevice.png" class="img-fluid mb-3 rounded"
                                alt="PAN Process">
                            <h5 class="text-primary mb-3">Application Process</h5>
                            <ol class="text-start small">
                                <li>Fill all required details carefully.</li>
                                <li>Upload clear Aadhaar, photo, and signature images.</li>
                                <li>Verify details before submitting.</li>
                                <li>Application will be processed via UTI portal.</li>
                                <li>Track your PAN status in the dashboard.</li>
                            </ol>
                            <div class="alert alert-info small mt-3">
                                <i class="bi bi-info-circle me-1"></i> Ensure Aadhaar and details match UIDAI records.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('footer.php'); ?>
</div>

<!-- Bootstrap JS -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dobInput = document.getElementById('dob');
        const ageMsg = document.getElementById('ageMessage');

        dobInput.addEventListener('change', function () {
            const dob = new Date(this.value);
            const today = new Date();

            if (isNaN(dob)) {
                ageMsg.textContent = '';
                return;
            }

            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }

            if (age >= 18) {
                ageMsg.textContent = 'Adult case';
                ageMsg.style.color = 'green';
                ageMsg.style.fontWeight = 'bold';
            } else {
                ageMsg.textContent = 'Minor case';
                ageMsg.style.color = 'red';
                ageMsg.style.fontWeight = 'bold';
            }
        });
    });
</script>
<script>
    document.getElementById("aadhaarNumber").addEventListener("input", function () {
        const aadhaar = this.value.trim();
        const statusMsg = document.getElementById("aadhaarStatus");

        statusMsg.textContent = '';
        statusMsg.style.color = '';

        if (aadhaar.length === 12 && /^\d{12}$/.test(aadhaar)) {
            statusMsg.textContent = '⏳ Checking Aadhaar status...';
            statusMsg.style.color = 'blue';

            fetch('aadhaar_pan_status _check.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `aadhaar=${encodeURIComponent(aadhaar)}`
            })
                .then(res => res.text())
                .then(text => {
                    const cleanText = text.replace(/<script[^>]*>.*?<\/script>/gi, '').trim();
                    console.log("Raw Response:", cleanText);

                    let data;
                    try {
                        data = JSON.parse(cleanText);
                    } catch (e) {
                        throw new Error("Invalid JSON format");
                    }

                    console.log("Parsed Data:", data);

                    if (data && data.status === "linked") {
                        statusMsg.textContent = '⚠️ Aadhaar is already linked with a PAN.';
                        statusMsg.style.color = 'red';
                    }
                    else if (data && data.message === "OK") {
                        statusMsg.textContent = '✅ Aadhaar is valid and ready for PAN linking.';
                        statusMsg.style.color = 'green';
                    }
                    else if (data && data.message && data.message.includes("valid Aadhaar")) {
                        statusMsg.textContent = '❌ Invalid Aadhaar number.';
                        statusMsg.style.color = 'red';
                    }
                    else if (data.error) {
                        statusMsg.textContent = '⚠️ ' + data.error;
                        statusMsg.style.color = 'orange';
                    }
                    else {
                        statusMsg.textContent = '⚠️ Unexpected response. Please try again.';
                        statusMsg.style.color = 'orange';
                    }
                })
                .catch((error) => {
                    console.error(error);
                    statusMsg.textContent = '⚠️ Unable to reach verification server. Please try again.';
                    statusMsg.style.color = 'orange';
                });
        }
    });
</script>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
<!-- <script src="../template/ahkweb/assets/js/jquery.min.js"></script> -->
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<!--app JS-->
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>

<script>
    $(document).ready(function () {

        $('#eid').inputmask();
        $('#date').inputmask();
        $('#timea').inputmask("hh:mm:ss", {
            placeholder: "00:00:00",
            insertMode: false,
            showMaskOnHover: false,
            hourFormat: 12
        });
    });
</script>

</html>