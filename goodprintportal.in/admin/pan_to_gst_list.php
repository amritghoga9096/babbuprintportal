<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `pan_to_gst_requests` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `application_no` VARCHAR(80) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `pan_no` VARCHAR(20) DEFAULT NULL,
    `total_gst` INT NOT NULL DEFAULT 0,
    `result_json` LONGTEXT DEFAULT NULL,
    `status_text` VARCHAR(50) DEFAULT NULL,
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">PAN to GST Request List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="pan_to_gst" class="btn btn-success">
                    <i class="bi bi-search me-1"></i> PAN to GST
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">PAN to GST Records</h5>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL.</th>
                                <th>Application No</th>
                                <th>Date</th>
                                <th>PAN</th>
                                <th>Total GST</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $safeUser = mysqli_real_escape_string($ahk_conn, (string) ($udata['phone'] ?? ''));
                            $res = mysqli_query($ahk_conn, "SELECT * FROM pan_to_gst_requests WHERE username='{$safeUser}' ORDER BY id DESC");
                            if ($res && mysqli_num_rows($res) > 0) {
                                $x = 0;
                                while ($data = mysqli_fetch_assoc($res)) {
                                    $x++;
                                    $json = json_decode((string) ($data['result_json'] ?? ''), true);
                                    $rows = (is_array($json) && isset($json['results']) && is_array($json['results'])) ? $json['results'] : [];
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $x; ?></td>
                                        <td><span class="badge bg-dark text-white"><?php echo htmlspecialchars((string) $data['application_no']); ?></span></td>
                                        <td><?php echo htmlspecialchars((string) $data['date']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['pan_no']); ?></td>
                                        <td class="text-center fw-bold"><?php echo (int) ($data['total_gst'] ?? 0); ?></td>
                                        <td style="min-width:340px;">
                                            <?php if ($rows): ?>
                                                <details>
                                                    <summary class="text-primary fw-semibold" style="cursor:pointer;">View GST Details</summary>
                                                    <div class="table-responsive mt-2">
                                                        <table class="table table-sm table-bordered mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>GSTIN</th>
                                                                    <th>Status</th>
                                                                    <th>State</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($rows as $row): ?>
                                                                    <tr>
                                                                        <td class="text-break"><?php echo htmlspecialchars((string) ($row['gstin'] ?? '-')); ?></td>
                                                                        <td><?php echo htmlspecialchars((string) ($row['status'] ?? '-')); ?></td>
                                                                        <td><?php echo htmlspecialchars((string) ($row['state'] ?? '-')); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </details>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo htmlspecialchars((string) ($data['message'] ?? 'No details')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="6" class="text-center">No records found.</td></tr>';
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
