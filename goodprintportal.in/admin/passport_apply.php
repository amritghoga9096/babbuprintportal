<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/passport_apply.php');

if (isset($_POST['applicant_name']) && !empty($_POST['applicant_name']) && !empty($_POST['father_name']) && !empty($_POST['dob']) && !empty($_POST['mother_name']) && !empty($_POST['mobile_number']) && !empty($_POST['gmail_id']) && !empty($_POST['parent_contact_number'])) {
    $applicant_name = mysqli_real_escape_string($ahk_conn, $_POST['applicant_name']);
    $father_name = mysqli_real_escape_string($ahk_conn, $_POST['father_name']);
    $dob = mysqli_real_escape_string($ahk_conn, $_POST['dob']);
    $mother_name = mysqli_real_escape_string($ahk_conn, $_POST['mother_name']);
    $mobile_number = mysqli_real_escape_string($ahk_conn, $_POST['mobile_number']);
    $gmail_id = mysqli_real_escape_string($ahk_conn, $_POST['gmail_id']);
    $parent_contact_number = mysqli_real_escape_string($ahk_conn, $_POST['parent_contact_number']);
    $application_no = "KAFILA" . rand(00000000, 99999999);
    $appliedby = $udata['phone'];
    $date = date("jS \of F Y h:i:s A");
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='panaply' "));

    if ($udata['balance'] >= $price['price']) {
        $fee = $price['price'];
        $nbal = $udata['balance'] - $price['price'];
        $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
        $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$nbal','New Pan Apply','1','Debit')");

        if ($debit && $updatehistory) {
            // File upload handling
            $aadharPdfPath = ''; // Placeholder for Aadhar PDF path
            $panPdfPath = ''; // Placeholder for Pan PDF path

            if (isset($_FILES['aadhar_pdf']) && $_FILES['aadhar_pdf']['error'] === 0) {
                $targetDirectory = "uploads/";
                $targetFileName = $targetDirectory . basename($_FILES['aadhar_pdf']['name']);
                $aadharPdfPath = mysqli_real_escape_string($ahk_conn, $targetFileName);

                if (move_uploaded_file($_FILES['aadhar_pdf']['tmp_name'], $targetFileName)) {
                    // File upload successful
                } else {
                    // Failed to upload Aadhar PDF
                    ?>
                    <script>
                        $(function(){
                            Swal.fire(
                                'Oops',
                                'Failed to upload Aadhar PDF',
                                'error'
                            )
                        })
                        setTimeout(() => {
                            window.location = '';
                        }, 1200);
                    </script>
                    <?php
                }
            }

            if (isset($_FILES['pan_pdf']) && $_FILES['pan_pdf']['error'] === 0) {
                $targetDirectory = "uploads/";
                $targetFileName = $targetDirectory . basename($_FILES['pan_pdf']['name']);
                $panPdfPath = mysqli_real_escape_string($ahk_conn, $targetFileName);

                if (move_uploaded_file($_FILES['pan_pdf']['tmp_name'], $targetFileName)) {
                    // File upload successful
                } else {
                    // Failed to upload Pan PDF
                    ?>
                    <script>
                        $(function(){
                            Swal.fire(
                                'Oops',
                                'Failed to upload Pan PDF',
                                'error'
                            )
                        })
                        setTimeout(() => {
                            window.location = '';
                        }, 1200);
                    </script>
                    <?php
                }
            }

            // Database insertion
            $submit = mysqli_query($ahk_conn, "INSERT INTO `passport_applications` (`appliedby`, `application_no`, `applicant_name`, `father_name`, `dob`, `mother_name`, `mobile_number`, `gmail_id`, `parent_contact_number`, `pan_pdf_path`, `aadhar_pdf_path`, `status`, `fee`, `date`) VALUES ('$appliedby', '$application_no', '$applicant_name', '$father_name', '$dob', '$mother_name', '$mobile_number', '$gmail_id', '$parent_contact_number', '$panPdfPath', '$aadharPdfPath', 'pending', '$fee', '$date')");

            if ($submit) {
                ?>
                <script>
                    $(function(){
                        Swal.fire(
                            'Application Submitted Successfully',
                            '',
                            'success'
                        )
                    })
                    setTimeout(() => {
                        window.location = '';
                    }, 1200);
                </script>
                <?php
            } else {
                ?>
                <script>
                    $(function(){
                        Swal.fire(
                            'Oops',
                            'Try Again1',
                            'error'
                        )
                    })
                    setTimeout(() => {
                        window.location = '';
                    }, 1200);
                </script>
                <?php
            }
        } else {
            ?>
            <script>
                $(function(){
                    Swal.fire(
                        'Oops',
                        'Try Again2',
                        'error'
                    )
                })
                setTimeout(() => {
                    window.location = '';
                }, 1200);
            </script>
            <?php
        }
    } else {
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
                window.location = 'wallet.php';
            }, 1200);
        </script>
        <?php
    }
}
?>
