<?php
include('../includes/session.php');
include('../includes/config.php');

include('../template/ahkweb/adduser.php');
if(isset($_POST['name'])){
    $name = mysqli_real_escape_string($ahk_conn,$_POST['name']);
    $phone = mysqli_real_escape_string($ahk_conn,$_POST['phone']);
    $email = mysqli_real_escape_string($ahk_conn,$_POST['email']);
    $type = "retailer";
    if($udata['type'] == "admin" || $udata['type'] == "Admin"){
        $type = mysqli_real_escape_string($ahk_conn,$_POST['type']);
    }else{
        $type = "retailer";
    }

    $password = mysqli_real_escape_string($ahk_conn,$_POST['password']);
    if(mysqli_num_rows(mysqli_query($ahk_conn,"SELECT * FROM users WHERE phone='$phone'")) ==0){
        $vpass = password_hash($password,PASSWORD_DEFAULT);
        $insert = mysqli_query($ahk_conn,"INSERT INTO users (`parent`,`name`,`phone`,`email`,`type`,`password`,`status`) VALUES('".$udata['phone']."','$name','$phone','$email','$type','$vpass','1')");
            if($insert){
                ?>
                <script>
                $(function(){
                    Swal.fire(
                        'User Created Successfully',
                        '',
                        'success'
                    )
                })
                setTimeout(() => {
                    window.location='users.php';
                }, 1200);
            </script>
            <?php
            }else{
                ?>
                <script>
                $(function(){
                    Swal.fire(
                        'User Not Created',
                        '',
                        'error'
                    )
                })
            </script>
            <?php
            }
    }
    
}
?>