<?php
/**
 * PHP File Manager — Glassmorphism / Deep Space Edition
 * Single-file implementation with authentication and security features
 * Version: 4.0
 */

define('FM_PASSWORD', 'bela');
define('FM_SESSION_TIMEOUT', 3600);
define('FM_ROOT_PATH', dirname(__FILE__));
define('FM_SHOW_HIDDEN', false);
define('FM_ALLOWED_EXTENSIONS', 'txt,php,html,css,js,json,xml,htaccess,md,log,sql,csv,ini,conf,yml,yaml,hpp,cpp,c,h,py,sh,bat');
define('FM_MAX_UPLOAD_SIZE_MB', 50);
define('FM_ALLOW_SYSTEM_WIDE', true);

class SecurityHelper {
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    public static function sanitizePath($path) {
        $path = str_replace(['../', '..\\'], '', $path);
        return preg_replace('#/+#', '/', $path);
    }
    public static function isPathAllowed($path) {
        if (!FM_ALLOW_SYSTEM_WIDE) {
            $rootPath = realpath(FM_ROOT_PATH);
            $checkPath = realpath($path);
            if ($checkPath === false || strpos($checkPath, $rootPath) !== 0) return false;
        }
        return true;
    }
    public static function setSecurityHeaders() {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
    }
}

