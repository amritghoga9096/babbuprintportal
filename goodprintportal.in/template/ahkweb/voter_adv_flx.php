<?php
include('header.php');
?>
 <?php
  $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='voter_manual_flx' "));
                  $fee = $price['price'];
$balance = $udata['balance'];
if ($balance < $fee) {?>
    <script>
                    $(function(){
                        Swal.fire(
                            'Wallet amount is Low Please Maintain Wallet Above From Fee ',
                            '',
                            'error'
                        )
                    })
                    setTimeout(() => {
                        window.location='wallet';
                    }, 1000);
                </script>
<?php }?>
     	<!--start page wrapper -->
	<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Advance</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="voter_adv_flx_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Print List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

				<!--end breadcrumb-->
			 <div class="container-fluid pt-4 px-4">
                <div class="row">
                    <!-- Left Side: Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                               <h3 class="card-title text-dark text-center"><strong>Voter Advance & Manual</strong></h3>
                            </div>
                            <div class="card-body">
                                <a href="vot_org_instant" class="btn btn-dark w-100 mb-3">Voter Oringnal PDF Download Here</a>
	<script type="text/javascript">
     function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $('#blah1')
                        .attr('value', e.target.result);
                };

                reader.readAsDataURL(input.files[0]);
            }
        }
</script>
                             	<form method="post" name="form" autocomplete="off"  enctype="multipart/form-data" action="voter_info_flx" style="width:100%">
									   <div class="row dgnform">
								       <div class="col-md-13 col-sm-12 col-xs-12">
											<div class="row">
                                                 <div class="form-group mb-3">
                                                    <label>Epic Number</label>
                                                    <div class="form-group">
                                                        <input class="form-control" name="epicno"  type="text" placeholder="Enter Your Epic Number" required="">
                                                    </div>
                                                </div>
                                              </div>
                                              
                                                <style>
                                                		.image-preview__image{
                                                  			max-width:110px;
                                                  			min-height: 110px;
                                                		}
                                                			.image-preview{
                                                        			width: 110px;
                                                        			min-height: 110px;
                                                        			border: 2px solid #ddd;
                                                        			margin-top: 5px;

                                                        			display: flex;
                                                        			align-items: center;
                                                        			justify-content: center;
                                                        			font-weight: bold;
                                                        			color: #ccc;
                                                			}
                                                		.image-preview__image{
                                                        		//display: none;
                                                        		width: 100%;
                                                		}
                                        		</style>
                                        	
						
                                                    <div class="row">
                                                       <div class="col-sm-5 col-xs-20">
                                                    	</div>
						                              </div>
						                              <br>
                                                   <label>Upload Photo </label>
                                                        <input type='file' id="filedata" name="" onchange="readURL(this);" />
                                                        <input class="form-control" id="blah1" name="filedata" type="hidden" value="" require>
											     	</div>
							                   
								          
								          		
								          	</div>
											    	<label>&nbsp;</label>
											     	<div class="form-group">              
												   <input type="submit" id="submit" name="submit" class="btn btn-primary w-100" style="" value="Submit" </input>
                                                	<div id="result"></div>
                                               <br><br>
                                                 <div class="col-12 ml-2 d-flex justify-content-center">
    <div class="fee-container text-center">
        <h5 class="text-warning">Application Fee: 
            <?php  
                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='voter_manual_flx'")); 
                echo "₹" . $price['price'];
            ?>
        </h5>
    </div>
</div>

<style>
    .fee-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 15px;
        max-width: 300px;
        width: 100%;
        margin-top: 10px;
        margin-bottom: 10px;
    }

    .fee-container h5 {
        color: #ff9800; /* Bright yellow/orange color for emphasis */
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }
</style>

                                               


	
	<script>
	
	$(document).ready(
    function(){
        $('input:file').change(
            function(){
                if ($(this).val()) {
                    $('input:submit').attr('disabled',false); 
                } 
            }
            );
    });
	</script> 
	</div> 
	
                                            <input type="hidden" id="deviceport" />
                                       
						
	<!--end page wrapper -->
		<?php 
		include('footer.php');
		?>
	<!-- Bootstrap JS -->
	

	<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
	<!--plugins-->
	<!-- <script src="../template/ahkweb/assets/js/jquery.min.js"></script> -->
	<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
	<!--app JS-->
	<script src="../template/ahkweb/assets/js/app.js"></script>
</body>

<script>
	$(document).ready(function() {
	
	$('#eid').inputmask();
	$('#date').inputmask();
	$('#pan_no').inputmask();
	$('#timea').inputmask("hh:mm:ss", {
        placeholder: "00:00:00", 
        insertMode: false, 
        showMaskOnHover: false,
        hourFormat: 12
      });
	});
</script>
</html>