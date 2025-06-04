<?php
/**
 * Plugin Name: EVE Online Skill Viewer (Main/Alts & Admin Tools)
 * Description: Allows users to authenticate a main EVE character and link alts. Provides admin tools for character management.
 * Version: 0.1.10
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

define( 'EVE_SKILL_PLUGIN_VERSION', '0.1.10' ); 
define( 'EVE_SKILL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EVE_SKILL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ESP_SSO_SESSION_KEY', 'esp_sso_pending_data' ); 
define( 'ESP_REDIRECT_MESSAGE_QUERY_ARG', 'eve_sso_message' );
define( 'ESP_SSO_CALLBACK_ACTION_NAME', 'esp_sso_callback_action');

if (!defined('EVE_SKILL_PLUGIN_USER_AGENT')) { 
    define('EVE_SKILL_PLUGIN_USER_AGENT', 'WordPress EVE Skill Plugin/' . EVE_SKILL_PLUGIN_VERSION . ' (Site: ' . get_site_url() . ')'); 
}

function esp_start_session_if_needed() { if ( ! session_id() && ! headers_sent() ) { session_start(); } }
add_action( 'init', 'esp_start_session_if_needed', 1 ); 

function esp_display_sso_message( $message_key ) { 
    $message_key = sanitize_key($message_key); $class = 'notice eve-sso-message is-dismissible '; $text = '';
    switch($message_key) {
        case 'sso_success': $class .= 'notice-success'; $text = esc_html__( 'Main EVE character authenticated successfully!', 'eve-skill-plugin' ); if (isset($_GET['new_user']) && $_GET['new_user'] === 'true') { $text .= ' ' . esc_html__('A WordPress account has been created for you and you are now logged in.', 'eve-skill-plugin'); } break;
        case 'sso_alt_success': $class .= 'notice-success'; $text = esc_html__( 'Alt EVE character authenticated successfully!', 'eve-skill-plugin' ); break;
        case 'sso_skills_failed': $class .= 'notice-warning'; $text = esc_html__( 'EVE authentication was successful, but skills could not be fetched.', 'eve-skill-plugin' ); break;
        case 'all_data_cleared': $class .= 'notice-success'; $text = esc_html__( 'All your EVE Online data (main and alts) has been cleared from this site.', 'eve-skill-plugin' ); break;
        case 'alt_removed': $class .= 'notice-success'; $text = esc_html__( 'Alt character has been removed successfully by you.', 'eve-skill-plugin' ); break;
        case 'admin_alt_removed': $class .= 'notice-success'; $text = esc_html__( 'Alt character has been removed by administrator.', 'eve-skill-plugin' ); break;
        case 'admin_alt_promoted': $class .= 'notice-success'; $text = esc_html__( 'Alt character has been promoted to main by administrator.', 'eve-skill-plugin' ); break;
        case 'admin_alt_assigned_new_main': $class .= 'notice-success'; $text = esc_html__( 'Character has been successfully assigned as an alt to the new main user.', 'eve-skill-plugin' ); break;
        case 'admin_main_reassigned_as_alt': $class .= 'notice-success'; $text = esc_html__( 'Main character has been successfully reassigned as an alt to the target user.', 'eve-skill-plugin' ); break;
        case 'admin_alt_already_main': $class .= 'notice-warning'; $text = esc_html__( 'This character is already the main for this user.', 'eve-skill-plugin'); break;
        case 'admin_alt_not_found_for_promote': case 'admin_alt_not_found': case 'admin_assign_alt_not_found_orig': $class .= 'notice-error'; $text = esc_html__( 'Specified alt character not found for the original user.', 'eve-skill-plugin'); break;
        case 'admin_assign_failed_params': case 'admin_op_failed_params': $class .= 'notice-error'; $text = esc_html__( 'Administrator operation failed due to missing parameters.', 'eve-skill-plugin'); break;
        case 'admin_assign_same_user': $class .= 'notice-error'; $text = esc_html__( 'Cannot assign a character to the same user it already belongs to.', 'eve-skill-plugin'); break;
        case 'admin_assign_new_main_invalid': $class .= 'notice-error'; $text = esc_html__( 'The selected target user is invalid or does not have a main character defined.', 'eve-skill-plugin'); break;
        case 'admin_assign_alt_is_new_main': $class .= 'notice-error'; $text = esc_html__( 'The character to move is already the main character of the selected target user.', 'eve-skill-plugin'); break;
        case 'admin_assign_alt_already_exists_new': $class .= 'notice-error'; $text = esc_html__( 'The character to move is already linked as an alt to the selected target user.', 'eve-skill-plugin'); break;
        case 'admin_reassign_main_has_alts': $class .= 'notice-error'; $text = esc_html__( 'Cannot reassign this main character because it has alts. Please reassign its alts first.', 'eve-skill-plugin'); break;
        case 'admin_reassign_main_not_found': $class .= 'notice-error'; $text = esc_html__( 'The main character to reassign was not found for the original user.', 'eve-skill-plugin'); break;
        case 'sso_alt_is_main': $class .= 'notice-warning'; $text = esc_html__( 'This character is already set as your main. Cannot add as alt.', 'eve-skill-plugin'); break;
        case 'sso_state_mismatch': case 'sso_token_wp_error': case 'sso_token_eve_error': case 'sso_verify_wp_error': case 'sso_verify_eve_error': case 'sso_wp_user_error': case 'sso_internal_user_error': case 'sso_unknown_auth_type': $class .= 'notice-error'; $text = esc_html__( 'An EVE authentication error occurred. Please try again. (Error: ' . $message_key . ')', 'eve-skill-plugin' ); break;
        case 'sso_no_config': $class .= 'notice-error'; $text = esc_html__( 'EVE integration is not configured by the site administrator.', 'eve-skill-plugin' ); break;
        default: return; 
    } if ($text) { printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $text ); }
}

function esp_add_admin_menu() {
    add_menu_page( __( 'EVE Skills Settings', 'eve-skill-plugin' ), __( 'EVE Skills', 'eve-skill-plugin' ), 'edit_others_pages', 'eve_skill_plugin_settings', 'esp_render_settings_page', 'dashicons-id-alt');
//add_submenu_page( 'eve_skill_plugin_settings', __( 'My Linked EVE Characters', 'eve-skill-plugin' ), __( 'My Linked Characters', 'eve-skill-plugin' ), 'read', 'eve_skill_user_characters_page', 'esp_render_user_characters_page');
    add_submenu_page( 'eve_skill_plugin_settings', __( 'View All User EVE Skills', 'eve-skill-plugin' ), __( 'View All User Skills', 'eve-skill-plugin' ), 'manage_options', 'eve_view_all_user_skills', 'esp_render_view_all_user_skills_page');
}
add_action( 'admin_menu', 'esp_add_admin_menu' );

function esp_register_settings() { register_setting( 'esp_settings_group', 'esp_client_id' ); register_setting( 'esp_settings_group', 'esp_client_secret' ); register_setting( 'esp_settings_group', 'esp_scopes', ['default' => 'esi-skills.read_skills.v1 publicData']); }
add_action( 'admin_init', 'esp_register_settings' );

function esp_render_settings_page() { 
    if ( ! current_user_can( 'edit_others_pages' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'eve-skill-plugin' ) ); return; } ?>
    <div class="wrap"> <h1><?php esc_html_e( 'EVE Online Skill Viewer Settings', 'eve-skill-plugin' ); ?></h1> <form method="post" action="options.php"> <?php settings_fields( 'esp_settings_group' ); do_settings_sections( 'esp_settings_group' ); ?> <table class="form-table"> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Client ID', 'eve-skill-plugin' ); ?></th> <td><input type="text" name="esp_client_id" value="<?php echo esc_attr( get_option( 'esp_client_id' ) ); ?>" size="60" /></td> </tr> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Secret Key', 'eve-skill-plugin' ); ?></th> <td><input type="password" name="esp_client_secret" value="<?php echo esc_attr( get_option( 'esp_client_secret' ) ); ?>" size="60" /></td> </tr> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Scopes', 'eve-skill-plugin' ); ?></th> <td> <input type="text" name="esp_scopes" value="<?php echo esc_attr( get_option( 'esp_scopes', 'esi-skills.read_skills.v1 publicData' ) ); ?>" size="60" /> <p class="description"><?php esc_html_e( 'Space separated. Default: esi-skills.read_skills.v1 publicData', 'eve-skill-plugin' ); ?></p> </td> </tr> </table> <?php submit_button(); ?> </form> <hr/> <h2><?php esc_html_e( 'Callback URL for EVE Application', 'eve-skill-plugin' ); ?></h2> <p><?php esc_html_e( 'Use this URL as the "Callback URL" or "Redirect URI" in your EVE Online application settings:', 'eve-skill-plugin' ); ?></p> <code><?php echo esc_url( admin_url( 'admin-post.php?action=' . ESP_SSO_CALLBACK_ACTION_NAME ) ); ?></code> <hr/> <h2><?php esc_html_e( 'Shortcode for Login Button', 'eve-skill-plugin' ); ?></h2> <p><?php esc_html_e( 'To place an EVE Online login button on any page or post, use the following shortcode:', 'eve-skill-plugin' ); ?></p> <code>[eve_sso_login_button]</code> <p><?php esc_html_e( 'You can customize the button text like this:', 'eve-skill-plugin'); ?> <code>[eve_sso_login_button text="Link Your EVE Character"]</code></p> </div> <?php
}

function esp_render_user_characters_page() {
    $current_user_id = get_current_user_id(); 
    if (!$current_user_id) { echo "<p>" . __("Please log in to view this page.", "eve-skill-plugin") . "</p>"; return; }
    $main_char_id = get_user_meta($current_user_id, 'esp_main_eve_character_id', true);
    $client_id = get_option('esp_client_id');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'My Linked EVE Characters', 'eve-skill-plugin' ); ?></h1>
        <?php if ( isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { esp_display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); } ?>
        <?php if ( ! $client_id ) : ?> <p style="color:red;"><?php esc_html_e( 'EVE Application Client ID is not configured.', 'eve-skill-plugin' ); ?></p> <?php return; endif; ?>

        <?php if ( $main_char_id ) : 
            $main_char_name = get_user_meta($current_user_id, 'esp_main_eve_character_name', true);
        ?>
            <h3><?php esc_html_e( 'Main Character', 'eve-skill-plugin' ); ?></h3>
            <p>
                <?php printf( esc_html__( '%s (ID: %s)', 'eve-skill-plugin' ), esc_html( $main_char_name ), esc_html( $main_char_id ) ); ?>
                 - <a href="<?php echo esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $current_user_id, 'view_char_id' => $main_char_id], admin_url('admin.php'))); ?>"><?php esc_html_e('View Skills', 'eve-skill-plugin'); ?></a>
            </p>
            <?php $current_admin_page_url = admin_url( 'admin.php?page=eve_skill_user_characters_page' ); ?>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block; margin-right: 10px;"> <input type="hidden" name="action" value="esp_initiate_sso"> <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?> <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"> <input type="hidden" name="esp_auth_type" value="main"> <?php submit_button( __( 'Re-Auth/Switch Main', 'eve-skill-plugin' ), 'secondary', 'submit', false ); ?> </form>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block;"> <input type="hidden" name="action" value="esp_initiate_sso"> <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?> <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"> <input type="hidden" name="esp_auth_type" value="alt"> <?php submit_button( __( 'Authenticate Alt Character', 'eve-skill-plugin' ), 'primary', 'submit', false ); ?> </form>
            <h3><?php esc_html_e( 'Alt Characters', 'eve-skill-plugin' ); ?></h3>
            <?php
            $alt_characters = get_user_meta($current_user_id, 'esp_alt_characters', true);
            if (is_array($alt_characters) && !empty($alt_characters)) {
                echo '<ul>';
                foreach ($alt_characters as $alt_char) {
                    if (!is_array($alt_char) || !isset($alt_char['id']) || !isset($alt_char['name'])) continue;
                    echo '<li>'; printf(esc_html__('%s (ID: %s)', 'eve-skill-plugin'), esc_html($alt_char['name']), esc_html($alt_char['id']));
                    echo ' - <a href="'. esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $current_user_id, 'view_char_id' => $alt_char['id']], admin_url('admin.php'))) .'">'. esc_html__('View Skills', 'eve-skill-plugin') .'</a>';
                    echo ' <form method="post" action="'. esc_url( admin_url('admin-post.php') ) .'" style="display:inline-block; margin-left:10px;">'; echo '<input type="hidden" name="action" value="esp_remove_alt_character">'; echo '<input type="hidden" name="esp_alt_char_id_to_remove" value="'. esc_attr($alt_char['id']) .'">'; echo '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url($current_admin_page_url) . '">'; wp_nonce_field('esp_remove_alt_action_' . $alt_char['id'], 'esp_remove_alt_nonce'); submit_button( __( 'Remove Alt', 'eve-skill-plugin' ), 'delete small', 'submit', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove this alt character?', 'eve-skill-plugin')).'");'] ); echo '</form>';
                    echo '</li>';
                } echo '</ul>';
            } else { echo '<p>' . esc_html__('No alt characters linked yet.', 'eve-skill-plugin') . '</p>'; } ?>
            <hr style="margin: 20px 0;">
             <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"> <input type="hidden" name="action" value="esp_clear_all_eve_data_for_user"> <?php wp_nonce_field( 'esp_clear_all_eve_data_action', 'esp_clear_all_eve_data_nonce' ); ?> <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"> <?php submit_button( __( 'Clear All My EVE Data (Main & Alts)', 'eve-skill-plugin' ), 'delete', 'submit', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove ALL EVE data, including main and all alts?', 'eve-skill-plugin')).'");'] ); ?> </form>
        <?php else : ?>
            <p><?php esc_html_e( 'You have not linked your main EVE Online character yet.', 'eve-skill-plugin' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"> <input type="hidden" name="action" value="esp_initiate_sso"> <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?> <?php $current_admin_page_url = admin_url( 'admin.php?page=eve_skill_user_characters_page' );?> <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"> <input type="hidden" name="esp_auth_type" value="main"> <?php submit_button( __( 'Link Your Main EVE Character', 'eve-skill-plugin' ) ); ?> </form>
        <?php endif; ?>
    </div>
    <?php
}

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
    if (!$alt_char_id_to_remove || !check_admin_referer('esp_remove_alt_action_' . $alt_char_id_to_remove, 'esp_remove_alt_nonce')) { wp_die('Invalid request or security check failed.'); }
    $removed = esp_handle_remove_alt_character_base($user_id, $alt_char_id_to_remove);
    $message = $removed ? 'alt_removed' : 'alt_not_found';
    $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : admin_url('admin.php?page=eve_skill_user_characters_page');
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $message, $redirect_back_url)); exit;
}
add_action('admin_post_esp_remove_alt_character', 'esp_handle_remove_alt_character');

function esp_handle_admin_remove_user_alt_character() {
    if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_remove_alt_action', 'esp_admin_remove_alt_nonce')) { wp_die('Security check failed or insufficient permissions.'); }
    $user_id_to_affect = isset($_POST['user_id_to_affect']) ? intval($_POST['user_id_to_affect']) : 0;
    $alt_char_id_to_remove = isset($_POST['alt_char_id_to_remove']) ? intval($_POST['alt_char_id_to_remove']) : 0;
    $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id_to_affect);
    if (!$user_id_to_affect || !$alt_char_id_to_remove) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); exit; }
    $removed = esp_handle_remove_alt_character_base($user_id_to_affect, $alt_char_id_to_remove);
    $message = $removed ? 'admin_alt_removed' : 'admin_alt_not_found';
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $message, $redirect_back_url)); exit;
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
        ];
        if (!is_array($old_main_data_as_alt['skills_data'])) { $old_main_data_as_alt['skills_data'] = []; }
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
    if (empty($remaining_alts)) { delete_user_meta($user_id_to_affect, 'esp_alt_characters');
    } else { update_user_meta($user_id_to_affect, 'esp_alt_characters', $remaining_alts); }
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_alt_promoted', $redirect_back_url)); 
    exit;
}
add_action('admin_post_esp_admin_promote_alt_to_main', 'esp_handle_admin_promote_alt_to_main');

/**
 * NEW: Admin action to assign a character (main or alt) to a different WP user as an alt
 */
