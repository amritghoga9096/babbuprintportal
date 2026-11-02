<?php
include('header.php');
?>
     	<!--start page wrapper -->
     	
     	<!--Design by Hkb Web Developing - https://hkbwebdeveloping.tech-->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">All Voter Manual List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="voter_adv_flx" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Voter Advance
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
			
	
				<!--end breadcrumb-->
			
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-id-card me-1 font-22 text-primary"></i>
									</div>
									<h5 class="mb-0 text-primary">Fetch Voter Details</h5>
								</div>
								<hr>
								
<script type="text/javascript">
     function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $('#blah')
                        .attr('src', e.target.result);
                };

                reader.readAsDataURL(input.files[0]);
            }
        }
function setHouseNo(){
  var houseno = document.getElementById('houseNo').value;
  if ( houseno.trim() !="" ) {
          galimn = houseno ;
      }
  var partname = document.getElementById('partname').value;
  var tahsil = document.getElementById('tehsil').value;
  var dist = document.getElementById('district').value;
  var state = document.getElementById('statename').value;
  document.getElementById('txtSource').innerHTML = galimn +","+ partname +"," + tahsil +","+ dist +","+ state; 

}
function setaddress(){                                                 
    var galimn = "";
  
    var houseno = document.getElementById('houseno').value;
    if ( houseno.trim() !="" ) {
        galimn = houseno ;
    } 
    var gali = document.getElementById('gali').value;
    if ( gali.trim() !="" ) {
        galimn = galimn + gali ;
    } 

    var locality = document.getElementById('locality').value;
    var vtcandpost = document.getElementById('vtcandpost').value;
    var dist = document.getElementById('dists').value;
    var state = document.getElementById('statename').value;
    var pincode = document.getElementById('pincodes').value;
    var policestation = document.getElementById('policestation').value;
    var tahshil = document.getElementById('tahshil').value;

  document.getElementById('txtSource').innerHTML = galimn + locality + " Police Station-" + policestation + ", Tahshil-" + tahshil + ", District-" + dist + ", Pin Code-" + pincode;
}
</script>
 
<?php
        if(isset($_POST['submit'])) {                         
           $epic =$_POST['epicno']; 
           $filedata =$_POST['filedata']; 
           $captchaId =$_POST['captchaTxnId']; 
           $captchaText =$_POST['captchaValue']; 
           $server=$_SERVER['SERVER_NAME'];
           $api_key="f0a84f40a73e2c3ae153941201f3bcd4b58063fd415f62a60221f29fa2d0b75a";
  $curl = curl_init();
  curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.flexsecure.xyz/serviceApi/V2/Voter-verification.php?epic=$epic&apiKey=$api_key&captchaId=$captchaId&captchaText=$captchaText",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "cache-control: no-cache",
    "postman-token: 	"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    $json = json_decode($response, true);
    
    // Safely check if structure exists
    if (isset($json['response']['docs'][0]['status'])) {
        $status = $json['response']['docs'][0]['status'];
        
        // Normalize status check
        if ($status === true || $status === 'true' || strtolower($status) === 'true') {
            $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='voter_manual_flx'"));
            $appliedby = $udata['phone'];
            $fee = $price['price'];
            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
        } else {
            echo "API responded but status is not true. No debit.";
        }
    } else {
        echo "Invalid API response. 'status' not found. No debit.";
    }
}

                                            
              $assco_name       = $a["ac_name"];
              $assco_no         = $a["ac_no"];
              $namelocallang    = $a["name_v1"];
              //print_r($assco_no);
              //die;
              $name             = $a["name"];
              $gender              = $a["gender"];
              $epic_no          = $a['epic_no'];
              $fname            = $a["rln_name"];
              $fnamelocal       = $a["rln_name_v1"];
              $part_no        = $a["part_no"];
              $part_name_in     = $a["part_name"];
              $part_name_local  = $a["part_name_v1"];
              $dist_name        = $a["district"];
              $tehsil           = $a["ac_name"];
              $state            = $a["state"];
              $dist_local      = $a["dist_name_v1"];
              $age              = $a["age"];
              $relation_type    = $a["rln_type"];
              $ac_name_local    = $a["ac_name_v1"];
              
              
        }                                        
?>

                            <?php 
