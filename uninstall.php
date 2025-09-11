<?php
/**
 * BKM Aksiyon Takip Uninstall Script
 * 
 * This file is executed when the plugin is uninstalled via WordPress admin.
 * It removes all plugin data from the database.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Confirm we're uninstalling BKM Aksiyon Takip
if (!defined('BKM_AKSIYON_TAKIP_VERSION')) {
    define('BKM_AKSIYON_TAKIP_VERSION', '1.0.0');
}

global $wpdb;

// Drop custom tables
$tables_to_drop = array(
    $wpdb->prefix . 'bkm_actions',
    $wpdb->prefix . 'bkm_categories', 
    $wpdb->prefix . 'bkm_performances',
    $wpdb->prefix . 'bkm_tasks'
);

foreach ($tables_to_drop as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Remove plugin options
$options_to_delete = array(
    'bkm_aksiyon_takip_db_version',
    'bkm_aksiyon_takip_settings',
    'bkm_aksiyon_takip_email_settings'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Remove user meta data related to the plugin
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'bkm_aksiyon_takip_%'");

// Clear any cached data
wp_cache_flush();

// Log uninstall (optional)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('BKM Aksiyon Takip plugin uninstalled and all data removed.');
}