<?php
include('../includes/session.php');
include('../includes/config.php');
if(checkAdmin($udata['type']) == false){
    ?>
    <script>
        window.location='index.php';
    </script>
    <?php
    die();
}
include('../template/ahkweb/SetComission.php');
if(isset($_POST['dist_com'])){
    $airtel_com = mysqli_real_escape_string($ahk_conn,$_POST['airtel_com']);
    $jio_com = mysqli_real_escape_string($ahk_conn,$_POST['jio_com']);
    $bsnl_com = mysqli_real_escape_string($ahk_conn,$_POST['bsnl_com']);
    $vi_com = mysqli_real_escape_string($ahk_conn,$_POST['vi_com']);
    $dist_com = mysqli_real_escape_string($ahk_conn,$_POST['dist_com']);
 

    $update = mysqli_query($ahk_conn,"UPDATE settings SET airtel_com='$airtel_com', jio_com='$jio_com', bsnl_com='$bsnl_com' , vi_com='$vi_com', dist_com='$dist_com' WHERE id=1");
    if($update){
        ?>
        <script>
        $(function(){
            Swal.fire(
                'Comission Updated Successfully',
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
                'Comission Not Updated',
                '',
                'error'
            )
        })
    </script>
    <?php
    }
}
?>