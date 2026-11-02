<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata'); // Ensure IST timezone

$appliedby = $udata['phone'];

/***************************************
    STEP 1: STATUS CHECK
****************************************/
if (isset($_GET['check_status'])) {

    $txn_id = mysqli_real_escape_string($ahk_conn, $_GET['check_status']);

    // Fetch transaction
    $q = mysqli_query($ahk_conn, "SELECT * FROM recharge_history WHERE rbid='$txn_id' LIMIT 1");
    $data = mysqli_fetch_assoc($q);

    if ($data) {

        $userid     = mysqli_real_escape_string($ahk_conn, $data['userid']);
        $amount     = floatval($data['amount']);
        $commission = floatval($data['commission']);

        /***************************************
            API CALL
        ****************************************/
        $url = "https://{$flexsecureapiurl}/api/v1/mobileRecharge/statusCheck?apiKey={$flexsecure_key}&orderid={$txn_id}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($response, true);

        if ($res && isset($res['statusMessage'])) {

            $statusMessage = strtolower(trim($res['statusMessage']));
            $message       = mysqli_real_escape_string($ahk_conn, $res['message'] ?? '');
            $opid          = isset($res['opid']) ? mysqli_real_escape_string($ahk_conn, $res['opid']) : '';

            /***************************************
                1. PENDING
            ****************************************/
            if ($statusMessage == "pending") {
                mysqli_query($ahk_conn,
                    "UPDATE recharge_history 
                     SET status='Pending', msg='$message' 
                     WHERE rbid='$txn_id'"
                );
                echo "<script>alert('Transaction is Still Pending');</script>";
            }

            /***************************************
                2. SUCCESS
            ****************************************/
            elseif ($statusMessage == "success") {
                mysqli_query($ahk_conn,
                    "UPDATE recharge_history 
                     SET status='Success', msg='$message', opid='$opid'
                     WHERE rbid='$txn_id'"
                );
                echo "<script>alert('Recharge Successful');</script>";
            }

/***************************************
    3. REFUND
****************************************/
elseif ($statusMessage == "refund") {

    // Prevent double refund
    if (strtolower($data['status']) == "refunded") {
        echo "<script>alert('Already Refunded. No duplicate refund allowed.');</script>";
        echo "<script>window.location.href='mobileRechargeHistory';</script>";
        exit;
    }

    // Use full debit amount for refund
    $refundAmount = floatval($data['debitamount']);
    if ($refundAmount < 0) $refundAmount = 0;

    // Begin transaction
    mysqli_begin_transaction($ahk_conn);
    try {
        // Update recharge history
        mysqli_query($ahk_conn,
            "UPDATE recharge_history 
             SET status='Refunded', msg='$message'
             WHERE rbid='$txn_id'"
        );

        // Update user wallet
        mysqli_query($ahk_conn,
            "UPDATE users SET balance = balance + $refundAmount 
             WHERE phone='$userid'"
        );

        // Get updated balance
        $ux = mysqli_query($ahk_conn,
            "SELECT balance FROM users WHERE phone='$userid' LIMIT 1"
        );
        $ud = mysqli_fetch_assoc($ux);
        $newBalance = floatval($ud['balance']);

        // Insert wallet history with IST timestamp
        $timestamp = date('Y-m-d H:i:s');
        $purpose = "Recharge Refund: $data[mobile] Amount=$amount, Commission=$commission, DebitAmount=$refundAmount";
        mysqli_query($ahk_conn,
            "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type, date)
             VALUES('$userid', '$refundAmount', '$newBalance', '$purpose', '1', 'Credit', '$timestamp')"
        );

        mysqli_commit($ahk_conn);
        echo "<script>alert('Amount Refunded Successfully: ₹$refundAmount');</script>";

    } catch (Exception $e) {
        mysqli_rollback($ahk_conn);
        echo "<script>alert('Refund Failed: ".$e->getMessage()."');</script>";
    }
}

            /***************************************
                UNKNOWN RESPONSE
            ****************************************/
            else {
                echo "<script>alert('Unknown API Response');</script>";
            }
        }
    }

    echo "<script>window.location.href='mobileRechargeHistory';</script>";
    exit;
}

/***************************************
    STEP 2: SHOW TABLE
****************************************/
if ($udata['type'] == 'admin') {
    $res = mysqli_query($ahk_conn, "SELECT * FROM recharge_history ORDER BY id DESC");
} else {
    $res = mysqli_query($ahk_conn, "SELECT * FROM recharge_history WHERE userid='$appliedby' ORDER BY id DESC");
}
?>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Mobile Recharge</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="mobileRecharge" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Recharge
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

<div class="card radius-10">
<div class="card-body">
    <h5 class="mb-0">Recharge History</h5>
    <hr>

    <div class="table-responsive">
        <table id="example2" class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Mobile/ID</th>
                    <th>Operator</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Commission</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
                <?php
                $x=0;
                while ($data = mysqli_fetch_assoc($res)) {
                    $x++;
                    $statusLower = strtolower(trim($data['status']));

                    if (in_array($statusLower, ["pending","inprogress","progress","100"])) {
                        $statusBtn = '<a href="mobileRechargeHistory.php?check_status='.$data['rbid'].'" class="btn btn-sm btn-warning">Check Status</a>';
                    }
                    elseif ($statusLower == "success") {
                        $statusBtn = '<span class="badge bg-success">Success</span>';
                    }
                    elseif ($statusLower == "failed") {
                        $statusBtn = '<span class="badge bg-danger">Failed</span>';
                    }
                    elseif ($statusLower == "refunded") {
                        $statusBtn = '<span class="badge bg-info">Refunded</span>';
                    }
                    else {
                        $statusBtn = '<span class="badge bg-secondary">'.$data['status'].'</span>';
                    }

                    echo "
                    <tr>
                        <td>$x</td>
                        <td>{$data['mobile']}</td>
                        <td>{$data['operator']}</td>
                        <td>₹{$data['amount']}</td>
                        <td>$statusBtn</td>
                        <td>₹{$data['commission']}</td>
                        <td>{$data['msg']}</td>
                        <td>{$data['date']}</td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

</div>
</div>

</div>
</div>

<?php include('../template/ahkweb/footer.php'); ?>



<!-- Bootstrap & Plugins JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<!-- Datatables -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.buttons.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/buttons.bootstrap5.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jszip.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/pdfmake.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/vfs_fonts.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/buttons.html5.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/buttons.print.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#example2').DataTable({
        lengthChange: false,
        buttons: [ 'copy', 'excel', 'pdf', 'print', 'colvis' ]
    });

    table.buttons().container()
        .appendTo('#example2_wrapper .col-md-6:eq(0)');
});
</script>