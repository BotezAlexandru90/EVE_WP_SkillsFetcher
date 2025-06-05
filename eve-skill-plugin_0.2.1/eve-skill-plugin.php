<?php
/**
 * Plugin Name: EVE Online Skill Viewer (Main/Alts & Admin Tools)
 * Description: Allows users to authenticate a main EVE character and link alts. Provides admin tools for character management and asset viewing.
 * Version: 0.2.1
 * Author: Surama Badasaz
 * Author URI: https://zkillboard.com/character/91036298/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eve-skill-plugin
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'EVE_SKILL_PLUGIN_VERSION', '0.2.0' );
define( 'EVE_SKILL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EVE_SKILL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ESP_SSO_SESSION_KEY', 'esp_sso_pending_data' ); 
define( 'ESP_REDIRECT_MESSAGE_QUERY_ARG', 'eve_sso_message' );
define( 'ESP_SSO_CALLBACK_ACTION_NAME', 'esp_sso_callback_action');
define( 'ESP_DEFAULT_SCOPES', 'esi-skills.read_skills.v1 publicData esi-assets.read_assets.v1' );

if (!defined('EVE_SKILL_PLUGIN_USER_AGENT')) { 
    define('EVE_SKILL_PLUGIN_USER_AGENT', 'WordPress EVE Skill Plugin/' . EVE_SKILL_PLUGIN_VERSION . ' (Site: ' . get_site_url() . ')'); 
}

// --- SESSION & BASIC SETUP ---
function esp_start_session_if_needed() { if ( ! session_id() && ! headers_sent() ) { session_start(); } }
add_action( 'init', 'esp_start_session_if_needed', 1 ); 

// --- MESSAGES & NOTICES ---
function esp_get_message_config() {
    return [
        // Success Notices
        'sso_success'        => ['class' => 'notice-success', 'text' => __('Main EVE character authenticated successfully! Skills and Assets are being fetched.', 'eve-skill-plugin')],
        'sso_alt_success'    => ['class' => 'notice-success', 'text' => __('Alt EVE character authenticated successfully! Skills and Assets are being fetched.', 'eve-skill-plugin')],
        'all_data_cleared'   => ['class' => 'notice-success', 'text' => __('All your EVE Online data (main and alts) has been cleared from this site.', 'eve-skill-plugin')],
        'alt_removed'        => ['class' => 'notice-success', 'text' => __('Alt character has been removed successfully by you.', 'eve-skill-plugin')],
        'admin_alt_removed'  => ['class' => 'notice-success', 'text' => __('Alt character has been removed by administrator.', 'eve-skill-plugin')],
        'admin_alt_promoted' => ['class' => 'notice-success', 'text' => __('Alt character has been promoted to main by administrator.', 'eve-skill-plugin')],
        'doctrine_added'     => ['class' => 'notice-success', 'text' => __('Doctrine ship requirements added successfully.', 'eve-skill-plugin')],
        'doctrine_updated'   => ['class' => 'notice-success', 'text' => __('Doctrine ship requirements updated successfully.', 'eve-skill-plugin')],
        'doctrine_deleted'   => ['class' => 'notice-success', 'text' => __('Doctrine ship requirements deleted successfully.', 'eve-skill-plugin')],

        // Warning Notices
        'sso_skills_failed'          => ['class' => 'notice-warning', 'text' => __('EVE authentication was successful, but skills could not be fetched.', 'eve-skill-plugin')],
        'sso_assets_failed'          => ['class' => 'notice-warning', 'text' => __('EVE authentication was successful, skills fetched, but assets could not be fetched.', 'eve-skill-plugin')],
        'sso_skills_assets_failed'   => ['class' => 'notice-warning', 'text' => __('EVE authentication was successful, but both skills and assets could not be fetched.', 'eve-skill-plugin')],
        'admin_alt_already_main'     => ['class' => 'notice-warning', 'text' => __('This character is already the main for this user.', 'eve-skill-plugin')],
        'sso_alt_is_main'            => ['class' => 'notice-warning', 'text' => __('This character is already set as your main. Cannot add as alt.', 'eve-skill-plugin')],
        'doctrine_no_valid_skills'   => ['class' => 'notice-warning', 'text' => __('No valid skills found in the provided list. Please ensure format is "Skill Name Level".', 'eve-skill-plugin')],

        // Error Notices
        'sso_no_config'                  => ['class' => 'notice-error', 'text' => __('EVE integration is not configured by the site administrator.', 'eve-skill-plugin')],
        'doctrine_missing_fields'        => ['class' => 'notice-error', 'text' => __('Ship name and skill requirements cannot be empty.', 'eve-skill-plugin')],
        'doctrine_not_found'             => ['class' => 'notice-error', 'text' => __('The specified doctrine ship was not found.', 'eve-skill-plugin')],
        'admin_op_failed_params'         => ['class' => 'notice-error', 'text' => __('Administrator operation failed due to missing parameters.', 'eve-skill-plugin')],
        'admin_assign_same_user'         => ['class' => 'notice-error', 'text' => __('Cannot assign a character to the same user it already belongs to.', 'eve-skill-plugin')],
        'admin_reassign_main_has_alts'   => ['class' => 'notice-error', 'text' => __('Cannot reassign this main character because it has alts. Please reassign its alts first.', 'eve-skill-plugin')],
        
        // ESI/SSO Error Notices (with placeholder)
        'sso_state_mismatch'     => ['class' => 'notice-error', 'text' => __('An EVE authentication error occurred. Please try again. (Error: %s)', 'eve-skill-plugin')],
        'sso_token_wp_error'     => ['class' => 'notice-error', 'text' => __('An EVE authentication error occurred. Please try again. (Error: %s)', 'eve-skill-plugin')],
        // ... add any other dynamic error messages here ...
    ];
}

function esp_display_sso_message( $message_key ) {
    $message_key = sanitize_key( $message_key );
    $messages = esp_get_message_config();

    if ( ! isset( $messages[$message_key] ) ) {
        return;
    }

    $message_config = $messages[$message_key];
    $text = $message_config['text'];
    $class = 'notice eve-sso-message is-dismissible ' . $message_config['class'];

    // Handle dynamic text replacement
    if (strpos($text, '%s') !== false) {
        $text = sprintf($text, esc_html($message_key));
    }

    // Handle special conditional logic
    if ($message_key === 'sso_success' && isset($_GET['new_user']) && $_GET['new_user'] === 'true') {
        $text .= ' ' . esc_html__('A WordPress account has been created for you and you are now logged in.', 'eve-skill-plugin');
    }

    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $text);
}

function esp_show_admin_page_messages() { 
    $current_screen = get_current_screen(); 
    if ( $current_screen && 
         (strpos($current_screen->id, 'eve_skill_plugin_settings') !== false || 
          strpos($current_screen->id, 'eve_skill_user_characters_page') !== false || 
          strpos($current_screen->id, 'eve_view_all_user_skills') !== false ||
          strpos($current_screen->id, 'eve_view_all_user_assets') !== false ) && // Added new assets page
         isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) 
       ) { 
        esp_display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); 
    } 
}
add_action( 'admin_notices', 'esp_show_admin_page_messages');

// --- ADMIN MENU & SETTINGS ---
function esp_add_admin_menu() {
    add_menu_page( __( 'EVE Online Data', 'eve-skill-plugin' ), __( 'EVE Data', 'eve-skill-plugin' ), 'edit_others_pages', 'eve_skill_plugin_settings', 'esp_render_settings_page', 'dashicons-id-alt');
    //add_submenu_page( 'eve_skill_plugin_settings', __( 'My Linked EVE Characters', 'eve-skill-plugin' ), __( 'My Linked Characters', 'eve-skill-plugin' ), 'read', 'eve_skill_user_characters_page', 'esp_render_user_characters_page');
    add_submenu_page( 'eve_skill_plugin_settings', __( 'View All User EVE Skills', 'eve-skill-plugin' ), __( 'View All User Skills', 'eve-skill-plugin' ), 'manage_options', 'eve_view_all_user_skills', 'esp_render_view_all_user_skills_page');
    add_submenu_page( 'eve_skill_plugin_settings', __( 'View All User EVE Assets', 'eve-skill-plugin' ), __( 'View Assets', 'eve-skill-plugin' ), 'manage_options', 'eve_view_all_user_assets', 'esp_render_view_all_user_assets_page');
	add_submenu_page(
    'eve_skill_plugin_settings',                          // Parent slug
    __( 'Doctrine Ship Requirements', 'eve-skill-plugin' ), // Page title
    __( 'Doctrine Ships', 'eve-skill-plugin' ),           // Menu title
    'manage_options',                                     // Capability
    'eve_skill_doctrine_ships_page',                      // Menu slug
    'esp_render_doctrine_ships_page'                      // Function to display the page
);
}
add_action( 'admin_menu', 'esp_add_admin_menu' );

function esp_register_settings() { 
    register_setting( 'esp_settings_group', 'esp_client_id' ); 
    register_setting( 'esp_settings_group', 'esp_client_secret' ); 
    register_setting( 'esp_settings_group', 'esp_scopes', ['default' => ESP_DEFAULT_SCOPES]); 
}
add_action( 'admin_init', 'esp_register_settings' );


function esp_get_character_skill_name_map( $character_skills_data ) {
    $skill_name_map = [];
    if ( ! is_array( $character_skills_data ) || empty( $character_skills_data ) ) {
        return $skill_name_map;
    }

    foreach ( $character_skills_data as $skill_entry ) {
        if ( isset( $skill_entry['skill_id'] ) && isset( $skill_entry['active_skill_level'] ) ) {
            $skill_name = esp_get_skill_name( (int) $skill_entry['skill_id'] );
            // Avoid using "Lookup Failed" or "Invalid Skill ID" as keys
            if ( $skill_name && strpos( $skill_name, 'Lookup Failed' ) === false && strpos( $skill_name, 'Invalid Skill ID' ) === false && strpos($skill_name, 'Unknown Skill') === false ) {
                $skill_name_map[ $skill_name ] = (int) $skill_entry['active_skill_level'];
            }
        }
    }
    return $skill_name_map;
}


function esp_get_character_compliant_doctrines( $user_id, $character_id ) {
    $compliant_ship_names = [];
    
    // Just one call to our new helper function!
    $character = esp_get_character_data( $user_id, $character_id );
    $character_skills_data = $character['skills_data'] ?? null;

    if ( ! $character_skills_data || ! is_array( $character_skills_data ) ) {
        return $compliant_ship_names;
    }

    // Cache the skill name map for this character to avoid repeated ESI lookups if checking multiple doctrines
    $transient_key = 'esp_char_skill_map_' . $user_id . '_' . $character_id;
    $character_skill_name_map = get_transient($transient_key);

    if (false === $character_skill_name_map) {
        $character_skill_name_map = esp_get_character_skill_name_map( $character_skills_data );
        set_transient($transient_key, $character_skill_name_map, HOUR_IN_SECONDS); // Cache for an hour
    }

    $all_doctrines = get_option( 'esp_doctrine_ships', [] );
    if ( empty( $all_doctrines ) ) {
        return $compliant_ship_names;
    }

    foreach ( $all_doctrines as $doctrine ) {
        if ( isset( $doctrine['parsed_skills'] ) && esp_check_character_doctrine_compliance( $character_skill_name_map, $doctrine['parsed_skills'] ) ) {
            $compliant_ship_names[] = $doctrine['ship_name'];
        }
    }
    return $compliant_ship_names;
}


function esp_check_character_doctrine_compliance( $character_skill_name_map, $doctrine_parsed_skills ) {
    if ( empty( $doctrine_parsed_skills ) ) {
        return false; // No skills to check against
    }
    if ( empty( $character_skill_name_map ) ) {
        return false; // Character has no skills (or map failed)
    }

    foreach ( $doctrine_parsed_skills as $required_skill ) {
        $req_skill_name = $required_skill['name'];
        $req_skill_level = $required_skill['level'];

        if ( ! isset( $character_skill_name_map[ $req_skill_name ] ) ) {
            return false; // Character doesn't have the skill
        }
        if ( $character_skill_name_map[ $req_skill_name ] < $req_skill_level ) {
            return false; // Character's skill level is too low
        }
    }
    return true; // All skills met
}


function esp_render_doctrine_ships_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'eve-skill-plugin' ) );
        return;
    }

    $doctrines = get_option( 'esp_doctrine_ships', [] );
    $edit_doctrine_id = isset( $_GET['edit_id'] ) ? sanitize_text_field( $_GET['edit_id'] ) : null;
    $current_doctrine_data = null;
    $is_editing = false;

    if ( $edit_doctrine_id && isset( $doctrines[ $edit_doctrine_id ] ) ) {
        $current_doctrine_data = $doctrines[ $edit_doctrine_id ];
        $is_editing = true;
    }
    ?>
    <div class="wrap">
        <h1><?php echo $is_editing ? esc_html__( 'Edit Doctrine Ship', 'eve-skill-plugin' ) : esc_html__( 'Add New Doctrine Ship', 'eve-skill-plugin' ); ?></h1>

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <input type="hidden" name="action" value="esp_save_doctrine_ship">
            <?php if ( $is_editing && $edit_doctrine_id ): ?>
                <input type="hidden" name="doctrine_id" value="<?php echo esc_attr( $edit_doctrine_id ); ?>">
            <?php endif; ?>
            <?php wp_nonce_field( 'esp_save_doctrine_action', 'esp_save_doctrine_nonce' ); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="esp_ship_name"><?php esc_html_e( 'Ship Name', 'eve-skill-plugin' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="esp_ship_name" name="esp_ship_name" value="<?php echo $is_editing ? esc_attr( $current_doctrine_data['ship_name'] ) : ''; ?>" size="40" required />
                        <p class="description"><?php esc_html_e( 'E.g., "Apocalypse Navy Issue - Standard"', 'eve-skill-plugin' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="esp_skills_text"><?php esc_html_e( 'Skill Requirements', 'eve-skill-plugin' ); ?></label>
                    </th>
                    <td>
                        <textarea id="esp_skills_text" name="esp_skills_text" rows="10" cols="50" required><?php echo $is_editing ? esc_textarea( $current_doctrine_data['skills_text'] ) : ''; ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Paste skill list, one skill per line, e.g., "Spaceship Command 3". Ensure skill names match ESI exactly.', 'eve-skill-plugin' ); ?><br>
                            <?php esc_html_e( 'Example:', 'eve-skill-plugin' ); ?><br>
                            <code>
                            Afterburner 3<br>
                            Astrometrics 4<br>
                            Caldari Frigate 5
                            </code>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( $is_editing ? __( 'Update Doctrine Ship', 'eve-skill-plugin' ) : __( 'Add Doctrine Ship', 'eve-skill-plugin' ) ); ?>
        </form>

        <hr/>
        <h2><?php esc_html_e( 'Existing Doctrine Ships', 'eve-skill-plugin' ); ?></h2>
        <?php if ( ! empty( $doctrines ) ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Ship Name', 'eve-skill-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Skills Count', 'eve-skill-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'eve-skill-plugin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $doctrines as $doc_id => $doctrine ) : ?>
                        <tr>
                            <td><?php echo esc_html( $doctrine['ship_name'] ); ?></td>
                            <td><?php echo count( $doctrine['parsed_skills'] ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( add_query_arg( ['page' => 'eve_skill_doctrine_ships_page', 'edit_id' => $doc_id], admin_url('admin.php') ) ); ?>"><?php esc_html_e( 'Edit', 'eve-skill-plugin' ); ?></a>
                                |
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=esp_delete_doctrine_ship&doctrine_id=' . urlencode($doc_id)), 'esp_delete_doctrine_action_' . $doc_id, 'esp_delete_doctrine_nonce' ) ); ?>"
                                   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this doctrine?', 'eve-skill-plugin' ); ?>');"
                                   style="color:red;">
                                    <?php esc_html_e( 'Delete', 'eve-skill-plugin' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No doctrine ships defined yet.', 'eve-skill-plugin' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
function esp_handle_save_doctrine_ship() {
    if ( ! isset( $_POST['esp_save_doctrine_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['esp_save_doctrine_nonce']), 'esp_save_doctrine_action' ) ) {
        wp_die( 'Nonce verification failed!' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.' );
    }

    $ship_name = isset( $_POST['esp_ship_name'] ) ? sanitize_text_field( trim( $_POST['esp_ship_name'] ) ) : '';
    $skills_text = isset( $_POST['esp_skills_text'] ) ? sanitize_textarea_field( trim( $_POST['esp_skills_text'] ) ) : '';
    $doctrine_id_to_edit = isset( $_POST['doctrine_id'] ) ? sanitize_text_field( $_POST['doctrine_id'] ) : null;

    if ( empty( $ship_name ) || empty( $skills_text ) ) {
        wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'doctrine_missing_fields', admin_url( 'admin.php?page=eve_skill_doctrine_ships_page' ) ) );
        exit;
    }

    $parsed_skills = [];
    $skill_lines = explode( "\n", $skills_text );
    foreach ( $skill_lines as $line ) {
        $line = trim( $line );
        if ( empty( $line ) ) continue;

        // Regex to capture skill name (can include spaces) and the level (number at the end)
        if ( preg_match( '/^(.*)\s+(\d+)$/', $line, $matches ) ) {
            $skill_name_candidate = trim( $matches[1] );
            $skill_level = intval( $matches[2] );
            if ( ! empty( $skill_name_candidate ) && $skill_level >= 1 && $skill_level <= 5 ) {
                $parsed_skills[] = [
                    'name'  => $skill_name_candidate,
                    'level' => $skill_level,
                ];
            }
        }
    }

    if ( empty( $parsed_skills ) ) {
        wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'doctrine_no_valid_skills', admin_url( 'admin.php?page=eve_skill_doctrine_ships_page' ) ) );
        exit;
    }

    $doctrines = get_option( 'esp_doctrine_ships', [] );
    $message_key = 'doctrine_added';

    // Use ship_name as ID if not editing, ensure it's unique or handle collision if needed
    // For simplicity, if editing, we use the passed doctrine_id_to_edit. If new, generate one.
    $current_doc_id = $doctrine_id_to_edit;
    if ( ! $doctrine_id_to_edit ) {
        // Create a somewhat unique ID from the ship name, can be improved.
        $current_doc_id = sanitize_title_with_dashes( $ship_name . '-' . time() );
        // A more robust unique ID might be needed if ship names aren't unique.
        // Or just use array_push and let PHP handle numeric keys.
        // For this example, let's allow editing by the generated ID.
    } else {
        $message_key = 'doctrine_updated';
    }


    $doctrines[ $current_doc_id ] = [
        'ship_name'     => $ship_name,
        'skills_text'   => $skills_text, // Store raw text for easier editing
        'parsed_skills' => $parsed_skills,
    ];

    update_option( 'esp_doctrine_ships', $doctrines );

    wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, $message_key, admin_url( 'admin.php?page=eve_skill_doctrine_ships_page' ) ) );
    exit;
}
add_action( 'admin_post_esp_save_doctrine_ship', 'esp_handle_save_doctrine_ship' );

function esp_handle_delete_doctrine_ship() {
    $doctrine_id = isset( $_GET['doctrine_id'] ) ? sanitize_text_field( urldecode( $_GET['doctrine_id'] ) ) : null; // urldecode might be needed if ID has special chars from sanitize_title

    if ( ! $doctrine_id || ! isset( $_GET['esp_delete_doctrine_nonce'] ) || ! wp_verify_nonce( sanitize_key($_GET['esp_delete_doctrine_nonce']), 'esp_delete_doctrine_action_' . $doctrine_id ) ) {
        wp_die( 'Invalid request or security check failed.' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.' );
    }

    $doctrines = get_option( 'esp_doctrine_ships', [] );
    if ( isset( $doctrines[ $doctrine_id ] ) ) {
        unset( $doctrines[ $doctrine_id ] );
        update_option( 'esp_doctrine_ships', $doctrines );
        $message_key = 'doctrine_deleted';
    } else {
        $message_key = 'doctrine_not_found'; // Add this to esp_display_sso_message
    }

    wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, $message_key, admin_url( 'admin.php?page=eve_skill_doctrine_ships_page' ) ) );
    exit;
}
add_action( 'admin_post_esp_delete_doctrine_ship', 'esp_handle_delete_doctrine_ship' );




function esp_render_settings_page() { 
    if ( ! current_user_can( 'edit_others_pages' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'eve-skill-plugin' ) ); return; } ?>
    <div class="wrap"> <h1><?php esc_html_e( 'EVE Online Data Viewer Settings', 'eve-skill-plugin' ); ?></h1> <form method="post" action="options.php"> <?php settings_fields( 'esp_settings_group' ); do_settings_sections( 'esp_settings_group' ); ?> <table class="form-table"> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Client ID', 'eve-skill-plugin' ); ?></th> <td><input type="text" name="esp_client_id" value="<?php echo esc_attr( get_option( 'esp_client_id' ) ); ?>" size="60" /></td> </tr> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Secret Key', 'eve-skill-plugin' ); ?></th> <td><input type="password" name="esp_client_secret" value="<?php echo esc_attr( get_option( 'esp_client_secret' ) ); ?>" size="60" /></td> </tr> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Scopes', 'eve-skill-plugin' ); ?></th> <td> <input type="text" name="esp_scopes" value="<?php echo esc_attr( get_option( 'esp_scopes', ESP_DEFAULT_SCOPES ) ); ?>" size="60" /> <p class="description"><?php printf(esc_html__( 'Space separated. Default: %s. Required for assets: esi-assets.read_assets.v1', 'eve-skill-plugin' ), ESP_DEFAULT_SCOPES); ?></p> </td> </tr> </table> <?php submit_button(); ?> </form> <hr/> <h2><?php esc_html_e( 'Callback URL for EVE Application', 'eve-skill-plugin' ); ?></h2> <p><?php esc_html_e( 'Use this URL as the "Callback URL" or "Redirect URI" in your EVE Online application settings:', 'eve-skill-plugin' ); ?></p> <code><?php echo esc_url( admin_url( 'admin-post.php?action=' . ESP_SSO_CALLBACK_ACTION_NAME ) ); ?></code> <hr/> <h2><?php esc_html_e( 'Shortcode for Login Button', 'eve-skill-plugin' ); ?></h2> <p><?php esc_html_e( 'To place an EVE Online login button on any page or post, use the following shortcode:', 'eve-skill-plugin' ); ?></p> <code>[eve_sso_login_button]</code> <p><?php esc_html_e( 'You can customize the button text like this:', 'eve-skill-plugin'); ?> <code>[eve_sso_login_button text="Link Your EVE Character"]</code></p> </div> <?php
}

function esp_render_user_characters_page() {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) { echo "<p>" . esc_html__("Please log in to view this page.", "eve-skill-plugin") . "</p>"; return; }
    $main_char_id = get_user_meta($current_user_id, 'esp_main_eve_character_id', true);
    $client_id = get_option('esp_client_id');

    // Get all defined doctrine ship names (once for the page)
    $all_doctrine_objects = get_option( 'esp_doctrine_ships', [] );
    $all_doctrine_names = [];
    if (is_array($all_doctrine_objects) && !empty($all_doctrine_objects)) {
        foreach ($all_doctrine_objects as $doctrine_item) {
            if (isset($doctrine_item['ship_name'])) {
                $all_doctrine_names[] = $doctrine_item['ship_name'];
            }
        }
        sort($all_doctrine_names); // Optional: for consistent display order
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'My Linked EVE Characters', 'eve-skill-plugin' ); ?></h1>
        <?php if ( ! $client_id ) : ?>
            <p style="color:red;"><?php esc_html_e( 'EVE Application Client ID is not configured by the administrator. Character linking is disabled.', 'eve-skill-plugin' ); ?></p>
        <?php endif; ?>
        <?php if ( $main_char_id ) :
            $main_char_name = get_user_meta($current_user_id, 'esp_main_eve_character_name', true);
        ?>
            <h3><?php esc_html_e( 'Main Character', 'eve-skill-plugin' ); ?></h3>
            <p>
                <?php printf( esc_html__( '%s (ID: %s)', 'eve-skill-plugin' ), esc_html( $main_char_name ), esc_html( $main_char_id ) ); ?>
                 - <a href="<?php echo esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $current_user_id, 'view_char_id' => $main_char_id], admin_url('admin.php'))); ?>"><?php esc_html_e('View Skills', 'eve-skill-plugin'); ?></a>
                <?php
                if (!empty($all_doctrine_names)) {
                    $main_compliant_doctrines = esp_get_character_compliant_doctrines( $current_user_id, $main_char_id, 'main' );
                    $main_non_compliant_doctrines = array_diff( $all_doctrine_names, $main_compliant_doctrines );

                    if ( ! empty( $main_compliant_doctrines ) ) {
                        echo '<br/><small><strong style="color: #6aa84f;">' . esc_html__( 'Can fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $main_compliant_doctrines ) ) . '</small>';
                    }
                    if ( ! empty( $main_non_compliant_doctrines ) ) {
                        echo '<br/><small><strong style="color: #dc3232;">' . esc_html__( 'Cannot fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $main_non_compliant_doctrines ) ) . '</small>';
                    }
                } else if ($client_id) { // Only show "no doctrines defined" if client ID is set, meaning feature could work.
                    echo '<br/><small>' . esc_html__('No doctrines defined by admin yet.', 'eve-skill-plugin') . '</small>';
                }
                ?>
            </p>
            <?php $current_admin_page_url = admin_url( 'admin.php?page=eve_skill_user_characters_page' ); ?>
            <?php if ($client_id): ?>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block; margin-right: 10px;">
                <input type="hidden" name="action" value="esp_initiate_sso">
                <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?>
                <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>">
                <input type="hidden" name="esp_auth_type" value="main">
                <?php submit_button( __( 'Re-Auth/Switch Main', 'eve-skill-plugin' ), 'secondary small', 'submit_main_auth', false ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block;">
                <input type="hidden" name="action" value="esp_initiate_sso">
                <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?>
                <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>">
                <input type="hidden" name="esp_auth_type" value="alt">
                <?php submit_button( __( 'Authenticate Alt Character', 'eve-skill-plugin' ), 'primary small', 'submit_alt_auth', false ); ?>
            </form>
            <?php endif; ?>
            <h3><?php esc_html_e( 'Alt Characters', 'eve-skill-plugin' ); ?></h3>
            <?php
            $alt_characters = get_user_meta($current_user_id, 'esp_alt_characters', true);
            if (is_array($alt_characters) && !empty($alt_characters)) {
                echo '<ul>';
                foreach ($alt_characters as $alt_char) {
                    if (!is_array($alt_char) || !isset($alt_char['id']) || !isset($alt_char['name'])) continue;
                    echo '<li>';
                    printf(esc_html__('%s (ID: %s)', 'eve-skill-plugin'), esc_html($alt_char['name']), esc_html($alt_char['id']));
                    echo ' - <a href="'. esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $current_user_id, 'view_char_id' => $alt_char['id']], admin_url('admin.php'))) .'">'. esc_html__('View Skills', 'eve-skill-plugin') .'</a>';

                    if (!empty($all_doctrine_names)) {
                        $alt_compliant_doctrines = esp_get_character_compliant_doctrines( $current_user_id, $alt_char['id'], 'alt' );
                        $alt_non_compliant_doctrines = array_diff( $all_doctrine_names, $alt_compliant_doctrines );

                        if ( ! empty( $alt_compliant_doctrines ) ) {
                            echo '<br/><small><strong style="color: #6aa84f;">' . esc_html__( 'Can fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $alt_compliant_doctrines ) ) . '</small>';
                        }
                        if ( ! empty( $alt_non_compliant_doctrines ) ) {
                            echo '<br/><small><strong style="color: #dc3232;">' . esc_html__( 'Cannot fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $alt_non_compliant_doctrines ) ) . '</small>';
                        }
                    } // No "else" for individual alts for "no doctrines defined" as it's handled once.

                    echo ' <form method="post" action="'. esc_url( admin_url('admin-post.php') ) .'" style="display:inline-block; margin-left:10px;">';
                    echo '<input type="hidden" name="action" value="esp_remove_alt_character">';
                    echo '<input type="hidden" name="esp_alt_char_id_to_remove" value="'. esc_attr($alt_char['id']) .'">';
                    echo '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url($current_admin_page_url) . '">';
                    wp_nonce_field('esp_remove_alt_action_' . $alt_char['id'], 'esp_remove_alt_nonce');
                    submit_button( __( 'Remove Alt', 'eve-skill-plugin' ), 'delete small', 'submit_remove_alt_' . esc_attr($alt_char['id']), false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove this alt character?', 'eve-skill-plugin')).'");'] );
                    echo '</form>';
                    echo '</li>';
                }
                echo '</ul>';
            } else { echo '<p>' . esc_html__('No alt characters linked yet.', 'eve-skill-plugin') . '</p>'; }
            ?>
            <hr style="margin: 20px 0;">
             <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="esp_clear_all_eve_data_for_user">
                <?php wp_nonce_field( 'esp_clear_all_eve_data_action', 'esp_clear_all_eve_data_nonce' ); ?>
                <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>">
                <?php submit_button( __( 'Clear All My EVE Data (Main & Alts)', 'eve-skill-plugin' ), 'delete', 'submit_clear_all_data', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove ALL EVE data, including main and all alts?', 'eve-skill-plugin')).'");'] ); ?>
            </form>
        <?php else : ?>
            <p><?php esc_html_e( 'You have not linked your main EVE Online character yet.', 'eve-skill-plugin' ); ?></p>
            <?php if ($client_id): ?>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="esp_initiate_sso">
                <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?>
                <?php $current_admin_page_url = admin_url( 'admin.php?page=eve_skill_user_characters_page' );?>
                <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>">
                <input type="hidden" name="esp_auth_type" value="main">
                <?php submit_button( __( 'Link Your Main EVE Character', 'eve-skill-plugin' ), 'primary' ); ?>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

// --- CHARACTER DATA HELPERS (USER META) ---
function esp_get_alt_character_data_item($user_id, $alt_char_id, $item_key) {
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
    if (is_array($alt_characters)) { 
        foreach ($alt_characters as $alt) { 
            if (isset($alt['id']) && $alt['id'] == $alt_char_id) { 
                return isset($alt[$item_key]) ? $alt[$item_key] : null; 
            } 
        } 
    } 
    return null;
}

function esp_update_alt_character_data_item($user_id, $alt_char_id, $item_key, $item_value, $char_name = null, $owner_hash = null) {
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); 
    if (!is_array($alt_characters)) { $alt_characters = []; }
    $found_alt_index = -1;
    foreach ($alt_characters as $index => $alt) { 
        if (isset($alt['id']) && $alt['id'] == $alt_char_id) { 
            $found_alt_index = $index; 
            break; 
        } 
    }
    if ($found_alt_index !== -1) { 
        $alt_characters[$found_alt_index][$item_key] = $item_value;
        if ($char_name && empty($alt_characters[$found_alt_index]['name'])) $alt_characters[$found_alt_index]['name'] = $char_name;
        if ($owner_hash && empty($alt_characters[$found_alt_index]['owner_hash'])) $alt_characters[$found_alt_index]['owner_hash'] = $owner_hash;
    } else { 
        $new_alt = ['id' => $alt_char_id]; 
        if ($char_name) $new_alt['name'] = $char_name; 
        if ($owner_hash) $new_alt['owner_hash'] = $owner_hash;
        $new_alt[$item_key] = $item_value; 
        $alt_characters[] = $new_alt;
    } 
    update_user_meta($user_id, 'esp_alt_characters', $alt_characters);
}
/**
 * Retrieves a consolidated data array for a specific character, regardless of type (main or alt).
 *
 * @param int $user_id The WordPress user ID.
 * @param int $character_id The EVE character ID.
 * @return array|null A structured array of the character's data, or null if not found.
 */
