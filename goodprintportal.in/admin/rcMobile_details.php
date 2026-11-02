<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fetch fee from database
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='rc_vehical_fee' "));
$fee = $price['price'] ?? 0;

// DEFAULT
$resdata = [];
$error_message = "";

// HELPER To Hide Null Rows
function renderRow($label, $value) {
    if (!isset($value) || trim($value) === "" || $value === null) return "";
    return "
        <tr>
            <th>{$label}</th>
            <td>" . htmlspecialchars($value) . "</td>
        </tr>
    ";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rcFindNumber'])) {

    $rcFindNumber = trim($_POST['rcFindNumber']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {

        $api_key = urlencode($flexsecure_key);
        $url = "https://{$flexsecureapiurl}/api/v4/rcFindMobile.php?apiKey={$api_key}&rcFindNumber={$rcFindNumber}";

        // CURL CALL
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $resdata = json_decode($response, true);

        if (!empty($resdata['status']) && $resdata['status'] == "100") {

            // deduct fee
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance-$fee WHERE phone='$appliedby'");

            // wallet history
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type)
                                     VALUES('$appliedby','$fee','$debit_fee','Rc Find By Mobile $rcFindNumber','1','Debit')");

        } else {
            $error_message = $resdata['statusMessage'] ?? "Something went wrong.";
        }

    } else {
        echo "<script>
                Swal.fire('Insufficient Balance', 'Recharge your wallet!', 'error');
                setTimeout(()=>{ location='wallet.php'; },1000);
              </script>";
    }
}
?>

<style>
.table th {
    width: 30%;
    background: #eef3ff !important;
    color: #003399;
    font-weight: 600;
}
.table td {
    background: #ffffff !important;
    font-weight: 500;
}
.print-card {
    background: #fffdf5;
    border-radius: 12px;
}
.page-breadcrumb {
    border-left: 5px solid #007bff;
}
</style>

<div class="page-wrapper">
    <div class="page-content">

        <!-- TITLE BAR -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div>
                <h4 class="mb-0 text-primary fw-bold">Vehicle Details (RC Find)</h4>
            </div>
            <a href="javascript:history.back()" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <!-- FORM + RESULT -->
        <div class="row">

            <!-- SEARCH FORM -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h4 class="text-center mb-3 text-dark fw-bold">Search Vehicle (RC Number)</h4>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <label class="mb-1">Enter RC Registered Mobile Number</label>
                            <input name="rcFindNumber"
                                   type="text"
                                   class="form-control mb-3"
                                   placeholder="Enter Mobile Number (ex: 9123456789)"
                                   required>

                            <div class="d-flex justify-content-between mt-3">
                                <input class="form-control w-50 me-2 text-danger fw-bold"
                                       value="Fee ₹ <?= htmlspecialchars($fee) ?>"
                                       readonly>

                                <button class="btn btn-success w-50">
                                    <i class="fa fa-check-circle me-1"></i> Submit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RESULT CARD -->
            <?php if (!empty($resdata) && $resdata['status'] == "100"): ?>
                <div class="col-lg-8">
                    <div class="card shadow-lg border-0 rounded print-card" id="printSection">
                        <div class="card-body">

                            <h4 class="mb-4 text-dark fw-bold">
                                <i class="fa fa-car text-primary"></i> Vehicle Details Found
                            </h4>

                            <table class="table table-bordered table-hover table-striped">
                                <tbody>

                                    <?= renderRow("Status", $resdata['statusMessage']); ?>
                                    <?= renderRow("Mobile Number", $resdata['mobile_number']); ?>
                                    <?= renderRow("RC Number", $resdata['rc_number']); ?>
                                    <?= renderRow("Owner", $resdata['owner']); ?>

                                    <?= renderRow("Vehicle", @$resdata['manufacturer']." ".@$resdata['vehicle']); ?>
                                    <?= renderRow("Variant", $resdata['variant']); ?>
                                    <?= renderRow("Fuel Type", $resdata['fuelType']); ?>
                                    <?= renderRow("Engine No.", $resdata['engine']); ?>
                                    <?= renderRow("Chassis No.", $resdata['chassis']); ?>

                                    <?= renderRow("Registration Date", $resdata['regDate']); ?>
                                    <?= renderRow("RTO Code", $resdata['rtoCode']); ?>
                                    <?= renderRow("RTO Name", @$resdata['rtoData']['rtoName']); ?>

                                    <?= renderRow("PUCC Valid Upto", $resdata['puccValidUpto']); ?>
                                    <?= renderRow("Insurance Company", $resdata['insuranceCompanyName']); ?>
                                    <?= renderRow("Insurance Policy", $resdata['insurancePolicyNumber']); ?>
                                    <?= renderRow("Insurance Upto", $resdata['insuranceUpto']); ?>

                                    <?= renderRow("Address", $resdata['presentAddress']); ?>

                                </tbody>
                            </table>

                            <div class="text-end mt-3">
                                <button class="btn btn-primary px-4" onclick="printVerification()">
                                    <i class="fa fa-print me-1"></i> Print
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function printVerification() {
    const printContents = document.getElementById("printSection").innerHTML;
    const win = window.open('', '', 'width=900,height=700');
    win.document.write(`
        <html>
        <head>
            <title>Vehicle Details</title>
            <style>
                body { font-family: Arial; margin: 20px; }
                table { width:100%; border-collapse: collapse; }
                th, td { padding: 8px; border: 1px solid #333; }
                th { background: #f2f2f2; }
            </style>
        </head>
        <body>${printContents}</body>
        <script>window.print();<\/script>
        </html>
    `);
    win.document.close();
}
</script>


<?php include('footer.php'); ?>

<!-- Required JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>