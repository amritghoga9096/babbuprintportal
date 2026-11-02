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
include('../template/ahkweb/notifications.php');
if(isset($_GET['delete']) && $_GET['id']){
    $id = base64_decode(mysqli_real_escape_string($ahk_conn,$_GET['id']));
    $delete = mysqli_query($ahk_conn,"DELETE FROM notification WHERE id='$id'");
    if($delete){
        ?>
        <script>
        $(function(){
            Swal.fire(
                'Notifications Deleted Successfully',
                '',
                'success'
            )
        })
        setTimeout(() => {
            window.location='notifications.php';
        }, 1200);
    </script>
    <?php
    }
}
if(isset($_POST['message'])&& $_POST['new'] && $_POST['new'] =="1"){
    $message = mysqli_real_escape_string($ahk_conn,$_POST['message']);
    $update = mysqli_query($ahk_conn,"INSERT INTO notification (`message`,`status`) VALUES('$message','1')");
    if($update){
        ?>
        <script>
        $(function(){
            Swal.fire(
                'Notifications Updated Successfully',
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
                'Settings Not Updated',
                '',
                'error'
            )
        })
    </script>
    <?php
    }
}
// Update
if(isset($_POST['message']) && $_POST['id'] && $_POST['edit'] && $_POST['edit'] =="1"){
    $message = mysqli_real_escape_string($ahk_conn,$_POST['message']);
    $id = mysqli_real_escape_string($ahk_conn,$_POST['id']);
    $status = mysqli_real_escape_string($ahk_conn,$_POST['status']);
    $update = mysqli_query($ahk_conn,"UPDATE `notification` SET `message`='$message',`status`='$status' WHERE id='$id'");
    if($update){
        ?>
        <script>
        $(function(){
            Swal.fire(
                'Notifications Updated Successfully',
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
                'Settings Not Updated',
                '',
                'error'
            )
        })
    </script>
    <?php
    }
}
?>