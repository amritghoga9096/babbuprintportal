<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/bapply.php');
if(isset($_POST['father_name']) && !empty($_POST['name'])  && !empty($_POST['state']) && !empty($_POST['date'])  && !empty($_POST['ret_wp_no']) ){
    $name = mysqli_real_escape_string($ahk_conn,$_POST['name']);
   
    $father_name = mysqli_real_escape_string($ahk_conn,$_POST['father_name']);
    $date = mysqli_real_escape_string($ahk_conn,$_POST['date']);
    $state = mysqli_real_escape_string($ahk_conn,$_POST['state']);
    $ret_wp_no = mysqli_real_escape_string($ahk_conn,$_POST['ret_wp_no']);
    $fee = mysqli_real_escape_string($ahk_conn,$_POST['fee']);
    $application_no = "KAFILA". rand(00000,99999);
    $appliedby = $udata['phone'];
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='kafilapass' "));
    if($udata['balance']>=$price['price']){
        $fee = $price['price'];
        $nbal = $udata['balance']-$price['price'];
        $debit = mysqli_query($ahk_conn,"UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
        $updatehistory = mysqli_query($ahk_conn,"INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$nbal','LL PASS','1','Debit')");
        if($debit && $updatehistory){
        $submit = mysqli_query($ahk_conn,"INSERT INTO `kafilapass` (`appliedby`, `name`, `father_name`, `date`, `state`, `status`, `ret_wp_no`,`fee`,`application_no`) VALUES('$appliedby','$name','$father_name','$date','$state','pending','$ret_wp_no','$fee','$application_no')");
        if($submit == true){
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
                        window.location='';
                    }, 1200);
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
                }, 1200);
            </script>
             <?php
        }
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
                    }, 1200);
                </script>
                 <?php
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
            }, 1200);
        </script>
         <?php
       
    }
    


}
?>