function esp_handle_admin_reassign_character() {
    if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce')) {
        wp_die(esc_html__('Security check failed or insufficient permissions.', 'eve-skill-plugin'));
    }

    $original_wp_user_id = isset($_POST['original_wp_user_id']) ? intval($_POST['original_wp_user_id']) : 0;
    $character_id_to_move = isset($_POST['character_id_to_move']) ? intval($_POST['character_id_to_move']) : 0;
    $character_type_to_move = isset($_POST['character_type_to_move']) ? sanitize_key($_POST['character_type_to_move']) : ''; // 'main' or 'alt'
    $new_main_wp_user_id = isset($_POST['new_main_wp_user_id']) ? intval($_POST['new_main_wp_user_id']) : 0;

    $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $original_wp_user_id);

    if (!$original_wp_user_id || !$character_id_to_move || !$new_main_wp_user_id || !in_array($character_type_to_move, ['main', 'alt'])) {
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_failed_params', $redirect_back_url));
        exit;
    }
    if ($original_wp_user_id === $new_main_wp_user_id) {
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_same_user', $redirect_back_url));
        exit;
    }

    // Validate target user and their main character
    $new_main_user_info = get_userdata($new_main_wp_user_id);
    $new_main_user_main_char_id = $new_main_user_info ? get_user_meta($new_main_wp_user_id, 'esp_main_eve_character_id', true) : null;
    if (!$new_main_user_info || !$new_main_user_main_char_id) {
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_new_main_invalid', $redirect_back_url));
        exit;
    }
    if ($character_id_to_move == $new_main_user_main_char_id) { // Cannot assign a char to be an alt of itself
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_is_new_main', $redirect_back_url));
        exit;
    }
    $new_main_user_alts = get_user_meta($new_main_wp_user_id, 'esp_alt_characters', true);
    if (!is_array($new_main_user_alts)) $new_main_user_alts = [];
    foreach ($new_main_user_alts as $existing_alt) {
        if (isset($existing_alt['id']) && $existing_alt['id'] == $character_id_to_move) {
            wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_already_exists_new', $redirect_back_url));
            exit;
        }
    }

    $moved_char_data_obj = null;

    if ($character_type_to_move === 'main') {
        $original_main_id = get_user_meta($original_wp_user_id, 'esp_main_eve_character_id', true);
        if ($original_main_id != $character_id_to_move) { // Mismatch
            wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_reassign_main_not_found', $redirect_back_url)); exit;
        }
        // Check if this main has alts - if so, disallow this specific action
        $original_user_alts = get_user_meta($original_wp_user_id, 'esp_alt_characters', true);
        if (!empty($original_user_alts) && is_array($original_user_alts)) {
             wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_reassign_main_has_alts', $redirect_back_url)); exit;
        }

        // Extract main character data
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
        ];
        if (!is_array($moved_char_data_obj['skills_data'])) $moved_char_data_obj['skills_data'] = [];

        // Clear main character data from original user
        delete_user_meta($original_wp_user_id, 'esp_main_eve_character_id');
        delete_user_meta($original_wp_user_id, 'esp_main_eve_character_name');
        delete_user_meta($original_wp_user_id, 'esp_main_access_token');
        delete_user_meta($original_wp_user_id, 'esp_main_refresh_token');
        delete_user_meta($original_wp_user_id, 'esp_main_token_expires');
        delete_user_meta($original_wp_user_id, 'esp_main_owner_hash');
        delete_user_meta($original_wp_user_id, 'esp_main_skills_data');
        delete_user_meta($original_wp_user_id, 'esp_main_total_sp');
        delete_user_meta($original_wp_user_id, 'esp_main_skills_last_updated');

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

    if (!$moved_char_data_obj) { // Should be caught by earlier checks
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); exit;
    }
    
    // Add the character as an alt to the new main user
    $new_main_user_alts[] = $moved_char_data_obj;
    update_user_meta($new_main_wp_user_id, 'esp_alt_characters', $new_main_user_alts);

    $success_message = ($character_type_to_move === 'main') ? 'admin_main_reassigned_as_alt' : 'admin_alt_assigned_new_main';
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $success_message, $redirect_back_url));
    exit;
}
add_action('admin_post_esp_admin_reassign_character', 'esp_handle_admin_reassign_character');


