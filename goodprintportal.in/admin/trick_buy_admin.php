<?php
include('../includes/session.php');
include('../includes/config.php');
include('trick_buy_common.php');
include('../template/ahkweb/header.php');

header('Content-Type: text/html; charset=UTF-8');

// --- Only admin / super admin allowed ---
$isAdmin = false;
if (!empty($udata)) {
    $role = strtolower(trim((string)($udata['role'] ?? $udata['type'] ?? '')));
    $isAdmin = ($role === 'admin' || $role === 'super admin' || $role === 'super_admin' || !empty($udata['is_admin']) || !empty($udata['admin_role']));
    if (isset($udata['user_type'])) $isAdmin = $isAdmin || (strtolower($udata['user_type']) === 'admin');
}
if (!$isAdmin) {
    echo '<div class="container mt-5"><div class="alert alert-danger"><h3>Access Denied</h3><p>Admin login required.</p><a href="index.php" class="btn btn-primary">Go to Dashboard</a></div></div>';
    include('footers.php'); exit;
}

$adminAlert = null;
// Pick up redirect-success alert from quick action PRG
if (isset($_GET['updated']) && isset($_GET['s'])) {
    $adminAlert = ['Status Updated', 'Order #' . (int)$_GET['updated'] . ' → ' . strtoupper(getSafe($_GET['s'])), 'success'];
}