function esp_get_character_data($user_id, $character_id) {
    $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);

    if ($character_id == $main_char_id) {
        // It's the main character. We need to build the data array from individual meta fields.
        return [
            'id'                     => (int) $main_char_id,
            'name'                   => get_user_meta($user_id, 'esp_main_eve_character_name', true),
            'owner_hash'             => get_user_meta($user_id, 'esp_main_owner_hash', true),
            'access_token'           => get_user_meta($user_id, 'esp_main_access_token', true),
            'refresh_token'          => get_user_meta($user_id, 'esp_main_refresh_token', true),
            'token_expires'          => get_user_meta($user_id, 'esp_main_token_expires', true),
            'skills_data'            => get_user_meta($user_id, 'esp_main_skills_data', true),
            'total_sp'               => get_user_meta($user_id, 'esp_main_total_sp', true),
            'skills_last_updated'    => get_user_meta($user_id, 'esp_main_skills_last_updated', true),
            'assets_data'            => get_user_meta($user_id, 'esp_main_assets_data', true),
            'assets_last_updated'    => get_user_meta($user_id, 'esp_main_assets_last_updated', true),
            'type'                   => 'main', // Add a type for convenience
        ];
    }

    // If not main, it must be an alt. Let's find it.
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
    if (is_array($alt_characters)) {
        foreach ($alt_characters as $alt) {
            if (isset($alt['id']) && $alt['id'] == $character_id) {
                $alt['type'] = 'alt'; // Add a type for convenience
                return $alt; // The alt array is already in the correct format
            }
        }
    }

    // If we reach here, the character was not found for this user.
    return null;
}

