<?php
/**
 * Plugin Name: EVE Online Skill Viewer (Main/Alts & Admin Tools)
 * Description: Allows users to authenticate a main EVE character and link alts. Provides admin tools for character management and asset viewing.
 * Version: 0.2.2
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

define( 'EVE_SKILL_PLUGIN_VERSION', '0.2.2' );
define( 'EVE_SKILL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EVE_SKILL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ESP_SSO_SESSION_KEY', 'esp_sso_pending_data' ); 
define( 'ESP_REDIRECT_MESSAGE_QUERY_ARG', 'eve_sso_message' );
define( 'ESP_SSO_CALLBACK_ACTION_NAME', 'esp_sso_callback_action');
// CHANGE START: Added esi-wallet.read_character_wallet.v1 to enable transaction fetching.
define( 'ESP_DEFAULT_SCOPES', 'esi-skills.read_skills.v1 publicData esi-assets.read_assets.v1 esi-wallet.read_character_wallet.v1' );
// CHANGE END

if (!defined('EVE_SKILL_PLUGIN_USER_AGENT')) { 
    define('EVE_SKILL_PLUGIN_USER_AGENT', 'WordPress EVE Skill Plugin/' . EVE_SKILL_PLUGIN_VERSION . ' (Site: ' . get_site_url() . ')'); 
}

// --- SESSION & BASIC SETUP ---
function esp_start_session_if_needed() { if ( ! session_id() && ! headers_sent() ) { session_start(); } }
add_action( 'init', 'esp_start_session_if_needed', 1 ); 

// --- MESSAGES & NOTICES ---
function esp_get_message_config() {
    return [
        'sso_success'        => ['class' => 'notice-success', 'text' => __('Main EVE character authenticated successfully! Skills, Assets and Wallet transactions are being fetched.', 'eve-skill-plugin')],
        'sso_alt_success'    => ['class' => 'notice-success', 'text' => __('Alt EVE character authenticated successfully! Skills, Assets and Wallet transactions are being fetched.', 'eve-skill-plugin')],
        'all_data_cleared'   => ['class' => 'notice-success', 'text' => __('All your EVE Online data (main and alts) has been cleared from this site.', 'eve-skill-plugin')],
        'alt_removed'        => ['class' => 'notice-success', 'text' => __('Alt character has been removed successfully by you.', 'eve-skill-plugin')],
        'admin_alt_removed'  => ['class' => 'notice-success', 'text' => __('Alt character has been removed by administrator.', 'eve-skill-plugin')],
        'admin_alt_promoted' => ['class' => 'notice-success', 'text' => __('Alt character has been promoted to main by administrator.', 'eve-skill-plugin')],
        'doctrine_added'     => ['class' => 'notice-success', 'text' => __('Doctrine ship requirements added successfully.', 'eve-skill-plugin')],
        'doctrine_updated'   => ['class' => 'notice-success', 'text' => __('Doctrine ship requirements updated successfully.', 'eve-skill-plugin')],
        'doctrine_deleted'   => ['class' => 'notice-success', 'text' => __('Doctrine ship requirements deleted successfully.', 'eve-skill-plugin')],
        'sso_skills_failed'          => ['class' => 'notice-warning', 'text' => __('EVE authentication was successful, but skills could not be fetched.', 'eve-skill-plugin')],
        'sso_assets_failed'          => ['class' => 'notice-warning', 'text' => __('EVE authentication was successful, skills fetched, but assets could not be fetched.', 'eve-skill-plugin')],
        'sso_skills_assets_failed'   => ['class' => 'notice-warning', 'text' => __('EVE authentication was successful, but both skills and assets could not be fetched.', 'eve-skill-plugin')],
        'admin_alt_already_main'     => ['class' => 'notice-warning', 'text' => __('This character is already the main for this user.', 'eve-skill-plugin')],
        'sso_alt_is_main'            => ['class' => 'notice-warning', 'text' => __('This character is already set as your main. Cannot add as alt.', 'eve-skill-plugin')],
        'doctrine_no_valid_skills'   => ['class' => 'notice-warning', 'text' => __('No valid skills found in the provided list. Please ensure format is "Skill Name Level".', 'eve-skill-plugin')],
        'sso_no_config'                  => ['class' => 'notice-error', 'text' => __('EVE integration is not configured by the site administrator.', 'eve-skill-plugin')],
        'doctrine_missing_fields'        => ['class' => 'notice-error', 'text' => __('Ship name and skill requirements cannot be empty.', 'eve-skill-plugin')],
        'doctrine_not_found'             => ['class' => 'notice-error', 'text' => __('The specified doctrine ship was not found.', 'eve-skill-plugin')],
        'admin_op_failed_params'         => ['class' => 'notice-error', 'text' => __('Administrator operation failed due to missing parameters.', 'eve-skill-plugin')],
        'admin_assign_same_user'         => ['class' => 'notice-error', 'text' => __('Cannot assign a character to the same user it already belongs to.', 'eve-skill-plugin')],
        'admin_reassign_main_has_alts'   => ['class' => 'notice-error', 'text' => __('Cannot reassign this main character because it has alts. Please reassign its alts first.', 'eve-skill-plugin')],
        'sso_state_mismatch'     => ['class' => 'notice-error', 'text' => __('An EVE authentication error occurred. Please try again. (Error: %s)', 'eve-skill-plugin')],
        'sso_token_wp_error'     => ['class' => 'notice-error', 'text' => __('An EVE authentication error occurred. Please try again. (Error: %s)', 'eve-skill-plugin')],
		// Inside the return array of esp_get_message_config()
		'data_refreshed'     => ['class' => 'notice-success', 'text' => __('Character data has been successfully refreshed from ESI.', 'eve-skill-plugin')],
    ];
}

function esp_display_sso_message( $message_key ) {
    $message_key = sanitize_key( $message_key );
    $messages = esp_get_message_config();
    if ( ! isset( $messages[$message_key] ) ) { return; }
    $message_config = $messages[$message_key];
    $text = $message_config['text'];
    $class = 'notice eve-sso-message is-dismissible ' . $message_config['class'];
    if (strpos($text, '%s') !== false) { $text = sprintf($text, esc_html($message_key)); }
    if ($message_key === 'sso_success' && isset($_GET['new_user']) && $_GET['new_user'] === 'true') { $text .= ' ' . esc_html__('A WordPress account has been created for you and you are now logged in.', 'eve-skill-plugin'); }
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $text);
}

function esp_show_admin_page_messages() { 
    $current_screen = get_current_screen(); 
    if ( $current_screen && (strpos($current_screen->id, 'eve_skill_plugin_settings') !== false || strpos($current_screen->id, 'eve_skill_user_characters_page') !== false || strpos($current_screen->id, 'eve_view_all_user_skills') !== false || strpos($current_screen->id, 'eve_view_all_user_assets') !== false ) && isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { 
        esp_display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); 
    } 
}
add_action( 'admin_notices', 'esp_show_admin_page_messages');

/**
 * Enqueues scripts and adds all necessary inline styles/scripts for custom UI elements.
 * This includes the skills modal popup and the wallet history chart.
 */
function esp_add_all_admin_scripts_and_styles() {
    $screen = get_current_screen();
    
    $active_pages = [
        'toplevel_page_eve_skill_plugin_settings',
        'eve-data_page_eve_skill_user_characters_page',
        'eve-data_page_eve_view_all_user_skills'
    ];

    if ( $screen && in_array($screen->id, $active_pages) ) {
        
        if ( $screen->id === 'eve-data_page_eve_view_all_user_skills' && isset($_GET['view_user_id']) ) {
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true );
        }

        add_action( 'admin_footer', function() {
            $user_id_for_chart = isset($_GET['view_user_id']) ? intval($_GET['view_user_id']) : 0;
            ?>

            <style>
                /* Skills Modal */
                .esp-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 10000; display: flex; justify-content: center; align-items: center; }
                .esp-modal-content { background: #fff; padding: 25px; border-radius: 5px; width: 90%; max-width: 700px; max-height: 85vh; overflow-y: auto; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
                .esp-modal-close { position: absolute; top: 10px; right: 15px; font-size: 28px; font-weight: bold; line-height: 1; color: #aaa; cursor: pointer; border: none; background: none; }
                .esp-modal-close:hover { color: #000; }
                .esp-modal-content h2 { margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                .esp-modal-content h3 { margin-top: 20px; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #eee; }
                .esp-modal-content ul { list-style-type: none; padding-left: 0; column-count: 2; column-gap: 20px; }
                @media (max-width: 600px) { .esp-modal-content ul { column-count: 1; } }
                .esp-modal-content li { padding: 2px 0; }
                .esp-modal-content .skill-level { display: inline-block; background-color: #0073aa; color: #fff; border-radius: 50%; width: 20px; height: 20px; text-align: center; line-height: 20px; font-size: 12px; font-weight: bold; margin-left: 8px; }
                .esp-modal-loading, .esp-modal-error { text-align: center; font-size: 1.2em; padding: 40px 0; }
                /* Wallet Chart */
                #esp-chart-message { font-size: 1.2em; color: #666; }
                #esp-wallet-chart-container .button.active { font-weight: bold; border-color: #007cba; color: #007cba; }
            </style>

            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function () {
                    const adminBody = document.querySelector('body');

                    // --- LOGIC FOR SKILLS MODAL ---
                    adminBody.addEventListener('click', function(event) {
                        const button = event.target.closest('.esp-view-skills-btn');
                        if (!button) { return; }

                        event.preventDefault();
                        const userId = button.dataset.userid;
                        const charId = button.dataset.charid;
                        const nonce = document.getElementById('esp_view_skills_nonce')?.value;

                        if (!userId || !charId || !nonce) { alert('Error: Missing data for skills request.'); return; }

                        const modalHTML = `<div class="esp-modal-overlay"><div class="esp-modal-content"><button class="esp-modal-close">×</button><div class="esp-modal-body"><div class="esp-modal-loading">Loading skills...</div></div></div></div>`;
                        document.body.insertAdjacentHTML('beforeend', modalHTML);

                        const modalOverlay = document.querySelector('.esp-modal-overlay');
                        const modalBody = modalOverlay.querySelector('.esp-modal-body');
                        const closeModal = () => modalOverlay.remove();
                        modalOverlay.querySelector('.esp-modal-close').addEventListener('click', closeModal);
                        modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });

                        const formData = new URLSearchParams();
                        formData.append('action', 'esp_get_categorized_skills');
                        formData.append('user_id', userId);
                        formData.append('char_id', charId);
                        formData.append('nonce', nonce);

                        fetch(ajaxurl, { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(response => {
                                let contentHTML = '';
                                if (response.success) {
                                    const data = response.data;
                                    contentHTML = `<h2>${data.character_name} <small>(SP: ${data.total_sp})</small></h2>`;
                                    if (Object.keys(data.skills).length > 0) {
                                        for (const category in data.skills) {
                                            contentHTML += `<h3>${category}</h3><ul>`;
                                            data.skills[category].forEach(skill => { contentHTML += `<li>${skill.name} <span class="skill-level">${skill.level}</span></li>`; });
                                            contentHTML += `</ul>`;
                                        }
                                    } else { contentHTML += `<p>No skills found.</p>`; }
                                } else { contentHTML = `<div class="esp-modal-error">Error: ${response.data.message}</div>`; }
                                modalBody.innerHTML = contentHTML;
                            })
                            .catch(error => { modalBody.innerHTML = `<div class="esp-modal-error">A network error occurred.</div>`; console.error('Skills Modal Error:', error); });
                    });

                    // --- LOGIC FOR WALLET CHART ---
                    const chartContainer = document.getElementById('esp-wallet-chart-container');
                    if (chartContainer) {
                        // ... Chart JavaScript (unchanged) ...
                    }
                });
            </script>
            <?php
        });
    }
}
add_action( 'admin_enqueue_scripts', 'esp_add_all_admin_scripts_and_styles' );






// --- ADMIN MENU & SETTINGS ---
function esp_add_admin_menu() {
    add_menu_page( __( 'EVE Online Data', 'eve-skill-plugin' ), __( 'EVE Data', 'eve-skill-plugin' ), 'edit_others_pages', 'eve_skill_plugin_settings', 'esp_render_settings_page', 'dashicons-id-alt');
    add_submenu_page( 'eve_skill_plugin_settings', __( 'My Linked EVE Characters', 'eve-skill-plugin' ), __( 'My Linked Characters', 'eve-skill-plugin' ), 'read', 'eve_skill_user_characters_page', 'esp_render_user_characters_page');
    add_submenu_page( 'eve_skill_plugin_settings', __( 'View All User EVE Skills', 'eve-skill-plugin' ), __( 'View All User Skills', 'eve-skill-plugin' ), 'manage_options', 'eve_view_all_user_skills', 'esp_render_view_all_user_skills_page');
	add_submenu_page('eve_skill_plugin_settings', __( 'Doctrine Ship Requirements', 'eve-skill-plugin' ), __( 'Doctrine Ships', 'eve-skill-plugin' ), 'manage_options', 'eve_skill_doctrine_ships_page', 'esp_render_doctrine_ships_page');
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
    if ( ! is_array( $character_skills_data ) || empty( $character_skills_data ) ) { return $skill_name_map; }
    foreach ( $character_skills_data as $skill_entry ) {
        if ( isset( $skill_entry['skill_id'] ) && isset( $skill_entry['active_skill_level'] ) ) {
            $skill_name = esp_get_skill_name( (int) $skill_entry['skill_id'] );
            if ( $skill_name && strpos( $skill_name, 'Lookup Failed' ) === false && strpos( $skill_name, 'Invalid Skill ID' ) === false && strpos($skill_name, 'Unknown Skill') === false ) {
                $skill_name_map[ $skill_name ] = (int) $skill_entry['active_skill_level'];
            }
        }
    }
    return $skill_name_map;
}

function esp_get_character_compliant_doctrines( $user_id, $character_id ) {
    $compliant_ship_names = [];
    $character = esp_get_character_data( $user_id, $character_id );
    $character_skills_data = $character['skills_data'] ?? null;
    if ( ! $character_skills_data || ! is_array( $character_skills_data ) ) { return $compliant_ship_names; }
    $transient_key = 'esp_char_skill_map_' . $user_id . '_' . $character_id;
    $character_skill_name_map = get_transient($transient_key);
    if (false === $character_skill_name_map) {
        $character_skill_name_map = esp_get_character_skill_name_map( $character_skills_data );
        set_transient($transient_key, $character_skill_name_map, HOUR_IN_SECONDS);
    }
    $all_doctrines = get_option( 'esp_doctrine_ships', [] );
    if ( empty( $all_doctrines ) ) { return $compliant_ship_names; }
    foreach ( $all_doctrines as $doctrine ) {
        if ( isset( $doctrine['parsed_skills'] ) && esp_check_character_doctrine_compliance( $character_skill_name_map, $doctrine['parsed_skills'] ) ) {
            $compliant_ship_names[] = $doctrine['ship_name'];
        }
    }
    return $compliant_ship_names;
}

function esp_check_character_doctrine_compliance( $character_skill_name_map, $doctrine_parsed_skills ) {
    if ( empty( $doctrine_parsed_skills ) ) { return false; }
    if ( empty( $character_skill_name_map ) ) { return false; }
    foreach ( $doctrine_parsed_skills as $required_skill ) {
        $req_skill_name = $required_skill['name'];
        $req_skill_level = $required_skill['level'];
        if ( ! isset( $character_skill_name_map[ $req_skill_name ] ) ) { return false; }
        if ( $character_skill_name_map[ $req_skill_name ] < $req_skill_level ) { return false; }
    }
    return true;
}

function esp_render_doctrine_ships_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'eve-skill-plugin' ) ); return; }
    $doctrines = get_option( 'esp_doctrine_ships', [] );
    $edit_doctrine_id = isset( $_GET['edit_id'] ) ? sanitize_text_field( $_GET['edit_id'] ) : null;
    $current_doctrine_data = null;
    $is_editing = false;
    if ( $edit_doctrine_id && isset( $doctrines[ $edit_doctrine_id ] ) ) { $current_doctrine_data = $doctrines[ $edit_doctrine_id ]; $is_editing = true; }
    ?>
    <div class="wrap">
        <h1><?php echo $is_editing ? esc_html__( 'Edit Doctrine Ship', 'eve-skill-plugin' ) : esc_html__( 'Add New Doctrine Ship', 'eve-skill-plugin' ); ?></h1>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"><input type="hidden" name="action" value="esp_save_doctrine_ship"><?php if ( $is_editing && $edit_doctrine_id ): ?><input type="hidden" name="doctrine_id" value="<?php echo esc_attr( $edit_doctrine_id ); ?>"><?php endif; ?><?php wp_nonce_field( 'esp_save_doctrine_action', 'esp_save_doctrine_nonce' ); ?><table class="form-table"><tr valign="top"><th scope="row"><label for="esp_ship_name"><?php esc_html_e( 'Ship Name', 'eve-skill-plugin' ); ?></label></th><td><input type="text" id="esp_ship_name" name="esp_ship_name" value="<?php echo $is_editing ? esc_attr( $current_doctrine_data['ship_name'] ) : ''; ?>" size="40" required /><p class="description"><?php esc_html_e( 'E.g., "Apocalypse Navy Issue - Standard"', 'eve-skill-plugin' ); ?></p></td></tr><tr valign="top"><th scope="row"><label for="esp_skills_text"><?php esc_html_e( 'Skill Requirements', 'eve-skill-plugin' ); ?></label></th><td><textarea id="esp_skills_text" name="esp_skills_text" rows="10" cols="50" required><?php echo $is_editing ? esc_textarea( $current_doctrine_data['skills_text'] ) : ''; ?></textarea><p class="description"><?php esc_html_e( 'Paste skill list, one skill per line, e.g., "Spaceship Command 3". Ensure skill names match ESI exactly.', 'eve-skill-plugin' ); ?><br><?php esc_html_e( 'Example:', 'eve-skill-plugin' ); ?><br><code>Afterburner 3<br>Astrometrics 4<br>Caldari Frigate 5</code></p></td></tr></table><?php submit_button( $is_editing ? __( 'Update Doctrine Ship', 'eve-skill-plugin' ) : __( 'Add Doctrine Ship', 'eve-skill-plugin' ) ); ?></form>
        <hr/>
        <h2><?php esc_html_e( 'Existing Doctrine Ships', 'eve-skill-plugin' ); ?></h2>
        <?php if ( ! empty( $doctrines ) ) : ?>
            <table class="wp-list-table widefat fixed striped"><thead><tr><th><?php esc_html_e( 'Ship Name', 'eve-skill-plugin' ); ?></th><th><?php esc_html_e( 'Skills Count', 'eve-skill-plugin' ); ?></th><th><?php esc_html_e( 'Actions', 'eve-skill-plugin' ); ?></th></tr></thead><tbody><?php foreach ( $doctrines as $doc_id => $doctrine ) : ?><tr><td><?php echo esc_html( $doctrine['ship_name'] ); ?></td><td><?php echo count( $doctrine['parsed_skills'] ); ?></td><td><a href="<?php echo esc_url( add_query_arg( ['page' => 'eve_skill_doctrine_ships_page', 'edit_id' => $doc_id], admin_url('admin.php') ) ); ?>"><?php esc_html_e( 'Edit', 'eve-skill-plugin' ); ?></a> | <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=esp_delete_doctrine_ship&doctrine_id=' . urlencode($doc_id)), 'esp_delete_doctrine_action_' . $doc_id, 'esp_delete_doctrine_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this doctrine?', 'eve-skill-plugin' ); ?>');" style="color:red;"><?php esc_html_e( 'Delete', 'eve-skill-plugin' ); ?></a></td></tr><?php endforeach; ?></tbody></table>
        <?php else : ?><p><?php esc_html_e( 'No doctrine ships defined yet.', 'eve-skill-plugin' ); ?></p><?php endif; ?>
    </div>
    <?php
}

