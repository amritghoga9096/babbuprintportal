<?php 
	include('header.php');
    if(checkAdmin($udata['type'] == true)){
        if(isset($_GET['apid']) && $_GET['approve'] == 1 ){
            $apid = base64_decode(mysqli_real_escape_string($ahk_conn,$_GET['apid']));
           $select = mysqli_query($ahk_conn,"SELECT * FROM wallet WHERE txn_id='$apid' AND status='pending'");
           if(mysqli_num_rows($select)==1){
           $data = mysqli_fetch_assoc($select);
           $amt = $data['amount'];
           $pupdate =  mysqli_query($ahk_conn,"UPDATE wallet SET status='success' WHERE txn_id='$apid' ");
           $update = mysqli_query($ahk_conn,"UPDATE users SET balance=balance+$amt WHERE phone='".$data['phone']."'");
           if($update && $pupdate){
               if (!empty($data['phone']) && function_exists('gpGiveReferralBonusIfEligible')) {
                   gpGiveReferralBonusIfEligible($data['phone'], $ahk_conn);
               }
               ?>
                <script>
                   $(function(){
                       Swal.fire(
                           'Balance Added Successfully', 
                           '',
                           'success'
                       )
                   })
                   setTimeout(() => {
                       window.location='qrpay_pending.php';
                   }, 1000);
                   </script>
               <?php
           }else{
               ?>
                <script>
                    $(function(){
                        Swal.fire(
                            'Not Added', 
                            '',
                            'error'
                        )
                    })
                    </script>
                    <?php
           }
           }
       }else if(isset($_GET['rejid']) && $_GET['reject'] == 1 ){
           $apid = base64_decode(mysqli_real_escape_string($ahk_conn,$_GET['rejid']));
          $select = mysqli_query($ahk_conn,"SELECT * FROM wallet WHERE txn_id='$apid' ");
          if(mysqli_num_rows($select)==1){
          $data = mysqli_fetch_assoc($select);
          $update = mysqli_query($ahk_conn,"UPDATE wallet SET status='rejected' WHERE txn_id='$apid'");
          if($update){
              ?>
               <script>
                  $(function(){
                      Swal.fire(
                          'Reject Successfully', 
                          '',
                          'success'
                      )
                  })
                  setTimeout(() => {
                      window.location='qrpay_pending.php';
                  }, 1000);
                  </script>
              <?php
          }else{
              ?>
                      <script>
                          $(function(){
                              Swal.fire(
                                  'Something Wrong', 
                                  '',
                                  'error'
                              )
                          })
                        
                      </script>
                      <?php
          }
          }
       }
    //    
    }else{
        header("Location: index.php");
        die();
    }
   ?>
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="mb-0">All QR pending Payments</h5>
                    </div>
                   
                </div>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">SL.</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Order id</th>
                                <th class="text-center">Username</th>
                                <th class="text-center">Amount</th>
                                <th class="text-center">UTR NO/ TXN no</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                           
<?php
$res = mysqli_query($ahk_conn,"SELECT * FROM wallet
INNER JOIN users
ON wallet.phone = users.phone
WHERE wallet.PAYMENTMODE= 'QR' AND wallet.status = 'pending' 
ORDER by wallet.id DESC");
if(mysqli_num_rows($res)>0){
    $x=0;
    while($data = mysqli_fetch_assoc($res)){

        $x ++;
        ?>
        <tr>
            <td class="text-center"><?= $x;?></td>
            <td class="text-center"><?= $data['name'];?></td>
            <td class="text-center">Order Id: <?= $data['txn_id'];?> <br>Phone: <?= $data['phone'];?> <br>Email: <?= $data['email'];?></td>
            <td class="text-center"><?= $data['email'];?></td>
            <td class="text-center">
                  <div class="ms-2">
                        <h6 class="mb-1 font-14"><?php echo strtoupper($data['amount']); ?></h6>
                    </div>
            </td>
        
            <td class="text-center"><?php 
            if($data['BANKTXNID'] !=NULL){
                echo strtoupper($data['BANKTXNID']); 
            }else{
                echo "TXN not Provided or fake TXN";
            }
            ?></td>
            
            <td  class="text-center">
                <div class="mr-12 font-24 ">
                    
                    <a class="text-success bg-light-success" title="Approve Payment" href="?apid=<?php echo base64_encode($data['txn_id']); ?>&approve=1"><i class='bx bx-check'></i></a>
                    <a class="text-danger bg-light-danger" title="Reject Payment" href="?rejid=<?php echo base64_encode($data['txn_id']); ?>&reject=1"><i class='bx bx-x'></i></a>
                </div>
                
            </td>
        </tr>
        <?php
       
    }
}
?>
                          
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
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
            $('#aadh').inputmask();
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