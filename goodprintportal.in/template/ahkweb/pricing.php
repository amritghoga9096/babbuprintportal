<?php
include('header.php');

/* ==========================================================================
   1) HANDLE: Update existing pricing rows (bulk save via form)
   ========================================================================== */
$save_message = '';
$save_type    = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'bulk_save' && isset($_POST['prices']) && is_array($_POST['prices'])) {
    if (checkAdmin($udata['type'] ?? '') === false) {
        echo '<script>window.location.href=\'../admin/index.php\';</script>'; exit;
    }
    $affected = 0;
    foreach ($_POST['prices'] as $svcNameRaw => $newPriceRaw) {
        $svcName  = (string)$svcNameRaw;
        if ($svcName === '') continue;
        $newPrice = (string)$newPriceRaw;
        $safeSvc  = mysqli_real_escape_string($ahk_conn, $svcName);
        $safePx   = mysqli_real_escape_string($ahk_conn, $newPrice);
        $res = @mysqli_query($ahk_conn, "UPDATE pricing SET price='{$safePx}' WHERE service_name='{$safeSvc}' LIMIT 1");
        if ($res && mysqli_affected_rows($ahk_conn) > 0) $affected++;
    }
    $save_message = $affected . ' price(s) updated successfully.';
    $save_type    = 'success';
}

/* ==========================================================================
   2) HANDLE: Insert a brand NEW custom service row
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'add_new'
    && !empty($_POST['new_service_name']) && isset($_POST['new_service_price'])) {
    if (checkAdmin($udata['type'] ?? '') === false) {
        echo '<script>window.location.href=\'../admin/index.php\';</script>'; exit;
    }
    $newSvc = trim((string)$_POST['new_service_name']);
    $newPx  = trim((string)$_POST['new_service_price']);
    if ($newSvc !== '') {
        $safeSvc = mysqli_real_escape_string($ahk_conn, $newSvc);
        $safePx  = mysqli_real_escape_string($ahk_conn, $newPx);
        // check duplicate
        $dup = @mysqli_query($ahk_conn, "SELECT id FROM pricing WHERE service_name='{$safeSvc}' LIMIT 1");
        if ($dup && mysqli_num_rows($dup) > 0) {
            $save_message = 'Service name already exists: ' . htmlspecialchars($newSvc);
            $save_type    = 'error';
        } else {
            $ins = @mysqli_query($ahk_conn, "INSERT INTO pricing (service_name, price) VALUES ('{$safeSvc}','{$safePx}')");
            if ($ins) {
                $save_message = 'New service added: ' . htmlspecialchars($newSvc) . ' @ ₹' . htmlspecialchars($newPx);
                $save_type    = 'success';
            } else {
                $save_message = 'DB Error adding service: ' . htmlspecialchars(mysqli_error($ahk_conn));
                $save_type    = 'error';
            }
        }
    }
}

/* ==========================================================================
   3) LOAD ALL pricing rows once for display
   ========================================================================== */
$allPricingRows = [];
$q = @mysqli_query($ahk_conn, "SELECT id, service_name, price FROM pricing ORDER BY LOWER(service_name) ASC, id ASC");
if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $allPricingRows[] = $row;
    }
    @mysqli_free_result($q);
}
$totalCount = count($allPricingRows);
?>