class FileManagerAuth {
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            session_start();
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > FM_SESSION_TIMEOUT)) {
            self::logout(); return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
    public static function isAuthenticated() {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    public static function login($password) {
        if ($password === FM_PASSWORD) {
            $_SESSION['authenticated'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    public static function logout() { session_unset(); session_destroy(); }
}

function formatSize($bytes) {
    $units = ['B','KB','MB','GB','TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getFileExt($filename) {
    $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
    return $ext ?: '—';
}

function getExtColor($filename, $isDir) {
    if ($isDir) return ['#7C6FFF','#4A3FCC'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'php'  => ['#A78BFA','#6D28D9'],
        'html' => ['#F97316','#C2410C'],
        'css'  => ['#38BDF8','#0284C7'],
        'js'   => ['#FBBF24','#D97706'],
        'json' => ['#34D399','#059669'],
        'xml'  => ['#FB923C','#EA580C'],
        'sql'  => ['#F472B6','#DB2777'],
        'py'   => ['#60A5FA','#2563EB'],
        'sh'   => ['#A3E635','#65A30D'],
        'md'   => ['#94A3B8','#64748B'],
        'log'  => ['#FCD34D','#F59E0B'],
        'txt'  => ['#CBD5E1','#94A3B8'],
    ];
    return $map[$ext] ?? ['#94A3B8','#64748B'];
}

function isEditableFile($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, explode(',', FM_ALLOWED_EXTENSIONS));
}

function getUserDirectories() {
    $dirs = [];
    if (is_dir('/home') && is_readable('/home')) {
        $scan = @scandir('/home');
        if ($scan) foreach ($scan as $item)
            if ($item !== '.' && $item !== '..' && is_dir('/home/' . $item))
                $dirs[] = '/home/' . $item;
    }
    return $dirs;
}

SecurityHelper::setSecurityHeaders();
FileManagerAuth::startSession();

if (isset($_GET['logout'])) {
    FileManagerAuth::logout();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* ── LOGIN ─────────────────────────────────── */
if (!FileManagerAuth::isAuthenticated()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (FileManagerAuth::login($_POST['password'])) {
            header('Location: ' . $_SERVER['PHP_SELF']); exit;
        } else { $loginError = true; }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FM — Access</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@200;300;400;600;700;800&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --bg: #05071A;
    --cyan: #00F5FF;
    --violet: #9D4EFF;
    --pink: #FF2D8E;
    --glass: rgba(255,255,255,0.04);
    --glass-border: rgba(255,255,255,0.10);
    --text: #E2E8F8;
    --muted: rgba(226,232,248,0.45);
}
body {
    font-family: 'Exo 2', sans-serif;
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

/* Nebula background */
.nebula {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}
.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.35;
}
.orb-1 { width: 600px; height: 600px; background: radial-gradient(circle, #4A1FCC 0%, transparent 70%); top: -200px; left: -100px; }
.orb-2 { width: 500px; height: 500px; background: radial-gradient(circle, #0D4FBB 0%, transparent 70%); bottom: -150px; right: -100px; }
.orb-3 { width: 350px; height: 350px; background: radial-gradient(circle, #7C0DBB 0%, transparent 70%); top: 40%; left: 60%; transform: translate(-50%,-50%); opacity: 0.2; }

/* Stars */
.stars {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    background-image:
        radial-gradient(1px 1px at 10% 15%, rgba(255,255,255,0.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 25% 60%, rgba(255,255,255,0.3) 0%, transparent 100%),
        radial-gradient(1px 1px at 40% 30%, rgba(255,255,255,0.4) 0%, transparent 100%),
        radial-gradient(1.5px 1.5px at 55% 80%, rgba(255,255,255,0.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 70% 20%, rgba(255,255,255,0.3) 0%, transparent 100%),
        radial-gradient(1px 1px at 80% 55%, rgba(255,255,255,0.4) 0%, transparent 100%),
        radial-gradient(1px 1px at 90% 40%, rgba(255,255,255,0.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 15% 85%, rgba(255,255,255,0.3) 0%, transparent 100%),
        radial-gradient(1px 1px at 60% 10%, rgba(255,255,255,0.4) 0%, transparent 100%),
        radial-gradient(1.5px 1.5px at 35% 45%, rgba(255,255,255,0.6) 0%, transparent 100%);
}

/* Scan-line texture */
body::after {
    content: '';
    position: fixed;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0,0,0,0.04) 2px,
        rgba(0,0,0,0.04) 4px
    );
    pointer-events: none;
    z-index: 0;
}

.login-wrap {
    position: relative;
    z-index: 10;
    width: 440px;
    animation: floatIn 0.7s cubic-bezier(0.34,1.4,0.64,1) both;
}
@keyframes floatIn {
    from { opacity:0; transform: translateY(40px) scale(0.95); }
    to   { opacity:1; transform: translateY(0) scale(1); }
}

.login-badge {
    text-align: center;
    margin-bottom: 28px;
}
.login-badge .sys-id {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    border: 1px solid rgba(0,245,255,0.25);
    background: rgba(0,245,255,0.05);
    border-radius: 20px;
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: var(--cyan);
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 20px;
}
.login-badge .sys-id::before {
    content: '';
    display: inline-block;
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--cyan);
    box-shadow: 0 0 8px var(--cyan);
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.4;} }

.login-badge h1 {
    font-size: 52px;
    font-weight: 800;
    letter-spacing: -1px;
    line-height: 1;
    background: linear-gradient(135deg, #fff 0%, var(--cyan) 50%, var(--violet) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.login-badge h1 span { display: block; font-size: 16px; font-weight: 300; letter-spacing: 6px; margin-top: 6px; background: none; -webkit-text-fill-color: var(--muted); }

.glass-card {
    background: var(--glass);
    border: 1px solid var(--glass-border);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 20px;
    padding: 36px;
    box-shadow: 0 24px 80px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.07);
}

.error-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,45,142,0.12);
    border: 1px solid rgba(255,45,142,0.3);
    border-radius: 8px;
    padding: 10px 14px;
    color: #FF6EB0;
    font-size: 13px;
    margin-bottom: 20px;
    font-family: 'Share Tech Mono', monospace;
}
.error-chip::before { content: '!'; display:inline-block; width:16px; height:16px; border-radius:50%; background:rgba(255,45,142,0.3); text-align:center; line-height:16px; font-size:10px; font-weight:700; flex-shrink:0; }

.field-wrap { margin-bottom: 20px; }
.field-label {
    display: block;
    font-size: 10px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
    font-family: 'Share Tech Mono', monospace;
}
.field-input {
    width: 100%;
    padding: 14px 18px;
    background: rgba(0,0,0,0.35);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: var(--cyan);
    font-family: 'Share Tech Mono', monospace;
    font-size: 16px;
    letter-spacing: 4px;
    outline: none;
    transition: all 0.2s;
    caret-color: var(--cyan);
}
.field-input:focus {
    border-color: rgba(0,245,255,0.4);
    background: rgba(0,245,255,0.05);
    box-shadow: 0 0 0 3px rgba(0,245,255,0.08), 0 0 20px rgba(0,245,255,0.1);
}
.field-input::placeholder { color: rgba(0,245,255,0.2); letter-spacing: 2px; font-size:13px; }

.submit-wrap { position: relative; }
.submit-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, rgba(0,245,255,0.15) 0%, rgba(157,78,255,0.15) 100%);
    border: 1px solid rgba(0,245,255,0.35);
    border-radius: 10px;
    color: var(--cyan);
    font-family: 'Exo 2', sans-serif;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 4px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}
.submit-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(0,245,255,0.1) 0%, rgba(157,78,255,0.1) 100%);
    opacity: 0;
    transition: opacity 0.2s;
}
.submit-btn:hover {
    border-color: rgba(0,245,255,0.7);
    box-shadow: 0 0 30px rgba(0,245,255,0.2), 0 0 60px rgba(0,245,255,0.08);
    transform: translateY(-2px);
}
.submit-btn:hover::before { opacity: 1; }
.submit-btn:active { transform: translateY(0); }
</style>
</head>
<body>
<div class="nebula">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
</div>
<div class="stars"></div>

<div class="login-wrap">
    <div class="login-badge">
        <div class="sys-id">System · Secure Access</div>
        <h1>FILE MGR<span>Management Console</span></h1>
    </div>
    <div class="glass-card">
        <?php if (isset($loginError)): ?>
        <div class="error-chip">Authentication failed — check your credentials</div>
        <?php endif; ?>
        <form method="POST">
            <div class="field-wrap">
                <label class="field-label">Access Key</label>
                <input class="field-input" type="password" name="password" placeholder="· · · · · · · ·" required autofocus>
            </div>
            <button class="submit-btn" type="submit">Initialize Session →</button>
        </form>
    </div>
</div>
</body>
</html>
<?php exit; }

/* ── POST HANDLING ──────────────────────────── */
$currentPath = FM_ROOT_PATH;
$message = ''; $messageType = '';

if (isset($_GET['path'])) {
    $rp = SecurityHelper::sanitizePath($_GET['path']);
    $ck = ($rp !== '' && $rp[0] === '/') ? $rp : FM_ROOT_PATH . '/' . $rp;
    if (is_dir($ck) && SecurityHelper::isPathAllowed($ck)) $currentPath = realpath($ck);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !SecurityHelper::validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Security token mismatch.'; $messageType = 'error';
    } else {
        if (isset($_FILES['upload_file'])) {
            $up = $currentPath . '/' . basename($_FILES['upload_file']['name']);
            $mx = FM_MAX_UPLOAD_SIZE_MB * 1024 * 1024;
            if ($_FILES['upload_file']['size'] > $mx) { $message = 'File exceeds limit.'; $messageType = 'error'; }
            elseif (move_uploaded_file($_FILES['upload_file']['tmp_name'], $up)) { $message = 'File uploaded successfully.'; $messageType = 'success'; }
            else { $message = 'Upload failed.'; $messageType = 'error'; }
        }
        if (isset($_POST['create_folder'])) {
            $nf = $currentPath . '/' . basename($_POST['folder_name']);
            if (!is_dir($nf) && mkdir($nf, 0755)) { $message = 'Directory created.'; $messageType = 'success'; }
            else { $message = 'Could not create directory.'; $messageType = 'error'; }
        }
        if (isset($_POST['create_file'])) {
            $nf = $currentPath . '/' . basename($_POST['file_name']);
            if (file_put_contents($nf, '') !== false) { $message = 'File created.'; $messageType = 'success'; }
            else { $message = 'Could not create file.'; $messageType = 'error'; }
        }
        if (isset($_POST['rename_item'])) {
            $old = $currentPath . '/' . basename($_POST['old_name']);
            $new = $currentPath . '/' . basename($_POST['new_name']);
            if (rename($old, $new)) { $message = 'Renamed successfully.'; $messageType = 'success'; }
            else { $message = 'Rename failed.'; $messageType = 'error'; }
        }
        if (isset($_POST['delete_item'])) {
            $ip = $currentPath . '/' . basename($_POST['item_name']);
            function deleteDirectory($dir) {
                if (!is_dir($dir)) return unlink($dir);
                foreach (array_diff(scandir($dir), ['.','..']) as $i) {
                    $p = $dir.'/'.$i; is_dir($p) ? deleteDirectory($p) : unlink($p);
                }
                return rmdir($dir);
            }
            if (deleteDirectory($ip)) { $message = 'Deleted.'; $messageType = 'success'; }
            else { $message = 'Delete failed.'; $messageType = 'error'; }
        }
        if (isset($_POST['save_file'])) {
            $fp = SecurityHelper::sanitizePath($_POST['file_path']);
            if (file_put_contents($fp, $_POST['file_content']) !== false) { $message = 'File saved.'; $messageType = 'success'; }
            else { $message = 'Save failed.'; $messageType = 'error'; }
        }
    }
}

if (isset($_GET['download'])) {
    $df = $currentPath . '/' . basename($_GET['download']);
    if (file_exists($df) && is_file($df)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($df) . '"');
        header('Content-Length: ' . filesize($df));
        readfile($df); exit;
    }
}

/* ── EDITOR ─────────────────────────────────── */
if (isset($_GET['edit'])) {
    $ef = $currentPath . '/' . basename($_GET['edit']);
    if (file_exists($ef) && is_file($ef) && isEditableFile($ef)) {
        $fc = file_get_contents($ef);
        $fsize = formatSize(filesize($ef));
        $fmod = date('d M Y · H:i', filemtime($ef));
        $fext = strtoupper(pathinfo($ef, PATHINFO_EXTENSION));
        $back = htmlspecialchars($_SERVER['PHP_SELF'] . '?path=' . urlencode(dirname($ef)));
        $colors = getExtColor($ef, false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit · <?php echo htmlspecialchars(basename($ef)); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --bg:#05071A; --glass:rgba(255,255,255,0.04);
    --glass-border:rgba(255,255,255,0.09);
    --cyan:#00F5FF; --violet:#9D4EFF;
    --text:#E2E8F8; --muted:rgba(226,232,248,0.45);
    --panel:#0B0E26; --line:#1A1F42;
}
html,body{height:100%;overflow:hidden;}
body{font-family:'Exo 2',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;}

body::before {
    content:'';position:fixed;inset:0;
    background:
        radial-gradient(ellipse 60% 50% at 0% 0%, rgba(74,31,204,0.25) 0%, transparent 60%),
        radial-gradient(ellipse 50% 60% at 100% 100%, rgba(13,79,187,0.2) 0%, transparent 60%);
    pointer-events:none;z-index:0;
}

.editor-topbar {
    position:relative;z-index:10;
    background:rgba(11,14,38,0.9);
    border-bottom:1px solid var(--line);
    backdrop-filter:blur(12px);
    -webkit-backdrop-filter:blur(12px);
    padding:0 24px;height:56px;
    display:flex;align-items:center;gap:16px;flex-shrink:0;
}

.ext-pill {
    padding:4px 12px;border-radius:6px;
    font-family:'Share Tech Mono',monospace;font-size:11px;font-weight:500;
    letter-spacing:1px;flex-shrink:0;
    color:#fff;
}

.file-title {
    font-size:16px;font-weight:600;color:var(--text);
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.file-meta {
    margin-left:auto;
    font-family:'Share Tech Mono',monospace;font-size:11px;
    color:var(--muted);display:flex;gap:20px;flex-shrink:0;
}

.back-btn {
    display:inline-flex;align-items:center;gap:6px;
    padding:7px 14px;
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:8px;
    color:var(--muted);text-decoration:none;
    font-size:12px;letter-spacing:1px;text-transform:uppercase;
    transition:all .15s;flex-shrink:0;
}
.back-btn:hover{color:var(--text);border-color:rgba(255,255,255,0.2);background:rgba(255,255,255,0.08);}

.save-btn {
    padding:9px 22px;flex-shrink:0;
    background:linear-gradient(135deg,rgba(0,245,255,0.15),rgba(157,78,255,0.15));
    border:1px solid rgba(0,245,255,0.4);border-radius:8px;
    color:var(--cyan);font-family:'Exo 2',sans-serif;
    font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;
    cursor:pointer;transition:all .15s;
}
.save-btn:hover{box-shadow:0 0 20px rgba(0,245,255,0.2);transform:translateY(-1px);border-color:rgba(0,245,255,0.7);}

.editor-body{flex:1;display:flex;overflow:hidden;position:relative;z-index:5;}
.gutter{
    background:var(--panel);border-right:1px solid var(--line);
    padding:14px 12px;
    font-family:'Share Tech Mono',monospace;font-size:12px;
    color:rgba(226,232,248,0.2);text-align:right;
    min-width:54px;overflow:hidden;white-space:pre;line-height:1.7;
    user-select:none;
}
textarea{
    flex:1;padding:14px 20px;
    background:transparent;border:none;
    font-family:'Share Tech Mono',monospace;font-size:13px;
    line-height:1.7;color:var(--text);
    resize:none;outline:none;tab-size:4;
    caret-color:var(--cyan);
}
textarea::selection{background:rgba(0,245,255,0.15);}

.status-bar{
    position:relative;z-index:10;
    background:rgba(11,14,38,0.95);
    border-top:1px solid var(--line);
    padding:6px 24px;display:flex;gap:28px;
    align-items:center;flex-shrink:0;
}
.sitem{
    font-family:'Share Tech Mono',monospace;font-size:10px;
    color:var(--muted);letter-spacing:1px;text-transform:uppercase;
}
.sitem strong{color:var(--cyan);}
</style>
</head>
<body>
<form method="POST" action="<?php echo $back; ?>" style="display:flex;flex-direction:column;height:100%;overflow:hidden;">
<input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
<input type="hidden" name="file_path" value="<?php echo htmlspecialchars($ef); ?>">

<div class="editor-topbar">
    <a href="<?php echo $back; ?>" class="back-btn">← Back</a>
    <span class="ext-pill" style="background:linear-gradient(135deg,<?php echo $colors[0]; ?>,<?php echo $colors[1]; ?>);"><?php echo $fext; ?></span>
    <span class="file-title"><?php echo htmlspecialchars(basename($ef)); ?></span>
    <div class="file-meta">
        <span><?php echo $fsize; ?></span>
        <span><?php echo $fmod; ?></span>
    </div>
    <button type="submit" name="save_file" class="save-btn">Save →</button>
</div>

<div class="editor-body">
    <div class="gutter" id="gutter">1</div>
    <textarea name="file_content" id="ed" spellcheck="false"
        oninput="upd()"
        onscroll="document.getElementById('gutter').scrollTop=this.scrollTop"><?php echo htmlspecialchars($fc); ?></textarea>
</div>

<div class="status-bar">
    <div class="sitem">Lines <strong id="lc">1</strong></div>
    <div class="sitem">Cursor <strong id="cp">1:1</strong></div>
    <div class="sitem">Encoding <strong>UTF-8</strong></div>
    <div class="sitem" style="margin-left:auto;"><?php echo htmlspecialchars($ef); ?></div>
</div>
</form>
<script>
const ed=document.getElementById('ed'),g=document.getElementById('gutter');
function upd(){
    const n=ed.value.split('\n').length;
    g.textContent=Array.from({length:n},(_,i)=>i+1).join('\n');
    document.getElementById('lc').textContent=n;
}
ed.addEventListener('keyup',()=>{
    const l=ed.value.substring(0,ed.selectionStart).split('\n');
    document.getElementById('cp').textContent=`${l.length}:${l[l.length-1].length+1}`;
});
ed.addEventListener('keydown',e=>{
    if(e.key==='Tab'){e.preventDefault();const s=ed.selectionStart;ed.value=ed.value.substring(0,s)+'    '+ed.value.substring(ed.selectionEnd);ed.selectionStart=ed.selectionEnd=s+4;upd();}
});
upd();
</script>
</body>
</html>
<?php exit; } }

/* ── DIRECTORY SCAN ─────────────────────────── */
$items = [];
if (is_readable($currentPath)) {
    foreach (scandir($currentPath) as $item) {
        if ($item === '.' || (!FM_SHOW_HIDDEN && $item[0]==='.' && $item!=='..')) continue;
        $ip = $currentPath.'/'.$item;
        $isDir = is_dir($ip);
        $items[] = [
            'name'  => $item,
            'is_dir'=> $isDir,
            'size'  => $isDir ? '—' : formatSize(filesize($ip)),
            'date'  => date('d M Y', filemtime($ip)),
            'time'  => date('H:i', filemtime($ip)),
            'perms' => substr(sprintf('%o', fileperms($ip)), -4),
            'ext'   => $isDir ? 'DIR' : getFileExt($item),
            'colors'=> getExtColor($item, $isDir),
        ];
    }
    usort($items, fn($a,$b)=>$b['is_dir']<=>$a['is_dir']?:strcmp($a['name'],$b['name']));
}

$parts = array_filter(explode('/', str_replace('\\','/',$currentPath)));
$bc = []; $cp2 = '';
foreach ($parts as $p) { $cp2 .= '/'.$p; $bc[] = ['name'=>$p,'path'=>$cp2]; }

$userDirs = getUserDirectories();
$csrf = SecurityHelper::generateCSRFToken();
$totalFiles = count(array_filter($items, fn($i)=>!$i['is_dir']));
$totalDirs  = count(array_filter($items, fn($i)=>$i['is_dir']));
$sessionAge = gmdate('H:i:s', time() - $_SESSION['login_time']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FM — <?php echo htmlspecialchars(basename($currentPath)?:'/'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@200;300;400;600;700;800&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --bg: #05071A;
    --panel: #0B0E26;
    --panel2: #0F1230;
    --line: #1A1F42;
    --line2: #252B54;
    --glass: rgba(255,255,255,0.035);
    --glass-b: rgba(255,255,255,0.08);
    --cyan: #00F5FF;
    --cyan-dim: rgba(0,245,255,0.12);
    --cyan-mid: rgba(0,245,255,0.3);
    --violet: #9D4EFF;
    --violet-dim: rgba(157,78,255,0.12);
    --pink: #FF2D8E;
    --pink-dim: rgba(255,45,142,0.12);
    --green: #00FF94;
    --green-dim: rgba(0,255,148,0.12);
    --text: #E2E8F8;
    --muted: rgba(226,232,248,0.45);
    --muted2: rgba(226,232,248,0.25);
}
html,body{height:100%;overflow:hidden;}
body{
    font-family:'Exo 2',sans-serif;
    background:var(--bg);color:var(--text);
    display:flex;flex-direction:column;
    position:relative;
}

/* Deep space BG */
body::before {
    content:'';position:fixed;inset:0;
    background:
        radial-gradient(ellipse 70% 60% at 10% 10%, rgba(74,31,204,0.2) 0%, transparent 55%),
        radial-gradient(ellipse 60% 70% at 90% 90%, rgba(13,79,187,0.18) 0%, transparent 55%),
        radial-gradient(ellipse 40% 40% at 50% 50%, rgba(124,13,187,0.08) 0%, transparent 60%);
    pointer-events:none;z-index:0;
}

/* Scanlines */
body::after {
    content:'';position:fixed;inset:0;
    background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,0.04) 3px,rgba(0,0,0,0.04) 6px);
    pointer-events:none;z-index:0;
}

/* ── TOPBAR ── */
.topbar{
    position:relative;z-index:100;
    background:rgba(11,14,38,0.92);
    border-bottom:1px solid var(--line);
    backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
    padding:0 24px;height:52px;
    display:flex;align-items:center;gap:0;flex-shrink:0;
}

.tb-brand{
    display:flex;align-items:center;gap:10px;
    padding-right:24px;border-right:1px solid var(--line2);
    font-size:13px;font-weight:800;letter-spacing:3px;text-transform:uppercase;
}
.tb-brand .dot{
    width:8px;height:8px;border-radius:50%;
    background:var(--cyan);box-shadow:0 0 10px var(--cyan);
    animation:pulse 2s ease-in-out infinite;
}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:0.4;}}