// --- CHARACTER ACTION HANDLERS (USER-INITIATED & ADMIN) ---
function esp_handle_remove_alt_character_base($user_id_to_affect, $alt_char_id_to_remove) {
    $alt_characters = get_user_meta($user_id_to_affect, 'esp_alt_characters', true);
    $removed = false;
    if (is_array($alt_characters)) {
        $updated_alts = [];
        foreach ($alt_characters as $alt_char) {
            if (isset($alt_char['id']) && $alt_char['id'] == $alt_char_id_to_remove) { 
                $removed = true; 
                continue; 
            } 
            $updated_alts[] = $alt_char;
        }
        if ($removed) {
            if (empty($updated_alts)) { delete_user_meta($user_id_to_affect, 'esp_alt_characters'); } 
            else { update_user_meta($user_id_to_affect, 'esp_alt_characters', $updated_alts); }
        }
    }
    return $removed;
}

function esp_handle_remove_alt_character() {
    if (!is_user_logged_in()) { wp_die('Not logged in.'); }
    $user_id = get_current_user_id();
    $alt_char_id_to_remove = isset($_POST['esp_alt_char_id_to_remove']) ? intval($_POST['esp_alt_char_id_to_remove']) : 0;
    if (!$alt_char_id_to_remove || !check_admin_referer('esp_remove_alt_action_' . $alt_char_id_to_remove, 'esp_remove_alt_nonce')) { 
        wp_die('Invalid request or security check failed.'); 
    }
    $removed = esp_handle_remove_alt_character_base($user_id, $alt_char_id_to_remove);
    $message = $removed ? 'alt_removed' : 'admin_alt_not_found'; // Using admin_alt_not_found as a generic "not found"
    $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : admin_url('admin.php?page=eve_skill_user_characters_page');
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $message, $redirect_back_url)); 
    exit;
}
add_action('admin_post_esp_remove_alt_character', 'esp_handle_remove_alt_character');

function esp_handle_admin_remove_user_alt_character() {
    if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_remove_alt_action', 'esp_admin_remove_alt_nonce')) { 
        wp_die('Security check failed or insufficient permissions.'); 
    }
    $user_id_to_affect = isset($_POST['user_id_to_affect']) ? intval($_POST['user_id_to_affect']) : 0;
    $alt_char_id_to_remove = isset($_POST['alt_char_id_to_remove']) ? intval($_POST['alt_char_id_to_remove']) : 0;
    // Default redirect, can be overridden if a more specific one is needed from the form
    $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id_to_affect); 
    if (isset($_POST['esp_redirect_back_url']) && !empty($_POST['esp_redirect_back_url'])) {
         $redirect_back_url = esc_url_raw($_POST['esp_redirect_back_url']); // Allow form to specify for flexibility
    }

    if (!$user_id_to_affect || !$alt_char_id_to_remove) { 
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); 
        exit; 
    }
    $removed = esp_handle_remove_alt_character_base($user_id_to_affect, $alt_char_id_to_remove);
    $message = $removed ? 'admin_alt_removed' : 'admin_alt_not_found';
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $message, $redirect_back_url)); 
    exit;
}
add_action('admin_post_esp_admin_remove_user_alt_character', 'esp_handle_admin_remove_user_alt_character');

function esp_handle_admin_promote_alt_to_main() {
    if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_promote_alt_action', 'esp_admin_promote_alt_nonce')) {
        wp_die(esc_html__('Security check failed or insufficient permissions.', 'eve-skill-plugin'));
    }
    $user_id_to_affect = isset($_POST['user_id_to_affect']) ? intval($_POST['user_id_to_affect']) : 0;
    $alt_char_id_to_promote = isset($_POST['alt_char_id_to_promote']) ? intval($_POST['alt_char_id_to_promote']) : 0;
    $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id_to_affect); 

    if (!$user_id_to_affect || !$alt_char_id_to_promote) {
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); 
        exit;
    }
    $current_main_id = get_user_meta($user_id_to_affect, 'esp_main_eve_character_id', true);
    if ($current_main_id == $alt_char_id_to_promote) { 
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_alt_already_main', $redirect_back_url)); 
        exit; 
    }
    $all_alts = get_user_meta($user_id_to_affect, 'esp_alt_characters', true);
    if (!is_array($all_alts)) { $all_alts = []; }
    $promoted_alt_data = null; $remaining_alts = [];
    foreach ($all_alts as $alt) {
        if (isset($alt['id']) && $alt['id'] == $alt_char_id_to_promote) { $promoted_alt_data = $alt;
        } else { $remaining_alts[] = $alt; }
    }
    if (!$promoted_alt_data) { 
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_alt_not_found_for_promote', $redirect_back_url)); 
        exit; 
    }
    if ($current_main_id) {
        $old_main_data_as_alt = [
            'id'            => (int)$current_main_id,
            'name'          => (string)get_user_meta($user_id_to_affect, 'esp_main_eve_character_name', true),
            'access_token'  => (string)get_user_meta($user_id_to_affect, 'esp_main_access_token', true),
            'refresh_token' => (string)get_user_meta($user_id_to_affect, 'esp_main_refresh_token', true),
            'token_expires' => (int)get_user_meta($user_id_to_affect, 'esp_main_token_expires', true),
            'owner_hash'    => (string)get_user_meta($user_id_to_affect, 'esp_main_owner_hash', true),
            'skills_data'   => get_user_meta($user_id_to_affect, 'esp_main_skills_data', true),
            'total_sp'      => (float)get_user_meta($user_id_to_affect, 'esp_main_total_sp', true),
            'skills_last_updated' => (int)get_user_meta($user_id_to_affect, 'esp_main_skills_last_updated', true),
            'assets_data'   => get_user_meta($user_id_to_affect, 'esp_main_assets_data', true),
            'assets_last_updated' => (int)get_user_meta($user_id_to_affect, 'esp_main_assets_last_updated', true),
        ];
        if (!is_array($old_main_data_as_alt['skills_data'])) { $old_main_data_as_alt['skills_data'] = []; }
        if (!is_array($old_main_data_as_alt['assets_data'])) { $old_main_data_as_alt['assets_data'] = []; } 
        $remaining_alts[] = $old_main_data_as_alt;
    }
    update_user_meta($user_id_to_affect, 'esp_main_eve_character_id', $promoted_alt_data['id']);
    update_user_meta($user_id_to_affect, 'esp_main_eve_character_name', $promoted_alt_data['name'] ?? ''); 
    update_user_meta($user_id_to_affect, 'esp_main_access_token', $promoted_alt_data['access_token'] ?? '');
    update_user_meta($user_id_to_affect, 'esp_main_refresh_token', $promoted_alt_data['refresh_token'] ?? '');
    update_user_meta($user_id_to_affect, 'esp_main_token_expires', $promoted_alt_data['token_expires'] ?? 0);
    update_user_meta($user_id_to_affect, 'esp_main_owner_hash', $promoted_alt_data['owner_hash'] ?? '');
    update_user_meta($user_id_to_affect, 'esp_main_skills_data', $promoted_alt_data['skills_data'] ?? []);
    update_user_meta($user_id_to_affect, 'esp_main_total_sp', $promoted_alt_data['total_sp'] ?? 0);
    update_user_meta($user_id_to_affect, 'esp_main_skills_last_updated', $promoted_alt_data['skills_last_updated'] ?? 0);
    update_user_meta($user_id_to_affect, 'esp_main_assets_data', $promoted_alt_data['assets_data'] ?? []);
    update_user_meta($user_id_to_affect, 'esp_main_assets_last_updated', $promoted_alt_data['assets_last_updated'] ?? 0);

    // Clear individual meta for old main if it existed, as its data is now in the promoted main or as an alt
    $main_meta_keys_to_clear = [
        'esp_main_eve_character_name', 'esp_main_access_token', 'esp_main_refresh_token',
        'esp_main_token_expires', 'esp_main_owner_hash', 'esp_main_skills_data',
        'esp_main_total_sp', 'esp_main_skills_last_updated', 'esp_main_assets_data', 'esp_main_assets_last_updated'
    ];
    // We already updated these for the new main. This step is redundant if $current_main_id was the one promoted.
    // This was to clear the *previous* main's specific keys if it becomes an alt.
    // However, the logic already copies the old main data to $remaining_alts,
    // and then updates the 'esp_main_*' keys with the promoted alt's data.
    // So, deleting these specific keys after they've been set for the NEW main would be wrong.
    // The old main's data is preserved in the $old_main_data_as_alt array within $remaining_alts.
    // The original 'esp_main_eve_character_id' meta is overwritten by the new main's ID.
    
    if (empty($remaining_alts)) { 
        delete_user_meta($user_id_to_affect, 'esp_alt_characters');
    } else { 
        update_user_meta($user_id_to_affect, 'esp_alt_characters', $remaining_alts); 
    }
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_alt_promoted', $redirect_back_url)); 
    exit;
}
add_action('admin_post_esp_admin_promote_alt_to_main', 'esp_handle_admin_promote_alt_to_main');

