
<title>Refer & Earn | Recharge Pay Web Official</title>
<?php 
	// BLOCK REFERRAL PAGE ACCESS IF SYSTEM OFF (ref_enabled = 0)
	$_gpRefEnabledPage = true;
	if (is_array($webdata ?? null) && array_key_exists('ref_enabled', $webdata)) {
	    $_gpRefEnabledPage = ((int)($webdata['ref_enabled'] ?? 1) === 1);
	} else {
	    global $ahk_conn;
	    if (!empty($ahk_conn)) {
	        static $_gpRefCache = null;
	        if ($_gpRefCache === null) {
	            $crc = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM settings LIKE 'ref_enabled'");
	            if ($crc && mysqli_num_rows($crc) > 0) {
	                $rrc = @mysqli_query($ahk_conn, "SELECT ref_enabled FROM settings WHERE id=1 LIMIT 1");
	                if ($rrc && ($rwr = mysqli_fetch_assoc($rrc))) $_gpRefCache = ((int)($rwr['ref_enabled'] ?? 1) === 1);
	                else $_gpRefCache = true;
	            } else {
	                $_gpRefCache = true;
	            }
	        }
	        $_gpRefEnabledPage = $_gpRefCache;
	    }
	}
	if (!$_gpRefEnabledPage) {
	    if (!headers_sent()) {
	        @header('Location: index.php?ref_disabled=1');
	        die();
	    } else {
	        echo '<script>window.location.href = "index.php?ref_disabled=1";</script>';
	        die();
	    }
	}

	include('header.php');
   ?>
   <!--start page wrapper -->
   <style>
    .pointer{
        cursor: pointer;
    }
    .gp-ref-link-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
    }
    .gp-ref-link-text {
        flex: 1 1 auto;
        min-width: 0;
        word-break: break-all;
        display: inline-block;
        padding: 8px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.5;
    }
    .gp-ref-share-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none !important;
        transition: all .2s ease;
        cursor: pointer;
        border: 0;
        color: #fff !important;
    }
    .gp-ref-share-btn:hover {
        transform: translateY(-1px);
        filter: brightness(1.1);
    }
    .gp-ref-btn-copy {
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 3px 8px rgba(16,185,129,0.35);
    }
    .gp-ref-btn-wa {
        background: linear-gradient(135deg, #25D366, #128C7E);
        box-shadow: 0 3px 8px rgba(37,211,102,0.35);
    }
    .gp-ref-btn-tg {
        background: linear-gradient(135deg, #229ED9, #1877B2);
        box-shadow: 0 3px 8px rgba(34,158,217,0.35);
    }
    @media (max-width: 520px) {
        .gp-ref-link-wrap { gap: 8px; }
        .gp-ref-share-btn { padding: 6px 10px; font-size: 12px; }
        .gp-ref-link-text { font-size: 11.5px; }
    }
   </style>
   <div class="page-wrapper">
       <div class="page-content">

           
               
        
           <!--end row-->
           
           <?php
               // ========= ROBUST REFERRAL LINK BUILD (fix: blank domain issue + /admin/register.php wrong path issue) =========
               if (function_exists('gp_get_referral_link')) {
                   $gpFinalReferLink = gp_get_referral_link($udata['phone'] ?? null, $ahk_conn ?? null);
               } else {
                   // Emergency fallback (helper missing — very rare)
                   $_gpP = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https://' : 'http://';
                   $_gpH = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (!empty($host) ? $host : 'goodprintportal.in');
                   $_gpH = trim((string)$_gpH, "/ \t\n\r\0\x0B");
                   $_gpPh = trim((string)($udata['phone'] ?? ''));
                   $gpFinalReferLink = $_gpP . $_gpH . '/register.php?ref=' . base64_encode($_gpPh);
                   // Safety sanitize: triple slash fix + wrong subfolder register fix
                   $gpFinalReferLink = preg_replace('#(?<!:)/{2,}#', '/', $gpFinalReferLink);
                   $gpFinalReferLink = str_replace(['http:/', 'https:/'], ['http://', 'https://'], $gpFinalReferLink);
                   $gpFinalReferLink = preg_replace('#/(admin|template|templates|includes|inc|drpay|paytmUPI|paytm|upi|assets|wp-admin|wp-content|uploads|public|vendor)(/[^/]+)*/register\.php#i', '/register.php', $gpFinalReferLink);
               }
               // Dynamic share message ke liye amount
               $gpRefBonus = '10';
               if (is_array($webdata ?? null) && isset($webdata['ref_bal']) && is_numeric($webdata['ref_bal'])) {
                   $gpRefBonus = (string)((float)$webdata['ref_bal']);
               }
               $gpShareText = urlencode("Hey! GoodPrintPortal par join karo — Aadhaar, PAN, RC, DL, Voter aur bahut saare services at cheapest rate. Mera referral link use karo aur first deposit karte hi MUJHE ₹{$gpRefBonus} bonus milega. Tum bhi apne doston ko refer karke unlimited kama sakte ho. Link:");
               $gpShareTextWA = urlencode("Hey! 👋 *GoodPrintPortal* par join karo —\n✅ Aadhaar / PAN / RC / DL / Voter services at cheapest rates\n✅ Mera referral link use karo → *tum first deposit karte hi MUJHE ₹{$gpRefBonus} bonus milega*\n✅ Tum bhi apne doston ko refer karke *₹{$gpRefBonus}/referral* kama sakte ho unlimited!\n\n👉 Mera Referral Link:");
           ?>

           <div class="row row-cols-1 row-cols-md-2 row-cols-xl-10">
           <div class="col pointer">
						<div class="card radius-10 bg-gradient-cosmic">
							<div class="card-body">
								<div>
									<p class="mb-0 text-white" style="font-weight:600;font-size:14px;">🔗 Your Referral Link </p>
                                    <div class="gp-ref-link-wrap">
                                        <input
                                            type="text"
                                            id="ref"
                                            readonly
                                            class="gp-ref-link-text text-white"
                                            value="<?= htmlspecialchars($gpFinalReferLink) ?>">
                                        <button class="gp-ref-share-btn gp-ref-btn-copy" onclick="gpCopyReferLink()" title="Copy Link">
                                            <i class='bx bx-copy' style="font-size:15px;"></i>
                                            <span id="gpCopyText">Copy</span>
                                        </button>
                                        <a class="gp-ref-share-btn gp-ref-btn-wa" target="_blank" rel="noopener noreferrer"
                                           href="https://wa.me/?text=<?= $gpShareTextWA ?>%0A<?= urlencode($gpFinalReferLink) ?>"
                                           title="WhatsApp par share karein">
                                            <i class="fab fa-whatsapp" style="font-size:15px;"></i>
                                            WhatsApp
                                        </a>
                                        <a class="gp-ref-share-btn gp-ref-btn-tg" target="_blank" rel="noopener noreferrer"
                                           href="https://t.me/share/url?url=<?= urlencode($gpFinalReferLink) ?>&text=<?= $gpShareText ?>"
                                           title="Telegram par share karein">
                                            <i class="fab fa-telegram" style="font-size:15px;"></i>
                                            Telegram
                                        </a>
                                    </div>
								</div>
                               
								<div class="text-white ms-auto font-35"><i class='bx bx-share-alt'></i>
								</div>
							</div>
						</div>
					</div>
                    <script>
                    function gpCopyReferLink() {
                        const inp = document.getElementById('ref');
                        const btnTxt = document.getElementById('gpCopyText');
                        const original = btnTxt ? btnTxt.innerText : 'Copy';
                        const okIcon = '✓ Copied!';
                        const doFallback = () => {
                            try {
                                inp.select();
                                inp.setSelectionRange(0, 99999);
                                const ok = document.execCommand('copy');
                                if (ok) {
                                    if (btnTxt) btnTxt.innerText = okIcon;
                                    if (typeof Swal !== 'undefined') {
                                        Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                                            title: 'Referral Link Copied!', showConfirmButton: false, timer: 1800 });
                                    }
                                    setTimeout(() => { if (btnTxt) btnTxt.innerText = original; }, 1800);
                                }
                            } catch(e) {}
                        };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(inp.value).then(() => {
                                if (btnTxt) btnTxt.innerText = okIcon;
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                                        title: 'Referral Link Copied!', showConfirmButton: false, timer: 1800 });
                                }
                                setTimeout(() => { if (btnTxt) btnTxt.innerText = original; }, 1800);
                            }).catch(doFallback);
                        } else {
                            doFallback();
                        }
                    }
                    // Backward compat: purana handleCopyTextFromParagraph bhi chala le
                    function handleCopyTextFromParagraph(){ gpCopyReferLink(); }
                </script>
                <?php
                $all = mysqli_query($ahk_conn,"SELECT * FROM referal WHERE referred_by='".$_SESSION['phone']."' ORDER BY id DESC");
                $count = 0;
                function CountEarning($all){
                    $count = 0;
                     if(mysqli_num_rows($all)>0){
                        while($earning = mysqli_fetch_assoc($all)){
                       $count += $earning['referral_balance'];
                    }
                    echo $count;
                }
                }
               
                ?>
					<div class="col pointer">
						<div class="card radius-10 bg-gradient-burning">
							<div class="card-body">
								<div class="d-flex align-items-center">
									<div>
										<p class="mb-0 text-white">Total Income</p>
										<h4 class="my-1 text-white"><?php CountEarning($all); ?></h4>
									</div>
									<div class="text-white ms-auto font-35"><i class='bx bx-rupee'></i>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="col pointer">
						<div class="card radius-10 bg-gradient-kyoto">
							<div class="card-body">
								<div class="d-flex align-items-center">
									<div>
										<p class="mb-0 text-dark">Total Users</p>
										<h4 class="text-dark my-1"><?php echo mysqli_num_rows($all); ?></h4>
									</div>
									<div class="text-dark ms-auto font-35"><i class='bx bx-user-pin'></i>
									</div>
								</div>
							</div>
						</div>
					</div>
					
        </div>
            
           <!-- Referral Info Card - How Bonus Milega -->
           <?php
               $refBonusAmt = '10';
               if (is_array($webdata ?? null) && isset($webdata['ref_bal']) && is_numeric($webdata['ref_bal'])) {
                   $refBonusAmt = (string)((float)$webdata['ref_bal']);
               } else {
                   $rbs = @mysqli_query($ahk_conn, "SELECT ref_bal FROM settings WHERE id=1 LIMIT 1");
                   if ($rbs && ($rbr = mysqli_fetch_assoc($rbs)) && is_numeric($rbr['ref_bal'] ?? '')) {
                       $refBonusAmt = (string)((float)$rbr['ref_bal']);
                   }
               }
           ?>
           <div class="row mt-4">
               <div class="col-12">
                   <div class="card radius-10 border-0 shadow-sm" style="background: linear-gradient(135deg, #f0f9ff 0%, #ede9fe 100%); border-left: 5px solid #8b5cf6 !important;">
                       <div class="card-body p-3 p-md-4">
                           <div class="d-flex align-items-start gap-3">
                               <div class="flex-shrink-0" style="font-size: 38px;">
                                   🎁
                               </div>
                               <div class="flex-grow-1">
                                   <h6 class="fw-bold mb-2 text-dark" style="font-size: 16px;">
                                       📢 Referral Bonus Kaise Milega?
                                   </h6>
                                   <ul class="mb-0 ps-3 text-dark" style="font-size: 13.5px; line-height: 1.7;">
                                       <li>
                                           Har <b>naye user</b> ke liye — jisko tumne apne link se refer kiya hai — uska
                                           <b>pehla wallet Deposit / Registration Fee Payment</b> successfully complete hote hi,
                                           tumhare wallet mein
                                           <span class="fw-bold text-white px-2 py-0.5 rounded" style="background: linear-gradient(135deg, #8b5cf6, #6366f1); font-size: 13px;">
                                               ₹<?= htmlspecialchars($refBonusAmt) ?>
                                           </span>
                                           ka bonus <b>auto credit</b> ho jayega.
                                       </li>
                                       <li>
                                           Bonus <b>har alag naye referral pe sirf EK HI BAAR</b> milega (ek user ke liye maximum 1 baar).
                                       </li>
                                       <li class="text-danger">
                                           <b>Sirf register karne se bonus nahi milega</b> — naye user ko Deposit karna zaroori hai!
                                       </li>
                                       <li>
                                           Referral income aapke wallet balance mein direct add ho jayegi, aur aap usko kisi bhi service me use kar sakte ho.
                                       </li>
                                   </ul>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>



           


           
 <!--end row-->
 <h6 class="mb-0 text-uppercase">All Referral List</h6>
				<hr/>
				<div class="card">
					<div class="card-body">
						<div class="table-responsive">
							<table id="example2" class="table table-striped table-bordered">

                    <?php
                    $res = mysqli_query($ahk_conn,"SELECT * FROM referal WHERE referred_by='".$_SESSION['phone']."' ORDER BY id DESC");

                    ?>
								<thead>
									<tr>
                                        <th>Sl.</th>
										<th>TXN ID</th>
										<th>Amount</th>
										<th>Refer date</th>
										<th>Phone</th>
										<th>Total Earning</th>
										
										<th>Status</th>
										
									</tr>
								</thead>

								<tbody>
                                    <?php
                                    $total = 0;
                                    $x = 0;
                                    if(mysqli_num_rows($res)>0){
                                        while($data = mysqli_fetch_assoc($res)){
                                            $x  ++;
                                            $total += $data['referral_balance'];
                                            ?>
                                            <tr>
										<td><?php echo $x; ?></td>
										<td><?php echo strtoupper($data['txn_no']); ?></td>
										<td><?php echo strtoupper($data['referral_balance']); ?></td>
										<td><?php echo strtoupper($data['refer_date']); ?></td>
										<td><?php echo strtoupper($data['phone']); ?></td>
										<td>-</td>
										

										<td class="badge badge-bg-primary"><?php echo strtoupper($data['status']); ?></td>
									</tr>
                                    <?php
                                        }
                                       
                                    }
                                    
                                    ?>
									
									
									
								</tbody>
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th><?php echo $total;?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
								
							</table>
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
   <script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
   <script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
   <script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
   <script src="../template/ahkweb/assets/plugins/jquery.easy-pie-chart/jquery.easypiechart.min.js"></script>
   <script src="../template/ahkweb/assets/plugins/sparkline-charts/jquery.sparkline.min.js"></script>
   <script src="../template/ahkweb/assets/plugins/jquery-knob/excanvas.js"></script>
   <script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
   <script>
$(function() {
    $(".knob").knob();
});
   </script>
   <script src="../template/ahkweb/assets/js/index.js"></script>
   <!--app JS-->
   <script src="../template/ahkweb/assets/js/app.js"></script>
   <!-- datatable -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
		$(document).ready(function() {
			$('#example').DataTable();
		  } );
	</script>
	
	<script>
		$(document).ready(function() {
			var table = $('#example2').DataTable( {
				lengthChange: false,
				buttons: [ 'copy', 'excel', 'pdf', 'print']
			} );
		 
			table.buttons().container()
				.appendTo( '#example2_wrapper .col-md-6:eq(0)' );
		} );
	</script>
	<!--app JS-->
	<script src="../template/ahkweb/assets/js/app.js"></script>
   </body>



   </html>