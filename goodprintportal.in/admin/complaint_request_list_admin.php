<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Admin Access Check
if (!checkAdmin($udata['type'])) {
    echo "<script>window.location='index';</script>";
    die();
}

function cmpEnsureComplaintRefColumn($conn) {
    if (!$conn) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM complaints");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    if (!in_array('complaint_ref', $cols, true)) {
        @mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN complaint_ref VARCHAR(64) NULL AFTER complaint_id");
    }
    @mysqli_query($conn, "CREATE UNIQUE INDEX idx_complaints_ref ON complaints(complaint_ref)");
    $missing = @mysqli_query($conn, "SELECT complaint_id FROM complaints WHERE complaint_ref IS NULL OR TRIM(complaint_ref) = '' ORDER BY complaint_id");
    if ($missing) {
        while ($row = mysqli_fetch_assoc($missing)) {
            $id = (int)($row['complaint_id'] ?? 0);
            if ($id <= 0) continue;
            do {
                $ref = strtoupper(bin2hex(random_bytes(12)));
                $exists = @mysqli_query($conn, "SELECT 1 FROM complaints WHERE complaint_ref = '{$ref}' LIMIT 1");
                $hasRow = $exists && mysqli_num_rows($exists) > 0;
            } while ($hasRow);
            @mysqli_query($conn, "UPDATE complaints SET complaint_ref = '{$ref}' WHERE complaint_id = {$id} LIMIT 1");
        }
    }
}

function cmpEnsureComplaintIdSchema($conn) {
    if (!$conn) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM complaints");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];

    if (!in_array('complaint_ref', $cols, true)) {
        @mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN complaint_ref VARCHAR(64) NULL AFTER complaint_id");
    }

    @mysqli_query($conn, "DELETE FROM complaints WHERE complaint_id = 0");
    @mysqli_query($conn, "ALTER TABLE complaints MODIFY complaint_id BIGINT UNSIGNED NOT NULL");

    $pk = @mysqli_query($conn, "SHOW INDEX FROM complaints WHERE Key_name = 'PRIMARY'");
    $hasPk = false;
    if ($pk) {
        while ($row = mysqli_fetch_assoc($pk)) {
            if (($row['Key_name'] ?? '') === 'PRIMARY') { $hasPk = true; }
        }
    }
    if (!$hasPk) {
        @mysqli_query($conn, "ALTER TABLE complaints ADD PRIMARY KEY (complaint_id)");
    }

    @mysqli_query($conn, "CREATE UNIQUE INDEX idx_complaints_ref ON complaints(complaint_ref)");
    $missing = @mysqli_query($conn, "SELECT complaint_id FROM complaints WHERE complaint_ref IS NULL OR TRIM(complaint_ref) = '' ORDER BY complaint_id");
    if ($missing) {
        while ($row = mysqli_fetch_assoc($missing)) {
            $id = (int)($row['complaint_id'] ?? 0);
            if ($id <= 0) continue;
            do {
                $ref = strtoupper(bin2hex(random_bytes(12)));
                $exists = @mysqli_query($conn, "SELECT 1 FROM complaints WHERE complaint_ref = '{$ref}' LIMIT 1");
                $hasRow = $exists && mysqli_num_rows($exists) > 0;
            } while ($hasRow);
            @mysqli_query($conn, "UPDATE complaints SET complaint_ref = '{$ref}' WHERE complaint_id = {$id} LIMIT 1");
        }
    }
}

cmpEnsureComplaintIdSchema($ahk_conn);

// Delete Complaint (POST-only to avoid accidental batch deletes from repeated row actions)
if (isset($_POST['delete_ticket'])) {
    $ref = trim((string)($_POST['complaint_ref'] ?? $_POST['complaint_id'] ?? ''));
    $deleteClause = '';
    if ($ref !== '') {
        $ref = mysqli_real_escape_string($ahk_conn, $ref);
        $deleteClause = "complaint_ref = '{$ref}'";
        if (!preg_match('/^[A-F0-9]{24,64}$/i', $ref)) {
            $deleteClause = "complaint_id = " . (int)$ref;
        }
    }
    if ($deleteClause !== '' && mysqli_query($ahk_conn, "DELETE FROM complaints WHERE {$deleteClause} LIMIT 1")) {
        showAlert('Complaint Deleted Successfully');
        ahkRedirect('complaint_request_list_admin', 1200);
    }
    showAlert('Delete Failed');
}

if (isset($_GET['deleteid']) && !empty($_GET['deleteid'])) {
    $deleteid = base64_decode(mysqli_real_escape_string($ahk_conn, $_GET['deleteid']));
    $deleteid = preg_replace('/[^0-9]/', '', (string)$deleteid);
    if ($deleteid !== '' && mysqli_query($ahk_conn, "DELETE FROM complaints WHERE complaint_id='{$deleteid}' LIMIT 1")) {
        showAlert('Complaint Deleted Successfully');
        ahkRedirect('complaint_request_list_admin', 1200);
    }
}