function esp_render_view_all_user_skills_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions.', 'eve-skill-plugin' ) ); }
    ?>
    <div class="wrap esp-admin-view">
        <h1><?php esc_html_e( 'View User EVE Skills', 'eve-skill-plugin' ); ?></h1>
        <style> .esp-admin-view .char-tree { list-style-type: none; padding-left: 0; } .esp-admin-view .char-tree ul { list-style-type: none; padding-left: 20px; margin-left: 10px; border-left: 1px dashed #ccc; } .esp-admin-view .char-item { padding: 5px 0; } .esp-admin-view .char-item strong { font-size: 1.1em; } .esp-admin-view .char-meta { font-size: 0.9em; color: #555; margin-left: 10px; } .esp-admin-view .char-actions a, .esp-admin-view .char-actions form { margin-left: 10px; display: inline-block; vertical-align: middle;} .esp-admin-view .main-char-item { border: 1px solid #0073aa; padding: 10px; margin-bottom:15px; background: #f7fcfe; } .esp-admin-view .alt-list-heading { margin-top: 15px; font-weight: bold; } .esp-admin-view .skill-table-container { margin-top: 20px; } .esp-admin-view .admin-action-button { padding: 2px 5px !important; font-size: 0.8em !important; line-height: 1.2 !important; height: auto !important; min-height: 0 !important;} .esp-admin-view .assign-alt-form select {vertical-align: baseline; margin: 0 5px;} </style>
        <?php
         if ( isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { esp_display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); }
        $selected_user_id = isset( $_GET['view_user_id'] ) ? intval( $_GET['view_user_id'] ) : 0;
        $selected_char_id_to_view_skills = isset( $_GET['view_char_id'] ) ? intval( $_GET['view_char_id'] ) : 0;

        if ( $selected_user_id > 0 ) { 
            $user_info = get_userdata( $selected_user_id );
            if ( ! $user_info ) { echo '<p>' . esc_html__( 'WordPress user not found.', 'eve-skill-plugin' ) . '</p>'; echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eve_view_all_user_skills' ) ) . '">« ' . esc_html__( 'Back to all users list', 'eve-skill-plugin' ) . '</a></p>'; echo '</div>'; return; }
            echo '<h2>' . sprintf( esc_html__( 'EVE Characters for: %s', 'eve-skill-plugin' ), esc_html( $user_info->display_name ) ) . ' (' . esc_html($user_info->user_login) . ')</h2>';
            if ( $selected_char_id_to_view_skills > 0 ) { 
                $is_main_view = ($selected_char_id_to_view_skills == get_user_meta( $selected_user_id, 'esp_main_eve_character_id', true ));
                $char_name_to_display = $is_main_view ? get_user_meta($selected_user_id, 'esp_main_eve_character_name', true) : esp_get_alt_character_data_item($selected_user_id, $selected_char_id_to_view_skills, 'name');
                echo '<h3>' . sprintf( esc_html__( 'Skills for %s (ID: %s)', 'eve-skill-plugin' ), esc_html( $char_name_to_display ), esc_html($selected_char_id_to_view_skills) ) . '</h3>'; echo '<div class="skill-table-container">'; esp_display_character_skills_for_admin( $selected_user_id, $selected_char_id_to_view_skills ); echo '</div>';
                echo '<p><a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id], admin_url('admin.php'))) . '">« ' . esc_html__( 'Back to character list for this user', 'eve-skill-plugin' ) . '</a></p>';
            } else { 
                $main_char_id = get_user_meta( $selected_user_id, 'esp_main_eve_character_id', true );
                $alt_characters = get_user_meta($selected_user_id, 'esp_alt_characters', true);
                if (!is_array($alt_characters)) $alt_characters = []; // Ensure it's an array for the check below

                echo '<ul class="char-tree">';
                if ( $main_char_id ) {
                    $main_char_name = get_user_meta( $selected_user_id, 'esp_main_eve_character_name', true ); $main_total_sp = get_user_meta( $selected_user_id, 'esp_main_total_sp', true ); $main_last_updated = get_user_meta( $selected_user_id, 'esp_main_skills_last_updated', true );
                    echo '<li class="char-item main-char-item">'; echo '<strong>MAIN:</strong> ' . esc_html( $main_char_name ) . ' (ID: ' . esc_html( $main_char_id ) . ')';
                    echo '<div class="char-meta">'; echo 'Total SP: ' . esc_html( number_format( (float) $main_total_sp ) ); if ($main_last_updated) echo ' | Last Updated: ' . esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$main_last_updated)); echo '</div>';
                    echo '<div class="char-actions"><a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id, 'view_char_id' => $main_char_id], admin_url('admin.php'))) . '">' . esc_html__('View Skills', 'eve-skill-plugin') . '</a>';
                    // Form to reassign this "solo" main character if it has no alts
                    if (current_user_can('manage_options') && empty($alt_characters)) {
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
                     echo '</div>'; // End char-actions for main

                    if (is_array($alt_characters) && !empty($alt_characters)) {
                        echo '<div class="alt-list-heading">' . esc_html__('ALTS:', 'eve-skill-plugin') . '</div>'; echo '<ul>'; 
                        foreach ($alt_characters as $alt_char_idx => $alt_char) {
                             if (!is_array($alt_char) || !isset($alt_char['id']) || !isset($alt_char['name'])) continue; 
                            echo '<li class="char-item">'; echo esc_html( $alt_char['name'] ) . ' (ID: ' . esc_html( $alt_char['id'] ) . ')';
                            echo '<div class="char-meta">'; echo 'Total SP: ' . esc_html( number_format( (float) ($alt_char['total_sp'] ?? 0) ) ); if (!empty($alt_char['skills_last_updated'])) echo ' | Last Updated: ' . esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$alt_char['skills_last_updated'])); echo '</div>';
                            echo '<div class="char-actions">';
                            echo '<a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id, 'view_char_id' => $alt_char['id']], admin_url('admin.php'))) . '">' . esc_html__('View Skills', 'eve-skill-plugin') . '</a>';
                            if (current_user_can('manage_options')) {
                                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; echo '<input type="hidden" name="action" value="esp_admin_promote_alt_to_main">'; echo '<input type="hidden" name="user_id_to_affect" value="'.esc_attr($selected_user_id).'">'; echo '<input type="hidden" name="alt_char_id_to_promote" value="'.esc_attr($alt_char['id']).'">'; wp_nonce_field('esp_admin_promote_alt_action', 'esp_admin_promote_alt_nonce'); submit_button(__('Promote to Main', 'eve-skill-plugin'), 'secondary small admin-action-button', 'promote_alt', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to promote this alt to main? The current main will become an alt.', 'eve-skill-plugin')).'");']); echo '</form>';
                                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; echo '<input type="hidden" name="action" value="esp_admin_remove_user_alt_character">'; echo '<input type="hidden" name="user_id_to_affect" value="'.esc_attr($selected_user_id).'">'; echo '<input type="hidden" name="alt_char_id_to_remove" value="'.esc_attr($alt_char['id']).'">'; wp_nonce_field('esp_admin_remove_alt_action', 'esp_admin_remove_alt_nonce'); submit_button(__('Remove Alt', 'eve-skill-plugin'), 'delete small admin-action-button', 'remove_alt', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove this alt from this user?', 'eve-skill-plugin')).'");']); echo '</form>';
                                
                                // Assign Alt to different Main form (for Alts)
                                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="assign-alt-form">';
                                echo '<input type="hidden" name="action" value="esp_admin_reassign_character">'; // Use the new general action
                                echo '<input type="hidden" name="original_wp_user_id" value="'.esc_attr($selected_user_id).'">';
                                echo '<input type="hidden" name="character_id_to_move" value="'.esc_attr($alt_char['id']).'">';
                                echo '<input type="hidden" name="character_type_to_move" value="alt">'; // Indicate this is an alt being moved
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
                                submit_button(__('Assign Alt', 'eve-skill-plugin'), 'secondary small admin-action-button', 'reassign_alt', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to re-assign this alt to the selected main character\'s account?', 'eve-skill-plugin')).'");']);
                                echo '</form>';

                            } echo '</div>'; echo '</li>';
                        } echo '</ul>'; 
                    } else { echo '<p class="char-meta">' . esc_html__('No alt characters linked.', 'eve-skill-plugin') . '</p>'; }
                    echo '</li>'; 
                } else { echo '<li>' . esc_html__( 'No main EVE character linked for this user.', 'eve-skill-plugin' ) . '</li>'; }
                echo '</ul>'; 
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eve_view_all_user_skills' ) ) . '">« ' . esc_html__( 'Back to all users list', 'eve-skill-plugin' ) . '</a></p>';
            }
        } else { 
            $args = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all', 'orderby' => 'display_name', ]; $users_with_main_eve = get_users( $args );
            if ( ! empty( $users_with_main_eve ) ) {
                echo '<table class="wp-list-table widefat fixed striped">'; echo '<thead><tr><th>' . esc_html__( 'WordPress User', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Main EVE Character', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Alts Count', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Action', 'eve-skill-plugin' ) . '</th></tr></thead>'; echo '<tbody>';
                foreach ( $users_with_main_eve as $user ) {
                    $main_char_name = get_user_meta( $user->ID, 'esp_main_eve_character_name', true ); $alts = get_user_meta($user->ID, 'esp_alt_characters', true); $alts_count = is_array($alts) ? count($alts) : 0; $view_link = add_query_arg( ['page' => 'eve_view_all_user_skills', 'view_user_id' => $user->ID ], admin_url( 'admin.php' ) );
                    echo '<tr>'; echo '<td>' . esc_html( $user->display_name ) . ' (' . esc_html($user->user_login) . ')</td>'; echo '<td>' . esc_html( $main_char_name ) . '</td>'; echo '<td>' . esc_html( $alts_count ) . '</td>'; echo '<td><a href="' . esc_url( $view_link ) . '">' . esc_html__( 'View Details', 'eve-skill-plugin' ) . '</a></td>'; echo '</tr>';
                } echo '</tbody></table>';
            } else { echo '<p>' . esc_html__( 'No users have linked their main EVE character yet.', 'eve-skill-plugin' ) . '</p>'; }
        } ?>
    </div> <?php
}