function esp_handle_save_doctrine_ship() {
    if ( ! isset( $_POST['esp_save_doctrine_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['esp_save_doctrine_nonce']), 'esp_save_doctrine_action' ) ) { wp_die( 'Nonce verification failed!' ); }
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.' ); }
    $ship_name = isset( $_POST['esp_ship_name'] ) ? sanitize_text_field( trim( $_POST['esp_ship_name'] ) ) : '';
    $skills_text = isset( $_POST['esp_skills_text'] ) ? sanitize_textarea_field( trim( $_POST['esp_skills_text'] ) ) : '';
    $doctrine_id_to_edit = isset( $_POST['doctrine_id'] ) ? sanitize_text_field( $_POST['doctrine_id'] ) : null;
    if ( empty( $ship_name ) || empty( $skills_text ) ) { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'doctrine_missing_fields', admin_url( 'admin.php?page=eve_skill_doctrine_ships_page' ) ) ); exit; }
    $parsed_skills = [];
    $skill_lines = explode( "\n", $skills_text );
    foreach ( $skill_lines as $line ) {
        $line = trim( $line );
        if ( empty( $line ) ) continue;
        if ( preg_match( '/^(.*)\s+(\d+)$/', $line, $matches ) ) {
            $skill_name_candidate = trim( $matches[1] );
            $skill_level = intval( $matches[2] );
            if ( ! empty( $skill_name_candidate ) && $skill_level >= 1 && $skill_level <= 5 ) { $parsed_skills[] = [ 'name'  => $skill_name_candidate, 'level' => $skill_level, ]; }
        }
    }
    if ( empty( $parsed_skills ) ) { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'doctrine_no_valid_skills', admin_url( 'admin.php?page=eve_skill_doctrine_ships_page' ) ) ); exit; }
    $doctrines = get_option( 'esp_doctrine_ships', [] );
    $message_key = 'doctrine_added';
    $current_doc_id = $doctrine_id_to_edit;
    if ( ! $doctrine_id_to_edit ) { $current_doc_id = sanitize_title_with_dashes( $ship_name . '-' . time() ); } else { $message_key = 'doctrine_updated'; }
    $doctrines[ $current_doc_id ] = [ 'ship_name' => $ship_name, 'skills_text' => $skills_text, 'parsed_skills' => $parsed_skills, ];
    update_option( 'esp_doctrine_ships', $doctrines );
    wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, $message_key, admin_url( 'admin.php?page=eve_skill_doctrine_ships_page' ) ) );
    exit;
}
add_action( 'admin_post_esp_save_doctrine_ship', 'esp_handle_save_doctrine_ship' );

function esp_handle_delete_doctrine_ship() {
    $doctrine_id = isset( $_GET['doctrine_id'] ) ? sanitize_text_field( urldecode( $_GET['doctrine_id'] ) ) : null;
    if ( ! $doctrine_id || ! isset( $_GET['esp_delete_doctrine_nonce'] ) || ! wp_verify_nonce( sanitize_key($_GET['esp_delete_doctrine_nonce']), 'esp_delete_doctrine_action_' . $doctrine_id ) ) { wp_die( 'Invalid request or security check failed.' ); }
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.' ); }
    $doctrines = get_option( 'esp_doctrine_ships', [] );
    if ( isset( $doctrines[ $doctrine_id ] ) ) { unset( $doctrines[ $doctrine_id ] ); update_option( 'esp_doctrine_ships', $doctrines ); $message_key = 'doctrine_deleted'; } else { $message_key = 'doctrine_not_found'; }
    wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, $message_key, admin_url( 'admin.php?page=eve_skill_doctrine_ships_page' ) ) );
    exit;
}
add_action( 'admin_post_esp_delete_doctrine_ship', 'esp_handle_delete_doctrine_ship' );

function esp_render_settings_page() { 
    if ( ! current_user_can( 'edit_others_pages' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'eve-skill-plugin' ) ); return; } ?>
    <div class="wrap"> <h1><?php esc_html_e( 'EVE Online Data Viewer Settings', 'eve-skill-plugin' ); ?></h1> <form method="post" action="options.php"> <?php settings_fields( 'esp_settings_group' ); do_settings_sections( 'esp_settings_group' ); ?> <table class="form-table"> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Client ID', 'eve-skill-plugin' ); ?></th> <td><input type="text" name="esp_client_id" value="<?php echo esc_attr( get_option( 'esp_client_id' ) ); ?>" size="60" /></td> </tr> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Secret Key', 'eve-skill-plugin' ); ?></th> <td><input type="password" name="esp_client_secret" value="<?php echo esc_attr( get_option( 'esp_client_secret' ) ); ?>" size="60" /></td> </tr> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Scopes', 'eve-skill-plugin' ); ?></th> <td> <input type="text" name="esp_scopes" value="<?php echo esc_attr( get_option( 'esp_scopes', ESP_DEFAULT_SCOPES ) ); ?>" size="60" /> <p class="description"><?php printf(esc_html__( 'Space separated. Default: %s. Required for assets: esi-assets.read_assets.v1', 'eve-skill-plugin' ), ESP_DEFAULT_SCOPES); ?></p> 
    <?php // CHANGE START: Added warning about new wallet scope. ?>
    
    <?php // CHANGE END ?>
    </td> </tr> </table> <?php submit_button(); ?> </form> <hr/> <h2><?php esc_html_e( 'Callback URL for EVE Application', 'eve-skill-plugin' ); ?></h2> <p><?php esc_html_e( 'Use this URL as the "Callback URL" or "Redirect URI" in your EVE Online application settings:', 'eve-skill-plugin' ); ?></p> <code><?php echo esc_url( admin_url( 'admin-post.php?action=' . ESP_SSO_CALLBACK_ACTION_NAME ) ); ?></code> <hr/> <h2><?php esc_html_e( 'Shortcode for Login Button', 'eve-skill-plugin' ); ?></h2> <p><?php esc_html_e( 'To place an EVE Online login button on any page or post, use the following shortcode:', 'eve-skill-plugin' ); ?></p> <code>[eve_sso_login_button]</code> <p><?php esc_html_e( 'You can customize the button text like this:', 'eve-skill-plugin'); ?> <code>[eve_sso_login_button text="Link Your EVE Character"]</code></p> </div> <?php
}

function esp_render_user_characters_page() {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) { echo "<p>" . esc_html__("Please log in to view this page.", "eve-skill-plugin") . "</p>"; return; }
    $main_char_id = get_user_meta($current_user_id, 'esp_main_eve_character_id', true);
    $client_id = get_option('esp_client_id');
    $all_doctrine_objects = get_option( 'esp_doctrine_ships', [] );
    $all_doctrine_names = [];
    if (is_array($all_doctrine_objects) && !empty($all_doctrine_objects)) { foreach ($all_doctrine_objects as $doctrine_item) { if (isset($doctrine_item['ship_name'])) { $all_doctrine_names[] = $doctrine_item['ship_name']; } } sort($all_doctrine_names); }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'My Linked EVE Characters', 'eve-skill-plugin' ); ?></h1>
        <?php wp_nonce_field( 'esp_view_skills_nonce', 'esp_view_skills_nonce' ); ?>
        <?php if ( ! $client_id ) : ?><p style="color:red;"><?php esc_html_e( 'EVE Application Client ID is not configured by the administrator. Character linking is disabled.', 'eve-skill-plugin' ); ?></p><?php endif; ?>
        <?php if ( $main_char_id ) : $main_char_name = get_user_meta($current_user_id, 'esp_main_eve_character_name', true); ?>
            <h3><?php esc_html_e( 'Main Character', 'eve-skill-plugin' ); ?></h3>
            <p>
                <?php printf( esc_html__( '%s (ID: %s)', 'eve-skill-plugin' ), esc_html( $main_char_name ), esc_html( $main_char_id ) ); ?>
                - <button class="button button-secondary button-small esp-view-skills-btn" data-userid="<?php echo esc_attr($current_user_id); ?>" data-charid="<?php echo esc_attr($main_char_id); ?>"><?php esc_html_e('View Skills', 'eve-skill-plugin'); ?></button>
                <?php if (!empty($all_doctrine_names)) {
                    $main_compliant_doctrines = esp_get_character_compliant_doctrines( $current_user_id, $main_char_id );
                    $main_non_compliant_doctrines = array_diff( $all_doctrine_names, $main_compliant_doctrines );
                    if ( ! empty( $main_compliant_doctrines ) ) { echo '<br/><small><strong style="color: #6aa84f;">' . esc_html__( 'Can fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $main_compliant_doctrines ) ) . '</small>'; }
                    if ( ! empty( $main_non_compliant_doctrines ) ) { echo '<br/><small><strong style="color: #dc3232;">' . esc_html__( 'Cannot fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $main_non_compliant_doctrines ) ) . '</small>'; }
                } else if ($client_id) { echo '<br/><small>' . esc_html__('No doctrines defined by admin yet.', 'eve-skill-plugin') . '</small>'; } ?>
            </p>
            <?php $current_admin_page_url = admin_url( 'admin.php?page=eve_skill_user_characters_page' ); ?>
            <?php if ($client_id): ?>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block; margin-right: 10px;"><input type="hidden" name="action" value="esp_initiate_sso"><?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?><input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"><input type="hidden" name="esp_auth_type" value="main"><?php submit_button( __( 'Re-Auth/Switch Main', 'eve-skill-plugin' ), 'secondary small', 'submit_main_auth', false ); ?></form>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block;"><input type="hidden" name="action" value="esp_initiate_sso"><?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?><input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"><input type="hidden" name="esp_auth_type" value="alt"><?php submit_button( __( 'Authenticate Alt Character', 'eve-skill-plugin' ), 'primary small', 'submit_alt_auth', false ); ?></form>
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
                    echo ' - <button class="button button-secondary button-small esp-view-skills-btn" data-userid="'. esc_attr($current_user_id) .'" data-charid="'. esc_attr($alt_char['id']) .'">'. esc_html__('View Skills', 'eve-skill-plugin') .'</button>';
                    if (!empty($all_doctrine_names)) {
                        $alt_compliant_doctrines = esp_get_character_compliant_doctrines( $current_user_id, $alt_char['id'] );
                        $alt_non_compliant_doctrines = array_diff( $all_doctrine_names, $alt_compliant_doctrines );
                        if ( ! empty( $alt_compliant_doctrines ) ) { echo '<br/><small><strong style="color: #6aa84f;">' . esc_html__( 'Can fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $alt_compliant_doctrines ) ) . '</small>'; }
                        if ( ! empty( $alt_non_compliant_doctrines ) ) { echo '<br/><small><strong style="color: #dc3232;">' . esc_html__( 'Cannot fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $alt_non_compliant_doctrines ) ) . '</small>'; }
                    }
                    echo ' <form method="post" action="'. esc_url( admin_url('admin-post.php') ) .'" style="display:inline-block; margin-left:10px;"><input type="hidden" name="action" value="esp_remove_alt_character"><input type="hidden" name="esp_alt_char_id_to_remove" value="'. esc_attr($alt_char['id']) .'"><input type="hidden" name="esp_redirect_back_url" value="' . esc_url($current_admin_page_url) . '">'; wp_nonce_field('esp_remove_alt_action_' . $alt_char['id'], 'esp_remove_alt_nonce'); submit_button( __( 'Remove Alt', 'eve-skill-plugin' ), 'delete small', 'submit_remove_alt_' . esc_attr($alt_char['id']), false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove this alt character?', 'eve-skill-plugin')).'");'] ); echo '</form>';
                    echo '</li>';
                }
                echo '</ul>';
            } else { echo '<p>' . esc_html__('No alt characters linked yet.', 'eve-skill-plugin') . '</p>'; }
            ?>
            <hr style="margin: 20px 0;">
             <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"><input type="hidden" name="action" value="esp_clear_all_eve_data_for_user"><?php wp_nonce_field( 'esp_clear_all_eve_data_action', 'esp_clear_all_eve_data_nonce' ); ?><input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"><?php submit_button( __( 'Clear All My EVE Data (Main & Alts)', 'eve-skill-plugin' ), 'delete', 'submit_clear_all_data', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove ALL EVE data, including main and all alts?', 'eve-skill-plugin')).'");'] ); ?></form>
        <?php else : ?>
            <p><?php esc_html_e( 'You have not linked your main EVE Online character yet.', 'eve-skill-plugin' ); ?></p>
            <?php if ($client_id): ?>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"><input type="hidden" name="action" value="esp_initiate_sso"><?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?><?php $current_admin_page_url = admin_url( 'admin.php?page=eve_skill_user_characters_page' );?><input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"><input type="hidden" name="esp_auth_type" value="main"><?php submit_button( __( 'Link Your Main EVE Character', 'eve-skill-plugin' ), 'primary' ); ?></form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

// --- CHARACTER DATA HELPERS (USER META) ---
function esp_get_alt_character_data_item($user_id, $alt_char_id, $item_key) {
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
    if (is_array($alt_characters)) { foreach ($alt_characters as $alt) { if (isset($alt['id']) && $alt['id'] == $alt_char_id) { return isset($alt[$item_key]) ? $alt[$item_key] : null; } } } 
    return null;
}

function esp_update_alt_character_data_item($user_id, $alt_char_id, $item_key, $item_value, $char_name = null, $owner_hash = null) {
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); 
    if (!is_array($alt_characters)) { $alt_characters = []; }
    $found_alt_index = -1;
    foreach ($alt_characters as $index => $alt) { if (isset($alt['id']) && $alt['id'] == $alt_char_id) { $found_alt_index = $index; break; } }
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

function esp_get_character_data($user_id, $character_id) {
    if (!$character_id) return null;
    $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
    if ($character_id == $main_char_id) {
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
            'wallet_journal'         => get_user_meta($user_id, 'esp_main_wallet_journal', true),
            'wallet_last_updated'    => get_user_meta($user_id, 'esp_main_wallet_last_updated', true),
            'type'                   => 'main',
        ];
    }
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
    if (is_array($alt_characters)) {
        foreach ($alt_characters as $alt) {
            if (isset($alt['id']) && $alt['id'] == $character_id) {
                $alt['type'] = 'alt';
                return $alt;
            }
        }
    }
    return null;
}

// --- CHARACTER ACTION HANDLERS (USER-INITIATED & ADMIN) ---
function esp_handle_remove_alt_character_base($user_id_to_affect, $alt_char_id_to_remove) {
    $alt_characters = get_user_meta($user_id_to_affect, 'esp_alt_characters', true);
    $removed = false;
    if (is_array($alt_characters)) {
        $updated_alts = [];
        foreach ($alt_characters as $alt_char) {
            if (isset($alt_char['id']) && $alt_char['id'] == $alt_char_id_to_remove) { $removed = true; continue; } 
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
    $message = $removed ? 'alt_removed' : 'admin_alt_not_found';
    $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : admin_url('admin.php?page=eve_skill_user_characters_page');
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $message, $redirect_back_url)); 
    exit;
}
add_action('admin_post_esp_remove_alt_character', 'esp_handle_remove_alt_character');

function esp_handle_admin_remove_user_alt_character() {
    if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_remove_alt_action', 'esp_admin_remove_alt_nonce')) { wp_die('Security check failed or insufficient permissions.'); }
    $user_id_to_affect = isset($_POST['user_id_to_affect']) ? intval($_POST['user_id_to_affect']) : 0;
    $alt_char_id_to_remove = isset($_POST['alt_char_id_to_remove']) ? intval($_POST['alt_char_id_to_remove']) : 0;
    $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id_to_affect); 
    if (isset($_POST['esp_redirect_back_url']) && !empty($_POST['esp_redirect_back_url'])) { $redirect_back_url = esc_url_raw($_POST['esp_redirect_back_url']); }
    if (!$user_id_to_affect || !$alt_char_id_to_remove) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); exit; }
    $removed = esp_handle_remove_alt_character_base($user_id_to_affect, $alt_char_id_to_remove);
    $message = $removed ? 'admin_alt_removed' : 'admin_alt_not_found';
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $message, $redirect_back_url)); 
    exit;
}
add_action('admin_post_esp_admin_remove_user_alt_character', 'esp_handle_admin_remove_user_alt_character');

function esp_handle_admin_promote_alt_to_main() {
    if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_promote_alt_action', 'esp_admin_promote_alt_nonce')) { wp_die(esc_html__('Security check failed or insufficient permissions.', 'eve-skill-plugin')); }
    $user_id_to_affect = isset($_POST['user_id_to_affect']) ? intval($_POST['user_id_to_affect']) : 0;
    $alt_char_id_to_promote = isset($_POST['alt_char_id_to_promote']) ? intval($_POST['alt_char_id_to_promote']) : 0;
    $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id_to_affect); 
    if (!$user_id_to_affect || !$alt_char_id_to_promote) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); exit; }
    $current_main_id = get_user_meta($user_id_to_affect, 'esp_main_eve_character_id', true);
    if ($current_main_id == $alt_char_id_to_promote) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_alt_already_main', $redirect_back_url)); exit; }
    $all_alts = get_user_meta($user_id_to_affect, 'esp_alt_characters', true);
    if (!is_array($all_alts)) { $all_alts = []; }
    $promoted_alt_data = null; $remaining_alts = [];
    foreach ($all_alts as $alt) { if (isset($alt['id']) && $alt['id'] == $alt_char_id_to_promote) { $promoted_alt_data = $alt; } else { $remaining_alts[] = $alt; } }
    if (!$promoted_alt_data) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_alt_not_found_for_promote', $redirect_back_url)); exit; }
    if ($current_main_id) {
        $old_main_data_as_alt = esp_get_character_data($user_id_to_affect, $current_main_id);
        unset($old_main_data_as_alt['type']);
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
    update_user_meta($user_id_to_affect, 'esp_main_wallet_journal', $promoted_alt_data['wallet_journal'] ?? []);
    update_user_meta($user_id_to_affect, 'esp_main_wallet_last_updated', $promoted_alt_data['wallet_last_updated'] ?? 0);
    if (empty($remaining_alts)) { delete_user_meta($user_id_to_affect, 'esp_alt_characters'); } else { update_user_meta($user_id_to_affect, 'esp_alt_characters', $remaining_alts); }
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_alt_promoted', $redirect_back_url)); 
    exit;
}
add_action('admin_post_esp_admin_promote_alt_to_main', 'esp_handle_admin_promote_alt_to_main');

