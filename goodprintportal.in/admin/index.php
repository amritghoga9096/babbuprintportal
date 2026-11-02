<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/index.php');

?>
<?php
if (isset($_POST['txn_form']) && $_POST['txn_form'] == "ahkwebsolutions"  ){
  
  $name = mysqli_real_escape_string($ahk_conn, $_POST['name']);
  $email = mysqli_real_escape_string($ahk_conn, $_POST['email']);
  $sub = mysqli_real_escape_string($ahk_conn, $_POST['sub']);
  $mess = mysqli_real_escape_string($ahk_conn, $_POST['mess']);
  $res = mysqli_query($ahk_conn,"INSERT INTO `contact`(`name`, `email`, `sub`, `mess`,`status`,`reply`) VALUES ('$name','$email','$sub','$mess','pending','Not Solve Yet')");
  if($res){
      ?>
      <script>
          $(function(){
              Swal.fire(
                  'Success',
                  'Message Send Successfully',
                  'success'
              )

          });
           
  window.setTimeout(function() {
  window.location.href = "index.php";
  }, 1500);
      </script>
      <?php
  }else{
      ?>
      <script>
          $(function(){
              Swal.fire(
                  'Opps',
                  'Internal Server Problem Please Try Again Lates!',
                  'error'
              )

          });
      </script>
      <?php

  }
  
}
?>


<div class="modal fade" tabindex="-1" role="dialog" id="exampleModal">
<div class="modal-dialog" role="document">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Notification</h5>
<button type="button" class="close" data-dismiss="modal" aria-label="Close">
<span aria-hidden="true">&times;</span>
</button>
</div>
<div class="modal-body">
<p><img src="https://i.ytimg.com/vi/1PrgVtC5jFI/maxresdefault.jpg" style="width: 50%;"><br></p>
</div>
<div class="modal-footer bg-whitesmoke br">
<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
</div>
</div>
</div>
</div>

<script language="JavaScript">
//Disable right mouse click Script

var message="This Function is Disable";

function clickIE4(){
if (event.button==2){
alert(message);
return false;
 }
}

function clickNS4(e){
if (document.layers||document.getElementById&&!document.all){
if (e.which==2||e.which==3){
alert(message);
return false;
}
}
}

if (document.layers){
document.captureEvents(Event.MOUSEDOWN);
document.onmousedown=clickNS4;
}
else if (document.all&&!document.getElementById){
document.onmousedown=clickIE4;
}

document.oncontextmenu=new Function("alert(message);return false")

</script>
<script disable-devtool-auto src='https://cdn.jsdelivr.net/npm/disable-devtool@x.x.x'></script>
<!--Use latest version-->
<script disable-devtool-auto src='https://cdn.jsdelivr.net/npm/disable-devtool@latest'></script>

