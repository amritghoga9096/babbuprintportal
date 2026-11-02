<?php

global $ahk_conn;
$__svc = 'samagra_merge_family_id_fee';
$__res = isset($ahk_conn) ? @mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='$__svc' LIMIT 1") : false;
$__row = $__res ? mysqli_fetch_assoc($__res) : null;
define('SAMAGRA_MERGE_FAMILY_ID_FEE', $__row && isset($__row['price']) ? (float)$__row['price'] : 600);
unset($__svc, $__res, $__row);
define('SAMAGRA_MERGE_FAMILY_ID_MAX_FILE_SIZE', 5 * 1024 * 1024);

function ensureSamagraMergeFamilyIdTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `samagra_merge_family_id_requests` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(30) NOT NULL,
        `user_id` INT NULL,
        `user_phone` VARCHAR(20) NOT NULL,
        `family_id_1` VARCHAR(50) NOT NULL,
        `family_id_2` VARCHAR(50) NOT NULL,
        `original_family_id` VARCHAR(50) NOT NULL,
        `mukhiya_pdf_1` VARCHAR(255) NOT NULL,
        `mukhiya_pdf_2` VARCHAR(255) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 600.00,
        `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `admin_details` TEXT NULL,
        `result_pdf_path` VARCHAR(255) NULL,
        `rejection_reason` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_samagra_merge_family_id_application_no` (`application_no`),
        KEY `idx_samagra_merge_family_id_user_phone` (`user_phone`),
        KEY `idx_samagra_merge_family_id_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return mysqli_query($conn, $sql);
}

function samagraMergeFamilyIdApplicationNumber()
{
    return 'SMF' . date('ymdHis') . random_int(100, 999);
}

function samagraMergeFamilyIdAlert($title, $text, $icon, $redirect = '')
{
    $payload = json_encode([
        'title' => $title,
        'text' => $text,
        'icon' => $icon,
        'redirect' => $redirect
    ], JSON_UNESCAPED_UNICODE);

    echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
            var data = {$payload};
            Swal.fire({title:data.title, text:data.text, icon:data.icon}).then(function () {
                if (data.redirect) window.location = data.redirect;
            });
        });
    </script>";
}

function samagraMergeFamilyIdUploadPdf($fieldName, $directory)
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please upload ' . $fieldName . '.');
    }

    if ((int) $_FILES[$fieldName]['size'] > SAMAGRA_MERGE_FAMILY_ID_MAX_FILE_SIZE) {
        throw new RuntimeException('Each PDF must be 5MB or smaller.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$fieldName]['tmp_name']);
    if ($mime !== 'application/pdf') {
        throw new RuntimeException('Only PDF files are allowed.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $filename = bin2hex(random_bytes(16)) . '.pdf';
    $destination = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save uploaded PDF.');
    }

    return $filename;
}