function esp_handle_admin_reassign_character() {
    if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce')) {
        wp_die(esc_html__('Security check failed or insufficient permissions.', 'eve-skill-plugin'));
    }
    $original_wp_user_id = isset($_POST['original_wp_user_id']) ? intval($_POST['original_wp_user_id']) : 0;
    $character_id_to_move = isset($_POST['character_id_to_move']) ? intval($_POST['character_id_to_move']) : 0;
    $character_type_to_move = isset($_POST['character_type_to_move']) ? sanitize_key($_POST['character_type_to_move']) : '';
    $new_main_wp_user_id = isset($_POST['new_main_wp_user_id']) ? intval($_POST['new_main_wp_user_id']) : 0;
    $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $original_wp_user_id);

    if (!$original_wp_user_id || !$character_id_to_move || !$new_main_wp_user_id || !in_array($character_type_to_move, ['main', 'alt'])) {
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_failed_params', $redirect_back_url)); exit;
    }
    if ($original_wp_user_id === $new_main_wp_user_id) {
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_same_user', $redirect_back_url)); exit;
    }
    $new_main_user_info = get_userdata($new_main_wp_user_id);
    $new_main_user_main_char_id = $new_main_user_info ? get_user_meta($new_main_wp_user_id, 'esp_main_eve_character_id', true) : null;
    if (!$new_main_user_info || !$new_main_user_main_char_id) {
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_new_main_invalid', $redirect_back_url)); exit;
    }
    if ($character_id_to_move == $new_main_user_main_char_id) { 
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_is_new_main', $redirect_back_url)); exit;
    }
    $new_main_user_alts = get_user_meta($new_main_wp_user_id, 'esp_alt_characters', true);
    if (!is_array($new_main_user_alts)) $new_main_user_alts = [];
    foreach ($new_main_user_alts as $existing_alt) {
        if (isset($existing_alt['id']) && $existing_alt['id'] == $character_id_to_move) {
            wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_already_exists_new', $redirect_back_url)); exit;
        }
    }
    $moved_char_data_obj = null;
    if ($character_type_to_move === 'main') {
        $original_main_id = get_user_meta($original_wp_user_id, 'esp_main_eve_character_id', true);
        if ($original_main_id != $character_id_to_move) {
            wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_reassign_main_not_found', $redirect_back_url)); exit;
        }
        $original_user_alts = get_user_meta($original_wp_user_id, 'esp_alt_characters', true);
        if (!empty($original_user_alts) && is_array($original_user_alts)) {
             wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_reassign_main_has_alts', $redirect_back_url)); exit;
        }
        $moved_char_data_obj = [
            'id'            => (int)$original_main_id,
            'name'          => (string)get_user_meta($original_wp_user_id, 'esp_main_eve_character_name', true),
            'access_token'  => (string)get_user_meta($original_wp_user_id, 'esp_main_access_token', true),
            'refresh_token' => (string)get_user_meta($original_wp_user_id, 'esp_main_refresh_token', true),
            'token_expires' => (int)get_user_meta($original_wp_user_id, 'esp_main_token_expires', true),
            'owner_hash'    => (string)get_user_meta($original_wp_user_id, 'esp_main_owner_hash', true),
            'skills_data'   => get_user_meta($original_wp_user_id, 'esp_main_skills_data', true),
            'total_sp'      => (float)get_user_meta($original_wp_user_id, 'esp_main_total_sp', true),
            'skills_last_updated' => (int)get_user_meta($original_wp_user_id, 'esp_main_skills_last_updated', true),
            'assets_data'   => get_user_meta($original_wp_user_id, 'esp_main_assets_data', true),
            'assets_last_updated' => (int)get_user_meta($original_wp_user_id, 'esp_main_assets_last_updated', true),
        ];
        if (!is_array($moved_char_data_obj['skills_data'])) $moved_char_data_obj['skills_data'] = [];
        if (!is_array($moved_char_data_obj['assets_data'])) $moved_char_data_obj['assets_data'] = [];

        // Clear all main character specific meta from original user
        $main_meta_keys_to_delete = [
            'esp_main_eve_character_id', 'esp_main_eve_character_name', 'esp_main_access_token',
            'esp_main_refresh_token', 'esp_main_token_expires', 'esp_main_owner_hash',
            'esp_main_skills_data', 'esp_main_total_sp', 'esp_main_skills_last_updated',
            'esp_main_assets_data', 'esp_main_assets_last_updated'
        ];
        foreach ($main_meta_keys_to_delete as $key) {
            delete_user_meta($original_wp_user_id, $key);
        }
    } elseif ($character_type_to_move === 'alt') {
        $original_user_alts = get_user_meta($original_wp_user_id, 'esp_alt_characters', true);
        if (!is_array($original_user_alts)) $original_user_alts = [];
        $remaining_original_user_alts = []; $found_in_original = false;
        foreach ($original_user_alts as $alt) {
            if (isset($alt['id']) && $alt['id'] == $character_id_to_move) {
                $moved_char_data_obj = $alt; $found_in_original = true;
            } else { $remaining_original_user_alts[] = $alt; }
        }
        if (!$found_in_original || !$moved_char_data_obj) {
            wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_not_found_orig', $redirect_back_url)); exit;
        }
        if (empty($remaining_original_user_alts)) { delete_user_meta($original_wp_user_id, 'esp_alt_characters'); } 
        else { update_user_meta($original_wp_user_id, 'esp_alt_characters', $remaining_original_user_alts); }
    }
    if (!$moved_char_data_obj) { 
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); exit;
    }
    if (!isset($moved_char_data_obj['assets_data']) || !is_array($moved_char_data_obj['assets_data'])) {
        $moved_char_data_obj['assets_data'] = [];
    }
    if (!isset($moved_char_data_obj['assets_last_updated'])) {
        $moved_char_data_obj['assets_last_updated'] = 0;
    }
    $new_main_user_alts[] = $moved_char_data_obj;
    update_user_meta($new_main_wp_user_id, 'esp_alt_characters', $new_main_user_alts);
    $success_message = ($character_type_to_move === 'main') ? 'admin_main_reassigned_as_alt' : 'admin_alt_assigned_new_main';
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $success_message, $redirect_back_url));
    exit;
}
add_action('admin_post_esp_admin_reassign_character', 'esp_handle_admin_reassign_character');

// --- ADMIN PAGE RENDERERS ---
function esp_render_view_all_user_skills_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions.', 'eve-skill-plugin' ) ); }
    ?>
    <div class="wrap esp-admin-view">
        <h1><?php esc_html_e( 'View User EVE Skills', 'eve-skill-plugin' ); ?></h1>
        <style>
            .esp-admin-view .char-tree { list-style-type: none; padding-left: 0; }
            .esp-admin-view .char-tree ul { list-style-type: none; padding-left: 20px; margin-left: 10px; border-left: 1px dashed #ccc; }
            .esp-admin-view .char-item { padding: 5px 0; }
            .esp-admin-view .char-item strong { font-size: 1.1em; }
            .esp-admin-view .char-meta { font-size: 0.9em; color: #555; margin-left: 10px; }
            .esp-admin-view .char-actions a, .esp-admin-view .char-actions form { margin-left: 10px; display: inline-block; vertical-align: middle;}
            .esp-admin-view .main-char-item { border: 1px solid #0073aa; padding: 10px; margin-bottom:15px; background: #f7fcfe; }
            .esp-admin-view .alt-list-heading { margin-top: 15px; font-weight: bold; }
            .esp-admin-view .skill-table-container { margin-top: 20px; }
            .esp-admin-view .admin-action-button { padding: 2px 5px !important; font-size: 0.8em !important; line-height: 1.2 !important; height: auto !important; min-height: 0 !important;}
            .esp-admin-view .assign-alt-form select {vertical-align: baseline; margin: 0 5px;}
        </style>
        <?php
        $selected_user_id = isset( $_GET['view_user_id'] ) ? intval( $_GET['view_user_id'] ) : 0;
        $selected_char_id_to_view_skills = isset( $_GET['view_char_id'] ) ? intval( $_GET['view_char_id'] ) : 0;

        // Get all defined doctrine ship names (once if a user is selected for character listing)
        $all_doctrine_names = [];
        if ($selected_user_id > 0 && $selected_char_id_to_view_skills == 0) { // Only needed for the character list view
            $all_doctrine_objects = get_option( 'esp_doctrine_ships', [] );
            if (is_array($all_doctrine_objects) && !empty($all_doctrine_objects)) {
                foreach ($all_doctrine_objects as $doctrine_item) {
                    if (isset($doctrine_item['ship_name'])) {
                        $all_doctrine_names[] = $doctrine_item['ship_name'];
                    }
                }
                sort($all_doctrine_names); // Optional: for consistent display order
            }
        }

        if ( $selected_user_id > 0 ) {
            $user_info = get_userdata( $selected_user_id );
            if ( ! $user_info ) {
                echo '<p>' . esc_html__( 'WordPress user not found.', 'eve-skill-plugin' ) . '</p>';
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eve_view_all_user_skills' ) ) . '">« ' . esc_html__( 'Back to all users list', 'eve-skill-plugin' ) . '</a></p>';
                echo '</div>'; return;
            }
            echo '<h2>' . sprintf( esc_html__( 'EVE Characters for: %s', 'eve-skill-plugin' ), esc_html( $user_info->display_name ) ) . ' (' . esc_html($user_info->user_login) . ')</h2>';

            if ( $selected_char_id_to_view_skills > 0 ) { // Viewing specific character's skills
                $is_main_view = ($selected_char_id_to_view_skills == get_user_meta( $selected_user_id, 'esp_main_eve_character_id', true ));
                $char_name_to_display = $is_main_view ? get_user_meta($selected_user_id, 'esp_main_eve_character_name', true) : esp_get_alt_character_data_item($selected_user_id, $selected_char_id_to_view_skills, 'name');
                echo '<h3>' . sprintf( esc_html__( 'Skills for %s (ID: %s)', 'eve-skill-plugin' ), esc_html( $char_name_to_display ), esc_html($selected_char_id_to_view_skills) ) . '</h3>';
                echo '<div class="skill-table-container">';
                esp_display_character_skills_for_admin( $selected_user_id, $selected_char_id_to_view_skills );
                echo '</div>';
                echo '<p><a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id], admin_url('admin.php'))) . '">« ' . esc_html__( 'Back to character list for this user', 'eve-skill-plugin' ) . '</a></p>';
            } else { // Viewing the list of characters for the selected user
                $main_char_id = get_user_meta( $selected_user_id, 'esp_main_eve_character_id', true );
                $alt_characters = get_user_meta($selected_user_id, 'esp_alt_characters', true);
                if (!is_array($alt_characters)) $alt_characters = [];
                echo '<ul class="char-tree">';
                if ( $main_char_id ) {
                    $main_char_name = get_user_meta( $selected_user_id, 'esp_main_eve_character_name', true );
                    $main_total_sp = get_user_meta( $selected_user_id, 'esp_main_total_sp', true );
                    $main_last_updated = get_user_meta( $selected_user_id, 'esp_main_skills_last_updated', true );
                    echo '<li class="char-item main-char-item">';
                    echo '<strong>MAIN:</strong> ' . esc_html( $main_char_name ) . ' (ID: ' . esc_html( $main_char_id ) . ')';
                    echo '<div class="char-meta">';
                    echo 'Total SP: ' . esc_html( number_format( (float) $main_total_sp ) );
                    if ($main_last_updated) echo ' | Last Updated: ' . esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$main_last_updated));
                    echo '</div>';

                    if (!empty($all_doctrine_names)) {
                        $admin_main_compliant_doctrines = esp_get_character_compliant_doctrines( $selected_user_id, $main_char_id, 'main' );
                        $admin_main_non_compliant_doctrines = array_diff( $all_doctrine_names, $admin_main_compliant_doctrines );

                        if ( ! empty( $admin_main_compliant_doctrines ) ) {
                            echo '<div class="char-meta" style="margin-top: 5px;"><strong style="color: #6aa84f;">' . esc_html__( 'Can fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $admin_main_compliant_doctrines ) ) . '</div>';
                        }
                        if ( ! empty( $admin_main_non_compliant_doctrines ) ) {
                            echo '<div class="char-meta" style="margin-top: 5px;"><strong style="color: #dc3232;">' . esc_html__( 'Cannot fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $admin_main_non_compliant_doctrines ) ) . '</div>';
                        }
                    } else {
                         echo '<div class="char-meta" style="margin-top: 5px;"><small>' . esc_html__('No doctrines defined by admin yet.', 'eve-skill-plugin') . '</small></div>';
                    }

                    echo '<div class="char-actions">';
                    echo '<a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id, 'view_char_id' => $main_char_id], admin_url('admin.php'))) . '">' . esc_html__('View Skills', 'eve-skill-plugin') . '</a>';
                    if (current_user_can('manage_options') && empty($alt_characters)) {
                        // ... (rest of the reassign main form code from your original) ...
                        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="assign-alt-form" style="margin-top: 5px;">';
                        echo '<input type="hidden" name="action" value="esp_admin_reassign_character">';
                        echo '<input type="hidden" name="original_wp_user_id" value="'.esc_attr($selected_user_id).'">';
                        echo '<input type="hidden" name="character_id_to_move" value="'.esc_attr($main_char_id).'">';
                        echo '<input type="hidden" name="character_type_to_move" value="main">';
                        wp_nonce_field('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce');
                        $select_id = 'reassign_main_to_user_'.esc_attr($selected_user_id).'_'.esc_attr($main_char_id);
                        echo '<label for="'.esc_attr($select_id).'" class="screen-reader-text">' . esc_html__('Assign this Main to different User as Alt:', 'eve-skill-plugin') . '</label>';
                        echo '<select name="new_main_wp_user_id" id="'.esc_attr($select_id).'">';
                        echo '<option value="">' . esc_html__('-- Select Target User --', 'eve-skill-plugin') . '</option>';
                        $all_potential_main_users_args = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all_with_meta', 'exclude' => [$selected_user_id] ];
                        $potential_main_users = get_users($all_potential_main_users_args);
                        foreach ($potential_main_users as $potential_user) {
                            $potential_main_char_name = get_user_meta($potential_user->ID, 'esp_main_eve_character_name', true);
                            if ($potential_main_char_name) {
                                echo '<option value="'.esc_attr($potential_user->ID).'">';
                                echo esc_html($potential_user->display_name . ' (' . $potential_main_char_name . ')');
                                echo '</option>';
                            }
                        }
                        echo '</select>';
                        submit_button(__('Assign as Alt', 'eve-skill-plugin'), 'secondary small admin-action-button', 'reassign_main', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to re-assign this main character as an alt to the selected user? This user will then have no main character.', 'eve-skill-plugin')).'");']);
                        echo '</form>';
                    }
                    echo '</div>';
                    if (is_array($alt_characters) && !empty($alt_characters)) {
                        echo '<div class="alt-list-heading">' . esc_html__('ALTS:', 'eve-skill-plugin') . '</div>';
                        echo '<ul>';
                        foreach ($alt_characters as $alt_char_idx => $alt_char) {
                             if (!is_array($alt_char) || !isset($alt_char['id']) || !isset($alt_char['name'])) continue;
                            echo '<li class="char-item">';
                            echo esc_html( $alt_char['name'] ) . ' (ID: ' . esc_html( $alt_char['id'] ) . ')';
                            echo '<div class="char-meta">';
                            echo 'Total SP: ' . esc_html( number_format( (float) ($alt_char['total_sp'] ?? 0) ) );
                            if (!empty($alt_char['skills_last_updated'])) echo ' | Last Updated: ' . esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$alt_char['skills_last_updated']));
                            echo '</div>';

                            if (!empty($all_doctrine_names)) {
                                $admin_alt_compliant_doctrines = esp_get_character_compliant_doctrines( $selected_user_id, $alt_char['id'], 'alt' );
                                $admin_alt_non_compliant_doctrines = array_diff( $all_doctrine_names, $admin_alt_compliant_doctrines );

                                if ( ! empty( $admin_alt_compliant_doctrines ) ) {
                                    echo '<div class="char-meta" style="margin-top: 5px;"><strong style="color: #6aa84f;">' . esc_html__( 'Can fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $admin_alt_compliant_doctrines ) ) . '</div>';
                                }
                                if ( ! empty( $admin_alt_non_compliant_doctrines ) ) {
                                    echo '<div class="char-meta" style="margin-top: 5px;"><strong style="color: #dc3232;">' . esc_html__( 'Cannot fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $admin_alt_non_compliant_doctrines ) ) . '</div>';
                                }
                            } // No else for "no doctrines" on individual alts, handled for main

                            echo '<div class="char-actions">';
                            echo '<a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id, 'view_char_id' => $alt_char['id']], admin_url('admin.php'))) . '">' . esc_html__('View Skills', 'eve-skill-plugin') . '</a>';
                            if (current_user_can('manage_options')) {
                                // ... (rest of the alt action forms from your original) ...
                                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;">'; 
                                echo '<input type="hidden" name="action" value="esp_admin_promote_alt_to_main">'; 
                                echo '<input type="hidden" name="user_id_to_affect" value="'.esc_attr($selected_user_id).'">'; 
                                echo '<input type="hidden" name="alt_char_id_to_promote" value="'.esc_attr($alt_char['id']).'">'; 
                                wp_nonce_field('esp_admin_promote_alt_action', 'esp_admin_promote_alt_nonce'); 
                                submit_button(__('Promote to Main', 'eve-skill-plugin'), 'secondary small admin-action-button', 'promote_alt_'.esc_attr($alt_char['id']), false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to promote this alt to main? The current main will become an alt.', 'eve-skill-plugin')).'");']); 
                                echo '</form>';
                                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;">'; 
                                echo '<input type="hidden" name="action" value="esp_admin_remove_user_alt_character">'; 
                                echo '<input type="hidden" name="user_id_to_affect" value="'.esc_attr($selected_user_id).'">'; 
                                echo '<input type="hidden" name="alt_char_id_to_remove" value="'.esc_attr($alt_char['id']).'">'; 
                                wp_nonce_field('esp_admin_remove_alt_action', 'esp_admin_remove_alt_nonce'); 
                                submit_button(__('Remove Alt', 'eve-skill-plugin'), 'delete small admin-action-button', 'remove_alt_'.esc_attr($alt_char['id']), false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove this alt from this user?', 'eve-skill-plugin')).'");']); 
                                echo '</form>';
                                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="assign-alt-form" style="display:inline-block;">';
                                echo '<input type="hidden" name="action" value="esp_admin_reassign_character">';
                                echo '<input type="hidden" name="original_wp_user_id" value="'.esc_attr($selected_user_id).'">';
                                echo '<input type="hidden" name="character_id_to_move" value="'.esc_attr($alt_char['id']).'">';
                                echo '<input type="hidden" name="character_type_to_move" value="alt">';
                                wp_nonce_field('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce');
                                $select_alt_id = 'reassign_alt_to_user_'.esc_attr($selected_user_id).'_'.esc_attr($alt_char['id']);
                                echo '<label for="'.esc_attr($select_alt_id).'" class="screen-reader-text">' . esc_html__('Assign Alt to different Main User:', 'eve-skill-plugin') . '</label>';
                                echo '<select name="new_main_wp_user_id" id="'.esc_attr($select_alt_id).'">';
                                echo '<option value="">' . esc_html__('-- Select Target User --', 'eve-skill-plugin') . '</option>';
                                $all_potential_main_users_args_alt = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all_with_meta', 'exclude' => [$selected_user_id] ];
                                $potential_main_users_alt = get_users($all_potential_main_users_args_alt);
                                foreach ($potential_main_users_alt as $potential_user_alt) {
                                    $potential_main_char_name_alt = get_user_meta($potential_user_alt->ID, 'esp_main_eve_character_name', true);
                                    if ($potential_main_char_name_alt) {
                                        echo '<option value="'.esc_attr($potential_user_alt->ID).'">';
                                        echo esc_html($potential_user_alt->display_name . ' (' . $potential_main_char_name_alt . ')');
                                        echo '</option>';
                                    }
                                }
                                echo '</select>';
                                submit_button(__('Assign Alt', 'eve-skill-plugin'), 'secondary small admin-action-button', 'reassign_alt_'.esc_attr($alt_char['id']), false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to re-assign this alt to the selected main character\'s account?', 'eve-skill-plugin')).'");']);
                                echo '</form>';
                            }
                            echo '</div>'; echo '</li>';
                        } echo '</ul>';
                    } else { echo '<p class="char-meta">' . esc_html__('No alt characters linked.', 'eve-skill-plugin') . '</p>'; }
                    echo '</li>';
                } else { echo '<li>' . esc_html__( 'No main EVE character linked for this user.', 'eve-skill-plugin' ) . '</li>'; }
                echo '</ul>';
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eve_view_all_user_skills' ) ) . '">« ' . esc_html__( 'Back to all users list', 'eve-skill-plugin' ) . '</a></p>';
            }
        } else { // Initial page view (list of all users with EVE data)
            $args = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all', 'orderby' => 'display_name', ];
            $users_with_main_eve = get_users( $args );
            if ( ! empty( $users_with_main_eve ) ) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>' . esc_html__( 'WordPress User', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Main EVE Character', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Alts Count', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Action', 'eve-skill-plugin' ) . '</th></tr></thead>';
                echo '<tbody>';
                foreach ( $users_with_main_eve as $user ) {
                    $main_char_name = get_user_meta( $user->ID, 'esp_main_eve_character_name', true );
                    $alts = get_user_meta($user->ID, 'esp_alt_characters', true);
                    $alts_count = is_array($alts) ? count($alts) : 0;
                    $view_link = add_query_arg( ['page' => 'eve_view_all_user_skills', 'view_user_id' => $user->ID ], admin_url( 'admin.php' ) );
                    echo '<tr>';
                    echo '<td>' . esc_html( $user->display_name ) . ' (' . esc_html($user->user_login) . ')</td>';
                    echo '<td>' . esc_html( $main_char_name ) . '</td>';
                    echo '<td>' . esc_html( $alts_count ) . '</td>';
                    echo '<td><a href="' . esc_url( $view_link ) . '">' . esc_html__( 'View Details', 'eve-skill-plugin' ) . '</a></td>';
                    echo '</tr>';
                } echo '</tbody></table>';
            } else { echo '<p>' . esc_html__( 'No users have linked their main EVE character yet.', 'eve-skill-plugin' ) . '</p>'; }
        } ?>
    </div> <?php
}