.tb-center{flex:1;padding:0 24px;overflow:hidden;}
.tb-path{
    font-family:'Share Tech Mono',monospace;font-size:12px;
    color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.tb-path strong{color:var(--cyan);}

.tb-right{
    display:flex;align-items:center;gap:12px;
    padding-left:24px;border-left:1px solid var(--line2);
    font-family:'Share Tech Mono',monospace;font-size:11px;color:var(--muted2);
}
.tb-right a{color:var(--pink);text-decoration:none;transition:opacity .15s;opacity:0.7;}
.tb-right a:hover{opacity:1;}
.tb-sep{color:var(--line2);}

/* ── LAYOUT ── */
.layout{display:flex;flex:1;overflow:hidden;position:relative;z-index:5;}

/* ── SIDEBAR ── */
.sidebar{
    width:230px;min-width:230px;
    background:rgba(11,14,38,0.7);
    border-right:1px solid var(--line);
    backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
    display:flex;flex-direction:column;overflow:hidden;
}

.sb-stats{
    padding:20px 18px 16px;
    border-bottom:1px solid var(--line);
}
.sb-stat-row{display:flex;gap:8px;margin-bottom:12px;}
.sb-stat{
    flex:1;
    background:var(--glass);
    border:1px solid var(--glass-b);
    border-radius:10px;padding:10px 12px;text-align:center;
}
.sb-stat .num{
    font-size:26px;font-weight:800;
    background:linear-gradient(135deg,var(--cyan),var(--violet));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-clip:text;line-height:1;
}
.sb-stat .lbl{font-size:9px;letter-spacing:2px;text-transform:uppercase;color:var(--muted2);margin-top:3px;}

.sb-curdir{
    font-family:'Share Tech Mono',monospace;font-size:10px;
    color:var(--muted2);line-height:1.5;word-break:break-all;
}
.sb-curdir strong{color:var(--cyan);display:block;font-size:12px;margin-bottom:3px;}

.sb-nav{flex:1;overflow-y:auto;padding:10px 0;}
.sb-nav::-webkit-scrollbar{width:3px;}
.sb-nav::-webkit-scrollbar-thumb{background:var(--line2);border-radius:2px;}

.sb-sec-label{
    padding:10px 18px 4px;
    font-size:9px;letter-spacing:3px;text-transform:uppercase;
    color:var(--muted2);font-family:'Share Tech Mono',monospace;
}

.sb-item{
    display:flex;align-items:center;gap:10px;
    padding:8px 18px;
    color:var(--muted);font-size:13px;font-weight:400;
    text-decoration:none;
    border-left:2px solid transparent;
    transition:all .15s;
    background:none;border-top:none;border-right:none;border-bottom:none;
    width:100%;font-family:'Exo 2',sans-serif;cursor:pointer;text-align:left;
}
.sb-item:hover{color:var(--text);background:var(--glass);border-left-color:var(--line2);}
.sb-item.active{color:var(--cyan);background:var(--cyan-dim);border-left-color:var(--cyan);}
.sb-item .tag{
    margin-left:auto;font-family:'Share Tech Mono',monospace;
    font-size:9px;letter-spacing:1px;color:var(--muted2);
}
.sb-item.active .tag{color:rgba(0,245,255,0.4);}

/* ── MAIN ── */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0;}

