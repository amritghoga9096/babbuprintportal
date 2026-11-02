<?php
include('../includes/session.php');
include('../includes/config.php');

 if(isset($_POST["familyid"]))  
 {  
     
    /* $familyid = $_POST['familyid'];
$id=$_POST['id'];
$phone=$_POST['userid'];
$stateid=$_POST['stid'];
   */ 
        $familyid = mysqli_real_escape_string($ahk_conn, $_POST["familyid"]);  
      $id = mysqli_real_escape_string($ahk_conn, $_POST["id"]); 
      $phone = mysqli_real_escape_string($ahk_conn, $_POST["phone"]);
       $stid = mysqli_real_escape_string($ahk_conn, $_POST["stid"]);
       $sdusername = mysqli_real_escape_string($ahk_conn, $_POST["sdusername"]);
       $dffthername = mysqli_real_escape_string($ahk_conn, $_POST["dffthername"]);
        $creatdatson = mysqli_real_escape_string($ahk_conn, $_POST["creatdatson"]);
   date_default_timezone_set("Asia/Kolkata");
$date = date('d-M-Y h:i:s');
$stds="1";
 date_default_timezone_set("Asia/Kolkata");
$datecheck = date('d-m-Y');



     $result = mysqli_query($ahk_conn,"SELECT * FROM `ayushmancard_save_date` WHERE  pairameeter_ayus='$familyid' && name_aush='$sdusername' && parameeter_id='$phone'");
$count=mysqli_num_rows($result);
if($count==1)
{
    include("mail.php");
    
    ?>
        <p class="alert alert-danger">Hi, <?php echo $sdusername; ?> Ayushmancard Already Saved.......Check Print List </p>
    
    <?php
}
   else{
       
       
       $dfdhsf="INSERT INTO `ayushmancard_save_date`(`name_aush`, `father_name`, `crteate_on_date`, `type_proof`, `pairameeter_ayus`, `stats_id`, `parameeter_id`, `payment_status`, `date_check`, `save_date`) VALUES 
       ('".$sdusername."','".$dffthername."','".$creatdatson."','".$id."','".$familyid."','".$stid."','".$phone."','".$stds."','".$datecheck."','".$date."')";
      
      if(mysqli_query($ahk_conn, $dfdhsf))  
      { 
          ?> 
        <p class="alert alert-success">Hi, <?php echo $sdusername; ?> Ayushman Card Saved Successful  <a class="btn btn-primary" href="print_ayushman.php"><i class="fa fa-check-circle"> Click Print List</i></a></p>

        <?php
          
      } 
   }
 }  
 ?>  