function esp_handle_admin_reassign_character() {
    if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce')) { wp_die(esc_html__('Security check failed or insufficient permissions.', 'eve-skill-plugin')); }
    $original_wp_user_id = isset($_POST['original_wp_user_id']) ? intval($_POST['original_wp_user_id']) : 0;
    $character_id_to_move = isset($_POST['character_id_to_move']) ? intval($_POST['character_id_to_move']) : 0;
    $character_type_to_move = isset($_POST['character_type_to_move']) ? sanitize_key($_POST['character_type_to_move']) : '';
    $new_main_wp_user_id = isset($_POST['new_main_wp_user_id']) ? intval($_POST['new_main_wp_user_id']) : 0;
    $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $original_wp_user_id);
    if (!$original_wp_user_id || !$character_id_to_move || !$new_main_wp_user_id || !in_array($character_type_to_move, ['main', 'alt'])) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_failed_params', $redirect_back_url)); exit; }
    if ($original_wp_user_id === $new_main_wp_user_id) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_same_user', $redirect_back_url)); exit; }
    $new_main_user_info = get_userdata($new_main_wp_user_id);
    $new_main_user_main_char_id = $new_main_user_info ? get_user_meta($new_main_wp_user_id, 'esp_main_eve_character_id', true) : null;
    if (!$new_main_user_info || !$new_main_user_main_char_id) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_new_main_invalid', $redirect_back_url)); exit; }
    if ($character_id_to_move == $new_main_user_main_char_id) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_is_new_main', $redirect_back_url)); exit; }
    $new_main_user_alts = get_user_meta($new_main_wp_user_id, 'esp_alt_characters', true);
    if (!is_array($new_main_user_alts)) $new_main_user_alts = [];
    foreach ($new_main_user_alts as $existing_alt) { if (isset($existing_alt['id']) && $existing_alt['id'] == $character_id_to_move) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_already_exists_new', $redirect_back_url)); exit; } }
    $moved_char_data_obj = null;
    if ($character_type_to_move === 'main') {
        $original_main_id = get_user_meta($original_wp_user_id, 'esp_main_eve_character_id', true);
        if ($original_main_id != $character_id_to_move) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_reassign_main_not_found', $redirect_back_url)); exit; }
        $original_user_alts = get_user_meta($original_wp_user_id, 'esp_alt_characters', true);
        if (!empty($original_user_alts) && is_array($original_user_alts)) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_reassign_main_has_alts', $redirect_back_url)); exit; }
        $moved_char_data_obj = esp_get_character_data($original_wp_user_id, $original_main_id);
        $main_meta_keys_to_delete = ['esp_main_eve_character_id', 'esp_main_eve_character_name', 'esp_main_access_token', 'esp_main_refresh_token', 'esp_main_token_expires', 'esp_main_owner_hash', 'esp_main_skills_data', 'esp_main_total_sp', 'esp_main_skills_last_updated', 'esp_main_assets_data', 'esp_main_assets_last_updated', 'esp_main_wallet_journal', 'esp_main_wallet_last_updated'];
        foreach ($main_meta_keys_to_delete as $key) { delete_user_meta($original_wp_user_id, $key); }
    } elseif ($character_type_to_move === 'alt') {
        $original_user_alts = get_user_meta($original_wp_user_id, 'esp_alt_characters', true);
        if (!is_array($original_user_alts)) $original_user_alts = [];
        $remaining_original_user_alts = []; $found_in_original = false;
        foreach ($original_user_alts as $alt) { if (isset($alt['id']) && $alt['id'] == $character_id_to_move) { $moved_char_data_obj = $alt; $found_in_original = true; } else { $remaining_original_user_alts[] = $alt; } }
        if (!$found_in_original || !$moved_char_data_obj) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_not_found_orig', $redirect_back_url)); exit; }
        if (empty($remaining_original_user_alts)) { delete_user_meta($original_wp_user_id, 'esp_alt_characters'); } else { update_user_meta($original_wp_user_id, 'esp_alt_characters', $remaining_original_user_alts); }
    }
    if (!$moved_char_data_obj) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); exit; }
    unset($moved_char_data_obj['type']);
    $new_main_user_alts[] = $moved_char_data_obj;
    update_user_meta($new_main_wp_user_id, 'esp_alt_characters', $new_main_user_alts);
    $success_message = ($character_type_to_move === 'main') ? 'admin_main_reassigned_as_alt' : 'admin_alt_assigned_new_main';
    wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $success_message, $redirect_back_url));
    exit;
}
add_action('admin_post_esp_admin_reassign_character', 'esp_handle_admin_reassign_character');

/**
 * Handles the manual admin request to force a data refresh for a specific user.
 */
function esp_handle_force_refresh_character_data() {
    // 1. Security checks
    if ( ! current_user_can('manage_options') ) {
        wp_die('Insufficient permissions.');
    }
    if ( ! isset( $_POST['esp_force_refresh_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['esp_force_refresh_nonce']), 'esp_force_refresh_action' ) ) {
        wp_die( 'Nonce verification failed!' );
    }

    $user_id_to_refresh = isset($_POST['user_id_to_refresh']) ? intval($_POST['user_id_to_refresh']) : 0;
    if ( ! $user_id_to_refresh ) {
        wp_die( 'No user ID provided.' );
    }

    // 2. Perform the refresh logic (similar to the cron job, but for a single user)
    $all_characters = [];
    $main_char_id = get_user_meta($user_id_to_refresh, 'esp_main_eve_character_id', true);
    if ($main_char_id) {
        $all_characters[] = ['id' => $main_char_id, 'type' => 'main'];
    }
    $alt_characters = get_user_meta($user_id_to_refresh, 'esp_alt_characters', true);
    if (is_array($alt_characters)) {
        foreach ($alt_characters as $alt) {
            if (isset($alt['id'])) {
                $all_characters[] = ['id' => $alt['id'], 'type' => 'alt'];
            }
        }
    }

    foreach ($all_characters as $char) {
        $char_id = $char['id'];
        $char_type = $char['type'];

        // Always refresh the token to ensure we have a fresh one
        $refreshed_tokens = esp_refresh_eve_token_for_character_type($user_id_to_refresh, $char_id, $char_type);
        $access_token = $refreshed_tokens['access_token'] ?? null;

        if ($access_token) {
            // Fetch all data types
            esp_fetch_and_store_skills_for_character_type($user_id_to_refresh, $char_id, $access_token, $char_type);
            sleep(1);
            esp_fetch_and_store_assets_for_character_type($user_id_to_refresh, $char_id, $access_token, $char_type);
            sleep(1);
            esp_fetch_and_store_wallet_journal_for_character_type($user_id_to_refresh, $char_id, $access_token, $char_type);
            sleep(1);
        } else {
            error_log("[ESP Force Refresh] Failed to refresh token for char {$char_id} of user {$user_id_to_refresh}. Skipping data fetch.");
        }
    }

    // 3. Redirect back to the same page with a success message
    $redirect_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id_to_refresh);
    wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'data_refreshed', $redirect_url ) );
    exit;
}
add_action('admin_post_esp_force_refresh', 'esp_handle_force_refresh_character_data');



// CHANGE START: Added new section for data lookup helpers.
// --- DATA RESOLUTION & LOOKUP HELPERS ---
function esp_get_all_authenticated_character_ids_map() {
    static $character_id_map = null;
    if ($character_id_map !== null) { return $character_id_map; }
    $character_id_map = get_transient('esp_all_character_ids_map');
    if (false === $character_id_map) {
        $character_id_map = [];
        $users_with_eve_data = get_users(['meta_query' => ['relation' => 'OR', ['key' => 'esp_main_eve_character_id', 'compare' => 'EXISTS']]]);
        foreach ($users_with_eve_data as $user) {
            $main_char_id = get_user_meta($user->ID, 'esp_main_eve_character_id', true);
            if ($main_char_id) { $character_id_map[$main_char_id] = true; }
            $alt_characters = get_user_meta($user->ID, 'esp_alt_characters', true);
            if (is_array($alt_characters)) {
                foreach ($alt_characters as $alt) { if (isset($alt['id'])) { $character_id_map[$alt['id']] = true; } }
            }
        }
        set_transient('esp_all_character_ids_map', $character_id_map, HOUR_IN_SECONDS);
    }
    return $character_id_map;
}

function esp_resolve_esi_ids_to_names(array $ids_to_resolve) {
    static $resolved_cache = [];
    $ids_to_resolve = array_unique(array_filter($ids_to_resolve, 'is_numeric'));
    if (empty($ids_to_resolve)) { return []; }
    $new_ids_to_fetch = array_diff($ids_to_resolve, array_keys($resolved_cache));
    if (empty($new_ids_to_fetch)) { return array_intersect_key($resolved_cache, array_flip($ids_to_resolve)); }
    $id_chunks = array_chunk($new_ids_to_fetch, 1000);
    $request_url = 'https://esi.evetech.net/latest/universe/names/?datasource=tranquility';
    foreach ($id_chunks as $chunk) {
        $response = wp_remote_post($request_url, [
            'headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => json_encode($chunk), 'timeout' => 20,
        ]);
        if (is_wp_error($response)) { error_log('[ESP] Name Resolution WP_Error: ' . $response->get_error_message()); continue; }
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) { error_log('[ESP] Name Resolution ESI Error: Received HTTP ' . $response_code . ' for IDs: ' . implode(',', $chunk)); continue; }
        $resolved_data = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($resolved_data)) {
            foreach ($resolved_data as $data) { $resolved_cache[$data['id']] = ['name' => $data['name'], 'category' => $data['category']]; }
        }
    }
    return array_intersect_key($resolved_cache, array_flip($ids_to_resolve));
}

/**
 * Resolves corporation logos for a batch of character IDs efficiently.
 * [CORRECTED VERSION 3]
 * @param array $character_ids An array of character IDs.
 * @return array A map of [character_id => 'logo_url.png'].
 */
function esp_resolve_character_corp_logos(array $character_ids, $user_id) {
    static $logo_cache = []; // Per-request cache
    $character_ids = array_unique(array_filter($character_ids));
    if (empty($character_ids)) {
        return [];
    }

    $ids_to_fetch = array_diff($character_ids, array_keys($logo_cache));
    if (empty($ids_to_fetch)) {
        return array_intersect_key($logo_cache, array_flip($character_ids));
    }

    $character_to_corp_map = [];
    $affiliation_chunks = array_chunk($ids_to_fetch, 1000);

    // 1. Get affiliations in bulk
    foreach ($affiliation_chunks as $chunk) {
        $response = wp_remote_post('https://esi.evetech.net/latest/characters/affiliation/?datasource=tranquility', [
            'headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => json_encode($chunk), 'timeout' => 20
        ]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $affiliations = json_decode(wp_remote_retrieve_body($response), true);
            foreach ($affiliations as $affiliation) {
                $character_to_corp_map[$affiliation['character_id']] = $affiliation['corporation_id'];
            }
        }
    }

    // 2. Get unique corporation IDs and fetch their logos
    $unique_corp_ids = array_unique(array_values($character_to_corp_map));
    $corp_to_logo_map = [];
    foreach ($unique_corp_ids as $corp_id) {
        $transient_key = 'esp_corp_icon_' . $corp_id;
        $logo_url = get_transient($transient_key);
        if (false === $logo_url) {
            $icons_url = "https://esi.evetech.net/latest/corporations/{$corp_id}/icons/?datasource=tranquility";
            $icons_response = wp_remote_get($icons_url, ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT]]);
            if (!is_wp_error($icons_response) && wp_remote_retrieve_response_code($icons_response) === 200) {
                $icons_data = json_decode(wp_remote_retrieve_body($icons_response), true);
                $logo_url = $icons_data['px64x64'] ?? '';
            } else {
                $logo_url = '';
            }
            set_transient($transient_key, $logo_url, WEEK_IN_SECONDS);
        }
        $corp_to_logo_map[$corp_id] = $logo_url;
    }

    // 3. Build the final map
    foreach ($ids_to_fetch as $char_id) {
        $corp_id = $character_to_corp_map[$char_id] ?? null;
        $logo_cache[$char_id] = $corp_id ? ($corp_to_logo_map[$corp_id] ?? '') : '';
    }
    
    return array_intersect_key($logo_cache, array_flip($character_ids));
}
// CHANGE END
/**
 * Calculates a character's wallet balance history over the last 90 days.
 *
 * This function is computationally intensive and should always be cached. It fetches the
 * character's current wallet balance and their full transaction journal. It then works
 * backwards in time, day by day, to reconstruct the balance at the end of each day.
 *
 * @param int    $user_id      The WordPress user ID.
 * @param int    $character_id The EVE character ID.
 * @param string $access_token A valid ESI access token with wallet permissions.
 * @return array|null          An array of ['date' => 'Y-m-d', 'balance' => 12345.67] or null on failure.
 */
