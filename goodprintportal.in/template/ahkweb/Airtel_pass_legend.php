<?php
include('header.php');

// ====== AUTO-FIX: ADD MISSING COLUMNS TO `airtelpass` (safe — INFORMATION_SCHEMA check) ======
if (!empty($ahk_conn)) {
    // 1) create_time (TIMESTAMP — List display uses date_create)
    $__ct = @mysqli_fetch_assoc(@mysqli_query($ahk_conn,
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='airtelpass' AND COLUMN_NAME='create_time' LIMIT 1"));
    if (!is_array($__ct) || empty($__ct['COLUMN_NAME'])) {
        @mysqli_query($ahk_conn,
            "ALTER TABLE `airtelpass`
             ADD COLUMN `create_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `date`");
    }
    // 2) pan varchar default empty (if missing) — sometimes schema old
    $__pn = @mysqli_fetch_assoc(@mysqli_query($ahk_conn,
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='airtelpass' AND COLUMN_NAME='pan' LIMIT 1"));
    if (!is_array($__pn) || empty($__pn['COLUMN_NAME'])) {
        @mysqli_query($ahk_conn,
            "ALTER TABLE `airtelpass` ADD COLUMN `pan` VARCHAR(20) NOT NULL DEFAULT '' AFTER `payment_status`");
    }
    // 3) account_type
    $__at = @mysqli_fetch_assoc(@mysqli_query($ahk_conn,
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='airtelpass' AND COLUMN_NAME='account_type' LIMIT 1"));
    if (!is_array($__at) || empty($__at['COLUMN_NAME'])) {
        @mysqli_query($ahk_conn,
            "ALTER TABLE `airtelpass` ADD COLUMN `account_type` VARCHAR(100) NOT NULL DEFAULT '' AFTER `pan`");
    }
    // 4) acc_opening_date
    $__aod = @mysqli_fetch_assoc(@mysqli_query($ahk_conn,
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='airtelpass' AND COLUMN_NAME='acc_opening_date' LIMIT 1"));
    if (!is_array($__aod) || empty($__aod['COLUMN_NAME'])) {
        @mysqli_query($ahk_conn,
            "ALTER TABLE `airtelpass` ADD COLUMN `acc_opening_date` VARCHAR(20) NOT NULL DEFAULT '' AFTER `account_type`");
    }
    unset($__ct,$__pn,$__at,$__aod);
}

$feeFallback = 10;
$price_row = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name IN ('Airtel_pass_fee','Airtel_pas','Airtel Passbook','airtel_pass') ORDER BY CASE service_name WHEN 'Airtel_pass_fee' THEN 1 WHEN 'Airtel_pas' THEN 2 ELSE 3 END LIMIT 1"));
$fee = (isset($price_row['price']) && is_numeric($price_row['price'])) ? (float)$price_row['price'] : $feeFallback;

if (isset($_POST['name'])) {
    $name        = trim($_POST['name']);
    $fathername_raw = trim($_POST['fathername']);
    $_dt = DateTime::createFromFormat('d/m/Y', $fathername_raw);
    $fathername = $_dt ? $_dt->format('d/m/Y') : $fathername_raw;
    $aadhar      = trim($_POST['aadhar']);
    $account     = trim($_POST['account']);
    $cif         = trim($_POST['cif']);
    $ifsc        = trim($_POST['ifsc']);
    $branch      = trim($_POST['branch']);
    $village     = trim($_POST['village']);
    $post        = trim($_POST['post']);
    $state       = trim($_POST['state']);
    $pincode     = trim($_POST['pincode']);
    $actype      = (isset($_POST['actype']) && is_string($_POST['actype'])) ? trim($_POST['actype']) : $branch;
    $userid      = $udata['phone'];
    $mobile      = $udata['phone'];
    $email       = $udata['email'];
    date_default_timezone_set("Asia/Kolkata");
    $timestamp = date('d/m/Y g:i:s');
    $appliedby = $udata['phone'];

    $ret_wp_no = isset($_POST['ret_wp_no']) ? mysqli_real_escape_string($ahk_conn, $_POST['ret_wp_no']) : '';
    $debit_fee = (float)$udata['balance'] - $fee;

    if ((float)$udata['balance'] >= $fee) {
        $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-".(float)$fee." WHERE phone='".mysqli_real_escape_string($ahk_conn,$userid)."'");
        $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('".mysqli_real_escape_string($ahk_conn,$userid)."','".(float)$fee."','".mysqli_real_escape_string($ahk_conn,(string)$debit_fee)."','Airtel Passbook Print','1','Debit')");

        if ($debit) {
            $name_s    = mysqli_real_escape_string($ahk_conn, $name);
            $fath_s    = mysqli_real_escape_string($ahk_conn, $fathername);
            $aadh_s    = mysqli_real_escape_string($ahk_conn, $aadhar);
            $acc_s     = mysqli_real_escape_string($ahk_conn, $account);
            $cif_s     = mysqli_real_escape_string($ahk_conn, $cif);
            $ifsc_s    = mysqli_real_escape_string($ahk_conn, $ifsc);
            $act_s     = mysqli_real_escape_string($ahk_conn, $actype);
            $br_s      = mysqli_real_escape_string($ahk_conn, $branch);
            $vil_s     = mysqli_real_escape_string($ahk_conn, $village);
            $post_s    = mysqli_real_escape_string($ahk_conn, $post);
            $blk_s     = ''; // block (NOT NULL old schema — empty default)
            $dist_s    = ''; // district (NOT NULL old schema — empty default)
            $occ_s     = ''; // occuption (NOT NULL old schema — empty default)
            $st_s      = mysqli_real_escape_string($ahk_conn, $state);
            $pin_s     = mysqli_real_escape_string($ahk_conn, $pincode);
            $userid_s  = mysqli_real_escape_string($ahk_conn, $userid);
            $mob_s     = mysqli_real_escape_string($ahk_conn, $mobile);
            $email_s   = mysqli_real_escape_string($ahk_conn, $email);
            $ts_s      = mysqli_real_escape_string($ahk_conn, $timestamp);

            $q = "INSERT INTO `airtelpass`(`name`, `fathername`,`aadhar`, `account`, `cif`, `ifsc`, `actype`, `occuption`, `branch`, `village`, `post`, `block`, `district`, `state`, `pincode`, `userid`,`mobile`,`email`, `date`, `create_time`, `status`, `payment_status`)
                  VALUES ('$name_s','$fath_s','$aadh_s','$acc_s','$cif_s','$ifsc_s','$act_s','$occ_s','$br_s','$vil_s','$post_s','$blk_s','$dist_s','$st_s','$pin_s','$userid_s','$mob_s','$email_s','$ts_s', NOW(), 'Generated', 1)";
            $res = mysqli_query($ahk_conn, $q);

            if ($res) {
                $msgno = 1;
                $msg   = "Application Submitted Successfully!";
                echo "<script>alert('Record updated successfully.')</script>";
            } else {
                $msgno = 0;
                $___dbg = mysqli_error($ahk_conn);
                echo "<script>alert('Record Rejected.'); console.log(".json_encode($___dbg).");</script>";
            }
        } else {
            echo "<script>alert('Balance Not Debited.')</script>";
        }
    } else {
        echo "<script>alert('Balance Low Please Add Balance.')</script>";
    }
}
?>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Airtel Passbook</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="Airtel_pass_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Print List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-xl-10 mx-auto">
                <h6 class="mb-0 text-uppercase">AIRTEL DIGITAL PASSBOOK PRINT</h6>
                <hr/>
                <div class="card border-top border-0 border-4 border-primary">
                    <div class="card-body p-5">
                        <div class="card-title d-flex align-items-center">
                            <div><i class="bx bxs-id-card me-1 font-22 text-primary"></i></div>
                        </div>

                        <section id="main-content">
                            <?php if (isset($msgno) && $msgno == 1) { ?>
                                <div style="width=100%" class="row cvmsgok"><?php echo $msg; ?></div>
                                <script>
                                    setTimeout(() => {
                                        window.location.href = "Airtel_pass_list.php";
                                    }, 200);
                                </script>
                            <?php } ?>

                            <div class="row dgnform">
                                <div class="col-sm-12">
                                    <form action="" method="post">
                                        <div class="row">
                                            <div class="col-sm-3">
                                                <label>Full Name</label>
                                                <div class="form-group">
                                                    <input class="form-control" id="name" name="name" placeholder="Full Name" type="text" maxlength="40" required>
                                                    <input type="hidden" name="photo" id="photo" required>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Passbook Issue Date</label>
                                                <div class="form-group">
                                                    <input class="form-control" 
                                                           id="fathername" 
                                                           name="fathername" 
                                                           type="text" 
                                                           placeholder="DD/MM/YYYY"
                                                           value="<?php echo isset($_POST['fathername']) ? htmlspecialchars($_POST['fathername']) : ''; ?>"
                                                           pattern="\d{2}/\d{2}/\d{4}"
                                                           required>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Aadhar Number</label>
                                                <div class="form-group">
                                                    <input class="form-control" id="aadhar" name="aadhar" placeholder="Aadhar Number" type="number" required>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Account No</label>
                                                <div class="form-group">
                                                    <input class="form-control" name="account" id="account" placeholder="20120677526" type="number" required>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Pan Number</label>
                                                <div class="form-group">
                                                    <input class="form-control" name="ifsc" id="ifsc" placeholder="ABCDE1234F" type="text" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" style="text-transform: uppercase;" required>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Customer ID</label>
                                                <div class="form-group">
                                                    <input class="form-control" name="cif" id="cif" placeholder="CIF NUMBER" type="number" required>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Account Type</label>
                                                <div class="form-group">
                                                    <select class="form-control" name="branch" id="branch" required>
                                                        <option value="" disabled selected>Select Type</option>
                                                        <option value="Bharosa Savings Account">Bharosa Savings Account</option>
                                                        <option value="Suraksha Savings Account">Suraksha Savings Account</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Village</label>
                                                <div class="form-group">
                                                    <input class="form-control" name="village" id="village" type="text" required>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Full Address</label>
                                                <div class="form-group">
                                                    <input class="form-control" name="post" id="post" type="text" required>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>State</label>
                                                <div class="form-group">
                                                    <select name="state" id="state" class="form-control" required>
                                                      <option value="">Select State</option>
                                                        <option value="Andhra Pradesh">Andhra Pradesh</option>
                                                        <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                                        <option value="Assam">Assam</option>
                                                        <option value="Bihar">Bihar</option>
                                                        <option value="Chandigarh">Chandigarh</option>
                                                        <option value="Chhattisgarh">Chhattisgarh</option>
                                                        <option value="Delhi">Delhi</option>
                                                        <option value="Gujarat">Gujarat</option>
                                                        <option value="Haryana">Haryana</option>
                                                        <option value="Himachal Pradesh">Himachal Pradesh</option>
                                                        <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                                                        <option value="Jharkhand">Jharkhand</option>
                                                        <option value="Karnataka">Karnataka</option>
                                                        <option value="Kerala">Kerala</option>
                                                        <option value="Madhya Pradesh">Madhya Pradesh</option>
                                                        <option value="Maharashtra">Maharashtra</option>
                                                        <option value="Manipur">Manipur</option>
                                                        <option value="Meghalaya">Meghalaya</option>
                                                        <option value="Orissa">Orissa</option>
                                                        <option value="Puducherry">Puducherry</option>
                                                        <option value="Punjab">Punjab</option>
                                                        <option value="Rajasthan">Rajasthan</option>
                                                        <option value="Tamil Nadu">Tamil Nadu</option>
                                                        <option value="Telangana">Telangana</option>
                                                        <option value="Tripura">Tripura</option>
                                                        <option value="Uttar Pradesh">Uttar Pradesh</option>
                                                        <option value="Uttarakhand">Uttarakhand</option>
                                                        <option value="West Bengal">West Bengal</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-sm-3">
                                                <label>Pincode</label>
                                                <div class="form-group">
                                                    <input class="form-control" name="pincode" id="pincode" type="number" maxlength="8" required>
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <label>&nbsp;</label>
                                                <div class="form-group">
                                                    <button class="form-control btn btn-success">Submit</button>
                                                </div>
                                            </div>

                                            <div class="col-12 ml-2">
                                                <h5 class="text-warning">Application Fee: ₹<?php
                                                    $_price_row = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name IN ('Airtel_pass_fee','Airtel_pas','Airtel Passbook','airtel_pass') ORDER BY CASE service_name WHEN 'Airtel_pass_fee' THEN 1 WHEN 'Airtel_pas' THEN 2 ELSE 3 END LIMIT 1"));
                                                    $_fee = (isset($_price_row['price']) && is_numeric($_price_row['price'])) ? (float)$_price_row['price'] : $feeFallback;
                                                    echo htmlspecialchars(number_format($_fee, 2));
                                                ?></h5>
                                                <input type="hidden" name="fee" value="<?php echo htmlspecialchars($_fee); ?>">
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </section>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<!-- Required Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<!-- Bootstrap Datepicker -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>

<script>
    $(document).ready(function () {
        $('#fathername').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true
        });
    });
</script>
