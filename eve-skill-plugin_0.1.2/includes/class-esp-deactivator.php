<?php

class ESP_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'esp_refresh_all_skills_hook' );
        error_log('[ESP] Deactivated and cleared scheduled hook.');
    }
}