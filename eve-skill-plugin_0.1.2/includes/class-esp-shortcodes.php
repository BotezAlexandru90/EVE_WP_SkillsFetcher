<?php

class ESP_Shortcodes {

    public function __construct() {
        // Constructor
    }

    public function sso_login_button_shortcode( $atts ) {
        $atts = shortcode_atts( [ 
            'text' => __( 'Authenticate Main EVE Character', 'eve-skill-plugin' ), 
            'alt_text' => __( 'Authenticate Alt Character', 'eve-skill-plugin' ), 
        ], $atts, 'eve_sso_login_button' ); 
        ESP_Helpers::start_session_if_needed(); 
        $output = ''; 
        if ( isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { 
            ob_start(); 
            ESP_Helpers::display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); 
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
}