function esp_display_character_skills_for_admin( $user_id_to_view, $character_id_to_display ) {
    $main_char_id = get_user_meta($user_id_to_view, 'esp_main_eve_character_id', true);
    $is_main = ($character_id_to_display == $main_char_id);
    $skills_data  = null; $total_sp = 0; $last_updated = null;

    if ($is_main) {
        $skills_data  = get_user_meta( $user_id_to_view, 'esp_main_skills_data', true );
        $total_sp     = get_user_meta( $user_id_to_view, 'esp_main_total_sp', true );
        $last_updated = get_user_meta( $user_id_to_view, 'esp_main_skills_last_updated', true);
    } else { 
        $skills_data  = esp_get_alt_character_data_item($user_id_to_view, $character_id_to_display, 'skills_data');
        $total_sp     = esp_get_alt_character_data_item($user_id_to_view, $character_id_to_display, 'total_sp');
        $last_updated = esp_get_alt_character_data_item($user_id_to_view, $character_id_to_display, 'skills_last_updated');
    }
    if ($last_updated) { echo '<p><small>' . sprintf(esc_html__('Skills last updated: %s', 'eve-skill-plugin'), esc_html(wp_date( get_option('date_format') . ' ' . get_option('time_format'), (int)$last_updated))) . '</small></p>';
    } else { echo '<p><small>' . esc_html__('Skills last updated: Unknown', 'eve-skill-plugin') . '</small></p>'; }

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
             echo '<tr><td colspan="4">' . esc_html__('Skill data appears to be malformed or empty.', 'eve-skill-plugin') . '</td></tr>';
        } else if (!is_array($skills_data)) { 
             echo '<tr><td colspan="4">' . esc_html__('Skill data is not in the expected format.', 'eve-skill-plugin') . '</td></tr>';
        }
        echo '</tbody></table>';
    } else { 
        echo '<p>' . esc_html__( 'No skill data found for this character, or skills have not been fetched/stored correctly.', 'eve-skill-plugin' ) . '</p>';
        if (is_array($skills_data) && empty($skills_data)) { echo '<p><small>' . esc_html__('(The skill list from ESI was empty).', 'eve-skill-plugin') . '</small></p>'; }
    }
}