// ===== Self-heal: ensure complaints notification columns exist =====
function _cmpSelfHealSchema($conn) {
    if (!$conn) return;
    static $done = null;
    if ($done === true) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM complaints");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    $add = function($n, $d) use ($conn, $cols) {
        if (!in_array($n, $cols, true)) @mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN {$n} {$d}");
    };
    $add('remark_updated_at', "DATETIME NULL DEFAULT NULL");
    $add('remark_seen', "TINYINT(1) NOT NULL DEFAULT 0");
    $done = true;
}
cmpEnsureComplaintRefColumn($ahk_conn);
_cmpSelfHealSchema($ahk_conn);

$__duplicateComplaintIds = [];
$__dupCheck = @mysqli_query($ahk_conn, "SELECT complaint_id, COUNT(*) AS cnt FROM complaints GROUP BY complaint_id HAVING COUNT(*) > 1 LIMIT 10");
if ($__dupCheck) {
    while ($__dupRow = mysqli_fetch_assoc($__dupCheck)) {
        $__dupId = (int)($__dupRow['complaint_id'] ?? 0);
        if ($__dupId > 0) $__duplicateComplaintIds[] = $__dupId;
    }
}

// Update Complaint Status & Remark
if (isset($_POST['update_ticket']) && isset($_POST['status']) && isset($_POST['remark'])) {
    $ref = trim((string)($_POST['complaint_ref'] ?? $_POST['complaint_id'] ?? ''));
    $status = mysqli_real_escape_string($ahk_conn, (string)($_POST['status'] ?? ''));
    $remark = mysqli_real_escape_string($ahk_conn, (string)($_POST['remark'] ?? ''));
    $whereClause = '';

    if ($ref !== '') {
        $ref = mysqli_real_escape_string($ahk_conn, $ref);
        if (preg_match('/^[A-F0-9]{24,64}$/i', $ref)) {
            $whereClause = "complaint_ref = '{$ref}'";
        } else {
            $whereClause = "complaint_id = " . (int)$ref;
        }
    }

    if ($whereClause !== '') {
        $oldRow = null;
        $or = @mysqli_query($ahk_conn, "SELECT remark FROM complaints WHERE {$whereClause} LIMIT 1");
        if ($or && mysqli_num_rows($or) > 0) $oldRow = mysqli_fetch_assoc($or);
        $oldRemark = $oldRow ? (string)($oldRow['remark'] ?? '') : '';
        $newRemarkNorm = trim((string)$remark);
        $remarkChanged = ($newRemarkNorm !== '' && $newRemarkNorm !== trim($oldRemark));

        if ($remarkChanged) {
            $update = mysqli_query($ahk_conn,
               "UPDATE complaints SET status='$status', remark='$remark',
                       remark_updated_at = NOW(), remark_seen = 0
                 WHERE {$whereClause} LIMIT 1");
        } else {
            $update = mysqli_query($ahk_conn,
               "UPDATE complaints SET status='$status', remark='$remark' WHERE {$whereClause} LIMIT 1");
        }

        if ($update) {
            showAlert('Complaint Updated Successfully');
            ahkRedirect('complaint_request_list_admin', 1200);
        } else {
            showAlert('Update Failed');
        }
    }
}
?>

<!-- Admin Complaint Panel -->
<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">Complaint Management (Admin)</h5>
                <?php if (!empty($__duplicateComplaintIds)): ?>
                    <div class="alert alert-danger mb-3">
                        Duplicate complaint IDs detected in the database: <?= implode(', ', $__duplicateComplaintIds) ?>. This can cause one ticket action to affect multiple rows.
                    </div>
                <?php endif; ?>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">#</th>
                                <th class="text-center">Ticket #</th>
                                <th class="text-center">User Details</th>
                                <th class="text-center">Complaint Type</th>
                                <th class="text-center">Message</th>
                                <th class="text-center">UTR</th>
                                <th class="text-center">Date</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Remark</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = mysqli_query($ahk_conn,
                                "SELECT c.*, u.phone as user_phone, u.email as user_email
                                 FROM complaints c
                                 LEFT JOIN users u ON c.user_id = u.id
                                 ORDER BY
                                   (CASE
                                      WHEN (c.status = 'Open' OR c.status IS NULL OR TRIM(COALESCE(c.status,'')) = '')
                                        OR (c.remark IS NULL OR TRIM(c.remark) = '')
                                      THEN 0 ELSE 1
                                    END) ASC,
                                   c.complaint_id DESC"
                            );
                            if ($query && mysqli_num_rows($query) > 0) {
                                $x = 0;
                                while ($row = mysqli_fetch_assoc($query)) {
                                    $x++;
                                    $isUnresolved = ($row['status'] === 'Open'
                                        || trim((string)($row['remark'] ?? '')) === ''
                                        || $row['status'] === null
                                        || trim((string)$row['status']) === '');
                                    $rowClass = $isUnresolved ? 'bg-light' : '';
                                    $ticketDisplay = !empty($row['complaint_ref'])
                                        ? $row['complaint_ref']
                                        : ('CMP-' . str_pad((int)$row['complaint_id'], 6, '0', STR_PAD_LEFT));
                                    $userPhoneDisplay = !empty($row['user_phone'])
                                        ? $row['user_phone']
                                        : (!empty($row['phone']) ? $row['phone'] : 'N/A');
                                    ?>
                                    <tr class="<?= $rowClass; ?>" style="<?= $isUnresolved ? 'background:linear-gradient(90deg,#fff3cd 0%,#ffffff 35%);' : ''; ?>">
                                        <td class="text-center">
                                            <?= $x; ?>
                                            <?php if ($isUnresolved) { ?>
                                                <br><span class="badge bg-danger" style="font-size:10px;">NEW</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center">
                                            <code style="font-weight:800;color:#0d6efd;"><?= htmlspecialchars($ticketDisplay); ?></code>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= htmlspecialchars($row['username']); ?></strong><br>
                                            <small>
                                                <i class="bx bx-user"></i> UID: <?= (int)$row['user_id']; ?><br>
                                                <i class="bx bx-phone"></i> 📞 <strong><?= htmlspecialchars($userPhoneDisplay); ?></strong><br>
                                                <?php if (!empty($row['user_email'])) { ?>
                                                    <i class="bx bx-envelope"></i> <?= htmlspecialchars($row['user_email']); ?>
                                                <?php } ?>
                                            </small>
                                        </td>
                                        <td class="text-center"><?= htmlspecialchars($row['complaint_type']); ?></td>
                                        <td class="text-center">
    <div class="message-box resizable-box text-center">
        <?= nl2br(htmlspecialchars($row['message'])); ?>
    </div>
</td>
                                        <td class="text-center"><?= $row['utrnumber'] ?? '-'; ?></td>
                                        <td class="text-center"><?= date('d M Y h:i A', strtotime($row['date_time'])); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['status'] === 'Open' || $row['status'] === null || trim((string)$row['status']) === '') { ?>
                                                <span class="badge bg-warning" style="font-size:13px;padding:6px 12px;">⚠️ Open</span>
                                            <?php } elseif ($row['status'] === 'Close') { ?>
                                                <span class="badge bg-success" style="font-size:13px;padding:6px 12px;">✅ Closed</span>
                                            <?php } else { ?>
                                                <span class="badge bg-secondary">-</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $remarkVal = trim((string)($row['remark'] ?? ''));
                                            if ($remarkVal === '') {
                                                echo '<span class="text-danger fw-bold">⚠️ No Response Yet</span>';
                                            } else {
                                                echo htmlspecialchars($remarkVal);
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" style="min-width:200px;" data-complaint-id="<?= htmlspecialchars((string)($row['complaint_ref'] ?? $row['complaint_id'])); ?>">
                                                <input type="hidden" name="complaint_ref" value="<?= htmlspecialchars((string)($row['complaint_ref'] ?? $row['complaint_id'])); ?>">
                                                <input type="hidden" name="complaint_id" value="<?= (int)$row['complaint_id']; ?>">
                                                <select id="status_<?= htmlspecialchars((string)($row['complaint_ref'] ?? $row['complaint_id'])); ?>" name="status" class="form-control mb-2" required>
                                                    <option value="">-- Status --</option>
                                                    <option value="Open" <?= ($row['status'] == 'Open' || $row['status'] === null || trim((string)$row['status']) === '') ? 'selected' : '' ?>>Open</option>
                                                    <option value="Close" <?= $row['status'] == 'Close' ? 'selected' : '' ?>>Close</option>
                                                </select>
                                                <input id="remark_<?= htmlspecialchars((string)($row['complaint_ref'] ?? $row['complaint_id'])); ?>" type="text" name="remark" class="form-control mb-2" placeholder="Remark (required)" value="<?= htmlspecialchars($row['remark']); ?>" required>
                                                <button type="submit" name="update_ticket" value="1" class="btn btn-success btn-sm">Update</button>
                                                <button type="submit" name="delete_ticket" value="1" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure to delete this complaint?');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="10" class="text-center">No complaints found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.message-box {
    max-height: 120px;
    overflow-y: auto;
    overflow-x: hidden;
    white-space: pre-wrap;
    word-wrap: break-word;
    text-align: center; /* <-- Center the text inside the box */
    padding: 10px;
    font-size: 14px;
    line-height: 1.5;
    background-color: #fefefe;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
    scrollbar-width: thin;
    scrollbar-color: #bbb #eee;
}

/* Make box resizable */
.resizable-box {
    resize: vertical;
    min-height: 60px;
}

/* WebKit scrollbar styling */
.message-box::-webkit-scrollbar {
    width: 6px;
}
.message-box::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
}
.message-box::-webkit-scrollbar-thumb {
    background-color: #adb5bd;
    border-radius: 8px;
}
</style>
<?php include('footer.php'); ?>

<!-- JS Includes -->
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
<script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
<script>
    $(function () {
        $(".knob").knob();
    });
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function () {
        var table = $('#example2').DataTable({
            lengthChange: false,
            buttons: ['copy', 'excel', 'pdf', 'print']
        });
        table.buttons().container()
            .appendTo('#example2_wrapper .col-md-6:eq(0)');
    });
</script>
<!-- Created By FlexSecure {flex4upoint} -->