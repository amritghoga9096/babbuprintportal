<?php 
	include('header.php');
   ?>
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">IPPB Passbook List</li>
                    </ol>
                </nav>
            </div> 
            <div class="btn-group">
                <a href="Ippb_pass_legend" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> IPPB Passbook
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
           <div class="main-content">
                <section class="section">
                    <div class="card-header bg-dark">
                        <div class="card-title">
                            <h3 style="color:white"><strong>INDIA POST DIGITAL PASSBOOK PRINT LIST <sup style="color:red"><B>HD</B></sup></strong></h3>
                        </div>
                    </div>
                   
                </div>
                <hr>
        <div class="card-content collpase show">
          <div class="card-body card-dashboard table-responsive">
             
           	<table id="ulist" class="table table-striped table-bordered zero-configuration" style="font-size:12px;">
									<tr style="background:linear-gradient(-62deg, #3a3a53, #03a9f4) !important;">
										<td style="color:#fff" align="left" valign="left"> Sn.No.          </td>
										<td style="color:#fff" align="left" valign="left"> Holder Name    </td>
										<td style="color:#fff" align="left" valign="left"> Account Number   </td>
										<td style="color:#fff" align="left" valign="left"> IFSC Code           </td>
										<td style="color:#fff" align="left" valign="left"> DATE & TIME     </td>
					                       <?php if($_SESSION['userid'] == '1') {?>
				                     	<td align="middle" valign="middle" style="color:#fff"><B>   Edit Admin  </B></td>
				                     	<?php } ?>
										<td style="color:#fff" align="middle" valign="middle">   Print     </td>
										<td style="color:#fff" align="middle" valign="middle">   Delete    </td>
									</tr>
									
								<?php
								if($_SESSION['userid']==1)
								    
								    $sql = "SELECT * FROM `postpass` order by id desc";
								else
								    $sql = "SELECT * FROM `postpass` WHERE userid='" . $_SESSION['userid'] . "' order by id desc";
								$a = mysqli_query($ahk_conn, $sql);
								$x = 0; ?>
								<?php while ($b = mysqli_fetch_array($a)) {
									$x++; $date = date_create($b['create_time']); ?>
                   
                                          <tr>
										<td align="left" valign="left"> <?=$x?> </td>
										<td align="left" valign="left"> <?=$b['name']?> </td>
										<td align="left" valign="left"> <?=$b['account']?> </td>
										<td align="left" valign="left"> <?=$b['ifsc']?> </td>
										<td align="left" valign="left"> <?=date_format($date, 'j M Y g:ia')?></td>
					                       <?php if($_SESSION['userid'] == '1') {?>
								 <td>
											<form action="postedit.php?id=<?php echo $b['id']?>" method="post" enctype="multipart/form-data" >
												<input name="userid" type="hidden" value="" />
												<input class="btn btn-raised btn-info round btn-min-width mr-1 mb-1" style="margin-top:2px;margin-bottom:2px;padding-top:2px;padding-bottom:2px;" type="submit" value="Edit" />
											</form>
										   </td>
										   <?php } ?>
										<?php
									
										if($b['payment_status']=='0')
										{
										    ?>
										    
										<td align="center" valign="middle">
										   <form action="postpay/index.php?id=<?php echo $b['id'];?>&amount=<?php echo $amt['amount'];?>" method="post">
										   <input type="hidden"  name="custId" value="<?php echo $b['id'];  ?>">
										     <input type="submit" name ="sub_val" style="margin-top:2px;margin-bottom:2px;padding-top:2px;padding-bottom:2px;"  class="btn btn-warning"value="PAY NOW"></form></td>
										<?php
										}
										else
										{
										    ?>
										<td align="center" valign="middle"> <a style="margin-top:2px;margin-bottom:2px;padding-top:2px;padding-bottom:2px;"  class="btn btn-success active " style="margin-top:2px;margin-bottom:2px;padding-top:2px;padding-bottom:2px;" href="passbook/post.php?searchid=<?php echo $b['id']?>" target="_blank"><i class="fa fa-print" style="color:black"></i> Print </a>
										</td>
										<?php } ?>
											<td align="center" valign="middle">
												<form action="active-post.php" method="post" enctype="multipart/form-data" >
													<input name="id" type="hidden" value="<?=$b['id']?>" />
													<input style="margin-top:2px;margin-bottom:2px;padding-top:2px;padding-bottom:2px;" class="btn btn-danger" type="submit"  value="Delete" />
											</form>
										</td>
									</tr>
									<?php } ?>
								</table>
								 </div>
								<div class="clearfix"></div>
							 </div>
						</section>
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