if (isset($_POST['savedata'])) { 
    $name = strtoupper(trim($_POST['name']));
    $aadharname = trim($_POST['aadharname']);
    $namelocal = trim($_POST['namelocal']);
    $gender = trim($_POST['gender']);
    $genderlocal = trim($_POST['genderlocal']);
    $dobadhar = trim($_POST['dobadhar']);
    $spousename = trim($_POST['father/husband']);
    $spousenamelocal = trim($_POST['spousenamelocal']);
    $fathername = strtoupper(trim($_POST['fathername']));
    $aadharfathername = trim($_POST['aadharfathername']);
    $fathernamelocal = trim($_POST['fathernamelocal']);
    $epicno = trim($_POST['epicno']);
    $policestation = trim($_POST['policestation']);
    $tahshil = trim($_POST['tahshil']);
    $assemblyconnameno = trim($_POST['assemblyconnameno']);
    $assemblyconnamenolocal = trim($_POST['assemblyconnamenolocal']);
    $partno = trim($_POST['partno']);
    $partname = trim($_POST['partname']);
    $partnamelocal = trim($_POST['partnamelocal']);
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["file_up"]["name"]);
    $hsrc = $_POST['img_vl'];
    $address = trim($_POST['address']);
    $localaddress = trim($_POST['addresslocal']);
    $language = trim($_POST['language']);
    $birthtithilocal = trim($_POST['birthtithilocal']);
    $patalocal = trim($_POST['patalocal']);
    $kanamelocal = trim($_POST['kanamelocal']);
    $sexlocal = trim($_POST['sexlocal']);
    $signlocal = trim($_POST['signlocal']);
    $assconnamenolocal = trim($_POST['assconnamenolocal']);
    $partnoandnamelocal = trim($_POST['partnoandnamelocal']);
    $user_image = $_POST['image_data'];

    $s = date($fetch['joindate']);
    $dt = new DateTime($s);
    $date = $dt->format('Y-m-d');

    // Validation
    if ($name == "") {
        $msgno = 'Please Enter Voter Name .... ';
    } else if ($fetch['mfee'] == 0 && $fetch['balance'] > 20 && $date <= '2019-12-16') {
        $msgno = 'Pay Maintaince Fee First!!!';
    } else if ($namelocal == "") {
        $msgno = 'Please Enter Voter Name in Local Language .... ';
    } else if ($gender == "") {
        $msgno = 'Please Enter Gender .... ';
    } else if ($dobadhar == "") {
        $msgno = 'Please Enter Date of Birth .... ';
    } else if ($fathername == "") {
        $msgno = 'Please Enter Father Name .... ';
    } else if ($tahshil == "") {
        $msgno = 'Please Enter Tahshil .... ';
    } else if ($language == "") {
        $msgno = 'Please Enter Local Language .... ';
    } else if ($address == "") {
        $msgno = 'Please Enter Address .... ';
    } else if ($localaddress == "") {
        $msgno = 'Please Enter Address in Local Language .... ';
    } else { 
        $a = mysqli_query($ahk_conn, "SELECT epicno FROM voterauto1 WHERE epicno='" . $epicno . "'");
        $b = mysqli_fetch_array($a);

        // Insert data
        $resultm = mysqli_query($ahk_conn, "SELECT srno FROM voterauto1 ORDER BY srno DESC LIMIT 1");
        $num_rows = mysqli_fetch_array($resultm);
        $srno = $num_rows['srno'] + 1;

        date_default_timezone_set('Asia/Kolkata');
        $timestamp = date("Y-m-d H:i:s");

        $query = "INSERT INTO `voterauto1`(
            `votername`, `aadharname`, `namelocal`, `dob`, `dobinlocal`, `gender`, `genderlocal`, `sexlocal`, 
            `spousename`, `spousenamelocal`, `fathername`, `fatheraadharname`, `fathernamelocal`, `epicno`, 
            `policestation`, `tahshil`, `assconnonm`, `assconnonmlocal`, `partno`, `partname`, `partnamelocal`, 
            `locallanguage`, `fulladdress`, `localaddress`, `pata`, `kaname`, `sign`, `signlocal`, `assconnameno`, 
            `assconnamenolocal`, `partnoandname`, `partnoandnamelocal`, `imagepathoriginal`, `status`, `srno`, 
            `createdatetime`, `userid`
        ) VALUES (
            '$name', '$aadharname', N'$namelocal', '$dobadhar', N'$birthtithilocal', '$gender', 
            N'$genderlocal', N'$sexlocal', '$spousename', N'$spousenamelocal', '$fathername', 
            '$aadharfathername', N'$fathernamelocal', '$epicno', '$policestation', '$tahshil', 
            '$assemblyconnameno', N'$assemblyconnamenolocal', '$partno', '$partname', N'$partnamelocal', 
            '$language', '$address', N'$localaddress', N'$patalocal', N'$kanamelocal', '$sign', 
            N'$signlocal', 'Assembly Constituency No. & Name', N'$assconnamenolocal', 
            'Part No and Name', N'$partnoandnamelocal', '$user_image', 'PENDING', '$srno', 
            '$timestamp', '{$_SESSION['phone']}'
        )";

        $result = mysqli_query($ahk_conn, $query);  
        $last_id = mysqli_insert_id($ahk_conn);

        $_SESSION["IMGPATH"] = '';
        $_SESSION["epicno"] = $epicno;

        $msg = "Please Wait Voter Preview just a second....";

        if ($result) {
            // Balance debit
            $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='voter_manual_flx'"));
            $appliedby = $_SESSION['phone'];
            $fee = $price['price'];
             $debit_fee = $udata['balance'] - $fee;
             mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$appliedby','$fee','$debit_fee','Voter Manual','1','Debit')");

            if (!$debit) {
                error_log("Balance debit failed for user $appliedby");
            }

            echo '<script>
                $(function(){
                    Swal.fire(
                        "' . $msg . '",
                        "",
                        "success"
                    );
                });
                setTimeout(function () {
                    window.location = "voter_adv_flx_list.php";
                }, 1200);
            </script>';
        }
    