function esp_display_character_skills_for_admin( $user_id_to_view, $character_id_to_display ) {
    // A single call gets all the data we need.
    $character = esp_get_character_data($user_id_to_view, $character_id_to_display);

    if (!$character) {
        echo '<p>' . esc_html__('Character data could not be found for this user.', 'eve-skill-plugin') . '</p>';
        return;
    }
    
    // Safely get the values from the returned array.
    $skills_data  = $character['skills_data'] ?? null;
    $total_sp     = $character['total_sp'] ?? 0;
    $last_updated = $character['skills_last_updated'] ?? null;

    if ($last_updated) { 
        echo '<p><small>' . sprintf(esc_html__('Skills last updated: %s', 'eve-skill-plugin'), esc_html(wp_date( get_option('date_format') . ' ' . get_option('time_format'), (int)$last_updated))) . '</small></p>';
    } else { 
        echo '<p><small>' . esc_html__('Skills last updated: Unknown', 'eve-skill-plugin') . '</small></p>'; 
    }

    if ( $skills_data && is_array( $skills_data ) && !empty($skills_data) ) {
        echo '<p>' . sprintf( esc_html__( 'Total Skillpoints: %s', 'eve-skill-plugin' ), number_format( (float) $total_sp ) ) . '</p>';
        echo '<table class="wp-list-table widefat striped"><thead><tr><th>Skill Name</th><th>Skill ID</th><th>Level</th><th>Skillpoints</th></tr></thead><tbody>';
        $skill_details_for_sort = [];
        foreach ( $skills_data as $skill_key => $skill ) {
            if ( !is_array($skill) || !isset($skill['skill_id']) || !isset($skill['active_skill_level']) || !isset($skill['skillpoints_in_skill']) ) {
                error_log("[EVE Skill Plugin] Malformed skill entry for user $user_id_to_view, char $character_id_to_display. Skill key: $skill_key. Data: " . print_r($skill, true)); continue; 
            }
            $skill_details_for_sort[] = [ 'name' => esp_get_skill_name( (int)$skill['skill_id'] ), 'id' => (int)$skill['skill_id'], 'level' => (int)$skill['active_skill_level'], 'sp' => (float)$skill['skillpoints_in_skill'] ];
        }
        if (!empty($skill_details_for_sort)) {
            usort($skill_details_for_sort, function($a, $b) { return strcmp($a['name'], $b['name']); });
            foreach ( $skill_details_for_sort as $skill_detail ) { printf( '<tr><td>%s</td><td>%d</td><td>%d</td><td>%s</td></tr>', esc_html( $skill_detail['name'] ), esc_html( $skill_detail['id'] ), esc_html( $skill_detail['level'] ), esc_html( number_format( $skill_detail['sp'] ) ) ); }
        } else if (is_array($skills_data) && empty($skill_details_for_sort) && !empty($skills_data) ) {
             echo '<tr><td colspan="4">' . esc_html__('Skill data appears to be malformed or empty after processing.', 'eve-skill-plugin') . '</td></tr>';
        } else if (!is_array($skills_data)) { 
             echo '<tr><td colspan="4">' . esc_html__('Skill data is not in the expected array format.', 'eve-skill-plugin') . '</td></tr>';
        }
        echo '</tbody></table>';
    } else { 
        echo '<p>' . esc_html__( 'No skill data found for this character, or skills have not been fetched/stored correctly.', 'eve-skill-plugin' ) . '</p>';
        if (is_array($skills_data) && empty($skills_data)) { echo '<p><small>' . esc_html__('(The skill list from ESI was empty or could not be processed).', 'eve-skill-plugin') . '</small></p>'; }
    }
}

