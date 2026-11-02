<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/profile.php');
// Password Change  Code
if(isset($_POST['curpass']) && $_POST['curpass']!=NULL && !empty($_POST['cpass']) && !empty($_POST['npass'])){
    $curpass = mysqli_real_escape_string($ahk_conn,$_POST['curpass']);
    $npass = mysqli_real_escape_string($ahk_conn,$_POST['npass']);
    $cpass = mysqli_real_escape_string($ahk_conn,$_POST['cpass']);
    $check = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM users WHERE phone='".$_SESSION['phone']."'"));
    if(password_verify($curpass,$check['password']) == true){
        if($npass == $cpass){
            $vpass = password_hash($cpass,PASSWORD_DEFAULT);
            $update = mysqli_query($ahk_conn,"UPDATE users SET password='$vpass' WHERE phone='".$_SESSION['phone']."'");
                if($update){
                    ?>
                    <script>
                         $(function(){
                        Swal.fire(
                            'Password Changed Successfully!',
                            '',
                            'success'
                        )
                    })
                    </script>
                    <?php 
                }
        }else{
            ?>
        <script>
             $(function(){
            Swal.fire(
                'Confirm Password not Match',
                'Please Enter valid Confirm Password!',
                'error'
            )
        })
        </script>
        <?php 
        }
    }else{
        ?>
        <script>
             $(function(){
            Swal.fire(
                'Current Password Wrong',
                'Enter Valid Current Password!',
                'error'
            )
        })
        </script>
        <?php 
    }

}
// Update Profile Code
if(isset($_POST['phone']) && 
!empty($_POST['email']) && 
!empty($_POST['name']) && 
!empty($_POST['address']))
{
    $name= mysqli_real_escape_string($ahk_conn,$_POST['name']);
    $email= mysqli_real_escape_string($ahk_conn,$_POST['email']);
    $phone= mysqli_real_escape_string($ahk_conn,$_POST['phone']);
    $address= mysqli_real_escape_string($ahk_conn,$_POST['address']);

    $update = mysqli_query($ahk_conn,"UPDATE users SET name='$name', email='$email',address='$address' WHERE phone='$phone'");
    if($update){
        
        ?>
         <script>
             $(function(){
            Swal.fire(
                'Profile Updated Successfully',
                '',
                'success'
            )
        })
        setTimeout(() => {
            window.location.href='';
        }, 1200);
        </script>
        <?php

    }else{
        ?>
        <script>
            $(function(){
           Swal.fire(
               'Something Went Wrong!',
               'Try Again',
               'error'
           )
       })
       </script>
       <?php
    }
}



?>