?>

                                        <?php
                                        mysqli_set_charset($ahk_conn,"utf8");
                                    $a = mysqli_query($ahk_conn,"SELECT * FROM voterauto1 Where epicno='".$_SESSION["epicno"]."'");
                                    $b = mysqli_fetch_array($a);

                                    $remark="";
                                    $remark= 'Voter Card No : '.$b['epicno'].' Voter Name : '.$b['votername'] ;
                                    // strat less point
                                    //  Dr amount start
                                   $res = mysqli_query(
    $ahk_conn,
    "SELECT * FROM users WHERE userid = '" . $_SESSION['phone'] . "'"
);

if (!$res) {
    die("User query failed: " . mysqli_error($ahk_conn));
}

$getpoint = mysqli_fetch_assoc($res);

                  
                                    $qu = "";
                                    $qu = "INSERT INTO `tbltrans`(`userid`, `username`, `transdate`, `transqty`, `transtype`, `touserid`, `tousername`, `remark`, `loginid`, `logdate`)
                                    VALUES ('".$_SESSION['phone']."','".$_SESSION['username']."',now(),'".$getpoint['aadharpoint']."','Dr','0','Voter Create','".$remark."','".$_SESSION['phone']."',now())";
                                    $a1q=mysqli_query($ahk_conn,$qu);

                  
