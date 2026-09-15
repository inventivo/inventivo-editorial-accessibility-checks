<?php
/**
 * Plugin Name: inventivo Editorial Accessibility Checks
 * Plugin URI: https://www.inventivo.de/wordpress-agentur/wordpress-plugins
 * Description: Finds five common editorial accessibility issues in your posts and pages and lists them in one screen. Reports only, changes nothing.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: inventivo
 * Author URI: https://www.inventivo.de/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: inventivo-editorial-accessibility-checks
 * Domain Path: /languages
 *
 * @package Editorial_Accessibility_Checks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-editorial-accessibility-checks-rules.php';
require_once __DIR__ . '/includes/class-editorial-accessibility-checks-plugin.php';
Editorial_Accessibility_Checks_Plugin::boot();
register_activation_hook( __FILE__, array( 'Editorial_Accessibility_Checks_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Editorial_Accessibility_Checks_Plugin', 'deactivate' ) );
