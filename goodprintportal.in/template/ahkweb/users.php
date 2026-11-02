<?php 
session_start(); // Must be the first line in the script
	include('header.php');
    if(checkAdmin($udata['type'] == true)){
        if(isset($_GET['deleteuser']) && $_GET['deleteuser'] ==1 && $_GET['id'] !=''){
            $id= base64_decode(mysqli_real_escape_string($ahk_conn,$_GET['id']));
            $delete = mysqli_query($ahk_conn,"DELETE FROM users WHERE id='$id'");
            if($delete){
                showAlert('User Deleted Successfully');
                ahkRedirect('users.php',1200);
            }else{
    
            }
        }
        if(isset($_GET['banid']) && $_GET['ban']=='1'){
            $banid = base64_decode(getSafe($_GET['banid']));
            if(ahkQuery("UPDATE users SET status='0' WHERE id='$banid'")){
                showAlert('User Banned Successfully');
                ahkRedirect('users.php',1200);
            }
        }
        if(isset($_GET['apid']) && $_GET['approve']=='1'){
            $apid = base64_decode(getSafe($_GET['apid']));
            if(ahkQuery("UPDATE users SET status='1' WHERE id='$apid'")){
                showAlert('User Activated Successfully');
                ahkRedirect('users.php',1200);
            }
        }
    }else{
        header("Location: index");
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
                        <h5 class="mb-0">All Users List</h5>
                    </div>
                   
                </div>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">SL.</th>
                                <th class="text-center">Username</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Email</th>
                                <th class="text-center">Type</th>
                                <th class="text-center">Balance</th>
                               
                                
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                           
<?php
$res = mysqli_query($ahk_conn,"SELECT * FROM users ORDER BY id DESC");
if(mysqli_num_rows($res)>0){
    $x=0;
    while($data = mysqli_fetch_assoc($res)){
        if($data['type'] == 'admin'){
            continue;
        }
        $x ++;
        ?>
        <tr>
            <td class="text-center"><?= $x;?></td>
            <td class="text-center"><?= $data['phone'];?></td>
            <td class="text-center"><?= $data['name'];?></td>
            <td class="text-center"><?= $data['email'];?></td>
            <td class="text-center">
                  <div class="ms-2">
                        <h6 class="mb-1 font-14"><?php echo strtoupper($data['type']); ?></h6>
                    </div>
            </td>
        
            <td class="text-center"><?php 
            if($data['balance']!=NULL){
                echo strtoupper($data['balance']); 
            }else{
                echo "0.00";
            }
            ?>
             <form action="BalanceTransfer.php" method="POST">
                        <input type="hidden" name="username" value="<?php echo $data['email']; ?>">
                        <button class="btn btn-success" type="submit">Balance Transfer</button>
                        
                    </form></td>
            
            <td  class="text-center">
                <div class="mr-12 font-24 ">
                   
                    <a class="text-success bg-light-success" title="Login As User" href="users.php?phone=<?php echo base64_encode($data['phone']); ?>&login=1"><img height="50px" src="../template/ahkweb/assets/login-rounded-right.png"></a>
                    <a class="text-success bg-light-success" title="Reset Password" href="users.php?id=<?php echo base64_encode($data['id']); ?>&reset=1"><i class='bx bx-refresh'></i></a>
                    <a class="text-success bg-light-success" href="EditUser.php?id=<?php echo  base64_encode($data['id']); ?>"><i class='bx bx-pencil'></i></a>
                    <?php
                    if($data['status']== '0'){
                        ?>
                        <a class="text-success bg-light-success" title="Approve User" href="?apid=<?php echo base64_encode($data['id']); ?>&approve=1"><i class='bx bx-check'></i></a>
                        <?php 
                    }else{
                        ?>
                        <a class="text-danger bg-light-danger" title="Bann User" href="?banid=<?php echo base64_encode($data['id']); ?>&ban=1"><i class='bx bx-x'></i></a>
                        <?php 
                    }
                    ?>
                    
                    <a class="text-danger bg-light-danger" href="?deleteuser=1&id=<?php echo base64_encode($data['id']); ?>"><i class='bx bx-trash'></i></a>
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