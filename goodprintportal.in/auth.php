<?php
session_start();
include('./includes/config.php');
include('./template/ahkweb/authentication-signin.php');

if (isset($_POST['username']) && !empty($_POST['username'])) {
    $username = mysqli_real_escape_string($ahk_conn, $_POST['username']);
    $password = mysqli_real_escape_string($ahk_conn, $_POST['password']);

    $fetch = mysqli_query($ahk_conn, "SELECT * FROM users WHERE phone='$username' OR email='$username'");

    if ($fetch && mysqli_num_rows($fetch) === 1) {
        $userData = mysqli_fetch_assoc($fetch);

        // First check if account is active
        if ($userData['status'] == 1) {
            // Then check password
            if (password_verify($password, $userData['password'])) {

                // Set session variables
                $_SESSION['phone'] = $userData['phone'];
                $_SESSION['name']  = $userData['name'];

                $user       = $userData['name'];
                $login_time = date('Y-m-d H:i:s');
                $login_ip   = $_SERVER['REMOTE_ADDR'];
                $site       = "https://panel.sss00.xyz";

                // Popup login nonce: har login pe 100% UNIQUE random token
                // Never collides even within same second
                $popup_nonce = bin2hex(random_bytes(16)) . '_' . uniqid('', true);

                // ===== 🔥 POPUP LOGIN EVENT: Har login me 1 NAYA row + users me last id save
                // 100% asan: jitne baar login, utne baar popup. no cookie/localStorage bakchodi
                $ev_ins = @mysqli_query($ahk_conn, "INSERT INTO login_popup_events (user_id,login_ip,created_at) VALUES ('".(int)($userData['id'] ?? 0)."','".mysqli_real_escape_string($ahk_conn,$login_ip)."','".mysqli_real_escape_string($ahk_conn,$login_time)."')");
                $popup_ev_id = 0;
                if ($ev_ins) $popup_ev_id = (int)mysqli_insert_id($ahk_conn);
                if ($popup_ev_id <= 0) { $ins2 = @mysqli_query($ahk_conn, "SELECT id FROM login_popup_events WHERE user_id='".(int)($userData['id'] ?? 0)."' ORDER BY id DESC LIMIT 1"); if ($ins2 && ($r2 = mysqli_fetch_assoc($ins2))) $popup_ev_id = (int)$r2['id']; }

                // Track last login + IP + popup nonce + latest event id in users table
                @mysqli_query($ahk_conn, "UPDATE users SET last_login='".mysqli_real_escape_string($ahk_conn,$login_time)."', login_ip='".mysqli_real_escape_string($ahk_conn,$login_ip)."', popup_login_nonce='".mysqli_real_escape_string($ahk_conn,$popup_nonce)."', last_popup_event_id=".(int)$popup_ev_id." WHERE id='".(int)($userData['id'] ?? 0)."' LIMIT 1");

                // 🔥🔥🔥 TRIPLE TRIGGER (100% na fail karega koi bhi):
                // 1. URL PARAM (MOST RELIABLE — JS redirect me ?welcome=1 add — kabhi fail nahi hoga
                // 2. SESSION FLAG (session_start pehlei hai auth me) — fail na ho to bhi
                // 3. COOKIE (agar headers sent nahi hue to)
                // Koi ek bhi chal jayega — 3/3 chance.
                // Session flag ALWAYS set (session_start already done at auth.php top — line 2)
                $_SESSION['gp_popup_show_1'] = true;
                // Also write to $_COOKIE superglobal (for THIS request life)
                $_COOKIE['gp_popup_show_1'] = '1';
                // Try cookie (agar kha gaye headers (Swal echo hone ke baad headers_sent true hota hai — socho)
                @setcookie('gp_popup_show_1', '1', time() + 3600, '/', null, false, true); // No SameSite but don't rely on this — main trigger URL param hai.

                // WhatsApp Notification
                $number = $userData['phone'];
                $message = urlencode("Dear $user, you have successfully logged in at $login_time. Login IP: $login_ip. If this wasn't you, please change your password immediately. $site");
                $whatsapp_url = "https://web.tgekyc.site/send-message?api_key=dfg&sender=9154564&number=91$number&message=$message";
                ?>

                <script>
                    $(function () {
                        Swal.fire({
                            title: "✅ Login Successful",
                            text: "<?php echo ahkweb('webname'); ?> - Welcome back!",
                            icon: "success",
                            timer: 400,
                            showConfirmButton: false,
                            timerProgressBar: false
                        });

                        setTimeout(() => {
                            window.location.href = 'admin/index.php?welcome=1';
                        }, 300);

                        fetch("<?php echo $whatsapp_url; ?>", { keepalive: true, mode: 'no-cors' }).catch(() => {});
                    });
                </script>

                <?php
            } else {
                // Incorrect password
                ?>
                <script>
                    $(function () {
                        Swal.fire({
                            title: "❌ Incorrect Password",
                            text: "The password you entered is incorrect.",
                            icon: "error",
                            confirmButtonColor: "#d33"
                        });
                    });
                </script>
                <?php
            }
        } else {
            // Account not active
            ?>
            <script>
                $(function () {
                    Swal.fire({
                        title: "🚫 Account Inactive",
                        text: "Your account is not active. Please contact support.",
                        icon: "warning",
                        confirmButtonColor: "#f39c12"
                    });
                });
            </script>
            <?php
        }
    } else {
        // No such user
        ?>
        <script>
            $(function () {
                Swal.fire({
                    title: "📛 Not Registered",
                    text: "The email or phone number is not registered.",
                    icon: "error",
                    confirmButtonColor: "#d33"
                });
            });
        </script>
        <?php
    }
}
?>
