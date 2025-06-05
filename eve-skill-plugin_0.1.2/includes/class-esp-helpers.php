<?php

class ESP_Helpers {

    public static function start_session_if_needed() {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }

    public static function display_sso_message( $message_key ) {
        $message_key = sanitize_key($message_key); $class = 'notice eve-sso-message is-dismissible '; $text = '';
        switch($message_key) {
            case 'sso_success': $class .= 'notice-success'; $text = esc_html__( 'Main EVE character authenticated successfully!', 'eve-skill-plugin' ); if (isset($_GET['new_user']) && $_GET['new_user'] === 'true') { $text .= ' ' . esc_html__('A WordPress account has been created for you and you are now logged in.', 'eve-skill-plugin'); } break;
            case 'sso_alt_success': $class .= 'notice-success'; $text = esc_html__( 'Alt EVE character authenticated successfully!', 'eve-skill-plugin' ); break;
            case 'sso_skills_failed': $class .= 'notice-warning'; $text = esc_html__( 'EVE authentication was successful, but skills could not be fetched.', 'eve-skill-plugin' ); break;
            case 'all_data_cleared': $class .= 'notice-success'; $text = esc_html__( 'All your EVE Online data (main and alts) has been cleared from this site.', 'eve-skill-plugin' ); break;
            case 'alt_removed': $class .= 'notice-success'; $text = esc_html__( 'Alt character has been removed successfully by you.', 'eve-skill-plugin' ); break;
            case 'admin_alt_removed': $class .= 'notice-success'; $text = esc_html__( 'Alt character has been removed by administrator.', 'eve-skill-plugin' ); break;
            case 'admin_alt_promoted': $class .= 'notice-success'; $text = esc_html__( 'Alt character has been promoted to main by administrator.', 'eve-skill-plugin' ); break;
            case 'admin_alt_assigned_new_main': $class .= 'notice-success'; $text = esc_html__( 'Character has been successfully assigned as an alt to the new main user.', 'eve-skill-plugin' ); break; // Added for new feature
            case 'admin_main_reassigned_as_alt': $class .= 'notice-success'; $text = esc_html__( 'Main character has been successfully reassigned as an alt to the target user.', 'eve-skill-plugin' ); break; // Added for new feature
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
        } 
        if ($text) { printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $text ); }
    }

    public static function show_admin_page_messages() { 
        $current_screen = get_current_screen(); 
        if ( $current_screen && 
             isset($current_screen->id) && 
             (strpos($current_screen->id, 'eve_skill_plugin_settings') !== false || 
              strpos($current_screen->id, 'eve_skill_user_characters_page') !== false || 
              strpos($current_screen->id, 'eve_view_all_user_skills') !== false) && 
             isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { 
            self::display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); 
        } 
    }

    public static function get_skill_name( $skill_id ) { 
        $skill_id = intval($skill_id); $transient_key = 'esp_skill_name_' . $skill_id; $skill_name = get_transient( $transient_key ); 
        if ( false === $skill_name ) { 
            $response = wp_remote_get( "https://esi.evetech.net/latest/universe/types/{$skill_id}/?datasource=tranquility", ['headers' => ['User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT]] ); 
            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) { 
                $type_data = json_decode( wp_remote_retrieve_body( $response ), true ); 
                if ( isset( $type_data['name'] ) ) { $skill_name = $type_data['name']; set_transient( $transient_key, $skill_name, DAY_IN_SECONDS * 30 ); } 
                else { $skill_name = "Unknown Skill (ID: {$skill_id})"; } 
            } else { $skill_name = "Skill ID: {$skill_id} (Lookup Failed)"; } 
        } return $skill_name; 
    }

    public static function get_alt_character_data_item($user_id, $alt_char_id, $item_key) {
        $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true);
        if (is_array($alt_characters)) { 
            foreach ($alt_characters as $alt) { 
                if (isset($alt['id']) && $alt['id'] == $alt_char_id) { 
                    return isset($alt[$item_key]) ? $alt[$item_key] : null; 
                } 
            } 
        } return null;
    }

    public static function update_alt_character_data_item($user_id, $alt_char_id, $item_key, $item_value, $char_name = null, $owner_hash = null) {
        $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); 
        if (!is_array($alt_characters)) { $alt_characters = []; }
        $found_alt_index = -1;
        foreach ($alt_characters as $index => $alt) { 
            if (isset($alt['id']) && $alt['id'] == $alt_char_id) { 
                $found_alt_index = $index; break; 
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
    
    public static function get_or_create_wp_user_for_eve_char( $character_id, $character_name, $owner_hash ) { 
        $existing_users = get_users( [ 'meta_key' => 'esp_main_eve_character_id', 'meta_value' => $character_id, 'number' => 1, 'count_total' => false ]); 
        if ( ! empty( $existing_users ) ) { return $existing_users[0]; } 
        $alt_users = get_users([ 'meta_query' => [ [ 'key' => 'esp_alt_characters', 'value' => '"id";i:'.$character_id.';', 'compare' => 'LIKE' ] ], 'number' => 1, 'count_total' => false ]); 
        if (!empty($alt_users)) { return $alt_users[0]; } 
        $username = sanitize_user( 'eve_' . $character_id . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $character_name), true ); 
        $i = 0; $base_username = $username; 
        while ( username_exists( $username ) ) { $i++; $username = $base_username . '_' . $i; } 
        $random_password = wp_generate_password( 20, true, true ); 
        $email_domain_part = sanitize_title(str_replace(['http://', 'https://', 'www.'], '', get_bloginfo('url'))); 
        if (empty($email_domain_part)) $email_domain_part = 'localhost'; $email = $character_id . '@' . $email_domain_part . '.eve-sso.invalid'; 
        $new_user_data = [ 'user_login' => $username, 'user_pass'  => $random_password, 'user_email' => $email, 'display_name' => $character_name, 'role' => 'subscriber' ]; 
        $new_user_id = wp_insert_user( $new_user_data ); 
        if ( is_wp_error( $new_user_id ) ) { return $new_user_id; } 
        $current_time = time(); update_user_meta( $new_user_id, 'created_via_eve_sso', $current_time ); 
        error_log("[ESP] Created new WP User ID $new_user_id ($username) for EVE Char $character_id ($character_name)"); 
        return get_user_by( 'id', $new_user_id ); 
    }

    public static function clear_specific_character_tokens($user_id, $character_id, $char_type = 'main') { 
        if (!$user_id || !$character_id) return; 
        if ($char_type === 'main') { 
            delete_user_meta( $user_id, 'esp_main_access_token'); 
            delete_user_meta( $user_id, 'esp_main_refresh_token'); 
            delete_user_meta( $user_id, 'esp_main_token_expires'); 
        } elseif ($char_type === 'alt') { 
            self::update_alt_character_data_item($user_id, $character_id, 'access_token', ''); 
            self::update_alt_character_data_item($user_id, $character_id, 'refresh_token', ''); 
            self::update_alt_character_data_item($user_id, $character_id, 'token_expires', 0); 
        } 
        error_log("[ESP] Cleared EVE tokens for $char_type CharID $character_id (User $user_id)"); 
    }

    /**
     * Base logic for removing an alt character.
     * MODIFIED: Now accepts a third parameter by reference to pass back the removed alt's data.
     */
    public static function handle_remove_alt_character_base($user_id_to_affect, $alt_char_id_to_remove, &$removed_alt_data_obj = null) {
        $alt_characters = get_user_meta($user_id_to_affect, 'esp_alt_characters', true);
        $removed = false;
        $removed_alt_data_obj = null; // Initialize to null

        if (is_array($alt_characters)) {
            $updated_alts = [];
            foreach ($alt_characters as $alt_char) {
                if (isset($alt_char['id']) && $alt_char['id'] == $alt_char_id_to_remove) { 
                    $removed = true; 
                    $removed_alt_data_obj = $alt_char; // Capture the data of the removed alt
                    continue; 
                } 
                $updated_alts[] = $alt_char;
            }
            if ($removed) {
                if (empty($updated_alts)) { 
                    delete_user_meta($user_id_to_affect, 'esp_alt_characters'); 
                } else { 
                    update_user_meta($user_id_to_affect, 'esp_alt_characters', $updated_alts); 
                }
            }
        }
        return $removed; // Return true if removed, false otherwise
    }
}