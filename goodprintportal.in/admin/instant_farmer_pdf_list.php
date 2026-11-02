<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `instant_farmer_pdf_requests` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `application_no` VARCHAR(80) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `aadhaar_no` VARCHAR(20) DEFAULT NULL,
    `state_code` VARCHAR(10) DEFAULT NULL,
    `order_id` VARCHAR(80) DEFAULT NULL,
    `farmer_name` VARCHAR(255) DEFAULT NULL,
    `farmer_id` VARCHAR(80) DEFAULT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `download_url` TEXT DEFAULT NULL,
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Instant Farmer PDF Request List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="instant_farmer_pdf" class="btn btn-success">
                    <i class="bi bi-search me-1"></i> Instant Farmer PDF
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">Instant Farmer PDF Records</h5>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL.</th>
                                <th>Application No</th>
                                <th>Date</th>
                                <th>Aadhaar</th>
                                <th>State</th>
                                <th>Name</th>
                                <th>Farmer ID</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $safeUser = mysqli_real_escape_string($ahk_conn, (string) ($udata['phone'] ?? ''));
                            $res = mysqli_query($ahk_conn, "SELECT * FROM instant_farmer_pdf_requests WHERE username='{$safeUser}' ORDER BY id DESC");
                            if ($res && mysqli_num_rows($res) > 0) {
                                $x = 0;
                                while ($data = mysqli_fetch_assoc($res)) {
                                    $x++;
                                    $json = json_decode((string) ($data['result_json'] ?? ''), true);
                                    $download = trim((string) ($data['download_url'] ?? ''));
                                    $fileName = (string) ($data['file_name'] ?? 'FarmerCard.pdf');
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $x; ?></td>
                                        <td><span class="badge bg-dark text-white"><?php echo htmlspecialchars((string) $data['application_no']); ?></span></td>
                                        <td><?php echo htmlspecialchars((string) $data['date']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['aadhaar_no']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['state_code']); ?></td>
                                        <td class="text-break"><?php echo htmlspecialchars((string) $data['farmer_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $data['farmer_id']); ?></td>
                                        <td style="min-width:260px;">
                                            <?php $rid = (int)$data['id']; ?>
                                            <?php if ($rid > 0): ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-success farmer-pdf-download-btn"
                                                        data-local-url="farmer_pdf_download.php?id=<?php echo $rid; ?>"
                                                        data-fallback-url="<?php echo htmlspecialchars($download); ?>"
                                                        data-record-id="<?php echo $rid; ?>"
                                                        data-filename="<?php echo htmlspecialchars($fileName); ?>">
                                                    <i class="bi bi-download"></i> Download PDF
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo htmlspecialchars((string) ($data['message'] ?? 'Download not available')); ?></span>
                                            <?php endif; ?>
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

(function () {
    function triggerBlobDownload(url, fallbackFilename, fallbackUrl) {
        var fb = fallbackUrl || url;
        return fetch(url, { method: 'GET', credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                var disp = response.headers.get('content-disposition') || '';
                var name = fallbackFilename || 'download';
                var m = /filename\*=UTF-8''([^;]+)/i.exec(disp) || /filename="?([^";]+)"?/i.exec(disp);
                if (m && m[1]) { try { name = decodeURIComponent(m[1].replace(/["']/g, '')); } catch (_) { name = m[1].replace(/["']/g, ''); } }
                var ct = (response.headers.get('content-type') || '').toLowerCase();
                if (ct.includes('application/pdf') || ct.includes('octet-stream') || ct.includes('application/x-download') || ct.includes('application/force-download') || (name && /\.(pdf|zip|png|jpe?g|docx?|xlsx?|csv|txt)$/i.test(name))) {
                    return response.blob().then(function (blob) {
                        var obj = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = obj;
                        a.download = name;
                        a.rel = 'noopener';
                        document.body.appendChild(a);
                        a.click();
                        setTimeout(function () {
                            try { URL.revokeObjectURL(obj); } catch (_) { }
                            try { if (a.parentNode) a.parentNode.removeChild(a); } catch (_) { }
                        }, 1400);
                        return true;
                    });
                }
                window.location.href = fb;
                return true;
            })
            .catch(function () {
                window.location.href = fb;
                return true;
            });
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('.farmer-pdf-download-btn');
        if (!btn) return;
        event.preventDefault();
        var url = btn.getAttribute('data-local-url') || '';
        var fb = btn.getAttribute('data-fallback-url') || url;
        var rid = btn.getAttribute('data-record-id') || '';
        var fn = btn.getAttribute('data-filename') || ('farmer-pdf-' + (rid || Date.now()) + '.pdf');
        if (!url) return;
        var orig = btn.innerHTML;
        try { btn.disabled = true; } catch (_) { }
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Preparing...';
        triggerBlobDownload(url, fn, fb).then(function () {
            try { btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Downloading...'; } catch (_) { }
            setTimeout(function () { try { btn.innerHTML = orig; btn.disabled = false; } catch (_) { } }, 1600);
        }).catch(function () {
            try { btn.innerHTML = orig; btn.disabled = false; } catch (_) { }
        });
    });
})();
</script>
</body>
</html>
