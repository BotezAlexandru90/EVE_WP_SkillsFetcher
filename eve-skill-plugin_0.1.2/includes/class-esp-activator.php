<?php

class ESP_Activator {
    public static function activate() {
        // Actions to run on plugin activation, e.g., set up default options, create tables
        // For now, if cron is scheduled on 'init' or 'wp', it might not need explicit scheduling here.
        // If esp_refresh_all_skills_hook was only scheduled on activation, it would go here.
        // error_log('[ESP] Plugin Activated.');
    }
}