<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Website Pricing</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <div class="btn-group">
                    <span class="badge bg-primary rounded-pill" style="font-size:14px;padding:8px 14px;">
                        💰 Total Products: <strong><?= (int)$totalCount; ?></strong>
                    </span>
                </div>
            </div>
        </div>
        <!--end breadcrumb-->

        <div class="row">
            <!-- ============== LEFT: Add New Service ============== -->
            <div class="col-xl-3 col-lg-4 mb-4">
                <div class="card border-start border-4 border-success shadow-sm">
                    <div class="card-body p-4">
                        <div class="card-title d-flex align-items-center">
                            <div><i class="bx bx-plus-circle me-1 font-22 text-success"></i></div>
                            <h5 class="mb-0 text-success">Add New Service</h5>
                        </div>
                        <hr>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="_action" value="add_new">
                            <div class="col-12">
                                <label class="form-label">Service Name (slug)</label>
                                <input class="form-control" type="text" name="new_service_name"
                                       required placeholder="e.g. my_custom_service_fee" maxlength="200">
                                <small class="text-muted">Same name use karna jo service file me code me read kare.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Price (₹)</label>
                                <input class="form-control" type="number" min="0" step="0.01"
                                       name="new_service_price" required placeholder="e.g. 30" value="0">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-success w-100 px-4 py-2" type="submit">
                                    <i class="bx bx-save me-1"></i> Add Service
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ============== Help / Tips ============== -->
                <div class="card border-start border-4 border-info shadow-sm mt-4">
                    <div class="card-body p-4">
                        <h6 class="text-info mb-3"><i class="bx bx-info-circle me-1"></i> Quick Tips</h6>
                        <ul class="mb-0 ps-3 text-muted small" style="line-height:1.7;">
                            <li>Har 1 service ka naam aur price <strong>pricing table</strong> se read hota hai.</li>
                            <li>Price change karne ke baad <strong>Save All Prices</strong> dabana mat bhoolo.</li>
                            <li><strong>Search box</strong> se 200+ products me jaldi dhoondho.</li>
                            <li>Naya service add karne ke baad, code me uss naam ko read karne ke liye same naam use karo.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============== RIGHT: Full Pricing Grid ============== -->
            <div class="col-xl-9 col-lg-8">
                <div class="card border-top border-0 border-4 border-primary shadow-sm">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                            <div class="card-title d-flex align-items-center">
                                <div><i class="bx bxs-cog me-1 font-22 text-primary"></i></div>
                                <h5 class="mb-0 text-primary">All Service Pricing (<?= (int)$totalCount; ?>)</h5>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="position-relative">
                                    <input id="priceSearchInput" type="text"
                                           class="form-control ps-4"
                                           placeholder="🔍 Search by service name..."
                                           style="min-width:260px;">
                                    <i class="bx bx-search"
                                       style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa1a9;"></i>
                                </div>
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="document.getElementById('priceSearchInput').value='';gpFilterRows();">
                                    Clear
                                </button>
                            </div>
                        </div>
                        <hr>

                        <form method="POST" class="row g-3" id="pricesForm" onsubmit="return gpValidateBeforeSave();">
                            <input type="hidden" name="_action" value="bulk_save">

                            <div class="col-12 mb-2 sticky-top"
                                 style="z-index:20;background:linear-gradient(180deg,#f8f9fc 0%,#ffffff 100%);padding:10px 0;border-bottom:1px dashed #e2e6f0;">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="text-muted small" id="rowsShowingMsg">
                                        Showing <strong><?= (int)$totalCount; ?></strong> of <?= (int)$totalCount; ?> services
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary px-3"
                                                onclick="window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});">
                                            <i class="bx bx-chevrons-down me-1"></i> Jump Bottom
                                        </button>
                                        <button type="submit" class="btn btn-primary px-5 ms-1">
                                            <i class="bx bxs-save me-1"></i> Save All Prices
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="pricesGrid"
                                 class="row g-3 gy-3" style="align-items:flex-start;">
                                <?php
                                $i = 0;
                                foreach ($allPricingRows as $p) {
                                    $i++;
                                    $svc   = (string)$p['service_name'];
                                    $px    = (string)$p['price'];
                                    $idRaw = (int)$p['id'];
                                    $displayName = ucwords(str_replace(['_','-'], ' ', $svc));
                                    $safeFieldName = 'prices[' . $svc . ']';
                                    ?>
                                    <div class="col-md-6 col-xl-4 gp-price-card"
                                         data-svc="<?= htmlspecialchars(strtolower($svc)); ?>"
                                         data-display="<?= htmlspecialchars(strtolower($displayName)); ?>"
                                         style="transition:opacity .2s,transform .2s;">
                                        <div class="card border rounded-4"
                                             style="border:1px solid #eef1f7;background:#fff;">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <small class="badge bg-light text-dark rounded-pill"
                                                           style="font-size:10px;font-weight:600;">
                                                        ID #<?= $idRaw; ?>
                                                    </small>
                                                    <small class="text-muted"
                                                           style="font-size:10px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;">
                                                        <?= htmlspecialchars($svc); ?>
                                                    </small>
                                                </div>
                                                <label class="form-label fw-bold mb-1 text-dark"
                                                       style="font-size:13px;line-height:1.3;">
                                                    <?= htmlspecialchars($displayName); ?>
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-light text-primary fw-bold"
                                                          style="font-size:14px;">₹</span>
                                                    <input type="number" step="0.01" min="0"
                                                           class="form-control gp-price-input"
                                                           name="<?= htmlspecialchars($safeFieldName); ?>"
                                                           value="<?= htmlspecialchars($px); ?>"
                                                           data-original="<?= htmlspecialchars($px); ?>"
                                                           data-svc="<?= htmlspecialchars($svc); ?>"
                                                           placeholder="Price"
                                                           style="font-weight:700;font-size:15px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                if ($i === 0) {
                                    ?>
                                    <div class="col-12 text-center py-5">
                                        <div class="alert alert-warning">Pricing table me koi service nahi mili.</div>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>

                            <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="text-muted small">
                                    Tip: Search box me naam likho, jaise hi price edit karoge to card ka border green ho jayega.
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary px-3 me-1"
                                            onclick="window.scrollTo({top:0,behavior:'smooth'});">
                                        <i class="bx bx-chevrons-up me-1"></i> Jump Top
                                    </button>
                                    <button type="submit" class="btn btn-primary px-5">
                                        <i class="bx bxs-save me-1"></i> Save All Prices
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div><!--end row-->
    </div>
</div>

<?php include('footer.php'); ?>

<!-- SweetAlert2 Script -->
<?php if ($save_message !== ''): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            icon: '<?= $save_type === 'success' ? 'success' : 'error'; ?>',
            title: '<?= $save_type === 'success' ? 'Done' : 'Oops'; ?>',
            text: <?= json_encode($save_message, JSON_UNESCAPED_SLASHES); ?>,
            confirmButtonText: 'OK',
            timer: 2200
        });
    </script>
