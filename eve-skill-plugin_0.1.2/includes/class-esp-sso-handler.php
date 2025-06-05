<?php

class ESP_SSO_Handler {

    public function __construct() {
        // Constructor can be used for setting up properties if needed
    }

    public function handle_sso_initiation() {
        if ( ! isset( $_POST['esp_initiate_sso_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['esp_initiate_sso_nonce']), 'esp_initiate_sso_action' ) ) { wp_die( 'Nonce verification failed!' ); }
        ESP_Helpers::start_session_if_needed(); 
        $client_id = get_option( 'esp_client_id' ); $scopes = get_option( 'esp_scopes', 'esi-skills.read_skills.v1 publicData' );
        if ( ! $client_id ) { wp_die( 'EVE Client ID not configured.' ); }
        $auth_type = isset($_POST['esp_auth_type']) ? sanitize_key($_POST['esp_auth_type']) : 'main'; 
        $redirect_back_url = isset($_POST['esp_redirect_back_url']) ? esc_url_raw($_POST['esp_redirect_back_url']) : home_url();
        $sso_state_value = bin2hex( random_bytes( 16 ) ); $_SESSION[ESP_SSO_SESSION_KEY] = [ 'nonce' => $sso_state_value, 'redirect_url' => $redirect_back_url, 'auth_type' => $auth_type ];
        $sso_redirect_uri_to_eve = admin_url( 'admin-post.php?action=' . ESP_SSO_CALLBACK_ACTION_NAME ); 
        $sso_url_to_eve = 'https://login.eveonline.com/v2/oauth/authorize/?' . http_build_query( [ 'response_type' => 'code', 'redirect_uri'  => $sso_redirect_uri_to_eve, 'client_id' => $client_id, 'scope' => $scopes, 'state' => $sso_state_value, ] );
        wp_redirect( $sso_url_to_eve ); exit;
    }

    public function handle_sso_callback() {
        ESP_Helpers::start_session_if_needed(); 
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
            $wp_user = ESP_Helpers::get_or_create_wp_user_for_eve_char( $authed_character_id, $authed_character_name, $authed_owner_hash );
            if ( is_wp_error( $wp_user ) ) { error_log( "[ESP] Error get/create WP user for EVE char $authed_character_id: " . $wp_user->get_error_message() ); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_wp_user_error', $redirect_url_after_sso ) ); exit; }
            $user_id = $wp_user->ID; $created_meta = get_user_meta($user_id, 'created_via_eve_sso', true); if ($created_meta && (time() - $created_meta < 60) ){ $is_new_wp_user = true; }
            wp_set_current_user( $user_id, $wp_user->user_login ); wp_set_auth_cookie( $user_id ); do_action( 'wp_login', $wp_user->user_login, $wp_user );
        }
        if ( !$user_id ) { error_log("[ESP] Critical error: No WP user ID after EVE auth for char $authed_character_id"); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_internal_user_error', $redirect_url_after_sso ) ); exit; }
        $current_main_char_id = get_user_meta($user_id, 'esp_main_eve_character_id', true);
        if ($auth_type === 'main' || !$current_main_char_id) { 
            if ($current_main_char_id && $current_main_char_id != $authed_character_id) { error_log("[ESP] User $user_id switching main EVE char from $current_main_char_id to $authed_character_id");}
            update_user_meta( $user_id, 'esp_main_eve_character_id', $authed_character_id ); update_user_meta( $user_id, 'esp_main_eve_character_name', $authed_character_name ); update_user_meta( $user_id, 'esp_main_access_token', $access_token ); update_user_meta( $user_id, 'esp_main_refresh_token', $refresh_token ); update_user_meta( $user_id, 'esp_main_token_expires', time() + $expires_in ); update_user_meta( $user_id, 'esp_main_owner_hash', $authed_owner_hash );
            $skills_fetched = $this->fetch_and_store_skills_for_character_type( $user_id, $authed_character_id, $access_token, 'main' );
        } elseif ($auth_type === 'alt') {
            if ($authed_character_id == $current_main_char_id) { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_alt_is_main', $redirect_url_after_sso ) ); exit; }
            $alt_characters = get_user_meta($user_id, 'esp_alt_characters', true); if (!is_array($alt_characters)) $alt_characters = [];
            $alt_exists_idx = -1; foreach ($alt_characters as $idx => $alt) { if (isset($alt['id']) && $alt['id'] == $authed_character_id) { $alt_exists_idx = $idx; break; } }
            $alt_data = [ 'id' => $authed_character_id, 'name' => $authed_character_name, 'owner_hash' => $authed_owner_hash, 'access_token' => $access_token, 'refresh_token' => $refresh_token, 'token_expires' => time() + $expires_in, ];
            if ($alt_exists_idx !== -1) { $alt_characters[$alt_exists_idx] = array_merge($alt_characters[$alt_exists_idx], $alt_data); } else { $alt_characters[] = $alt_data; }
            update_user_meta($user_id, 'esp_alt_characters', $alt_characters);
            $skills_fetched = $this->fetch_and_store_skills_for_character_type( $user_id, $authed_character_id, $access_token, 'alt' );
        } else { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_unknown_auth_type', $redirect_url_after_sso ) ); exit; }
        $final_redirect_url = $redirect_url_after_sso;
        if ($skills_fetched) { $message_key = ($auth_type === 'alt') ? 'sso_alt_success' : 'sso_success'; if ($is_new_wp_user) $final_redirect_url = add_query_arg('new_user', 'true', $final_redirect_url); wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, $message_key, $final_redirect_url ) );
        } else { wp_redirect( add_query_arg( ESP_REDIRECT_MESSAGE_QUERY_ARG, 'sso_skills_failed', $final_redirect_url ) ); }
        exit;
    }

    public function fetch_and_store_skills_for_character_type( $user_id, $character_id, $access_token, $char_type = 'main' ) { 
        if ( ! $user_id || ! $character_id || ! $access_token) return false; 
        $skills_url = "https://esi.evetech.net/latest/characters/{$character_id}/skills/?datasource=tranquility"; 
        $skills_response = wp_remote_get( $skills_url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $access_token, 'Accept' => 'application/json', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, ], 'timeout' => 20, ]); 
        if ( is_wp_error( $skills_response ) || wp_remote_retrieve_response_code( $skills_response ) !== 200 ) { error_log("[ESP] Skills fetch error for char $character_id: " . (is_wp_error($skills_response) ? $skills_response->get_error_message() : wp_remote_retrieve_response_code($skills_response))); return false; } 
        $skills_body = wp_remote_retrieve_body( $skills_response ); $skills_data_esi = json_decode( $skills_body, true ); 
        if ( ! is_array($skills_data_esi) || ! isset( $skills_data_esi['skills'] ) || ! isset( $skills_data_esi['total_sp'] ) ) { error_log("[ESP] Skills JSON error for char $character_id"); return false; } 
        $skills_list = $skills_data_esi['skills']; $total_sp_value = (float) $skills_data_esi['total_sp']; $current_time = time(); 
        if ($char_type === 'main') { update_user_meta( $user_id, 'esp_main_skills_data', $skills_list ); update_user_meta( $user_id, 'esp_main_total_sp', $total_sp_value ); update_user_meta( $user_id, 'esp_main_skills_last_updated', $current_time ); } 
        elseif ($char_type === 'alt') { 
            ESP_Helpers::update_alt_character_data_item($user_id, $character_id, 'skills_data', $skills_list);
            ESP_Helpers::update_alt_character_data_item($user_id, $character_id, 'total_sp', $total_sp_value);
            ESP_Helpers::update_alt_character_data_item($user_id, $character_id, 'skills_last_updated', $current_time);
        } return true; 
    }

    public function refresh_eve_token_for_character_type( $user_id, $character_id, $char_type = 'main' ) { 
        if (!$user_id || !$character_id) return false; $refresh_token_value = ''; 
        if ($char_type === 'main') { $refresh_token_value = get_user_meta( $user_id, 'esp_main_refresh_token', true ); } 
        elseif ($char_type === 'alt') { $refresh_token_value = ESP_Helpers::get_alt_character_data_item($user_id, $character_id, 'refresh_token'); } 
        if ( ! $refresh_token_value ) return false; 
        $client_id = get_option( 'esp_client_id' ); $client_secret = get_option( 'esp_client_secret' ); 
        if ( ! $client_id || ! $client_secret ) { error_log('[ESP] Token Refresh: Client ID/Secret not set.'); return false; } 
        $auth_header = base64_encode( $client_id . ':' . $client_secret ); $token_url = 'https://login.eveonline.com/v2/oauth/token'; 
        $response = wp_remote_post( $token_url, [ 'headers' => [ 'Authorization' => 'Basic ' . $auth_header, 'Content-Type'  => 'application/x-www-form-urlencoded', 'Host' => 'login.eveonline.com', 'User-Agent' => EVE_SKILL_PLUGIN_USER_AGENT, ], 'body' => [ 'grant_type' => 'refresh_token', 'refresh_token' => $refresh_token_value, ], 'timeout' => 20, ]); 
        if ( is_wp_error( $response ) ) { error_log("[ESP] Token Refresh WP Error for $char_type CharID $character_id: " . $response->get_error_message()); return false; } 
        $body = wp_remote_retrieve_body( $response ); $token_data = json_decode( $body, true ); $response_code = wp_remote_retrieve_response_code( $response ); 
        if ( $response_code !== 200 || ! isset( $token_data['access_token'] ) ) { error_log("[ESP] Token Refresh Failed for $char_type CharID $character_id (User $user_id). HTTP: $response_code. EVE: $body"); if (strpos($body, 'invalid_token') !== false || strpos($body, 'invalid_grant') !== false || $response_code === 400) { ESP_Helpers::clear_specific_character_tokens($user_id, $character_id, $char_type); } return false; } 
        $new_access_token = sanitize_text_field( $token_data['access_token'] ); $new_refresh_token = isset($token_data['refresh_token']) ? sanitize_text_field($token_data['refresh_token']) : $refresh_token_value; $new_expires_in = intval( $token_data['expires_in'] ); 
        if ($char_type === 'main') { update_user_meta( $user_id, 'esp_main_access_token', $new_access_token ); update_user_meta( $user_id, 'esp_main_refresh_token', $new_refresh_token ); update_user_meta( $user_id, 'esp_main_token_expires', time() + $new_expires_in ); } 
        elseif ($char_type === 'alt') { ESP_Helpers::update_alt_character_data_item($user_id, $character_id, 'access_token', $new_access_token); ESP_Helpers::update_alt_character_data_item($user_id, $character_id, 'refresh_token', $new_refresh_token); ESP_Helpers::update_alt_character_data_item($user_id, $character_id, 'token_expires', time() + $new_expires_in); } 
        error_log("[ESP] Token refreshed for $char_type CharID $character_id (User $user_id)"); 
        return ['access_token' => $new_access_token, 'expires_in' => $new_expires_in]; 
    }
}