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
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">PAN Requests</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="pan_find.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i> New Search
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
                        <h5 class="mb-0">ALL PAN REQUESTS</h5>
                    </div>
                </div>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">SL.</th>
                                <th class="text-center">ORDER ID</th>
                                <th class="text-center">AADHAAR NO</th>
                                <th class="text-center">PAN NUMBER</th>
                                <th class="text-center">DATE</th>
                                <th class="text-center">AMOUNT</th>
                                <th class="text-center">STATUS</th>
                                <th class="text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($ahk_conn, "SELECT * FROM pan_list WHERE userid='".$udata['phone']."' ORDER BY id DESC");
                            if(mysqli_num_rows($res) > 0){
                                $x = 0;
                                while($data = mysqli_fetch_assoc($res)){
                                    $x++;
                                    $status = strtoupper($data['status']);
                                    // Default badge logic
                                    if ($status == 'SUCCESS') $badge = 'success';
                                    elseif ($status == 'FAILED') $badge = 'danger';
                                    else $badge = 'warning';
                            ?>
                            <tr>
                                <td class="text-center"><?= $x; ?></td>
                                <td class="text-center"><strong><?= htmlspecialchars($data['order_id']); ?></strong></td>
                                <td class="text-center"><?= htmlspecialchars($data['aadhaar_number']); ?></td>
                                <td class="text-center fw-bold text-success">
                                    <?= (!empty($data['pan_number'])) ? htmlspecialchars($data['pan_number']) : '--'; ?>
                                </td>
                                <td class="text-center"><?= htmlspecialchars($data['date']); ?></td>
                                <td class="text-center">₹<?= htmlspecialchars($data['amount']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $badge; ?>"><?= $status; ?></span>
                                </td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-info btn-sm check-status-btn" 
                                            data-tracking-id="<?= htmlspecialchars($data['order_id']); ?>"
                                            id="btn-<?= $data['id']; ?>"> 
                                       <i class="fas fa-sync-alt"></i> Check Status
                                    </button>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>No Records Found</td></tr>";
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
    const statusButtons = document.querySelectorAll('.check-status-btn');
    
    async function checkStatus(trackingId, buttonElement) {
        if (buttonElement.disabled) return;
        
        buttonElement.disabled = true;
        const originalHtml = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
        
        try {
            Swal.fire({
                title: 'Checking Status...',
                text: 'Please wait while we fetch your PAN details.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            // 1. Call External API
            const response = await fetch(`https://apiadda.in/api_menual/pan_status.php?order_id=${encodeURIComponent(trackingId)}`);
            
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            
            const data = await response.json();
            
            let dbStatus = 'pending'; 
            let panNumber = ''; 
            let displayTitle = 'Status Update';
            let displayIcon = 'info';
            let displayText = data.message || 'No details found.';

            // 2. Parse Response
            if (data.status === true) {
                // If PAN found in response
                if(data.pan_number){
                    dbStatus = 'success';
                    panNumber = data.pan_number;
                    displayTitle = 'PAN Found! ✅';
                    displayIcon = 'success';
                    displayText = `PAN Number: ${panNumber}. Details saved successfully.`;
                } else {
                    // Status true but no pan (rare case, usually pending)
                    dbStatus = 'pending';
                    displayTitle = 'Processing...';
                    displayIcon = 'info';
                }
            } else {
                // Status false (Pending or Failed)
                if(data.message && data.message.toLowerCase().includes('not found')){
                    dbStatus = 'failed';
                    displayTitle = 'Not Found ❌';
                    displayIcon = 'error';
                } else {
                    dbStatus = 'pending'; // Still pending
                    displayIcon = 'warning';
                }
            }

            // 3. Update Local Database (Status + PAN Number)
            const formData = new URLSearchParams();
            formData.append('order_id', trackingId);
            formData.append('status', dbStatus);
            formData.append('pan_number', panNumber);

            await fetch('update_pan_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });

            // 4. Show Result and Reload
            Swal.fire({
                title: displayTitle,
                text: displayText,
                icon: displayIcon,
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6'
            }).then((result) => {
                window.location.reload();
            });

        } catch (error) {
            Swal.close();
            Swal.fire({
                title: 'Connection Error',
                text: 'Could not connect to server. Please try again.',
                icon: 'error',
                confirmButtonText: 'Try Again'
            });
        } finally {
             // Safety re-enable
             // buttonElement.disabled = false; 
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