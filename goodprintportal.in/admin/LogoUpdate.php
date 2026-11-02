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
include('../template/ahkweb/LogoUpdate.php');
if(isset($_POST['lg'])){
   // Upload Files
   $file = $_FILES['logo'] ?? null;
   if (!$file || empty($file['name']) || !is_uploaded_file($file['tmp_name'])) {
       ?>
       <script>
           $(function(){
               Swal.fire(
                   'No Logo Selected!',
                   'Please choose a logo image first.',
                   'error'
               );
           });
       </script>
       <?php
   } else {
       $allowed = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
       $type = strtolower($file['type'] ?? '');
       if (in_array($type, $allowed, true)) {
           // ============================================================
           // MARK: LOGO UPLOAD TO ROOT (public_html) — taaki domain.com/logof.png direct accessible ho
           // Old path was: admin/uploads/...
           // New path:  ROOT folder (public_html) → https://goodprintportal.in/
           // Static alias:  logof.png  (NOT logo.png — taaki manually-dala logo.png untouched rahe)
           // ============================================================
           $rootDir = __DIR__ . '/../'; // goes UP from /admin/ to / (public_html)
           if (!is_dir($rootDir)) {
               // fallback (highly unlikely, root always exists)
               $rootDir = __DIR__ . '/uploads';
               if (!is_dir($rootDir) && !mkdir($rootDir, 0775, true)) {
                   ?>
                   <script>
                       $(function(){
                           Swal.fire(
                               'Error While Creating Upload Folder!',
                               '',
                               'error'
                           );
                       });
                   </script>
                   <?php
               }
           }
           $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename($file['name']));
           $targetPath = rtrim($rootDir, '/\\') . '/' . $safeName;

           if (move_uploaded_file($file['tmp_name'], $targetPath)) {
               // --- ALWAYS KEEP A STATIC COPY AS /logof.png taaki favicons + templates isse serve karein ---
               // NOTE: /logo.png (manually dala hua) untouched rahega — hamlog ab logof.png use kar rahe hain
               $staticLogo = rtrim($rootDir, '/\\') . '/logof.png';
               @copy($targetPath, $staticLogo);

               // --- Save in DB: /logof.png absolute path works from BOTH /admin/* AND /* pages ---
               $safeLink = '/logof.png';
               $safeLinkEsc = mysqli_real_escape_string($ahk_conn, $safeLink);
               if (ahkQuery("UPDATE settings SET logo='$safeLinkEsc' WHERE id=1")) {
                   showAlert('Logo Updated Successfully!');
                   ahkRedirect('', 0);
               } else {
                   ?>
                   <script>
                       $(function(){
                           Swal.fire(
                               'Error While Saving Logo!',
                               '',
                               'error'
                           );
                       });
                   </script>
                   <?php
               }
           } else {
               ?>
               <script>
                   $(function(){
                       Swal.fire(
                           'Error While Uploading Files!',
                           '',
                           'error'
                       );
                   });
               </script>
               <?php
           }
       } else {
           ?>
           <script>
               $(function(){
                   Swal.fire(
                       'Wrong File Type!',
                       'JPEG, PNG, GIF and WEBP Allowed only',
                       'error'
                   );
               });
           </script>
           <?php
       }
   }
   // Upload End
}
?>