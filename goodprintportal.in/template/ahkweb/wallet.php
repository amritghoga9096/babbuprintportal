<?php
session_start();
include('../template/ahkweb/header.php'); // $ahk_conn defined here

// === 1. DATABASE SE USER DATA FETCH KARNA ===
 $phone = $_SESSION['phone'] ?? '';
 $udata = []; // Initialize

if(!empty($phone)){
    $stmt = mysqli_prepare($ahk_conn, "SELECT * FROM users WHERE phone = ?");
    mysqli_stmt_bind_param($stmt, "s", $phone);
    mysqli_stmt_execute($stmt);
    $udata = db_stmt_fetch_assoc($stmt);
    mysqli_stmt_close($stmt);
}
?>

<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        
<!-- Breadcrumb -->
<div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
    <div class="d-flex align-items-center">
        <div class="breadcrumb-title pe-3">Wallet</div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 ps-2">
                <li class="breadcrumb-item active text-muted" aria-current="page">Add Money</li>
            </ol>
        </nav>
    </div>
    <div class="btn-group">
        <a href="wallet_history" class="btn btn-success btn-sm">
            <i class="bi bi-list-ul me-1"></i> History
        </a>
    </div>
</div>

<!-- MODERNIZED USER PROFILE SECTION -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 modern-card">
            <div class="card-header bg-transparent border-0 pb-0">
                <h5 class="text-primary fw-bold mb-0"><i class="bi bi-person-circle me-2"></i>User Details</h5>
            </div>
            <div class="card-body pt-2">
                <div class="row g-3">
                    <!-- Email -->
                    <div class="col-md-4">
                        <div class="info-box p-3 bg-light rounded border no-select">
                            <small class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 11px;">Email Address</small>
                            <span class="fs-6 text-dark fw-bold">
                                <i class="bi bi-envelope-fill text-secondary me-2"></i><?php echo htmlspecialchars($udata['email'] ?? 'N/A'); ?>
                            </span>
                        </div>
                    </div>
                    <!-- Mobile -->
                    <div class="col-md-4">
                        <div class="info-box p-3 bg-light rounded border no-select">
                            <small class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 11px;">Mobile Number</small>
                            <span class="fs-6 text-dark fw-bold">
                                <i class="bi bi-phone-fill text-success me-2"></i><?php echo htmlspecialchars($udata['phone'] ?? 'N/A'); ?>
                            </span>
                        </div>
                    </div>
                    <!-- Name -->
                    <div class="col-md-4">
                        <div class="info-box p-3 bg-light rounded border no-select">
                            <small class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 11px;">Name</small>
                            <span class="fs-6 text-dark fw-bold">
                                <i class="bi bi-person-fill text-primary me-2"></i><?php echo htmlspecialchars($udata['name'] ?? 'N/A'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WALLET ADD FORM SECTION -->
<div class="row mt-4">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm border-0 modern-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2"></i>Add Money to Wallet</h5>
            </div>
            
            <!-- Error Message Box -->
            <div id="error-msg" style="display: none; background: linear-gradient(45deg, #ff416c, #ff4b2b); color: #fff; font-size: 16px; font-weight: bold; padding: 12px; border-radius: 8px; text-align: center; margin: 20px;">
                ⚠️ कृपया वैध राशि दर्ज करें (न्यूनतम ₹100)!
            </div>
            
            <!-- PHP Error Message -->
            <?php if(!empty($msg)) echo '<div class="alert alert-danger m-3">'.$msg.'</div>'; ?>

            <div class="card-body">
                <form action="" method="POST" onsubmit="return validateAmount();">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Payment Gateway</label>
                        <select class="form-select" disabled>
                            <option>UPI, BHIM/Paytm/PhonePe/GooglePay</option>
                        </select>
                        <input type="hidden" name="gateway" value="UPI">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Enter Amount (₹)</label>
                        <input type="number" min="100" class="form-control form-control-lg fw-bold text-center" placeholder="₹ 0.00" name="amount" id="TXN_AMOUNT" oninput="checkAmount();" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg btn-gradient shadow">
                            <i class="bi bi-lightning-charge-fill me-2"></i>Instant Pay
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Payment List Table -->
<h6 class="mb-0 text-uppercase mt-5 fw-bold">Recent Transactions</h6>
<hr/>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table id="example2" class="table table-hover table-striped table-bordered text-center align-middle">
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
                    // Data fetch using $ahk_conn
                    $phone_safe = $udata['phone'] ?? '';
                    if($phone_safe){
                        $stmt_list = mysqli_prepare($ahk_conn, "SELECT * FROM wallet WHERE phone=? ORDER BY id DESC LIMIT 20");
                        mysqli_stmt_bind_param($stmt_list, "s", $phone_safe);
                        mysqli_stmt_execute($stmt_list);
                        $wallet_rows = db_stmt_fetch_all_assoc($stmt_list);
                        $x = 1;
                        foreach($wallet_rows as $data){
                            // Status Badge Logic
                            $status_class = 'bg-warning text-dark';
                            $status_text = strtoupper($data['status']);
                            if($status_text == 'SUCCESS') $status_class = 'bg-success';
                            if($status_text == 'REJECTED') $status_class = 'bg-danger';
                    ?>
                    <tr>
                        <td><?php echo $x++; ?></td>
                        <td><?php echo htmlspecialchars($data['txn_id']); ?></td>
                        <td class="fw-bold text-success">₹<?php echo htmlspecialchars($data['amount']); ?></td>
                        <td><?php echo htmlspecialchars($data['txn_date']); ?></td>
                        <td>
                            <span class="badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                    </tr>
                    <?php } 
                        mysqli_stmt_close($stmt_list);
                    } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- STYLES: Modern Look & No Select -->
<style>
    /* Mouse Selection Disable (User Request) */
    .no-select, .info-box, input[readonly] {
        -webkit-user-select: none; /* Safari */
        -moz-user-select: none; /* Firefox */
        -ms-user-select: none; /* IE10+/Edge */
        user-select: none; /* Standard */
    }

    /* Modern Card Style */
    .modern-card {
        border-radius: 12px;
        transition: transform 0.2s;
    }
    .modern-card:hover {
        transform: translateY(-2px);
    }

    /* Info Box Hover Effect */
    .info-box {
        transition: all 0.3s ease;
    }
    .info-box:hover {
        background-color: #e9ecef !important;
        border-color: #0d6efd !important;
    }

    /* Button Gradient */
    .btn-gradient {
        background: linear-gradient(45deg, #0d6efd, #0b5ed7);
        border: none;
    }

    /* Status Badge Padding */
    .badge {
        padding: 8px 12px;
        font-size: 12px;
        letter-spacing: 0.5px;
    }
</style>

<script>
function validateAmount() {
    var amount = parseFloat(document.getElementById("TXN_AMOUNT").value);
    var errorMsg = document.getElementById("error-msg");

    if (isNaN(amount) || amount < 100) {
        errorMsg.style.display = "block"; 
        return false; 
    } else {
        errorMsg.style.display = "none"; 
        return true; 
    }
}

function checkAmount() {
    var amount = parseFloat(document.getElementById("TXN_AMOUNT").value);
    var errorMsg = document.getElementById("error-msg");
    if (isNaN(amount) || amount < 100) {
        errorMsg.style.display = "block";
    } else {
        errorMsg.style.display = "none";
    }
}
</script>

<!--end page wrapper -->
<?php include('footer.php');?>

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