?>
<?php }
}
?>
                
                <?php if($msg !='') { ?>
                  <div style="width=100%"  class="row cvmsgok"><?php echo $msg; ?></div>
                <?php } elseif($msgno !='') { ?>
                  <div style="width=100%"  class="row cvmsgno"><?php echo $msgno; ?></div>
                <?php  } ?>
                <form method="post" autocomplete="off"  onSubmit="return validation();"   enctype="multipart/form-data" action="" style="width:100%">
                  <div class="row dgnform">
          <style>
            .image-preview__image{
              width:123px;
              height: 186px;
            }
            .image-preview{
              width: 123px;
              height: 186px;
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
          </style><div class="container">
    <div class="row">
        <!-- Image Upload Section -->
        <div class="col-sm-4 col-xs-12">
            <label style="margin-top: 15px;">Image</label>
            <div class="image-preview" id="imagePreview">
                <img class="image-preview__image" id="blah" name="blah" src="<?php echo isset($filedata) ? $filedata : ''; ?>" style="max-width: 100%; height: auto;" />
                <input class="form-control" name="image_data" type="hidden" value="<?php echo isset($filedata) ? $filedata : ''; ?>" id="image_data">
            </div>
            <input type="file" id="filedata" name="filedata" accept="image/*" onchange="readURL(this);" style="margin-top: 10px;">
        </div>

        <!-- Epic No. Input Section -->
        <div class="col-sm-5 col-xs-12">
            <label style="margin-top: 15px;">Epic No.</label>
            <div class="form-group">
                <input class="form-control" name="epicno" type="text" value="<?php echo $epic_no; ?>" required>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to Preview Image -->
<script>
function readURL(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const imageElement = document.getElementById('blah');
            const hiddenInput = document.getElementById('image_data');
            imageElement.src = e.target.result;
            hiddenInput.value = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

                                                <div class="row">
                                                <div class="col-sm-3 col-xs-6">
                                                    <label>Name</label>
                                                    <div class="form-group">
                                                       <input class="form-control" name="name"  id="name"  type="text" value="<?php echo $name; ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="col-sm-3 col-xs-6">
                                                    <label>Name in Local Language</label>
                                                    <div class="form-group">
                                                       <input class="form-control" name="namelocal" id="namelocal"  type="text" value="<?php echo $namelocallang; ?>">
                                                    </div>
                                                </div>
                                               
                                                <div class="col-sm-2 col-xs-6">
                                                    <label>Age</label>
                                                    <div class="form-group">
                                                       <input class="form-control"  name="dobadhar"  id="dob"  type="text" value="<?php echo $age; ?>"  required>
                                                  </div>
                                                </div>      
                                                
                                                <div class="col-sm-3 col-xs-6">
                                                    <label>Gender</label>
                                                    <div class="form-group">
                                                        <input class="form-control " id="gender"  name="gender"  type="text" value="<?php echo $gender; ?>" required> 
                                                    </div>
                                                </div>
                                                </div>

                                            <div class="row">
                                                <div class="col-sm-2 col-xs-12">
                                                    <label>Father/Husband </label>
                                                    <div class="form-group">
                                                    <input class="form-control" id="spouse" name="father/husband"  id="dob"  type="text" value="<?php echo $relation_type; ?>" required>    
                                                    </div>
                                                </div>
                                                <div class="col-sm-5 col-xs-12">
                                                    <label>Father/Husband Name</label>
                                                    <div class="form-group">
                                                        <input class="form-control" name="fathername" id="fathername"  type="text" value="<?php echo $fname; ?>" required placeholder="Father/Husband Name">
                                                        
                                                    </div>
                                                </div>
                                                <div class="col-sm-5 col-xs-12">
                                                    <label>Father/Husband Name(Local Language)</label>
                                                    <div class="form-group">
                                                        <input class="form-control" name="fathernamelocal" id="fathernamelocal"  type="text" value="<?php echo $fnamelocal; ?>"  required placeholder="Father/Husband Name(Local Language)" >
                                                    </div>
                                                </div>                                                    
                                            </div>
                                            
                                                <div class="col-sm-4 col-xs-12">
                                                    <label>Tahshil </label>
                                                    <div class="form-group">
                                                        <input class="form-control"  name="tahshil" id="tehsil" type="text" value="<?php echo ucfirst(strtolower($tehsil)); ?>" required  placeholder="Tahshil" required>
                                                        <span id="errortahshil" class="error"></span>
                                                    </div>
                                                </div>
                                                
                                                 <div class="col-sm-4 col-xs-12">
                                                    <label>District </label>
                                                    <div class="form-group">
                                                        <input class="form-control"  name="district" id="district" type="text" value="<?php echo ucfirst(strtolower($dist_name)); ?>" required  placeholder="District" required>
                                                        <span id="errordistrict" class="error"></span>
                                                    </div>
                                                </div>
                                                 <div class="col-sm-4 col-xs-12">
                                                    <label>House No.</label>
                                                    <div class="form-group">
                                                        <input class="form-control " id="houseNo" name="houseNo" oninput="setHouseNo()"  type="text"  required> 
                                                    </div>
                                                </div>
                                                </div>
                                                 <div class="col-sm-12">
                                                 <div class="row">
                                                 <div class="col-sm-6 col-xs-12">
                                                    <label>Assembly Constituency  Number and Name</label>
                                                    <div class="form-group">
                                                        <input class="form-control" id="assemblyconnameno"  name="assemblyconnameno"  type="text" value="<?php echo $assco_no.' - '.$assco_name ; ?>"  required placeholder="Assembly Constituency  Number and Name" required>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-xs-12">
                                                    <label>Assembly Constituency  Number and Name(Local)</label>
                                                    <div class="form-group">
                                                        <input class="form-control" id="assemblyconnamenolocal"  name="assemblyconnamenolocal"  type="text" value="<?php echo $assco_no.' - '.$ac_name_local; ?>"  required placeholder="Assembly Constituency  Number and Name Local Language" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-4 col-xs-12">
                                                    <label>Part Number</label>
                                                    <div class="form-group">
                                                        <input class="form-control" required name="partno"  type="text" value="<?php echo $part_no; ?>"  required placeholder="Part Number">
                                                    </div>
                                                </div>
                                               <div class="col-sm-4 col-xs-12">
                                                    <label>Part Name</label>
                                                    <div class="form-group">
                                                        <input class="form-control"  required id="partname" name="partname"  type="text" value="<?php echo  ucfirst(strtolower($part_name_in)); ?>"  required placeholder="Part Name">
                                                    </div>
                                                </div>
                                                <div class="col-sm-4 col-xs-12">
                                                    <label>Part Name(Local Language)</label>
                                                    <div class="form-group">
                                                        <input class="form-control" id="partnamelocal"  name="partnamelocal" type="text" value="<?php echo $part_name_local; ?>" required placeholder="Part Name(Local Language)">
                                                       
                                                        <input class="form-control" id="houseno"  name="houseno"  type="hidden" value="<?php echo $txtbuld; ?>">
                                                        <input class="form-control" id="gali"  name="gali"  type="hidden" value="<?php echo $txtgali; ?>">
                                                        <input class="form-control" id="locality"  name="locality"  type="hidden" value="<?php echo $txtlocality; ?>">
                                                        <input class="form-control" id="pincode"  name="pincode"  type="hidden" value="<?php echo $txtpincode; ?>">
                                                        <input class="form-control" id="vtcandpost"  name="vtcandpost"  type="hidden" value="<?php echo $txtpost; ?>">
                                                        <input class="form-control" id="dist"  name="dist"  type="hidden" value="<?php echo $dist_name; ?>">
                                                        <input class="form-control" id="statename"  name="statename" oninput="setHouseNo()" type="hidden" value="<?php echo $state; ?>">
                                                       
                                                        <input class="form-control" id="aadharname" name="aadharname"  type="hidden" value="<?php echo $aadharname; ?>">
                                                        <input class="form-control" id="aadharfathername" name="aadharfathername"  type="hidden" value="<?php echo $aadharfname; ?>">
                                                        <input class="form-control" id="genderlocal" name="genderlocal"  type="hidden" value="">
                                                        <input class="form-control" id="birthtithi" name="birthtithi"  type="hidden" value="BirthTithi / Age ">
                                                        <input class="form-control" id="birthtithilocal" name="birthtithilocal"  type="hidden" value="">
                                                        <input class="form-control" id="pata" name="pata"  type="hidden" value="address">
                                                        <input class="form-control" id="patalocal" name="patalocal"  type="hidden" value="">
                                                        <input class="form-control" id="spousenamelocal" name="spousenamelocal"  type="hidden" value="">
                                                        <input class="form-control" id="kaname" name="kaname"  type="hidden" value="Ka Name">
                                                        <input class="form-control" id="kanamelocal" name="kanamelocal"  type="hidden" value="">
                                                        <input class="form-control" id="sex" name="sex" Address"" type="hidden" value="Sex">
                                                        <input class="form-control" id="sexlocal" name="sexlocal"  type="hidden" value="">
                                                        <input class="form-control" id="sign" name="sign"  type="hidden" value="Electoral Registration Officer">
                                                        <input class="form-control" id="signlocal" name="signlocal"  type="hidden" value="">
                                                        <input class="form-control" id="assconnameno" name="assconnameno"  type="hidden" value="Assembly Constituency Number and Name">
                                                        <input class="form-control" id="assconnamenolocal" name="assconnamenolocal"  type="hidden" value="">
                                                        <input class="form-control" id="partnoandname" name="partnoandname"  type="hidden" value="Part Number and Name">
                                                        <input class="form-control" id="partnoandnamelocal" name="partnoandnamelocal" type="hidden" value="">

                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-sm-2 col-xs-12">
                                                    <label>Select Language</label>
                                                    <div class="form-group">
                                                        <select class="form-control" oninput="changelang()"   name="language" id="lang1" required>
                                                            <option value="">SELECT</option>
                                                            <option value="HI">Hindi</option>
                                                            <option value="PA">Punjabi</option>
                                                            <option value="GU">Gujrati</option>
                                                            <option value="MR">Marathi</option>
                                                            <option value="TA">Tamil</option>
                                                            <option value="KN">Kannada</option>
                                                            <option value="BN">Bengali</option>
                                                            <option value="TE">Telugu</option>
                                                            <option value="SD">Sindhi</option>
                                                            <option value="OR">Oriya</option>
                                                        </select>   
                                                        <span id="errorlanguage" class="error"></span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-5 col-xs-12">
                                                    <label>Address  </label>
                                                    <div class="form-group">
                                                        <textarea class="form-control" style="height:55px"   id="txtSource" name="address" rows="10" type="text" ></textarea>
                                                        <span id="errortxtSource" class="error"></span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-5 col-xs-12">
                                                    <label>Address (Local Language)  </label>
                                                    <div class="form-group">
                                                        <textarea class="form-control" id="txtTarget"     style="height:55px" name="addresslocal" rows="10" type="text" ></textarea>
                                                        <span id="errortxtTarget" class="error"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-sm-3">
                                                <label>&nbsp;</label>
                                                <div class="form-group">              
                                                <button type="submit" name="savedata" class="btn btn-success btn-block">Submit</button> 
                        <label>&nbsp;</label>
                        
                                                <div class="form-group"> 
                        
                        <a href="https://www.google.com/intl/sa/inputtools/try/" target="_blank" button class="btn btn-light  btn-block float-right" type="submit">Language Translator</a>
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
        
        var aadharno = document.getElementById('aadharno').value;
        if ( aadharno.length < 12 ) {
           document.getElementById('erroraadharno').innerHTML = " **Please Enter 12 Digit Aadhaar Card Number !!!";
           document.getElementById('aadharno').style.border = "1px solid red";
           document.getElementById('aadharno').focus();
           return false;
        }

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
        </script>
           
<script type="text/javascript">
//English to hindi translate code
    function changelang() {
    //alert("123456789");
            var lang = document.getElementById("lang1").value;
            //alert(lang);
            var url = 
            "https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#txtSource").val());
            //alert(url);
           $.get(url, function (data, status) {
  //  alert(data);
  //  alert(status);
             var result= '';
              for(var i=0; i<=500; i++)
                {
                  result += data[0][i][0];
          //        alert(result);
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

            
            var gen = $("#gender").val();
            url = 
            "https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape(gen.toLowerCase());
            //alert(url);
           $.get(url, function (data, status) {
             var result= '';
              for(var i=0; i<=500; i++)
                {
                  result += data[0][i][0];
                 // alert(result);
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

      url = "";
            url = "https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#assconnameno").val());
                    //alert(url);
                   $.get(url, function (data, status) {
                         var result= '';
                          for(var i=0; i<=500; i++)
                            {
                              result += data[0][i][0];
                 // alert(result);
                                  $("#assconnamenolocal").val(result);

                            }
            });

      url = "";
            url = "https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#partnoandname").val());
                    //alert(url);
                   $.get(url, function (data, status) {
                         var result= '';
                          for(var i=0; i<=500; i++)
                            {
                              result += data[0][i][0];
                 // alert(result);
                                  $("#partnoandnamelocal").val(result);

                            }
            });

      url = "";
            url = "https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#sign").val());
                    //alert(url);
                   $.get(url, function (data, status) {
                         var result= '';
                          for(var i=0; i<=500; i++)
                            {
                              result += data[0][i][0];
                 // alert(result);
                                  $("#signlocal").val(result);

                            }
            });

      url = "";
            url = "https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#sex").val());
                    //alert(url);
                   $.get(url, function (data, status) {
                         var result= '';
                          for(var i=0; i<=500; i++)
                            {
                              result += data[0][i][0];
                 // alert(result);
                                  $("#sexlocal").val(result);

                            }
            });

      url = "";
            url = "https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#spouse").val());
                    //alert(url);
                   $.get(url, function (data, status) {
    //  alert(data);
    //  alert(status);
                         var result= '';
                          for(var i=0; i<=500; i++)
                            {
                              result += data[0][i][0];
                 // alert(result);
                                  $("#spousenamelocal").val(result);

                            }
            });

            url = "";
            url = "https://translate.googleapis.com/translate_a/single?client=gtx";
            url += "&sl=" + 'EN';
            url += "&tl=" + lang;
            url += "&dt=t&q=" + escape($("#kaname").val());
                    //alert(url);
                   $.get(url, function (data, status) {
                         var result= '';
                          for(var i=0; i<=500; i++)
                            {
                              result += data[0][i][0];
                 // alert(result);
                                  $("#kanamelocal").val(result);

                            }
            });

        };  
