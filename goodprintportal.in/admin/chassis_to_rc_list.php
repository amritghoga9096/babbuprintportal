<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `chassis_to_rc_requests` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `application_no` VARCHAR(80) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `chassis_number` VARCHAR(80) DEFAULT NULL,
    `order_id` VARCHAR(80) DEFAULT NULL,
    `rc_number` VARCHAR(80) DEFAULT NULL,
    `owner_name` VARCHAR(255) DEFAULT NULL,
    `download_url` TEXT DEFAULT NULL,
    `result_json` LONGTEXT DEFAULT NULL,
    `status_text` VARCHAR(50) DEFAULT NULL,
    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `old_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `new_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `message` TEXT DEFAULT NULL,
    `date` VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ctrc_application_no` (`application_no`),
    KEY `idx_ctrc_username` (`username`),
    KEY `idx_ctrc_chassis_number` (`chassis_number`),
    KEY `idx_ctrc_rc_number` (`rc_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
?>
<style>
    .kv-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px; }
    .kv-card { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:10px 12px; }
    .kv-card .k { font-size:11px; text-transform: uppercase; letter-spacing: .5px; color:#15803d; font-weight:800; margin-bottom:2px; }
    .kv-card .v { font-size: 14px; color:#052e16; word-break: break-word; font-weight:600; }
    @media (max-width: 640px) { .kv-grid { grid-template-columns: 1fr; } }
</style>
<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Chassis To RC Find — Request List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="chassis_to_rc" class="btn text-white" style="background:#16a34a;">
                    <i class="bi bi-search me-1"></i> Chassis → RC New Search
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">Chassis To RC Find — Records</h5>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL.</th>
                                <th>Chassis</th>
                                <th>RC Number</th>
                                <th>Owner</th>
                                <th>Date</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $safeUser = mysqli_real_escape_string($ahk_conn, (string)($udata['phone'] ?? ''));
                            $res = mysqli_query($ahk_conn, "SELECT * FROM chassis_to_rc_requests WHERE username='{$safeUser}' ORDER BY id DESC");
                            if ($res && mysqli_num_rows($res) > 0) {
                                $x = 0;
                                while ($data = mysqli_fetch_assoc($res)) {
                                    $x++;
                                    $json = json_decode((string)($data['result_json'] ?? ''), true);
                                    $summary = [];
                                    $payload = [];
                                    if (is_array($json)) {
                                        $summary = $json;
                                        unset($summary['wallet_balance']);
                                        if (isset($json['data']) && is_array($json['data'])) $payload = $json['data'];
                                    }
                                    $downloadUrl = trim((string)($data['download_url'] ?? ''));
                                    $labels = [
                                        'chassis_number' => 'Chassis / VIN',
                                        'rc_number'      => 'RC No',
                                        'owner_name'     => 'Owner',
                                    ];
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $x; ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars((string)($data['chassis_number'] ?? '-')); ?></span></td>
                                        <td><?php if (!empty($data['rc_number'])): ?><span class="badge bg-primary"><?php echo htmlspecialchars((string)$data['rc_number']); ?></span><?php else: ?>—<?php endif; ?></td>
                                        <td class="text-break"><?php echo htmlspecialchars((string)($data['owner_name'] ?? '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($data['date'] ?? '-')); ?></td>
                                        <td class="text-center"><?php echo 'Rs. ' . number_format((float)($data['fee'] ?? 0), 2); ?></td>
                                        <td>
                                            <?php
                                            $_st = strtolower(trim((string)($data['status_text'] ?? '')));
                                            $_cls = 'bg-info';
                                            if ($_st === 'success' || $_st === 'true' || $_st === 'ok' || $_st === '200') $_cls = 'bg-success';
                                            if ($_st === 'fail' || $_st === 'failed' || $_st === 'error' || $_st === 'false') $_cls = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $_cls; ?>"><?php echo htmlspecialchars((string)($data['status_text'] ?? '-')); ?></span>
                                        </td>
                                        <td style="min-width:400px;">
                                            <details>
                                                <summary class="fw-semibold" style="cursor:pointer;color:#15803d;">View Full Details</summary>
                                                <?php if (!empty($summary)): ?>
                                                    <div class="mt-2 mb-2 p-2" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;">
                                                        <div class="small text-muted mb-1">Summary (wallet_balance removed)</div>
                                                        <div class="kv-grid">
                                                            <div class="kv-card"><div class="k">Message</div><div class="v"><?php echo htmlspecialchars((string)($summary['message'] ?? '-')); ?></div></div>
                                                            <div class="kv-card"><div class="k">Code</div><div class="v"><?php echo htmlspecialchars((string)($summary['code'] ?? '-')); ?></div></div>
                                                            <div class="kv-card"><div class="k">Fee</div><div class="v">Rs. <?php echo number_format((float)($data['fee'] ?? 0),2); ?></div></div>
                                                            <div class="kv-card"><div class="k">Balance Change</div><div class="v">Old: <?php echo number_format((float)($data['old_balance'] ?? 0),2); ?> → New: <?php echo number_format((float)($data['new_balance'] ?? 0),2); ?></div></div>
                                                            <div class="kv-card"><div class="k">Application No</div><div class="v"><?php echo htmlspecialchars((string)($data['application_no'] ?? '-')); ?></div></div>
                                                            <?php if (!empty($data['order_id'])): ?><div class="kv-card"><div class="k">Order / Request ID</div><div class="v"><?php echo htmlspecialchars((string)$data['order_id']); ?></div></div><?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($payload)): ?>
                                                    <div class="mt-2 card border">
                                                        <div class="card-header bg-white">
                                                            <div class="fw-bold" style="color:#16a34a;"><i class="bi bi-database-fill-gear me-2"></i>data Object (All Fields)</div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="kv-grid">
                                                                <?php foreach ($labels as $fk => $lbl):
                                                                    $v = isset($payload[$fk]) ? trim((string)$payload[$fk]) : (isset($data[$fk]) ? trim((string)$data[$fk]) : '');
                                                                    if ($v === '' || $v === null) continue;
                                                                ?>
                                                                    <div class="kv-card"><div class="k"><?php echo $lbl; ?></div><div class="v"><?php echo htmlspecialchars($v); ?></div></div>
                                                                <?php endforeach; ?>
                                                                <?php
                                                                $merged = is_array($payload) ? $payload : [];
                                                                foreach ($merged as $ek => $ev):
                                                                    if (array_key_exists($ek, $labels)) continue;
                                                                    if ($ev === '' || $ev === null) continue;
                                                                    if (is_array($ev) || is_object($ev)) $ev = json_encode($ev, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                                ?>
                                                                    <div class="kv-card"><div class="k"><?php echo htmlspecialchars(ucwords(str_replace(['_','-'],' ',(string)$ek))); ?></div><div class="v"><?php echo htmlspecialchars((string)$ev); ?></div></div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($downloadUrl !== ''): ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo htmlspecialchars($downloadUrl); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Open PariPrint Record</a>
                                                    </div>
                                                <?php endif; ?>
                                                <details class="mt-2">
                                                    <summary class="text-muted small">Raw JSON</summary>
                                                    <pre class="small bg-light p-2 rounded mt-1 mb-0"><?php echo htmlspecialchars(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                                                </details>
                                            </details>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">No records found. Go to <a href="chassis_to_rc" class="text-decoration-underline text-success">Chassis To RC Find</a> to search your first chassis number.</td></tr>';
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
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#example2').DataTable({ lengthChange: false, order: [[0, 'desc']] });
});
</script>
</body>
</html>
