<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== IMAGE PATH DIAGNOSTIC FOR MANUAL PAN ===\n\n";
echo "CWD: " . getcwd() . "\n\n";

$dirs_to_check = [
    __DIR__ . '/../../admin/uploads/',
    __DIR__ . '/../uploads/',
    __DIR__ . '/uploads/',
];
echo "--- Checking which uploads dir actually has files ---\n";
foreach($dirs_to_check as $d) {
    $real = @realpath($d);
    $exists = is_dir($d) ? "EXISTS" : "NOPE";
    $count = 0;
    if(is_dir($d)){
        $files = glob($d."*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}", GLOB_BRACE);
        $count = count($files);
    }
    echo "$d -> $exists, $count images\n";
    if($real) echo "  realpath: $real\n";
}
echo "\n--- Sample DB paths & what resolveImg should produce ---\n";
$sample_paths = [
    '',
    'uploads/Profile-Male-PNG.png',
    'uploads/WhatsApp Image 2026-06-06 at 10.08.32.jpeg',
    'uploads/A.jpg',
    'uploads/1758904520_b137ea5c9713_imagefile.png',
    'https://www.tribal.mp.gov.in/images/photo.jpg',
    'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA',
    'admin/uploads/myphoto.jpg',
];

function encodeImgUrl($path) {
    if(empty($path) || substr($path,0,5)=='data:' || substr($path,0,4)=='http') return $path;
    $parts = explode('/', $path);
    $encoded = [];
    foreach($parts as $p) {
        $encoded[] = rawurlencode($p);
    }
    return implode('/', $encoded);
}

function resolveImg($path, $weburl, $fallback='images/q.jpg') {
    if(empty($path)) return $fallback;
    if(substr($path,0,10)=='data:image') return $path;
    if(substr($path,0,7)=='http://' || substr($path,0,8)=='https://') return $path;
    $path = ltrim($path,'/');
    $bn = basename($path);

    $try_local = [
        $path,
        '../'.$path,
        '../../admin/'.$path,
        'uploads/'.$bn,
        '../uploads/'.$bn,
        '../../admin/uploads/'.$bn,
    ];
    $found = [];
    foreach($try_local as $lp) {
        if(file_exists($lp)) {
            $found[] = $lp;
        }
    }
    if(!empty($found)){
        return ["resolved"=>encodeImgUrl($found[0]), "tried"=>$found];
    }
    if(substr($path,0,6)=='admin/') {
        if(!empty($weburl)) return ["resolved"=>encodeImgUrl($weburl.'/'.$path),"tried"=>$try_local];
        return ["resolved"=>encodeImgUrl('../'.substr($path,6)),"tried"=>$try_local];
    }
    if(!empty($weburl)) {
        if(substr($path,0,7)=='uploads') return ["resolved"=>encodeImgUrl($weburl.'/admin/'.$path),"tried"=>$try_local];
        return ["resolved"=>encodeImgUrl($weburl.'/'.$path),"tried"=>$try_local];
    }
    return ["resolved"=>encodeImgUrl('../uploads/'.$bn),"tried"=>$try_local];
}

$weburl = "https://goodprintportal.in";
echo "Using weburl = $weburl\n\n";
foreach($sample_paths as $sp) {
    echo "INPUT: " . var_export($sp,true) . "\n";
    $r = resolveImg($sp,$weburl);
    if(is_array($r)){
        echo "  RESOLVED: " . $r["resolved"] . "\n";
        if(!empty($r["tried"])) echo "  FILESYSTEM MATCHES: ".implode(", ",$r["tried"])."\n";
    } else {
        echo "  RESOLVED: $r\n";
    }
    echo "\n";
}

echo "=== DONE. Now load /admin/aadharpvc/pan.php?searchid=154 in browser, View Source, find DEBUG_PAN_IMG comment to see actual DB values. ===\n";
?>