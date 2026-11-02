<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/pan_manual.php');

?>
 <?php
                           if($udata['balance'] < 10){
                               ?>
                               <script>
                             alert("Dear user your Wallet Balance is Low Please Recharge Now");
                             window.location.href = "../admin/index.php";
                           </script>

                            <?php 
                                 }
                              elseif(isset($_POST['savedata'])) {	

                             
 
							  $q = "";

                                    $q = "SELECT * FROM users where phone='".$_SESSION['phone']."'";

                                    $r = mysqli_query($ahk_conn,$q);

                                    $rw = mysqli_fetch_assoc($r);

                                    

									

                               $aadharno = trim($_POST['pannumber']);

                               $name = trim($_POST['name']);

                               

                               $fathername = trim($_POST['fathername']);

                               $dobadhar = trim($_POST['dobadhar']);
                               
                               $gender = trim($_POST['gender']);

                               

							   $file = $_FILES['imagefile']['name'];

							   $target_dir = "uploads/";
							   $imgExt = pathinfo(basename($_FILES["imagefile"]["name"]), PATHINFO_EXTENSION);
							   $imgUnique = time().'_'.md5(rand()).'.'.$imgExt;
                               $target_file = $target_dir . $imgUnique;

							   

							   $signfile = $_FILES['signfile']['name'];

							   $sign_dir = "uploads/";
							   $signExt = pathinfo(basename($_FILES["signfile"]["name"]), PATHINFO_EXTENSION);
							   $signUnique = time().'_'.md5(rand()+1).'.'.$signExt;
                               $sign_file = $sign_dir . $signUnique;

							   

                               

                                                          

                               

                                if ($aadharno=="") {

                                   $msgno = 'Please Enter Pan Card No .... ';

                               }

else if ($rw['aadharpoint'] > $rw['balance']){

                                        $msgno= "Sorry, Your Balance is Low .... Please Recharge Soon";

                                        ?>

                                        <script>

                                        setTimeout(function () {

                                        window.location.href= 'panmanual.php';

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

                                

                               else { 

                                   $a = mysqli_query($ahk_conn,"SELECT aadharno FROM panauto Where panno ='".$aadharno."'");

                                   $b = mysqli_fetch_array($a);

                                   if($b['panno']==$aadharno){

                                       $msgno = 'This Pan Card No Already Exist .... ';

                                   } else {

                                    

                                    /// insert value
                                    $word = "image";
									  $sign_image_type = $_FILES["signfile"]["type"];
									  $image_type = $_FILES["imagefile"]["type"];
									  
									    if((strpos($sign_image_type, $word) !== false) && (strpos($image_type, $word) !== false)){
                                            //echo "Word Found!";




                                    

                                  date_default_timezone_set('Asia/Kolkata');

$timestamp = date("Y-m-d H:i:s");

                                    $query='';
                                    $query = "insert into panauto(`userid`,`panno`,`name`,`fathername`,`dob`,`gender`,`image`,`signimage`,`create_time`)values('".$_SESSION['phone']."','".$aadharno."','".$name."','".$fathername."','".$dobadhar."','".$gender."','".$target_file."','".$sign_file."','".$timestamp."')";

									  $result = mysqli_query($ahk_conn, $query);
                                      
                                      if($result){
									   $upload_ok1 = move_uploaded_file($_FILES["imagefile"]["tmp_name"], $target_file);
									   $upload_ok2 = move_uploaded_file($_FILES["signfile"]["tmp_name"], $sign_file);
                                       if(!$upload_ok1 || !$upload_ok2){
                                           $msgno = "Image upload failed! Please check folder permissions.";
                                       }
                                      }

                                       $msg = "Please Wait Pan Priveiw just a second...";

                                       $_SESSION["IMGPATH"]='';

                                       $_SESSION["Panno"]=trim($aadharno);


if ($result) {
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='instant_pan_manual_fee' "));
    $fee = $price['price'];
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;
    $debit_fee = $udata['balance'] - $fee;
        $debit = mysqli_query($ahk_conn,"UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
        mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$appliedby','$fee','$debit_fee','Pan Manual','1','Debit')");
    echo '<script>
        $(function(){
            Swal.fire(
                "' . $msg . '",
                "",
                "success"
            );
        });
        setTimeout(function () {
            window.location = "pan_manual_list.php";
        }, 1200);
    </script>';
}


                                   



                                    /// end insert

                                    /// start qr code



                                    mysqli_set_charset($ahk_conn,"utf8");

                                    $a = mysqli_query($ahk_conn,"SELECT * FROM panauto Where panno='".$_SESSION["Panno"]."'");

                                    $b = mysqli_fetch_array($a);



                                    $remark="";

                                    $remark= 'Pan No : '.$b['panno'].' Pan Name : '.$b['name'] ;

                                    // strat less point//  Dr amount start

									$getpoint = mysqli_fetch_assoc(mysqli_query($ahk_conn,"select * from users where phone=".$_SESSION['phone'].""));

                                    $qu = "";

                                    $qu = "INSERT INTO `tbltrans`(`userid`, `username`, `transdate`, `transqty`, `transtype`, `touserid`, `tousername`, `remark`, `loginid`, `logdate`)

                                    VALUES ('".$_SESSION['phone']."','".$_SESSION['username']."',now(),'".$getpoint['aadharpoint']."','Dr','0','Pan Create','".$remark."','".$_SESSION['phone']."',now())";

                                    $a1q=mysqli_query($ahk_conn,$qu);

                                    //  Dr amount end

                                   // end point





                                   //echo $b['wamt'];

									// start led wallet



									    }else{
                                            $msgno = "Please select PNG and JPEG image only.";
                                            //sleep(5);
                                        }


                                   }

                                   

                                   ?>

                                   <script>

                                   setTimeout(function () {

                                      window.location.href= '#';

                                   }, 5000);

                                   </script>

                                   <?php

                               }



                              }

                            ?>
                            
<?php
if (!empty($msgno)) {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            alert("' . $msgno . '");
        });
    </script>';
}
?>

