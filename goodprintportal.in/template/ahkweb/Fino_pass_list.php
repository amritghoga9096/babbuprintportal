<?php 
	include(__DIR__ . '/header.php');
	$dbname_fino = 'goodprintportal_good';
	if(!empty($GLOBALS['ahk_conn']) || !empty($ahk_conn)){
	    $_fconn = !empty($ahk_conn) ? $ahk_conn : $GLOBALS['ahk_conn'];
	    @mysqli_select_db($_fconn, $dbname_fino);
	}
   ?>
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Fino Passbook List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="Fino_pass_legend" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Fino Passbook
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
                            <h3 style="color:white"><strong>FINO DIGITAL PASSBOOK PRINT LIST <sup style="color:red"><B>HD</B></sup></strong></h3>
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
										<td style="color:#fff" align="left" valign="left"> Father Name   </td>	
										<td style="color:#fff" align="left" valign="left"> DATE & TIME     </td>
				                     	<?php if($_isAdminFino) {?>
				                     	<td align="middle" valign="middle" style="color:#fff"><B>   Edit Admin  </B></td>
				                     	<?php } ?>
										<td style="color:#fff" align="middle" valign="middle">   Print     </td>
										<td style="color:#fff" align="middle" valign="middle">   Delete    </td>
									</tr>
									
									<?php
								$_isAdminFino = false;
								if(isset($_SESSION['userid']) && (string)$_SESSION['userid'] === '1') $_isAdminFino = true;
								elseif(isset($_SESSION['userid']) && $_SESSION['userid'] == 1) $_isAdminFino = true;
								if(!$_isAdminFino && function_exists('checkAdmin') && isset($udata) && is_array($udata)){ 
								    if(checkAdmin(isset($udata['type']) ? $udata['type'] : '')) $_isAdminFino = true;
								    if(checkAdmin($udata)) $_isAdminFino = true;
								}
								if(!$_isAdminFino && isset($udata['type']) && (string)$udata['type'] === '1') $_isAdminFino = true;

								if($_isAdminFino)
								    
								    $sql = "SELECT * FROM `passbook` order by id desc";
								else
								    $_listUid = (isset($udata['phone']) && $udata['phone']!=='' && $udata['phone']!==null) ? (string)$udata['phone'] : (isset($_SESSION['userid']) ? (string)$_SESSION['userid'] : '0');
								    $_sessUid = isset($_SESSION['userid']) ? (string)$_SESSION['userid'] : '0';
								    $_udataId  = (isset($udata['id']) && $udata['id']!=='') ? (string)$udata['id'] : 'NONE_XYZ_NONE';
								    $_uidPhone = mysqli_real_escape_string($ahk_conn, $_listUid);
								    $_uidSess  = mysqli_real_escape_string($ahk_conn, $_sessUid);
								    $_uidDataId = mysqli_real_escape_string($ahk_conn, $_udataId);
								    $sql = "SELECT * FROM `passbook` WHERE userid='{$_uidPhone}' OR userid='{$_uidSess}' OR userid='{$_uidDataId}' OR userid='0' OR userid='' OR userid IS NULL order by id desc";
								$a = mysqli_query($ahk_conn, $sql);
								$x = 0;
								$_amtPay = '10';
								$_pR = @mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='Fino_pass_fee'"));
								if(isset($_pR['price']) && $_pR['price']!=='') $_amtPay = $_pR['price'];
								?>
								<?php while ($b = mysqli_fetch_array($a)) {
									$x++;
									$_rawDt = (isset($b['create_time']) && !empty($b['create_time']) && $b['create_time']!=='0000-00-00 00:00:00') ? $b['create_time'] : (isset($b['date']) ? $b['date'] : null);
									$_dt = @date_create($_rawDt);
									$_dateStr = '-';
									if(is_object($_dt)){ $_dateStr = date_format($_dt,'j M Y g:ia'); }
									?>
                   
                                          <tr>
										<td align="left" valign="left"> <?=$x?> </td>
										<td align="left" valign="left"> <?=$b['name']?> </td>
										<td align="left" valign="left"> <?=$b['account']?> </td>
										<td align="left" valign="left"> <?=$b['ifsc']?> </td>
										<td align="left" valign="left"> <?=$b['fathername']?> </td>
										<td align="left" valign="left"> <?=$_dateStr?></td>
				                	<?php if($_isAdminFino) {?> 
								 <td>
											<form action="finoedit.php?id=<?php echo $b['id']?>" method="post" enctype="multipart/form-data" >
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
										   <form action="finopay/index.php?id=<?php echo $b['id'];?>&amount=<?php echo $_amtPay;?>" method="post">
										   <input type="hidden"  name="custId" value="<?php echo $b['id'];  ?>">
										     <input type="submit" name ="sub_val" style="margin-top:2px;margin-bottom:2px;padding-top:2px;padding-bottom:2px;"  class="btn btn-warning"value="PAY NOW"></form></td>
										<?php
										}
										else
										{
										    ?>
										<td align="center" valign="middle"> <a style="margin-top:2px;margin-bottom:2px;padding-top:2px;padding-bottom:2px;"  class="btn btn-success active " style="margin-top:2px;margin-bottom:2px;padding-top:2px;padding-bottom:2px;" href="passbook/fino.php?searchid=<?php echo $b['id']?>" target="_blank"><i class="fa fa-print" style="color:black"></i> Print </a>
										</td>
										<?php } ?>
											<td align="center" valign="middle">
												<form action="active-fino.php" method="post" enctype="multipart/form-data" >
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