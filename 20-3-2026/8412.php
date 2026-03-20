<?php
/**
 * PHP File Manager — Read-Only Edition
 * Directory browsing and file viewing only. No upload, edit, or delete.
 * Version: 4.0 — NEON TERMINAL
 */

define('FM_PASSWORD', 'bela');
define('FM_SESSION_TIMEOUT', 3600);
define('FM_ROOT_PATH', dirname(__FILE__));
define('FM_SHOW_HIDDEN', false);
define('FM_ALLOWED_EXTENSIONS', 'txt,php,html,css,js,json,xml,htaccess,md,log,sql,csv,ini,conf,yml,yaml,hpp,cpp,c,h,py,sh,bat');
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

function getFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'php'=>'PHP','html'=>'HTML','htm'=>'HTML','css'=>'CSS','js'=>'JS',
        'json'=>'JSON','xml'=>'XML','txt'=>'TXT','md'=>'MD','log'=>'LOG',
        'sql'=>'SQL','csv'=>'CSV','ini'=>'INI','yml'=>'YML','yaml'=>'YML',
        'conf'=>'CNF','py'=>'PY','sh'=>'SH','bat'=>'BAT','cpp'=>'C++',
        'c'=>'C','h'=>'H','hpp'=>'H++',
    ];
    return $map[$ext] ?? strtoupper($ext) ?: '—';
}

function getFileColor($filename, $isDir) {
    if ($isDir) return '#00f0ff';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $colors = [
        'php'=>'#a78bfa','html'=>'#f472b6','css'=>'#38bdf8','js'=>'#fbbf24',
        'json'=>'#c084fc','xml'=>'#fb923c','txt'=>'#94a3b8','md'=>'#2dd4bf',
        'sql'=>'#f43f5e','py'=>'#34d399','sh'=>'#ef4444','log'=>'#a3a310',
        'csv'=>'#22d3ee','yml'=>'#e879f9','yaml'=>'#e879f9',
    ];
    return $colors[$ext] ?? '#64748b';
}

function isViewableFile($filename) {
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

if (!FileManagerAuth::isAuthenticated()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (FileManagerAuth::login($_POST['password'])) {
            header('Location: ' . $_SERVER['PHP_SELF']); exit;
        } else { $loginError = 'ACCESS DENIED — Invalid credentials.'; }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>// TERMINAL ACCESS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,300;0,400;0,600;0,700;1,400&family=Orbitron:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --bg: #0a0a0f;
    --surface: #0f1018;
    --surface2: #141420;
    --neon: #00f0ff;
    --neon-dim: #00f0ff40;
    --neon-glow: #00f0ff20;
    --magenta: #f0a;
    --magenta-dim: #f0a4;
    --green: #0f6;
    --green-dim: #0f64;
    --text: #c8d0e0;
    --text-dim: #5a6080;
    --border: #1a1a2e;
    --danger: #f43f5e;
}

body {
    font-family: 'JetBrains Mono', monospace;
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    color: var(--text);
}

/* Scanline overlay */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0, 0, 0, 0.08) 2px,
        rgba(0, 0, 0, 0.08) 4px
    );
    pointer-events: none;
    z-index: 9999;
}

/* Grid background */
body::after {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
        linear-gradient(var(--border) 1px, transparent 1px),
        linear-gradient(90deg, var(--border) 1px, transparent 1px);
    background-size: 60px 60px;
    opacity: 0.3;
    pointer-events: none;
}

/* Floating orbs */
.orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    animation: orbFloat 12s ease-in-out infinite alternate;
}
.orb-1 {
    width: 500px; height: 500px;
    background: var(--neon-glow);
    top: -200px; right: -150px;
}
.orb-2 {
    width: 400px; height: 400px;
    background: rgba(255, 0, 170, 0.06);
    bottom: -150px; left: -100px;
    animation-delay: -6s;
}
@keyframes orbFloat {
    from { transform: translate(0, 0) scale(1); }
    to { transform: translate(30px, -20px) scale(1.1); }
}

