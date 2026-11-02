<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `instant_challan_requests` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `application_no` VARCHAR(80) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `vehicle_number` VARCHAR(30) DEFAULT NULL,
    `order_id` VARCHAR(80) DEFAULT NULL,
    `owner_name` VARCHAR(255) DEFAULT NULL,
    `total_challans` INT NOT NULL DEFAULT 0,
    `pending_challans` INT NOT NULL DEFAULT 0,
    `paid_challans` INT NOT NULL DEFAULT 0,
    `download_url` TEXT DEFAULT NULL,
    `result_json` LONGTEXT DEFAULT NULL,
    `status_text` VARCHAR(50) DEFAULT NULL,
    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `old_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `new_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `message` TEXT DEFAULT NULL,
    `date` VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_application_no` (`application_no`),
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Instant Challan Details Request List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="instant_challan_details" class="btn btn-success">
                    <i class="bi bi-search me-1"></i> Instant Challan Details
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">Instant Challan Details Records</h5>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL.</th>
                                <th>Vehicle Number</th>
                                <th>Date</th>
                                <th>Owner</th>
                                <th>Total</th>
                                <th>Pending</th>
                                <th>Paid</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $safeUser = mysqli_real_escape_string($ahk_conn, (string) ($udata['phone'] ?? ''));
                            $res = mysqli_query($ahk_conn, "SELECT * FROM instant_challan_requests WHERE username='{$safeUser}' ORDER BY id DESC");
                            if ($res && mysqli_num_rows($res) > 0) {
                                $x = 0;
                                while ($data = mysqli_fetch_assoc($res)) {
                                    $x++;
                                    $json = json_decode((string) ($data['result_json'] ?? ''), true);
                                    $challans = (is_array($json) && isset($json['challans']) && is_array($json['challans'])) ? $json['challans'] : [];
                                    $vehicleInfo = (is_array($json) && isset($json['vehicle_info']) && is_array($json['vehicle_info'])) ? $json['vehicle_info'] : [];
                                    $vehicleDetails = (is_array($json) && isset($json['vehicle_details']) && is_array($json['vehicle_details'])) ? $json['vehicle_details'] : [];
                                    $downloadUrl = trim((string) ($data['download_url'] ?? ''));
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $x; ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['vehicle_number']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['date']); ?></td>
                                        <td class="text-break"><?php echo htmlspecialchars((string) $data['owner_name']); ?></td>
                                        <td class="text-center"><?php echo (int) $data['total_challans']; ?></td>
                                        <td class="text-center"><?php echo (int) $data['pending_challans']; ?></td>
                                        <td class="text-center"><?php echo (int) $data['paid_challans']; ?></td>
                                        <td style="min-width:380px;">
                                            <details>
                                                <summary class="text-primary fw-semibold" style="cursor:pointer;">View Full Details</summary>
                                                <div class="table-responsive mt-2">
                                                    <table class="table table-sm table-bordered mb-2">
                                                        <tbody>
                                                            <tr><th style="width:35%;">RC Number</th><td><?php echo htmlspecialchars((string) ($vehicleInfo['rc_number'] ?? '-')); ?></td></tr>
                                                            <tr><th>RTO</th><td class="text-break"><?php echo htmlspecialchars((string) ($vehicleInfo['rto'] ?? '-')); ?></td></tr>
                                                            <tr><th>Registration Date</th><td><?php echo htmlspecialchars((string) ($vehicleInfo['reg_date'] ?? '-')); ?></td></tr>
                                                            <tr><th>RC Status</th><td><?php echo htmlspecialchars((string) ($vehicleInfo['rc_status'] ?? '-')); ?></td></tr>
                                                            <tr><th>Make / Model</th><td class="text-break"><?php echo htmlspecialchars((string) (($vehicleDetails['make'] ?? '-') . ' / ' . ($vehicleDetails['model'] ?? '-'))); ?></td></tr>
                                                            <tr><th>Fuel / Color</th><td><?php echo htmlspecialchars((string) (($vehicleDetails['fuel'] ?? '-') . ' / ' . ($vehicleDetails['color'] ?? '-'))); ?></td></tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Challan No</th>
                                                                <th>Amount</th>
                                                                <th>Status</th>
                                                                <th>Offence</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (!empty($challans)): ?>
                                                                <?php foreach ($challans as $challan): ?>
                                                                    <tr>
                                                                        <td class="text-break"><?php echo htmlspecialchars((string) ($challan['challan_no'] ?? '-')); ?></td>
                                                                        <td><?php echo htmlspecialchars((string) ($challan['amount'] ?? '-')); ?></td>
                                                                        <td><?php echo htmlspecialchars((string) ($challan['status'] ?? '-')); ?></td>
                                                                        <td class="text-break"><?php echo htmlspecialchars((string) ($challan['offence'] ?? '-')); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <tr><td colspan="4" class="text-center">No challans found.</td></tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <?php if ($downloadUrl !== ''): ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo htmlspecialchars($downloadUrl); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Open PariPrint Record</a>
                                                    </div>
                                                <?php endif; ?>
                                            </details>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">No records found.</td></tr>';
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