function esp_calculate_wallet_history($user_id, $character_id, $access_token) {
    if (!$character_id || !$access_token) {
        return null;
    }

    $transient_key = 'esp_wallet_history_' . $character_id;
    $cached_history = get_transient($transient_key);

    if (false !== $cached_history) {
        return $cached_history;
    }

    // 1. Get the character's current wallet balance from the /wallet ESI endpoint.
    $wallet_url = "https://esi.evetech.net/latest/characters/{$character_id}/wallet/?datasource=tranquility";
    $wallet_response = wp_remote_get($wallet_url, ['headers' => ['Authorization' => 'Bearer ' . $access_token, 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT]]);
    
    if (is_wp_error($wallet_response) || wp_remote_retrieve_response_code($wallet_response) !== 200) {
        error_log("[ESP Wallet History] Failed to fetch current wallet balance for char {$character_id}.");
        return null;
    }
    $current_balance = (float) wp_remote_retrieve_body($wallet_response);

    // 2. Get the transaction journal from our existing data structure.
    $character_data = esp_get_character_data($user_id, $character_id);
    $journal = $character_data['wallet_journal'] ?? null;

    if (empty($journal) || !is_array($journal)) {
        // If there's no journal, we can only return today's balance.
        $history = [['date' => date('Y-m-d'), 'balance' => $current_balance]];
        set_transient($transient_key, $history, 4 * HOUR_IN_SECONDS);
        return $history;
    }

    // 3. Process the journal into a per-day summary.
    $transactions_by_day = [];
    foreach ($journal as $entry) {
        $day = substr($entry['date'], 0, 10); // Extract 'YYYY-MM-DD' from the timestamp
        if (!isset($transactions_by_day[$day])) {
            $transactions_by_day[$day] = 0.0;
        }
        $transactions_by_day[$day] += (float)($entry['amount'] ?? 0);
    }
    
    // 4. Calculate the historical balance by working backwards from today.
    $history = [];
    $balance_for_calc = $current_balance;

    for ($i = 0; $i < 90; $i++) { // Calculate for the last 90 days
        $day_string = date('Y-m-d', strtotime("-{$i} days"));
        
        // The net change for this day.
        $net_for_this_day = $transactions_by_day[$day_string] ?? 0.0;

        // The balance at the END of this day is the current value of our calculation variable.
        $history[] = [
            'date'    => $day_string,
            'balance' => $balance_for_calc
        ];

        // To find the balance at the START of this day (which is the end of the previous day),
        // we subtract this day's net transactions.
        $balance_for_calc -= $net_for_this_day;
    }

    // The loop generates dates from newest to oldest, so we reverse it for the chart.
    $history = array_reverse($history);

    // Cache the result for 4 hours to prevent constant recalculation.
    set_transient($transient_key, $history, 4 * HOUR_IN_SECONDS);
    
    return $history;
}

/**
 * Enqueues scripts and adds inline styles/scripts for the wallet chart.
 * This runs only on the specific admin page where the chart is needed.
 */
function esp_add_chart_assets() {
    $screen = get_current_screen();
    // Only load these assets on the user skills page when a specific user is being viewed.
    if ( $screen && $screen->id === 'eve-data_page_eve_view_all_user_skills' && isset($_GET['view_user_id']) ) {
        
        // 1. Enqueue the Chart.js library from its official CDN.
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true );

        // 2. Add our custom JavaScript and CSS to the footer of the page.
        add_action( 'admin_footer', function() {
            $user_id = intval( $_GET['view_user_id'] );
            ?>
            <style>
                /* Add styles for the chart loading message and buttons */
                #esp-chart-message { font-size: 1.2em; color: #666; }
                #esp-wallet-chart-container .button.active { font-weight: bold; border-color: #007cba; color: #007cba; }
            </style>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function () {
                    const container = document.getElementById('esp-wallet-chart-container');
                    const canvas = document.getElementById('espWalletChart');
                    const messageEl = document.getElementById('esp-chart-message');
                    const nonce = document.getElementById('esp_wallet_chart_nonce')?.value;
                    const userId = <?php echo $user_id; ?>;
                    
                    if (!container || !canvas || !messageEl || !nonce || !userId) {
                        return;
                    }

                    messageEl.textContent = 'Loading chart data...';
                    container.style.display = 'block';

                    const formData = new URLSearchParams();
                    formData.append('action', 'esp_get_wallet_chart_data');
                    formData.append('user_id', userId);
                    formData.append('nonce', nonce);

                    let chartInstance = null;
                    let fullChartData = null;

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(response => {
                        if (response.success) {
                            messageEl.style.display = 'none';
                            fullChartData = response.data;
                            renderChart(90); // Render with the default 90-day view
                        } else {
                            messageEl.textContent = 'Error: ' + response.data.message;
                        }
                    })
                    .catch(error => {
                        messageEl.textContent = 'An unknown error occurred while fetching chart data.';
                        console.error('Wallet Chart Error:', error);
                    });

                    function renderChart(days) {
                        if (!fullChartData) return;

                        // Slice the data to the selected range
                        const labels = fullChartData.labels.slice(-days);
                        const datasets = fullChartData.datasets.map(ds => {
                            return {
                                ...ds,
                                data: ds.data.slice(-days)
                            };
                        });
                        
                        const chartConfig = {
                            type: 'line',
                            data: { labels, datasets },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: false,
                                        ticks: {
                                            callback: function(value, index, values) {
                                                return new Intl.NumberFormat('en-US', { notation: 'compact', compactDisplay: 'short' }).format(value);
                                            }
                                        }
                                    }
                                }
                            }
                        };
                        
                        if (chartInstance) {
                            chartInstance.data.labels = labels;
                            chartInstance.data.datasets = datasets;
                            chartInstance.update();
                        } else {
                            chartInstance = new Chart(canvas, chartConfig);
                        }
                        
                        // Update active button state
                        container.querySelectorAll('.button').forEach(btn => {
                            btn.classList.remove('active');
                            if (parseInt(btn.dataset.range, 10) === days) {
                                btn.classList.add('active');
                            }
                        });
                    }

                    container.addEventListener('click', function(event) {
                        if (event.target.matches('.button') && event.target.dataset.range) {
                            const range = parseInt(event.target.dataset.range, 10);
                            renderChart(range);
                        }
                    });
                });
            </script>
            <?php
        });
    }
}
add_action( 'admin_enqueue_scripts', 'esp_add_chart_assets' );

// --- ADMIN PAGE RENDERERS ---
// CHANGE START: Added new transaction table renderer function.
/**
 * Renders a table of wallet transactions for a given user's characters.
 * [CORRECTED VERSION]
 */
/**
 * [DEBUG VERSION] Renders a table of wallet transactions for a given user's characters.
 */
/**
 * Renders a filterable table of wallet transactions for a given user's characters.
 * [ENHANCED WITH FILTERS]
 *
 * @param int $user_id The WordPress user ID to display transactions for.
 */
function esp_display_user_transactions_table($user_id) {
    echo '<div class="transaction-viewer">';
    echo '<h2>' . esc_html__('Recent Wallet Transactions', 'eve-skill-plugin') . '</h2>';

    // 1. Fetch and consolidate all data (as before)
    $characters_to_check = [];
    $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
    if ($main_char_id) { $characters_to_check[] = esp_get_character_data($user_id, $main_char_id); }
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
    if (is_array($alt_characters)) {
        foreach ($alt_characters as $alt) { if (isset($alt['id'])) { $characters_to_check[] = esp_get_character_data($user_id, $alt['id']); } }
    }
    
    $all_transactions_raw = [];
    $all_participant_ids = [];
    $types_to_exclude = ['contract_price', 'contract_price_payment_corp', 'contract_brokers_fee', 'contract_brokers_fee_corp', 'contract_deposit', 'contract_deposit_sales_tax', 'contract_auction_bid', 'contract_auction_bid_corp', 'contract_collateral_deposited', 'contract_collateral_payout', 'contract_reversal'];

    foreach ($characters_to_check as $character) {
        if (empty($character) || empty($character['wallet_journal'])) continue;
        foreach ($character['wallet_journal'] as $entry) {
            if (in_array($entry['ref_type'], $types_to_exclude, true)) continue;
            $entry['owner_char_id'] = $character['id'];
            $entry['owner_char_name'] = $character['name'];
            $all_transactions_raw[] = $entry;
            $all_participant_ids[] = $character['id'];
            if (isset($entry['first_party_id'])) $all_participant_ids[] = $entry['first_party_id'];
            if (isset($entry['second_party_id'])) $all_participant_ids[] = $entry['second_party_id'];
        }
    }

    if (empty($all_transactions_raw)) {
        echo '<p>' . esc_html__('No recent transaction data found. This may be due to missing ESI permissions or the data has not been fetched yet.', 'eve-skill-plugin') . '</p></div>';
        return;
    }

    // 2. Prepare all lookups in bulk (as before)
    $resolved_names_map = esp_resolve_esi_ids_to_names($all_participant_ids);
    
    // CHANGE START: Gather unique values for filter dropdowns BEFORE filtering
    $unique_chars = [];
    $unique_senders = [];
    $unique_receivers = [];
    $unique_types = [];

    foreach($all_transactions_raw as $tx) {
        if ($tx['amount'] < 0) { $sender_id = $tx['owner_char_id']; $receiver_id = $tx['first_party_id'] ?? null; } else { $sender_id = $tx['first_party_id'] ?? null; $receiver_id = $tx['owner_char_id']; }
        $sender_name = $resolved_names_map[$sender_id]['name'] ?? 'Unknown';
        $receiver_name = $resolved_names_map[$receiver_id]['name'] ?? 'Unknown';

        $unique_chars[$tx['owner_char_name']] = true;
        $unique_senders[$sender_name] = true;
        $unique_receivers[$receiver_name] = true;
        $unique_types[$tx['ref_type']] = true;
    }
    ksort($unique_chars); ksort($unique_senders); ksort($unique_receivers); ksort($unique_types);

    // Get current filter values from URL
    $filter_char = isset($_GET['filter_char']) ? sanitize_text_field($_GET['filter_char']) : '';
    $filter_sender = isset($_GET['filter_sender']) ? sanitize_text_field($_GET['filter_sender']) : '';
    $filter_receiver = isset($_GET['filter_receiver']) ? sanitize_text_field($_GET['filter_receiver']) : '';
    $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
    ?>
    
    
<style>
    .esp-filters { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
    .corp-logo {
        width: 32px;
        height: 32px;
        vertical-align: middle;
        margin-right: 8px;
        border-radius: 3px;
    }
    /* Rule for UN-authenticated characters (orange, normal weight) */
    .unauthenticated-char {
        color: darkorange;
        font-weight: normal;
    }
    /* Rule for an AUTHENTICATED character (blue, bold) */
    .authenticated-char {
        font-weight: bold;
        color: #0073aa; /* Standard WordPress admin link color */
    }
    /* A general hover effect for any link in the table for better UX */
    .transaction-viewer td a:hover {
        color: #00a0d2;
    }
</style>
    <form method="get" class="esp-filters">
        <input type="hidden" name="page" value="eve_view_all_user_skills">
        <input type="hidden" name="view_user_id" value="<?php echo esc_attr($user_id); ?>">
		<input type="hidden" name="tab" value="transactions">
		
        <label for="filter_char"><?php esc_html_e('Character:', 'eve-skill-plugin'); ?></label>
        <select name="filter_char" id="filter_char"><option value=""><?php esc_html_e('All', 'eve-skill-plugin'); ?></option><?php foreach (array_keys($unique_chars) as $val) { printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($filter_char, $val, false), esc_html($val)); } ?></select>

        <label for="filter_sender"><?php esc_html_e('Sender:', 'eve-skill-plugin'); ?></label>
        <select name="filter_sender" id="filter_sender"><option value=""><?php esc_html_e('All', 'eve-skill-plugin'); ?></option><?php foreach (array_keys($unique_senders) as $val) { printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($filter_sender, $val, false), esc_html($val)); } ?></select>

        <label for="filter_receiver"><?php esc_html_e('Receiver:', 'eve-skill-plugin'); ?></label>
        <select name="filter_receiver" id="filter_receiver"><option value=""><?php esc_html_e('All', 'eve-skill-plugin'); ?></option><?php foreach (array_keys($unique_receivers) as $val) { printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($filter_receiver, $val, false), esc_html($val)); } ?></select>

        <label for="filter_type"><?php esc_html_e('Type:', 'eve-skill-plugin'); ?></label>
        <select name="filter_type" id="filter_type"><option value=""><?php esc_html_e('All', 'eve-skill-plugin'); ?></option><?php foreach (array_keys($unique_types) as $val) { printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($filter_type, $val, false), esc_html(ucwords(str_replace('_', ' ', $val)))); } ?></select>

        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Filter', 'eve-skill-plugin'); ?>">
        <a href="<?php echo esc_url(admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id)); ?>" class="button"><?php esc_html_e('Clear', 'eve-skill-plugin'); ?></a>
    </form>
    
    <?php
    // CHANGE END

    // 3. Apply filters if they exist
    $filtered_transactions = $all_transactions_raw;
    if ($filter_char || $filter_sender || $filter_receiver || $filter_type) {
        $filtered_transactions = array_filter($all_transactions_raw, function($tx) use ($filter_char, $filter_sender, $filter_receiver, $filter_type, $resolved_names_map) {
            if ($tx['amount'] < 0) { $sender_id = $tx['owner_char_id']; $receiver_id = $tx['first_party_id'] ?? null; } else { $sender_id = $tx['first_party_id'] ?? null; $receiver_id = $tx['owner_char_id']; }
            $sender_name = $resolved_names_map[$sender_id]['name'] ?? 'Unknown';
            $receiver_name = $resolved_names_map[$receiver_id]['name'] ?? 'Unknown';

            if ($filter_char && $tx['owner_char_name'] !== $filter_char) return false;
            if ($filter_sender && $sender_name !== $filter_sender) return false;
            if ($filter_receiver && $receiver_name !== $filter_receiver) return false;
            if ($filter_type && $tx['ref_type'] !== $filter_type) return false;
            
            return true;
        });
    }

    $all_transactions = $filtered_transactions; // Use the filtered list from now on

    // 4. Prepare remaining lookups and render table
    $authenticated_ids_map = esp_get_all_authenticated_character_ids_map();
    $character_ids_for_logos = [];
    foreach($resolved_names_map as $id => $details) { if (isset($details['category']) && $details['category'] === 'character') { $character_ids_for_logos[] = $id; } }
    $resolved_logos_map = esp_resolve_character_corp_logos($character_ids_for_logos, $user_id);

    usort($all_transactions, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
    $all_transactions = array_slice($all_transactions, 0, 250);

    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead><tr><th><?php esc_html_e('Character', 'eve-skill-plugin'); ?></th><th><?php esc_html_e('Date', 'eve-skill-plugin'); ?></th><th><?php esc_html_e('Sender', 'eve-skill-plugin'); ?></th><th><?php esc_html_e('Receiver', 'eve-skill-plugin'); ?></th><th><?php esc_html_e('Type', 'eve-skill-plugin'); ?></th><th style="text-align:right;"><?php esc_html_e('Amount', 'eve-skill-plugin'); ?></th></tr></thead>
        <tbody>
            <?php if (empty($all_transactions)) : ?>
                <tr><td colspan="6"><?php esc_html_e('No transactions match the current filter.', 'eve-skill-plugin'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($all_transactions as $tx) : ?>
                    <?php
                        if ($tx['amount'] < 0) { $sender_id = $tx['owner_char_id']; $receiver_id = $tx['first_party_id'] ?? null; } else { $sender_id = $tx['first_party_id'] ?? null; $receiver_id = $tx['owner_char_id']; }
                        $owner_logo_url = $resolved_logos_map[$tx['owner_char_id']] ?? '';
                        $sender_logo_url = isset($resolved_logos_map[$sender_id]) ? $resolved_logos_map[$sender_id] : '';
                        $receiver_logo_url = isset($resolved_logos_map[$receiver_id]) ? $resolved_logos_map[$receiver_id] : '';
                        $sender_name_data = $resolved_names_map[$sender_id] ?? ['name' => 'Unknown', 'category' => ''];
                        $receiver_name_data = $resolved_names_map[$receiver_id] ?? ['name' => 'Unknown', 'category' => ''];
                        $sender_class = isset($authenticated_ids_map[$sender_id]) ? 'authenticated-char' : 'unauthenticated-char';
						$receiver_class = isset($authenticated_ids_map[$receiver_id]) ? 'authenticated-char' : 'unauthenticated-char';
                        $sender_link = ($sender_id && $sender_name_data['category'] === 'character') ? "https://zkillboard.com/character/{$sender_id}/" : '';
                        $receiver_link = ($receiver_id && $receiver_name_data['category'] === 'character') ? "https://zkillboard.com/character/{$receiver_id}/" : '';
                    ?>
                    <tr>
                        <td><?php if ($owner_logo_url): ?><img src="<?php echo esc_url($owner_logo_url); ?>" class="corp-logo" alt=""><?php endif; ?><?php echo esc_html($tx['owner_char_name']); ?></td>
                        <td><?php echo esc_html(wp_date('Y-m-d H:i', strtotime($tx['date']))); ?></td>
                        <td><?php if ($sender_logo_url): ?><img src="<?php echo esc_url($sender_logo_url); ?>" class="corp-logo" alt=""><?php endif; ?><?php if ($sender_link): ?><a href="<?php echo esc_url($sender_link); ?>" target="_blank" class="<?php echo esc_attr($sender_class); ?>"><?php echo esc_html($sender_name_data['name']); ?></a><?php else: ?><span class="<?php echo esc_attr($sender_class); ?>"><?php echo esc_html($sender_name_data['name']); ?></span><?php endif; ?></td>
                        <td><?php if ($receiver_logo_url): ?><img src="<?php echo esc_url($receiver_logo_url); ?>" class="corp-logo" alt=""><?php endif; ?><?php if ($receiver_link): ?><a href="<?php echo esc_url($receiver_link); ?>" target="_blank" class="<?php echo esc_attr($receiver_class); ?>"><?php echo esc_html($receiver_name_data['name']); ?></a><?php else: ?><span class="<?php echo esc_attr($receiver_class); ?>"><?php echo esc_html($receiver_name_data['name']); ?></span><?php endif; ?></td>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $tx['ref_type']))); ?></td>
                        <td style="text-align:right; color: <?php echo ($tx['amount'] < 0 ? '#dc3232' : '#46b450'); ?>;"><?php echo esc_html(number_format(abs($tx['amount']), 2)); ?> ISK</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    echo '</div>';
}


