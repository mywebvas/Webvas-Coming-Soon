<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function wvcsn_uninstall_cleanup_site() {
    global $wpdb;

    delete_option('wvcsn_enabled');
    delete_option('wvcsn_mode');
    delete_option('wvcsn_allowlist_paths');
    delete_option('wvcsn_bypass_token');
    delete_option('wvcsn_audit_log');
    delete_option('wvcsn_brand_color');
    delete_option('wvcsn_headline');
    delete_option('wvcsn_description');
    delete_option('wvcsn_button_text');
    delete_option('wvcsn_button_microcopy');
    delete_option('wvcsn_social_proof_mode');
    delete_option('wvcsn_social_proof_text');
    delete_option('wvcsn_schema_version');

    $table = str_replace('`', '', $wpdb->prefix . 'wvcsn_waitlist');
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    $visitor_table = str_replace('`', '', $wpdb->prefix . 'wvcsn_visitors');
    $wpdb->query("DROP TABLE IF EXISTS `{$visitor_table}`");

    $transient_key = $wpdb->esc_like('_transient_wvcsn_rl_') . '%';
    $timeout_key = $wpdb->esc_like('_transient_timeout_wvcsn_rl_') . '%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $transient_key,
            $timeout_key
        )
    );
}

if (is_multisite()) {
    $site_ids = get_sites(
        array(
            'fields' => 'ids',
            'number' => 0,
        )
    );

    foreach ($site_ids as $site_id) {
        switch_to_blog((int) $site_id);
        wvcsn_uninstall_cleanup_site();
        restore_current_blog();
    }
} else {
    wvcsn_uninstall_cleanup_site();
}