.card {
    position: relative;
    z-index: 10;
    width: 460px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 2px;
    overflow: hidden;
    animation: glitchIn 0.5s ease-out both;
    box-shadow:
        0 0 40px rgba(0, 240, 255, 0.05),
        inset 0 0 80px rgba(0, 0, 0, 0.3);
}

@keyframes glitchIn {
    0% { opacity: 0; transform: translateY(20px); clip-path: inset(0 0 100% 0); }
    40% { clip-path: inset(0 0 40% 0); }
    60% { clip-path: inset(0 0 10% 0); opacity: 1; }
    100% { transform: translateY(0); clip-path: inset(0 0 0 0); }
}

.card-top {
    background: linear-gradient(135deg, var(--surface2) 0%, #0d0d1a 100%);
    padding: 32px 36px 28px;
    position: relative;
    border-bottom: 1px solid var(--border);
}

.card-top::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--neon), var(--magenta), transparent);
    animation: scanBar 3s linear infinite;
}

@keyframes scanBar {
    0% { opacity: 0.3; }
    50% { opacity: 1; }
    100% { opacity: 0.3; }
}

.eyebrow {
    font-family: 'Orbitron', sans-serif;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--neon);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.eyebrow::before {
    content: '>';
    color: var(--magenta);
    font-size: 12px;
}

.card-top h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 28px;
    font-weight: 900;
    color: #fff;
    line-height: 1.1;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.card-top h1 span {
    color: var(--neon);
    text-shadow: 0 0 20px var(--neon-dim), 0 0 40px var(--neon-glow);
}

.card-body { padding: 32px 36px 36px; }

.error-msg {
    background: rgba(244, 63, 94, 0.08);
    border: 1px solid rgba(244, 63, 94, 0.2);
    border-left: 3px solid var(--danger);
    padding: 12px 16px;
    color: var(--danger);
    font-size: 11px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.field-label {
    display: block;
    font-family: 'Orbitron', sans-serif;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--text-dim);
    margin-bottom: 10px;
}

.field-input {
    width: 100%;
    padding: 14px 18px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 2px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    color: var(--neon);
    outline: none;
    transition: all 0.2s;
    margin-bottom: 28px;
    letter-spacing: 4px;
}

.field-input::placeholder { color: var(--text-dim); letter-spacing: 2px; }

.field-input:focus {
    border-color: var(--neon);
    box-shadow: 0 0 0 3px var(--neon-glow), 0 0 20px var(--neon-glow);
}

.submit-btn {
    width: 100%;
    padding: 16px;
    background: transparent;
    color: var(--neon);
    border: 1px solid var(--neon);
    border-radius: 2px;
    font-family: 'Orbitron', sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 3px;
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
    background: linear-gradient(135deg, var(--neon-glow) 0%, transparent 50%);
    opacity: 0;
    transition: opacity 0.2s;
}

.submit-btn:hover {
    background: var(--neon);
    color: var(--bg);
    box-shadow: 0 0 30px var(--neon-dim), 0 0 60px var(--neon-glow);
}
.submit-btn:hover::before { opacity: 1; }
.submit-btn:active { transform: scale(0.98); }

.card-footer {
    padding: 14px 36px;
    border-top: 1px solid var(--border);
    font-size: 10px;
    color: var(--text-dim);
    text-align: center;
    letter-spacing: 1px;
    display: flex;
    justify-content: center;
    gap: 16px;
}

.card-footer span { display: flex; align-items: center; gap: 6px; }
.card-footer .dot { width: 5px; height: 5px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green); animation: blink 2s infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
</style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="card">
    <div class="card-top">
        <div class="eyebrow">Read-Only Terminal</div>
        <h1>FILE <span>ACCESS</span></h1>
    </div>
    <div class="card-body">
        <?php if (isset($loginError)): ?>
        <div class="error-msg">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo htmlspecialchars($loginError); ?>
        </div>
        <?php endif; ?>
        <form method="POST">
            <label class="field-label">Passphrase</label>
            <input class="field-input" type="password" name="password" placeholder="••••••••" required autofocus>
            <button class="submit-btn" type="submit">Authenticate &rarr;</button>
        </form>
    </div>
    <div class="card-footer">
        <span><span class="dot"></span> System Online</span>
        <span>Session: 60 min</span>
    </div>
