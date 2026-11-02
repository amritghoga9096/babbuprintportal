<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
?>
<!--start page wrapper -->
<div class="page-wrapper">
<div class="page-content">
    <div class="card radius-10">
        <div class="card-body">
            <h5 class="mb-0">MCD BIRTH LIST</h5>
            <hr>
            <div class="table-responsive">
                <table id="example2" class="table align-middle mb-0">
                     <thead style="color:white;background:linear-gradient(-62deg, #3a3a53, #03a9f4);">
                                                <tr>
                                                    <th style="color:white; text-align:center;">#</th>
                                                    <th style="color:white">Name</th>
                                                      <th style="color:white">Father Name</th>
                                                       <th style="color:white">Date of Birth</th>
                                                       <th style="color:white">Date</th>
                                                     <th style="color:white; text-align:center;">Print</th>
                                                     
                                                    
                                                </tr>
                                            </thead>
                   <tbody>
                                                 <?php
                                                $sql = mysqli_query($ahk_conn, "SELECT * FROM mcd_birth_fireapi WHERE username='".$udata['phone']."' order by id DESC LIMIT 10");
                                                if (mysqli_num_rows($sql) > 0) {
                                                    $slno = 1;
                                                    while ($data = mysqli_fetch_assoc($sql)) {
                                                ?>
                                                        <tr>
                                                            <td><?php echo $slno; ?></td>
                                                             <td><?php echo $data['name']; ?></td>
                                                              <td><?php echo $data['fname']; ?></td>
                                                              <td><?php echo $data['dob']; ?></td>
                                                              <td><?php echo $data['date']; ?></td>
                                                             <td>
    <button onclick="openBase64PDF('<?php echo $data['pdf']; ?>')"
        class="btn btn-warning btn-sm ml-2">
        <i class="fa fa-print"></i> Print
    </button>
</td>
                                                          
                                                             
                                                            
                                                        </tr>
                                                <?php
                                                        $slno++;
                                                    }
                                                }
                                                ?>
                                            </tbody>
                  </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
<!--end page wrapper -->
<?php include('../template/ahkweb/footer.php'); ?>

<!-- Bootstrap & Plugins JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script>
function openBase64PDF(base64Data) {

    // convert base64 → blob
    const byteCharacters = atob(base64Data.split(',')[1]);
    const byteNumbers = new Array(byteCharacters.length);

    for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
    }

    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], {type: 'application/pdf'});

    const blobUrl = URL.createObjectURL(blob);

    window.open(blobUrl, '_blank');
}
</script>
<!-- Datatables -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.buttons.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/buttons.bootstrap5.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jszip.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/pdfmake.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/vfs_fonts.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/buttons.html5.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/buttons.print.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function() {
    $('#example2').DataTable({
        lengthChange: false,
        buttons: [ 'copy', 'excel', 'pdf', 'print', 'colvis' ]
    }).buttons().container().appendTo('#example2_wrapper .col-md-6:eq(0)');
});
</script>
