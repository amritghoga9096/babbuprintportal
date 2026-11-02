<?php

global $ahk_conn;
$__svc = 'samagra_pdf_fee';
$__res = isset($ahk_conn) ? @mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='$__svc' LIMIT 1") : false;
$__row = $__res ? mysqli_fetch_assoc($__res) : null;
define('SAMAGRA_PDF_FEE', $__row && isset($__row['price']) ? (float)$__row['price'] : 20);
unset($__svc, $__res, $__row);

function ensureSamagraPdfTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `samagra_pdf_requests` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `request_no` VARCHAR(30) NOT NULL,
        `user_id` INT NULL,
        `user_phone` VARCHAR(20) NOT NULL,
        `samagra_id` VARCHAR(9) NOT NULL,
        `id_type` ENUM('family','member') NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 20.00,
        `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `admin_details` TEXT NULL,
        `pdf_path` VARCHAR(255) NULL,
        `rejection_reason` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_samagra_request_no` (`request_no`),
        KEY `idx_samagra_user_phone` (`user_phone`),
        KEY `idx_samagra_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return mysqli_query($conn, $sql);
}

function samagraRequestNumber()
{
    return 'SMG' . date('ymdHis') . random_int(100, 999);
}

function samagraAlert($title, $text, $icon, $redirect = '')
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
