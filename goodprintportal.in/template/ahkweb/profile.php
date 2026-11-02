<?php
include('header.php');

// ================== HELPER: Format date nicely ==================
function _gpFmtDate($raw) {
    if (empty($raw)) return '<span class="text-muted fst-italic">Not available</span>';
    $ts = strtotime((string)$raw);
    if (!$ts) return htmlspecialchars((string)$raw);
    return date('d M Y, h:i A', $ts);
}
// ================== HELPER: User type badge color ==================
function _gpTypeBadge($t) {
    $t = strtolower(trim((string)$t));
    $map = [
        'admin'         => ['bg'=>'#dc2626', 'label'=>'ADMINISTRATOR'],
        'administrator' => ['bg'=>'#dc2626', 'label'=>'ADMINISTRATOR'],
        'superadmin'    => ['bg'=>'#7c2d12', 'label'=>'SUPER ADMIN'],
        'master'        => ['bg'=>'#7c3aed', 'label'=>'MASTER DISTRIBUTOR'],
        'distributor'   => ['bg'=>'#2563eb', 'label'=>'DISTRIBUTOR'],
        'retailer'      => ['bg'=>'#059669', 'label'=>'RETAILER'],
        'user'          => ['bg'=>'#475569', 'label'=>'USER'],
    ];
    $m = $map[$t] ?? ['bg'=>'#64748b', 'label'=>strtoupper($t ?: 'USER')];
    return '<span class="badge rounded-pill px-3 py-2" style="background:'.$m['bg'].'; color:#fff; font-size:11px; letter-spacing:.5px; font-weight:700;">'.$m['label'].'</span>';
}
// ================== HELPER: Status badge ==================
function _gpStatusBadge($s) {
    $s = (string)$s;
    if ($s === '1' || strcasecmp($s,'active')===0 || $s === 'success') {
        return '<span class="badge rounded-pill px-3 py-2" style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700;">● ACTIVE</span>';
    }
    if ($s === '0' || strcasecmp($s,'pending')===0) {
        return '<span class="badge rounded-pill px-3 py-2" style="background:#fef3c7; color:#92400e; font-size:11px; font-weight:700;">● PENDING</span>';
    }
    if (strcasecmp($s,'rejected')===0 || strcasecmp($s,'block')===0 || $s==='2') {
        return '<span class="badge rounded-pill px-3 py-2" style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700;">● BLOCKED</span>';
    }
    return '<span class="badge rounded-pill px-3 py-2 bg-secondary text-white" style="font-size:11px; font-weight:700;">'.htmlspecialchars($s).'</span>';
}
// ================== HELPER: Generate initials ==================
function _gpInitials($name) {
    $n = trim(preg_replace('/\s+/',' ',(string)$name));
    if ($n === '') return 'GP';
    $parts = explode(' ', $n);
    if (count($parts)===1) return strtoupper(substr($parts[0],0,2));
    return strtoupper(substr($parts[0],0,1).substr(end($parts),0,1));
}
// ================== HELPER: Gradient from user id ==================
function _gpAvatarGradient($uid) {
    $grads = [
        'linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%)',
        'linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%)',
        'linear-gradient(135deg,#10b981 0%,#059669 100%)',
        'linear-gradient(135deg,#f59e0b 0%,#d97706 100%)',
        'linear-gradient(135deg,#ef4444 0%,#dc2626 100%)',
        'linear-gradient(135deg,#ec4899 0%,#db2777 100%)',
        'linear-gradient(135deg,#2a2469 0%,#4f46e5 100%)',
    ];
    $i = (int)$uid % count($grads);
    return $grads[$i];
}

