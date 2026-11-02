<?php
include('../includes/session.php');
include('../includes/config.php');
if(checkAdmin($udata['type']) == false){
    ?>
    <script>
        window.location='index';
    </script>
    <?php
    die();
}
include('../template/ahkweb/users.php');
if(isset($_GET['id']) && $_GET['id'] !=NULL && $_GET['reset'] == 1){
    $id = base64_decode($_GET['id']);
    $vpass = rand(000000,999999);
    $pass = password_hash($vpass,PASSWORD_DEFAULT);
    $reset = mysqli_query($ahk_conn,"UPDATE users SET password='$pass' WHERE id='$id'");
    if($reset){
        ?>
    <script>
        $(function(){
            Swal.fire(
                'Password Reset Successfully',
                'New: <?php echo $vpass; ?>',
                'success'
            )
        })
    </script>
    <?php
    }
}
if(isset($_GET['login']) && $_GET['login'] ==1 && $_GET['phone'] && $_GET['phone']!=NULL){
    $phone = base64_decode($_GET['phone']);
    $_SESSION['phone'] = $phone;
    $_SESSION['adminasuser']  = true;
    $_SESSION['adminusername']  = $udata['phone'];
    ?>
    <script>
         setTimeout(function () {
                window.location='';
            }, 00);
    </script>
    <?php
    
}
?>