// ----- Actions -----
$do = $_GET['do'] ?? $_POST['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

// Quick 1-click Approve / Reject / Process -> use same backend function as status form
if (($do === 'approve' || $do === 'reject' || $do === 'process' || $do === 'complete') && $id > 0) {
    $quickMap = [
        'approve'  => 'completed',
        'reject'   => 'rejected',
        'process'  => 'processing',
        'complete' => 'completed',
    ];
    $_POST['new_status'] = $quickMap[$do];
    $_POST['admin_note'] = $_POST['admin_note'] ?? '';
    $_POST['rejection_reason'] = $_POST['rejection_reason'] ?? '';
    $do = 'status';
}

if ($do === 'status' && $id > 0 && isset($_POST['new_status'])) {
    $newStatus = $_POST['new_status'];
    $adminNote = trim($_POST['admin_note'] ?? '');
    $rejectReason = trim($_POST['rejection_reason'] ?? '');
    $ok     = false;
    $uploaded = null;
    $refunded = false;
    try {
        if (!in_array($newStatus, ['pending','processing','completed','rejected','refunded','cancelled'])) {
            throw new Exception('Invalid status.');
        }

        // Refund = status 'refunded' → revert wallet balance + record trans
        if ($newStatus === 'refunded') {
            // get user_id + amount
            $stmt = mysqli_prepare($ahk_conn, "SELECT user_id, amount, status FROM trick_orders WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $uid, $amt, $oldStatus);
            $okFetch = mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
            if (!$okFetch) throw new Exception('Order not found.');
            if ($oldStatus === 'refunded') throw new Exception('Already refunded.');
            if ((int)$uid > 0) {
                $stmt = mysqli_prepare($ahk_conn, "UPDATE users SET balance = balance + ? WHERE id=? LIMIT 1");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'di', $amt, $uid);
                    @mysqli_stmt_execute($stmt); @mysqli_stmt_close($stmt);
                }
                // Correct table columns: uid, amount, description, txn_ref, date, status (credit = positive amount)
                $stmt = mysqli_prepare($ahk_conn,
                    "INSERT INTO transactions (uid, amount, description, txn_ref, date, status)
                     VALUES (?, ?, ?, ?, NOW(), 'success')");
                $desc = 'Trick Order Refund #'.$id.' '.$oldStatus; $refno = 'REF-TRK-'.$id;
                $amtCredit = abs((float)$amt);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'idss', $uid, $amtCredit, $desc, $refno);
                    @mysqli_stmt_execute($stmt); @mysqli_stmt_close($stmt);
                }
                $refunded = true;
            }
        }

        // Upload file if completed + file attached
        if ($newStatus === 'completed' && !empty($_FILES['attachment']['name'])) {
            $f = $_FILES['attachment'];
            if (!empty($f['error']) && $f['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload error code: ' . $f['error']);
            }
            $nameClean = preg_replace('#[^a-z0-9._-]+#i', '_', $f['name']);
            $ext = strtolower(pathinfo($nameClean, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','zip','rar','txt','png','jpg','jpeg','doc','docx','xlsx','ppt','pptx'])) {
                throw new Exception('File type not allowed (pdf/zip/rar/txt/png/jpg/doc/etc only).');
            }
            if ($f['size'] > 25*1024*1024) throw new Exception('Max 25 MB.');
            $targetDir = __DIR__ . '/uploads/trick_orders/results/';
            if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
            $fname = 'trick_' . $id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $targetDir . $fname;
            if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('Unable to save uploaded file.');
            $uploaded = 'results/' . $fname;
        }

        // Update
        if ($uploaded) {
            $stmt = mysqli_prepare($ahk_conn,
                "UPDATE trick_orders SET status=?, admin_note=?, rejection_reason=?, attachment_path=? WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'ssssi', $newStatus, $adminNote, $rejectReason, $uploaded, $id);
        } else {
            $stmt = mysqli_prepare($ahk_conn,
                "UPDATE trick_orders SET status=?, admin_note=?, rejection_reason=? WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'sssi', $newStatus, $adminNote, $rejectReason, $id);
        }
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) throw new Exception(mysqli_error($ahk_conn));

        $adminAlert = ['Status Updated', 'Order #' . $id . ' → ' . strtoupper($newStatus) . ($refunded ? ' (wallet refunded)' : '') . ($uploaded ? ' (file attached)' : ''), 'success'];
        // PRG redirect for GET quick actions (approve/reject via URL) to avoid refresh re-applying
        if (($_GET['do'] ?? '') === 'approve' || ($_GET['do'] ?? '') === 'reject' || ($_GET['do'] ?? '') === 'process' || ($_GET['do'] ?? '') === 'complete') {
            header('Location: trick_buy_admin.php?updated=' . (int)$id . '&s=' . urlencode($newStatus));
            exit;
        }
    } catch (Throwable $e) {
        $adminAlert = ['Update Failed', $e->getMessage(), 'error'];
    }
}

// Fetch orders
$requests = mysqli_query($ahk_conn,
    "SELECT * FROM trick_orders ORDER BY FIELD(status,'pending','processing','completed','rejected','refunded','cancelled'), id DESC");

?>
  <div class="page-wrapper">
    <div class="page-content">
      <?php if ($adminAlert): ?>
        <div class="alert alert-<?= $adminAlert[2] === 'success' ? 'success' : 'danger' ?> mb-3">
          <i class="bi bi-<?= $adminAlert[2] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
          <b><?= htmlspecialchars($adminAlert[0]) ?>:</b> <?= htmlspecialchars($adminAlert[1]) ?>
        </div>
      <?php endif; ?>

      <div class="card border-0 shadow">
        <div class="card-body p-3 p-md-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
              <h3 class="mb-1">TRICK Orders Admin</h3>
              <div class="small text-muted">
                Pending: <b class="text-warning"><?= trick_pending_count($ahk_conn) ?></b> |
                Total: <b><?= $requests ? mysqli_num_rows($requests) : 0 ?></b>
              </div>
            </div>
            <a class="btn btn-outline-primary" href="trick_buy_list.php"><i class="bi bi-people me-1"></i> My Orders (user view)</a>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>ID / App</th>
                  <th>User</th>
                  <th>Service</th>
                  <th>WhatsApp<br><small class="text-muted">AnyDesk</small></th>
                  <th>Note</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>File</th>
                  <th>Created</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$requests || mysqli_num_rows($requests) < 1): ?>
                  <tr><td colspan="10" class="text-center py-5 text-muted">No orders yet.</td></tr>
                <?php endif;
                while ($row = mysqli_fetch_assoc($requests)):
                    $rowStatus = isset($row['status']) ? (string)$row['status'] : '';
                    switch ($rowStatus) {
                        case 'pending':    $statusBadge = '<span class="badge bg-warning text-dark">PENDING</span>'; break;
                        case 'processing': $statusBadge = '<span class="badge bg-info text-white">PROCESSING</span>'; break;
                        case 'completed':  $statusBadge = '<span class="badge bg-success">COMPLETED</span>'; break;
                        case 'rejected':   $statusBadge = '<span class="badge bg-danger">REJECTED</span>'; break;
                        case 'refunded':   $statusBadge = '<span class="badge bg-secondary">REFUNDED</span>'; break;
                        case 'cancelled':  $statusBadge = '<span class="badge bg-dark">CANCELLED</span>'; break;
                        default:           $statusBadge = '<span class="badge bg-light text-dark">'.htmlspecialchars($rowStatus).'</span>';
                    }
                ?>
                  <tr>
                    <td>
                      <div class="fw-semibold">#<?= (int)$row['id'] ?></div>
                      <div class="font-monospace small text-muted"><?= htmlspecialchars($row['application_no']) ?></div>
                    </td>
                    <td>
                      <div><?= htmlspecialchars($row['user_name'] ?? '-') ?></div>
                      <div class="small text-muted"><?= htmlspecialchars($row['user_phone']) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($row['service_label']) ?></div>
                      <div class="small text-muted"><?= htmlspecialchars($row['service_key']) ?></div>
                    </td>
                    <td>
                      <div><a class="text-success" target="_blank" href="https://wa.me/<?= preg_replace('#[^0-9]#', '', $row['whatsapp_number']) ?>"><i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($row['whatsapp_number']) ?></a></div>
                      <div class="small text-muted">AnyDesk: <b><?= htmlspecialchars($row['anydesk_id']) ?></b></div>
                    </td>
                    <td class="small">
                      <?php if (!empty($row['customer_note'])): ?><div><?= htmlspecialchars(mb_substr($row['customer_note'], 0, 120)) ?></div><?php endif; ?>
                      <?php if (!empty($row['admin_note'])): ?><div class="text-primary mt-1"><b>Admin:</b> <?= htmlspecialchars(mb_substr($row['admin_note'],0,120)) ?></div><?php endif; ?>
                      <?php if (!empty($row['rejection_reason'])): ?><div class="text-danger mt-1"><b>Reject Reason:</b> <?= htmlspecialchars(mb_substr($row['rejection_reason'],0,120)) ?></div><?php endif; ?>
                    </td>
                    <td>₹<?= number_format((float)$row['amount'], 2) ?></td>
                    <td><?= $statusBadge ?></td>
                    <td>
                      <?php if ($row['attachment_path']): ?>
                        <a class="btn btn-sm btn-outline-success" href="uploads/trick_orders/<?= htmlspecialchars($row['attachment_path']) ?>" target="_blank"><i class="bi bi-download"></i> File</a>
                      <?php else: ?>-<?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($row['created_at']) ?><br><span class="text-xs"><?= htmlspecialchars($row['updated_at']) ?></span></td>
                    <td>
                      <div class="d-flex gap-2 align-items-center justify-content-center flex-wrap">
                        <a href="?do=approve&id=<?= (int)$row['id'] ?>"
                           class="btn btn-sm btn-success trick-quick-btn"
                           onclick="return confirm('APPROVE / COMPLETE order #<?= (int)$row['id'] ?> (status = completed)?');">
                           <i class="bi bi-check2 me-1"></i>Approve
                        </a>
                        <a href="?do=reject&id=<?= (int)$row['id'] ?>"
                           class="btn btn-sm btn-danger trick-quick-btn"
                           onclick="return confirm('REJECT order #<?= (int)$row['id'] ?>? User money will NOT be returned unless you use Refund instead.');">
                           <i class="bi bi-x-lg me-1"></i>Reject
                        </a>
                        <button class="btn btn-sm btn-outline-secondary trick-action-btn"
                          data-id="<?= (int)$row['id'] ?>"
                          data-status="<?= htmlspecialchars($row['status']) ?>"
                          data-admin-note="<?= htmlspecialchars($row['admin_note'] ?? '') ?>"
                          data-reason="<?= htmlspecialchars($row['rejection_reason'] ?? '') ?>">
                          <i class="bi bi-sliders me-1"></i>More
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