function esp_render_view_all_user_assets_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'eve-skill-plugin' ) );
    }
    ?>
    <div class="wrap esp-admin-view">
        <h1><?php esc_html_e( 'View User EVE Assets', 'eve-skill-plugin' ); ?></h1>
        <style>
            .esp-admin-view .assets-table { width: 100%; margin-top: 20px; border-collapse: collapse; }
            .esp-admin-view .assets-table th, .esp-admin-view .assets-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
            .esp-admin-view .assets-table th { background-color: #f1f1f1; }
            .esp-admin-view .assets-table td.quantity { text-align: right; }
            .esp-admin-view .filter-form input[type="text"] { margin-right: 10px; }
            .esp-admin-view .user-section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; background: #fff; }
            .esp-admin-view .user-section h2 { margin-top: 0;}
        </style>
        <?php
        $filter_char_name = isset($_GET['filter_char_name']) ? sanitize_text_field($_GET['filter_char_name']) : '';
        $filter_item_name = isset($_GET['filter_item_name']) ? sanitize_text_field($_GET['filter_item_name']) : '';
        $filter_location_name = isset($_GET['filter_location_name']) ? sanitize_text_field($_GET['filter_location_name']) : '';
        ?>
        <form method="get" class="filter-form">
            <input type="hidden" name="page" value="eve_view_all_user_assets">
            <?php esc_html_e('Char Name:', 'eve-skill-plugin'); ?> <input type="text" name="filter_char_name" value="<?php echo esc_attr($filter_char_name); ?>">
            <?php esc_html_e('Item Name:', 'eve-skill-plugin'); ?> <input type="text" name="filter_item_name" value="<?php echo esc_attr($filter_item_name); ?>">
            <?php esc_html_e('Location:', 'eve-skill-plugin'); ?> <input type="text" name="filter_location_name" value="<?php echo esc_attr($filter_location_name); ?>">
            <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'eve-skill-plugin'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=eve_view_all_user_assets')); ?>" class="button"><?php esc_html_e('Clear Filters', 'eve-skill-plugin'); ?></a>
        </form><hr>
        <?php
        $users_with_eve_data = get_users(['meta_query' => ['relation' => 'OR',['key' => 'esp_main_eve_character_id', 'compare' => 'EXISTS'],],'fields' => 'all_with_meta',]);
        if ( empty( $users_with_eve_data ) ) {
            echo '<p>' . esc_html__( 'No users have linked EVE characters or no asset data available.', 'eve-skill-plugin' ) . '</p>'; echo '</div>'; return;
        }
        foreach ( $users_with_eve_data as $user ) {
            echo '<div class="user-section">';
            echo '<h2>' . sprintf( esc_html__( 'Assets for WordPress User: %s', 'eve-skill-plugin' ), esc_html( $user->display_name ) ) . ' (' . esc_html($user->user_login) . ')</h2>';
            $user_assets_found = false; $character_asset_list_for_user = [];
            $main_char_id = get_user_meta( $user->ID, 'esp_main_eve_character_id', true );
            if ( $main_char_id ) {
                $main_char_name = get_user_meta( $user->ID, 'esp_main_eve_character_name', true );
                $main_assets_data = get_user_meta( $user->ID, 'esp_main_assets_data', true );
                $main_assets_last_updated = get_user_meta( $user->ID, 'esp_main_assets_last_updated', true );
                $main_access_token = get_user_meta( $user->ID, 'esp_main_access_token', true);
                if ( is_array( $main_assets_data ) && ! empty( $main_assets_data ) ) {
                    $user_assets_found = true;
                    foreach ($main_assets_data as $asset) {
                        $asset['char_name'] = $main_char_name . " (Main)"; $asset['char_id'] = $main_char_id;
                        $asset['wp_user_id'] = $user->ID; $asset['wp_user_display_name'] = $user->display_name;
                        $asset['access_token_for_location_lookup'] = $main_access_token;
                        $character_asset_list_for_user[] = $asset;
                    }
                    if ($main_assets_last_updated) { echo '<p><small>' . sprintf(esc_html__('Main char assets last updated: %s', 'eve-skill-plugin'), esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$main_assets_last_updated))) . '</small></p>';}
                } else { echo '<p>' . sprintf(esc_html__('No asset data found for main character %s or not yet fetched.', 'eve-skill-plugin'), esc_html($main_char_name)) . '</p>';}
            }
            $alt_characters = get_user_meta( $user->ID, 'esp_alt_characters', true );
            if ( is_array( $alt_characters ) && ! empty( $alt_characters ) ) {
                foreach ( $alt_characters as $alt_char ) {
                    if ( !isset($alt_char['id']) || !isset($alt_char['name']) ) continue;
                    $alt_assets_data = $alt_char['assets_data'] ?? [];
                    $alt_assets_last_updated = $alt_char['assets_last_updated'] ?? null;
                    $alt_access_token = $alt_char['access_token'] ?? null;
                    if ( is_array( $alt_assets_data ) && ! empty( $alt_assets_data ) ) {
                        $user_assets_found = true;
                        foreach ($alt_assets_data as $asset) {
                            $asset['char_name'] = $alt_char['name'] . " (Alt)"; $asset['char_id'] = $alt_char['id'];
                            $asset['wp_user_id'] = $user->ID; $asset['wp_user_display_name'] = $user->display_name;
                            $asset['access_token_for_location_lookup'] = $alt_access_token;
                            $character_asset_list_for_user[] = $asset;
                        }
                         if ($alt_assets_last_updated) { echo '<p><small>' . sprintf(esc_html__('Assets for alt %s last updated: %s', 'eve-skill-plugin'), esc_html($alt_char['name']), esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$alt_assets_last_updated))) . '</small></p>';}
                    } else { echo '<p>' . sprintf(esc_html__('No asset data found for alt character %s or not yet fetched.', 'eve-skill-plugin'), esc_html($alt_char['name'])) . '</p>';}
                }
            }
            if ($user_assets_found && !empty($character_asset_list_for_user)) {
                $filtered_assets_for_user = array_filter($character_asset_list_for_user, function($asset) use ($filter_char_name, $filter_item_name, $filter_location_name) {
                    $char_match = empty($filter_char_name) || stripos($asset['char_name'], $filter_char_name) !== false;
                    $item_name_resolved = esp_get_item_name($asset['type_id']);
                    $item_match = empty($filter_item_name) || stripos($item_name_resolved, $filter_item_name) !== false;
                    $location_name_resolved = esp_get_location_name($asset['location_id'], $asset['location_type'], $asset['access_token_for_location_lookup'] ?? null, $asset['char_id']);
                    $location_match = empty($filter_location_name) || stripos($location_name_resolved, $filter_location_name) !== false;
                    return $char_match && $item_match && $location_match;
                });
                if (!empty($filtered_assets_for_user)) {
                    echo '<table class="assets-table widefat striped">';
                    echo '<thead><tr><th>' . esc_html__( 'Character Name', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Item Name', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Quantity', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Location', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Location Flag', 'eve-skill-plugin' ) . '</th></tr></thead>';
                    echo '<tbody>';
                    usort($filtered_assets_for_user, function($a, $b) {
                        $char_comp = strcmp($a['char_name'], $b['char_name']);
                        if ($char_comp !== 0) return $char_comp;
                        return strcmp(esp_get_item_name($a['type_id']), esp_get_item_name($b['type_id']));
                    });
                    foreach ( $filtered_assets_for_user as $asset ) {
                        $item_name = esp_get_item_name( $asset['type_id'] );
                        $location_name = esp_get_location_name( $asset['location_id'], $asset['location_type'], $asset['access_token_for_location_lookup'] ?? null, $asset['char_id'] ); 
                        $location_flag = $asset['location_flag'];
                        echo '<tr>';
                        echo '<td>' . esc_html( $asset['char_name'] ) . '</td>';
                        echo '<td>' . esc_html( $item_name ) . ' (ID: ' . esc_html( $asset['type_id'] ) . ')' . ($asset['is_singleton'] ? ' <i>(Singleton)</i>':'') . (isset($asset['is_blueprint_copy']) && $asset['is_blueprint_copy'] ? ' <i>(BPC)</i>':''). '</td>';
                        echo '<td class="quantity">' . esc_html( number_format( $asset['quantity'] ) ) . '</td>';
                        echo '<td>' . esc_html( $location_name ) . '</td>';
                        echo '<td>' . esc_html( $location_flag ) . '</td>';
                        echo '</tr>';
                    } echo '</tbody></table>';
                } else if (!empty($filter_char_name) || !empty($filter_item_name) || !empty($filter_location_name)) {
                     echo '<p>' . esc_html__( 'No assets found matching your current filter criteria for this user.', 'eve-skill-plugin' ) . '</p>';
                } else { echo '<p>' . esc_html__( 'No asset data to display for this user (perhaps it was empty from ESI or an error occurred).', 'eve-skill-plugin' ) . '</p>';}
            } elseif (!$user_assets_found) { echo '<p>' . esc_html__( 'No EVE characters with asset data linked for this WordPress user.', 'eve-skill-plugin' ) . '</p>';}
            echo '</div>'; 
        } ?>
    </div> <?php
}

// --- ESI DATA FETCHERS & HELPERS ---
function esp_get_skill_name( $skill_id ) { 
    $skill_id = intval($skill_id); if ($skill_id <= 0) return "Invalid Skill ID";
    $transient_key = 'esp_skill_name_' . $skill_id; $skill_name = get_transient( $transient_key ); 
    if ( false === $skill_name ) { 
        $request_url = "https://esi.evetech.net/latest/universe/types/{$skill_id}/?datasource=tranquility";
        $response = wp_remote_get( $request_url, ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT,'Accept-Language' => 'en-us']]); 
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) { 
            $type_data = json_decode( wp_remote_retrieve_body( $response ), true ); 
            if ( isset( $type_data['name'] ) ) { $skill_name = $type_data['name']; set_transient( $transient_key, $skill_name, DAY_IN_SECONDS * 30 ); } 
            else { $skill_name = "Unknown Skill (ID: {$skill_id})"; } 
        } else { 
            $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
            error_log("[ESP] Failed to get skill name for skill_id {$skill_id}. ESI Error: {$error_message}");
            $skill_name = "Skill ID: {$skill_id} (Lookup Failed)"; 
        } 
    } return $skill_name; 
}

function esp_get_item_name( $type_id ) {
    $type_id = intval($type_id); if ($type_id <= 0) return "Invalid Type ID";
    $transient_key = 'esp_item_name_' . $type_id; $item_name = get_transient( $transient_key );
    if ( false === $item_name ) {
        $request_url = "https://esi.evetech.net/latest/universe/types/{$type_id}/?datasource=tranquility";
        $response = wp_remote_get( $request_url, ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, 'Accept-Language' => 'en-us']] );
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $type_data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $type_data['name'] ) ) { $item_name = $type_data['name']; set_transient( $transient_key, $item_name, DAY_IN_SECONDS * 30 ); } 
            else { $item_name = "Unknown Item (ID: {$type_id})"; }
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
            error_log("[ESP] Failed to get item name for type_id {$type_id}. ESI Error: {$error_message}");
            $item_name = "Item ID: {$type_id} (Lookup Failed)";
        }
    } return $item_name;
}

function esp_get_location_name($location_id, $location_type, $access_token = null, $character_id_for_structure_lookup = null) {
    $location_id = (int)$location_id; if ($location_id <= 0) return "Invalid Location ID";
    $transient_key = 'esp_loc_name_' . $location_id; $location_name = get_transient($transient_key);
    if (false === $location_name) {
        $esi_url = '';
        switch ($location_type) {
            case 'station': $esi_url = "https://esi.evetech.net/latest/universe/stations/{$location_id}/?datasource=tranquility"; break;
            case 'structure': 
            case 'solar_system': 
                if ($access_token && $character_id_for_structure_lookup) { 
                     $esi_url = "https://esi.evetech.net/latest/universe/structures/{$location_id}/?datasource=tranquility";
                } else {
                    $location_name = "Structure/System ID: {$location_id}";
                    set_transient($transient_key, $location_name, HOUR_IN_SECONDS); return $location_name;
                } break;
            case 'item': $location_name = "In Container (ID: {$location_id})"; set_transient($transient_key, $location_name, DAY_IN_SECONDS); return $location_name;
            default: $location_name = ucfirst(str_replace('_', ' ', $location_type)) . " (ID: {$location_id})"; set_transient($transient_key, $location_name, DAY_IN_SECONDS); return $location_name;
        }
        if (!empty($esi_url)) {
            $headers = ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, 'Accept-Language' => 'en-us'];
            if ($access_token && ($location_type == 'structure')) { $headers['Authorization'] = 'Bearer ' . $access_token; } // Only for structure endpoint that needs auth
            $response = wp_remote_get($esi_url, ['headers' => $headers]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($data['name'])) { $location_name = $data['name'];} 
                elseif (isset($data['station_name'])) { $location_name = $data['station_name'];} 
                else { $location_name = "Location {$location_id} (Name N/A)";}
                set_transient($transient_key, $location_name, DAY_IN_SECONDS * 7);
            } else {
                $error_code = wp_remote_retrieve_response_code($response);
                if (($location_type == 'structure' || $location_type == 'solar_system') && ($error_code == 404 || $error_code == 403) ) { // If structure lookup failed, try as solar system (if it was solar_system originally or structure that might be a system id)
                    $sys_esi_url = "https://esi.evetech.net/latest/universe/systems/{$location_id}/?datasource=tranquility";
                    $sys_response = wp_remote_get($sys_esi_url, ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, 'Accept-Language' => 'en-us']]);
                    if(!is_wp_error($sys_response) && wp_remote_retrieve_response_code($sys_response) === 200) {
                        $sys_data = json_decode(wp_remote_retrieve_body($sys_response), true);
                        $location_name = isset($sys_data['name']) ? $sys_data['name'] . " (System)" : "System ID: {$location_id}";
                    } else { $location_name = "System/Structure ID: {$location_id} (Lookup Failed)";}
                } else { $location_name = "Location {$location_id} (Lookup Failed - $error_code)";}
                set_transient($transient_key, $location_name, HOUR_IN_SECONDS); 
            }
        }
    } return $location_name;
}