function esp_get_alt_character_data_item($user_id, $alt_char_id, $item_key) {
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
    if (is_array($alt_characters)) { foreach ($alt_characters as $alt) { if (isset($alt['id']) && $alt['id'] == $alt_char_id) { return isset($alt[$item_key]) ? $alt[$item_key] : null; } } } return null;
}

function esp_update_alt_character_data_item($user_id, $alt_char_id, $item_key, $item_value, $char_name = null, $owner_hash = null) {
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); if (!is_array($alt_characters)) { $alt_characters = []; }
    $found_alt_index = -1;
    foreach ($alt_characters as $index => $alt) { if (isset($alt['id']) && $alt['id'] == $alt_char_id) { $found_alt_index = $index; break; } }
    if ($found_alt_index !== -1) { 
        $alt_characters[$found_alt_index][$item_key] = $item_value;
        if ($char_name && empty($alt_characters[$found_alt_index]['name'])) $alt_characters[$found_alt_index]['name'] = $char_name;
        if ($owner_hash && empty($alt_characters[$found_alt_index]['owner_hash'])) $alt_characters[$found_alt_index]['owner_hash'] = $owner_hash;
    } else { 
        $new_alt = ['id' => $alt_char_id]; if ($char_name) $new_alt['name'] = $char_name; if ($owner_hash) $new_alt['owner_hash'] = $owner_hash;
        $new_alt[$item_key] = $item_value; $alt_characters[] = $new_alt;
    } update_user_meta($user_id, 'esp_alt_characters', $alt_characters);
}

function esp_get_skill_name( $skill_id ) { $skill_id = intval($skill_id); $transient_key = 'esp_skill_name_' . $skill_id; $skill_name = get_transient( $transient_key ); if ( false === $skill_name ) { $response = wp_remote_get( "https://esi.evetech.net/latest/universe/types/{$skill_id}/?datasource=tranquility", ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT]] ); if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) { $type_data = json_decode( wp_remote_retrieve_body( $response ), true ); if ( isset( $type_data['name'] ) ) { $skill_name = $type_data['name']; set_transient( $transient_key, $skill_name, DAY_IN_SECONDS * 30 ); } else { $skill_name = "Unknown Skill (ID: {$skill_id})"; } } else { $skill_name = "Skill ID: {$skill_id} (Lookup Failed)"; } } return $skill_name; }