//Words and Characters Count    
</script>

<div id="myModald123456" class="modal fade" role="dialog" style="background-color: rgba(0, 0, 0, 0.67);" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      
      <div class="modal-body">
    <center>
    <div class="form-group" style="display: inline-grid;" id="step1">
    <label>Upload Photo </label>
   <label> यहाँ फोटो अपलोड करे </label>
    <br>
       <input id="file" class="form-control" type="file"/>
     <br>
     <div class="col-sm-12" style="display: flex;">
     <div class="col-sm-6">    
     <button id="button" class="btn btn-primary" style="border-radius: 20px;
    padding: 10px;background-color:#28a745;border:1px solid orange;">Next Step</button>
     <br>
     </div>
     <div class="col-sm-6">
     <a href="panel.php" class="btn btn-primary" >Home</a>
     </div>
     </div>
     
     </div>
     
     <div class="col-md-12" style="display: inline-grid;" id="step2">
     
    <form>
    <div class="col-sm-12">
    <label style="float:left;">Police Station (थाना )</label>
    <div class="form-group">
      <input type="text" id="policestation" oninput="setaddress()" class="form-control" placeholder="Enter Police Station" required style="text-transform: capitalize;">
    </div>
    </div>
    <div class="col-sm-12">
     <label style="float:left;">Pincode(पिनकोड)</label>
    <div class="form-group">
      <input type="text" id="pincodes"  maxlength="6" oninput="setaddress()" class="form-control" placeholder="Enter Pincode" onkeypress="return isNumber(event)" required>
    </div>
    </div>
    <div class="col-sm-12">
     <label style="float:left;">District (जिला )</label>
    <div class="form-group">
      <input type="text" id="dists" oninput="setaddress()" class="form-control" placeholder="Enter District" required style="text-transform: capitalize;">
     </div>
     </div>
     <div class="col-sm-12">
     <label style="float:left;">Tahshil (ब्लॉक)</label>
     <div class="form-group">
     <input class="form-control"   oninput="setaddress()" id="tahshil" type="text"  placeholder="Enter Tahshil" required required style="text-transform: capitalize;">
     </div>
     </div>
     
     <div class="col-sm-12">
     <label style="float:left;">Date Of Birth (जन्म तिथि)</label>
     <div class="form-group">
     <input class="form-control"  name="dobadhar" id="dobs"  type="date"  required>
     </div>
     </div>
     
     <div class="col-sm-12">
          <label style="float:left;">Father/Husband </label>
      <div class="form-group">
                                                  
                                                        <select class="form-control" id="spouseold" required="">
                                                            <option value="0">SELECT</option>
                                                            <option value="Father">Father</option>
                                                            <option value="Husband">Husband</option>
                                                        </select> 
                                         </div>   