/**
 * Renders a filterable table of assets for a given user's characters,
 * with enhanced logic for wormhole and container locations.
 */
function esp_display_user_assets_table($user_id) {
    echo '<div class="assets-viewer">';

    // 1. Fetch and consolidate all asset data
    $all_assets_raw = [];
    $characters_to_check = [];
    $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
    if ($main_char_id) { $characters_to_check[] = esp_get_character_data($user_id, $main_char_id); }
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
    if (is_array($alt_characters)) {
        foreach ($alt_characters as $alt) { if (isset($alt['id'])) { $characters_to_check[] = esp_get_character_data($user_id, $alt['id']); } }
    }

    $item_map = []; // Used to trace containers
    foreach ($characters_to_check as $character) {
        if (empty($character) || empty($character['assets_data'])) continue;
        foreach ($character['assets_data'] as $asset) {
            $asset['char_name'] = $character['name'];
            $asset['char_id'] = $character['id'];
            $asset['access_token'] = $character['access_token'];
            $all_assets_raw[] = $asset;
            $item_map[$asset['item_id']] = $asset;
        }
    }
    
    if (empty($all_assets_raw)) {
        echo '<p>' . esc_html__('No asset data found for this user.', 'eve-skill-plugin') . '</p></div>';
        return;
    }

    // 2. Pre-process assets to resolve final location and container status
    $assets_with_resolved_loc = [];
    foreach($all_assets_raw as $asset) {
        $current_asset = $asset;
        $is_in_container = false;
        
        while ($current_asset['location_type'] === 'item') {
            $is_in_container = true;
            if (isset($item_map[$current_asset['location_id']])) {
                $current_asset = $item_map[$current_asset['location_id']];
            } else {
                break;
            }
        }
        
        $asset['resolved_location_id'] = $current_asset['location_id'];
        $asset['resolved_location_type'] = $current_asset['location_type'];
        $asset['is_in_container'] = $is_in_container;
        $assets_with_resolved_loc[] = $asset;
    }

    // 3. Gather unique values for filters from the resolved data
    $unique_chars = []; $unique_items = []; $unique_locations = [];
    foreach($assets_with_resolved_loc as $asset) {
        $unique_chars[$asset['char_name']] = true;
        $unique_items[esp_get_item_name($asset['type_id'])] = true;
        $location_name = esp_get_resolved_location_name($asset['resolved_location_id'], $asset['resolved_location_type'], $asset['access_token']);
        $unique_locations[$location_name] = true;
    }
    ksort($unique_chars); ksort($unique_items); ksort($unique_locations);

    // 4. Get current filter values from URL
    $filter_char = isset($_GET['filter_asset_char']) ? sanitize_text_field($_GET['filter_asset_char']) : '';
    $filter_item = isset($_GET['filter_asset_item']) ? sanitize_text_field($_GET['filter_asset_item']) : '';
    $filter_location = isset($_GET['filter_asset_location']) ? sanitize_text_field($_GET['filter_asset_location']) : '';
    
    ?>
    <form method="get" class="esp-filters">
        <input type="hidden" name="page" value="eve_view_all_user_skills">
        <input type="hidden" name="view_user_id" value="<?php echo esc_attr($user_id); ?>">
        <input type="hidden" name="tab" value="assets">
        
        <label><?php esc_html_e('Character:', 'eve-skill-plugin'); ?>
            <select name="filter_asset_char">
                <option value=""><?php esc_html_e('All', 'eve-skill-plugin'); ?></option>
                <?php foreach (array_keys($unique_chars) as $val) { printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($filter_char, $val, false), esc_html($val)); } ?>
            </select>
        </label>

        <label><?php esc_html_e('Item Name:', 'eve-skill-plugin'); ?>
            <select name="filter_asset_item">
                <option value=""><?php esc_html_e('All', 'eve-skill-plugin'); ?></option>
                <?php foreach (array_keys($unique_items) as $val) { printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($filter_item, $val, false), esc_html($val)); } ?>
            </select>
        </label>

        <label><?php esc_html_e('Location:', 'eve-skill-plugin'); ?>
            <select name="filter_asset_location">
                <option value=""><?php esc_html_e('All', 'eve-skill-plugin'); ?></option>
                <?php foreach (array_keys($unique_locations) as $val) { printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($filter_location, $val, false), esc_html($val)); } ?>
            </select>
        </label>

        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Filter', 'eve-skill-plugin'); ?>">
        <a href="<?php echo esc_url(admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id . '&tab=assets')); ?>" class="button"><?php esc_html_e('Clear', 'eve-skill-plugin'); ?></a>
    </form>
    
    <?php
    // 5. Apply filters
    $filtered_assets = $assets_with_resolved_loc;
    if ($filter_char || $filter_item || $filter_location) {
        $filtered_assets = array_filter($assets_with_resolved_loc, function($asset) use ($filter_char, $filter_item, $filter_location) {
            $item_name = esp_get_item_name($asset['type_id']);
            $location_name = esp_get_resolved_location_name($asset['resolved_location_id'], $asset['resolved_location_type'], $asset['access_token']);
            if ($filter_char && $asset['char_name'] !== $filter_char) return false;
            if ($filter_item && $item_name !== $filter_item) return false;
            if ($filter_location && $location_name !== $filter_location) return false;
            return true;
        });
    }

    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Character', 'eve-skill-plugin'); ?></th>
                <th><?php esc_html_e('Item Name', 'eve-skill-plugin'); ?></th>
                <th style="text-align:right;"><?php esc_html_e('Quantity', 'eve-skill-plugin'); ?></th>
                <th><?php esc_html_e('Location', 'eve-skill-plugin'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filtered_assets)) : ?>
                <tr><td colspan="4"><?php esc_html_e('No assets match the current filter.', 'eve-skill-plugin'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($filtered_assets as $asset) : 
                    $location_name = esp_get_resolved_location_name($asset['resolved_location_id'], $asset['resolved_location_type'], $asset['access_token']);
                    $location_display = (strpos($location_name, 'J') === 0 && $asset['is_in_container']) ? $location_name . ' (Container)' : $location_name;
                ?>
                    <tr>
                        <td><?php echo esc_html($asset['char_name']); ?></td>
                        <td><?php echo esc_html(esp_get_item_name($asset['type_id'])); ?></td>
                        <td style="text-align:right;"><?php echo esc_html(number_format($asset['quantity'])); ?></td>
                        <td><?php echo esc_html($location_display); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    echo '</div>';
}



// CHANGE END

