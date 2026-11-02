<?php
// ============================================================
// PROJECT STANDARD: ob_start to prevent "headers already sent"
// ============================================================
@ob_start();

require_once('../includes/session.php');
require_once('../includes/config.php');

if (!function_exists('checkAdmin')) {
    // fallback: session/config may have it declared; just safe guard
}

if (empty($udata) || !isset($udata['type']) || checkAdmin($udata['type']) == false) {
    header('Location: index.php');
    die();
}

// ============================================================
// FORM HANDLERS — MUST run BEFORE template include so that
// header() redirects (ahkRedirect) work BEFORE any HTML output.
// Otherwise: "Headers already sent" fatal error → 500 / blank page.
// ============================================================

// -- Helper: ensures writable root dir fallback --
if (!function_exists('ahkweb_resolve_root_dir')) {
    function ahkweb_resolve_root_dir() {
        $dir = __DIR__ . '/../';
        if (is_dir($dir)) return $dir;
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

// -- Helper: shows Swal on next render via session flash --
if (!function_exists('ahkweb_swal_flash')) {
    function ahkweb_swal_flash($title, $text, $icon = 'success') {
        // Store flash in session so Swal renders AFTER redirect
        @session_start();
        if (!isset($_SESSION['_swal_flashes'])) $_SESSION['_swal_flashes'] = [];
        $_SESSION['_swal_flashes'][] = [
            'title' => $title,
            'text'  => $text,
            'icon'  => $icon,
        ];
    }
}

// ====== OPTION 1 — Main Logo → /logomain.png ======
if (isset($_POST['mlg'])) {
    $file = $_FILES['mainlogo'] ?? null;
    $redirect = true;

    if (!$file || empty($file['name']) || !is_uploaded_file($file['tmp_name'] ?? '')) {
        ahkweb_swal_flash('No Main Logo Selected!', 'Please choose a main logo image first.', 'error');
    } else {
        $allowed = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $type = strtolower($file['type'] ?? '');
        if (!in_array($type, $allowed, true)) {
            ahkweb_swal_flash('Wrong File Type!', 'JPEG, PNG, GIF and WEBP Allowed only', 'error');
        } else {
            $rootDir    = ahkweb_resolve_root_dir();
            $safeName   = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename($file['name']));
            $targetPath = rtrim($rootDir, '/\\') . '/' . $safeName;

            if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
                ahkweb_swal_flash('Error While Uploading Files!', '', 'error');
            } else {
                $staticLogo = rtrim($rootDir, '/\\') . '/logomain.png';
                $copyOk     = @copy($targetPath, $staticLogo);
                if (!$copyOk && !file_exists($staticLogo)) {
                    ahkweb_swal_flash('Static Copy Failed', 'Permission issue on public_html folder?', 'error');
                } else {
                    ahkweb_swal_flash('Main Logo Updated!', 'Homepage + Login Page logo changed', 'success');
                }
            }
        }
    }

    if ($redirect) {
        // clean buffers + safe redirect to self via JavaScript fallback
        @ob_end_clean();
        header('Location: ' . $_SERVER['PHP_SELF']);
        echo '<script>window.location="' . $_SERVER['PHP_SELF'] . '";</script>';
        die();
    }
}

// ====== OPTION 2 — Static Site Logo → /logo.png ======
if (isset($_POST['lg_static'])) {
    $file = $_FILES['staticlogo'] ?? null;
    $redirect = true;

    if (!$file || empty($file['name']) || !is_uploaded_file($file['tmp_name'] ?? '')) {
        ahkweb_swal_flash('No Logo Selected!', 'Please choose a logo image first for /logo.png', 'error');
    } else {
        $allowed = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $type = strtolower($file['type'] ?? '');
        if (!in_array($type, $allowed, true)) {
            ahkweb_swal_flash('Wrong File Type!', 'JPEG, PNG, GIF and WEBP Allowed only', 'error');
        } else {
            $rootDir    = ahkweb_resolve_root_dir();
            $safeName   = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename($file['name']));
            $targetPath = rtrim($rootDir, '/\\') . '/' . $safeName;

            if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
                ahkweb_swal_flash('Error While Uploading Files!', '', 'error');
            } else {
                $staticLogo = rtrim($rootDir, '/\\') . '/logo.png';
                $copyOk     = @copy($targetPath, $staticLogo);
                if (!$copyOk && !file_exists($staticLogo)) {
                    ahkweb_swal_flash('Static Copy Failed', 'Permission issue on public_html folder?', 'error');
                } else {
                    ahkweb_swal_flash('/logo.png Updated!', 'Static logo uploaded to public_html/logo.png', 'success');
                }
            }
        }
    }

    if ($redirect) {
        @ob_end_clean();
        header('Location: ' . $_SERVER['PHP_SELF']);
        echo '<script>window.location="' . $_SERVER['PHP_SELF'] . '";</script>';
        die();
    }
}

// ====== Flash renderer (fires inside template after DOM ready) ======
$GLOBALS['ahkweb_swal_inline'] = '';
@session_start();
if (!empty($_SESSION['_swal_flashes'])) {
    $flashes = $_SESSION['_swal_flashes'];
    $_SESSION['_swal_flashes'] = [];
    $js = [];
    foreach ($flashes as $f) {
        $title = addslashes($f['title']);
        $text  = addslashes($f['text']);
        $icon  = addslashes($f['icon']);
        $js[]  = "Swal.fire('{$title}','{$text}','{$icon}');";
    }
    $GLOBALS['ahkweb_swal_inline'] = implode('', $js);
}

// ============================================================
// NOW render template — handlers already finished + redirects done
// ============================================================
include('../template/ahkweb/LogoMainUpdate.php');
?>
