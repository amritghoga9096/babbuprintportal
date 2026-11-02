<?php
if (!isset($ahk_conn) || !$ahk_conn) {
    $_cfgPath = __DIR__ . '/config.php';
    if (file_exists($_cfgPath)) { require_once($_cfgPath); }
}
$_wa_no = '';
if (isset($webdata) && is_array($webdata) && !empty($webdata['whatsapp_number'])) {
    $_wa_no = trim((string)$webdata['whatsapp_number']);
}
if ($_wa_no === '' && isset($ahk_conn)) {
    $_wr = @mysqli_query($ahk_conn, "SELECT whatsapp_number FROM settings WHERE id=1 LIMIT 1");
    if ($_wr && ($_rw = mysqli_fetch_assoc($_wr)) && !empty($_rw['whatsapp_number'])) {
        $_wa_no = trim((string)$_rw['whatsapp_number']);
    }
}
$_wa_no = preg_replace('/[^0-9]/', '', $_wa_no);
if ($_wa_no !== '') {
    $_wa_msg = rawurlencode('Hello! I need support regarding ' . (isset($webdata['webname']) ? $webdata['webname'] : 'our services') . '.');
    $_wa_url = 'https://wa.me/' . $_wa_no . '?text=' . $_wa_msg;
    ?>
    <style>
    .wa-float-btn {
        position: fixed;
        width: 60px;
        height: 60px;
        bottom: 24px;
        right: 24px;
        background-color: #25D366;
        color: #FFF;
        border-radius: 50%;
        text-align: center;
        font-size: 30px;
        box-shadow: 0 4px 16px rgba(37,211,102,0.45);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        animation: wa-pulse 2.2s infinite;
    }
    .wa-float-btn:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 22px rgba(37,211,102,0.6);
        color: #fff;
        text-decoration: none;
    }
    .wa-float-btn svg {
        width: 34px;
        height: 34px;
        fill: #fff;
    }
    @keyframes wa-pulse {
        0%   { box-shadow: 0 0 0 0 rgba(37,211,102,0.55); }
        70%  { box-shadow: 0 0 0 16px rgba(37,211,102,0); }
        100% { box-shadow: 0 0 0 0 rgba(37,211,102,0); }
    }
    @media (max-width: 576px) {
        .wa-float-btn {
            width: 54px;
            height: 54px;
            bottom: 18px;
            right: 18px;
            font-size: 26px;
        }
        .wa-float-btn svg {
            width: 30px;
            height: 30px;
        }
    }
    </style>
    <a href="<?= htmlspecialchars($_wa_url) ?>" target="_blank" rel="noopener noreferrer"
       class="wa-float-btn" aria-label="Chat on WhatsApp" title="WhatsApp Support">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path d="M19.11 17.205c-.372 0-1.088 1.39-1.518 1.39-.324 0-.559-.243-.984-.559-.173-.132-.374-.282-.576-.407-.2-.124-.408-.22-.583-.387-.172-.166-.28-.335-.473-.529-.193-.193-.114-.579.12-.911.098-.133.215-.274.311-.418.164-.245.329-.49.356-.796.042-.45-.24-.78-.516-1.045-.254-.245-.593-.461-.999-.667-.406-.205-1.03-.39-1.639-.058-.152.082-.282.183-.427.32-.158.158-.419.388-.555.464-.339.189-.667.377-.952.582l-.028.019c-.474.343-.9.728-1.304 1.134-.406.406-.77.841-1.13 1.298-.245.312-.488.627-.721.946-.22.301-.427.603-.605.912-.178.309-.325.62-.436.934-.111.313-.184.63-.21.947-.056.653.036 1.287.285 1.867.223.527.59 1.005 1.095 1.426.32.265.686.491 1.053.706.368.215.743.416 1.098.616.709.4 1.469.755 2.256 1.069.787.313 1.603.58 2.409.808.677.191 1.374.35 2.044.495.67.145 1.317.276 1.925.392.542.104 1.081.204 1.587.294.506.09 1.01.176 1.471.257.461.081.877.157 1.272.227.396.07.771.137 1.125.197.354.06.686.115 1.002.166.316.05.617.096.904.138.287.042.56.079.82.114.26.035.507.067.742.095.235.028.458.053.67.074.212.021.414.039.606.055.192.016.376.03.55.041.174.011.339.02.496.027 0 0 1.491-.005 2.395-1.921l.005-.01c.118-.247.209-.51.272-.784.064-.274.1-.559.107-.847.005-.21.005-.42 0-.63-.007-.27-.037-.538-.086-.803-.049-.266-.12-.527-.208-.782a7.66 7.66 0 0 0-.317-.775 5.662 5.662 0 0 0-.398-.677c-.143-.193-.299-.379-.469-.557-.169-.177-.353-.345-.55-.504-.198-.158-.41-.308-.636-.45a9.053 9.053 0 0 0-.686-.389 7.44 7.44 0 0 0-.732-.329c-.249-.097-.505-.186-.767-.267-.262-.082-.53-.156-.802-.223a11.872 11.872 0 0 0-.832-.183c-.281-.053-.567-.1-.856-.143-.29-.044-.584-.082-.879-.115-.296-.034-.594-.062-.892-.086-.298-.024-.598-.043-.897-.059a14.54 14.54 0 0 0-.899-.064c-.3-.014-.6-.022-.899-.027zM28.694 3.311A15.872 15.872 0 0 0 16.001 0C7.168 0 .002 7.165.002 15.998c0 2.879.772 5.666 2.237 8.153L0 32l8.017-2.193a15.962 15.962 0 0 0 7.982 2.131h.003c8.833 0 15.999-7.165 15.999-15.999 0-4.265-1.664-8.281-4.693-11.316l-.004-.004a15.908 15.908 0 0 0-2.31-1.308zm-12.69 26.54h-.002a13.618 13.618 0 0 1-6.954-1.9l-.493-.293-4.758 1.301 1.33-4.632-.322-.51a13.632 13.632 0 0 1-2.093-7.313C2.704 8.629 8.631 2.701 16.002 2.701c3.565 0 6.91 1.39 9.423 3.901a13.58 13.58 0 0 1 3.9 9.42c.001 7.373-5.926 13.299-13.32 13.829z"/>
        </svg>
    </a>
    <?php
}
