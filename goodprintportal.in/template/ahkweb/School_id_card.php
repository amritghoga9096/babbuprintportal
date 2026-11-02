<?php
include('header.php');
?>

	<!--start page wrapper -->
		<div class="page-wrapper">
			<div class="page-content">
				<!--breadcrumb-->
				<div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
					<div class="breadcrumb-title pe-3">Home </div>
					<div class="ps-3">
						<nav aria-label="breadcrumb">
							<ol class="breadcrumb mb-0 p-0">
								<li class="breadcrumb-item"><a href="index"><i class="bx bx-home-alt"></i></a>
								</li>
								<li class="breadcrumb-item active" aria-current="page">School Id Service</li>
							</ol>
						</nav>
					</div>
					<div class="ms-auto">
						<div class="btn-group">
						
							</div>
						</div>
					</div>
				</div>
				<!--end breadcrumb-->
			
            <div class="container-fluid pt-4 px-4">
                <div class="row">
                    <!-- Left Side: Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                               <h3 class="card-title text-dark text-center"><strong>School Id Card Fill In The Box</strong></h3>
                            </div>
                            <div class="card-body">
                              
	<?php if($msg !='') { ?>
									<div style="width=100%"  class="row cvmsgok"><?php echo $msg; ?></div>
								<?php } elseif($msgno !='') { ?>
									<div style="width=100%"  class="row cvmsgno"><?php echo $msgno; ?></div>
								<?php  } ?>

								<form method="post" autocomplete="off"  onSubmit="return validation();"   enctype="multipart/form-data" action="" style="width:100%">
									<div class="form-group mb-3">
                                                        
                                                    <div class="col-sm-12">
                                                        <label>Class.</label>
                                                        <div class="form-group">
                                                            <input class="form-control stylec" id="normaltext" name="normaltext" type="text" placeholder="Enter Student Class Details" autocomplete="off" required>
                                                            <span id="erroraadharno" class="error"></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-12">
                                                        <label>Name</label>
                                                        <div class="form-group">
                                                            <input class="form-control stylec" value="<?php echo $json['name'];?>" id="name" placeholder="Student Name" name="name"    type="text" required onkeyup="this.value = this.value.toUpperCase();" >
															
															
															
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-12">
                                                        <label>Father Name </label>
                                                        <div class="form-group">

                                                           <input class="form-control stylec" name="fathername" id="fathername" placeholder="Student Father Name" value="<?php echo $json['fathername'];?>" type="text">
                                                        </div>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label>Mobile Number</label>
                                                        <div class="form-group">
                                                           <input class="form-control stylec" name="mobile" type="tel" placeholder="Enter 10-digit mobile number" 
       maxlength="10" pattern="[0-9]{10}" required 
       oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                                            
                                                        </div>
                                                    </div>
                                                  <div class="form-group mb-3">
    <label>Address</label>
    <input type="text" name="address" class="form-control stylec" placeholder="Enter your address" required>
</div>

                                                    </div>
										<div class="form-group mb-3">
                                            <label id="stype">Select Image   </label>
                                            <div class="form-group">
											  <input type="file" name="imagefile" class="form-control stylec" id="imgInp" required/>
                                              <img src=""   id="blah" width="100px" height="100px" style="margin-top: 12px;
    box-shadow: 4px 4px 2px 1px;
    border-radius: 10px;"/>
                                              <input type="hidden" name="blahin" id="blahin" value="" class="form-control"/>
                                            </div>
                                        </div>
                                    
                                        </div>
                                        <div class="form-group mb-3">
                                           
                                                <label>&nbsp;</label>
                                                <div class="text-center">              
                                                <button type="submit" name="savedata" class="btn btn-success btn-block" style="box-shadow: 0 0 0 3px rgba(40,167,69,.5);">Submit</button> 
                                                <div class="col-12 ml-2 d-flex justify-content-center">
    <div class="fee-container text-center">
        <h5 class="text-warning">Application Fee: 
            <?php  
                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='instant_pan_manual_fee'")); 
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

                                                </div> 
                                            </div>
                                        </div>
									</div>
								</form>
							</div>
							<!-- /# row -->
						</section>
							<style>
								.stylec
								{
								    box-shadow: 2px 3px #000 !important;
    border-radius: 50px !important;
								}
								</style>
					</div>
				</div>
            </div>
        </div>

        <script type="text/javascript">
			function validation() {
				
				var aadharno = document.getElementById('pannumber').value;
				if ( aadharno.length < 10 ) {
					 document.getElementById('erroraadharno').innerHTML = " **Please Enter 10 Digit Pan Card Number !!!";
					 document.getElementById('pannumber').style.border = "1px solid red";
					 document.getElementById('pannumber').focus();
					 return false;
				}

               
                
                
				
			}
			
		
        </script>
	         
<script type="text/javascript">
//English to hindi translate code
    
//Words and Characters Count
function readURL(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();

        reader.onload = function (e) {
            $('#blah').attr('src', e.target.result);
            $('#blahin').val(e.target.result);
        }

        reader.readAsDataURL(input.files[0]);
    }
}
$("#blah").hide();
$("#imgInp").change(function(){
    readURL(this);
	$("#blah").show();
});	


