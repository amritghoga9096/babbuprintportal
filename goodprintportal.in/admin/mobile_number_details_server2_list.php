<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `mobile_number_details_server2_requests` (
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
    KEY `idx_mobile_s2_application_no` (`application_no`),
    KEY `idx_mobile_s2_username` (`username`),
    KEY `idx_mobile_s2_mobile_number` (`mobile_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
?>
<style>
    .badge-s2 { background:#7c3aed !important; }
    .kv-card { background:#faf5ff; border:1px solid #ede9fe; border-radius:12px; padding:10px 12px; }
    .kv-card .k { font-size:11px; text-transform: uppercase; letter-spacing: 0.5px; color:#7c3aed; font-weight:800; margin-bottom:2px; }
    .kv-card .v { font-size: 14px; color:#1e1b4b; word-break: break-word; font-weight:600; }
    .kv-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px; }
    @media (max-width: 640px) { .kv-grid { grid-template-columns: 1fr; } }
</style>
<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Mobile Number Details Server 2 — Request List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="mobile_number_details_server2" class="btn badge-s2 text-white">
                    <i class="bi bi-search me-1"></i> Server 2 Search
                </a>
                <a href="mobile_number_details" class="btn btn-outline-primary" title="Original Server 1">
                    <i class="bi bi-arrow-left-right me-1"></i> S1
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">Mobile Number Details Server 2 — Records</h5>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL.</th>
                                <th>Mobile Number</th>
                                <th>Date</th>
                                <th>Fee</th>
                                <th>Total Count</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $safeUser = mysqli_real_escape_string($ahk_conn, (string)($udata['phone'] ?? ''));
                            $res = mysqli_query($ahk_conn, "SELECT * FROM mobile_number_details_server2_requests WHERE username='{$safeUser}' ORDER BY id DESC");
                            if ($res && mysqli_num_rows($res) > 0) {
                                $x = 0;
                                while ($data = mysqli_fetch_assoc($res)) {
                                    $x++;
                                    $json = json_decode((string)($data['result_json'] ?? ''), true);
                                    $records = [];
                                    $summary = [];
                                    if (is_array($json)) {
                                        $summary = $json;
                                        unset($summary['wallet_balance']);
                                        if (isset($json['data']) && is_array($json['data'])) {
                                            foreach ($json['data'] as $rr) {
                                                if (is_array($rr)) $records[] = $rr;
                                            }
                                        }
                                    }
                                    $downloadUrl = trim((string)($data['download_url'] ?? ''));
                                    $mapLabels = [
                                        'name'    => 'Owner Name',
                                        'fname'   => 'Father Name',
                                        'id'      => 'Aadhaar / ID',
                                        'mobile'  => 'Primary Mobile',
                                        'alt'     => 'Alt Mobile',
                                        'email'   => 'Email',
                                        'address' => 'Address',
                                        'circle'  => 'Circle',
                                    ];
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $x; ?></td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars((string)$data['mobile_number']); ?></span></td>
                                        <td><?php echo htmlspecialchars((string)$data['date']); ?></td>
                                        <td class="text-center"><?php echo 'Rs. ' . number_format((float)$data['fee'], 2); ?></td>
                                        <td class="text-center"><?php echo (int)$data['total_count']; ?></td>
                                        <td>
                                            <?php
                                            $_st = strtolower(trim((string)$data['status_text']));
                                            $_cls = 'bg-info';
                                            if ($_st === 'success' || $_st === 'true' || $_st === 'ok' || $_st === '200') $_cls = 'bg-success';
                                            if ($_st === 'fail' || $_st === 'failed' || $_st === 'error' || $_st === 'false') $_cls = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $_cls; ?>"><?php echo htmlspecialchars((string)$data['status_text']); ?></span>
                                        </td>
                                        <td style="min-width:400px;">
                                            <details>
                                                <summary class="text-primary fw-semibold" style="cursor:pointer;">View Full Details</summary>
                                                <?php if (!empty($summary)): ?>
                                                    <div class="mt-2 mb-2 p-2" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;">
                                                        <div class="small text-muted mb-1">Summary (wallet_balance removed)</div>
                                                        <div class="kv-grid">
                                                            <div class="kv-card"><div class="k">Message</div><div class="v"><?php echo htmlspecialchars((string)($summary['message'] ?? '-')); ?></div></div>
                                                            <div class="kv-card"><div class="k">Service</div><div class="v"><?php echo htmlspecialchars((string)($summary['service_name'] ?? '-')); ?></div></div>
                                                            <div class="kv-card"><div class="k">Mobile</div><div class="v"><?php echo htmlspecialchars((string)($summary['mobile'] ?? '-')); ?></div></div>
                                                            <div class="kv-card"><div class="k">Total Results</div><div class="v"><?php echo (int)($summary['total_results'] ?? 0); ?></div></div>
                                                            <div class="kv-card"><div class="k">Code</div><div class="v"><?php echo htmlspecialchars((string)($summary['code'] ?? '-')); ?></div></div>
                                                            <div class="kv-card"><div class="k">Fee Charged</div><div class="v">Rs. <?php echo number_format((float)$data['fee'], 2); ?>, Old: <?php echo number_format((float)$data['old_balance'], 2); ?> → New: <?php echo number_format((float)$data['new_balance'], 2); ?></div></div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($records)): ?>
                                                    <?php $rrI = 1; foreach ($records as $rrRow): ?>
                                                        <div class="mt-3 card border">
                                                            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                                <div class="fw-bold text-primary">#<?php echo $rrI++; ?> Record</div>
                                                                <div>
                                                                    <?php if (!empty($rrRow['mobile'])): ?><span class="badge bg-primary me-1"><?php echo htmlspecialchars($rrRow['mobile']); ?></span><?php endif; ?>
                                                                    <?php if (!empty($rrRow['circle'])): ?><span class="badge bg-info"><?php echo htmlspecialchars($rrRow['circle']); ?></span><?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="kv-grid">
                                                                    <?php foreach ($mapLabels as $fk => $lbl):
                                                                        $v = isset($rrRow[$fk]) ? trim((string)$rrRow[$fk]) : '';
                                                                        if ($v === '' || $v === null) continue;
                                                                        $vc = (strcasecmp($v, 'N/A') === 0) ? 'text-muted fst-italic' : '';
                                                                    ?>
                                                                        <div class="kv-card"><div class="k"><?php echo $lbl; ?></div><div class="v <?php echo $vc; ?>"><?php echo htmlspecialchars($v); ?></div></div>
                                                                    <?php endforeach; ?>
                                                                    <?php foreach ($rrRow as $ek => $ev):
                                                                        if (array_key_exists($ek, $mapLabels)) continue;
                                                                        if ($ev === '' || $ev === null) continue;
                                                                        if (is_array($ev) || is_object($ev)) $ev = json_encode($ev, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                                    ?>
                                                                        <div class="kv-card"><div class="k"><?php echo htmlspecialchars(ucwords(str_replace(['_','-'], ' ', (string)$ek))); ?></div><div class="v"><?php echo htmlspecialchars((string)$ev); ?></div></div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="mt-2 text-center text-muted small">No records saved.</div>
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
                                echo '<tr><td colspan="7" class="text-center">No records found. Go to <a href="mobile_number_details_server2" class="text-decoration-underline text-primary">Mobile Number Details Server 2</a> to search your first number.</td></tr>';
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
