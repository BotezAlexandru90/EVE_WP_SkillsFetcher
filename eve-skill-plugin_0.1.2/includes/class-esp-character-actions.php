<?php

class ESP_Character_Actions {

    public function __construct() {
        // Hooks for these actions are added in ESP_Main
    }

    public function handle_remove_alt_character() {
        if (!is_user_logged_in()) { wp_die('Not logged in.'); }
        $user_id = get_current_user_id();
        $alt_char_id_to_remove = isset($_POST['esp_alt_char_id_to_remove']) ? intval($_POST['esp_alt_char_id_to_remove']) : 0;
        if (!$alt_char_id_to_remove || !check_admin_referer('esp_remove_alt_action_' . $alt_char_id_to_remove, 'esp_remove_alt_nonce')) { wp_die('Invalid request or security check failed.'); }
        
        $removed = ESP_Helpers::handle_remove_alt_character_base($user_id, $alt_char_id_to_remove);
        $message = $removed ? 'alt_removed' : 'alt_not_found';
        $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : admin_url('admin.php?page=eve_skill_user_characters_page');
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $message, $redirect_back_url)); exit;
    }

    public function handle_admin_remove_user_alt_character() {
        if (!current_user_can('manage_options') || !check_admin_referer('esp_admin_remove_alt_action', 'esp_admin_remove_alt_nonce')) { wp_die('Security check failed or insufficient permissions.'); }
        $user_id_to_affect = isset($_POST['user_id_to_affect']) ? intval($_POST['user_id_to_affect']) : 0;
        $alt_char_id_to_remove = isset($_POST['alt_char_id_to_remove']) ? intval($_POST['alt_char_id_to_remove']) : 0;
        $redirect_back_url = admin_url('admin.php?page=eve_view_all_user_skills&view_user_id=' . $user_id_to_affect);
        if (!$user_id_to_affect || !$alt_char_id_to_remove) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); exit; }
        
        $removed = ESP_Helpers::handle_remove_alt_character_base($user_id_to_affect, $alt_char_id_to_remove);
        $message = $removed ? 'admin_alt_removed' : 'admin_alt_not_found';
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $message, $redirect_back_url)); exit;
    }

    public function handle_admin_promote_alt_to_main() {
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

    public function handle_admin_reassign_character() {
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
            if ($original_main_id != $character_id_to_move) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_reassign_main_not_found', $redirect_back_url)); exit; }
            $original_user_alts_check = get_user_meta($original_wp_user_id, 'esp_alt_characters', true);
            if (!empty($original_user_alts_check) && is_array($original_user_alts_check)) {
                 wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_reassign_main_has_alts', $redirect_back_url)); exit;
            }
            $moved_char_data_obj = [
                'id' => (int)$original_main_id, 'name' => (string)get_user_meta($original_wp_user_id, 'esp_main_eve_character_name', true),
                'access_token' => (string)get_user_meta($original_wp_user_id, 'esp_main_access_token', true), 'refresh_token' => (string)get_user_meta($original_wp_user_id, 'esp_main_refresh_token', true),
                'token_expires' => (int)get_user_meta($original_wp_user_id, 'esp_main_token_expires', true), 'owner_hash' => (string)get_user_meta($original_wp_user_id, 'esp_main_owner_hash', true),
                'skills_data' => get_user_meta($original_wp_user_id, 'esp_main_skills_data', true), 'total_sp' => (float)get_user_meta($original_wp_user_id, 'esp_main_total_sp', true),
                'skills_last_updated' => (int)get_user_meta($original_wp_user_id, 'esp_main_skills_last_updated', true),
            ];
            if (!is_array($moved_char_data_obj['skills_data'])) $moved_char_data_obj['skills_data'] = [];
            delete_user_meta($original_wp_user_id, 'esp_main_eve_character_id'); delete_user_meta($original_wp_user_id, 'esp_main_eve_character_name');
            delete_user_meta($original_wp_user_id, 'esp_main_access_token'); delete_user_meta($original_wp_user_id, 'esp_main_refresh_token');
            delete_user_meta($original_wp_user_id, 'esp_main_token_expires'); delete_user_meta($original_wp_user_id, 'esp_main_owner_hash');
            delete_user_meta($original_wp_user_id, 'esp_main_skills_data'); delete_user_meta($original_wp_user_id, 'esp_main_total_sp');
            delete_user_meta($original_wp_user_id, 'esp_main_skills_last_updated');
        } elseif ($character_type_to_move === 'alt') {
            if (!ESP_Helpers::handle_remove_alt_character_base($original_wp_user_id, $character_id_to_move, $moved_char_data_obj)) { // Pass by reference
                 wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_assign_alt_not_found_orig', $redirect_back_url)); exit;
            }
            // $moved_char_data_obj is now populated by handle_remove_alt_character_base if found
        }
        if (!$moved_char_data_obj) { wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'admin_op_failed_params', $redirect_back_url)); exit; }
        $new_main_user_alts[] = $moved_char_data_obj;
        update_user_meta($new_main_wp_user_id, 'esp_alt_characters', $new_main_user_alts);
        $success_message = ($character_type_to_move === 'main') ? 'admin_main_reassigned_as_alt' : 'admin_alt_assigned_new_main';
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, $success_message, $redirect_back_url));
        exit;
    }
    
    public function handle_clear_all_eve_data_for_user() { 
        if ( !is_user_logged_in() || !check_admin_referer('esp_clear_all_eve_data_action', 'esp_clear_all_eve_data_nonce')) { wp_die('Security check failed or not logged in.'); } 
        $user_id = get_current_user_id(); 
        delete_user_meta($user_id, 'esp_main_eve_character_id'); delete_user_meta($user_id, 'esp_main_eve_character_name'); delete_user_meta($user_id, 'esp_main_access_token'); delete_user_meta($user_id, 'esp_main_refresh_token'); delete_user_meta($user_id, 'esp_main_token_expires'); delete_user_meta($user_id, 'esp_main_owner_hash'); delete_user_meta($user_id, 'esp_main_skills_data'); delete_user_meta($user_id, 'esp_main_total_sp'); delete_user_meta($user_id, 'esp_main_skills_last_updated'); 
        delete_user_meta($user_id, 'esp_alt_characters'); 
        // Legacy single char fields
        delete_user_meta( $user_id, 'eve_character_id' ); delete_user_meta( $user_id, 'eve_character_name' ); delete_user_meta( $user_id, 'eve_access_token' ); delete_user_meta( $user_id, 'eve_refresh_token' ); delete_user_meta( $user_id, 'eve_token_expires' ); delete_user_meta( $user_id, 'eve_skills_data' ); delete_user_meta( $user_id, 'eve_total_sp' ); delete_user_meta( $user_id, 'eve_owner_hash');  delete_user_meta( $user_id, 'eve_skills_last_updated'); 
        $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : admin_url('admin.php?page=eve_skill_user_characters_page'); 
        wp_redirect(add_query_arg(ESP_REDIRECT_MESSAGE_QUERY_ARG, 'all_data_cleared', $redirect_back_url)); exit; 
    }
}