function esp_handle_sso_initiation() {
    if ( ! isset( $_POST['esp_initiate_sso_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['esp_initiate_sso_nonce']), 'esp_initiate_sso_action' ) ) { wp_die( 'Nonce verification failed!' ); }
    esp_start_session_if_needed(); $client_id = get_option( 'esp_client_id' ); $scopes = get_option( 'esp_scopes', 'esi-skills.read_skills.v1 publicData' );
    if ( ! $client_id ) { wp_die( 'EVE Client ID not configured.' ); }
    $auth_type = isset($_POST['esp_auth_type']) ? sanitize_key($_POST['esp_auth_type']) : 'main'; 
    $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : home_url();
    $sso_state_value = bin2hex( random_bytes( 16 ) ); $_SESSION[ESP_SSO_SESSION_KEY] = [ 'nonce' => $sso_state_value, 'redirect_url' => $redirect_back_url, 'auth_type' => $auth_type ];
    $sso_redirect_uri_to_eve = admin_url( 'admin-post.php?action=' . ESP_SSO_CALLBACK_ACTION_NAME ); 
    $sso_url_to_eve = 'https://login.eveonline.com/v2/oauth/authorize/?' . http_build_query( [ 'response_type' => 'code', 'redirect_uri'  => $sso_redirect_uri_to_eve, 'client_id' => $client_id, 'scope' => $scopes, 'state' => $sso_state_value, ] );
    wp_redirect( $sso_url_to_eve ); exit;
}
add_action( 'admin_post_esp_initiate_sso', 'esp_handle_sso_initiation' ); 
add_action( 'admin_post_nopriv_esp_initiate_sso', 'esp_handle_sso_initiation' ); 

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
    if ($auth_type === 'main' || !$current_main_char_id) { 
        if ($current_main_char_id && $current_main_char_id != $authed_character_id) { error_log("[ESP] User $user_id switching main EVE char from $current_main_char_id to $authed_character_id");}
        update_user_meta( $user_id, 'esp_main_eve_character_id', $authed_character_id ); update_user_meta( $user_id, 'esp_main_eve_character_name', $authed_character_name ); update_user_meta( $user_id, 'esp_main_access_token', $access_token ); update_user_meta( $user_id, 'esp_main_refresh_token', $refresh_token ); update_user_meta( $user_id, 'esp_main_token_expires', time() + $expires_in ); update_user_meta( $user_id, 'esp_main_owner_hash', $authed_owner_hash );
        $skills_fetched = esp_fetch_and_store_skills_for_character_type( $user_id, $authed_character_id, $access_token, 'main' );
    } elseif ($auth_type === 'alt') {
        if ($authed_character_id == $current_main_char_id) { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_alt_is_main', $redirect_url_after_sso ) ); exit; }
        $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); if (!is_array($alt_characters)) $alt_characters = [];
        $alt_exists_idx = -1; foreach ($alt_characters as $idx => $alt) { if (isset($alt['id']) && $alt['id'] == $authed_character_id) { $alt_exists_idx = $idx; break; } }
        $alt_data = [ 'id' => $authed_character_id, 'name' => $authed_character_name, 'owner_hash' => $authed_owner_hash, 'access_token' => $access_token, 'refresh_token' => $refresh_token, 'token_expires' => time() + $expires_in, ];
        if ($alt_exists_idx !== -1) { $alt_characters[$alt_exists_idx] = array_merge($alt_characters[$alt_exists_idx], $alt_data); } else { $alt_characters[] = $alt_data; }
        update_user_meta($user_id, 'esp_alt_characters', $alt_characters);
        $skills_fetched = esp_fetch_and_store_skills_for_character_type( $user_id, $authed_character_id, $access_token, 'alt' );
    } else { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_unknown_auth_type', $redirect_url_after_sso ) ); exit; }
    $final_redirect_url = $redirect_url_after_sso;
    if ($skills_fetched) { $message_key = ($auth_type === 'alt') ? 'sso_alt_success' : 'sso_success'; if ($is_new_wp_user) $final_redirect_url = add_query_arg('new_user', 'true', $final_redirect_url); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, $message_key, $final_redirect_url ) );
    } else { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_skills_failed', $final_redirect_url ) ); }
    exit;
}
add_action( 'admin_post_nopriv_' . ESP_SSO_CALLBACK_ACTION_NAME, 'esp_handle_sso_callback' );
add_action( 'admin_post_' . ESP_SSO_CALLBACK_ACTION_NAME, 'esp_handle_sso_callback' );

function esp_get_or_create_wp_user_for_eve_char( $character_id, $character_name, $owner_hash ) { $existing_users = get_users( [ 'meta_key' => 'esp_main_eve_character_id', 'meta_value' => $character_id, 'number' => 1, 'count_total' => false ]); if ( ! empty( $existing_users ) ) { return $existing_users[0]; } $alt_users = get_users([ 'meta_query' => [ [ 'key' => 'esp_alt_characters', 'value' => '"id";i:'.$character_id.';', 'compare' => 'LIKE' ] ], 'number' => 1, 'count_total' => false ]); if (!empty($alt_users)) { return $alt_users[0]; } $username = sanitize_user( 'eve_' . $character_id . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $character_name), true ); $i = 0; $base_username = $username; while ( username_exists( $username ) ) { $i++; $username = $base_username . '_' . $i; } $random_password = wp_generate_password( 20, true, true ); $email_domain_part = sanitize_title(str_replace(['http://', 'https://', 'www.'], '', get_bloginfo('url'))); if (empty($email_domain_part)) $email_domain_part = 'localhost'; $email = $character_id . '@' . $email_domain_part . '.eve-sso.invalid'; $new_user_data = [ 'user_login' => $username, 'user_pass'  => $random_password, 'user_email' => $email, 'display_name' => $character_name, 'role' => 'subscriber' ]; $new_user_id = wp_insert_user( $new_user_data ); if ( is_wp_error( $new_user_id ) ) { return $new_user_id; } $current_time = time(); update_user_meta( $new_user_id, 'created_via_eve_sso', $current_time ); error_log("[ESP] Created new WP User ID $new_user_id ($username) for EVE Char $character_id ($character_name)"); return get_user_by( 'id', $new_user_id ); }

function esp_fetch_and_store_skills_for_character_type( $user_id, $character_id, $access_token, $char_type = 'main' ) { if ( ! $user_id || ! $character_id || ! $access_token) return false; $skills_url = "https://esi.evetech.net/latest/characters/{$character_id}/skills/?datasource=tranquility"; $skills_response = wp_remote_get( $skills_url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $access_token, 'Accept' => 'application/json', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, ], 'timeout' => 20, ]); if ( is_wp_error( $skills_response ) || wp_remote_retrieve_response_code( $skills_response ) !== 200 ) { error_log("[ESP] Skills fetch error for char $character_id: " . (is_wp_error($skills_response) ? $skills_response->get_error_message() : wp_remote_retrieve_response_code($skills_response))); return false; } $skills_body = wp_remote_retrieve_body( $skills_response ); $skills_data_esi = json_decode( $skills_body, true ); if ( ! is_array($skills_data_esi) || ! isset( $skills_data_esi['skills'] ) || ! isset( $skills_data_esi['total_sp'] ) ) { error_log("[ESP] Skills JSON error for char $character_id"); return false; } $skills_list = $skills_data_esi['skills']; $total_sp_value = (float) $skills_data_esi['total_sp']; $current_time = time(); if ($char_type === 'main') { update_user_meta( $user_id, 'esp_main_skills_data', $skills_list ); update_user_meta( $user_id, 'esp_main_total_sp', $total_sp_value ); update_user_meta( $user_id, 'esp_main_skills_last_updated', $current_time ); } elseif ($char_type === 'alt') { $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); if (!is_array($alt_characters)) $alt_characters = []; $found_alt_idx = -1; foreach($alt_characters as $idx => $alt) { if (isset($alt['id']) && $alt['id'] == $character_id) { $found_alt_idx = $idx; break; } } if ($found_alt_idx !== -1) { $alt_characters[$found_alt_idx]['skills_data'] = $skills_list; $alt_characters[$found_alt_idx]['total_sp'] = $total_sp_value; $alt_characters[$found_alt_idx]['skills_last_updated'] = $current_time; update_user_meta($user_id, 'esp_alt_characters', $alt_characters); } else { error_log("[ESP] fetch_skills: Alt char ID $character_id not found for user $user_id."); return false; } } return true; }