function esp_render_view_all_user_skills_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions.', 'eve-skill-plugin' ) ); }
    ?>
    <div class="wrap esp-admin-view">
        <h1><?php esc_html_e( 'View User EVE Characters', 'eve-skill-plugin' ); ?></h1>
        <style>
            .esp-admin-view .char-tree { list-style-type: none; width: 49%; padding-left: 0; }
            .esp-admin-view .char-tree ul { list-style-type: none; padding-left: 20px; margin-left: 10px; border-left: 1px dashed #ccc; }
            .esp-admin-view .char-item { padding: 5px 0; }
            .esp-admin-view .char-item strong { font-size: 1.1em; }
            .esp-admin-view .char-meta { font-size: 0.9em; color: #555; margin-left: 10px; }
            .esp-admin-view .char-actions a, .esp-admin-view .char-actions .button, .esp-admin-view .char-actions form { margin-left: 10px; display: inline-block; vertical-align: middle;}
            .esp-admin-view .main-char-item { border: 1px solid #0073aa; padding: 10px; margin-bottom:15px; background: #f7fcfe; }
            .esp-admin-view .alt-list-heading { margin-top: 15px; font-weight: bold; }
            .esp-admin-view .admin-action-button { padding: 2px 5px !important; font-size: 0.8em !important; line-height: 1.2 !important; height: auto !important; min-height: 0 !important;}
            .esp-admin-view .assign-alt-form select {vertical-align: baseline; margin: 0 5px;}
        </style>
        <?php
        $selected_user_id = isset( $_GET['view_user_id'] ) ? intval( $_GET['view_user_id'] ) : 0;
        
        if ( $selected_user_id > 0 ) {
            $user_info = get_userdata( $selected_user_id );
            if ( ! $user_info ) {
                echo '<p>' . esc_html__( 'WordPress user not found.', 'eve-skill-plugin' ) . '</p>';
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eve_view_all_user_skills' ) ) . '">« ' . esc_html__( 'Back to all users list', 'eve-skill-plugin' ) . '</a></p>';
                echo '</div>'; return;
            }
            echo '<h2>' . sprintf( esc_html__( 'EVE Characters for: %s', 'eve-skill-plugin' ), esc_html( $user_info->display_name ) ) . ' (' . esc_html($user_info->user_login) . ')</h2>';
			?>
<div style="margin: 15px 0;">
    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block;">
        <input type="hidden" name="action" value="esp_force_refresh">
        <input type="hidden" name="user_id_to_refresh" value="<?php echo esc_attr($selected_user_id); ?>">
        <?php wp_nonce_field( 'esp_force_refresh_action', 'esp_force_refresh_nonce' ); ?>
        <?php submit_button( __( 'Force Data Refresh', 'eve-skill-plugin' ), 'primary small', 'submit_force_refresh', false ); ?>
    </form>
</div>
<?php
            wp_nonce_field( 'esp_view_skills_nonce', 'esp_view_skills_nonce' );

            $main_char_id = get_user_meta( $selected_user_id, 'esp_main_eve_character_id', true );
            $alt_characters = get_user_meta($selected_user_id, 'esp_alt_characters', true);
            if (!is_array($alt_characters)) $alt_characters = [];
            $all_doctrine_names = [];
            $all_doctrine_objects = get_option( 'esp_doctrine_ships', [] );
            if (is_array($all_doctrine_objects) && !empty($all_doctrine_objects)) { foreach ($all_doctrine_objects as $doctrine_item) { if (isset($doctrine_item['ship_name'])) { $all_doctrine_names[] = $doctrine_item['ship_name']; } } sort($all_doctrine_names); }

            echo '<ul class="char-tree">';
			
			

            if ( $main_char_id ) {
                $main_char_name = get_user_meta( $selected_user_id, 'esp_main_eve_character_name', true );
                $main_total_sp = get_user_meta( $selected_user_id, 'esp_main_total_sp', true );
                $main_last_updated = get_user_meta( $selected_user_id, 'esp_main_skills_last_updated', true );
                echo '<li class="char-item main-char-item">';
                echo '<strong>MAIN:</strong> ' . esc_html( $main_char_name ) . ' (ID: ' . esc_html( $main_char_id ) . ')';
                echo '<div class="char-meta">Total SP: ' . esc_html( number_format( (float) $main_total_sp ) ); if ($main_last_updated) echo ' | Last Updated: ' . esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$main_last_updated)); echo '</div>';
                if (!empty($all_doctrine_names)) { $admin_main_compliant_doctrines = esp_get_character_compliant_doctrines( $selected_user_id, $main_char_id ); $admin_main_non_compliant_doctrines = array_diff( $all_doctrine_names, $admin_main_compliant_doctrines ); if ( ! empty( $admin_main_compliant_doctrines ) ) { echo '<div class="char-meta" style="margin-top: 5px;"><strong style="color: #6aa84f;">' . esc_html__( 'Can fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $admin_main_compliant_doctrines ) ) . '</div>'; } if ( ! empty( $admin_main_non_compliant_doctrines ) ) { echo '<div class="char-meta" style="margin-top: 5px;"><strong style="color: #dc3232;">' . esc_html__( 'Cannot fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $admin_main_non_compliant_doctrines ) ) . '</div>'; } }
                echo '<div class="char-actions">';
                echo '<button class="button esp-view-skills-btn" data-userid="'.esc_attr($selected_user_id).'" data-charid="'.esc_attr($main_char_id).'">'.esc_html__('View Skills', 'eve-skill-plugin').'</button>';
                if (current_user_can('manage_options') && empty($alt_characters)) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="assign-alt-form" style="margin-top: 5px;"><input type="hidden" name="action" value="esp_admin_reassign_character"><input type="hidden" name="original_wp_user_id" value="'.esc_attr($selected_user_id).'"><input type="hidden" name="character_id_to_move" value="'.esc_attr($main_char_id).'"><input type="hidden" name="character_type_to_move" value="main">'; wp_nonce_field('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce'); $select_id = 'reassign_main_to_user_'.esc_attr($selected_user_id).'_'.esc_attr($main_char_id); echo '<label for="'.esc_attr($select_id).'" class="screen-reader-text">' . esc_html__('Assign this Main to different User as Alt:', 'eve-skill-plugin') . '</label><select name="new_main_wp_user_id" id="'.esc_attr($select_id).'"><option value="">' . esc_html__('-- Select Target User --', 'eve-skill-plugin') . '</option>'; $all_potential_main_users_args = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all_with_meta', 'exclude' => [$selected_user_id] ]; $potential_main_users = get_users($all_potential_main_users_args); foreach ($potential_main_users as $potential_user) { $potential_main_char_name = get_user_meta($potential_user->ID, 'esp_main_eve_character_name', true); if ($potential_main_char_name) { echo '<option value="'.esc_attr($potential_user->ID).'">'.esc_html($potential_user->display_name . ' (' . $potential_main_char_name . ')').'</option>'; } } echo '</select>'; submit_button(__('Assign as Alt', 'eve-skill-plugin'), 'secondary small admin-action-button', 'reassign_main', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to re-assign this main character as an alt to the selected user? This user will then have no main character.', 'eve-skill-plugin')).'");']); echo '</form>'; }
                echo '</div>';
                if (is_array($alt_characters) && !empty($alt_characters)) {
                    echo '<div class="alt-list-heading">' . esc_html__('ALTS:', 'eve-skill-plugin') . '</div><ul>';
                    foreach ($alt_characters as $alt_char) {
                        if (!is_array($alt_char) || !isset($alt_char['id']) || !isset($alt_char['name'])) continue;
                        echo '<li class="char-item">';
                        echo esc_html( $alt_char['name'] ) . ' (ID: ' . esc_html( $alt_char['id'] ) . ')';
                        echo '<div class="char-meta">Total SP: ' . esc_html( number_format( (float) ($alt_char['total_sp'] ?? 0) ) ); if (!empty($alt_char['skills_last_updated'])) echo ' | Last Updated: ' . esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$alt_char['skills_last_updated'])); echo '</div>';
                        if (!empty($all_doctrine_names)) { $admin_alt_compliant_doctrines = esp_get_character_compliant_doctrines( $selected_user_id, $alt_char['id'] ); $admin_alt_non_compliant_doctrines = array_diff( $all_doctrine_names, $admin_alt_compliant_doctrines ); if ( ! empty( $admin_alt_compliant_doctrines ) ) { echo '<div class="char-meta" style="margin-top: 5px;"><strong style="color: #6aa84f;">' . esc_html__( 'Can fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $admin_alt_compliant_doctrines ) ) . '</div>'; } if ( ! empty( $admin_alt_non_compliant_doctrines ) ) { echo '<div class="char-meta" style="margin-top: 5px;"><strong style="color: #dc3232;">' . esc_html__( 'Cannot fly:', 'eve-skill-plugin' ) . '</strong> ' . esc_html( implode( ', ', $admin_alt_non_compliant_doctrines ) ) . '</div>'; } }
                        echo '<div class="char-actions">';
                        echo '<button class="button esp-view-skills-btn" data-userid="'.esc_attr($selected_user_id).'" data-charid="'.esc_attr($alt_char['id']).'">'.esc_html__('View Skills', 'eve-skill-plugin').'</button>';
                        if (current_user_can('manage_options')) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;"><input type="hidden" name="action" value="esp_admin_promote_alt_to_main"><input type="hidden" name="user_id_to_affect" value="'.esc_attr($selected_user_id).'"><input type="hidden" name="alt_char_id_to_promote" value="'.esc_attr($alt_char['id']).'">'; wp_nonce_field('esp_admin_promote_alt_action', 'esp_admin_promote_alt_nonce'); submit_button(__('Promote to Main', 'eve-skill-plugin'), 'secondary small admin-action-button', 'promote_alt_'.esc_attr($alt_char['id']), false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to promote this alt to main? The current main will become an alt.', 'eve-skill-plugin')).'");']); echo '</form>'; echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;"><input type="hidden" name="action" value="esp_admin_remove_user_alt_character"><input type="hidden" name="user_id_to_affect" value="'.esc_attr($selected_user_id).'"><input type="hidden" name="alt_char_id_to_remove" value="'.esc_attr($alt_char['id']).'">'; wp_nonce_field('esp_admin_remove_alt_action', 'esp_admin_remove_alt_nonce'); submit_button(__('Remove Alt', 'eve-skill-plugin'), 'delete small admin-action-button', 'remove_alt_'.esc_attr($alt_char['id']), false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove this alt from this user?', 'eve-skill-plugin')).'");']); echo '</form>'; echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="assign-alt-form" style="display:inline-block;"><input type="hidden" name="action" value="esp_admin_reassign_character"><input type="hidden" name="original_wp_user_id" value="'.esc_attr($selected_user_id).'"><input type="hidden" name="character_id_to_move" value="'.esc_attr($alt_char['id']).'"><input type="hidden" name="character_type_to_move" value="alt">'; wp_nonce_field('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce'); $select_alt_id = 'reassign_alt_to_user_'.esc_attr($selected_user_id).'_'.esc_attr($alt_char['id']); echo '<label for="'.esc_attr($select_alt_id).'" class="screen-reader-text">' . esc_html__('Assign Alt to different Main User:', 'eve-skill-plugin') . '</label><select name="new_main_wp_user_id" id="'.esc_attr($select_alt_id).'"><option value="">' . esc_html__('-- Select Target User --', 'eve-skill-plugin') . '</option>'; $all_potential_main_users_args_alt = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all_with_meta', 'exclude' => [$selected_user_id] ]; $potential_main_users_alt = get_users($all_potential_main_users_args_alt); foreach ($potential_main_users_alt as $potential_user_alt) { $potential_main_char_name_alt = get_user_meta($potential_user_alt->ID, 'esp_main_eve_character_name', true); if ($potential_main_char_name_alt) { echo '<option value="'.esc_attr($potential_user_alt->ID).'">'.esc_html($potential_user_alt->display_name . ' (' . $potential_main_char_name_alt . ')').'</option>'; } } echo '</select>'; submit_button(__('Assign Alt', 'eve-skill-plugin'), 'secondary small admin-action-button', 'reassign_alt_'.esc_attr($alt_char['id']), false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to re-assign this alt to the selected main character\'s account?', 'eve-skill-plugin')).'");']); echo '</form>'; }
                        echo '</div></li>';
                    } echo '</ul>';
                }
                echo '</li>';
            } else { echo '<li>' . esc_html__( 'No main EVE character linked for this user.', 'eve-skill-plugin' ) . '</li>'; }
            echo '</ul>';
            
           // This is the HTML block for the chart.
            echo '<hr style="margin: 30px 0;" />';
            ?>
		
            <div id="esp-wallet-chart-container">
                <h2><?php esc_html_e('Wallet Balance History (Last 90 Days)', 'eve-skill-plugin'); ?></h2>
                <div>
                    <button class="button" data-range="90"><?php esc_html_e('90 Days', 'eve-skill-plugin'); ?></button>
                    <button class="button" data-range="30"><?php esc_html_e('30 Days', 'eve-skill-plugin'); ?></button>
                    <button class="button" data-range="7"><?php esc_html_e('7 Days', 'eve-skill-plugin'); ?></button>
                </div>
                <div id="esp-chart-wrapper" style="position: relative; height: 302px; width: 100%;">
                    <canvas id="espWalletChart"></canvas>
                </div>
                <p id="esp-chart-message" style="text-align: center; padding: 20px;"></p>
            </div>
            <?php wp_nonce_field('esp_wallet_chart_nonce', 'esp_wallet_chart_nonce'); ?>

                        <!-- NEW TABBED INTERFACE START -->
            <style>
                .esp-tabs-nav { display: flex; border-bottom: 2px solid #ccc; margin-bottom: -2px; padding-left: 0; }
                .esp-tabs-nav li { list-style: none; margin-bottom: 0; }
                .esp-tabs-nav a { display: block; padding: 10px 15px; border: 2px solid transparent; text-decoration: none; color: #555; font-size: 1.1em; }
                .esp-tabs-nav li.active a { border-color: #ccc #ccc #fff #ccc; background: #fff; font-weight: bold; color: #000; }
                .esp-tab-content { display: none; border: 2px solid #ccc; border-top: none; padding: 20px; background: #fff; }
                .esp-tab-content.active { display: block; }
            </style>

            <?php
            // Get the active tab from the URL, defaulting to 'transactions'.
            $active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['transactions', 'assets']) ? sanitize_key($_GET['tab']) : 'transactions';
            ?>

            <ul class="esp-tabs-nav">
                <li class="<?php echo $active_tab === 'transactions' ? 'active' : ''; ?>"><a href="#tab-transactions"><?php esc_html_e('Recent Wallet Transactions', 'eve-skill-plugin'); ?></a></li>
                <li class="<?php echo $active_tab === 'assets' ? 'active' : ''; ?>"><a href="#tab-assets"><?php esc_html_e('Assets', 'eve-skill-plugin'); ?></a></li>
            </ul>

            <div id="tab-transactions" class="esp-tab-content <?php echo $active_tab === 'transactions' ? 'active' : ''; ?>">
                <?php esp_display_user_transactions_table($selected_user_id); ?>
            </div>
            <div id="tab-assets" class="esp-tab-content <?php echo $active_tab === 'assets' ? 'active' : ''; ?>">
                <?php esp_display_user_assets_table($selected_user_id); ?>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const tabs = document.querySelectorAll('.esp-tabs-nav a');
                tabs.forEach(tab => {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        const targetId = this.getAttribute('href');
                        
                        document.querySelectorAll('.esp-tabs-nav li').forEach(li => li.classList.remove('active'));
                        this.parentElement.classList.add('active');
                        
                        document.querySelectorAll('.esp-tab-content').forEach(panel => panel.classList.remove('active'));
                        document.querySelector(targetId).classList.add('active');

                        // Also update the URL in the browser bar without reloading the page
                        const url = new URL(window.location);
                        const tabName = targetId.substring(5); // gets 'transactions' or 'assets'
                        url.searchParams.set('tab', tabName);
                        window.history.pushState({}, '', url);
                    });
                });
            });
            </script>
            <!-- NEW TABBED INTERFACE END -->
            <?php
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eve_view_all_user_skills' ) ) . '">« ' . esc_html__( 'Back to all users list', 'eve-skill-plugin' ) . '</a></p>';
        } else {
            // This ELSE block shows the list of all users on the initial page load.
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
            } else { 
                echo '<p>' . esc_html__( 'No users have linked their main EVE character yet.', 'eve-skill-plugin' ) . '</p>'; 
            }
        } ?>
    </div> <?php
}



// --- ESI DATA FETCHERS & HELPERS ---
/**
 * Resolves an EVE Online skill ID to its name.
 *
 * This function uses WordPress transients to cache the results for 30 days,
 * as skill names are static and this avoids unnecessary ESI lookups.
 *
 * @param int $skill_id The EVE skill type ID.
 * @return string       The name of the skill, or an error/status string on failure.
 */
function esp_get_skill_name( $skill_id ) { 
    $skill_id = intval($skill_id); 
    if ($skill_id <= 0) {
        return "Invalid Skill ID";
    }

    $transient_key = 'esp_skill_name_' . $skill_id; 
    $skill_name = get_transient( $transient_key ); 
    
    if ( false === $skill_name ) { 
        $request_url = "https://esi.evetech.net/latest/universe/types/{$skill_id}/?datasource=tranquility";
        $response = wp_remote_get( $request_url, [
            'headers' => [
                'User-Agent'      => EVE_SKILL_PLUGIN_USER_AGENT,
                'Accept-Language' => 'en-us'
            ]
        ]); 
        
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) { 
            $type_data = json_decode( wp_remote_retrieve_body( $response ), true ); 
            if ( isset( $type_data['name'] ) ) { 
                $skill_name = $type_data['name']; 
                set_transient( $transient_key, $skill_name, DAY_IN_SECONDS * 30 ); 
            } else { 
                $skill_name = "Unknown Skill (ID: {$skill_id})"; 
            } 
        } else { 
            $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
            error_log("[ESP] Failed to get skill name for skill_id {$skill_id}. ESI Error: {$error_message}");
            $skill_name = "Skill ID: {$skill_id} (Lookup Failed)"; 
        } 
    } 
    return $skill_name; 
}
/**
 * Resolves an EVE Online item type ID to its name.
 *
 * This function uses WordPress transients to cache the results for 30 days,
 * as item names are static and this avoids unnecessary ESI lookups.
 *
 * @param int $type_id The EVE item type ID.
 * @return string      The name of the item, or an error/status string on failure.
 */
function esp_get_item_name( $type_id ) {
    $type_id = intval($type_id); 
    if ($type_id <= 0) {
        return "Invalid Type ID";
    }

    $transient_key = 'esp_item_name_' . $type_id; 
    $item_name = get_transient( $transient_key );
    
    if ( false === $item_name ) {
        $request_url = "https://esi.evetech.net/latest/universe/types/{$type_id}/?datasource=tranquility";
        $response = wp_remote_get( $request_url, [
            'headers' => [
                'User-Agent'      => EVE_SKILL_PLUGIN_USER_AGENT, 
                'Accept-Language' => 'en-us'
            ]
        ]);

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $type_data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $type_data['name'] ) ) { 
                $item_name = $type_data['name']; 
                set_transient( $transient_key, $item_name, DAY_IN_SECONDS * 30 ); 
            } else { 
                $item_name = "Unknown Item (ID: {$type_id})"; 
            }
        } else {
            $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
            error_log("[ESP] Failed to get item name for type_id {$type_id}. ESI Error: {$error_message}");
            $item_name = "Item ID: {$type_id} (Lookup Failed)";
        }
    } 
    return $item_name;
}

/**
 * Resolves a location ID to a meaningful name, with robust handling for
 * stations, private structures (using tokens), and solar systems.
 *
 * @param int         $location_id   The ID of the location.
 * @param string      $location_type The type of location ('station', 'structure', 'solar_system').
 * @param string|null $access_token  A valid ESI access token, required for private structures.
 * @return string                    The resolved location name.
 */
function esp_get_resolved_location_name($location_id, $location_type, $access_token) {
    if (empty($location_id)) {
        return 'Unknown Location';
    }

    $transient_key = 'esp_resolved_loc_name_' . $location_id;
    $location_name = get_transient($transient_key);

    if (false === $location_name) {
        $location_name = "ID: {$location_id}"; // Default fallback

        // Case 1: The location is a public station
        if ($location_type === 'station') {
            $station_url = "https://esi.evetech.net/latest/universe/stations/{$location_id}/?datasource=tranquility";
            $response = wp_remote_get($station_url, ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT]]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $location_name = $data['name'] ?? $location_name;
            }
        } 
        // Case 2: The location is a structure (requires authentication)
        elseif ($location_type === 'structure' && !empty($access_token)) {
            $structure_url = "https://esi.evetech.net/latest/universe/structures/{$location_id}/?datasource=tranquility";
            $headers = [
                'User-Agent'    => EVE_SKILL_PLUGIN_USER_AGENT,
                'Authorization' => 'Bearer ' . $access_token
            ];
            $response = wp_remote_get($structure_url, ['headers' => $headers]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $system_id = $data['solar_system_id'] ?? null;
                
                // For any structure, we want its solar system's name.
                if ($system_id) {
                    // This is a recursive call to resolve the system name, which will be cached.
                    $location_name = esp_get_resolved_location_name($system_id, 'solar_system', null);
                } else {
                    $location_name = $data['name'] ?? $location_name; // Fallback to structure name if system ID is missing
                }
            } else {
                 error_log("[ESP] Failed to resolve structure ID {$location_id}. Code: " . wp_remote_retrieve_response_code($response));
            }
        }
        // Case 3: The location is a solar system ID directly
        elseif ($location_type === 'solar_system') {
            $system_url = "https://esi.evetech.net/latest/universe/systems/{$location_id}/?datasource=tranquility";
            $response = wp_remote_get($system_url, ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT]]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $location_name = $data['name'] ?? $location_name;
            }
        }

        // Cache the final resolved name for 1 week to minimize API calls.
        set_transient($transient_key, $location_name, WEEK_IN_SECONDS);
    }
    
    return $location_name;
}


/**
 * Fetches and caches a map of EVE Online skill groups and the skills within them.
 *
 * @return array A map where keys are category names and values are arrays of skill IDs.
 */
function esp_get_skill_category_map() {
    $transient_key = 'esp_skill_category_map_v2';
    $skill_map = get_transient($transient_key);

    if (false === $skill_map) {
        $skill_map = [];
        $skill_category_id = 16; // The static ID for the "Skill" category in ESI.

        $category_url = "https://esi.evetech.net/latest/universe/categories/{$skill_category_id}/?datasource=tranquility";
        $category_response = wp_remote_get($category_url, ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT]]);

        if (is_wp_error($category_response) || wp_remote_retrieve_response_code($category_response) !== 200) {
            error_log('[ESP] Failed to fetch skill category details from ESI.');
            return [];
        }

        $category_data = json_decode(wp_remote_retrieve_body($category_response), true);
        $skill_group_ids = $category_data['groups'] ?? [];

        foreach ($skill_group_ids as $group_id) {
            $group_url = "https://esi.evetech.net/latest/universe/groups/{$group_id}/?datasource=tranquility";
            $group_response = wp_remote_get($group_url, ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT]]);

            if (!is_wp_error($group_response) && wp_remote_retrieve_response_code($group_response) === 200) {
                $group_data = json_decode(wp_remote_retrieve_body($group_response), true);
                $group_name = $group_data['name'] ?? 'Unknown Group';
                $skill_ids_in_group = $group_data['types'] ?? [];

                if (!empty($skill_ids_in_group) && ($group_data['published'] ?? false)) {
                    $skill_map[$group_name] = $skill_ids_in_group;
                }
            }
            sleep(0.1); 
        }
        set_transient($transient_key, $skill_map, WEEK_IN_SECONDS);
    }
    return $skill_map;
}


