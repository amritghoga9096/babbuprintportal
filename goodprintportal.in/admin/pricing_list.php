<?php
// pricing_list_readonly.php
// Read-only pricing list (loader removed) and header image display
// Keep your includes paths intact.

include('../includes/session.php');
include('../includes/config.php'); // must create $conn or $ahk_conn (mysqli)
include('../template/ahkweb/header.php');

// Determine DB connection variable
if (isset($ahk_conn) && $ahk_conn instanceof mysqli) {
    $db = $ahk_conn;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die('<div style="padding:20px;font-family:Arial,sans-serif;color:#a00;">Database connection not found. Check config.php to ensure $conn or $ahk_conn is created.</div>');
}

// Fetch all pricing rows
$pricing = [];
$sql = "SELECT service_name, price FROM pricing ORDER BY service_name ASC";
if ($res = $db->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $pricing[$row['service_name']] = $row['price'];
    }
    $res->free();
} else {
    $pricing_error = "Failed to fetch pricing: " . $db->error;
}

// Optionally split services into two columns (e.g., by category or just alternate)
$left = [];
$right = [];
$idx = 0;
foreach ($pricing as $k => $v) {
    if ($idx % 2 == 0) $left[$k] = $v; else $right[$k] = $v;
    $idx++;
}
?>

<style>
/* Minimal styles to mimic the provided screenshot card/table layout */
.container-fluid.custom-page { padding: 20px 28px; background:#f3f4f6; min-height:100vh; }
.header-image { width:100%; max-height:160px; object-fit:cover; border-radius:10px; box-shadow: 0 6px 18px rgba(20,20,40,0.06); margin-bottom:18px; }
.card.pricing-card{ border-radius:12px; box-shadow: 0 6px 18px rgba(20,20,40,0.06); border: 1px solid rgba(99,102,241,0.08); overflow:hidden; }
.card .card-header{ padding:16px 20px; color:#fff; font-weight:700; }
.card .card-body{ padding:0; }
.table { margin-bottom:0; }
.table thead th{ border-bottom:2px solid rgba(0,0,0,0.08); background:#fff; }
.status-pill{ display:inline-block; padding:6px 12px; border-radius:20px; background:rgba(34,197,94,0.12); color:#166534; font-weight:600; }
.section-title{ font-size:18px; margin-bottom:10px; color:#0f172a; }
@media (max-width:991px){ .col-lg-6{ width:100%; display:block; }
  .header-image{ max-height:120px; }
}
</style>
<div class="page-wrapper">
	    
	    		
		<div class="page-content">
				<!--breadcrumb-->
				<div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
					<div class="breadcrumb-title pe-3">Services</div>
					<div class="ps-3">
						<nav aria-label="breadcrumb">
							<ol class="breadcrumb mb-0 p-0">
								<li class="breadcrumb-item"><a href="https://cardsadda.in"></a>
								</li>
								<li class="active" aria-current="page">Price List</li>
							</ol>
						</nav>
					</div>
				</div>
				<!--end breadcrumb-->
<div class="container-fluid custom-page">
    <!-- Display the provided image as a hero/banner. The image path was supplied in the container by the developer. -->
    

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card pricing-card">
                <div class="card-header" style="background:#0ea5a1;">Cards Service:</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width:6%;">Sl. No.</th>
                                    <th>Service Name</th>
                                    <th style="width:12%;">Price</th>
                                    <th style="width:12%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                if (empty($left)) {
                                    echo '<tr><td colspan="4">No services found</td></tr>';
                                } else {
                                    foreach ($left as $service => $price) {
                                        $label = ucwords(str_replace(['_','-'], [' ',' '], $service));
                                        $display_price = ($price === '' || $price === null) ? '—' : htmlspecialchars($price, ENT_QUOTES, 'UTF-8');
                                        echo '<tr>';
                                        echo '<td>' . $i . '</td>';
                                        echo '<td>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
                                        echo '<td> &nbsp;₹ ' . $display_price . '</td>';
                                        echo '<td><span class="status-pill">Live</span></td>';
                                        echo '</tr>';
                                        $i++;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card pricing-card">
                <div class="card-header" style="background:#0284c7;">Find Service:</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width:6%;">Sl. No.</th>
                                    <th>Service Name</th>
                                    <th style="width:12%;">Price</th>
                                    <th style="width:12%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $j = 1;
                                if (empty($right)) {
                                    echo '<tr><td colspan="4">No services found</td></tr>';
                                } else {
                                    foreach ($right as $service => $price) {
                                        $label = ucwords(str_replace(['_','-'], [' ',' '], $service));
                                        $display_price = ($price === '' || $price === null) ? '—' : htmlspecialchars($price, ENT_QUOTES, 'UTF-8');
                                        echo '<tr>';
                                        echo '<td>' . $j . '</td>';
                                        echo '<td>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
                                        echo '<td> &nbsp;₹ ' . $display_price . '</td>';
                                        echo '<td><span class="status-pill">Live</span></td>';
                                        echo '</tr>';
                                        $j++;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>


<?php include('footer.php'); ?>

<!-- Keep existing app scripts for styling/behaviour if present -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

</body>
</html>