function esp_refresh_eve_token_for_character_type( $user_id, $character_id, $char_type = 'main' ) { if (!$user_id || !$character_id) return false; $refresh_token_value = ''; if ($char_type === 'main') { $refresh_token_value = get_user_meta( $user_id, 'esp_main_refresh_token', true ); } elseif ($char_type === 'alt') { $refresh_token_value = esp_get_alt_character_data_item($user_id, $character_id, 'refresh_token'); } if ( ! $refresh_token_value ) return false; $client_id = get_option( 'esp_client_id' ); $client_secret = get_option( 'esp_client_secret' ); if ( ! $client_id || ! $client_secret ) { error_log('[ESP] Token Refresh: Client ID/Secret not set.'); return false; } $auth_header = base64_encode( $client_id . ':' . $client_secret ); $token_url = 'https://login.eveonline.com/v2/oauth/token'; $response = wp_remote_post( $token_url, [ 'headers' => [ 'Authorization' => 'Basic ' . $auth_header, 'Content-Type'  => 'application/x-www-form-urlencoded', 'Host' => 'login.eveonline.com', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, ], 'body' => [ 'grant_type' => 'refresh_token', 'refresh_token' => $refresh_token_value, ], 'timeout' => 20, ]); if ( is_wp_error( $response ) ) { error_log("[ESP] Token Refresh WP Error for $char_type CharID $character_id: " . $response->get_error_message()); return false; } $body = wp_remote_retrieve_body( $response ); $token_data = json_decode( $body, true ); $response_code = wp_remote_retrieve_response_code( $response ); if ( $response_code !== 200 || ! isset( $token_data['access_token'] ) ) { error_log("[ESP] Token Refresh Failed for $char_type CharID $character_id (User $user_id). HTTP: $response_code. EVE: $body"); if (strpos($body, 'invalid_token') !== false || strpos($body, 'invalid_grant') !== false || $response_code === 400) { esp_clear_specific_character_tokens($user_id, $character_id, $char_type); } return false; } $new_access_token = sanitize_text_field( $token_data['access_token'] ); $new_refresh_token = isset($token_data['refresh_token']) ? sanitize_text_field($token_data['refresh_token']) : $refresh_token_value; $new_expires_in = intval( $token_data['expires_in'] ); if ($char_type === 'main') { update_user_meta( $user_id, 'esp_main_access_token', $new_access_token ); update_user_meta( $user_id, 'esp_main_refresh_token', $new_refresh_token ); update_user_meta( $user_id, 'esp_main_token_expires', time() + $new_expires_in ); } elseif ($char_type === 'alt') { esp_update_alt_character_data_item($user_id, $character_id, 'access_token', $new_access_token); esp_update_alt_character_data_item($user_id, $character_id, 'refresh_token', $new_refresh_token); esp_update_alt_character_data_item($user_id, $character_id, 'token_expires', time() + $new_expires_in); } error_log("[ESP] Token refreshed for $char_type CharID $character_id (User $user_id)"); return ['access_token' => $new_access_token, 'expires_in' => $new_expires_in]; }

function esp_clear_specific_character_tokens($user_id, $character_id, $char_type = 'main') { if (!$user_id || !$character_id) return; if ($char_type === 'main') { delete_user_meta( $user_id, 'esp_main_access_token'); delete_user_meta( $user_id, 'esp_main_refresh_token'); delete_user_meta( $user_id, 'esp_main_token_expires'); } elseif ($char_type === 'alt') { esp_update_alt_character_data_item($user_id, $character_id, 'access_token', ''); esp_update_alt_character_data_item($user_id, $character_id, 'refresh_token', ''); esp_update_alt_character_data_item($user_id, $character_id, 'token_expires', 0); } error_log("[ESP] Cleared EVE tokens for $char_type CharID $character_id (User $user_id)"); }

function esp_do_refresh_all_skills() { error_log('[ESP] Starting scheduled skill refresh cron.'); $users_with_main_char = get_users([ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'ID', ]); if (empty($users_with_main_char)) { error_log('[ESP] Cron: No users with main EVE char.'); return; } foreach ($users_with_main_char as $user_id) { $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true); if ($main_char_id) { $main_token = get_user_meta($user_id, 'esp_main_access_token', true); $main_expires = get_user_meta($user_id, 'esp_main_token_expires', true); $current_main_access_token = $main_token; if (!$main_token || time() > ((int)$main_expires - 300)) { $refreshed_main_tokens = esp_refresh_eve_token_for_character_type($user_id, $main_char_id, 'main'); if ($refreshed_main_tokens && isset($refreshed_main_tokens['access_token'])) { $current_main_access_token = $refreshed_main_tokens['access_token']; } else { error_log("[ESP] Cron: Failed to refresh main token for User $user_id, Char $main_char_id. Skipping skills."); $current_main_access_token = null; } } if ($current_main_access_token) { esp_fetch_and_store_skills_for_character_type($user_id, $main_char_id, $current_main_access_token, 'main'); sleep(1); } } $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); if (is_array($alt_characters) && !empty($alt_characters)) { foreach ($alt_characters as $alt_char_data) { if (!isset($alt_char_data['id'])) continue; $alt_char_id = $alt_char_data['id']; $alt_token = isset($alt_char_data['access_token']) ? $alt_char_data['access_token'] : null; $alt_expires = isset($alt_char_data['token_expires']) ? $alt_char_data['token_expires'] : 0; $current_alt_access_token = $alt_token; if (!$alt_token || time() > ((int)$alt_expires - 300)) { $refreshed_alt_tokens = esp_refresh_eve_token_for_character_type($user_id, $alt_char_id, 'alt'); if ($refreshed_alt_tokens && isset($refreshed_alt_tokens['access_token'])) { $current_alt_access_token = $refreshed_alt_tokens['access_token']; } else { error_log("[ESP] Cron: Failed to refresh alt token for User $user_id, Alt Char $alt_char_id. Skipping skills."); $current_alt_access_token = null; } } if ($current_alt_access_token) { esp_fetch_and_store_skills_for_character_type($user_id, $alt_char_id, $current_alt_access_token, 'alt'); sleep(1); } } } } error_log('[ESP] Finished scheduled skill refresh cron.'); }
add_action( 'esp_refresh_all_skills_hook', 'esp_do_refresh_all_skills' );
if ( ! wp_next_scheduled( 'esp_refresh_all_skills_hook' ) ) { wp_schedule_event( time(), 'hourly', 'esp_refresh_all_skills_hook' ); }

