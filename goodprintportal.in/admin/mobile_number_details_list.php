<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `mobile_number_details_requests` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `application_no` VARCHAR(80) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `mobile_number` VARCHAR(20) DEFAULT NULL,
    `order_id` VARCHAR(80) DEFAULT NULL,
    `total_count` INT NOT NULL DEFAULT 0,
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Mobile Number Details Request List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="mobile_number_details" class="btn btn-success">
                    <i class="bi bi-search me-1"></i> Mobile Number Details
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">Mobile Number Details Records</h5>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL.</th>
                                <th>Mobile Number</th>
                                <th>Date</th>
                                <th>Total Count</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $safeUser = mysqli_real_escape_string($ahk_conn, (string) ($udata['phone'] ?? ''));
                            $res = mysqli_query($ahk_conn, "SELECT * FROM mobile_number_details_requests WHERE username='{$safeUser}' ORDER BY id DESC");
                            if ($res && mysqli_num_rows($res) > 0) {
                                $x = 0;
                                while ($data = mysqli_fetch_assoc($res)) {
                                    $x++;
                                    $json = json_decode((string) ($data['result_json'] ?? ''), true);
                                    $payload = (is_array($json) && isset($json['data']) && is_array($json['data'])) ? $json['data'] : [];
                                    $results = (isset($payload['results']) && is_array($payload['results'])) ? $payload['results'] : [];
                                    $downloadUrl = trim((string) ($data['download_url'] ?? ''));
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $x; ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['mobile_number']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['date']); ?></td>
                                        <td class="text-center"><?php echo (int) $data['total_count']; ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['status_text']); ?></td>
                                        <td style="min-width:380px;">
                                            <details>
                                                <summary class="text-primary fw-semibold" style="cursor:pointer;">View Full Details</summary>
                                                <div class="table-responsive mt-2">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Mobile</th>
                                                                <th>Alt</th>
                                                                <th>Aadhaar</th>
                                                                <th>Name</th>
                                                                <th>Father Name</th>
                                                                <th>Address</th>
                                                                <th>Circle</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (!empty($results)): ?>
                                                                <?php foreach ($results as $row): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars((string) ($row['mobile'] ?? '-')); ?></td>
                                                                        <td><?php echo htmlspecialchars((string) ($row['alt'] ?? '-')); ?></td>
                                                                        <td><?php echo htmlspecialchars((string) ($row['aadhar'] ?? '-')); ?></td>
                                                                        <td class="text-break"><?php echo htmlspecialchars((string) ($row['name'] ?? '-')); ?></td>
                                                                        <td class="text-break"><?php echo htmlspecialchars((string) ($row['father_name'] ?? '-')); ?></td>
                                                                        <td class="text-break"><?php echo htmlspecialchars((string) ($row['address'] ?? '-')); ?></td>
                                                                        <td class="text-break"><?php echo htmlspecialchars((string) ($row['circle'] ?? '-')); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <tr><td colspan="7" class="text-center">No records found.</td></tr>
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