function readURLs(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();

        reader.onload = function (e) {
            $('#blahs').attr('src', e.target.result);
            $('#blahsin').val(e.target.result);
        }

        reader.readAsDataURL(input.files[0]);
    }
}
$("#blahs").hide();
$("#signInp").change(function(){
    readURLs(this);
	$("#blahs").show();
});	
									
</script>



<div id="myModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      
      <div class="modal-body">
        <img src="<?php echo $slct['pafile'];?>" width="<?php echo $slct['iwidth'];?>" height="<?php echo $slct['iheight'];?>"/>
      </div>
     
    </div>
<button type="button" class="btn btn-default" data-dismiss="modal" style="    position: absolute;
    top: -20px;
    right: -422px;
    border-radius: 50%;
    background: red;
	border-color:red;
    color: white;">X</button>
  </div>
</div>

<div id="myModals" class="modal fade" role="dialog" data-backdrop="static" data-keyboard="false" style="background-color: rgba(0, 0, 0, 0.7);">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content" style="     height: 352px;
    width: 727px;
    margin-left: 165px;
">
      
      <div class="modal-body" style="height: 243px;">
	 <img src="abs.jpg" height="200px" width="100%">
	<form method="post" action="paytmv/index.php">
									<div class="">
									
									 <p style="    font-size: 18px;
    text-align: center;
    margin-top:20px;
    ">You Not Authorized For using This Pan Services Please Buy Services.</p>
									    <div class="row col-md-12 col-sm-12 col-xs-12">
											<div class="col-md-3 col-sm-3 col-xs-6">
											<?php
												error_reporting(0);
												include("config.php");

												
												//$slct = mysqli_fetch_assoc($r);
												//$slct['aadharpoint'];
                                       $c = "select * from user_data where phone=".$_SESSION['phone']."";
									   $updts = mysqli_query($ahk_conn,$c) ;
												$slcts = mysqli_fetch_array($updts);
												?>
												
												
												
                                    
													
												
												</div> 
											</div>
											
											<div class="col-md-12 col-sm-4 col-xs-6">
												
												<div class="form-group"> 
									
<input type="hidden" name="service[]" value="1"/> 


	
												<span id="erroruserid" class="error"></span>  
												</div> 
											</div>										
											
											<div class="col-md-12 col-sm-4 col-xs-6">
												
												<div class="form-group">              
												<input autocomplete="off" type="hidden" class="form-control"  id="Pay_Amt" value="500" name="Pay_Amt" placeholder="Website URL/ Link" readonly >
												<input type="hidden" value="<?php echo $_SESSION['phone'];?>" name="pay_uid"/>
												<span id="erroruserid" class="error"></span>  
												</div> 
											</div>										
											
															
													
																						
												
												
										
										
												
												
												
												
												
												
										</div> 
											</table>											
										</div>										
										<div class="col-md-12 col-sm-4 col-xs-6">
											<label>&nbsp;</label>
											<div class="form-group"> 
                                                   											
											   
											   <div class="col-md-6"><button type="submit" id="submit" name="submit" style="padding:17px;background:orange;border:1px solid orange;" class="btn btn-success btn-block"> &nbsp;&nbsp;&nbsp; Buy Now &nbsp;&nbsp;&nbsp;</button> </div><div class="col-md-6"><a href="panel.php" style="padding:17px;background:orange;border:1px solid orange;    margin-top: -65px;
    margin-left: 343px;" class="btn btn-success btn-block"> &nbsp;&nbsp;&nbsp; Dashboard &nbsp;&nbsp;&nbsp;</a></div>
											</div> 
										</div>
									</div>
								</form>
      </div>
      
        
      
    </div>
	
	
<style>
.modal-body
{
	flex:inherit !important;
	padding:inherit !important;
}
.modal-footer
{
	border:none !important;
}
.modal-dialog {
	margin: 30px 248px auto !important;
    max-width: auto !important;
	width : 100% !important;
}
</style>

  
 

<?php include('userFooter.php'); ?>

		<!--[if lt IE 9]>
			<link rel="stylesheet" type="text/css" href="DateTimePicker-ltie9.css" />
			<script type="text/javascript" src="DateTimePicker-ltie9.js"></script>
		<![endif]-->
<div id="dtBox"></div>
		<script>
					    $("#ptype").on('change',function()
					    {
					       if($(this).val() == 'UTI-MINOR' || $(this).val() == 'NSDL-MINOR') 
					       {
					           $("#signInp").hide();
					           
					           $("#simgs").hide();
					       }
					       else 
					       {
					           $("#signInp").show();
					           $("#imgInp").show();
					           $("#stype").show();
					           $("#simgs").show();
					       }
					    });
					</script>
					
					<script type="text/javascript">
function ValidatePAN()
{
	 var pan_no = document.getElementById("pannumber");
	
 if (pan_no.value != "") {
            PanNo = pan_no.value;
            var panPattern = /^([a-zA-Z]{5})(\d{4})([a-zA-Z]{1})$/;
            if (PanNo.search(panPattern) == -1) {
                alert("Invalid Pan No");
                pan_no.focus();
                pan_no.value='';
                return false;
                
            }
           
          
        }
}

</script>
								
								
								
								
		
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
	$('#timea').inputmask("hh:mm:ss", {
        placeholder: "00:00:00", 
        insertMode: false, 
        showMaskOnHover: false,
        // hourFormat: 24
      });
	});
</script>
</html>