function esp_show_admin_page_messages() { $current_screen = get_current_screen(); if ( $current_screen && (strpos($current_screen->id, 'eve_skill_plugin_settings') !== false || strpos($current_screen->id, 'eve_skill_user_characters_page') !== false || strpos($current_screen->id, 'eve_view_all_user_skills') !== false) && isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { esp_display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); } }
add_action( 'admin_notices', 'esp_show_admin_page_messages');

function esp_sso_login_button_shortcode( $atts ) { 
    $atts = shortcode_atts( [ 
        'text' => __( 'Authenticate Main EVE Character', 'eve-skill-plugin' ), 
        'alt_text' => __( 'Authenticate Alt Character', 'eve-skill-plugin' ), 
    ], $atts, 'eve_sso_login_button' ); 
    esp_start_session_if_needed(); 
    $output = ''; 
    if ( isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { 
        ob_start(); 
        esp_display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); 
        $output .= ob_get_clean(); 
    } 
    $client_id = get_option('esp_client_id'); 
    if ( ! $client_id ) { 
        $output .= '<p style="color:orange;">' . esc_html__( 'EVE login not fully configured by admin.', 'eve-skill-plugin' ) . '</p>'; 
    } 
    
    $current_page_url = get_permalink(); 
    if (!$current_page_url) { 
        global $wp; 
        if (isset($wp->request) && !empty($wp->request)) {
            $current_page_url = home_url(add_query_arg(array(), $wp->request)); 
        }
    } 
    if (!$current_page_url) { 
        $current_page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $current_page_url = strtok($current_page_url, '?'); 
    }
    if (!$current_page_url) { 
         $current_page_url = home_url('/');
    }

    if ( is_user_logged_in() ) { 
        $user_id = get_current_user_id(); 
        $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true); 
        $main_char_name = get_user_meta($user_id, 'esp_main_eve_character_name', true); 
        if ($main_char_id && $main_char_name) { 
            $output .= '<p class="eve-sso-status">' . sprintf( esc_html__( 'Main EVE Character: %s.', 'eve-skill-plugin' ), esc_html( $main_char_name ) ) . '</p>'; 
            $output .= '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" class="eve-sso-form" style="display:inline-block; margin-right:10px;">'; 
            $output .= '<input type="hidden" name="action" value="esp_initiate_sso">'; 
            $output .= '<input type="hidden" name="esp_auth_type" value="main">'; 
            $output .= '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url( $current_page_url ) . '">'; 
            $output .= wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce', true, false ); 
            $output .= '<button type="submit" class="button eve-sso-button">' . __( 'Re-Auth/Switch Main', 'eve-skill-plugin') . '</button>'; 
            $output .= '</form>'; 
            $output .= '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" class="eve-sso-form" style="display:inline-block;">'; 
            $output .= '<input type="hidden" name="action" value="esp_initiate_sso">'; 
            $output .= '<input type="hidden" name="esp_auth_type" value="alt">'; 
            $output .= '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url( $current_page_url ) . '">'; 
            $output .= wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce', true, false ); 
            $output .= '<button type="submit" class="button eve-sso-button-alt primary">' . esc_html($atts['alt_text']) . '</button>'; 
            $output .= '</form>'; 
        } else { 
            $output .= '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" class="eve-sso-form">'; 
            $output .= '<input type="hidden" name="action" value="esp_initiate_sso">'; 
            $output .= '<input type="hidden" name="esp_auth_type" value="main">'; 
            $output .= '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url( $current_page_url ) . '">'; 
            $output .= wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce', true, false ); 
            $output .= '<button type="submit" class="button eve-sso-button primary">' . esc_html($atts['text']) . '</button>'; 
            $output .= '</form>'; 
        } 
        $output .= '<p><a href="'.esc_url(admin_url('admin.php?page=eve_skill_user_characters_page')).'">'.__('Manage My Linked EVE Characters', 'eve-skill-plugin').'</a></p>'; 
    } else { 
        $output .= '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" class="eve-sso-form">'; 
        $output .= '<input type="hidden" name="action" value="esp_initiate_sso">'; 
        $output .= '<input type="hidden" name="esp_auth_type" value="main">'; 
        $output .= '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url( $current_page_url ) . '">'; 
        $output .= wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce', true, false ); 
        $output .= '<button type="submit" class="button eve-sso-button primary">' . esc_html($atts['text']) . '</button>'; 
        $output .= '</form>'; 
    } 
    return $output; 
}
add_shortcode( 'eve_sso_login_button', 'esp_sso_login_button_shortcode' );

function esp_deactivate_plugin() { wp_clear_scheduled_hook( 'esp_refresh_all_skills_hook' ); error_log('[ESP] Deactivated and cleared scheduled hook.'); }
register_deactivation_hook( __FILE__, 'esp_deactivate_plugin' );

function esp_handle_clear_all_eve_data_for_user() { if ( !is_user_logged_in() || !check_admin_referer('esp_clear_all_eve_data_action', 'esp_clear_all_eve_data_nonce')) { wp_die('Security check failed or not logged in.'); } $user_id = get_current_user_id(); delete_user_meta($user_id, 'esp_main_eve_character_id'); delete_user_meta($user_id, 'esp_main_eve_character_name'); delete_user_meta($user_id, 'esp_main_access_token'); delete_user_meta($user_id, 'esp_main_refresh_token'); delete_user_meta($user_id, 'esp_main_token_expires'); delete_user_meta($user_id, 'esp_main_owner_hash'); delete_user_meta($user_id, 'esp_main_skills_data'); delete_user_meta($user_id, 'esp_main_total_sp'); delete_user_meta($user_id, 'esp_main_skills_last_updated'); delete_user_meta($user_id, 'esp_alt_characters'); delete_user_meta( $user_id, 'eve_character_id' ); delete_user_meta( $user_id, 'eve_character_name' ); delete_user_meta( $user_id, 'eve_access_token' ); delete_user_meta( $user_id, 'eve_refresh_token' ); delete_user_meta( $user_id, 'eve_token_expires' ); delete_user_meta( $user_id, 'eve_skills_data' ); delete_user_meta( $user_id, 'eve_total_sp' ); delete_user_meta( $user_id, 'eve_owner_hash');  delete_user_meta( $user_id, 'eve_skills_last_updated'); $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : admin_url('admin.php?page=eve_skill_user_characters_page'); wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'all_data_cleared', $redirect_back_url)); exit; }
add_action('admin_post_esp_clear_all_eve_data_for_user', 'esp_handle_clear_all_eve_data_for_user');

?>
