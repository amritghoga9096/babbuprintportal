<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/balance.php');
// 
if(isset($_POST['amount']) && $_POST['amount'] != NULL){
    if($_POST['mode'] =="paytm"){
    $ORDER_ID =  $udata['phone'] . rand(1000,99999);
    $amount = mysqli_real_escape_string($ahk_conn,$_POST['amount']);
    $email = mysqli_real_escape_string($ahk_conn,$_POST['email']);
    $ins = mysqli_query($ahk_conn,"INSERT INTO `wallet`(`phone`,`amount`,`txn_id`,`email`,`status`) VALUES ('".$udata['phone']."','$amount','$ORDER_ID','$email','pending')");

    if($ins){
        
       

    }
}else if($_POST['mode'] == "payu"){
    // Payu Code Start Here 
    $ORDER_ID =  $udata['phone'] . rand(1000,99999);
    $amount = mysqli_real_escape_string($ahk_conn,$_POST['amount']);
    $email = mysqli_real_escape_string($ahk_conn,$_POST['email']);
    $ins = mysqli_query($ahk_conn,"INSERT INTO `wallet`(`phone`,`amount`,`txn_id`,`email`,`status`) VALUES ('".$udata['phone']."','$amount','$ORDER_ID','$email','pending')");

    if($ins){
         ?>

</script>
    <?php 
    }
    // Payu Code End Here 
}else if($_POST['mode'] == "sam"){
    // Payu Code Start Here 
    $ORDER_ID =  $udata['phone'] . rand(1000,99999);
    $amount = mysqli_real_escape_string($ahk_conn,$_POST['amount']);
    $email = mysqli_real_escape_string($ahk_conn,$_POST['email']);
    $ins = mysqli_query($ahk_conn,"INSERT INTO `wallet`(`phone`,`amount`,`txn_id`,`email`,`status`) VALUES ('".$udata['phone']."','$amount','$ORDER_ID','$email','pending')");

    if($ins){
         ?>
<form class="mt-2" method="post" name="f2" action="../payment/index.php">
    
 <input tabindex="2" type="hidden" maxlength="15" size="15" name="emailid" autocomplete="off" value="<?php echo $udata['email'];?>">
 <!--<input type="number" class="form-control" min="200" id="amount" name="amount" required="" placeholder="ENTER AMOUNT HERE">-->
  <input tabindex="3" type="hidden" maxlength="15" size="15" name="phone" autocomplete="off" value="<?php echo $udata['phone'];?>">
 <input tabindex="3" type="hidden" maxlength="15" size="15" name="name" autocomplete="off" value="<?php echo $udata['name'];?>">
<!--<input  type="hidden"  name="pay_uid"  value="<?php echo $udata['phone']; ?>">-->
<input  type="hidden"  name="order_id"  value="<?php echo $ORDER_ID; ?>">
<input type="hidden" name="amount" value="<?php echo $amount; ?>">
</form>
<script type="text/javascript">
document.f2.submit();
</script>
    <?php 
    }
}else if($_POST['mode'] == "upi"){
    // UPI Code Start Here 
    date_default_timezone_set("Asia/Kolkata");
    $date = date("d-m-Y");
    $ORDER_ID =  $udata['phone'] . rand(1000,99999);
    $amount = mysqli_real_escape_string($ahk_conn,$_POST['amount']);
    $email = mysqli_real_escape_string($ahk_conn,$_POST['email']);
    $ins = mysqli_query($ahk_conn,"INSERT INTO `wallet`(`phone`,`amount`,`txn_id`,`email`,`status`,`date`) VALUES ('".$udata['phone']."','$amount','$ORDER_ID','$email','pending','$date')");

    if($ins){
         ?>

</script>
    <?php 
    }
    // UPI Code END Here 
}else if($_POST['mode'] == "qr"){
    // QR Code Start Here 
    date_default_timezone_set("Asia/Kolkata");
    $date = date("d-m-Y");
    $ORDER_ID =  $udata['phone'] . rand(1000,99999);
    $amount = mysqli_real_escape_string($ahk_conn,$_POST['amount']);
    $email = $udata['email'];
    $ins = mysqli_query($ahk_conn,"INSERT INTO `wallet`(`phone`,`amount`,`txn_id`,`email`,`status`,`date`,`PAYMENTMODE`) VALUES ('".$udata['phone']."','$amount','$ORDER_ID','$email','pending','$date','QR')");

    if($ins){
        $_SESSION['wuphone']=$udata['phone']; ?>
         
<form class="mt-2" method="post" name="f2" action="../HBconnect4in1/secure.php">
<input  type="hidden" name="order_id"  value="<?php echo $ORDER_ID; ?>">
<input type="hidden"  name="phone" value="<?php echo $udata['phone'] ?>">
<input type="hidden" name="amount" value="<?php echo $amount; ?>">
</form>
<script type="text/javascript">
document.f2.submit();
</script>
    <?php 
    }
    // QR Code END Here 
}
    


}
// isset End
if(isset($_POST['successmsg']) && $_POST['successmsg'] == "true"){
    ?>
    <script>
        $(function(){
            Swal.fire(
                'Payment Added Successfully',
                'Your Payment Added!',
                'success'
            )
        })
    </script>
    <?php
}
if(isset($_POST['failedmsg']) && $_POST['failedmsg'] == "true"){
    ?>
    <script>
        $(function(){
            Swal.fire(
                'Payment Added Failed',
                'if Payment Deduct Then Contact us!',
                'error'
            )
        })
    </script>
    <?php
}
// UPI Code Success 
if(isset($_POST['success']) && $_POST['success'] == "true"){
    ?>
    <script>
        $(function(){
            Swal.fire(
                'Payment Added Successfully',
                'Your Payment Added!',
                'success'
            )
        })
    </script>
    <?php
}
if(isset($_POST['failed']) && $_POST['failed'] == "true"){
    ?>
    <script>
        $(function(){
            Swal.fire(
                'Payment Added Failed',
                'if Payment Deduct Then Contact us!',
                'error'
            )
        })
    </script>
    <?php
}
if(isset($_POST['success1']) && $_POST['success1'] == "true"){
    ?>
    <script>
        $(function(){
            Swal.fire(
                'Payment Already updated',
                'if Payment Not Update Then Contact us!',
                'success'
            )
        })
    </script>
    
    
    <?php
}
?>