// --- SSO AUTHENTICATION FLOW ---
function esp_handle_sso_initiation() {
    if ( ! isset( $_POST['esp_initiate_sso_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['esp_initiate_sso_nonce']), 'esp_initiate_sso_action' ) ) { wp_die( 'Nonce verification failed!' ); }
    esp_start_session_if_needed(); 
    $client_id = get_option( 'esp_client_id' ); 
    $scopes = get_option( 'esp_scopes', ESP_DEFAULT_SCOPES ); 
    if ( ! $client_id ) { wp_die( 'EVE Client ID not configured.' ); }
    $auth_type = isset($_POST['esp_auth_type']) ? sanitize_key($_POST['esp_auth_type']) : 'main'; 
    $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : home_url();
    $sso_state_value = bin2hex( random_bytes( 16 ) ); 
    $_SESSION[ESP_SSO_SESSION_KEY] = [ 'nonce' => $sso_state_value, 'redirect_url' => $redirect_back_url, 'auth_type' => $auth_type ];
    $sso_redirect_uri_to_eve = admin_url( 'admin-post.php?action=' . ESP_SSO_CALLBACK_ACTION_NAME ); 
    $sso_url_to_eve = 'https://login.eveonline.com/v2/oauth/authorize/?' . http_build_query( [ 
        'response_type' => 'code', 'redirect_uri'  => $sso_redirect_uri_to_eve, 'client_id' => $client_id, 
        'scope' => $scopes, 'state' => $sso_state_value, 
    ] );
    wp_redirect( $sso_url_to_eve ); exit;
}
add_action( 'admin_post_esp_initiate_sso', 'esp_handle_sso_initiation' ); 
add_action( 'admin_post_nopriv_esp_initiate_sso', 'esp_handle_sso_initiation' ); 

function esp_get_or_create_wp_user_for_eve_char( $character_id, $character_name, $owner_hash ) { 
    $existing_users = get_users( [ 'meta_key' => 'esp_main_eve_character_id', 'meta_value' => $character_id, 'number' => 1, 'count_total' => false ]); 
    if ( ! empty( $existing_users ) ) { return $existing_users[0]; } 
    // Check if character exists as an alt for any user
    $alt_users_query_args = [
        'meta_query' => [
            [
                'key' => 'esp_alt_characters',
                'value' => '"id";i:'.$character_id.';', // Check for "id";i:CHAR_ID;
                'compare' => 'LIKE'
            ]
        ],
        'number' => 1,
        'count_total' => false
    ];
    $alt_users = get_users($alt_users_query_args);
    if (!empty($alt_users)) { return $alt_users[0]; } 

    $username = sanitize_user( 'eve_' . $character_id . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $character_name), true ); 
    $i = 0; $base_username = $username; 
    while ( username_exists( $username ) ) { $i++; $username = $base_username . '_' . $i; } 
    $random_password = wp_generate_password( 20, true, true ); 
    $email_domain_part = sanitize_title(str_replace(['http://', 'https://', 'www.'], '', get_bloginfo('url'))); 
    if (empty($email_domain_part)) $email_domain_part = 'localhost.local'; // More valid-looking placeholder
    $email = sanitize_email($character_id . '@' . $email_domain_part . '.eve-sso.invalid'); 
    $new_user_data = [ 'user_login' => $username, 'user_pass'  => $random_password, 'user_email' => $email, 'display_name' => $character_name, 'role' => get_option('default_role', 'subscriber') ]; 
    $new_user_id = wp_insert_user( $new_user_data ); 
    if ( is_wp_error( $new_user_id ) ) { return $new_user_id; } 
    update_user_meta( $new_user_id, 'created_via_eve_sso', time() ); 
    error_log("[ESP] Created new WP User ID $new_user_id ($username) for EVE Char $character_id ($character_name)"); 
    return get_user_by( 'id', $new_user_id ); 
}

function esp_handle_sso_callback() {
    esp_start_session_if_needed(); 
    if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) { wp_die( 'Invalid callback. Missing code/state.' ); }
    $code = sanitize_text_field( wp_unslash( $_GET['code'] ) ); $received_sso_state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
    $stored_sso_data = isset($_SESSION[ESP_SSO_SESSION_KEY]) ? $_SESSION[ESP_SSO_SESSION_KEY] : null;
    $auth_type = 'main'; $redirect_url_on_error = home_url(); 
    if ($stored_sso_data) { if(isset($stored_sso_data['redirect_url'])) $redirect_url_on_error = $stored_sso_data['redirect_url']; if(isset($stored_sso_data['auth_type'])) $auth_type = $stored_sso_data['auth_type']; }
    if ( ! $stored_sso_data || !isset($stored_sso_data['nonce']) || $stored_sso_data['nonce'] !== $received_sso_state ) { unset($_SESSION[ESP_SSO_SESSION_KEY]); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_state_mismatch', $redirect_url_on_error ) ); exit; }
    unset($_SESSION[ESP_SSO_SESSION_KEY]); $redirect_url_after_sso = $stored_sso_data['redirect_url'];
    $client_id = get_option( 'esp_client_id' ); $client_secret = get_option( 'esp_client_secret' );
    if ( ! $client_id || ! $client_secret ) { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_no_config', $redirect_url_after_sso ) ); exit; }
    $token_url = 'https://login.eveonline.com/v2/oauth/token'; $auth_header = base64_encode( $client_id . ':' . $client_secret );
    $response = wp_remote_post( $token_url, [ 'headers' => [ 'Authorization' => 'Basic ' . $auth_header, 'Content-Type'  => 'application/x-www-form-urlencoded', 'Host' => 'login.eveonline.com', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, ], 'body' => [ 'grant_type' => 'authorization_code', 'code' => $code, ], 'timeout' => 20, ]);
    if ( is_wp_error( $response ) ) { error_log('[ESP] SSO Token WP Error: ' . $response->get_error_message()); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_token_wp_error', $redirect_url_after_sso ) ); exit; }
    $body = wp_remote_retrieve_body( $response ); $token_data = json_decode( $body, true );
    if ( wp_remote_retrieve_response_code( $response ) !== 200 || ! isset( $token_data['access_token'] ) ) { error_log('[ESP] SSO Token EVE Error: ' . $body); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_token_eve_error', $redirect_url_after_sso ) ); exit; }
    $access_token = sanitize_text_field( $token_data['access_token'] ); $refresh_token = sanitize_text_field( $token_data['refresh_token'] ); $expires_in = intval( $token_data['expires_in'] );
    $verify_url = 'https://login.eveonline.com/oauth/verify'; 
    $verify_response = wp_remote_get( $verify_url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $access_token, 'Host' => 'login.eveonline.com', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, ], 'timeout' => 20, ]);
    if ( is_wp_error( $verify_response ) ) { error_log('[ESP] SSO Verify WP Error: ' . $verify_response->get_error_message()); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_verify_wp_error', $redirect_url_after_sso ) ); exit; }
    $verify_body = wp_remote_retrieve_body( $verify_response ); $char_data_from_esi = json_decode( $verify_body, true );
    if ( wp_remote_retrieve_response_code( $verify_response ) !== 200 || ! isset( $char_data_from_esi['CharacterID'] ) ) { error_log('[ESP] SSO Verify EVE Error: ' . $verify_body); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_verify_eve_error', $redirect_url_after_sso ) ); exit; }
    $authed_character_id = intval( $char_data_from_esi['CharacterID'] ); $authed_character_name = sanitize_text_field( $char_data_from_esi['CharacterName'] ); $authed_owner_hash = sanitize_text_field( $char_data_from_esi['CharacterOwnerHash'] );
    $user_id = 0; $is_new_wp_user = false;
    if ( is_user_logged_in() ) { $user_id = get_current_user_id(); } 
    else { 
        $wp_user = esp_get_or_create_wp_user_for_eve_char( $authed_character_id, $authed_character_name, $authed_owner_hash );
        if ( is_wp_error( $wp_user ) ) { error_log( "[ESP] Error get/create WP user for EVE char $authed_character_id: " . $wp_user->get_error_message() ); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_wp_user_error', $redirect_url_after_sso ) ); exit; }
        $user_id = $wp_user->ID; $created_meta = get_user_meta($user_id, 'created_via_eve_sso', true); if ($created_meta && (time() - $created_meta < 60) ){ $is_new_wp_user = true; }
        wp_set_current_user( $user_id, $wp_user->user_login ); wp_set_auth_cookie( $user_id ); do_action( 'wp_login', $wp_user->user_login, $wp_user );
    }
    if ( !$user_id ) { error_log("[ESP] Critical error: No WP user ID after EVE auth for char $authed_character_id"); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_internal_user_error', $redirect_url_after_sso ) ); exit; }
    $current_main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
    $skills_fetched = false; $assets_fetched = false; 
    if ($auth_type === 'main' || !$current_main_char_id) { 
        if ($current_main_char_id && $current_main_char_id != $authed_character_id) { error_log("[ESP] User $user_id switching main EVE char from $current_main_char_id to $authed_character_id");}
        update_user_meta( $user_id, 'esp_main_eve_character_id', $authed_character_id ); update_user_meta( $user_id, 'esp_main_eve_character_name', $authed_character_name ); update_user_meta( $user_id, 'esp_main_access_token', $access_token ); update_user_meta( $user_id, 'esp_main_refresh_token', $refresh_token ); update_user_meta( $user_id, 'esp_main_token_expires', time() + $expires_in ); update_user_meta( $user_id, 'esp_main_owner_hash', $authed_owner_hash );
        $skills_fetched = esp_fetch_and_store_skills_for_character_type( $user_id, $authed_character_id, $access_token, 'main' );
        $assets_fetched = esp_fetch_and_store_assets_for_character_type( $user_id, $authed_character_id, $access_token, 'main' ); 
    } elseif ($auth_type === 'alt') {
        if ($authed_character_id == $current_main_char_id) { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_alt_is_main', $redirect_url_after_sso ) ); exit; }
        $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); if (!is_array($alt_characters)) $alt_characters = [];
        $alt_exists_idx = -1; foreach ($alt_characters as $idx => $alt) { if (isset($alt['id']) && $alt['id'] == $authed_character_id) { $alt_exists_idx = $idx; break; } }
        $alt_data = [ 'id' => $authed_character_id, 'name' => $authed_character_name, 'owner_hash' => $authed_owner_hash, 'access_token' => $access_token, 'refresh_token' => $refresh_token, 'token_expires' => time() + $expires_in ];
        if ($alt_exists_idx !== -1) {
            $alt_characters[$alt_exists_idx] = array_merge($alt_characters[$alt_exists_idx], $alt_data); 
        } else { $alt_characters[] = $alt_data; }
        update_user_meta($user_id, 'esp_alt_characters', $alt_characters);
        $skills_fetched = esp_fetch_and_store_skills_for_character_type( $user_id, $authed_character_id, $access_token, 'alt' );
        $assets_fetched = esp_fetch_and_store_assets_for_character_type( $user_id, $authed_character_id, $access_token, 'alt' ); 
    } else { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_unknown_auth_type', $redirect_url_after_sso ) ); exit; }
    $final_redirect_url = $redirect_url_after_sso; $message_key = ($auth_type === 'alt') ? 'sso_alt_success' : 'sso_success';
    if (!$skills_fetched && !$assets_fetched) { $message_key = 'sso_skills_assets_failed';} 
    elseif (!$skills_fetched) { $message_key = 'sso_skills_failed';} 
    elseif (!$assets_fetched) { $message_key = 'sso_assets_failed';}
    if ($is_new_wp_user) $final_redirect_url = add_query_arg('new_user', 'true', $final_redirect_url); 
    wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, $message_key, $final_redirect_url ) ); exit;
}
add_action( 'admin_post_nopriv_' . ESP_SSO_CALLBACK_ACTION_NAME, 'esp_handle_sso_callback' );
add_action( 'admin_post_' . ESP_SSO_CALLBACK_ACTION_NAME, 'esp_handle_sso_callback' );

// --- ESI DATA FETCHING AND STORAGE (SKILLS & ASSETS) ---
function esp_fetch_and_store_skills_for_character_type( $user_id, $character_id, $access_token, $char_type = 'main' ) { 
    if ( ! $user_id || ! $character_id || ! $access_token) return false; 
    $skills_url = "https://esi.evetech.net/latest/characters/{$character_id}/skills/?datasource=tranquility"; 
    $skills_response = wp_remote_get( $skills_url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $access_token, 'Accept' => 'application/json', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, ], 'timeout' => 20, ]); 
    if ( is_wp_error( $skills_response ) || wp_remote_retrieve_response_code( $skills_response ) !== 200 ) { error_log("[ESP] Skills fetch error for char $character_id: " . (is_wp_error($skills_response) ? $skills_response->get_error_message() : wp_remote_retrieve_response_code($skills_response))); return false; } 
    $skills_body = wp_remote_retrieve_body( $skills_response ); $skills_data_esi = json_decode( $skills_body, true ); 
    if ( ! is_array($skills_data_esi) || ! isset( $skills_data_esi['skills'] ) || ! isset( $skills_data_esi['total_sp'] ) ) { error_log("[ESP] Skills JSON error for char $character_id"); return false; } 
    $skills_list = $skills_data_esi['skills']; $total_sp_value = (float) $skills_data_esi['total_sp']; $current_time = time(); 
    if ($char_type === 'main') { 
        update_user_meta( $user_id, 'esp_main_skills_data', $skills_list ); 
        update_user_meta( $user_id, 'esp_main_total_sp', $total_sp_value ); 
        update_user_meta( $user_id, 'esp_main_skills_last_updated', $current_time ); 
    } elseif ($char_type === 'alt') { 
        $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); 
        if (!is_array($alt_characters)) $alt_characters = []; 
        $found_alt_idx = -1; 
        foreach($alt_characters as $idx => $alt) { if (isset($alt['id']) && $alt['id'] == $character_id) { $found_alt_idx = $idx; break; } } 
        if ($found_alt_idx !== -1) { 
            $alt_characters[$found_alt_idx]['skills_data'] = $skills_list; 
            $alt_characters[$found_alt_idx]['total_sp'] = $total_sp_value; 
            $alt_characters[$found_alt_idx]['skills_last_updated'] = $current_time; 
            update_user_meta($user_id, 'esp_alt_characters', $alt_characters); 
        } else { error_log("[ESP] fetch_skills: Alt char ID $character_id not found for user $user_id."); return false; } 
    } return true; 
}

function esp_fetch_and_store_assets_for_character_type( $user_id, $character_id, $access_token, $char_type = 'main' ) {
    if ( ! $user_id || ! $character_id || ! $access_token) return false;
    $all_assets = []; $page = 1; $max_pages = 1; 
    do {
        $assets_url = "https://esi.evetech.net/latest/characters/{$character_id}/assets/?datasource=tranquility&page={$page}";
        $assets_response = wp_remote_get( $assets_url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $access_token, 'Accept' => 'application/json', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT,], 'timeout' => 30,]);
        if ( is_wp_error( $assets_response ) || wp_remote_retrieve_response_code( $assets_response ) !== 200 ) {
            error_log("[ESP] Assets fetch error for char $character_id (Page $page): " . (is_wp_error($assets_response) ? $assets_response->get_error_message() : wp_remote_retrieve_response_code($assets_response) . " Body: " . wp_remote_retrieve_body($assets_response)));
            return false; 
        }
        $headers = wp_remote_retrieve_headers($assets_response);
        if (isset($headers['x-pages']) && is_numeric($headers['x-pages'])) { $max_pages = intval($headers['x-pages']);}
        $assets_page_data = json_decode( wp_remote_retrieve_body( $assets_response ), true );
        if ( ! is_array($assets_page_data) ) { error_log("[ESP] Assets JSON error for char $character_id (Page $page)"); return false; }
        $all_assets = array_merge($all_assets, $assets_page_data); $page++;
        if ($page <= $max_pages) sleep(1); 
    } while ($page <= $max_pages);
    $current_time = time();
    if ($char_type === 'main') {
        update_user_meta( $user_id, 'esp_main_assets_data', $all_assets );
        update_user_meta( $user_id, 'esp_main_assets_last_updated', $current_time );
    } elseif ($char_type === 'alt') {
        // Re-fetch alt_characters array before updating to ensure latest state, then update specifically.
        $alt_characters_current = get_user_meta($user_id, 'esp_alt_characters', true);
        if (!is_array($alt_characters_current)) $alt_characters_current = [];
        $found_alt_idx = -1;
        foreach($alt_characters_current as $idx => $alt) { if (isset($alt['id']) && $alt['id'] == $character_id) { $found_alt_idx = $idx; break;}}
        if ($found_alt_idx !== -1) {
            $alt_characters_current[$found_alt_idx]['assets_data'] = $all_assets;
            $alt_characters_current[$found_alt_idx]['assets_last_updated'] = $current_time;
            update_user_meta($user_id, 'esp_alt_characters', $alt_characters_current);
        } else { error_log("[ESP] fetch_assets: Alt char ID $character_id not found for user $user_id during asset storage."); return false;}
    }
    error_log("[ESP] Successfully fetched and stored " . count($all_assets) . " asset entries for $char_type char $character_id (User $user_id)");
    return true;
}

// --- TOKEN REFRESH & CRON ---
function esp_refresh_eve_token_for_character_type( $user_id, $character_id, $char_type = 'main' ) { 
    if (!$user_id || !$character_id) return false; 
    $refresh_token_value = ''; 
    if ($char_type === 'main') { $refresh_token_value = get_user_meta( $user_id, 'esp_main_refresh_token', true ); } 
    elseif ($char_type === 'alt') { $refresh_token_value = esp_get_alt_character_data_item($user_id, $character_id, 'refresh_token'); } 
    if ( ! $refresh_token_value ) return false; 
    $client_id = get_option( 'esp_client_id' ); $client_secret = get_option( 'esp_client_secret' ); 
    if ( ! $client_id || ! $client_secret ) { error_log('[ESP] Token Refresh: Client ID/Secret not set.'); return false; } 
    $auth_header = base64_encode( $client_id . ':' . $client_secret ); $token_url = 'https://login.eveonline.com/v2/oauth/token'; 
    $response = wp_remote_post( $token_url, [ 'headers' => [ 'Authorization' => 'Basic ' . $auth_header, 'Content-Type'  => 'application/x-www-form-urlencoded', 'Host' => 'login.eveonline.com', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, ], 'body' => [ 'grant_type' => 'refresh_token', 'refresh_token' => $refresh_token_value, ], 'timeout' => 20, ]); 
    if ( is_wp_error( $response ) ) { error_log("[ESP] Token Refresh WP Error for $char_type CharID $character_id: " . $response->get_error_message()); return false; } 
    $body = wp_remote_retrieve_body( $response ); $token_data = json_decode( $body, true ); $response_code = wp_remote_retrieve_response_code( $response ); 
    if ( $response_code !== 200 || ! isset( $token_data['access_token'] ) ) { 
        error_log("[ESP] Token Refresh Failed for $char_type CharID $character_id (User $user_id). HTTP: $response_code. EVE: $body"); 
        if (strpos($body, 'invalid_token') !== false || strpos($body, 'invalid_grant') !== false || $response_code === 400) { 
            esp_clear_specific_character_tokens($user_id, $character_id, $char_type); 
        } return false; 
    } 
    $new_access_token = sanitize_text_field( $token_data['access_token'] ); 
    $new_refresh_token = isset($token_data['refresh_token']) ? sanitize_text_field($token_data['refresh_token']) : $refresh_token_value; // EVE might not always return a new refresh token
    $new_expires_in = intval( $token_data['expires_in'] ); 
    if ($char_type === 'main') { 
        update_user_meta( $user_id, 'esp_main_access_token', $new_access_token ); 
        update_user_meta( $user_id, 'esp_main_refresh_token', $new_refresh_token ); 
        update_user_meta( $user_id, 'esp_main_token_expires', time() + $new_expires_in ); 
    } elseif ($char_type === 'alt') { 
        // Need to re-fetch the alts array, modify, and save back
        $current_alts = get_user_meta($user_id, 'esp_alt_characters', true);
        if (!is_array($current_alts)) $current_alts = [];
        $alt_found_for_token_update = false;
        foreach ($current_alts as $idx => $alt_data) {
            if (isset($alt_data['id']) && $alt_data['id'] == $character_id) {
                $current_alts[$idx]['access_token'] = $new_access_token;
                $current_alts[$idx]['refresh_token'] = $new_refresh_token;
                $current_alts[$idx]['token_expires'] = time() + $new_expires_in;
                $alt_found_for_token_update = true;
                break;
            }
        }
        if ($alt_found_for_token_update) {
            update_user_meta($user_id, 'esp_alt_characters', $current_alts);
        } else {
             error_log("[ESP] Token Refresh: Alt char ID $character_id not found in meta for user $user_id during token update storage.");
        }
    } 
    error_log("[ESP] Token refreshed for $char_type CharID $character_id (User $user_id)"); 
    return ['access_token' => $new_access_token, 'expires_in' => $new_expires_in]; 
}

function esp_clear_specific_character_tokens($user_id, $character_id, $char_type = 'main') { 
    if (!$user_id || !$character_id) return; 
    if ($char_type === 'main') { 
        delete_user_meta( $user_id, 'esp_main_access_token'); 
        delete_user_meta( $user_id, 'esp_main_refresh_token'); 
        delete_user_meta( $user_id, 'esp_main_token_expires'); 
    } elseif ($char_type === 'alt') { 
        // Use the get/update helper which handles the array structure
        $current_alts = get_user_meta($user_id, 'esp_alt_characters', true);
        if (!is_array($current_alts)) $current_alts = [];
        $alt_found_for_clear = false;
        foreach ($current_alts as $idx => $alt_data) {
            if (isset($alt_data['id']) && $alt_data['id'] == $character_id) {
                $current_alts[$idx]['access_token'] = '';
                $current_alts[$idx]['refresh_token'] = '';
                $current_alts[$idx]['token_expires'] = 0;
                $alt_found_for_clear = true;
                break;
            }
        }
        if ($alt_found_for_clear) {
            update_user_meta($user_id, 'esp_alt_characters', $current_alts);
        }
    } 
    error_log("[ESP] Cleared EVE tokens for $char_type CharID $character_id (User $user_id)"); 
}

function esp_do_refresh_all_character_data() { 
    error_log('[ESP] Starting scheduled character data refresh cron (skills & assets).');
    $users_with_main_char = get_users([ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'ID', ]);
    if (empty($users_with_main_char)) { error_log('[ESP] Cron: No users with main EVE char.'); return; }
    foreach ($users_with_main_char as $user_id) {
        $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
        if ($main_char_id) {
            $main_token_expires = get_user_meta($user_id, 'esp_main_token_expires', true);
            $current_main_access_token = get_user_meta($user_id, 'esp_main_access_token', true);
            if (!$current_main_access_token || time() > ((int)$main_token_expires - 300)) {
                $refreshed_main_tokens = esp_refresh_eve_token_for_character_type($user_id, $main_char_id, 'main');
                if ($refreshed_main_tokens && isset($refreshed_main_tokens['access_token'])) {
                    $current_main_access_token = $refreshed_main_tokens['access_token'];
                } else {
                    error_log("[ESP] Cron: Failed to refresh main token for User $user_id, Char $main_char_id. Skipping data fetch.");
                    $current_main_access_token = null;
                }
            }
            if ($current_main_access_token) {
                esp_fetch_and_store_skills_for_character_type($user_id, $main_char_id, $current_main_access_token, 'main'); sleep(1); 
                esp_fetch_and_store_assets_for_character_type($user_id, $main_char_id, $current_main_access_token, 'main'); sleep(1);
            }
        }
        $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
        if (is_array($alt_characters) && !empty($alt_characters)) {
            // Create a temporary copy to iterate over, as token refresh might modify the underlying meta
            $alts_to_process = $alt_characters; 
            foreach ($alts_to_process as $alt_char_data) { 
                if (!isset($alt_char_data['id'])) continue;
                $alt_char_id = $alt_char_data['id'];
                // Fetch fresh token data for this alt before check, as it might have been updated by previous iteration for another alt of same user
                $current_alt_access_token = esp_get_alt_character_data_item($user_id, $alt_char_id, 'access_token');
                $alt_token_expires = esp_get_alt_character_data_item($user_id, $alt_char_id, 'token_expires');

                if (!$current_alt_access_token || time() > ((int)$alt_token_expires - 300)) {
                    $refreshed_alt_tokens = esp_refresh_eve_token_for_character_type($user_id, $alt_char_id, 'alt');
                    if ($refreshed_alt_tokens && isset($refreshed_alt_tokens['access_token'])) {
                        $current_alt_access_token = $refreshed_alt_tokens['access_token'];
                    } else {
                        error_log("[ESP] Cron: Failed to refresh alt token for User $user_id, Alt Char $alt_char_id. Skipping data fetch.");
                        $current_alt_access_token = null;
                    }
                }
                if ($current_alt_access_token) {
                    esp_fetch_and_store_skills_for_character_type($user_id, $alt_char_id, $current_alt_access_token, 'alt'); sleep(1);
                    esp_fetch_and_store_assets_for_character_type($user_id, $alt_char_id, $current_alt_access_token, 'alt'); sleep(1);
                }
            }
        }
    }
    error_log('[ESP] Finished scheduled character data refresh cron.');
}
add_action( 'esp_refresh_character_data_hook', 'esp_do_refresh_all_character_data' ); 
if ( ! wp_next_scheduled( 'esp_refresh_character_data_hook' ) ) { 
    wp_schedule_event( time(), 'hourly', 'esp_refresh_character_data_hook' ); 
}

// --- SHORTCODE ---
function esp_sso_login_button_shortcode( $atts ) { 
    $atts = shortcode_atts( [ 'text' => __( 'Authenticate Main EVE Character', 'eve-skill-plugin' ), 'alt_text' => __( 'Authenticate Alt Character', 'eve-skill-plugin' ), ], $atts, 'eve_sso_login_button' ); 
    esp_start_session_if_needed(); $output = ''; 
    if ( isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { ob_start(); esp_display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); $output .= ob_get_clean(); } 
    $client_id = get_option('esp_client_id'); 
    if ( ! $client_id ) { $output .= '<p style="color:orange;">' . esc_html__( 'EVE login is not fully configured by the site administrator.', 'eve-skill-plugin' ) . '</p>';} 
    $current_page_url = '';
    if (get_permalink()) { $current_page_url = get_permalink(); } 
    else { global $wp; if (isset($wp->request) && !empty($wp->request)) { $current_page_url = home_url(add_query_arg(array(), $wp->request)); }}
    if (!$current_page_url && isset($_SERVER['REQUEST_URI'])) { $current_page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; $current_page_url = strtok($current_page_url, '?');  }
    if (!$current_page_url) { $current_page_url = home_url('/'); }
    if ( is_user_logged_in() ) { 
        $user_id = get_current_user_id(); $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true); $main_char_name = get_user_meta($user_id, 'esp_main_eve_character_name', true); 
        if ($main_char_id && $main_char_name) { 
            $output .= '<div class="eve-sso-user-status">'; 
            $output .= '<p class="eve-sso-status">' . sprintf( esc_html__( 'Main EVE Character: %s.', 'eve-skill-plugin' ), esc_html( $main_char_name ) ) . '</p>'; 
            if ($client_id) { 
                $output .= '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" class="eve-sso-form" style="display:inline-block; margin-right:10px;">'; 
                $output .= '<input type="hidden" name="action" value="esp_initiate_sso"><input type="hidden" name="esp_auth_type" value="main">'; 
                $output .= '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url( $current_page_url ) . '">'; 
                $output .= wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce', true, false ); 
                $output .= '<button type="submit" class="button eve-sso-button">' . esc_html__( 'Re-Auth/Switch Main', 'eve-skill-plugin') . '</button></form>'; 
                $output .= '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" class="eve-sso-form" style="display:inline-block;">'; 
                $output .= '<input type="hidden" name="action" value="esp_initiate_sso"><input type="hidden" name="esp_auth_type" value="alt">'; 
                $output .= '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url( $current_page_url ) . '">'; 
                $output .= wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce', true, false ); 
                $output .= '<button type="submit" class="button eve-sso-button-alt primary">' . esc_html($atts['alt_text']) . '</button></form>';
            } $output .= '</div>'; 
        } else { 
            if ($client_id) {
                $output .= '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" class="eve-sso-form">'; 
                $output .= '<input type="hidden" name="action" value="esp_initiate_sso"><input type="hidden" name="esp_auth_type" value="main">'; 
                $output .= '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url( $current_page_url ) . '">'; 
                $output .= wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce', true, false ); 
                $output .= '<button type="submit" class="button eve-sso-button primary">' . esc_html($atts['text']) . '</button></form>'; 
            }
        } $output .= '<p><a href="'.esc_url(admin_url('admin.php?page=eve_skill_user_characters_page')).'">'.__('Manage My Linked EVE Characters', 'eve-skill-plugin').'</a></p>'; 
    } else { 
        if ($client_id) {
            $output .= '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" class="eve-sso-form">'; 
            $output .= '<input type="hidden" name="action" value="esp_initiate_sso"><input type="hidden" name="esp_auth_type" value="main">'; 
            $output .= '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url( $current_page_url ) . '">'; 
            $output .= wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce', true, false ); 
            $output .= '<button type="submit" class="button eve-sso-button primary">' . esc_html($atts['text']) . '</button></form>'; 
        }
    } return $output; 
}
add_shortcode( 'eve_sso_login_button', 'esp_sso_login_button_shortcode' );