</div>
</body>
</html>
<?php exit; }

// ---- AUTHENTICATED SECTION ----

$currentPath = FM_ROOT_PATH;

if (isset($_GET['path'])) {
    $rp = SecurityHelper::sanitizePath($_GET['path']);
    $checkPath = ($rp[0] === '/') ? $rp : FM_ROOT_PATH . '/' . $rp;
    if (is_dir($checkPath) && SecurityHelper::isPathAllowed($checkPath))
        $currentPath = realpath($checkPath);
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

if (isset($_GET['view'])) {
    $ef = $currentPath . '/' . basename($_GET['view']);
    if (file_exists($ef) && is_file($ef) && isViewableFile($ef)) {
        $fc = file_get_contents($ef);
        $fsize = formatSize(filesize($ef));
        $fmod = date('d M Y, H:i', filemtime($ef));
        $fext = strtoupper(pathinfo($ef, PATHINFO_EXTENSION));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>// <?php echo htmlspecialchars(basename($ef)); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600&family=Orbitron:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --bg: #0a0a0f;
    --surface: #0f1018;
    --surface2: #141420;
    --neon: #00f0ff;
    --neon-dim: #00f0ff40;
    --neon-glow: #00f0ff20;
    --magenta: #f0a;
    --green: #0f6;
    --text: #c8d0e0;
    --text-dim: #5a6080;
    --border: #1a1a2e;
    --line-num: #2a2a40;
}
body {
    font-family: 'JetBrains Mono', monospace;
    background: var(--bg);
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    color: var(--text);
}

/* Scanlines */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px);
    pointer-events: none;
    z-index: 9999;
}

.editor-top {
    background: var(--surface);
    padding: 0 24px;
    height: 56px;
    display: flex;
    align-items: center;
    gap: 18px;
    flex-shrink: 0;
    border-bottom: 1px solid var(--border);
    position: relative;
}

.editor-top::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--neon), var(--magenta), transparent);
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--neon);
    text-decoration: none;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 600;
    transition: all 0.15s;
    padding: 7px 14px;
    border: 1px solid var(--border);
    border-radius: 2px;
    background: transparent;
}
.back-btn:hover { background: var(--neon-glow); border-color: var(--neon-dim); }

.file-info { flex: 1; }
.file-name {
    font-family: 'Orbitron', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    letter-spacing: 1px;
}
.file-meta { font-size: 10px; color: var(--text-dim); margin-top: 2px; letter-spacing: 0.5px; }
.file-meta span { color: var(--neon); }

.readonly-badge {
    padding: 6px 14px;
    background: rgba(0, 240, 255, 0.06);
    color: var(--neon);
    border: 1px solid rgba(0, 240, 255, 0.15);
    border-radius: 2px;
    font-family: 'Orbitron', sans-serif;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.editor-body {
    flex: 1;
    display: flex;
    overflow: hidden;
    background: var(--bg);
}

.line-gutter {
    background: var(--surface);
    border-right: 1px solid var(--border);
    padding: 16px 14px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: var(--line-num);
    text-align: right;
    min-width: 58px;
    overflow: hidden;
    white-space: pre;
    line-height: 1.8;
    user-select: none;
}

textarea {
    flex: 1;
    padding: 16px 20px;
    background: transparent;
    border: none;
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    line-height: 1.8;
    color: var(--text);
    resize: none;
    outline: none;
    tab-size: 4;
    cursor: default;
}
textarea::selection { background: rgba(0, 240, 255, 0.15); }

.editor-foot {
    background: var(--surface);
    padding: 0 24px;
    height: 32px;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-shrink: 0;
    border-top: 1px solid var(--border);
}

.status-pill {
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--text-dim);
    display: flex;
    align-items: center;
    gap: 6px;
}
.status-pill span { color: var(--neon); font-weight: 600; }
</style>
</head>
<body>
<div class="editor-top">
    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?path=' . urlencode(dirname($ef))); ?>" class="back-btn">
        &larr; Back
    </a>
    <div class="file-info">
        <div class="file-name"><?php echo htmlspecialchars(basename($ef)); ?></div>
        <div class="file-meta"><span><?php echo $fext; ?></span> &middot; <?php echo $fsize; ?> &middot; <?php echo $fmod; ?></div>
    </div>
    <div class="readonly-badge">Read Only</div>