/* ── NAV BAR ── */
.nav-bar{
    background:rgba(11,14,38,0.6);
    border-bottom:1px solid var(--line);
    backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
    padding:0 24px;height:48px;
    display:flex;align-items:center;gap:12px;flex-shrink:0;
}
.breadcrumb{flex:1;display:flex;align-items:center;gap:0;overflow:hidden;min-width:0;}
.breadcrumb a{
    color:var(--muted);text-decoration:none;
    font-size:13px;padding:3px 7px;border-radius:6px;
    transition:all .1s;white-space:nowrap;
}
.breadcrumb a:hover{color:var(--cyan);background:var(--cyan-dim);}
.breadcrumb a:last-child{color:var(--text);}
.bc-sep{color:var(--line2);padding:0 3px;font-size:18px;}

.path-form{display:flex;gap:0;}
.path-form input{
    padding:8px 14px;
    background:rgba(0,0,0,0.4);
    border:1px solid var(--line2);border-right:none;
    border-radius:8px 0 0 8px;
    color:var(--text);
    font-family:'Share Tech Mono',monospace;font-size:12px;
    outline:none;width:250px;
    transition:border-color .15s;
    caret-color:var(--cyan);
}
.path-form input:focus{border-color:var(--cyan-mid);}
.path-form button{
    padding:8px 16px;
    background:var(--cyan-dim);border:1px solid var(--cyan-mid);
    border-radius:0 8px 8px 0;
    color:var(--cyan);
    font-family:'Exo 2',sans-serif;font-size:13px;font-weight:700;
    letter-spacing:1px;text-transform:uppercase;cursor:pointer;
    transition:all .15s;
}
.path-form button:hover{background:var(--cyan-mid);}