<!-- Status Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="trick_buy_admin.php" enctype="multipart/form-data" class="modal-content">
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="id" id="m_id" value="0">
      <div class="modal-header">
        <h5 class="modal-title">Update Order Status #<span id="m_id_disp">0</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">New Status</label>
            <select name="new_status" id="m_status" class="form-select" required onchange="toggleFields()">
              <option value="pending">PENDING</option>
              <option value="processing">PROCESSING</option>
              <option value="completed">COMPLETED</option>
              <option value="rejected">REJECTED</option>
              <option value="refunded">REFUNDED (revert wallet amount)</option>
              <option value="cancelled">CANCELLED</option>
            </select>
          </div>
          <div class="col-md-6" id="fileWrap">
            <label class="form-label fw-semibold">Attachment (for Completed)</label>
            <input type="file" name="attachment" class="form-control"
                   accept=".pdf,.zip,.rar,.txt,.png,.jpg,.jpeg,.doc,.docx,.xlsx">
            <div class="form-text">Max 25 MB. Result PDF/ZIP.</div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Admin Note (visible to user)</label>
            <textarea name="admin_note" id="m_admin_note" rows="2" class="form-control"></textarea>
          </div>
          <div class="col-12" id="rejectWrap">
            <label class="form-label fw-semibold">Rejection / Refund Reason (visible to user)</label>
            <textarea name="rejection_reason" id="m_reason" rows="2" class="form-control" placeholder="Why rejected/refunded?"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php include('footers.php'); ?>