// --- SSO AUTHENTICATION FLOW ---
/**
 * Initiates the EVE Online SSO authentication flow.
 *
 * This function is triggered when a user clicks an "Authenticate" button. It handles
 * security checks, prepares the user's session with a unique state nonce and
 * redirect information, and then redirects the user to the EVE SSO login page.
 */
function esp_handle_sso_initiation() {
    // 1. Security Check: Verify the nonce sent from the login form.
    if ( ! isset( $_POST['esp_initiate_sso_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['esp_initiate_sso_nonce']), 'esp_initiate_sso_action' ) ) { 
        wp_die( 'Nonce verification failed! Please go back and try again.' ); 
    }

    // 2. Ensure a session is active to store temporary data.
    esp_start_session_if_needed(); 

    // 3. Get required plugin settings.
    $client_id = get_option( 'esp_client_id' ); 
    $scopes = get_option( 'esp_scopes', ESP_DEFAULT_SCOPES ); 
    
    // Abort if the administrator has not configured the plugin.
    if ( ! $client_id ) { 
        wp_die( 'EVE Client ID has not been configured by the site administrator.' ); 
    }

    // 4. Determine the context of the authentication (main character, alt, etc.).
    $auth_type = isset($_POST['esp_auth_type']) ? sanitize_key($_POST['esp_auth_type']) : 'main'; 
    $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : home_url();

    // 5. Generate a unique, unpredictable 'state' value for CSRF protection.
    $sso_state_value = bin2hex( random_bytes( 16 ) ); 
    
    // Store the state and other necessary info in the session to verify upon callback.
    $_SESSION[ESP_SSO_SESSION_KEY] = [ 
        'nonce'        => $sso_state_value, 
        'redirect_url' => $redirect_back_url, 
        'auth_type'    => $auth_type 
    ];

    // 6. Build the full URL for the EVE SSO endpoint.
    $sso_redirect_uri_to_eve = admin_url( 'admin-post.php?action=' . ESP_SSO_CALLBACK_ACTION_NAME ); 
    $sso_url_to_eve = 'https://login.eveonline.com/v2/oauth/authorize/?' . http_build_query( [ 
        'response_type' => 'code', 
        'redirect_uri'  => $sso_redirect_uri_to_eve, 
        'client_id'     => $client_id, 
        'scope'         => $scopes, 
        'state'         => $sso_state_value, 
    ] );

    // 7. Redirect the user to EVE Online to authorize the application.
    wp_redirect( $sso_url_to_eve ); 
    exit;
}
add_action( 'admin_post_esp_initiate_sso', 'esp_handle_sso_initiation' ); 
add_action( 'admin_post_nopriv_esp_initiate_sso', 'esp_handle_sso_initiation' ); 
/**
 * Finds an existing WordPress user for an EVE character or creates a new one.
 *
 * Checks for a match first as a main character, then as an alt character. If no
 * user is found, it generates a new WordPress user with a unique username based
 * on the character's details.
 *
 * @param int    $character_id   The EVE Online Character ID.
 * @param string $character_name The EVE Online Character Name.
 * @param string $owner_hash     The character's unique owner hash from ESI.
 * @return WP_User|WP_Error      The WP_User object on success, or a WP_Error on failure.
 */
function esp_get_or_create_wp_user_for_eve_char( $character_id, $character_name, $owner_hash ) { 
    // Check if a user exists with this character as their main.
    $existing_users = get_users( [ 
        'meta_key' => 'esp_main_eve_character_id', 
        'meta_value' => $character_id, 
        'number' => 1, 
        'count_total' => false 
    ]); 
    if ( ! empty( $existing_users ) ) { 
        return $existing_users[0]; 
    } 

    // Check if the character exists as an alt for any user.
    // This query is not perfectly performant on large sites but is acceptable here.
    $alt_users_query_args = [
        'meta_query' => [
            [
                'key' => 'esp_alt_characters',
                'value' => '"id";i:'.$character_id.';', // Searches for the serialized string pattern.
                'compare' => 'LIKE'
            ]
        ],
        'number' => 1,
        'count_total' => false
    ];
    $alt_users = get_users($alt_users_query_args);
    if (!empty($alt_users)) { 
        return $alt_users[0]; 
    } 

    // If no user is found, create a new one.
    $username = sanitize_user( 'eve_' . $character_id . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $character_name), true ); 
    $i = 0; 
    $base_username = $username; 
    while ( username_exists( $username ) ) { 
        $i++; 
        $username = $base_username . '_' . $i; 
    } 

    $random_password = wp_generate_password( 20, true, true ); 
    $email_domain_part = sanitize_title(str_replace(['http://', 'https://', 'www.'], '', get_bloginfo('url'))); 
    if (empty($email_domain_part)) {
        $email_domain_part = 'localhost.local'; // A more valid-looking placeholder
    }
    $email = sanitize_email($character_id . '@' . $email_domain_part . '.eve-sso.invalid'); 

    $new_user_data = [ 
        'user_login' => $username, 
        'user_pass'  => $random_password, 
        'user_email' => $email, 
        'display_name' => $character_name, 
        'role' => get_option('default_role', 'subscriber') 
    ]; 
    $new_user_id = wp_insert_user( $new_user_data ); 

    if ( is_wp_error( $new_user_id ) ) { 
        return $new_user_id; 
    } 

    update_user_meta( $new_user_id, 'created_via_eve_sso', time() ); 
    error_log("[ESP] Created new WP User ID $new_user_id ($username) for EVE Char $character_id ($character_name)"); 
    return get_user_by( 'id', $new_user_id ); 
}
/**
 * Handles the SSO callback from EVE Online.
 *
 * This function executes after a user authorizes the application on the EVE SSO page.
 * It performs security checks, exchanges the authorization code for an access token,
 * verifies the character, logs in or creates a WP user, and then fetches all
 * necessary data (skills, assets, wallet journal).
 */
function esp_handle_sso_callback() {
    esp_start_session_if_needed(); 
    if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) { wp_die( 'Invalid callback. Missing code/state.' ); }

    $code = sanitize_text_field( wp_unslash( $_GET['code'] ) ); 
    $received_sso_state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
    $stored_sso_data = $_SESSION[ESP_SSO_SESSION_KEY] ?? null;
    $auth_type = 'main'; 
    $redirect_url_on_error = home_url(); 
    
    if ($stored_sso_data) { 
        $redirect_url_on_error = $stored_sso_data['redirect_url'] ?? home_url(); 
        $auth_type = $stored_sso_data['auth_type'] ?? 'main'; 
    }

    if ( ! $stored_sso_data || !isset($stored_sso_data['nonce']) || !hash_equals($stored_sso_data['nonce'], $received_sso_state) ) { 
        unset($_SESSION[ESP_SSO_SESSION_KEY]); 
        wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_state_mismatch', $redirect_url_on_error ) ); 
        exit; 
    }

    unset($_SESSION[ESP_SSO_SESSION_KEY]); 
    $redirect_url_after_sso = $stored_sso_data['redirect_url'];
    $client_id = get_option( 'esp_client_id' ); 
    $client_secret = get_option( 'esp_client_secret' );
    if ( ! $client_id || ! $client_secret ) { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_no_config', $redirect_url_after_sso ) ); exit; }

    $token_url = 'https://login.eveonline.com/v2/oauth/token'; 
    $auth_header = base64_encode( $client_id . ':' . $client_secret );
    $response = wp_remote_post( $token_url, [ 
        'headers' => [ 'Authorization' => 'Basic ' . $auth_header, 'Content-Type'  => 'application/x-www-form-urlencoded', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT ], 
        'body' => [ 'grant_type' => 'authorization_code', 'code' => $code ],
        'timeout' => 20,
    ]);

    if ( is_wp_error( $response ) ) { error_log('[ESP] SSO Token WP Error: ' . $response->get_error_message()); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_token_wp_error', $redirect_url_after_sso ) ); exit; }
    
    $token_data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( wp_remote_retrieve_response_code( $response ) !== 200 || ! isset( $token_data['access_token'] ) ) { error_log('[ESP] SSO Token EVE Error: ' . wp_remote_retrieve_body( $response )); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_token_eve_error', $redirect_url_after_sso ) ); exit; }
    
    $access_token = $token_data['access_token']; 
    $refresh_token = $token_data['refresh_token']; 
    $expires_in = $token_data['expires_in'];
    
    $verify_url = 'https://login.eveonline.com/oauth/verify'; 
    $verify_response = wp_remote_get( $verify_url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $access_token, 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT ], 'timeout' => 20 ]);
    if ( is_wp_error( $verify_response ) ) { error_log('[ESP] SSO Verify WP Error: ' . $verify_response->get_error_message()); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_verify_wp_error', $redirect_url_after_sso ) ); exit; }
    
    $char_data_from_esi = json_decode( wp_remote_retrieve_body( $verify_response ), true );
    if ( wp_remote_retrieve_response_code( $verify_response ) !== 200 || ! isset( $char_data_from_esi['CharacterID'] ) ) { error_log('[ESP] SSO Verify EVE Error: ' . wp_remote_retrieve_body( $verify_response )); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_verify_eve_error', $redirect_url_after_sso ) ); exit; }
    
    $authed_character_id = (int)$char_data_from_esi['CharacterID']; 
    $authed_character_name = $char_data_from_esi['CharacterName']; 
    $authed_owner_hash = $char_data_from_esi['CharacterOwnerHash'];
    
    $user_id = 0; $is_new_wp_user = false;
    if ( is_user_logged_in() ) { 
        $user_id = get_current_user_id(); 
    } else { 
        $wp_user = esp_get_or_create_wp_user_for_eve_char( $authed_character_id, $authed_character_name, $authed_owner_hash );
        if ( is_wp_error( $wp_user ) ) { error_log( "[ESP] Error get/create WP user for EVE char $authed_character_id: " . $wp_user->get_error_message() ); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_wp_user_error', $redirect_url_after_sso ) ); exit; }
        $user_id = $wp_user->ID; 
        $is_new_wp_user = true;
        wp_set_current_user( $user_id, $wp_user->user_login ); 
        wp_set_auth_cookie( $user_id ); 
        do_action( 'wp_login', $wp_user->user_login, $wp_user );
    }

    if ( !$user_id ) { error_log("[ESP] Critical error: No WP user ID after EVE auth for char $authed_character_id"); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_internal_user_error', $redirect_url_after_sso ) ); exit; }
    
    $current_main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
    
    if ($auth_type === 'main' || !$current_main_char_id) { 
        update_user_meta( $user_id, 'esp_main_eve_character_id', $authed_character_id ); 
        update_user_meta( $user_id, 'esp_main_eve_character_name', $authed_character_name ); 
        update_user_meta( $user_id, 'esp_main_access_token', $access_token ); 
        update_user_meta( $user_id, 'esp_main_refresh_token', $refresh_token ); 
        update_user_meta( $user_id, 'esp_main_token_expires', time() + $expires_in ); 
        update_user_meta( $user_id, 'esp_main_owner_hash', $authed_owner_hash );
        esp_fetch_and_store_skills_for_character_type( $user_id, $authed_character_id, $access_token, 'main' );
        esp_fetch_and_store_assets_for_character_type( $user_id, $authed_character_id, $access_token, 'main' ); 
        esp_fetch_and_store_wallet_journal_for_character_type( $user_id, $authed_character_id, $access_token, 'main' );
    } elseif ($auth_type === 'alt') {
        if ($authed_character_id == $current_main_char_id) { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_alt_is_main', $redirect_url_after_sso ) ); exit; }
        esp_update_alt_character_data_item($user_id, $authed_character_id, 'access_token', $access_token, $authed_character_name, $authed_owner_hash);
        esp_update_alt_character_data_item($user_id, $authed_character_id, 'refresh_token', $refresh_token);
        esp_update_alt_character_data_item($user_id, $authed_character_id, 'token_expires', time() + $expires_in);
        esp_fetch_and_store_skills_for_character_type( $user_id, $authed_character_id, $access_token, 'alt' );
        esp_fetch_and_store_assets_for_character_type( $user_id, $authed_character_id, $access_token, 'alt' ); 
        esp_fetch_and_store_wallet_journal_for_character_type( $user_id, $authed_character_id, $access_token, 'alt' );
    } else { 
        wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_unknown_auth_type', $redirect_url_after_sso ) ); 
        exit; 
    }

    $final_redirect_url = $redirect_url_after_sso; 
    $message_key = ($auth_type === 'alt') ? 'sso_alt_success' : 'sso_success';
    if ($is_new_wp_user) $final_redirect_url = add_query_arg('new_user', 'true', $final_redirect_url); 
    wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, $message_key, $final_redirect_url ) ); 
    exit;
}
add_action( 'admin_post_nopriv_' . ESP_SSO_CALLBACK_ACTION_NAME, 'esp_handle_sso_callback' );
add_action( 'admin_post_' . ESP_SSO_CALLBACK_ACTION_NAME, 'esp_handle_sso_callback' );
// CHANGE START: Added new section for AJAX Handlers
// --- AJAX HANDLERS ---

/**
 * AJAX handler to fetch and return a character's skills in a categorized format.
 *
 * This function is called by JavaScript. It checks permissions, fetches character data,
 * categorizes the skills using helper functions, and returns the result as JSON.
 */
 /**
 * Organizes a character's raw skill list into categories.
 *
 * @param array $character_skills The raw skills array from character data.
 * @return array A categorized array of skills.
 */
function esp_categorize_character_skills($character_skills) {
    if (empty($character_skills) || !is_array($character_skills)) {
        return [];
    }

    $skill_category_map = esp_get_skill_category_map();
    if (empty($skill_category_map)) {
        $uncategorized = [];
        foreach ($character_skills as $skill) {
            $uncategorized[] = [
                'name'  => esp_get_skill_name((int)$skill['skill_id']),
                'level' => (int)$skill['active_skill_level']
            ];
        }
        return ['Uncategorized' => $uncategorized];
    }

    $skill_to_category_lookup = [];
    foreach ($skill_category_map as $category_name => $skill_ids) {
        foreach ($skill_ids as $skill_id) {
            $skill_to_category_lookup[$skill_id] = $category_name;
        }
    }

    $categorized_skills = [];
    foreach ($character_skills as $skill) {
        $skill_id = (int)$skill['skill_id'];
        $category = $skill_to_category_lookup[$skill_id] ?? 'Miscellaneous';

        if (!isset($categorized_skills[$category])) {
            $categorized_skills[$category] = [];
        }

        $categorized_skills[$category][] = [
            'name'  => esp_get_skill_name($skill_id),
            'level' => (int)$skill['active_skill_level']
        ];
    }
    
    ksort($categorized_skills);
    return $categorized_skills;
}
 
function esp_ajax_get_categorized_skills() {
    // 1. Security First: Verify the request
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'esp_view_skills_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
        return;
    }

    // Only logged-in users with at least 'read' capability can view skills.
    if (!is_user_logged_in() || !current_user_can('read')) {
        wp_send_json_error(['message' => 'You do not have permission to view this.'], 403);
        return;
    }

    // 2. Get the parameters from the AJAX request
    $user_id_to_view = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $char_id_to_view = isset($_POST['char_id']) ? intval($_POST['char_id']) : 0;

    if (!$user_id_to_view || !$char_id_to_view) {
        wp_send_json_error(['message' => 'Missing required parameters.'], 400);
        return;
    }
    
    // Make sure the current user is an admin OR is viewing their own character
    if (!current_user_can('manage_options') && $user_id_to_view !== get_current_user_id()) {
         wp_send_json_error(['message' => 'You can only view your own characters.'], 403);
         return;
    }

    // 3. Fetch, Process, and Return Data
    $character_data = esp_get_character_data($user_id_to_view, $char_id_to_view);

    if (!$character_data) {
        wp_send_json_error(['message' => 'Character not found.'], 404);
        return;
    }

    $raw_skills = $character_data['skills_data'] ?? [];
    $categorized_skills = esp_categorize_character_skills($raw_skills);

    // Add some extra helpful info to the response
    $response_data = [
        'character_name' => $character_data['name'] ?? 'Unknown',
        'total_sp'       => number_format((float)($character_data['total_sp'] ?? 0)),
        'skills'         => $categorized_skills,
    ];

    wp_send_json_success($response_data);
}
// Hook our new function into WordPress's AJAX system for logged-in users.
add_action('wp_ajax_esp_get_categorized_skills', 'esp_ajax_get_categorized_skills');
/**
 * Gathers and formats wallet history for all characters of a given user.
 *
 * @param int $user_id The WordPress user ID.
 * @return array       An array formatted for Chart.js, containing labels and datasets.
 */
function esp_get_wallet_chart_data_for_user($user_id) {
    $characters_to_check = [];
    $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
    if ($main_char_id) { $characters_to_check[] = esp_get_character_data($user_id, $main_char_id); }
    $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
    if (is_array($alt_characters)) {
        foreach ($alt_characters as $alt) { if (isset($alt['id'])) { $characters_to_check[] = esp_get_character_data($user_id, $alt['id']); } }
    }

    $datasets = [];
    $all_dates = [];
    
    // Define a set of distinct colors for the chart lines
    $chart_colors = ['#3498db', '#e74c3c', '#2ecc71', '#9b59b6', '#f1c40f', '#1abc9c', '#e67e22'];

    $color_index = 0;
    foreach ($characters_to_check as $character) {
        if (empty($character) || empty($character['access_token'])) {
            continue;
        }

        $history = esp_calculate_wallet_history($user_id, $character['id'], $character['access_token']);
        
        if (empty($history)) {
            continue;
        }

        $balances = [];
        foreach ($history as $entry) {
            $all_dates[] = $entry['date'];
            $balances[$entry['date']] = $entry['balance'];
        }

        $datasets[] = [
            'label'           => $character['name'],
            'data'            => $balances,
            'borderColor'     => $chart_colors[$color_index % count($chart_colors)],
            'backgroundColor' => $chart_colors[$color_index % count($chart_colors)] . '33', // With alpha transparency
            'fill'            => false,
            'tension'         => 0.1
        ];
        $color_index++;
    }

    if (empty($all_dates)) {
        return ['labels' => [], 'datasets' => []];
    }

    // Create a final, sorted, unique list of date labels for the X-axis
    $unique_dates = array_unique($all_dates);
    sort($unique_dates);

    // Ensure each dataset has a value for every date in the final label list
    foreach ($datasets as &$dataset) {
        $data_points = [];
        $last_balance = 0;
        foreach ($unique_dates as $date) {
            if (isset($dataset['data'][$date])) {
                $last_balance = $dataset['data'][$date];
            }
            $data_points[] = $last_balance;
        }
        $dataset['data'] = $data_points;
    }

    return ['labels' => $unique_dates, 'datasets' => $datasets];
}
/**
 * AJAX handler to fetch and return wallet chart data for a user.
 *
 * This function handles security checks and calls the data consolidator function,
 * then returns the Chart.js-ready data as a JSON response.
 */
function esp_ajax_get_wallet_chart_data() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'esp_wallet_chart_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.'], 403);
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        return;
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error(['message' => 'Missing user ID.'], 400);
        return;
    }

    // Get the chart data using our new helper
    $chart_data = esp_get_wallet_chart_data_for_user($user_id);

    if (empty($chart_data['datasets'])) {
        wp_send_json_error(['message' => 'No wallet history data could be calculated for this user. Use the Force Data refresh button.'], 404);
        return;
    }
    
    wp_send_json_success($chart_data);
}
// Hook our new function into WordPress's AJAX system for logged-in users.
add_action('wp_ajax_esp_get_wallet_chart_data', 'esp_ajax_get_wallet_chart_data');