/* ── ACTION BAR ── */
.action-bar{
    background:rgba(11,14,38,0.4);
    border-bottom:1px solid var(--line);
    padding:10px 24px;display:flex;align-items:center;gap:8px;flex-shrink:0;
}
.act{
    display:inline-flex;align-items:center;gap:7px;
    padding:8px 16px;
    border-radius:8px;
    font-family:'Exo 2',sans-serif;font-size:12px;font-weight:700;
    letter-spacing:1.5px;text-transform:uppercase;
    cursor:pointer;text-decoration:none;
    transition:all .15s;
}
.act-upload{
    background:var(--cyan-dim);border:1px solid var(--cyan-mid);color:var(--cyan);
}
.act-upload:hover{background:rgba(0,245,255,0.2);box-shadow:0 0 16px rgba(0,245,255,0.15);transform:translateY(-1px);}
.act-mkdir{
    background:var(--violet-dim);border:1px solid rgba(157,78,255,0.3);color:var(--violet);
}
.act-mkdir:hover{background:rgba(157,78,255,0.2);box-shadow:0 0 16px rgba(157,78,255,0.15);transform:translateY(-1px);}
.act-mkfile{
    background:var(--glass);border:1px solid var(--glass-b);color:var(--muted);
}
.act-mkfile:hover{color:var(--text);border-color:var(--line2);transform:translateY(-1px);}