// --- PLUGIN ACTIVATION/DEACTIVATION ---
function esp_deactivate_plugin() { 
    wp_clear_scheduled_hook( 'esp_refresh_character_data_hook' ); 
    wp_clear_scheduled_hook( 'esp_refresh_all_skills_hook' ); // Clear old hook too, just in case
    error_log('[ESP] Deactivated and cleared scheduled hooks.'); 
}
register_deactivation_hook( __FILE__, 'esp_deactivate_plugin' );

// --- DATA CLEARING ---
function esp_handle_clear_all_eve_data_for_user() { 
    if ( !is_user_logged_in() || !check_admin_referer('esp_clear_all_eve_data_action', 'esp_clear_all_eve_data_nonce')) { 
        wp_die('Security check failed or not logged in.'); 
    } 
    $user_id = get_current_user_id(); 
    $main_meta_to_delete = [
        'esp_main_eve_character_id', 'esp_main_eve_character_name', 'esp_main_access_token',
        'esp_main_refresh_token', 'esp_main_token_expires', 'esp_main_owner_hash',
        'esp_main_skills_data', 'esp_main_total_sp', 'esp_main_skills_last_updated',
        'esp_main_assets_data', 'esp_main_assets_last_updated'
    ];
    foreach ($main_meta_to_delete as $key) { delete_user_meta($user_id, $key); }
    delete_user_meta($user_id, 'esp_alt_characters'); 
    // Legacy data (from initial version if any user used it)
    $legacy_meta_to_delete = [
        'eve_character_id', 'eve_character_name', 'eve_access_token', 'eve_refresh_token', 
        'eve_token_expires', 'eve_skills_data', 'eve_total_sp', 'eve_owner_hash', 'eve_skills_last_updated'
    ];
    foreach ($legacy_meta_to_delete as $key) { delete_user_meta($user_id, $key); }
    $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : admin_url('admin.php?page=eve_skill_user_characters_page'); 
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'all_data_cleared', $redirect_back_url)); exit; 
}
add_action('admin_post_esp_clear_all_eve_data_for_user', 'esp_handle_clear_all_eve_data_for_user');

?>