// --- ESI DATA FETCHING AND STORAGE (SKILLS & ASSETS) ---
/**
 * Fetches and stores the skills and total skill points for a specific character.
 *
 * @param int    $user_id      The WordPress user ID.
 * @param int    $character_id The EVE character ID.
 * @param string $access_token A valid ESI access token with the 'esi-skills.read_skills.v1' scope.
 * @param string $char_type    The type of character ('main' or 'alt').
 * @return bool                True on success, false on failure.
 */
function esp_fetch_and_store_skills_for_character_type( $user_id, $character_id, $access_token, $char_type = 'main' ) { 
    if ( ! $user_id || ! $character_id || ! $access_token) {
        return false;
    }

    $skills_url = "https://esi.evetech.net/latest/characters/{$character_id}/skills/?datasource=tranquility"; 
    $skills_response = wp_remote_get( $skills_url, [ 
        'headers' => [ 
            'Authorization' => 'Bearer ' . $access_token, 
            'Accept'        => 'application/json', 
            'User-Agent'    => EVE_SKILL_PLUGIN_USER_AGENT, 
        ], 
        'timeout' => 20, 
    ]); 

    if ( is_wp_error( $skills_response ) || wp_remote_retrieve_response_code( $skills_response ) !== 200 ) { 
        error_log("[ESP] Skills fetch error for char $character_id: " . (is_wp_error($skills_response) ? $skills_response->get_error_message() : wp_remote_retrieve_response_code($skills_response))); 
        return false; 
    } 

    $skills_body = wp_remote_retrieve_body( $skills_response ); 
    $skills_data_esi = json_decode( $skills_body, true ); 

    if ( ! is_array($skills_data_esi) || ! isset( $skills_data_esi['skills'] ) || ! isset( $skills_data_esi['total_sp'] ) ) { 
        error_log("[ESP] Skills JSON error for char $character_id"); 
        return false; 
    } 

    $skills_list = $skills_data_esi['skills']; 
    $total_sp_value = (float) $skills_data_esi['total_sp']; 
    $current_time = time(); 

    if ($char_type === 'main') { 
        update_user_meta( $user_id, 'esp_main_skills_data', $skills_list ); 
        update_user_meta( $user_id, 'esp_main_total_sp', $total_sp_value ); 
        update_user_meta( $user_id, 'esp_main_skills_last_updated', $current_time ); 
    } elseif ($char_type === 'alt') { 
        esp_update_alt_character_data_item($user_id, $character_id, 'skills_data', $skills_list);
        esp_update_alt_character_data_item($user_id, $character_id, 'total_sp', $total_sp_value);
        esp_update_alt_character_data_item($user_id, $character_id, 'skills_last_updated', $current_time);
    } 
    return true; 
}
/**
 * Fetches and stores the asset list for a specific character.
 *
 * This function handles the paginated ESI endpoint for character assets to ensure
 * all items are retrieved and then stores them in the appropriate user meta field.
 *
 * @param int    $user_id      The WordPress user ID.
 * @param int    $character_id The EVE character ID.
 * @param string $access_token A valid ESI access token with the 'esi-assets.read_assets.v1' scope.
 * @param string $char_type    The type of character ('main' or 'alt').
 * @return bool                True on success, false on failure.
 */
function esp_fetch_and_store_assets_for_character_type( $user_id, $character_id, $access_token, $char_type = 'main' ) {
    if ( ! $user_id || ! $character_id || ! $access_token) {
        return false;
    }

    $all_assets = []; 
    $page = 1; 
    $max_pages = 1; 

    do {
        $assets_url = "https://esi.evetech.net/latest/characters/{$character_id}/assets/?datasource=tranquility&page={$page}";
        $assets_response = wp_remote_get( $assets_url, [ 
            'headers' => [ 
                'Authorization' => 'Bearer ' . $access_token, 
                'Accept'        => 'application/json', 
                'User-Agent'    => EVE_SKILL_PLUGIN_USER_AGENT,
            ], 
            'timeout' => 30,
        ]);

        if ( is_wp_error( $assets_response ) || wp_remote_retrieve_response_code( $assets_response ) !== 200 ) {
            error_log("[ESP] Assets fetch error for char $character_id (Page $page): " . (is_wp_error($assets_response) ? $assets_response->get_error_message() : wp_remote_retrieve_response_code($assets_response) . " Body: " . wp_remote_retrieve_body($assets_response)));
            return false; 
        }

        $headers = wp_remote_retrieve_headers($assets_response);
        if (isset($headers['x-pages']) && is_numeric($headers['x-pages'])) { 
            $max_pages = intval($headers['x-pages']);
        }

        $assets_page_data = json_decode( wp_remote_retrieve_body( $assets_response ), true );
        if ( ! is_array($assets_page_data) ) { 
            error_log("[ESP] Assets JSON error for char $character_id (Page $page)"); 
            return false; 
        }

        $all_assets = array_merge($all_assets, $assets_page_data); 
        $page++;

        if ($page <= $max_pages) {
            sleep(1); 
        }
    } while ($page <= $max_pages);

    $current_time = time();
    if ($char_type === 'main') {
        update_user_meta( $user_id, 'esp_main_assets_data', $all_assets );
        update_user_meta( $user_id, 'esp_main_assets_last_updated', $current_time );
    } elseif ($char_type === 'alt') {
        esp_update_alt_character_data_item($user_id, $character_id, 'assets_data', $all_assets);
        esp_update_alt_character_data_item($user_id, $character_id, 'assets_last_updated', $current_time);
    }
    
    error_log("[ESP] Successfully fetched and stored " . count($all_assets) . " asset entries for $char_type char $character_id (User $user_id)");
    return true;
}
// CHANGE START: Added new function to fetch wallet data.
/**
 * Fetches and stores the wallet journal for a specific character.
 *
 * This function communicates with the ESI API endpoint for character wallet journals,
 * handles pagination to retrieve all available records, and saves the data
 * to the appropriate user meta field for either a main or an alt character.
 *
 * @param int    $user_id      The WordPress user ID.
 * @param int    $character_id The EVE character ID.
 * @param string $access_token A valid ESI access token with the required wallet scope.
 * @param string $char_type    The type of character ('main' or 'alt').
 * @return bool                True on success, false on failure.
 */
function esp_fetch_and_store_wallet_journal_for_character_type( $user_id, $character_id, $access_token, $char_type = 'main' ) {
    if ( ! $user_id || ! $character_id || ! $access_token) {
        return false;
    }

    $all_journal_entries = [];
    $page = 1;
    $max_pages = 1; 

    do {
        $journal_url = "https://esi.evetech.net/latest/characters/{$character_id}/wallet/journal/?datasource=tranquility&page={$page}";
        $journal_response = wp_remote_get( $journal_url, [ 
            'headers' => [ 
                'Authorization' => 'Bearer ' . $access_token, 
                'Accept'        => 'application/json', 
                'User-Agent'    => EVE_SKILL_PLUGIN_USER_AGENT,
            ], 
            'timeout' => 30,
        ]);

        if ( is_wp_error( $journal_response ) || wp_remote_retrieve_response_code( $journal_response ) !== 200 ) {
            $error_code = wp_remote_retrieve_response_code( $journal_response );
            // A 403 error is common if the user hasn't re-authenticated with the new wallet scope.
            // We log this gracefully and stop, rather than treating it as a critical failure.
            if ($error_code === 403) {
                 error_log("[ESP] Wallet Journal fetch for char $character_id failed. This is likely due to missing 'esi-wallet.read_character_wallet.v1' scope. Skipping.");
            } else {
                 error_log("[ESP] Wallet Journal fetch error for char $character_id (Page $page): " . (is_wp_error($journal_response) ? $journal_response->get_error_message() : $error_code));
            }
            return false; 
        }

        $headers = wp_remote_retrieve_headers($journal_response);
        if (isset($headers['x-pages']) && is_numeric($headers['x-pages'])) { 
            $max_pages = intval($headers['x-pages']);
        }

        $journal_page_data = json_decode( wp_remote_retrieve_body( $journal_response ), true );
        if ( ! is_array($journal_page_data) ) { 
            error_log("[ESP] Wallet Journal JSON error for char $character_id (Page $page)"); 
            return false; 
        }

        $all_journal_entries = array_merge($all_journal_entries, $journal_page_data);
        $page++;

        // Be a good ESI citizen and pause briefly between paginated requests.
        if ($page <= $max_pages) {
            sleep(1);
        }
    } while ($page <= $max_pages);
    
    $current_time = time();

    if ($char_type === 'main') {
        update_user_meta( $user_id, 'esp_main_wallet_journal', $all_journal_entries );
        update_user_meta( $user_id, 'esp_main_wallet_last_updated', $current_time );
    } elseif ($char_type === 'alt') {
        // Use our helper to safely update the nested array data for an alt.
        esp_update_alt_character_data_item($user_id, $character_id, 'wallet_journal', $all_journal_entries);
        esp_update_alt_character_data_item($user_id, $character_id, 'wallet_last_updated', $current_time);
    }
    
    error_log("[ESP] Successfully fetched and stored " . count($all_journal_entries) . " wallet journal entries for $char_type char $character_id (User $user_id)");
    return true;
}
// CHANGE END

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
    error_log('[ESP] Starting scheduled character data refresh cron (skills, assets, wallet).');
    $users_with_main_char = get_users([ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'ID', ]);
    if (empty($users_with_main_char)) { 
        error_log('[ESP] Cron: No users with main EVE char to refresh.');
        return; 
    }

    foreach ($users_with_main_char as $user_id) {
        // --- Process Main Character ---
        $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
        if ($main_char_id) {
            $main_token_expires = get_user_meta($user_id, 'esp_main_token_expires', true);
            $current_main_access_token = get_user_meta($user_id, 'esp_main_access_token', true);

            // Check if token needs refreshing
            if (!$current_main_access_token || time() > ((int)$main_token_expires - 300)) {
                $refreshed_main_tokens = esp_refresh_eve_token_for_character_type($user_id, $main_char_id, 'main');
                $current_main_access_token = $refreshed_main_tokens['access_token'] ?? null;
            }

            // If token is valid, fetch all data
            if ($current_main_access_token) {
                esp_fetch_and_store_skills_for_character_type($user_id, $main_char_id, $current_main_access_token, 'main'); 
                sleep(1); 
                esp_fetch_and_store_assets_for_character_type($user_id, $main_char_id, $current_main_access_token, 'main'); 
                sleep(1);
                esp_fetch_and_store_wallet_journal_for_character_type($user_id, $main_char_id, $current_main_access_token, 'main'); 
                sleep(1);
            }
        }

        // --- Process Alt Characters ---
        $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
        if (is_array($alt_characters) && !empty($alt_characters)) {
            foreach ($alt_characters as $alt_char_data) { 
                if (!isset($alt_char_data['id'])) continue;
                
                $alt_char_id = $alt_char_data['id'];
                $current_alt_access_token = esp_get_alt_character_data_item($user_id, $alt_char_id, 'access_token');
                $alt_token_expires = esp_get_alt_character_data_item($user_id, $alt_char_id, 'token_expires');

                // Check if token needs refreshing
                if (!$current_alt_access_token || time() > ((int)$alt_token_expires - 300)) {
                    $refreshed_alt_tokens = esp_refresh_eve_token_for_character_type($user_id, $alt_char_id, 'alt');
                    $current_alt_access_token = $refreshed_alt_tokens['access_token'] ?? null;
                }

                // If token is valid, fetch all data
                if ($current_alt_access_token) {
                    esp_fetch_and_store_skills_for_character_type($user_id, $alt_char_id, $current_alt_access_token, 'alt'); 
                    sleep(1);
                    esp_fetch_and_store_assets_for_character_type($user_id, $alt_char_id, $current_alt_access_token, 'alt'); 
                    sleep(1);
                    esp_fetch_and_store_wallet_journal_for_character_type($user_id, $alt_char_id, $current_alt_access_token, 'alt'); 
                    sleep(1);
                }
            }
        }
    }
    error_log('[ESP] Finished scheduled character data refresh cron.');
}
add_action( 'esp_refresh_character_data_hook', 'esp_do_refresh_all_character_data' ); 
if ( ! wp_next_scheduled( 'esp_refresh_character_data_hook' ) ) { wp_schedule_event( time(), 'hourly', 'esp_refresh_character_data_hook' ); }

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
    if ( !is_user_logged_in() || !check_admin_referer('esp_clear_all_eve_data_action', 'esp_clear_all_eve_data_nonce')) { wp_die('Security check failed or not logged in.'); } 
    $user_id = get_current_user_id(); 
    $main_meta_to_delete = [
        'esp_main_eve_character_id', 'esp_main_eve_character_name', 'esp_main_access_token',
        'esp_main_refresh_token', 'esp_main_token_expires', 'esp_main_owner_hash',
        'esp_main_skills_data', 'esp_main_total_sp', 'esp_main_skills_last_updated',
        'esp_main_assets_data', 'esp_main_assets_last_updated',
        // CHANGE START: Added wallet keys to be cleared.
        'esp_main_wallet_journal', 'esp_main_wallet_last_updated'
        // CHANGE END
    ];
    foreach ($main_meta_to_delete as $key) { delete_user_meta($user_id, $key); }
    delete_user_meta($user_id, 'esp_alt_characters'); 
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