</div>

<div class="editor-body">
    <div class="line-gutter" id="gutter">1</div>
    <textarea id="ed" spellcheck="false" readonly><?php echo htmlspecialchars($fc); ?></textarea>
</div>

<div class="editor-foot">
    <div class="status-pill">Lines <span id="lcount">1</span></div>
    <div class="status-pill">Enc <span>UTF-8</span></div>
    <div class="status-pill" style="margin-left:auto;">Mode <span>Read</span></div>
</div>

<script>
const ed = document.getElementById('ed'), g = document.getElementById('gutter');
function upd() {
    const n = ed.value.split('\n').length;
    g.textContent = Array.from({length:n},(_,i)=>i+1).join('\n');
    document.getElementById('lcount').textContent = n;
}
function sync() { g.scrollTop = ed.scrollTop; }
ed.addEventListener('scroll', sync);
upd();
</script>
</body>
</html>
<?php exit; } }

// ---- MAIN FILE BROWSER ----

$items = [];
if (is_readable($currentPath)) {
    foreach (scandir($currentPath) as $item) {
        if ($item === '.' || (!FM_SHOW_HIDDEN && $item[0] === '.' && $item !== '..')) continue;
        $ip = $currentPath . '/' . $item;
        $isDir = is_dir($ip);
        $items[] = [
            'name' => $item,
            'is_dir' => $isDir,
            'size' => $isDir ? '—' : formatSize(filesize($ip)),
            'modified' => date('d M Y', filemtime($ip)),
            'time' => date('H:i', filemtime($ip)),
            'permissions' => substr(sprintf('%o', fileperms($ip)), -4),
            'color' => getFileColor($item, $isDir),
            'type' => $isDir ? 'DIR' : getFileType($item),
        ];
    }
    usort($items, fn($a, $b) => $b['is_dir'] <=> $a['is_dir'] ?: strcmp($a['name'], $b['name']));
}

$pathParts = explode('/', str_replace('\\', '/', $currentPath));
$breadcrumb = []; $cp = '';
foreach ($pathParts as $p) {
    if ($p === '') continue;
    $cp .= '/' . $p;
    $breadcrumb[] = ['name' => $p, 'path' => $cp];
}

$userDirs = getUserDirectories();
$sessionAge = gmdate('H:i:s', time() - $_SESSION['login_time']);
$totalItems = count($items);
$totalFiles = count(array_filter($items, fn($i) => !$i['is_dir']));
$totalDirs = count(array_filter($items, fn($i) => $i['is_dir']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>// TERMINAL · <?php echo htmlspecialchars(basename($currentPath) ?: '/'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Orbitron:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --bg: #0a0a0f;
    --surface: #0f1018;
    --surface2: #141420;
    --surface3: #181828;
    --neon: #00f0ff;
    --neon-dim: #00f0ff40;
    --neon-glow: #00f0ff15;
    --magenta: #f0a;
    --magenta-dim: #ff00aa30;
    --green: #0f6;
    --green-dim: #00ff6630;
    --yellow: #fbbf24;
    --text: #c8d0e0;
    --text-dim: #4a5070;
    --text-bright: #e8ecf4;
    --border: #1a1a2e;
    --border2: #22223a;
    --hover: #12121e;
    --danger: #f43f5e;
}

html, body { height: 100%; }
body {
    font-family: 'JetBrains Mono', monospace;
    background: var(--bg);
    color: var(--text);
    overflow: hidden;
}

/* Scanlines */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.05) 2px, rgba(0,0,0,0.05) 4px);
    pointer-events: none;
    z-index: 9999;
}