// =========== Populate fallback-safe display values ===========
$u_name    = trim((string)($udata['name']     ?? ''));
$u_phone   = trim((string)($udata['phone']    ?? ''));
$u_email   = trim((string)($udata['email']    ?? ''));
$u_addr    = trim((string)($udata['address']  ?? ''));
$u_type    = trim((string)($udata['type']     ?? 'user'));
$u_status  = (string)($udata['status']        ?? '0');
$u_bal     = (float)($udata['balance']        ?? 0);
$u_id      = (int)($udata['id']               ?? 0);
$u_regdate = $udata['date']       ?? ($udata['created_at'] ?? '');
$u_lastlog = $udata['last_login'] ?? '';
$u_loginip = $udata['login_ip']   ?? '';
$u_refby   = trim((string)($udata['referred_by'] ?? ''));
$u_parent  = trim((string)($udata['parent']      ?? ''));
$u_orderid = trim((string)($udata['order_id']    ?? ''));
$u_gender  = trim((string)($udata['gender']      ?? ''));
$u_dob     = trim((string)($udata['dob']         ?? ''));
$u_pin     = trim((string)($udata['pincode']     ?? ''));
$u_state   = trim((string)($udata['state']       ?? ''));
$u_city    = trim((string)($udata['city']        ?? ''));
?>
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">User Profile</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="index.php"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">User Profile</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!--end breadcrumb-->
        <div class="container">
            <div class="main-body">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card radius-15 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex flex-column align-items-center text-center">
                                    <?php
                                    // PREMIUM INITIALS AVATAR — ALWAYS visible, company logo ke jagah user ke naam ke letters
                                    // Design: 120px circle → double ring (white outer + gradient inner ring) → soft drop shadow → shine highlight top → bold center initials
                                    // User ID based gradient = same user har baar same rang dekhega
                                    ?>
                                    <div class="position-relative mb-3" style="width:128px; height:128px;">
                                        <!-- Outer premium ring (white border + soft shadow halo) -->
                                        <div style="
                                            position:absolute; inset:-4px;
                                            border-radius:50%;
                                            background:linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.6));
                                            box-shadow:
                                                0 0 0 1px rgba(255,255,255,0.9),
                                                0 18px 40px rgba(79, 70, 229, 0.18),
                                                0 8px 20px rgba(0,0,0,0.08);
                                        "></div>
                                        <!-- Inner gradient ring (colored accent) -->
                                        <div style="
                                            position:absolute; inset:0;
                                            border-radius:50%;
                                            padding:3px;
                                            background:linear-gradient(135deg, <?php echo _gpAvatarGradient($u_id); ?>);
                                        ">
                                            <!-- Solid white buffer to make inner avatar look inset -->
                                            <div style="
                                                width:100%; height:100%; border-radius:50%;
                                                background:#fff;
                                                padding:3px;
                                            ">
                                                <!-- AVATAR CIRCLE (the gradient one with initials) -->
                                                <div style="
                                                    width:100%; height:100%;
                                                    border-radius:50%;
                                                    background:<?php echo _gpAvatarGradient($u_id); ?>;
                                                    position:relative;
                                                    overflow:hidden;
                                                    display:flex;
                                                    align-items:center;
                                                    justify-content:center;
                                                ">
                                                    <!-- Subtle top shine for glass/premium look -->
                                                    <div style="
                                                        position:absolute;
                                                        top:0; left:0; right:0;
                                                        height:48%;
                                                        background:linear-gradient(to bottom, rgba(255,255,255,0.35), rgba(255,255,255,0));
                                                        border-radius:50% 50% 0 0 / 100% 100% 0 0;
                                                        pointer-events:none;
                                                    "></div>
                                                    <!-- Soft bottom shadow for depth -->
                                                    <div style="
                                                        position:absolute;
                                                        bottom:0; left:10%; right:10%;
                                                        height:35%;
                                                        background:linear-gradient(to top, rgba(0,0,0,0.15), rgba(0,0,0,0));
                                                        border-radius:0 0 50% 50% / 0 0 100% 100%;
                                                        pointer-events:none;
                                                    "></div>
                                                    <!-- Initials (bold, premium white, slight drop shadow so readable on any gradient) -->
                                                    <span style="
                                                        position:relative;
                                                        z-index:1;
                                                        font-family:'Nunito','Inter',system-ui,sans-serif;
                                                        font-weight:900;
                                                        font-size:48px;
                                                        letter-spacing:1.5px;
                                                        color:#ffffff;
                                                        line-height:1;
                                                        text-shadow:
                                                            0 2px 6px rgba(0,0,0,0.22),
                                                            0 0 1px rgba(0,0,0,0.3);
                                                    ">
                                                        <?php echo _gpInitials($u_name); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 w-100">
                                        <h4 class="mb-1 fw-bold" style="color:#1e293b; font-size:22px;">
                                            <?php echo $u_name !== '' ? htmlspecialchars($u_name) : '<span class="text-muted">—</span>'; ?>
                                        </h4>
                                        <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 mt-2 mb-2">
                                            <?php echo _gpTypeBadge($u_type); ?>
                                            <?php echo _gpStatusBadge($u_status); ?>
                                        </div>
                                        <p class="text-muted font-size-sm mb-3" style="font-size:14px;">
                                            <i class="bx bx-calendar-alt me-1"></i>
                                            Registration: <?php echo _gpFmtDate($u_regdate); ?>
                                        </p>
                                        <div class="d-grid gap-2 mt-3">
                                            <a href="wallet.php" class="btn btn-primary d-flex align-items-center justify-content-center gap-2" style="background:linear-gradient(135deg,#4f46e5,#2a2469); border:none; border-radius:10px; font-weight:600;">
                                                <i class="bx bx-wallet"></i> Wallet (₹<?php echo number_format($u_bal,2); ?>)
                                            </a>
                                            <a href="index.php" class="btn btn-outline-primary d-flex align-items-center justify-content-center gap-2" style="border-radius:10px; font-weight:600;">
                                                <i class="bx bxs-dashboard"></i> Dashboard
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4" style="border-top:1px dashed #e2e8f0;">

                                <h6 class="mb-3 text-uppercase" style="font-size:12px; letter-spacing:1px; color:#64748b; font-weight:700;">
                                    <i class="bx bx-user-circle me-2"></i> Account Details
                                </h6>
                                <ul class="list-group list-group-flush">

                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="width:30px; height:30px; border-radius:8px; background:#eef2ff; color:#4f46e5; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-id-card"></i>
                                            </span>
                                            <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">User ID</h6>
                                        </div>
                                        <span class="text-dark fw-bold" style="font-size:14px;">#<?php echo $u_id; ?></span>
                                    </li>

                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="width:30px; height:30px; border-radius:8px; background:#ecfdf5; color:#059669; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-mobile-alt"></i>
                                            </span>
                                            <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Phone</h6>
                                        </div>
                                        <span class="text-dark fw-bold" style="font-size:14px;"><?php echo $u_phone !== '' ? htmlspecialchars($u_phone) : '<span class="text-muted">—</span>'; ?></span>
                                    </li>

                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="width:30px; height:30px; border-radius:8px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-envelope"></i>
                                            </span>
                                            <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Email</h6>
                                        </div>
                                        <span class="text-dark fw-bold" style="font-size:13px; max-width:170px; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($u_email); ?>">
                                            <?php echo $u_email !== '' ? htmlspecialchars($u_email) : '<span class="text-muted">—</span>'; ?>
                                        </span>
                                    </li>

                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="width:30px; height:30px; border-radius:8px; background:#fffbeb; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-rupee"></i>
                                            </span>
                                            <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Wallet Balance</h6>
                                        </div>
                                        <span class="fw-bold" style="font-size:14px; color:#059669;">₹<?php echo number_format($u_bal,2); ?></span>
                                    </li>

                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="width:30px; height:30px; border-radius:8px; background:#f0f9ff; color:#0284c7; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-calendar"></i>
                                            </span>
                                            <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Reg. Date</h6>
                                        </div>
                                        <span class="text-dark" style="font-size:12px;"><?php echo _gpFmtDate($u_regdate); ?></span>
                                    </li>

                                    <?php if ($u_lastlog !== '' || $u_loginip !== ''): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="width:30px; height:30px; border-radius:8px; background:#fdf2f8; color:#be185d; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-log-in-circle"></i>
                                            </span>
                                            <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Last Login</h6>
                                        </div>
                                        <span class="text-dark" style="font-size:12px;">
                                            <?php
                                            echo _gpFmtDate($u_lastlog);
                                            if ($u_loginip !== '') echo ' <small class="text-muted">(IP: '.htmlspecialchars($u_loginip).')</small>';
                                            ?>
                                        </span>
                                    </li>
                                    <?php endif; ?>

                                    <?php if ($u_orderid !== ''): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="width:30px; height:30px; border-radius:8px; background:#f5f3ff; color:#7c3aed; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-receipt"></i>
                                            </span>
                                            <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Reg. Order ID</h6>
                                        </div>
                                        <span class="text-dark fw-bold" style="font-size:12px;"><?php echo htmlspecialchars($u_orderid); ?></span>
                                    </li>
                                    <?php endif; ?>

                                    <?php if ($u_refby !== '' || $u_parent !== ''): ?>
                                        <?php if ($u_refby !== ''): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="border:none;">
                                            <div class="d-flex align-items-center gap-2">
                                                <span style="width:30px; height:30px; border-radius:8px; background:#ecfdf5; color:#047857; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                    <i class="bx bx-share-alt"></i>
                                                </span>
                                                <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Referral Code</h6>
                                            </div>
                                            <span class="text-dark fw-bold" style="font-size:13px;"><?php echo htmlspecialchars($u_refby); ?></span>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($u_parent !== ''): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="border:none;">
                                            <div class="d-flex align-items-center gap-2">
                                                <span style="width:30px; height:30px; border-radius:8px; background:#fef3c7; color:#92400e; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                    <i class="bx bxs-user-voice"></i>
                                                </span>
                                                <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Parent / Upline</h6>
                                            </div>
                                            <span class="text-dark fw-bold" style="font-size:13px;"><?php echo htmlspecialchars($u_parent); ?></span>
                                        </li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($u_gender !== '' || $u_dob !== '' || $u_pin !== '' || $u_state !== '' || $u_city !== ''): ?>
                                    <li class="list-group-item d-block px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span style="width:30px; height:30px; border-radius:8px; background:#f1f5f9; color:#475569; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-map"></i>
                                            </span>
                                            <h6 class="mb-0" style="font-size:14px; color:#475569; font-weight:600;">Personal / Location</h6>
                                        </div>
                                        <div class="ms-4 small text-muted" style="font-size:13px; line-height:1.7;">
                                            <?php
                                            $bits = [];
                                            if ($u_gender !== '') $bits[] = '<b class="text-dark">Gender:</b> '.htmlspecialchars(ucfirst($u_gender));
                                            if ($u_dob    !== '') $bits[] = '<b class="text-dark">DOB:</b> '.htmlspecialchars($u_dob);
                                            if ($u_city   !== '') $bits[] = '<b class="text-dark">City:</b> '.htmlspecialchars($u_city);
                                            if ($u_state  !== '') $bits[] = '<b class="text-dark">State:</b> '.htmlspecialchars($u_state);
                                            if ($u_pin    !== '') $bits[] = '<b class="text-dark">PIN:</b> '.htmlspecialchars($u_pin);
                                            echo implode('<br>', $bits);
                                            ?>
                                        </div>
                                    </li>
                                    <?php endif; ?>

                                    <?php if ($u_addr !== ''): ?>
                                    <li class="list-group-item d-block px-0 py-2" style="border:none;">
                                        <div class="d-flex align-items-start gap-2">
                                            <span style="width:30px; height:30px; min-width:30px; border-radius:8px; background:#ede9fe; color:#6d28d9; display:flex; align-items:center; justify-content:center; font-size:15px;">
                                                <i class="bx bx-home-circle"></i>
                                            </span>
                                            <div>
                                                <h6 class="mb-1" style="font-size:14px; color:#475569; font-weight:600;">Address</h6>
                                                <p class="mb-0 text-dark" style="font-size:13px; line-height:1.55;"><?php echo nl2br(htmlspecialchars($u_addr)); ?></p>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endif; ?>

                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <!-- Details Form -->
                        <form action="" method="POST">
                            <div class="card radius-15 shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="mb-4 fw-bold" style="color:#1e293b;"><i class="bx bxs-edit-alt me-2" style="color:#4f46e5;"></i> Edit Profile</h5>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <h6 class="mb-0">Full Name</h6>
                                    </div>
                                    <div class="col-sm-9 text-secondary">
                                        <input required name="name" type="text" class="form-control" value="<?php echo htmlspecialchars($u_name); ?>" placeholder="Enter your full name" />
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <h6 class="mb-0">Email</h6>
                                    </div>
                                    <div class="col-sm-9 text-secondary">
                                        <input required name="email" type="email" class="form-control"
                                            value="<?php echo htmlspecialchars($u_email); ?>" placeholder="name@example.com" />
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <h6 class="mb-0">Phone</h6>
                                    </div>
                                    <div class="col-sm-9 text-secondary">
                                        <input readonly name="phone" type="text" class="form-control bg-light"
                                            value="<?php echo htmlspecialchars($u_phone); ?>" title="Registered mobile number cannot be changed" />
                                        <small class="text-muted d-block mt-1"><i class="bx bx-info-circle"></i> Registered mobile number cannot be changed</small>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <h6 class="mb-0">Address</h6>
                                    </div>
                                    <div class="col-sm-9 text-secondary">
                                        <textarea name="address" rows="3" class="form-control" placeholder="Full address with street / city / state..."><?php echo htmlspecialchars($u_addr); ?></textarea>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-3"></div>
                                    <div class="col-sm-9 text-secondary">
                                        <input type="submit" class="btn btn-primary px-5" style="background:linear-gradient(135deg,#4f46e5,#2a2469); border:none; border-radius:10px; font-weight:600;" value="Save Changes" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        </form>

                        <!-- Password Form -->
                        <form action="" method="POST">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="card radius-15 shadow-sm">
                                    <div class="card-body p-4">
                                        <h5 class="mb-4 fw-bold" style="color:#1e293b;"><i class="bx bxs-lock-alt me-2" style="color:#d97706;"></i> Change Password</h5>
                                        <div class="row mb-3">
                                            <div class="col-sm-3">
                                                <h6 class="mb-0">Current Password</h6>
                                            </div>
                                            <div class="col-sm-9 text-secondary">
                                                <input required name="curpass" type="password" class="form-control" value=""
                                                    placeholder="Enter your current password" />
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3">
                                                <h6 class="mb-0">New Password</h6>
                                            </div>
                                            <div class="col-sm-9 text-secondary">
                                                <input required name="npass" type="password" minlength="6" class="form-control" value=""
                                                    placeholder="Enter new password (min 6 characters)" />
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3">
                                                <h6 class="mb-0">Confirm New Password</h6>
                                            </div>
                                            <div class="col-sm-9 text-secondary">
                                                <input required name="cpass" type="password" minlength="6" class="form-control" value=""
                                                    placeholder="Re-enter new password" />
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-sm-3"></div>
                                            <div class="col-sm-9 text-secondary">
                                                <input type="submit" class="btn btn-warning px-5"
                                                    style="background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none; border-radius:10px; font-weight:600;"
                                                    value="Update Password" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end page wrapper -->
<?php
        include('footer.php');
        ?>
<!-- Bootstrap JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<!--app JS-->
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>


</html>
