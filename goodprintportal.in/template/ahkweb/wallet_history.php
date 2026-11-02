<?php
session_start();
include('header.php'); // must define $ahk_conn and $udata
$today = date('Y-m-d');
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

if (!empty($from_date) && !empty($to_date)) {
    $query = "SELECT * FROM wallethistory WHERE DATE(date) BETWEEN '" . mysqli_real_escape_string($ahk_conn, $from_date) . "' 
              AND '" . mysqli_real_escape_string($ahk_conn, $to_date) . "'";
} else {
    $query = "SELECT * FROM wallethistory WHERE DATE(date) = '$today'";
}

if ($udata['type'] != 'admin') {
    $query .= " AND userid='" . mysqli_real_escape_string($ahk_conn, $udata['phone']) . "'";
}

$query .= " ORDER BY id DESC";
$res = mysqli_query($ahk_conn, $query);

// Check if we have data
$has_data = mysqli_num_rows($res) > 0;
$rows = [];

if ($has_data) {
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    $rows = array_reverse($rows);
}
// ✅ Admin check
$isAdmin = (isset($udata['type']) && strtolower($udata['type']) === 'admin');
?>

<!-- Add DataTables and Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<!--start page wrapper -->
<div class="page-wrapper">
  <div class="page-content">
    <div class="card radius-15 shadow-lg border-0 luxury-card">
      <div class="card-body">
        <!-- Back & Title -->
        <div class="d-flex align-items-center justify-content-between mb-4">
          <a href="javascript:void(0);" onclick="window.history.back();" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-semibold luxury-btn">
            <i class="fas fa-arrow-left me-2"></i> Go Back
          </a>
          <div class="text-center">
            <h4 class="mb-0 fw-bold text-gradient">💰 Wallet History</h4>
            <small class="text-muted">Track your financial transactions</small>
          </div>
          <div class="wallet-summary">
            <span class="badge bg-primary rounded-pill px-3 py-2">
              <i class="fas fa-wallet me-1"></i> Transactions
            </span>
          </div>
        </div>
        
        <div class="luxury-divider"></div>

        <!-- 🔍 Date Filter Form -->
        <div class="filter-card mb-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <h6 class="card-title mb-3"><i class="fas fa-filter me-2 text-primary"></i>Filter Transactions</h6>
              <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                  <label class="form-label fw-semibold text-dark">From Date</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-calendar text-primary"></i></span>
                    <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="form-control rounded-end luxury-input">
                  </div>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold text-dark">To Date</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-calendar text-primary"></i></span>
                    <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="form-control rounded-end luxury-input">
                  </div>
                </div>
                <div class="col-md-6 text-start text-md-end">
                  <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold luxury-btn">
                    <i class="fas fa-search me-1"></i> Apply Filter
                  </button>
                  <a href="?" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold ms-2 luxury-btn">
                    <i class="fas fa-refresh me-1"></i> Reset
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- 🧾 Table -->
        <div class="table-card">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <div class="table-responsive luxury-table-container">
                <table id="walletTable" class="table table-hover align-middle" style="width:100%">
                  <thead class="luxury-thead">
                    <tr>
                      <th class="text-center">#</th>
                      <th>Amount</th>
                      <th>Balance</th>
                      <th>Purpose</th>
                      <th>Type</th>
                      <th>User ID</th>
                      <th>Date & Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($has_data): ?>
                      <?php
                      $x = 0;
                      foreach ($rows as $data) {
                        $x++;
                        $isDebit = strtoupper(trim($data['type'])) == 'DEBIT';
                        $amountColor = $isDebit ? 'text-danger' : 'text-success';
                        $amountSign = $isDebit ? '-' : '+';
                        $typeIcon = $isDebit ? 'fa-arrow-down' : 'fa-arrow-up';
                      ?>
                        <tr class="luxury-table-row">
                          <td class="text-center fw-bold"><?= $x; ?></td>
                          <td>
                            <div class="d-flex align-items-center">
                              <span class="amount-icon me-2 <?= $amountColor; ?>">
                                <i class="fas <?= $typeIcon; ?>"></i>
                              </span>
                              <span class="<?= $amountColor; ?> fw-bold fs-6">
                                <?= $amountSign . $data['amount']; ?>
                              </span>
                            </div>
                          </td>
                        <td>
  <span style="color:#198754 !important; font-weight:600;">
    <?= $data['balance']; ?>
  </span>
</td>

                          <td>
                            <span class="purpose-text" data-bs-toggle="tooltip" title="<?= htmlspecialchars($data['purpose']); ?>">
                              <?= htmlspecialchars($data['purpose']); ?>
                            </span>
                          </td>
                         <td>
  <span class="fw-bold <?= $isDebit ? 'text-danger' : 'text-success'; ?>">
    <i class="fas <?= $isDebit ? 'fa-arrow-trend-down text-danger' : 'fa-arrow-trend-up text-success'; ?> me-1"></i>
    <?= strtoupper($data['type']); ?>
  </span>
</td>

                          <td>
                            <span class="user-id"><?= strtoupper($data['userid']); ?></span>
                          </td>
                          <td>
                            <div class="date-time">
                              <i class="fas fa-clock me-1 text-muted"></i>
                              <small><?= date('M j, Y', strtotime($data['date'])); ?></small>
                              <br>
                              <small class="text-muted"><?= date('h:i A', strtotime($data['date'])); ?></small>
                            </div>
                          </td>
                        </tr>
                      <?php } ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="7" class="text-center py-5">
                          <div class="empty-state">
                            <i class="fas fa-wallet fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Transactions Found</h5>
                            <p class="text-muted mb-0">No wallet history found for selected date(s)</p>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!--end page wrapper -->
<?php 
include('footer.php');
?>
<!-- Bootstrap JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery.easy-pie-chart/jquery.easypiechart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/sparkline-charts/jquery.sparkline.min.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/excanvas.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
<script>
$(function() {
    $(".knob").knob();
});
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<!--app JS-->
<script src="../template/ahkweb/assets/js/app.js"></script>
<!-- datatable -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
		$(document).ready(function() {
			$('#example').DataTable();
            $('#aadh').inputmask();
		  } );
	</script>
	
	<script>
		$(document).ready(function() {
			var table = $('#example2').DataTable( {
				lengthChange: false,
				buttons: [ 'copy', 'excel', 'pdf', 'print']
			} );
		 
			table.buttons().container()
				.appendTo( '#example2_wrapper .col-md-6:eq(0)' );
		} );


	</script>
	
</body>



</html>