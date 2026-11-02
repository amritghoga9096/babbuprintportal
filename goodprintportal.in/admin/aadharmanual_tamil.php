<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/aadharmanualnew_tamil.php');

?>
						   <?php
							   
							 
							   if(isset($_POST['submit'])) {	
                                    $q = "";
                                    $q = "SELECT * FROM users where  userid='".$_SESSION['phone']."'";
                                    $r = mysqli_query($ahk_conn,$q);
                                    $rw = mysqli_fetch_assoc($r);
                                    
                                    if ($rw['aadharpoint']>$rw['balance']){
                                        $msgno= "Sorry, Your Balance is Low .... Please Recharge Soon";
                                        ?>
                                        <script>
                                        setTimeout(function () {
                                        window.location.href= 'aadharauto.php';
                                        }, 2000);
                                        </script>
                                    <?php
                                    } elseif ($_FILES['imagefile']['name']==""){
                                        $msgno = 'Please Select MPTASS Files  .... ';
                                    ?>
                                    
                                        <script>
                                        setTimeout(function () {
                                        window.location.href= 'aadharauto.php';
                                        }, 2000);
                                        </script>
                                    <?php
                                    } 
                                    
                                    else {
                                        //$ch=curl_init();
                                        //curl_setopt($ch,CURLOPT_URL,'htmlfile/MPTAAS1234.html');
                                        //curl_setopt($ch,CURLOPT_POST,false);
                                        //curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
                                        //$html=curl_exec($ch);
                                        //curl_close($ch);

                                        $target_dir = "/admin/aadharpvc/imgmanualaadhaar/";
                                        $file = $_FILES['imagefile']['name'];
                                        $path = pathinfo($file);
                                        $filename = $path['filename'];
                                        $ext = $path['extension'];
                                        if (strtoupper($ext)=='HTML'){
                                            $temp_name = $_FILES['imagefile']['tmp_name'];
                                            $path_filename_ext = $target_dir.$filename.".".$ext;
                                        
                                            // Check if file already exists
                                            if (file_exists($path_filename_ext)) {
                                                //$msgno= "Sorry, file already exists.";
                                                unlink ($path_filename_ext);
                                                move_uploaded_file($temp_name,$path_filename_ext);
                                                //$msg= "Congraadharsiteations! File Uploaded Successfully.";
                                            } else {
                                                unlink ($path_filename_ext);
                                                move_uploaded_file($temp_name,$path_filename_ext);
                                                //$msg= "Congraadharsiteations! File Uploaded Successfully.";
                                            }
                                            
                                        $html=file_get_contents($path_filename_ext);
                                        unlink ($path_filename_ext);
                                        $DOM = new DOMDocument();
                                        libxml_use_internal_errors(true);
                                        $DOM -> loadHTML($html);
                                        $images = $DOM->getElementsByTagName('img');
                                        foreach($images as $image){
                                            if($image->getAttribute("class") == "img-aadhar-wrap"){
                                            //if ($input->getAttribute("class") == "img-aadhar-wrap"){
                                            $img = $image->getAttribute('src');
                                            //echo "<img src='".$img."' />";
                                            }
                                        }
                                        $imgpth='/admin/aadharpvc/imgmanualaadhaar/';
                                    
                                     
                                        $iparr = explode ("/", $img); 
                                        $aaaa =  $iparr[2];
                                       
                                        $imgfpath=$imgpth;
                                        $_SESSION["IMGPATH"]=$imgfpath;
										?>
										<script>
										alert('<?php echo $imgfpath; ?>');
										</script>
										<?php
                                        $aaaa = str_replace(".jpg","",$aaaa);
                                       // $st=substr($aaaa, 0, 4);
                                       // $nd=substr($aaaa, 4, 4);
                                       // $rd=substr($aaaa, 8, 4);
                                       // $aadharno=$st.' '.$nd.' '.$rd;
                                       $aadharno=substr($aaaa, 0, 12);
                                        //echo "<br />";
                                        $count=0;
                                        foreach($DOM->getElementsByTagName('input') as $input) {
                                         // if($input->getAttribute("name") == "name"){
                                            
                                            //if(preg_match('/[^ ]+/',$input->getAttribute('value'),$match)){
                                                if ($input->getAttribute("name") == "name"){
                                                   $txtnm= $input->getAttribute('value');
                                                }
                                                if ($input->getAttribute("name") == "fathername"){
                                                    $txtfnm= $input->getAttribute('value');
                                                 }
                                                 if ($input->getAttribute("name") == "dobadhar"){
                                                    $txtdob= $input->getAttribute('value');
                                                    $txtdob = str_replace("-","/",$txtdob);
                                                 }
                                                 if ($input->getAttribute("name") == "gender"){
                                                    $txtsex= $input->getAttribute('value');
                                                    if ($txtsex=='M'){
                                                        $txtgender='Male';
                                                    }
                                                    else{
                                                        $txtgender='Female'; 
                                                    }
                                                 }
                                                 if ($input->getAttribute("name") == "building"){
                                                    $txtbuld= $input->getAttribute('value');
                                                 }
                                                 if ($input->getAttribute("name") == "gali"){
                                                    $txtgali= $input->getAttribute('value');
                                                 }
                                                 if ($input->getAttribute("name") == "locality"){
                                                    $txtlocality= $input->getAttribute('value');
                                                 }
                                                 if ($input->getAttribute("name") == "vtc"){
                                                    $txtpost= $input->getAttribute('value');
                                                 }
                                                 if ($input->getAttribute("name") == "district"){
                                                    $txtdistrict= $input->getAttribute('value');
                                                 }
                                                 if ($input->getAttribute("name") == "state"){
                                                    $txtstate= $input->getAttribute('value');
                                                 }
                                                 if ($input->getAttribute("name") == "pincode"){
                                                    $txtpincode= $input->getAttribute('value');
                                                 }
                                                
                                           // }

                                        }   

                                    } 
                                    if (trim($txtbuld)==""){
                                        $txtadd='S/O '.$txtfnm.','.$txtgali.' '.$txtlocality.' '.$txtpost.', '.$txtdistrict.', '.$txtstate.', '.$txtpincode;
                                    } else {
                                        $txtadd='S/O '.$txtfnm.','.$txtbuld.' '.$txtgali.' '.$txtlocality.' '.$txtpost.', '.$txtdistrict.', '.$txtstate.', '.$txtpincode;
                                    }
                                   
                                    
                                    if (trim($txtnm)==""){
                                    $msgno = 'Please Select Proper MPTASS File  .... ';
                                    ?>
                                        <script>
                                        setTimeout(function () {
                                        window.location.href= 'aadharauto.php';
                                        }, 2000);
                                        </script>
                                    <?php
                                    } 

                                    } 

                               
                            }
                            ?>


                            <?php 
                              if(isset($_POST['savedata'])) {	
                             
							  $q = "";
                                    $q = "SELECT * FROM users where  userid='".$_SESSION['phone']."'";
                                    $r = mysqli_query($ahk_conn,$q);
                                    $rw = mysqli_fetch_assoc($r);
                                    
									
                               $aadharno = trim($_POST['aadharno']);
                               $name = trim($_POST['name']);
                               
                               $fathername = trim($_POST['fathername']);
                               $dobadhar = trim($_POST['dobadhar']);
                               $birthtithilocal = trim($_POST['birthtithilocal']);
                               $gender = strtoupper(trim($_POST['gender']));
                               $genderlocal = trim($_POST['genderlocal']);
                               $address = trim($_POST['address']);
                               $language = trim($_POST['language']);
                               $namelocal = trim($_POST['namelocal']);
							   $file = $_FILES['imagefile']['name'];
							  // $target_dir = "uploadssssssss/";
							  // $target_dir = "aadhar4/imgmanualaadhaar/";
							  $target_file = "/admin/aadharpvc/imgmanualaadhaar/".md5($_FILES["imagefile"]["name"]).".jpg";
                             //  $target_file = "aadhar4/imgmanualaadhaar/".$_FILES["imagefile"]["name"];
                               $localaddress = trim($_POST['addresslocal']);
                               $patalocal = trim($_POST['patalocal']);
                               $houseno = trim($_POST['houseno']);
                               $street = trim($_POST['street']);
                               $pincode = trim($_POST['pincode']);
                               $vtcandpost = trim($_POST['vtcandpost']);
                               $dist = trim($_POST['dist']);
                               $statename = trim($_POST['statename']);
                                                          
                               
                                if ($aadharno=="") {
                                   $msgno = 'Please Enter Aadhar Card No .... ';
                               }
else if ($rw['aadharpoint'] > $rw['balance']){
                                        $msgno= "Sorry, Your Balance is Low .... Please Recharge Soon";
                                        ?>
                                        <script>
                                        setTimeout(function () {
                                        window.location.href= 'aadharmanual.php';
                                        }, 2000);
                                        </script>
                                    <?php	
}									
                               elseif ($name=="") {
                                $msgno = 'Please Enter Name  .... ';
                               }
                               elseif ($fathername=="") {
                                $msgno = 'Please Enter Father Name  .... ';
                               }
                               elseif ($dobadhar=="") {
                                $msgno = 'Please Enter Date of Birth  .... ';
                               }
                               elseif ($gender=="") {
                                $msgno = 'Please Enter Gender  .... ';
                               }
                               elseif ($address=="") {
                                $msgno = 'Please Enter Address  .... ';
                               }
                               elseif ($language=="") {
                                $msgno = 'Please Enter Local Language  .... ';
                               }
                               elseif ($namelocal=="") {
                                $msgno = 'Please Enter Name in Local Language  .... ';
                               } 
                               elseif ($localaddress=="") {
                                $msgno = 'Please Enter Address in Local Language  .... ';
                               } 
                               else { 
                                   $a = mysqli_query($ahk_conn,"SELECT aadharno FROM aadharmanual Where aadharno='".$aadharno."'");
                                   $b = mysqli_fetch_array($a);
                                   if($b['aadharno']==$aadharno){
                                       $msgno = 'This Aadhar Card No Already Exist .... ';
                                   } else {
                                    $st='';
                                    $nd='';
                                    $rd='';
                                    $adhrno='';
                                     $st=substr($aadharno, 0, 4);
                                     $nd=substr($aadharno, 4, 4);
                                     $rd=substr($aadharno, 8, 4);
                                     $adhrno=$st.' '.$nd.' '.$rd;
                                     //echo $imgfpath;
                                     $sex='';
                                     if ($gender=='Male'){
                                        $sex='M'; 
                                     } 
                                     else {
                                        $sex='F';
                                     }
                                    /// insert value


                                    $resultm = mysqli_query($ahk_conn,"SELECT srno FROM aadharmanual ORDER BY srno DESC LIMIT 1");
					                $num_rows = mysqli_fetch_array($resultm);
					                $srno = $num_rows['srno']+1;
                                  
                                    $query='';
                                    $query = "INSERT INTO `aadharmanual`
                                    ( `aadharno`, `originalaadharno`, `aadharname`, `fathername`, `dob`, `gender`, `sexinlocal`, `fulladdress`,
                                     `locallanguage`, `localname`, `localaddress`, `imagepathoriginal`, `dobinlocal`, `pata`, `houseno`, `street`, `vtcandpost`, `dist`, `statename`,`pincode`,`srno`,`userid`,`createdatetime`) 
                                    VALUES ('".trim($aadharno)."','".trim($adhrno)."','".$name."','".$fathername."','".$dobadhar."','".$gender."',N'".$genderlocal."',
                                    '".$address."','".$language."',N'".$namelocal."',N'".$localaddress."','".$target_file."',N'".$birthtithilocal."',N'".$patalocal."','".$houseno."','".$street."','".$vtcandpost."','".$dist."','".$statename."','".$pincode."','".$srno."','".$_SESSION['phone']."',now())";
                                       $result = mysqli_query($ahk_conn, $query);
									   move_uploaded_file($_FILES["imagefile"]["tmp_name"], $target_file);
                                       $msg = "Please Wait Aadhar Preview just a second...";
                                       $_SESSION["IMGPATH"]='';
                                       $_SESSION["AADHAARNO"]=trim($aadharno);

                                   

                                    /// end insert
                                    /// start qr code

                                    mysqli_set_charset($ahk_conn,"utf8");
                                    $a = mysqli_query($ahk_conn,"SELECT * FROM aadharmanual Where aadharno='".$_SESSION["AADHAARNO"]."'");
                                    $b = mysqli_fetch_array($a);

                                    $remark="";
                                    $remark= 'Aadhar No : '.$b['aadharno'].' Aadhar Name : '.$b['aadharname'] ;
                                    // strat less point
                                    //  Dr amount start
									$getpoint = mysqli_fetch_assoc(mysqli_query($ahk_conn,"select * from users where userid=".$_SESSION['phone'].""));
                                    $qu = "";
                                    $qu = "INSERT INTO `tbltrans`(`userid`, `username`, `transdate`, `transqty`, `transtype`, `touserid`, `tousername`, `remark`, `loginid`, `logdate`)
                                    VALUES ('".$_SESSION['phone']."','".$_SESSION['username']."',now(),'".$getpoint['aadharpoint']."','Dr','0','Aadhaar Create','".$remark."','".$_SESSION['phone']."',now())";
                                    $a1q=mysqli_query($ahk_conn,$qu);
                                 
									
                                    $sql="";
									$sql = "update users SET balance= balance - 1 where userid='".$_SESSION['phone']."'";
									$abs = mysqli_query($ahk_conn, $sql);


// start copy
									$h = mysqli_fetch_assoc(mysqli_query($ahk_conn,"select * from users where userid=".$_SESSION['phone'].""));
		
$parentid =  $h['refrenceid'];
$noofc = 1;
$childid = $_SESSION['phone'];
date_default_timezone_set('Asia/Kolkata');
$timestamp = date("Y-m-d");
$cp = $noofc * $slct['discom'];;  //Distributor Amount
$cpc = $noofc * $slct['discom'];; //SuperDistibutor Amount
if($parentid != 0)
{
  mysqli_query($ahk_conn,"insert into commission_report(`userid`,`commission`,`cardprint`,`refid`,`date`)values(".$parentid.",".$cp.",".$noofc.",".$childid.",'".$timestamp."')");
   mysqli_query($ahk_conn,"update users set balance = balance + ".$cp." where userid=".$parentid."");
   $fetchmain = mysqli_fetch_assoc(mysqli_query($ahk_conn,"select * from users where userid=".$parentid.""));
   if($fetchmain['refrenceid'] != 0 or $fetchmain['refrenceid'] != 1)
   {
	  
   mysqli_query($ahk_conn,"insert into commission_report(`userid`,`commission`,`cardprint`,`refid`,`date`)values(".$fetchmain['refrenceid'].",".$cpc.",".$noofc.",".$parentid.",'".$timestamp."')");
   
   mysqli_query($ahk_conn,"update users set balance = balance + ".$cpc." where userid=".$fetchmain['refrenceid']."");
   }
		   }

 }
 ?>
                                   <script>
                                   setTimeout(function () {
                                      window.location.href= 'aadharlist.php';
                                   }, 2000);
                                   </script>
                                   <?php
                               }

                              }
                            ?>
							
							
							 <?php 
                              if(isset($_POST['savedataauto'])) {	
                             
							  $q = "";
                                    $q = "SELECT * FROM users where  userid='".$_SESSION['phone']."'";
                                    $r = mysqli_query($ahk_conn,$q);
                                    $rw = mysqli_fetch_assoc($r);
                                    
									
                               $aadharno = trim($_POST['aadharno']);
                               $name = trim($_POST['name']);
                               
                               $fathername = trim($_POST['fathername']);
                               $dobadhar = trim($_POST['dobadhar']);
                               $birthtithilocal = trim($_POST['birthtithilocal']);
                               $gender = strtoupper(trim($_POST['gender']));
                               $genderlocal = trim($_POST['genderlocal']);
                               $address = trim($_POST['address']);
                               $language = trim($_POST['language']);
                               $namelocal = trim($_POST['namelocal']);
							   $file = $_FILES['imagefile']['name'];
							  // $target_dir = "uploadssssssss/";
							  // $target_dir = "aadhar4/imgmanualaadhaar/";
							   
                             //  $target_file = $target_dir . basename($_FILES["imagefile"]["name"]);
                               $target_file = "/admin/aadharpvc/imgmanualaadhaar/".md5($_FILES["imagefile"]["name"]).".jpg";
                               $localaddress = trim($_POST['addresslocal']);
                               $patalocal = trim($_POST['patalocal']);
                               $houseno = trim($_POST['houseno']);
                               $street = trim($_POST['street']);
                               $pincode = trim($_POST['pincode']);
                               $vtcandpost = trim($_POST['vtcandpost']);
                               $dist = trim($_POST['dist']);
                               $statename = trim($_POST['statename']);
                                                          
                               
                                if ($aadharno=="") {
                                   $msgno = 'Please Enter Aadhar Card No .... ';
                               }
	
                               elseif ($name=="") {
                                $msgno = 'Please Enter Name  .... ';
                               }
                               elseif ($fathername=="") {
                                $msgno = 'Please Enter Father Name  .... ';
                               }
                               elseif ($dobadhar=="") {
                                $msgno = 'Please Enter Date of Birth  .... ';
                               }
                               elseif ($gender=="") {
                                $msgno = 'Please Enter Gender  .... ';
                               }
                               elseif ($address=="") {
                                $msgno = 'Please Enter Address  .... ';
                               }
                               elseif ($language=="") {
                                $msgno = 'Please Enter Local Language  .... ';
                               }
                               elseif ($namelocal=="") {
                                $msgno = 'Please Enter Name in Local Language  .... ';
                               } 
                               elseif ($localaddress=="") {
                                $msgno = 'Please Enter Address in Local Language  .... ';
                               } 
                               else { 
                               $a = mysqli_query($ahk_conn,"SELECT aadharno FROM aadharmanual Where aadharno='".$aadharno."'");
                               $b = mysqli_fetch_array($a);
                                   if($b['aadharno']==$aadharno){
                                       $msgno = 'This Aadhar Card No Already Exist .... ';
                                   } else {
                                    $st='';
                                    $nd='';
                                    $rd='';
                                    $adhrno='';
                                     $st=substr($aadharno, 0, 4);
                                     $nd=substr($aadharno, 4, 4);
                                     $rd=substr($aadharno, 8, 4);
                                     $adhrno=$st.' '.$nd.' '.$rd;
                                     //echo $imgfpath;
                                     $sex='';
                                     if ($gender=='Male'){
                                        $sex='M'; 
                                     } 
                                     else {
                                        $sex='F';
                                     }
                                    /// insert value

}}
                                    $resultm = mysqli_query($ahk_conn,"SELECT srno FROM aadharmanual ORDER BY srno DESC LIMIT 1");
					                $num_rows = mysqli_fetch_array($resultm);
					                $srno = $num_rows['srno']+1;
                                   $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='aadhar_manual_fee' "));
                                                $fee = $price['price'];
                                                $appliedby = $udata['phone'];
                                                if($udata['balance']>=$price['price']){
                                                    $fee = $price['price'];
                                                    $debit_fee = $udata['balance'] - $fee;
                                                    $debit = mysqli_query($ahk_conn,"UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                                                    $updatehistory = mysqli_query($ahk_conn,"INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','Aadhar Card Manual ','1','Debit')");
                                    if($debit && $updatehistory){
                                    $query='';
                                    $query = "INSERT INTO `aadharmanual`
                                    ( `aadharno`, `originalaadharno`, `aadharname`, `fathername`, `dob`, `gender`, `sexinlocal`, `fulladdress`,
                                     `locallanguage`, `localname`, `localaddress`, `imagepathoriginal`, `dobinlocal`, `pata`, `houseno`, `street`, `vtcandpost`, `dist`, `statename`,`pincode`,`srno`,`userid`,`createdatetime`) 
                                    VALUES ('".trim($aadharno)."','".trim($adhrno)."','".$name."','".$fathername."','".$dobadhar."','".$gender."',N'".$genderlocal."',
                                    '".$address."','".$language."',N'".$namelocal."',N'".$localaddress."','".$target_file."',N'".$birthtithilocal."',N'".$patalocal."','".$houseno."','".$street."','".$vtcandpost."','".$dist."','".$statename."','".$pincode."','".$srno."','".$_SESSION['phone']."',now())";
                                       $result = mysqli_query($ahk_conn, $query);
									   move_uploaded_file($_FILES["imagefile"]["tmp_name"], $target_file);
                                    if($result){
                                         
											?>
                <script>
                    $(function(){
                        Swal.fire(
                            'Application Submitted Success',
                            '',
                            'success'
                        )
                    })
                    setTimeout(() => {
                        window.location='aadharmanual_list.php';
                    }, 1500);
                </script>
                    <?php
 }else{
            ?>
            <script>
              $(function(){
                 Swal.fire(
                     'Opps',
                     'Try Again',
                     'error'
                 )
             })
             setTimeout(() => {
                    window.location='';
                }, 1500);
            </script>
             <?php
        }
            }
        
    }else{ 
        ?>
        <script>
          $(function(){
             Swal.fire(
                 'Wallet Balance is Low!',
                 'Please Recharge Now!',
                 'error'
             )
         })
         setTimeout(() => {
                window.location='wallet.php';
            }, 1500);
        </script>
         <?php
    }
    



}
                  
?>