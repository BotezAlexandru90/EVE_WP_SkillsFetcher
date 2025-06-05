<?php
/**
 * Plugin Name: EVE Online Skill Viewer (Main/Alts & Admin Tools)
 * Description: Allows users to authenticate a main EVE character and link alts. Provides admin tools for character management.
 * Version: 0.1.10
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eve-skill-plugin
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define Core Plugin Constants
define( 'EVE_SKILL_PLUGIN_VERSION', '0.1.10' );
define( 'EVE_SKILL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'EVE_SKILL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ESP_SSO_SESSION_KEY', 'esp_sso_pending_data' ); 
define( 'ESP_REDIRECT_MESSAGE_QUERY_ARG', 'eve_sso_message' );
define( 'ESP_SSO_CALLBACK_ACTION_NAME', 'esp_sso_callback_action');

if (!defined('EVE_SKILL_PLUGIN_USER_AGENT')) { 
    define('EVE_SKILL_PLUGIN_USER_AGENT', 'WordPress EVE Skill Plugin/' . EVE_SKILL_PLUGIN_VERSION . ' (Site: ' . get_site_url() . ')'); 
}

/**
 * The code that runs during plugin activation.
 */
function esp_activate_plugin_main() {
    require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-activator.php';
    ESP_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function esp_deactivate_plugin_main() {
    require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-deactivator.php';
    ESP_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'esp_activate_plugin_main' );
register_deactivation_hook( __FILE__, 'esp_deactivate_plugin_main' );

/**
 * Requiring the main plugin class that orchestrates everything.
 */
require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-main.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_esp_plugin() {
    $plugin = new ESP_Main();
    $plugin->run();
}
run_esp_plugin();