<script>
(function(){
    function _toggleFields(){
        var s = document.getElementById('m_status') ? document.getElementById('m_status').value : '';
        if (document.getElementById('fileWrap')) document.getElementById('fileWrap').style.display = (s === 'completed') ? '' : 'none';
        if (document.getElementById('rejectWrap')) document.getElementById('rejectWrap').style.display = (s === 'rejected' || s === 'refunded' || s === 'cancelled') ? '' : 'none';
    }
    function _openAction(id, status, note, reason){
        if (!document.getElementById('m_id')) return;
        document.getElementById('m_id').value = id;
        if (document.getElementById('m_id_disp')) document.getElementById('m_id_disp').textContent = id;
        if (document.getElementById('m_status')) document.getElementById('m_status').value = status || 'pending';
        if (document.getElementById('m_admin_note')) document.getElementById('m_admin_note').value = note || '';
        if (document.getElementById('m_reason')) document.getElementById('m_reason').value = reason || '';
        _toggleFields();
        var modal = document.getElementById('actionModal');
        if (!modal) return;
        // 3-level fallback: bootstrap 5, jquery modal, native class toggle display block
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var bm = bootstrap.Modal.getOrCreateInstance(modal);
            bm.show();
        } else if (window.jQuery && jQuery.fn && jQuery.fn.modal) {
            jQuery(modal).modal('show');
        } else {
            modal.classList.add('show');
            modal.style.display = 'block';
            modal.setAttribute('aria-modal', 'true');
            if (document.querySelectorAll('.modal-backdrop').length === 0) {
                var bd = document.createElement('div');
                bd.className = 'modal-backdrop fade show';
                bd.onclick = function(){
                    modal.classList.remove('show'); modal.style.display='none';
                    bd.parentNode.removeChild(bd);
                };
                document.body.appendChild(bd);
            }
        }
    }
    _toggleFields();
    // 🔴 BUBBLE DELEGATION on document.body — WORKS REGARDLESS OF WHEN ROWS ADDED / WHEN SCRIPT LOADED / WHETHER BOOTSTRAP READY
    document.body.addEventListener('click', function(ev){
        var btn = (ev.target && ev.target.closest) ? ev.target.closest('.trick-action-btn') : null;
        if (!btn) return;
        ev.preventDefault();
        ev.stopPropagation();
        var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
        var st = btn.getAttribute('data-status') || 'pending';
        var an = btn.getAttribute('data-admin-note') || '';
        var rr = btn.getAttribute('data-reason') || '';
        _openAction(id, st, an, rr);
    }, false);
})();
</script>
<?php
if ($adminAlert) trickAlert($adminAlert[0], $adminAlert[1], $adminAlert[2], 'trick_buy_admin.php');
?>