</div>                     
 <div class="col-sm-12">
          <label style="float:left;">Select Language </label>
                                           <div class="form-group">                     
                                           <select class="form-control"     id="language" required>
                                                            <option value="0">SELECT</option>
                                                            <option value="HI">Hindi</option>
                                                            <option value="PA">Punjabi</option>
                                                            <option value="GU">Gujrati</option>
                                                            <option value="MR">Marathi</option>
                                                            <option value="TA">Tamil</option>
                                                            <option value="KN">Kannada</option>
                                                            <option value="BN">Bengali</option>
                                                            <option value="TE">Telugu</option>
                                                            <option value="SD">Sindhi</option>
                                                            <option value="OR">Oriya</option>
                                                        </select> 
</div>                            
</div>  
<div class="col-sm-12">                         
     <button id="finish_data" class="btn btn-primary" style="border-radius: 20px;
    padding: 10px;background-color:#28a745;border:1px solid orange;">Submit/ Finish</button>
     <a href="panel.php" class="btn btn-primary">Dashboard</a>
     </div>
     </form>
     
     </div>
     </center>
      </div>
      
        
      
    </div>

  </div>
</div>


  <style>
                
                .content-wrap {
    
  height: 1200px !important;
}
         

$('#language').on('change',function()
    {
        
        if($(this).val() != '' &&  $(this).val() == 'OR')
        {
    var langs = $(this).val();
    var lang = langs.toLowerCase();
                  var name = $("#name").val();
                  var address = $("#txtSource").val();
                  
                  $.post("https://xknfdjdjfktit3kktied3rifcdddsrtwq89764dspt4krktgoe48kjdjbds.com/admin/test.php",{lang:lang,name:name,address:address}).done(function (data) {
                      //alert(data);
                      var json = JSON.parse(data);
                      //alert(json.data);
                      $("[name='namelocal']").val(json.name.replace(/"/g,''));
                      $("[name='addresslocal']").val(json.address.replace(/"/g,''));
                  })
                  
                  var dob = $("#fathername").val();
                  var gender = $("#partname").val();
                 
                  $.post("https://xknfdjdjfktit3kktied3rifcdddsrtwq89764dspt4krktgoe48kjdjbds.com/admin/test.php",{lang:lang,name:dob,address:gender}).done(function (data) {
                      //alert(data);
                      var json = JSON.parse(data);
                      //alert(json.data);
                      $("[name='fathernamelocal']").val(json.name.replace(/"/g,''));
                      $("[name='partnamelocal']").val(json.address.replace(/"/g,''));
                  })
                  
                   var dob = $("#assemblyconnameno").val();
                   var gender = $("#kaname").val();
                 
                  $.post("https://xknfdjdjfktit3kktied3rifcdddsrtwq89764dspt4krktgoe48kjdjbds.com/admin/test.php",{lang:lang,name:dob,address:gender}).done(function (data) {
                      //alert(data);
                      var json = JSON.parse(data);
                      //alert(json.data);
                      $("[name='assemblyconnamenolocal']").val(json.name.replace(/"/g,''));
                     $("[name='kanamelocal']").val(json.address.replace(/"/g,''));
                  })
                  
                   var dob = $("#sex").val();
                   var gender = $("#sign").val();
                 
                  $.post("https://xknfdjdjfktit3kktied3rifcdddsrtwq89764dspt4krktgoe48kjdjbds.com/admin/test.php",{lang:lang,name:dob,address:gender}).done(function (data) {
                      //alert(data);
                      var json = JSON.parse(data);
                      //alert(json.data);
                      $("[name='sexlocal']").val(json.name.replace(/"/g,''));
                     $("[name='signlocal']").val(json.address.replace(/"/g,''));
                  })
                  
                  var dob = $("#assconnameno").val();
                   var gender = $("#partnoandname").val();
                 
                  $.post("https://xknfdjdjfktit3kktied3rifcdddsrtwq89764dspt4krktgoe48kjdjbds.com/admin/test.php",{lang:lang,name:dob,address:gender}).done(function (data) {
                      //alert(data);
                      var json = JSON.parse(data);
                      //alert(json.data);
                      $("[name='assconnamenolocal']").val(json.name.replace(/"/g,''));
                     $("[name='partnoandnamelocal']").val(json.address.replace(/"/g,''));
                  })
                  
                  var dob = $("#birthtithi").val();
                   var gender = $("#pata").val();
                 
                  $.post("https://xknfdjdjfktit3kktied3rifcdddsrtwq89764dspt4krktgoe48kjdjbds.com/admin/test.php",{lang:lang,name:dob,address:gender}).done(function (data) {
                      //alert(data);
                      var json = JSON.parse(data);
                      //alert(json.data);
                      $("[name='birthtithilocal']").val(json.name.replace(/"/g,''));
                     $("[name='patalocal']").val(json.address.replace(/"/g,''));
                  })
                  
                   var dob = $("#spouse").val();
                  
                 
                  $.post("https://xknfdjdjfktit3kktied3rifcdddsrtwq89764dspt4krktgoe48kjdjbds.com/admin/test.php",{lang:lang,name:dob,address:gender}).done(function (data) {
                      //alert(data);
                      var json = JSON.parse(data);
                      //alert(json.data);
                      $("[name='spousenamelocal']").val(json.name.replace(/"/g,''));
                    
                  })
                  
                  
        }
        else 
        {
         changelang();   
        }
                  
    });
  
   function isNumber(evt) {
    evt = (evt) ? evt : window.event;
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    }
    return true;
}

   jQuery('#myModald').modal('show');
  $("#step2").hide();
  $("#finish_data").on("click", function(){
     
  var sname = $("#spouse").val();
  var police = $("#policestation").val();
  var teh = $("#tahshil").val();
  var pin = $("#pincodes").val();
  var dist = $("#dists").val();
  var dobs = $("#dobs").val();
  var langu = $("#language").val();
  if(teh == '' || police == '' || sname == 0 || pin == '' || dist == '' || dobs == '' || langu == 0)
  {
    alert('Please Fill All Details!!');
    return false;
  }
  else 
  {
    $("#spousename").val(sname);
    $("#tahshil1").val(teh);
    $("#police").val(police);
    $("#language").val(langu);
    $("#dob").val(dobs);
    $('#myModald').modal('hide');
   return false;
  }
  });
  </script>
  
  <script>
//document.getElementById('button').addEventListener('click', function() {
document.getElementById('img_v1').change( function() {
  var files = document.getElementById('file').files;
  if (files.length > 0) {
    getBase64(files[0]);
  }
});

function getBase64(file) {
   var reader = new FileReader();
   reader.readAsDataURL(file);
   reader.onload = function () {
     
  document.getElementById("myImg").src = reader.result;
  document.getElementById("img_vl").value = reader.result;
  $("#step1").hide();
  $("#step2").show();
   };
   reader.onerror = function (error) {
     console.log('Error: ', error);
   };
}
</script>


<script>


</script>
 
 <style>
     input#policestation,input#dists, {
    text-transform: capitalize;
}
 </style>
 
 	<!--end page wrapper -->
		<?php 
		include('../admin/footers.php');
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