.app { display: flex; height: 100vh; }

/* ─── SIDEBAR ─── */
.sidebar {
    width: 260px;
    min-width: 260px;
    background: var(--surface);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
    border-right: 1px solid var(--border);
}

.sidebar::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, var(--neon), var(--magenta));
}

.sidebar-brand {
    padding: 24px 20px 20px;
    border-bottom: 1px solid var(--border);
}

.sidebar-brand .eyebrow {
    font-family: 'Orbitron', sans-serif;
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--text-dim);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.sidebar-brand .eyebrow::before {
    content: '';
    width: 6px; height: 6px;
    background: var(--green);
    border-radius: 50%;
    box-shadow: 0 0 8px var(--green);
    animation: pulse 2s infinite;
}

@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.sidebar-brand h2 {
    font-family: 'Orbitron', sans-serif;
    font-size: 18px;
    font-weight: 900;
    color: #fff;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.sidebar-brand h2 span {
    color: var(--neon);
    text-shadow: 0 0 10px var(--neon-dim);
}

.sidebar-meta {
    font-size: 10px;
    color: var(--text-dim);
    margin-top: 8px;
    letter-spacing: 0.5px;
}
.sidebar-meta span { color: var(--neon); }

.sidebar-nav { flex: 1; overflow-y: auto; padding: 12px 0; }
.sidebar-nav::-webkit-scrollbar { width: 3px; }
.sidebar-nav::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }

.nav-section { margin-bottom: 4px; }
.nav-section-label {
    padding: 10px 20px 6px;
    font-family: 'Orbitron', sans-serif;
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--text-dim);
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 20px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 12px;
    transition: all 0.12s;
    border-left: 2px solid transparent;
    background: none;
    border-top: none; border-right: none; border-bottom: none;
    width: 100%;
    font-family: 'JetBrains Mono', monospace;
    cursor: pointer;
    text-align: left;
    letter-spacing: 0.3px;
}
.nav-item:hover { color: var(--text); background: var(--hover); border-left-color: var(--neon-dim); }
.nav-item.active { color: var(--neon); background: rgba(0, 240, 255, 0.04); border-left-color: var(--neon); }

.nav-icon { width: 16px; flex-shrink: 0; opacity: 0.5; }
.nav-item:hover .nav-icon { opacity: 0.8; }
.nav-item.active .nav-icon { opacity: 1; }

.sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
}
.logout-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 11px;
    letter-spacing: 0.5px;
    transition: color 0.15s;
    background: none;
    border: none;
    cursor: pointer;
    font-family: 'JetBrains Mono', monospace;
    padding: 0;
}
.logout-btn:hover { color: var(--danger); }

/* ─── MAIN ─── */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

/* ─── TOPBAR ─── */
.topbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    height: 50px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.breadcrumb {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0;
    overflow: hidden;
    min-width: 0;
}
.breadcrumb a, .breadcrumb span.crumb-text {
    color: var(--text-dim);
    text-decoration: none;
    font-size: 12px;
    padding: 4px 6px;
    border-radius: 2px;
    transition: all 0.12s;
    white-space: nowrap;
}
.breadcrumb a:hover { color: var(--neon); background: var(--neon-glow); }
.breadcrumb .sep { color: var(--border2); font-size: 14px; padding: 0 2px; }
.breadcrumb a:last-child { color: var(--text-bright); font-weight: 500; }