/* ── FLASH ── */
.flash{
    margin:12px 24px 0;padding:10px 16px;
    border-radius:8px;font-size:13px;font-weight:600;
    display:flex;align-items:center;gap:10px;flex-shrink:0;
    font-family:'Share Tech Mono',monospace;letter-spacing:0.5px;
}
.flash.success{background:var(--green-dim);border:1px solid rgba(0,255,148,0.3);color:var(--green);}
.flash.error{background:var(--pink-dim);border:1px solid rgba(255,45,142,0.3);color:var(--pink);}

/* ── FILE AREA ── */
.file-area{flex:1;overflow-y:auto;padding:16px 24px 24px;}
.file-area::-webkit-scrollbar{width:6px;}
.file-area::-webkit-scrollbar-thumb{background:var(--line2);border-radius:3px;}

.file-table-wrap{
    background:rgba(11,14,38,0.6);
    border:1px solid var(--line);
    border-radius:12px;overflow:hidden;
    backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
}

table{width:100%;border-collapse:collapse;}

thead th{
    padding:11px 18px;text-align:left;
    font-size:9px;letter-spacing:3px;text-transform:uppercase;
    color:var(--muted2);
    background:rgba(0,0,0,0.3);
    border-bottom:1px solid var(--line2);
    font-weight:600;position:sticky;top:0;z-index:5;
    font-family:'Share Tech Mono',monospace;
}

tbody tr{border-bottom:1px solid var(--line);transition:background .1s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:rgba(0,245,255,0.03);}

td{padding:10px 18px;vertical-align:middle;}

.ext-pip{
    display:inline-block;
    width:36px;height:36px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    font-family:'Share Tech Mono',monospace;font-size:9px;
    font-weight:500;letter-spacing:0.5px;color:#fff;
    flex-shrink:0;
}

.name-cell{display:flex;align-items:center;gap:12px;}
.item-name{font-size:14px;font-weight:400;color:var(--text);}
.item-name a{color:inherit;text-decoration:none;transition:color .15s;}
.item-name a:hover{color:var(--cyan);}
.item-name a.isdir{color:rgba(157,78,255,0.9);font-weight:600;}
.item-name a.isdir:hover{color:var(--violet);}

td.size{font-family:'Share Tech Mono',monospace;font-size:12px;color:var(--muted);}
td.date{font-family:'Share Tech Mono',monospace;font-size:12px;color:var(--muted);}
td.date .t{color:var(--muted2);font-size:10px;margin-top:2px;}
td.perm{font-family:'Share Tech Mono',monospace;font-size:11px;color:var(--line2);}

.row-acts{display:flex;gap:4px;opacity:0;transition:opacity .1s;}
tr:hover .row-acts{opacity:1;}

.ract{
    padding:5px 11px;border-radius:6px;
    font-family:'Exo 2',sans-serif;font-size:11px;font-weight:700;
    letter-spacing:1px;text-transform:uppercase;
    cursor:pointer;text-decoration:none;
    display:inline-flex;align-items:center;
    border:1px solid var(--line2);background:transparent;
    color:var(--muted);transition:all .12s;
}
.ract:hover{transform:translateY(-1px);}
.ract-edit:hover{color:var(--cyan);border-color:var(--cyan-mid);background:var(--cyan-dim);}
.ract-dl:hover{color:var(--green);border-color:rgba(0,255,148,0.3);background:var(--green-dim);}
.ract-ren:hover{color:var(--violet);border-color:rgba(157,78,255,0.3);background:var(--violet-dim);}
.ract-del:hover{color:var(--pink);border-color:rgba(255,45,142,0.3);background:var(--pink-dim);}

.empty-state{
    text-align:center;padding:80px 20px;
    color:var(--muted2);
}
.empty-big{
    font-size:72px;font-weight:800;
    background:linear-gradient(135deg,var(--line),var(--line2));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    margin-bottom:12px;
}
.empty-state p{font-size:14px;letter-spacing:2px;text-transform:uppercase;}

/* ── MODALS ── */
.backdrop{
    display:none;position:fixed;inset:0;
    background:rgba(5,7,26,0.8);
    backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
    z-index:1000;align-items:center;justify-content:center;
}
.backdrop.open{display:flex;}

.modal{
    background:rgba(11,14,38,0.95);
    border:1px solid var(--line2);
    border-radius:16px;width:420px;max-width:92vw;
    backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
    box-shadow:0 24px 80px rgba(0,0,0,0.6),inset 0 1px 0 rgba(255,255,255,0.05);
    animation:mIn .2s cubic-bezier(0.34,1.4,0.64,1) both;
    overflow:hidden;
}
@keyframes mIn{from{opacity:0;transform:scale(0.92) translateY(-20px);}to{opacity:1;transform:scale(1) translateY(0);}}

