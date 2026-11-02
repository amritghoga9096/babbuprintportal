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
								<li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
								</li>
								<li class="breadcrumb-item active" aria-current="page">New APPLY</li>
							</ol>
						</nav>
					</div>
					<div class="ms-auto">
						<div class="btn-group">
							<button type="button" class="btn btn-primary">Settings</button>
							<button type="button" class="btn btn-primary split-bg-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">	<span class="visually-hidden">Toggle Dropdown</span>
							</button>
							<div class="dropdown-menu dropdown-menu-right dropdown-menu-lg-end">	<a class="dropdown-item" href="javascript:;">Action</a>
								<a class="dropdown-item" href="javascript:;">Another action</a>
								<a class="dropdown-item" href="javascript:;">Something else here</a>
								<div class="dropdown-divider"></div>	<a class="dropdown-item" href="javascript:;">Separated link</a>
							</div>
						</div>
					</div>
				</div>
				<!--end breadcrumb-->
				<div class="row">
					<div class="col-xl-10 mx-auto">
						<h6 class="mb-0 text-uppercase">Aadhar Manaual</h6>
						<hr/>
						  <div class="main-content">
                            <div class="section-header">
                                <div class="card-header bg-warning">
             
                                           <div class="card-title ">
                                            <h3><strong>Aadhar Card Make Manual In Just !</strong> </h3><hr>
                                             <div class="card-title">
                                             <h6>Disclaimer :- This Aadhaar card is fake, do not use it in any scheme of the government, it will be your responsibility to do so.</h6>
                                             <!--<h4>Disclaimer :- <B>Pan Find Charge =  <?php echo $rw['panfindfee'];?></B></h4>-->
                                             <!--  <div style = "display: flex; justify-content:flex-start">-->
                                             <!--<a class="btn btn-dark" href="check.php" target="_blank">Available Balance : Rs <?php echo $rw['findwallet'];?></a>-->
                                             <!-- </div>-->
                                             <!--  <div style = "display: flex; justify-content:flex-end">-->
                                             <!--      <a class="btn btn-dark" href="sure_fast_list"><i class="fa fa-check-circle"></i> Instant List</a>-->
                                             <!--  </div> -->
                                             </div>
                                           </div>
                                         </div>
                                      </div>

						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-id-card me-1 font-22 text-primary"></i>
									</div>
									<h5 class="mb-0 text-primary">Enter Aadhar Details</h5>
								</div>
								<hr>

            
            
							
				
								<?php if($msg !='') { ?>
									<div style="width=100%"  class="row cvmsgok"><?php echo $msg; ?></div>
								<?php } elseif($msgno !='') { ?>
									<div style="width=100%"  class="row cvmsgno"><?php echo $msgno; ?></div>
								<?php  } ?>
						
								<form method="post" autocomplete="off"  onSubmit="return validation();"   enctype="multipart/form-data" action="" style="width:100%">
									<div class="row dgnform">
                                        <div class="col-sm-9">
                                                <div class="row">
                                                    <div class="col-sm-4">
                                                        <label>Aadhar Card No.</label>
                                                        <div class="form-group">
                                                            <input class="form-control " value=""  id="aadharno" placeholder="Aadharcard No..." autocomplete="off" name="aadharno" type="text" maxlength="12" required value="<?php echo $aadharno; ?>">
                                                            <span id="erroraadharno" class="error"></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <label>Name</label>
                                                        <div class="form-group">
                                                            <input class="form-control " value="" id="name" placeholder="Example : Raju Kumar" name="name" type="text" required value="<?php echo $txtnm; ?>">
														</div>
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <label>Father Name </label>
                                                        <div class="form-group">

                                                            <input class="form-control" name="fathername" id="fathername" placeholder="Example : Shyam Singh" Value="" type="text" value="<?php echo $txtfnm; ?>" oninput="setaddress()">
                                                        </div>
                                                    </div>
													

													
                                                </div>
												<div class="row">
												<div class="col-md-4">
												 <label>House No</label>
												 <input class="form-control" name="houseno" id="houseno" type="text" oninput="setaddress()" required="" placeholder="House No">
												</div>
												<div class="col-md-4">
												<label>Gali,Locality</label>
												<input class="form-control" name="streetlocality" id="streetlocality" oninput="setaddress()" type="text" required="" placeholder="Gali, Locality, Panchayat">
												</div>
												<div class="col-md-4">
												<label>Post Office</label>
												<input class="form-control" name="vtcandpost" id="vtcandpost" type="text" oninput="setaddress()" required="" placeholder="Post Office">
												</div>
												<div class="col-md-4">
												<label>State</label>
												<input class="form-control" name="state" id="state" type="text" oninput="setaddress()" required="" placeholder="State">
												</div>
												<div class="col-md-4">
												<label>City</label>
												<input class="form-control" name="city" id="city" type="text" oninput="setaddress()" required="" placeholder="City">
												</div>
												<div class="col-md-4">
												<label>Pin code</label>
												<input class="form-control" name="pincode" id="pincode" type="text" maxlength="7"oninput="setaddress()" required="" placeholder="pincode">
												</div>
												</div>
												
                                                <div class="row">
                                                    <div class="col-sm-4">
                                                        <label>Date Of Birth</label>
                                                        <div class="form-group">
                                                            <input class="form-control " name="dobadhar"  type="text" value="26/07/1992" required placeholder="D.O.B.(dd/MM/yyyy)" value="<?php echo $txtdob; ?>">
                                                            <input class="form-control " name="houseno"  type="hidden" value="<?php echo $txtbuld; ?>">
                                                            <input class="form-control " name="street"  type="hidden" value="<?php echo $txtlocality; ?>">
                                                            <input class="form-control " name="pincode"  type="hidden" value="<?php echo $txtpincode; ?>">
                                                            <input class="form-control " name="vtcandpost"  type="hidden" value="<?php echo $txtpost; ?>">
                                                            <input class="form-control " name="dist"  type="hidden" value="<?php echo $txtdistrict; ?>">
                                                            <input class="form-control " name="statename"  type="hidden" value="<?php echo $txtstate; ?>">
															<input class="form-control " id="birthtithi" name="birthtithi"  type="hidden" value="Birth Tithi">
                                                            
                                                           
                                                        </div>
                                                    </div>
													<div class="col-sm-4">
													<label>Date Of Birth Local</label>
													<input class="form-control " id="birthtithilocal" name="birthtithilocal" placeholder="Auto Fill"  type="text" value="">
                                                    </div></br>
													
                                                    <div class="col-md-4 col-sm-4 col-xs-6">
                                                    <label>Select Gender</label>
                                                    <div class="form-group">
                                                        <select class="form-control" name="gender" id="gender" required>
                                                            <option value="">GENDER</option>
                                                            <option value="Male" name="gender" id="gender" >Male</option>
                                                            <option value="Female" name="gender" id="gender" >Female</option>
                                                        </select>   
                                                    </div>
                                                    </div>
													<div class="col-sm-4">
													<label>Gender Local</label>
													<input class="form-control " id="genderlocal" name="genderlocal"  type="text" value="">
													</div>
													

                                                    <input class="form-control " id="pata" name="pata" readonly="readonly" type="hidden" value="address">
                                                    <input class="form-control " id="patalocal" name="patalocal" readonly="readonly" type="hidden" value="">
                                                        </div>
                                                    </div>
													
                                                    <div class="col-sm-7">
                                                        <label>Address  </label>
                                                        <div class="form-group">
                                                            <textarea class="form-control"  placeholder="S/O : Mo Rahim,  khurdaha, Jakhauli, Faizabad, Uttar Pradesh, 878987"  style="height:55px" id="txtSource" name="address" rows="10" type="text"  ><?php echo $txtadd; ?></textarea> 
                                                            <span id="errortxtSource" class="error"></span>
                                                        </div>
                                                  </div>
                                        <div class="col-sm-3">
                                            <label>Select Image  </label>
                                            <div class="form-group">
											  <input type="file" name="imagefile" class="form-control" id="imgInp" accept="image/x-png,image/jpg,image/jpeg" required />
                                                 <img src="" id="blah" width="100px" height="100px" style="display: none;">
                                            </div>
                                        </div>
                                        <div class="col-sm-12">
                                            <div class="row">
                                                <div class="col-sm-3">
                                                    <label>Select Local Language</label>
                                                    <div class="form-group">
                                                        <select class="form-control"  onchange="changelang()" name="language" id="language" required>
                                                            <option value="">SELECT  </option>
                                                             <option value="HI">Hindi   </option>
                                                             <option value="PA">Punjabi </option>
                                                             <option value="GU">Gujarati</option>
                                                             <option value="MR">Marathi </option>
                                                             <option value="TA">Tamil   </option>
                                                             <option value="KN">Kannada </option>
                                                             <option value="BN">Bengali </option>
                                                             <option value="TE">Telugu  </option>
                                                             <option value="OR">Oriya   </option>
                                                             <option value="SD">Sindhi  </option>
                                                        </select>  
                                                        <span id="errorlanguage" class="error"></span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-3">
                                                    <label>Name (Local Language) </label>
                                                    <div class="form-group">
                                                        <input class="form-control" id="name_regional"  name="namelocal" type="text" value="">
                                                        <span id="errorname_regional" class="error"></span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <label>Address (Local Language)  </label>
                                                    <div class="form-group">
                                                        <textarea class="form-control" id="txtTarget"   style="height:55px" name="addresslocal" rows="10" type="text" ></textarea>
                                                        <span id="errortxtTarget" class="error"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <label>&nbsp;</label>
                                                <div class="form-group">              
                                                <button type="submit" name="savedataauto" class="btn btn-success btn-block">Submit</button>  
                                                </div> 
												
												
													  
                                            </div>
                                            <div class="col-sm-3">
                                                <label>&nbsp;</label>
                                                <div class="form-group">              
                                                <a href="https://www.google.com/intl/sa/inputtools/try/" target="_blank" type="button" name="button" class="btn btn-primary btn-block">Open Google Input Tools</a> 
                                                </div>
												
												
                                            </div>
                                        </div>
									</div>

                                    
								</form>
					
							</div>
							<!-- /# row -->
						</section>
					</div>
				</div>
            </div>
        </div>


        <script type="text/javascript">
			function validation() {
				
			/*	var aadharno = document.getElementById('aadharno').value;
				if ( aadharno.length < 12 ) {
					 document.getElementById('erroraadharno').innerHTML = " **Please Enter 12 Digit Aadhaar Card Number !!!";
					 document.getElementById('aadharno').style.border = "1px solid red";
					 document.getElementById('aadharno').focus();
					 return false;
				}*/

                var txtSource = document.getElementById('txtSource').value;
				if ( txtSource.trim() =="" ) {
					 document.getElementById('errortxtSource').innerHTML = " **Please Enter Address !!!";
					 document.getElementById('txtSource').style.border = "1px solid red";
					 document.getElementById('txtSource').focus();
					 return false;
                }
                
                var name_regional = document.getElementById('name_regional').value;
				if ( name_regional.trim() =="" ) {
					 document.getElementById('errorname_regional').innerHTML = " **Please Enter Name in Local Language !!!";
					 document.getElementById('name_regional').style.border = "1px solid red";
					 document.getElementById('name_regional').focus();
					 return false;
				}

                var txtTarget = document.getElementById('txtTarget').value;
				if ( txtTarget.trim() =="" ) {
					 document.getElementById('errortxtTarget').innerHTML = " **Please Enter Local Language Address !!!";
					 document.getElementById('txtTarget').style.border = "1px solid red";
					 document.getElementById('txtTarget').focus();
					 return false;
				}
				
			}
			
			function setaddress(){
	var fathername = document.getElementById('fathername').value;
    var houseno = document.getElementById('houseno').value;
    var streetlocality = document.getElementById('streetlocality').value;
    var vtcandpost = document.getElementById('vtcandpost').value;
    var state = document.getElementById('state').value;
    var city = document.getElementById('city').value;
    var pincode = document.getElementById('pincode').value;

	document.getElementById('txtSource').innerHTML ="S/O : " + fathername + ", " + houseno + ", " + streetlocality + ", " + vtcandpost + ", " + city + ", " + state + ", " + pincode;
}

        </script>
	         
