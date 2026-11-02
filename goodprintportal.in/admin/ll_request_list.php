<?php 
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
?>

<!-- SweetAlert2 Library -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>

<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">LL Exam Applications</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="ll_exam.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i> New Application
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="mb-0">ALL LL EXAM APPLICATIONS</h5>
                    </div>
                </div>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">SL.</th>
                                <th class="text-center">Application No</th>
                                <th class="text-center">Applicant Name</th>
                                <th class="text-center">DOB</th>
                                <th class="text-center">Password</th>
                                <th class="text-center">Exam Pin</th>
                                <th class="text-center">State</th>
                                <th class="text-center">Date</th>
                                <th class="text-center">Amount</th>
                                <th class="text-center">STATUS</th>
                                <th class="text-center">CHECK STATUS</th>
                                <th class="text-center">DELETE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($ahk_conn, "SELECT * FROM ll_exam_list WHERE userid='".$udata['phone']."' ORDER BY id DESC");
                            if(mysqli_num_rows($res) > 0){
                                $x = 0;
                                while($data = mysqli_fetch_assoc($res)){
                                    $x++;
                                    $status = strtoupper($data['status']);
                                    // Default badge logic
                                    if ($status == 'PASSED') $badge = 'success';
                                    elseif ($status == 'REJECTED') $badge = 'danger';
                                    else $badge = 'warning';
                            ?>
                            <tr>
                                <td class="text-center"><?= $x; ?></td>
                                <td class="text-center"><strong><?= htmlspecialchars($data['application_no']); ?></strong></td>
                                <td class="text-center"><?= htmlspecialchars($data['applicant_name']); ?></td>
                                <td class="text-center"><?= date('d-m-Y', strtotime($data['dob'])); ?></td>
                                <td class="text-center"><code style="background:#f3f4f6;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($data['password']); ?></code></td>
                                <td class="text-center"><?= !empty($data['exam_pin']) ? '<code style="background:#fff3cd;padding:2px 6px;border-radius:4px;">'.htmlspecialchars($data['exam_pin']).'</code>' : '<span class="text-muted">-</span>'; ?></td>
                                <td class="text-center"><?= !empty($data['state']) ? htmlspecialchars($data['state']) : '<span class="text-muted">-</span>'; ?></td>
                                <td class="text-center"><?= htmlspecialchars($data['date']); ?></td>
                                <td class="text-center">₹<?= htmlspecialchars($data['amount']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $badge; ?>"><?= $status; ?></span>
                                </td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-info btn-sm check-status-btn" 
                                            data-tracking-id="<?= htmlspecialchars($data['tracking_id']); ?>"
                                            id="btn-<?= $data['id']; ?>"> 
                                       <i class="fas fa-sync-alt"></i> Check
                                    </button>
                                </td>
                                <td class="text-center">
                                    <form action="action_ll_exam.php" method="post" onsubmit="return confirm('Are you sure you want to remove this record?');">
                                        <input name="id" type="hidden" value="<?= (int)$data['id']; ?>" />
                                        <button class="btn btn-danger btn-sm" type="submit">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='12' class='text-center'>No Records Found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status is fetched through our own ll_status_check.php (PariPrint API key stays on the server).
    const PARIPRINT_STATUS_API = 'll_status_check.php?order_id=';
    const statusButtons = document.querySelectorAll('.check-status-btn');

    async function checkStatus(trackingId, buttonElement) {
        if (buttonElement.disabled) return;

        buttonElement.disabled = true;
        const originalHtml = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';

        try {
            Swal.fire({
                title: 'Checking Status via PARIPRINT...',
                text: 'Please wait while we fetch latest status from admin.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            // 1. Poll PARIPRINT status API (Voter PDF jaisa pattern)
            const statusUrl = PARIPRINT_STATUS_API + encodeURIComponent(trackingId);
            const response = await fetch(statusUrl, { method: 'GET' });

            let rawText = '';
            try { rawText = await response.text(); } catch(_e) {}
            let data = null;
            try { if (rawText && rawText.trim() !== '') data = JSON.parse(rawText); } catch(_pe) { data = null; }
            if (!data && typeof rawText === 'string' && rawText.trim() !== '') {
                const jsonMatch = rawText.match(/\{[\s\S]*\}/);
                if (jsonMatch) { try { data = JSON.parse(jsonMatch[0]); } catch(_e){} }
            }

            let statusName = 'pending';
            let message = data && data.message ? data.message : 'No update yet. Admin abhi process nahi kiya.';
            let downloadUrl = '';
            if (data && (typeof data.status_name === 'string')) statusName = String(data.status_name).toLowerCase().trim();
            else if (data && (typeof data.status_text === 'string')) statusName = String(data.status_text).toLowerCase().trim();
            else if (data && (typeof data.service_status === 'string')) statusName = String(data.service_status).toLowerCase().trim();
            else if (data && (typeof data.status === 'string')) statusName = String(data.status).toLowerCase().trim();
            if (data && (typeof data.download_url === 'string')) downloadUrl = String(data.download_url);
            else if (data && (typeof data.direct_download_url === 'string')) downloadUrl = String(data.direct_download_url);

            let dbStatus = 'pending';
            let displayTitle = 'Under Process ⏳';
            let displayIcon = 'warning';
            let displayText = message;

            if (statusName === 'passed' || statusName === 'success') {
                dbStatus = 'passed';
                displayTitle = 'Exam Passed! ✅';
                displayIcon = 'success';
                displayText = 'Congratulations! Your LL Exam status: ' + statusName.toUpperCase() + '.' + (message ? '\n\nNote: ' + message : '');
            } else if (statusName === 'rejected' || statusName === 'failed') {
                dbStatus = 'rejected';
                displayTitle = 'Exam Rejected ❌';
                displayIcon = 'error';
                displayText = (message ? message : 'Unfortunately, your exam request was rejected.');
            } else {
                dbStatus = 'pending';
                displayTitle = 'Under Process ⏳';
                displayIcon = 'warning';
                displayText = message ? message : 'Request pending hai. Admin abhi action nahi liya hai. Thodi der baad dobara check karein.';
            }

            if (downloadUrl !== '') {
                displayText += '\n\n✅ Result PDF ready hai. Download karne ke liye "Download Result" button par click karein.';
            }

            // 2. Update Local GOODPRINTPORTAL database + read REFUND response
            let refundInfo = '';
            try {
                const formData = new URLSearchParams();
                formData.append('order_id', trackingId);
                formData.append('status', dbStatus);
                const updResp = await fetch('update_ll_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                });
                try {
                    const updText = await updResp.text();
                    let updJson = null;
                    try { if (updText) updJson = JSON.parse(updText); } catch(_e){}
                    if (updJson && typeof updJson === 'object' && dbStatus === 'rejected') {
                        if (updJson.refund_message) {
                            refundInfo = '\n\n💰 ' + String(updJson.refund_message);
                        } else if (updJson.refund === true) {
                            const amt = Number(updJson.refund_amount || 0);
                            if (amt > 0) refundInfo = '\n\n💰 ₹' + amt.toFixed(2) + ' refunded to your wallet.';
                        }
                    }
                } catch(_u2) {}
            } catch(_u) {}

            // 3. Show Result
            Swal.close();
            const opts = {
                title: displayTitle,
                text: displayText + refundInfo,
                icon: displayIcon,
                confirmButtonColor: '#3085d6'
            };
            if (downloadUrl !== '') {
                opts.showCancelButton = true;
                opts.confirmButtonText = 'Download Result';
                opts.cancelButtonText = 'Close';
            } else {
                opts.confirmButtonText = 'OK';
            }
            Swal.fire(opts).then((res) => {
                if (downloadUrl !== '' && res && res.isConfirmed) {
                    window.open(downloadUrl, '_blank', 'noopener,noreferrer');
                }
                setTimeout(function(){ window.location.reload(); }, 300);
            });
        } catch (error) {
            Swal.close();
            Swal.fire({
                title: 'Connection Error',
                text: 'PARIPRINT server se connect nahi ho saka. Please try again.',
                icon: 'error',
                confirmButtonText: 'Try Again'
            });
        } finally {
            try { buttonElement.innerHTML = originalHtml; } catch(_e){}
            try { buttonElement.disabled = false; } catch(_e){}
        }
    }

    statusButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const trackingId = this.getAttribute('data-tracking-id');
            if (trackingId) checkStatus(trackingId, this);
            else Swal.fire('Error', 'Tracking ID missing.', 'error');
        });
    });
});
</script>

<?php include('footer.php'); ?>
<!-- DataTable Scripts -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script>
    $(document).ready(function() {
        $('#example2').DataTable({
            lengthChange: false,
            buttons: ['copy', 'excel', 'pdf', 'print']
        }).buttons().container().appendTo('#example2_wrapper .col-md-6:eq(0)');
    });
</script>
</body>
</html>