.path-go {
    display: flex;
    gap: 0;
}
.path-go input {
    padding: 7px 14px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 2px 0 0 2px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: var(--text);
    outline: none;
    width: 220px;
    transition: border-color 0.15s;
}
.path-go input:focus { border-color: var(--neon); box-shadow: 0 0 0 2px var(--neon-glow); }
.path-go button {
    padding: 7px 16px;
    background: var(--neon);
    border: 1px solid var(--neon);
    border-left: none;
    border-radius: 0 2px 2px 0;
    color: var(--bg);
    font-family: 'Orbitron', sans-serif;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.15s;
}
.path-go button:hover { background: #fff; border-color: #fff; }

/* ─── INFO STRIP ─── */
.info-strip {
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    padding: 8px 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.readonly-notice {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 2px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-dim);
    font-size: 10px;
    letter-spacing: 0.5px;
}

.stats-pill {
    margin-left: auto;
    font-size: 10px;
    color: var(--text-dim);
    background: var(--surface);
    padding: 5px 14px;
    border-radius: 2px;
    border: 1px solid var(--border);
    display: flex;
    gap: 12px;
    align-items: center;
}
.stats-pill strong { color: var(--neon); font-weight: 600; }

/* ─── FILE TABLE ─── */
.file-area { flex: 1; overflow-y: auto; padding: 16px 24px 24px; }
.file-area::-webkit-scrollbar { width: 5px; }
.file-area::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }
.file-area::-webkit-scrollbar-track { background: transparent; }

.file-grid {
    background: var(--surface);
    border-radius: 2px;
    border: 1px solid var(--border);
    overflow: hidden;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
}

table { width: 100%; border-collapse: collapse; }

thead th {
    padding: 11px 18px;
    text-align: left;
    font-family: 'Orbitron', sans-serif;
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: var(--text-dim);
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
}

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.08s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--hover); }

td { padding: 10px 18px; vertical-align: middle; }

.type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 2px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.5px;
    min-width: 36px;
    text-align: center;
    background: transparent;
    border: 1px solid;
}

.name-cell { display: flex; align-items: center; gap: 10px; }
.name-cell a, .name-cell span {
    color: var(--text);
    text-decoration: none;
    font-size: 13px;
    transition: color 0.12s;
}
.name-cell a:hover { color: var(--neon); }
.dir-name { color: var(--neon) !important; }
.dir-name::before { content: '> '; color: var(--text-dim); font-size: 11px; }
td.size-col { font-size: 11px; color: var(--text-dim); }
td.date-col { font-size: 11px; color: var(--text-dim); }
td.date-col .time { font-size: 9px; color: var(--border2); margin-top: 1px; }
td.perm-col { font-size: 10px; color: var(--border2); }

.row-actions { display: flex; gap: 4px; opacity: 0; transition: opacity 0.1s; }
tr:hover .row-actions { opacity: 1; }

.ract {
    padding: 4px 10px;
    border-radius: 2px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid var(--border);
    background: var(--surface2);
    color: var(--text-dim);
    transition: all 0.1s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    letter-spacing: 0.3px;
}
.ract:hover { transform: translateY(-1px); }
.ract-view:hover { color: var(--neon); border-color: var(--neon-dim); background: var(--neon-glow); box-shadow: 0 0 12px var(--neon-glow); }
.ract-dl:hover { color: var(--green); border-color: var(--green-dim); background: rgba(0, 255, 102, 0.05); box-shadow: 0 0 12px rgba(0, 255, 102, 0.08); }

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--text-dim);
}
.empty-state .empty-icon {
    font-size: 40px;
    margin-bottom: 16px;
    opacity: 0.3;
}
.empty-state p { font-size: 13px; letter-spacing: 1px; }