<script type="text/javascript">
//English to hindi translate code
    function changelang() {
            var lang = document.getElementById("language").value;
            //alert(lang);
            var url = 
			"https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#txtSource").val());
		    
		   $.get(url, function (data, status) {
			 var result= '';
			  for(var i=0; i<=500; i++)
			    {
			      result += data[0][i][0];
                  //alert(result);
				  $("#txtTarget").val(result);
					
			    }
            });	

            url = 
			"https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#name").val());
		    //alert(url);
		   $.get(url, function (data, status) {
			 var result= '';
			  for(var i=0; i<=500; i++)
			    {
			      result += data[0][i][0];
                 // alert(result);
				  $("#name_regional").val(result);
					
			    }
            });	

            
            url = 
			"https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#gender").val());
		    //alert(url);
		   $.get(url, function (data, status) {
			 var result= '';
			  for(var i=0; i<=500; i++)
			    {
			      result += data[0][i][0];
                  // alert(result);
            if(result=="नर")
            result="पुरुष";
            $("#genderlocal").val(result);
					
			    }
            });
            
            url = 
			"https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#gender").val());
		    //alert(url);
		   $.get(url, function (data, status) {
			 var result= '';
			  for(var i=0; i<=500; i++)
			    {
			      result += data[0][i][0];
                  // alert(result);
            if(result=="ਨਰ")
            result="ਮਰਦ";
            $("#genderlocal").val(result);
					
			    }
            });

            url = 
			"https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#birthtithi").val());
		    //alert(url);
		   $.get(url, function (data, status) {
			 var result= '';
			  for(var i=0; i<=500; i++)
			    {
			      result += data[0][i][0];
                 //alert(result);
				  $("#birthtithilocal").val(result);
					
			    }
            });


            url = 
			"https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#pata").val());
		    //alert(url);
		   $.get(url, function (data, status) {
			 var result= '';
			  for(var i=0; i<=500; i++)
			    {
			      result += data[0][i][0];
                 // alert(result);
				  $("#patalocal").val(result);
					
			    }
            });

		};	
//Words and Characters Count
function readURL(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();

        reader.onload = function (e) {
            $('#blah').attr('src', e.target.result);
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


  </script>
<div id="myModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      
      <div class="modal-body">
        <img src="<?php echo $slct['aafile'];?>" width="<?php echo $slct['iwidth'];?>" height="<?php echo $slct['iheight'];?>"/>
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