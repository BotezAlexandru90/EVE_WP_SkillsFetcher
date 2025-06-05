<?php

class ESP_Cron {

    public function __construct() {
        if ( ! wp_next_scheduled( 'esp_refresh_all_skills_hook' ) ) {
            wp_schedule_event( time(), 'hourly', 'esp_refresh_all_skills_hook' );
        }
    }

    public function do_refresh_all_skills() {
        error_log('[ESP] Starting scheduled skill refresh cron.'); 
        $sso_handler = new ESP_SSO_Handler(); // We need its methods
        $users_with_main_char = get_users([ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'ID', ]); 
        if (empty($users_with_main_char)) { error_log('[ESP] Cron: No users with main EVE char.'); return; } 
        foreach ($users_with_main_char as $user_id) { 
            $main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true); 
            if ($main_char_id) { 
                $main_token = get_user_meta($user_id, 'esp_main_access_token', true); 
                $main_expires = get_user_meta($user_id, 'esp_main_token_expires', true); 
                $current_main_access_token = $main_token; 
                if (!$main_token || time() > ((int)$main_expires - 300)) { 
                    $refreshed_main_tokens = $sso_handler->refresh_eve_token_for_character_type($user_id, $main_char_id, 'main'); 
                    if ($refreshed_main_tokens && isset($refreshed_main_tokens['access_token'])) { 
                        $current_main_access_token = $refreshed_main_tokens['access_token']; 
                    } else { 
                        error_log("[ESP] Cron: Failed to refresh main token for User $user_id, Char $main_char_id. Skipping skills."); 
                        $current_main_access_token = null; 
                    } 
                } 
                if ($current_main_access_token) { 
                    $sso_handler->fetch_and_store_skills_for_character_type($user_id, $main_char_id, $current_main_access_token, 'main'); 
                    sleep(1); 
                } 
            } 
            $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); 
            if (is_array($alt_characters) && !empty($alt_characters)) { 
                foreach ($alt_characters as $alt_char_data) { 
                    if (!isset($alt_char_data['id'])) continue; 
                    $alt_char_id = $alt_char_data['id']; 
                    $alt_token = isset($alt_char_data['access_token']) ? $alt_char_data['access_token'] : null; 
                    $alt_expires = isset($alt_char_data['token_expires']) ? $alt_char_data['token_expires'] : 0; 
                    $current_alt_access_token = $alt_token; 
                    if (!$alt_token || time() > ((int)$alt_expires - 300)) { 
                        $refreshed_alt_tokens = $sso_handler->refresh_eve_token_for_character_type($user_id, $alt_char_id, 'alt'); 
                        if ($refreshed_alt_tokens && isset($refreshed_alt_tokens['access_token'])) { 
                            $current_alt_access_token = $refreshed_alt_tokens['access_token']; 
                        } else { 
                            error_log("[ESP] Cron: Failed to refresh alt token for User $user_id, Alt Char $alt_char_id. Skipping skills."); 
                            $current_alt_access_token = null; 
                        } 
                    } 
                    if ($current_alt_access_token) { 
                        $sso_handler->fetch_and_store_skills_for_character_type($user_id, $alt_char_id, $current_alt_access_token, 'alt'); 
                        sleep(1); 
                    } 
                } 
            } 
        } 
        error_log('[ESP] Finished scheduled skill refresh cron.'); 
    }
}