<?php endif; ?>

<!-- Bootstrap JS + common template assets -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<script>
/* =========================================================================
   1) Search filter (client side, instant, case-insensitive)
   ========================================================================= */
(function () {
    var input = document.getElementById('priceSearchInput');
    if (!input) return;
    input.addEventListener('input', gpFilterRows);
    window.gpFilterRows = gpFilterRows;

    function gpFilterRows() {
        var q = (input.value || '').trim().toLowerCase();
        var cards = document.querySelectorAll('.gp-price-card');
        var showing = 0;
        cards.forEach(function (card) {
            var s = (card.getAttribute('data-svc') || '') + ' ' + (card.getAttribute('data-display') || '');
            if (q === '' || s.indexOf(q) !== -1) {
                card.style.display = '';
                showing++;
            } else {
                card.style.display = 'none';
            }
        });
        var msg = document.getElementById('rowsShowingMsg');
        if (msg) {
            msg.innerHTML = 'Showing <strong>' + showing + '</strong> of ' + cards.length + ' services'
                + (q !== '' ? ' 🔍' : '');
        }
    }
})();

/* =========================================================================
   2) Highlight changed price cards (green border + subtle pulse)
   ========================================================================= */
(function () {
    var inputs = document.querySelectorAll('.gp-price-input');
    inputs.forEach(function (inp) {
        var orig = String(inp.getAttribute('data-original') || '');
        var card = inp.closest('.card');
        function sync() {
            var cur = String(inp.value || '');
            if (cur !== orig) {
                if (card) {
                    card.style.border = '2px solid #198754';
                    card.style.boxShadow = '0 4px 16px rgba(25,135,84,.15)';
                }
            } else {
                if (card) {
                    card.style.border = '';
                    card.style.boxShadow = '';
                }
            }
        }
        inp.addEventListener('input', sync);
        inp.addEventListener('change', sync);
        sync();
    });
})();

/* =========================================================================
   3) Validate before save (at least 1 change + confirm with count)
   ========================================================================= */
function gpValidateBeforeSave() {
    var changed = 0;
    var inputs = document.querySelectorAll('.gp-price-input');
    inputs.forEach(function (inp) {
        var orig = String(inp.getAttribute('data-original') || '');
        var cur  = String(inp.value || '');
        if (cur !== orig) changed++;
    });
    if (changed === 0) {
        Swal.fire({
            icon: 'info',
            title: 'No changes detected',
            text: 'Koi bhi price edit nahi kiya gaya. Save karne se pehle kisi ek price ko badlo.',
            confirmButtonText: 'OK'
        });
        return false;
    }
    return true;
}
</script>
</body>
</html>
