<?php
include('../includes/session.php');
include('../includes/config.php');

// ===== Self-heal schema =====
function _cmpSelfHealList($conn) {
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
_cmpSelfHealList($ahk_conn);

// ===== Fetch complaints FIRST so we can highlight unread rows on first visit =====
$__userComplaints = [];
if (!empty($udata) && !empty($udata['id'])) {
    $__res = @mysqli_query($ahk_conn,
        "SELECT c.*, (CASE WHEN c.remark IS NOT NULL AND TRIM(c.remark) <> '' AND (c.remark_seen IS NULL OR c.remark_seen = 0) THEN 1 ELSE 0 END) AS is_unread
         FROM complaints c
         WHERE c.user_id = ".(int)$udata['id']."
         ORDER BY c.complaint_id DESC"
    );
    if ($__res) while ($__rr = mysqli_fetch_assoc($__res)) $__userComplaints[] = $__rr;

    // ===== Now mark all as seen (so next page load highlights are gone) =====
    @mysqli_query($ahk_conn,
        "UPDATE complaints SET remark_seen = 1
         WHERE user_id = ".(int)$udata['id']."
           AND remark IS NOT NULL AND TRIM(remark) <> ''
           AND (remark_seen IS NULL OR remark_seen = 0)"
    );
}

include('../template/ahkweb/header.php');
?>

<!-- Start Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h5 class="mb-0">📋 My Complaint List</h5>
                </div>
                <hr>

                <div class="table-responsive">
                    <table id="example2" class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">#</th>
                                <th class="text-center">Ticket #</th>
                                <th class="text-center">Complaint Type</th>
                                <th class="text-center">Message</th>
                                <th class="text-center">UTR</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Remark</th>
                                <th class="text-center">Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($__userComplaints)) {
                                $x = 0;
                                foreach ($__userComplaints as $row) {
                                    $x++;
                                    $unreadCls = (!empty($row['is_unread'])) ? ' class="gp-cmp-row-unread"' : '';
                                    $ticketNo = '';
                                    if (!empty($row['complaint_ref'])) {
                                        $ticketNo = strtoupper((string)$row['complaint_ref']);
                                    } else {
                                        $ticketNo = 'CMP-' . str_pad((string)$row['complaint_id'], 6, '0', STR_PAD_LEFT);
                                    }
                            ?>
                                    <tr<?= $unreadCls ?>>
                                        <td class="text-center"><?= $x; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-dark px-3 py-2 rounded-pill"
                                                  style="font-size:11px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.5px;">
                                                🎫 <?= htmlspecialchars($ticketNo); ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><?= htmlspecialchars($row['complaint_type']); ?></td>
                                       <td class="text-center">
    <div class="message-box resizable-box text-center">
        <?= nl2br(htmlspecialchars($row['message'])); ?>
    </div>
</td>

                                        <td class="text-center"><?= $row['utrnumber'] ? htmlspecialchars($row['utrnumber']) : '-'; ?></td>
                                        <td class="text-center">
                                            <?php if ($row['status'] == 'Open') { ?>
                                                <span class="badge bg-warning text-dark px-3 py-2">Open</span>
                                            <?php } elseif ($row['status'] == 'Close') { ?>
                                                <span class="badge bg-success px-3 py-2">Closed</span>
                                            <?php } else { ?>
                                                <span class="badge bg-secondary px-3 py-2">Unknown</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center"><?= $row['remark'] ? htmlspecialchars($row['remark']) : 'No Remark'; ?></td>
                                        <td class="text-center"><?= date("d M Y, h:i A", strtotime($row['date_time'])); ?></td>
                                    </tr>
                            <?php
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="8" class="text-center">No complaints submitted yet.</td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styling -->
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


<!-- End Page Wrapper -->

<!--end page wrapper -->
<?php 
include('footer.php');
?>
<!-- Bootstrap JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
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
<script>
$(function() {
    $(".knob").knob();
});
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<!--app JS-->
<script src="../template/ahkweb/assets/js/app.js"></script>
<!-- datatable -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
		$(document).ready(function() {
			$('#example').DataTable();
		  } );
	</script>
	
	<script>
		$(document).ready(function() {
			var table = $('#example2').DataTable( {
				lengthChange: false,
				buttons: [ 'copy', 'excel', 'pdf', 'print']
			} );
		 
			table.buttons().container()
				.appendTo( '#example2_wrapper .col-md-6:eq(0)' );
		} );
	</script>
	
</body>



</html>
<!-- Created By FlexSecure {flex4upoint} -->