/* ─── ANIMATIONS ─── */
@keyframes fadeRow {
    from { opacity: 0; transform: translateX(-8px); }
    to { opacity: 1; transform: translateX(0); }
}
tbody tr {
    animation: fadeRow 0.3s ease-out both;
}
<?php foreach ($items as $i => $item): ?>
tbody tr:nth-child(<?php echo $i + 1; ?>) { animation-delay: <?php echo $i * 0.02; ?>s; }
<?php endforeach; ?>
</style>
</head>
<body>
<div class="app">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="eyebrow">Workspace</div>
        <h2>FILE <span>SYS</span></h2>
        <div class="sidebar-meta"><span><?php echo htmlspecialchars(get_current_user()); ?></span> &middot; <?php echo $sessionAge; ?></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-label">Nodes</div>
            <form method="GET" style="display:contents;">
                <button type="submit" name="path" value="/" class="nav-item <?php echo $currentPath==='/'?'active':''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="2" x2="12" y2="22"/><path d="M2 12h20"/></svg>
                    root /
                </button>
            </form>
            <form method="GET" style="display:contents;">
                <button type="submit" name="path" value="/home" class="nav-item <?php echo $currentPath==='/home'?'active':''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    /home
                </button>
            </form>
            <form method="GET" style="display:contents;">
                <button type="submit" name="path" value="<?php echo FM_ROOT_PATH; ?>" class="nav-item <?php echo $currentPath===realpath(FM_ROOT_PATH)?'active':''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    ./local
                </button>
            </form>
        </div>
        <?php if (!empty($userDirs)): ?>
        <div class="nav-section">
            <div class="nav-section-label">Users</div>
            <?php foreach ($userDirs as $ud): ?>
            <form method="GET" style="display:contents;">
                <button type="submit" name="path" value="<?php echo htmlspecialchars($ud); ?>" class="nav-item <?php echo $currentPath===realpath($ud)?'active':''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="4" width="16" height="16" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="13" y2="13"/></svg>
                    <?php echo htmlspecialchars(basename($ud)); ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="?logout" class="logout-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            disconnect
        </a>
    </div>
</aside>

<!-- MAIN AREA -->
<div class="main">
    <div class="topbar">
        <nav class="breadcrumb">
            <a href="?path=/">~</a>
            <?php foreach ($breadcrumb as $crumb): ?>
                <span class="sep">/</span>
                <a href="?path=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="GET" class="path-go">
            <input type="text" name="path" placeholder="/path/to/dir" value="<?php echo htmlspecialchars($currentPath); ?>">
            <button type="submit">Go</button>
        </form>
    </div>

    <div class="info-strip">
        <div class="readonly-notice">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            read-only
        </div>
        <div class="stats-pill">
            <span><strong><?php echo $totalDirs; ?></strong> dirs</span>
            <span style="color:var(--border2)">&middot;</span>
            <span><strong><?php echo $totalFiles; ?></strong> files</span>
        </div>
    </div>

    <div class="file-area">
        <div class="file-grid">
            <?php if (empty($items)): ?>
            <div class="empty-state">
                <div class="empty-icon">&#9633;</div>
                <p>// empty directory</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:44px">Type</th>
                        <th>Name</th>
                        <th style="width:90px">Size</th>
                        <th style="width:120px">Modified</th>
                        <th style="width:70px">Mode</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <span class="type-badge" style="color:<?php echo $item['color']; ?>; border-color:<?php echo $item['color']; ?>40;">
                            <?php echo htmlspecialchars($item['type']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="name-cell">
                            <?php if ($item['is_dir']): ?>
                                <a href="?path=<?php echo urlencode($currentPath . '/' . $item['name']); ?>" class="dir-name">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="size-col"><?php echo htmlspecialchars($item['size']); ?></td>
                    <td class="date-col">
                        <?php echo htmlspecialchars($item['modified']); ?>
                        <div class="time"><?php echo htmlspecialchars($item['time']); ?></div>
                    </td>
                    <td class="perm-col"><?php echo htmlspecialchars($item['permissions']); ?></td>
                    <td>
                        <div class="row-actions">
                            <?php if (!$item['is_dir'] && isViewableFile($item['name'])): ?>
                            <a href="?path=<?php echo urlencode($currentPath); ?>&view=<?php echo urlencode($item['name']); ?>" class="ract ract-view">
                                view
                            </a>
                            <?php endif; ?>
                            <?php if (!$item['is_dir']): ?>
                            <a href="?path=<?php echo urlencode($currentPath); ?>&download=<?php echo urlencode($item['name']); ?>" class="ract ract-dl">
                                save
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</body>
</html>
