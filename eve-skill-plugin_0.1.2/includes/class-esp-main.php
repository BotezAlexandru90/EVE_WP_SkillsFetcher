<?php

class ESP_Main {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->version = EVE_SKILL_PLUGIN_VERSION;
        $this->plugin_name = 'eve-skill-plugin';

        $this->load_dependencies();
        $this->init_hooks(); // Initialize common hooks like session start
        $this->define_admin_hooks();
        $this->define_sso_hooks();
        $this->define_character_action_hooks();
        $this->define_cron_hooks();
        $this->define_shortcode_hooks();
    }

    private function load_dependencies() {
        require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-helpers.php';
        require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-admin.php';
        require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-sso-handler.php';
        require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-character-actions.php';
        require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-cron.php';
        require_once EVE_SKILL_PLUGIN_PATH . 'includes/class-esp-shortcodes.php';
    }
    
    private function init_hooks() {
        add_action( 'init', array( 'ESP_Helpers', 'start_session_if_needed' ), 1 );
    }

    private function define_admin_hooks() {
        $plugin_admin = new ESP_Admin( $this->get_plugin_name(), $this->get_version() );
        add_action( 'admin_menu', array( $plugin_admin, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $plugin_admin, 'register_plugin_settings' ) );
        add_action( 'admin_notices', array( 'ESP_Helpers', 'show_admin_page_messages' ) ); 
    }

    private function define_sso_hooks() {
        $sso_handler = new ESP_SSO_Handler();
        add_action( 'admin_post_esp_initiate_sso', array( $sso_handler, 'handle_sso_initiation' ) );
        add_action( 'admin_post_nopriv_esp_initiate_sso', array( $sso_handler, 'handle_sso_initiation' ) );
        add_action( 'admin_post_nopriv_' . ESP_SSO_CALLBACK_ACTION_NAME, array( $sso_handler, 'handle_sso_callback' ) );
        add_action( 'admin_post_' . ESP_SSO_CALLBACK_ACTION_NAME, array( $sso_handler, 'handle_sso_callback' ) );
    }

    private function define_character_action_hooks() {
        $char_actions = new ESP_Character_Actions();
        add_action('admin_post_esp_remove_alt_character', array( $char_actions, 'handle_remove_alt_character' ));
        add_action('admin_post_esp_clear_all_eve_data_for_user', array( $char_actions, 'handle_clear_all_eve_data_for_user' ));
        add_action('admin_post_esp_admin_remove_user_alt_character', array( $char_actions, 'handle_admin_remove_user_alt_character' ));
        add_action('admin_post_esp_admin_promote_alt_to_main', array( $char_actions, 'handle_admin_promote_alt_to_main' ));
        add_action('admin_post_esp_admin_reassign_character', array( $char_actions, 'handle_admin_reassign_character' ));
    }
    
    private function define_cron_hooks() {
        $cron_handler = new ESP_Cron();
        // Cron scheduling is handled by ESP_Cron constructor or its own init method
        add_action( 'esp_refresh_all_skills_hook', array( $cron_handler, 'do_refresh_all_skills' ) );
    }

    private function define_shortcode_hooks() {
        $shortcode_handler = new ESP_Shortcodes();
        add_shortcode( 'eve_sso_login_button', array( $shortcode_handler, 'sso_login_button_shortcode' ) );
    }

    public function run() {
        // The plugin is now running. Hooks are set.
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}