.modal-head{
    padding:20px 24px 18px;
    border-bottom:1px solid var(--line);
    display:flex;align-items:center;justify-content:space-between;
    background:rgba(0,0,0,0.3);
}
.modal-head h3{
    font-size:16px;font-weight:700;letter-spacing:2px;text-transform:uppercase;
    background:linear-gradient(135deg,var(--cyan),var(--violet));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.modal-close{
    background:none;border:none;color:var(--muted);font-size:18px;
    cursor:pointer;padding:0;line-height:1;transition:color .15s;
    font-family:'Exo 2',sans-serif;font-weight:300;
}
.modal-close:hover{color:var(--text);}

.modal-body{padding:22px 24px;}

.m-label{
    display:block;font-size:9px;letter-spacing:3px;text-transform:uppercase;
    color:var(--muted2);margin-bottom:8px;font-family:'Share Tech Mono',monospace;
}
.m-input{
    width:100%;padding:12px 16px;
    background:rgba(0,0,0,0.5);
    border:1px solid var(--line2);border-radius:8px;
    color:var(--text);font-family:'Share Tech Mono',monospace;font-size:14px;
    outline:none;transition:all .15s;margin-bottom:20px;caret-color:var(--cyan);
}
.m-input:focus{border-color:var(--cyan-mid);box-shadow:0 0 0 3px rgba(0,245,255,0.06);}

.m-acts{display:flex;gap:8px;padding:0 24px 22px;}
.m-btn{
    padding:10px 22px;border-radius:8px;
    font-family:'Exo 2',sans-serif;font-size:13px;font-weight:700;
    letter-spacing:2px;text-transform:uppercase;cursor:pointer;border:1px solid;
    transition:all .15s;
}
.m-btn-ok{background:var(--cyan-dim);border-color:var(--cyan-mid);color:var(--cyan);}
.m-btn-ok:hover{background:rgba(0,245,255,0.2);box-shadow:0 0 20px rgba(0,245,255,0.15);transform:translateY(-1px);}
.m-btn-del{background:var(--pink-dim);border-color:rgba(255,45,142,0.4);color:var(--pink);}
.m-btn-del:hover{background:rgba(255,45,142,0.2);}
.m-btn-cancel{background:transparent;border-color:var(--line2);color:var(--muted);}
.m-btn-cancel:hover{border-color:var(--line2);color:var(--text);}

.del-warn{
    background:var(--pink-dim);border:1px solid rgba(255,45,142,0.25);
    border-radius:8px;padding:14px 16px;
    font-size:13px;color:var(--text);line-height:1.6;margin-bottom:20px;
    font-family:'Share Tech Mono',monospace;
}
.del-warn strong{color:var(--pink);}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="tb-brand">
        <div class="dot"></div>
        FILE MGR
    </div>
    <div class="tb-center">
        <div class="tb-path"><strong><?php echo htmlspecialchars($currentPath); ?></strong></div>
    </div>
    <div class="tb-right">
        <span><?php echo htmlspecialchars(get_current_user()); ?></span>
        <span class="tb-sep">|</span>
        <span><?php echo $sessionAge; ?></span>
        <span class="tb-sep">|</span>
        <a href="?logout">Logout</a>
    </div>
</div>

<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sb-stats">
        <div class="sb-stat-row">
            <div class="sb-stat">
                <div class="num"><?php echo $totalDirs; ?></div>
                <div class="lbl">Dirs</div>
            </div>
            <div class="sb-stat">
                <div class="num"><?php echo $totalFiles; ?></div>
                <div class="lbl">Files</div>
            </div>
        </div>
        <div class="sb-curdir">
            <strong><?php echo htmlspecialchars(basename($currentPath) ?: '/'); ?></strong>
            <?php echo htmlspecialchars($currentPath); ?>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-sec-label">Quick Nav</div>
        <form method="GET" style="display:contents;">
            <button type="submit" name="path" value="/" class="sb-item <?php echo $currentPath==='/'?'active':''; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Root
                <span class="tag">/</span>
            </button>
        </form>
        <form method="GET" style="display:contents;">
            <button type="submit" name="path" value="/home" class="sb-item <?php echo $currentPath==='/home'?'active':''; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Home
                <span class="tag">/home</span>
            </button>
        </form>
        <form method="GET" style="display:contents;">
            <button type="submit" name="path" value="<?php echo FM_ROOT_PATH; ?>" class="sb-item <?php echo $currentPath===realpath(FM_ROOT_PATH)?'active':''; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                Script Dir
                <span class="tag">cwd</span>
            </button>
        </form>
        <?php if (!empty($userDirs)): ?>
        <div class="sb-sec-label" style="margin-top:6px;">Users</div>
        <?php foreach ($userDirs as $ud): ?>
        <form method="GET" style="display:contents;">
            <button type="submit" name="path" value="<?php echo htmlspecialchars($ud); ?>" class="sb-item <?php echo $currentPath===realpath($ud)?'active':''; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                <?php echo htmlspecialchars(basename($ud)); ?>
                <span class="tag">usr</span>
            </button>
        </form>
        <?php endforeach; ?>
        <?php endif; ?>
    </nav>
</aside>

<!-- MAIN -->
<main class="main">
    <!-- NAV BAR -->
    <div class="nav-bar">
        <nav class="breadcrumb">
            <a href="?path=/">~</a>
            <?php foreach ($bc as $crumb): ?>
                <span class="bc-sep">/</span>
                <a href="?path=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="GET" class="path-form">
            <input type="text" name="path" placeholder="/navigate/here" value="<?php echo htmlspecialchars($currentPath); ?>">
            <button type="submit">Go</button>
        </form>
    </div>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <button onclick="openModal('upModal')" class="act act-upload">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
            Upload
        </button>
        <button onclick="openModal('mkdirModal')" class="act act-mkdir">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            New Dir
        </button>
        <button onclick="openModal('mkfileModal')" class="act act-mkfile">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            New File
        </button>
    </div>

    <?php if ($message): ?>
    <div class="flash <?php echo $messageType; ?>">
        <?php echo $messageType==='success'
            ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>'
            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'; ?>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- FILE LIST -->
    <div class="file-area">
        <?php if (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-big">VOID</div>
            <p>This directory is empty</p>
        </div>
        <?php else: ?>
        <div class="file-table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:52px;">Type</th>
                    <th>Name</th>
                    <th style="width:85px;">Size</th>
                    <th style="width:115px;">Date</th>
                    <th style="width:60px;">Mode</th>
                    <th style="width:190px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <div class="ext-pip" style="background:linear-gradient(135deg,<?php echo $item['colors'][0]; ?>22,<?php echo $item['colors'][1]; ?>44);border:1px solid <?php echo $item['colors'][0]; ?>44;color:<?php echo $item['colors'][0]; ?>;">
                        <?php echo htmlspecialchars(strlen($item['ext'])>3 ? substr($item['ext'],0,3) : $item['ext']); ?>
                    </div>
                </td>
                <td>
                    <div class="name-cell">
                        <span class="item-name">
                            <?php if ($item['is_dir']): ?>
                                <a href="?path=<?php echo urlencode($currentPath.'/'.$item['name']); ?>" class="isdir"><?php echo htmlspecialchars($item['name']); ?></a>
                            <?php else: ?>
                                <a><?php echo htmlspecialchars($item['name']); ?></a>
                            <?php endif; ?>
                        </span>
                    </div>
                </td>
                <td class="size"><?php echo htmlspecialchars($item['size']); ?></td>
                <td class="date">
                    <?php echo htmlspecialchars($item['date']); ?>
                    <div class="t"><?php echo htmlspecialchars($item['time']); ?></div>
                </td>
                <td class="perm"><?php echo htmlspecialchars($item['perms']); ?></td>
                <td>
                    <div class="row-acts">
                        <?php if (!$item['is_dir'] && isEditableFile($item['name'])): ?>
                        <a href="?path=<?php echo urlencode($currentPath); ?>&edit=<?php echo urlencode($item['name']); ?>" class="ract ract-edit">Edit</a>
                        <?php endif; ?>
                        <?php if (!$item['is_dir']): ?>
                        <a href="?path=<?php echo urlencode($currentPath); ?>&download=<?php echo urlencode($item['name']); ?>" class="ract ract-dl">DL</a>
                        <?php endif; ?>
                        <?php if ($item['name']!=='..' && $item['name']!=='.'): ?>
                        <button onclick="openRename('<?php echo htmlspecialchars(addslashes($item['name'])); ?>')" class="ract ract-ren">Ren</button>
                        <button onclick="openDelete('<?php echo htmlspecialchars(addslashes($item['name'])); ?>')" class="ract ract-del">Del</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</main>
</div>

<!-- UPLOAD MODAL -->
<div id="upModal" class="backdrop">
    <div class="modal">
        <div class="modal-head">
            <h3>Upload File</h3>
            <button class="modal-close" onclick="closeModal('upModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <label class="m-label">Select File — max <?php echo FM_MAX_UPLOAD_SIZE_MB; ?>MB</label>
                <input type="file" name="upload_file" required class="m-input" style="padding:10px 16px;font-size:12px;cursor:pointer;">
            </div>
            <div class="m-acts">
                <button type="submit" class="m-btn m-btn-ok">Upload →</button>
                <button type="button" onclick="closeModal('upModal')" class="m-btn m-btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MKDIR MODAL -->
<div id="mkdirModal" class="backdrop">
    <div class="modal">
        <div class="modal-head">
            <h3>New Directory</h3>
            <button class="modal-close" onclick="closeModal('mkdirModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <label class="m-label">Directory Name</label>
                <input type="text" name="folder_name" placeholder="my-directory" required class="m-input">
            </div>
            <div class="m-acts">
                <button type="submit" name="create_folder" class="m-btn m-btn-ok">Create →</button>
                <button type="button" onclick="closeModal('mkdirModal')" class="m-btn m-btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- MKFILE MODAL -->
<div id="mkfileModal" class="backdrop">
    <div class="modal">
        <div class="modal-head">
            <h3>New File</h3>
            <button class="modal-close" onclick="closeModal('mkfileModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <div class="modal-body">
                <label class="m-label">File Name</label>
                <input type="text" name="file_name" placeholder="index.php" required class="m-input">
            </div>
            <div class="m-acts">
                <button type="submit" name="create_file" class="m-btn m-btn-ok">Create →</button>
                <button type="button" onclick="closeModal('mkfileModal')" class="m-btn m-btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- RENAME MODAL -->
<div id="renModal" class="backdrop">
    <div class="modal">
        <div class="modal-head">
            <h3>Rename Item</h3>
            <button class="modal-close" onclick="closeModal('renModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="old_name" id="renOld">
            <div class="modal-body">
                <label class="m-label">New Name</label>
                <input type="text" name="new_name" id="renNew" required class="m-input">
            </div>
            <div class="m-acts">
                <button type="submit" name="rename_item" class="m-btn m-btn-ok">Rename →</button>
                <button type="button" onclick="closeModal('renModal')" class="m-btn m-btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE MODAL -->
<div id="delModal" class="backdrop">
    <div class="modal">
        <div class="modal-head">
            <h3>Delete Item</h3>
            <button class="modal-close" onclick="closeModal('delModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="item_name" id="delItem">
            <div class="modal-body">
                <div class="del-warn">Delete <strong id="delName"></strong>? This is permanent and cannot be undone.</div>
            </div>
            <div class="m-acts">
                <button type="submit" name="delete_item" class="m-btn m-btn-del">Delete</button>
                <button type="button" onclick="closeModal('delModal')" class="m-btn m-btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
function openRename(n){
    document.getElementById('renOld').value=n;
    document.getElementById('renNew').value=n;
    openModal('renModal');
    setTimeout(()=>document.getElementById('renNew').select(),60);
}
function openDelete(n){
    document.getElementById('delItem').value=n;
    document.getElementById('delName').textContent=n;
    openModal('delModal');
}
document.querySelectorAll('.backdrop').forEach(b=>{
    b.addEventListener('click',e=>{if(e.target===b)b.classList.remove('open');});
});
document.addEventListener('keydown',e=>{
    if(e.key==='Escape')document.querySelectorAll('.backdrop.open').forEach(b=>b.classList.remove('open'));
});
</script>
</body>
</html>
