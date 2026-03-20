<?php
/**
 * Front to the WordPress application.
 * Directed exclusively to the Hello Dolly plugin.
 *
 * @package WordPress
 */

// Define WordPress theme constant (though likely unused without core)
define('WP_USE_THEMES', true);

// Load the Hello Dolly plugin directly
require_once __DIR__ . '/wp/wp-content/plugins/hello-dolly/hello.php';
