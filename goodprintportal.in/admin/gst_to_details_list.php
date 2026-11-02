<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `gst_to_details_requests` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `application_no` VARCHAR(80) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `gstin` VARCHAR(20) DEFAULT NULL,
    `legal_name` VARCHAR(255) DEFAULT NULL,
    `status_text` VARCHAR(50) DEFAULT NULL,
    `result_json` LONGTEXT DEFAULT NULL,
    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `old_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `new_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `message` TEXT DEFAULT NULL,
    `date` VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `application_no` (`application_no`),
    KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
?>
<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">GST to Details Request List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="gst_to_details" class="btn btn-success">
                    <i class="bi bi-search me-1"></i> GST to Details
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">GST to Details Records</h5>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL.</th>
                                <th>Application No</th>
                                <th>Date</th>
                                <th>GSTIN</th>
                                <th>Legal Name</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $safeUser = mysqli_real_escape_string($ahk_conn, (string) ($udata['phone'] ?? ''));
                            $res = mysqli_query($ahk_conn, "SELECT * FROM gst_to_details_requests WHERE username='{$safeUser}' ORDER BY id DESC");
                            if ($res && mysqli_num_rows($res) > 0) {
                                $x = 0;
                                while ($data = mysqli_fetch_assoc($res)) {
                                    $x++;
                                    $json = json_decode((string) ($data['result_json'] ?? ''), true);
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $x; ?></td>
                                        <td><span class="badge bg-dark text-white"><?php echo htmlspecialchars((string) $data['application_no']); ?></span></td>
                                        <td><?php echo htmlspecialchars((string) $data['date']); ?></td>
                                        <td class="text-break"><?php echo htmlspecialchars((string) $data['gstin']); ?></td>
                                        <td class="text-break"><?php echo htmlspecialchars((string) $data['legal_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['status_text']); ?></td>
                                        <td style="min-width:360px;">
                                            <details>
                                                <summary class="text-primary fw-semibold" style="cursor:pointer;">View Full Details</summary>
                                                <div class="table-responsive mt-2">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <tbody>
                                                            <tr><th style="width:35%;">Constitution</th><td><?php echo htmlspecialchars((string) ($json['constitution'] ?? '-')); ?></td></tr>
                                                            <tr><th>Tax Payer Type</th><td><?php echo htmlspecialchars((string) ($json['tax_payer_type'] ?? '-')); ?></td></tr>
                                                            <tr><th>Registration Date</th><td><?php echo htmlspecialchars((string) ($json['registration_date'] ?? '-')); ?></td></tr>
                                                            <tr><th>State Jurisdiction</th><td class="text-break"><?php echo htmlspecialchars((string) ($json['state_jurisdiction'] ?? '-')); ?></td></tr>
                                                            <tr><th>Primary Address</th><td class="text-break"><?php echo htmlspecialchars((string) ($json['primary_address'] ?? '-')); ?></td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center">No records found.</td></tr>';
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
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
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
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#example2').DataTable({
        lengthChange: false
    });
});
</script>
</body>
</html>
