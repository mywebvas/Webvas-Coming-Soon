<?php
/*
Plugin Name: Webvas Coming Soon Ultra-Tiny
Description: High-converting coming soon and maintenance mode with waitlist capture, private preview links, honest visitor counts, CSV export, and zero builder bloat.
Version: 2.4.1
Author: Webvas
Author URI: https://mywebvas.com/coming-soon
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.0
Requires PHP: 8.0
Tested up to: 6.9.4
Text Domain: wvcsn
*/

if (!defined('ABSPATH')) {
    exit;
}

final class Webvas_Coming_Soon_Ultra_Tiny {
    private const VERSION = '2.4.1';
    private const ADMIN_CAPABILITY = 'activate_plugins';
    private const OPTION_ENABLED = 'wvcsn_enabled';
    private const OPTION_MODE = 'wvcsn_mode';
    private const OPTION_ALLOWLIST_PATHS = 'wvcsn_allowlist_paths';
    private const OPTION_BYPASS_TOKEN = 'wvcsn_bypass_token';
    private const OPTION_AUDIT_LOG = 'wvcsn_audit_log';
    private const OPTION_BRAND_COLOR = 'wvcsn_brand_color';
    private const OPTION_HEADLINE = 'wvcsn_headline';
    private const OPTION_DESCRIPTION = 'wvcsn_description';
    private const OPTION_BUTTON_TEXT = 'wvcsn_button_text';
    private const OPTION_BUTTON_MICROCOPY = 'wvcsn_button_microcopy';
    private const OPTION_SOCIAL_PROOF_MODE = 'wvcsn_social_proof_mode';
    private const OPTION_SOCIAL_PROOF_TEXT = 'wvcsn_social_proof_text';
    private const OPTION_SCHEMA_VERSION = 'wvcsn_schema_version';
    private const SCHEMA_VERSION = '2.3.3';
    private const SETTINGS_SLUG = 'wvcsn-settings';
    private const NONCE_ACTION_SUBMIT = 'wvcsn_submit_waitlist';
    private const NONCE_ACTION_EXPORT = 'wvcsn_export_waitlist';
    private const NONCE_ACTION_CLEAR = 'wvcsn_clear_waitlist';
    private const NONCE_ACTION_CLEAR_COUNTS = 'wvcsn_clear_visit_counts';
    private const NONCE_ACTION_TOGGLE = 'wvcsn_toggle_mode';
    private const TABLE_SUFFIX = 'wvcsn_waitlist';
    private const VISITOR_TABLE_SUFFIX = 'wvcsn_visitors';
    private const MODE_COMING_SOON = 'coming_soon';
    private const MODE_MAINTENANCE = 'maintenance';
    private const SOCIAL_PROOF_OFF = 'off';
    private const SOCIAL_PROOF_AUTO = 'auto';
    private const SOCIAL_PROOF_CUSTOM = 'custom';
    private const RATE_LIMIT_WINDOW = 600;
    private const RATE_LIMIT_MAX_ATTEMPTS = 10;
    private const MAX_NAME_LENGTH = 120;
    private const MAX_EMAIL_LENGTH = 190;
    private const MAX_HEADLINE_LENGTH = 120;
    private const MAX_DESCRIPTION_LENGTH = 280;
    private const MAX_DESCRIPTION_RAW_LENGTH = 1200;
    private const MAX_BUTTON_TEXT_LENGTH = 40;
    private const MAX_BUTTON_MICROCOPY_LENGTH = 120;
    private const MAX_SOCIAL_PROOF_TEXT_LENGTH = 120;
    private const MAX_TRACKING_VALUE_LENGTH = 190;
    private const MAX_URL_LENGTH = 500;
    private const VISITOR_COOKIE = 'wvcsn_visitor';
    private const VISITOR_COOKIE_TTL = 2592000;
    private const BYPASS_COOKIE = 'wvcsn_bypass';
    private const BYPASS_COOKIE_TTL = 604800;
    private const BYPASS_QUERY_ARG = 'wvcsn_access';
    private const MAX_AUDIT_LOG_ENTRIES = 50;
    private const CSV_BATCH_SIZE = 2000;
    private $table_ready = null;
    private $visitor_table_ready = null;
    private $bypass_current_request = false;

    public static function bootstrap() {
        static $instance = null;

        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }

    public static function activate() {
        $instance = self::bootstrap();

        if (get_option(self::OPTION_ENABLED, null) === null) {
            add_option(self::OPTION_ENABLED, 1, '', false);
        }

        if (get_option(self::OPTION_MODE, null) === null) {
            add_option(self::OPTION_MODE, self::MODE_MAINTENANCE, '', false);
        }

        if (get_option(self::OPTION_ALLOWLIST_PATHS, null) === null) {
            add_option(self::OPTION_ALLOWLIST_PATHS, '', '', false);
        }

        if (get_option(self::OPTION_BYPASS_TOKEN, null) === null) {
            add_option(self::OPTION_BYPASS_TOKEN, $instance->generate_access_token(), '', false);
        }

        if (get_option(self::OPTION_AUDIT_LOG, null) === null) {
            add_option(self::OPTION_AUDIT_LOG, array(), '', false);
        }

        if (get_option(self::OPTION_BRAND_COLOR, null) === null) {
            add_option(self::OPTION_BRAND_COLOR, $instance->get_default_brand_color(), '', false);
        }

        if (get_option(self::OPTION_HEADLINE, null) === null) {
            add_option(self::OPTION_HEADLINE, $instance->get_default_headline(), '', false);
        }

        if (get_option(self::OPTION_DESCRIPTION, null) === null) {
            add_option(self::OPTION_DESCRIPTION, $instance->get_default_description(), '', false);
        }

        if (get_option(self::OPTION_BUTTON_TEXT, null) === null) {
            add_option(self::OPTION_BUTTON_TEXT, $instance->get_default_button_text(), '', false);
        }

        if (get_option(self::OPTION_BUTTON_MICROCOPY, null) === null) {
            add_option(self::OPTION_BUTTON_MICROCOPY, $instance->get_default_button_microcopy(), '', false);
        }

        if (get_option(self::OPTION_SOCIAL_PROOF_MODE, null) === null) {
            add_option(self::OPTION_SOCIAL_PROOF_MODE, self::SOCIAL_PROOF_OFF, '', false);
        }

        if (get_option(self::OPTION_SOCIAL_PROOF_TEXT, null) === null) {
            add_option(self::OPTION_SOCIAL_PROOF_TEXT, '', '', false);
        }

        $instance->maybe_install_schema();
    }

    private function __construct() {
        $this->maybe_install_schema();
        $this->maybe_define_runtime_compat_flags();

        add_action('send_headers', array($this, 'maybe_send_runtime_headers'), 0);
        add_action('template_redirect', array($this, 'maybe_handle_bypass_request'), 0);
        add_action('template_redirect', array($this, 'maybe_render_coming_soon'), 1);
        add_action('admin_post_nopriv_wvcsn_submit_waitlist', array($this, 'handle_waitlist_submission'));
        add_action('admin_post_wvcsn_submit_waitlist', array($this, 'handle_waitlist_submission'));
        add_action('admin_post_wvcsn_export_waitlist', array($this, 'handle_export_waitlist'));
        add_action('admin_post_wvcsn_clear_waitlist', array($this, 'handle_clear_waitlist'));
        add_action('admin_post_wvcsn_clear_visit_counts', array($this, 'handle_clear_visit_counts'));
        add_action('admin_post_wvcsn_toggle_mode', array($this, 'handle_toggle_mode'));
        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_dashboard_setup', array($this, 'register_dashboard_widget'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_links'));
        add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
    }

    public function add_plugin_links($links) {
        $url = admin_url('options-general.php?page=' . self::SETTINGS_SLUG);
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'wvcsn') . '</a>');

        return $links;
    }

    public function add_plugin_row_meta($links, $file) {
        if ($file !== plugin_basename(__FILE__)) {
            return $links;
        }

        $links[] = '<a href="' . esc_url($this->get_support_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Support', 'wvcsn') . '</a>';
        $links[] = '<a href="' . esc_url($this->get_feature_request_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Request Feature', 'wvcsn') . '</a>';
        $links[] = '<a href="' . esc_url($this->get_developer_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Developer: Michael Madojutimi', 'wvcsn') . '</a>';

        return $links;
    }

    public function register_settings() {
        register_setting(
            'wvcsn_settings',
            self::OPTION_ENABLED,
            array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_enabled_option'),
                'default' => 1,
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_MODE,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_mode_option'),
                'default' => self::MODE_MAINTENANCE,
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_ALLOWLIST_PATHS,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_allowlist_paths_option'),
                'default' => '',
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_BYPASS_TOKEN,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_bypass_token_option'),
                'default' => $this->generate_access_token(),
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_BRAND_COLOR,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_brand_color_option'),
                'default' => $this->get_default_brand_color(),
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_HEADLINE,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_headline_option'),
                'default' => $this->get_default_headline(),
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_DESCRIPTION,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_description_option'),
                'default' => $this->get_default_description(),
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_BUTTON_TEXT,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_button_text_option'),
                'default' => $this->get_default_button_text(),
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_BUTTON_MICROCOPY,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_button_microcopy_option'),
                'default' => $this->get_default_button_microcopy(),
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_SOCIAL_PROOF_MODE,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_social_proof_mode_option'),
                'default' => self::SOCIAL_PROOF_OFF,
            )
        );

        register_setting(
            'wvcsn_settings',
            self::OPTION_SOCIAL_PROOF_TEXT,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_social_proof_text_option'),
                'default' => '',
            )
        );
    }

    public function sanitize_enabled_option($value) {
        return empty($value) ? 0 : 1;
    }

    public function sanitize_mode_option($value) {
        return $value === self::MODE_COMING_SOON ? self::MODE_COMING_SOON : self::MODE_MAINTENANCE;
    }

    public function sanitize_allowlist_paths_option($value) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $clean = array();

        foreach ((array) $lines as $line) {
            $line = trim(wp_strip_all_tags((string) $line));
            if ($line === '') {
                continue;
            }

            $wildcard = substr($line, -1) === '*';
            $line = $wildcard ? substr($line, 0, -1) : $line;

            $path = wp_parse_url($line, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = $line;
            }

            $normalized = $this->to_relative_site_path($path);
            if ($normalized === '') {
                continue;
            }

            $clean[] = $wildcard ? $normalized . '*' : $normalized;
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    public function sanitize_bypass_token_option($value) {
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value);
        $token = is_string($token) ? trim($token) : '';

        return $token === '' ? $this->generate_access_token() : $this->substr($token, 0, 64);
    }

    public function sanitize_headline_option($value) {
        $headline = sanitize_text_field((string) $value);
        $headline = trim($this->substr($headline, 0, self::MAX_HEADLINE_LENGTH));

        return $headline === '' ? $this->get_default_headline() : $headline;
    }

    public function sanitize_brand_color_option($value) {
        $color = sanitize_hex_color((string) $value);

        return is_string($color) && $color !== '' ? strtolower($color) : $this->get_default_brand_color();
    }

    public function sanitize_description_option($value) {
        $description = trim((string) $value);
        $description = $this->substr($description, 0, self::MAX_DESCRIPTION_RAW_LENGTH);
        $description = force_balance_tags($description);
        $description = wp_kses($description, $this->get_description_allowed_html(), array('http', 'https', 'mailto'));
        $plain_description = $this->normalize_whitespace(wp_strip_all_tags($description));

        if ($plain_description === '') {
            return $this->get_default_description();
        }

        if ($this->strlen($plain_description) > self::MAX_DESCRIPTION_LENGTH) {
            return trim($this->substr($plain_description, 0, self::MAX_DESCRIPTION_LENGTH));
        }

        return $description;
    }

    public function sanitize_button_text_option($value) {
        $button_text = sanitize_text_field((string) $value);
        $button_text = trim($this->substr($button_text, 0, self::MAX_BUTTON_TEXT_LENGTH));

        return $button_text === '' ? $this->get_default_button_text() : $button_text;
    }

    public function sanitize_button_microcopy_option($value) {
        $microcopy = sanitize_text_field((string) $value);
        $microcopy = trim($this->substr($microcopy, 0, self::MAX_BUTTON_MICROCOPY_LENGTH));

        return $microcopy === '' ? $this->get_default_button_microcopy() : $microcopy;
    }

    public function sanitize_social_proof_mode_option($value) {
        $value = sanitize_key((string) $value);

        return in_array($value, array(self::SOCIAL_PROOF_OFF, self::SOCIAL_PROOF_AUTO, self::SOCIAL_PROOF_CUSTOM), true) ? $value : self::SOCIAL_PROOF_OFF;
    }

    public function sanitize_social_proof_text_option($value) {
        $text = sanitize_text_field((string) $value);

        return trim($this->substr($text, 0, self::MAX_SOCIAL_PROOF_TEXT_LENGTH));
    }

    private function get_admin_capability() {
        $capability = apply_filters('wvcsn_admin_capability', self::ADMIN_CAPABILITY);

        return is_string($capability) && $capability !== '' ? $capability : self::ADMIN_CAPABILITY;
    }

    private function current_user_can_manage_plugin() {
        return current_user_can($this->get_admin_capability());
    }

    public function register_admin_page() {
        add_options_page(
            __('Webvas Coming Soon', 'wvcsn'),
            __('Webvas Coming Soon', 'wvcsn'),
            $this->get_admin_capability(),
            self::SETTINGS_SLUG,
            array($this, 'render_admin_page')
        );
    }

    public function register_dashboard_widget() {
        if (!$this->current_user_can_manage_plugin()) {
            return;
        }

        wp_add_dashboard_widget('wvcsn_dashboard_widget', __('Webvas Coming Soon', 'wvcsn'), array($this, 'render_dashboard_widget'));
    }

    public function render_dashboard_widget() {
        $enabled = $this->is_enabled();
        $count = $this->get_signup_count();
        $visit_count = $this->get_visit_count();
        $readiness = $this->get_launch_readiness_snapshot($count, $visit_count);
        $settings_url = admin_url('options-general.php?page=' . self::SETTINGS_SLUG);
        ?>
        <div style="display:grid;gap:14px;">
            <p style="margin:0;"><strong><?php esc_html_e('Coming soon page:', 'wvcsn'); ?></strong> <?php echo $enabled ? esc_html(sprintf(__('On (%s mode)', 'wvcsn'), $this->get_mode_label())) : esc_html__('Off', 'wvcsn'); ?></p>
            <p style="margin:0;"><strong><?php esc_html_e('People on the list:', 'wvcsn'); ?></strong> <?php echo esc_html(number_format_i18n($count)); ?></p>
            <p style="margin:0;"><strong><?php esc_html_e('Unique visitors:', 'wvcsn'); ?></strong> <?php echo esc_html(number_format_i18n($visit_count)); ?></p>
            <p style="margin:0;"><strong><?php esc_html_e('Signup rate:', 'wvcsn'); ?></strong> <?php echo esc_html($readiness['conversion_rate_label']); ?></p>
            <p style="margin:-6px 0 0;color:#50575e;font-size:12px;"><?php echo esc_html($readiness['formula']); ?></p>
            <div style="padding:12px 14px;border-left:4px solid <?php echo esc_attr($readiness['color']); ?>;background:<?php echo esc_attr($readiness['background']); ?>;border-radius:8px;">
                <p style="margin:0 0 6px;"><strong><?php esc_html_e('Launch signal:', 'wvcsn'); ?></strong> <?php echo esc_html($readiness['label']); ?></p>
                <p style="margin:0;color:#50575e;"><?php echo esc_html($readiness['summary']); ?></p>
                <p style="margin:6px 0 0;color:#50575e;font-size:12px;"><?php echo esc_html($readiness['detail']); ?></p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input type="hidden" name="action" value="wvcsn_toggle_mode">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url(admin_url('index.php')); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION_TOGGLE); ?>
                <?php submit_button($enabled ? __('Turn Off', 'wvcsn') : __('Turn On', 'wvcsn'), 'primary', 'submit', false); ?>
                <a class="button button-secondary" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Settings & Export', 'wvcsn'); ?></a>
            </form>
        </div>
        <?php
    }

    public function render_admin_page() {
        if (!$this->current_user_can_manage_plugin()) {
            return;
        }

        $enabled = $this->is_enabled();
        $count = $this->get_signup_count();
        $visit_count = $this->get_visit_count();
        $admin_notice = $this->get_admin_notice();
        $bypass_url = $this->get_bypass_url();
        $audit_entries = $this->get_audit_log_entries();
        $brand_color = $this->get_frontend_brand_color();
        $headline = $this->get_frontend_headline();
        $description = $this->get_frontend_description();
        $button_text = $this->get_frontend_button_text();
        $button_microcopy = $this->get_frontend_button_microcopy();
        $social_proof_mode = $this->get_social_proof_mode();
        $social_proof_text = $this->get_social_proof_text();
        $readiness = $this->get_launch_readiness_snapshot($count, $visit_count);
        $social_proof_preview = $this->get_social_proof_preview_text($count, $visit_count);
        $guide_url = $this->get_admin_guide_url();
        ?>
        <div class="wrap" id="wvcsn-settings-page">
            <h1><?php esc_html_e('Webvas Coming Soon', 'wvcsn'); ?></h1>
            <p><?php esc_html_e('Turn on a simple coming soon page, collect early interest, and export your list when you are ready to launch.', 'wvcsn'); ?></p>

            <table class="widefat striped" style="max-width:860px;margin:18px 0 24px;">
                <tbody>
                    <tr><td style="width:240px;"><strong><?php esc_html_e('Coming soon page', 'wvcsn'); ?></strong></td><td><?php echo $enabled ? esc_html__('On', 'wvcsn') : esc_html__('Off', 'wvcsn'); ?></td></tr>
                    <tr><td><strong><?php esc_html_e('Page mode', 'wvcsn'); ?></strong></td><td><?php echo esc_html($this->get_mode_label()); ?></td></tr>
                    <tr><td><strong><?php esc_html_e('People on the list', 'wvcsn'); ?></strong></td><td><?php echo esc_html(number_format_i18n($count)); ?></td></tr>
                    <tr><td><strong><?php esc_html_e('Unique visitors', 'wvcsn'); ?></strong></td><td><?php echo esc_html(number_format_i18n($visit_count)); ?></td></tr>
                    <tr><td><strong><?php esc_html_e('Signup rate', 'wvcsn'); ?></strong></td><td><?php echo esc_html($readiness['conversion_rate_label']); ?></td></tr>
                    <tr><td><strong><?php esc_html_e('Launch signal', 'wvcsn'); ?></strong></td><td><?php echo esc_html($readiness['label']); ?></td></tr>
                    <tr><td><strong><?php esc_html_e('Storage', 'wvcsn'); ?></strong></td><td><?php echo esc_html($this->is_table_ready() ? __('Saved in your WordPress database with low-memory CSV export', 'wvcsn') : __('Database table not available yet', 'wvcsn')); ?></td></tr>
                </tbody>
            </table>
            <p class="description" style="max-width:860px;margin-top:-12px;margin-bottom:24px;"><?php esc_html_e('Unique visitors are counted once per browser or device with a first-party cookie. This keeps the number honest without turning the plugin into a heavy analytics tool.', 'wvcsn'); ?></p>
            <div style="max-width:860px;margin:-6px 0 24px;padding:14px 16px;border-left:4px solid <?php echo esc_attr($readiness['color']); ?>;background:<?php echo esc_attr($readiness['background']); ?>;border-radius:8px;">
                <p style="margin:0 0 4px;font-weight:600;"><?php echo esc_html($readiness['label']); ?></p>
                <p style="margin:0;color:#50575e;"><?php echo esc_html($readiness['summary']); ?></p>
                <p style="margin:8px 0 0;color:#50575e;font-size:12px;"><?php echo esc_html($readiness['detail']); ?></p>
            </div>
            <p class="description" style="max-width:860px;margin-top:-12px;margin-bottom:24px;"><?php echo esc_html($readiness['formula']); ?></p>

            <form method="post" action="options.php" style="max-width:860px;">
                <?php settings_fields('wvcsn_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Coming soon page', 'wvcsn'); ?></th>
                        <td>
                            <label for="wvcsn_enabled">
                                <input type="checkbox" id="wvcsn_enabled" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="1" <?php checked($enabled); ?>>
                                <?php esc_html_e('Show the coming soon page to visitors while approved admins can still open the normal site.', 'wvcsn'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_mode"><?php esc_html_e('Page mode', 'wvcsn'); ?></label></th>
                        <td>
                            <select id="wvcsn_mode" name="<?php echo esc_attr(self::OPTION_MODE); ?>">
                                <option value="<?php echo esc_attr(self::MODE_COMING_SOON); ?>" <?php selected($this->get_mode(), self::MODE_COMING_SOON); ?>><?php esc_html_e('Coming Soon (HTTP 200)', 'wvcsn'); ?></option>
                                <option value="<?php echo esc_attr(self::MODE_MAINTENANCE); ?>" <?php selected($this->get_mode(), self::MODE_MAINTENANCE); ?>><?php esc_html_e('Maintenance (HTTP 503)', 'wvcsn'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Use Coming Soon before launch. Use Maintenance when the live site is temporarily down and should tell browsers and search engines it is unavailable for now.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_bypass_token"><?php esc_html_e('Private preview code', 'wvcsn'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text code" id="wvcsn_bypass_token" name="<?php echo esc_attr(self::OPTION_BYPASS_TOKEN); ?>" value="<?php echo esc_attr($this->get_bypass_token()); ?>" autocomplete="off" spellcheck="false">
                            <p class="description"><?php esc_html_e('This code is used to create a private preview link so trusted people can open the real site without turning the coming soon page off for everyone else.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Private preview link', 'wvcsn'); ?></th>
                        <td>
                            <input type="text" class="large-text code" value="<?php echo esc_attr($bypass_url); ?>" readonly onclick="this.select();">
                            <p class="description"><?php esc_html_e('Opening this link gives that browser temporary access to the real site, then removes the code from the address bar.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_allowlist_paths"><?php esc_html_e('Public pages', 'wvcsn'); ?></label></th>
                        <td>
                            <textarea id="wvcsn_allowlist_paths" name="<?php echo esc_attr(self::OPTION_ALLOWLIST_PATHS); ?>" rows="6" class="large-text code"><?php echo esc_textarea((string) get_option(self::OPTION_ALLOWLIST_PATHS, '')); ?></textarea>
                            <p class="description"><?php esc_html_e('One site path per line. Use * at the end to match everything inside a section, for example /contact/ or /preview/*. If a page does not exist, visitors will still see the coming soon page instead of a public error page.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_brand_color"><?php esc_html_e('Brand color', 'wvcsn'); ?></label></th>
                        <td>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <input type="text" class="regular-text code" id="wvcsn_brand_color" name="<?php echo esc_attr(self::OPTION_BRAND_COLOR); ?>" value="<?php echo esc_attr($brand_color); ?>" maxlength="7" inputmode="text" spellcheck="false" placeholder="#0f43aa">
                                <span aria-hidden="true" style="display:inline-block;width:28px;height:28px;border-radius:999px;border:1px solid #dcdcde;background:<?php echo esc_attr($brand_color); ?>;"></span>
                            </div>
                            <p class="description"><?php esc_html_e('Use one hex color like #0f43aa. The stronger shade used on buttons and highlights is generated automatically.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_headline"><?php esc_html_e('Frontend heading', 'wvcsn'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="wvcsn_headline" name="<?php echo esc_attr(self::OPTION_HEADLINE); ?>" value="<?php echo esc_attr($headline); ?>" maxlength="<?php echo esc_attr(self::MAX_HEADLINE_LENGTH); ?>">
                            <p class="description"><?php esc_html_e('Default works out of the box, but you can change the main heading if you want a custom launch message.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_description"><?php esc_html_e('Frontend description', 'wvcsn'); ?></label></th>
                        <td>
                            <textarea id="wvcsn_description" name="<?php echo esc_attr(self::OPTION_DESCRIPTION); ?>" rows="4" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                            <p class="description"><?php esc_html_e('Keep this short and clear so visitors quickly understand why they should join the list. Simple inline HTML is allowed: strong, em, br, code, and safe links.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_button_text"><?php esc_html_e('Form button text', 'wvcsn'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="wvcsn_button_text" name="<?php echo esc_attr(self::OPTION_BUTTON_TEXT); ?>" value="<?php echo esc_attr($button_text); ?>" maxlength="<?php echo esc_attr(self::MAX_BUTTON_TEXT_LENGTH); ?>">
                            <p class="description"><?php esc_html_e('Default is tuned for stronger launch intent, but you can change the button label to match your offer.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_button_microcopy"><?php esc_html_e('Text under the button', 'wvcsn'); ?></label></th>
                        <td>
                            <input type="text" class="large-text" id="wvcsn_button_microcopy" name="<?php echo esc_attr(self::OPTION_BUTTON_MICROCOPY); ?>" value="<?php echo esc_attr($button_microcopy); ?>" maxlength="<?php echo esc_attr(self::MAX_BUTTON_MICROCOPY_LENGTH); ?>">
                            <p class="description"><?php esc_html_e('Short reassurance under the button can reduce fear and improve signups. Text only.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_social_proof_mode"><?php esc_html_e('Social proof line', 'wvcsn'); ?></label></th>
                        <td>
                            <select id="wvcsn_social_proof_mode" name="<?php echo esc_attr(self::OPTION_SOCIAL_PROOF_MODE); ?>">
                                <option value="<?php echo esc_attr(self::SOCIAL_PROOF_OFF); ?>" <?php selected($social_proof_mode, self::SOCIAL_PROOF_OFF); ?>><?php esc_html_e('Off', 'wvcsn'); ?></option>
                                <option value="<?php echo esc_attr(self::SOCIAL_PROOF_AUTO); ?>" <?php selected($social_proof_mode, self::SOCIAL_PROOF_AUTO); ?>><?php esc_html_e('Automatic', 'wvcsn'); ?></option>
                                <option value="<?php echo esc_attr(self::SOCIAL_PROOF_CUSTOM); ?>" <?php selected($social_proof_mode, self::SOCIAL_PROOF_CUSTOM); ?>><?php esc_html_e('My own text', 'wvcsn'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Automatic mode shows demand only when the numbers are strong enough. My own text lets you write one line yourself and optionally use live placeholders.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_social_proof_text"><?php esc_html_e('Custom social proof text', 'wvcsn'); ?></label></th>
                        <td>
                            <input type="text" class="large-text" id="wvcsn_social_proof_text" name="<?php echo esc_attr(self::OPTION_SOCIAL_PROOF_TEXT); ?>" value="<?php echo esc_attr($social_proof_text); ?>" maxlength="<?php echo esc_attr(self::MAX_SOCIAL_PROOF_TEXT_LENGTH); ?>">
                            <p class="description"><?php esc_html_e('Only used when "My own text" is selected. Use plain text, or include {count}, {visitors}, and {rate} to insert live numbers.', 'wvcsn'); ?></p>
                            <p class="description"><?php echo esc_html(sprintf(__('Preview: %s', 'wvcsn'), $social_proof_preview)); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Changes', 'wvcsn')); ?>
            </form>

            <h2 style="margin-top:28px;"><?php esc_html_e('Export saved contacts', 'wvcsn'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:860px;">
                <input type="hidden" name="action" value="wvcsn_export_waitlist">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wvcsn_export_from"><?php esc_html_e('From date', 'wvcsn'); ?></label></th>
                        <td>
                            <input type="date" id="wvcsn_export_from" name="wvcsn_export_from" value="">
                            <p class="description"><?php esc_html_e('Optional. Export contacts saved on or after this date.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_export_to"><?php esc_html_e('To date', 'wvcsn'); ?></label></th>
                        <td>
                            <input type="date" id="wvcsn_export_to" name="wvcsn_export_to" value="">
                            <p class="description"><?php esc_html_e('Optional. Export contacts saved on or before this date.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wvcsn_export_source"><?php esc_html_e('Source', 'wvcsn'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="wvcsn_export_source" name="wvcsn_export_source" value="" maxlength="<?php echo esc_attr(self::MAX_TRACKING_VALUE_LENGTH); ?>">
                            <p class="description"><?php esc_html_e('Optional. Matches the UTM source field exactly, for example facebook or google.', 'wvcsn'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php wp_nonce_field(self::NONCE_ACTION_EXPORT); ?>
                <?php submit_button(__('Download CSV', 'wvcsn'), 'secondary', 'submit', false); ?>
            </form>

            <h2 style="margin-top:28px;"><?php esc_html_e('Recent admin activity', 'wvcsn'); ?></h2>
            <?php if (empty($audit_entries)) : ?>
                <p><?php esc_html_e('No admin actions have been logged yet.', 'wvcsn'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="max-width:860px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Time (UTC)', 'wvcsn'); ?></th>
                            <th><?php esc_html_e('Person', 'wvcsn'); ?></th>
                            <th><?php esc_html_e('Action', 'wvcsn'); ?></th>
                            <th><?php esc_html_e('Details', 'wvcsn'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit_entries as $entry) : ?>
                            <tr>
                                <td><?php echo esc_html(isset($entry['time']) ? (string) $entry['time'] : ''); ?></td>
                                <td><?php echo esc_html(isset($entry['user_label']) ? (string) $entry['user_label'] : __('System', 'wvcsn')); ?></td>
                                <td><?php echo esc_html($this->get_audit_action_label(isset($entry['action']) ? (string) $entry['action'] : '')); ?></td>
                                <td><?php echo esc_html($this->get_audit_entry_details(isset($entry['details']) && is_array($entry['details']) ? $entry['details'] : array())); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top:28px;color:#9b1c1c;"><?php esc_html_e('Danger zone', 'wvcsn'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return window.confirm('<?php echo esc_js(__('Delete all saved contacts? This cannot be undone.', 'wvcsn')); ?>');" style="max-width:860px;">
                <input type="hidden" name="action" value="wvcsn_clear_waitlist">
                <?php wp_nonce_field(self::NONCE_ACTION_CLEAR); ?>
                <?php submit_button(__('Delete all saved contacts', 'wvcsn'), 'delete', 'submit', false, $count < 1 ? array('disabled' => 'disabled') : array()); ?>
                <p class="description"><?php esc_html_e('Use this after launch only if you want to empty the saved contact list. This does not remove your visitor count or plugin settings.', 'wvcsn'); ?></p>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return window.confirm('<?php echo esc_js(__('Delete all saved visitor counts? Returning visitors may be counted again after this reset.', 'wvcsn')); ?>');" style="max-width:860px;margin-top:14px;">
                <input type="hidden" name="action" value="wvcsn_clear_visit_counts">
                <?php wp_nonce_field(self::NONCE_ACTION_CLEAR_COUNTS); ?>
                <?php submit_button(__('Delete all visitor counts', 'wvcsn'), 'delete', 'submit', false, $visit_count < 1 ? array('disabled' => 'disabled') : array()); ?>
                <p class="description"><?php esc_html_e('This empties the stored visitor count history. Existing browser cookies cannot be removed remotely, so some returning visitors may be counted again later.', 'wvcsn'); ?></p>
            </form>

            <?php if ($admin_notice) : ?>
                <p style="margin-top:10px;color:<?php echo esc_attr($admin_notice['color']); ?>;"><?php echo esc_html($admin_notice['message']); ?></p>
            <?php endif; ?>

            <div style="max-width:860px;margin-top:28px;padding-top:18px;border-top:1px solid #dcdcde;color:#50575e;">
                <p style="margin:0 0 8px;font-size:13px;line-height:1.6;">
                    <?php
                    echo esc_html(
                        sprintf(
                            __('Built by Webvas. Version %s. No external tracking. Data stays in your WordPress database.', 'wvcsn'),
                            $this->get_plugin_version()
                        )
                    );
                    ?>
                </p>
                <p style="margin:0;font-size:13px;line-height:1.6;">
                    <a href="<?php echo esc_url($guide_url); ?>" target="_blank" rel="noopener noreferrer" data-wvcsn-guide-open data-wvcsn-guide-url="<?php echo esc_attr($guide_url); ?>"><?php esc_html_e('Launch Guide', 'wvcsn'); ?></a>
                    <span aria-hidden="true"> | </span>
                    <a href="<?php echo esc_url($this->get_support_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Support', 'wvcsn'); ?></a>
                    <span aria-hidden="true"> | </span>
                    <a href="<?php echo esc_url($this->get_feature_request_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Request Feature', 'wvcsn'); ?></a>
                    <span aria-hidden="true"> | </span>
                    <a href="<?php echo esc_url($this->get_developer_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Michael Madojutimi', 'wvcsn'); ?></a>
                </p>
            </div>
            <script>
                (function(){
                    var root=document.getElementById('wvcsn-settings-page');
                    if(!root){return;}
                    var trigger=root.querySelector('[data-wvcsn-guide-open]');
                    if(!trigger){return;}
                    trigger.addEventListener('click',function(event){
                        var guideUrl=trigger.getAttribute('data-wvcsn-guide-url');
                        var viewportWidth=window.innerWidth||document.documentElement.clientWidth||0;
                        var viewportHeight=window.innerHeight||document.documentElement.clientHeight||0;
                        var shouldUsePopup=guideUrl&&viewportWidth>=960&&viewportHeight>=700;
                        if(!shouldUsePopup){return;}
                        event.preventDefault();
                        var popupWidth=Math.min(920,Math.max(760,viewportWidth-160));
                        var popupHeight=Math.min(820,Math.max(640,viewportHeight-140));
                        var left=Math.max(40,Math.round((window.screen.width-popupWidth)/2));
                        var top=Math.max(40,Math.round((window.screen.height-popupHeight)/2));
                        var features='popup=yes,width='+popupWidth+',height='+popupHeight+',left='+left+',top='+top+',resizable=yes,scrollbars=yes';
                        var popup=window.open(guideUrl,'wvcsnLaunchGuide',features);
                        if(popup&&typeof popup.focus==='function'){
                            popup.focus();
                            return;
                        }
                        window.open(guideUrl,'_blank','noopener,noreferrer');
                    });
                }());
            </script>
        </div>
        <?php
    }

    public function handle_toggle_mode() {
        if (!$this->current_user_can_manage_plugin()) {
            wp_die(esc_html__('You are not allowed to do this.', 'wvcsn'));
        }

        check_admin_referer(self::NONCE_ACTION_TOGGLE);
        $next_enabled = $this->is_enabled() ? 0 : 1;
        update_option(self::OPTION_ENABLED, $next_enabled, false);
        $this->append_audit_log(
            'toggle_page',
            array(
                'enabled' => (bool) $next_enabled,
                'mode' => $this->get_mode_label(),
            )
        );
        $redirect = admin_url('index.php');

        if (isset($_POST['redirect_to'])) {
            $redirect = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['redirect_to'])), $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_export_waitlist() {
        if (!$this->current_user_can_manage_plugin()) {
            wp_die(esc_html__('You are not allowed to download the waitlist.', 'wvcsn'));
        }

        check_admin_referer(self::NONCE_ACTION_EXPORT);
        $filters = $this->get_export_filters_from_request();

        if (!$this->is_table_ready()) {
            wp_safe_redirect($this->get_settings_url('storage_error'));
            exit;
        }

        $matching_count = $this->get_signup_count($filters);
        if ($matching_count < 1) {
            wp_safe_redirect($this->get_settings_url($this->filters_are_empty($filters) ? 'empty' : 'empty_filtered'));
            exit;
        }

        $this->append_audit_log(
            'export_csv',
            array(
                'matches' => $matching_count,
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
                'source' => $filters['source'],
            )
        );
        $this->stream_waitlist_csv($filters);
    }

    public function handle_clear_waitlist() {
        if (!$this->current_user_can_manage_plugin()) {
            wp_die(esc_html__('You are not allowed to delete the saved contact list.', 'wvcsn'));
        }

        check_admin_referer(self::NONCE_ACTION_CLEAR);

        if (!$this->is_table_ready()) {
            wp_safe_redirect($this->get_settings_url('storage_error'));
            exit;
        }

        $deleted = $this->clear_waitlist_entries();
        if ($deleted === false) {
            wp_safe_redirect($this->get_settings_url('clear_error'));
            exit;
        }

        $this->append_audit_log('clear_waitlist', array('deleted_entries' => (int) $deleted));
        wp_safe_redirect($this->get_settings_url('cleared'));
        exit;
    }

    public function handle_clear_visit_counts() {
        if (!$this->current_user_can_manage_plugin()) {
            wp_die(esc_html__('You are not allowed to delete the saved visitor counts.', 'wvcsn'));
        }

        check_admin_referer(self::NONCE_ACTION_CLEAR_COUNTS);

        if (!$this->is_visitor_table_ready()) {
            wp_safe_redirect($this->get_settings_url('storage_error'));
            exit;
        }

        $deleted = $this->clear_visitor_entries();
        if ($deleted === false) {
            wp_safe_redirect($this->get_settings_url('counts_clear_error'));
            exit;
        }

        $this->append_audit_log('clear_counts', array('cleared_counts' => (int) $deleted));
        wp_safe_redirect($this->get_settings_url('counts_cleared'));
        exit;
    }

    public function handle_waitlist_submission() {
        $response = $this->process_waitlist_submission();
        $is_async = isset($_POST['wvcsn_async']) && wp_unslash($_POST['wvcsn_async']) === '1';

        if ($is_async) {
            wp_send_json_success(
                array(
                    'code' => $response['code'],
                    'message' => $response['message'],
                    'replaceForm' => $response['replace_form'],
                    'type' => $response['type'],
                )
            );
        }

        wp_safe_redirect(add_query_arg('wvcsn_status', $response['code'], $this->get_submission_redirect()));
        exit;
    }

    public function maybe_handle_bypass_request() {
        if (!$this->is_enabled() || $this->is_request_context_exempt()) {
            return;
        }

        if (!isset($_GET[self::BYPASS_QUERY_ARG])) {
            return;
        }

        $submitted = preg_replace('/[^A-Za-z0-9_-]/', '', (string) wp_unslash($_GET[self::BYPASS_QUERY_ARG]));
        $submitted = is_string($submitted) ? $submitted : '';

        if ($submitted === '' || !hash_equals($this->get_bypass_token(), $submitted)) {
            return;
        }

        $this->bypass_current_request = true;

        if (!$this->set_runtime_cookie(self::BYPASS_COOKIE, $this->get_bypass_cookie_value(), self::BYPASS_COOKIE_TTL)) {
            return;
        }

        wp_safe_redirect($this->get_current_request_url(array(self::BYPASS_QUERY_ARG)));
        exit;
    }

    public function maybe_render_coming_soon() {
        if (!$this->is_enabled() || $this->should_bypass_coming_soon()) {
            return;
        }

        $site_name = get_bloginfo('name');
        $brand_color = $this->get_frontend_brand_color();
        $brand_color_strong = $this->get_brand_color_strong($brand_color);
        $brand_color_rgb = $this->hex_to_rgb_css($brand_color, '15,67,170');
        $headline = $this->get_frontend_headline();
        $description = $this->get_frontend_description();
        $description_html = $this->format_frontend_description($description);
        $button_text = $this->get_frontend_button_text();
        $button_microcopy = $this->get_frontend_button_microcopy();
        $social_proof_text = $this->get_social_proof_text_for_frontend();
        $title = $this->build_frontend_title($site_name, $headline);
        $meta_description = $this->build_frontend_meta_description($description);
        $logo = $this->build_logo($site_name);
        $tracking = $this->get_request_tracking_context();
        $status = isset($_GET['wvcsn_status']) ? sanitize_key(wp_unslash($_GET['wvcsn_status'])) : '';
        $feedback = $this->get_feedback_message($status);
        $form_hidden = in_array($status, array('success', 'duplicate'), true);
        $success_title = $status === 'duplicate' ? __('You are already on the list.', 'wvcsn') : __('Thanks. You are on the list.', 'wvcsn');
        $success_copy = $status === 'duplicate' ? __('We already have this email saved. We will reach out when the site is ready.', 'wvcsn') : __('We will email you when the site is ready.', 'wvcsn');
        $form_context_issued_at = time();
        $form_context_signature = $this->build_tracking_context_signature($tracking, $form_context_issued_at);

        $this->maybe_track_visit();
        $this->send_coming_soon_headers();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($title); ?></title>
            <meta name="description" content="<?php echo esc_attr($meta_description); ?>">
            <style>
                :root{--bg:#f6f4ee;--text:#16315f;--muted:#5b6d93;--accent:<?php echo esc_html($brand_color); ?>;--accent-strong:<?php echo esc_html($brand_color_strong); ?>;--accent-rgb:<?php echo esc_html($brand_color_rgb); ?>;--line:rgba(var(--accent-rgb),.10);--panel:rgba(255,255,255,.86);--success:#0f7a4d;--error:#9b1c1c;--warning:#8a5800}
                *{box-sizing:border-box}
                [hidden]{display:none!important}
                html,body{width:100%;max-width:100%;margin:0;min-height:100%;overflow-x:hidden}
                body{font-family:"Segoe UI Variable","Aptos","Trebuchet MS",sans-serif;background:radial-gradient(circle at top,rgba(var(--accent-rgb),.12),transparent 36%),linear-gradient(180deg,#fcfbf8 0%,var(--bg) 100%);color:var(--text);padding:24px 16px;overflow-wrap:anywhere}
                .wrap{width:100%;max-width:560px;margin:0 auto;min-height:calc(100vh - 48px);display:flex;align-items:center;justify-content:center}
                .card{width:100%;max-width:100%;background:var(--panel);border:1px solid var(--line);border-radius:28px;box-shadow:0 24px 70px rgba(var(--accent-rgb),.10);padding:clamp(28px,6vw,44px);backdrop-filter:blur(14px)}
                .topline{display:flex;align-items:center;gap:12px;margin-bottom:22px}
                .logo{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;background:linear-gradient(135deg,var(--accent) 0%,var(--accent-strong) 100%);color:#fff;font-size:22px;font-weight:700;letter-spacing:.03em}
                .site{display:block;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent-strong)}
                .status{display:block;margin-top:4px;font-size:14px;color:var(--muted)}
                h1{margin:0 0 12px;font-size:clamp(2rem,7vw,3.4rem);line-height:1;letter-spacing:-.045em}
                .copy{margin:0 0 24px;font-size:clamp(1rem,2.7vw,1.08rem);line-height:1.72;color:var(--muted)}
                .copy a{color:var(--accent);text-decoration-thickness:1px;text-underline-offset:2px}
                .copy strong,.copy b{color:var(--text)}
                .copy code{padding:.14rem .34rem;border-radius:8px;background:rgba(var(--accent-rgb),.08);color:var(--accent-strong);font-family:Consolas,"Courier New",monospace;font-size:.92em}
                .alert{margin:0 0 18px;padding:13px 15px;border-radius:16px;font-size:14px;line-height:1.55}
                .alert.success{background:rgba(15,122,77,.10);color:var(--success);border:1px solid rgba(15,122,77,.16)}
                .alert.error{background:rgba(155,28,28,.08);color:var(--error);border:1px solid rgba(155,28,28,.14)}
                .alert.warning{background:rgba(138,88,0,.08);color:var(--warning);border:1px solid rgba(138,88,0,.14)}
                .form{display:grid;gap:14px}
                .field label{display:block;margin:0 0 8px;font-size:14px;font-weight:600}
                .input{width:100%;min-height:52px;padding:14px 16px;border:1px solid rgba(var(--accent-rgb),.16);border-radius:16px;background:#fff;color:var(--text);font:inherit;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
                .input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 4px rgba(var(--accent-rgb),.12);transform:translateY(-1px)}
                .button{min-height:54px;border:0;border-radius:16px;padding:14px 18px;background:linear-gradient(135deg,var(--accent) 0%,var(--accent-strong) 100%);color:#fff;font:inherit;font-weight:700;cursor:pointer;box-shadow:0 16px 36px rgba(var(--accent-rgb),.18)}
                .button[disabled]{opacity:.7;cursor:wait}
                .microcopy{margin:10px 2px 0;font-size:.94rem;line-height:1.55;color:var(--muted)}
                .proof{margin:0 0 18px;padding:12px 14px;border-radius:14px;background:rgba(var(--accent-rgb),.07);border:1px solid rgba(var(--accent-rgb),.12);color:var(--accent-strong);font-size:.95rem;line-height:1.55}
                .success-box{display:grid;gap:12px}
                .success-box h2{margin:0;font-size:1.5rem;letter-spacing:-.03em}
                .success-box p{margin:0;color:var(--muted);line-height:1.65}
                .hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
                @media (max-width:480px){body{padding:12px}.wrap{min-height:calc(100vh - 24px)}.card{border-radius:22px;padding:24px 18px}.topline{align-items:flex-start}}
            </style>
        </head>
        <body>
            <main class="wrap">
                <section class="card">
                    <div class="topline">
                        <div class="logo"><?php echo esc_html($logo); ?></div>
                        <div>
                            <span class="site"><?php echo esc_html($site_name); ?></span>
                            <span class="status"><?php echo esc_html($this->get_mode_label()); ?></span>
                        </div>
                    </div>

                    <h1><?php echo esc_html($headline); ?></h1>
                    <div class="copy"><?php echo $description_html; ?></div>
                    <?php if ($social_proof_text !== '') : ?>
                        <div class="proof"><?php echo esc_html($social_proof_text); ?></div>
                    <?php endif; ?>

                    <?php if ($feedback && !$form_hidden) : ?>
                        <div class="alert <?php echo esc_attr($this->status_class($status)); ?>" role="status" tabindex="-1" data-wvcsn-alert><?php echo esc_html($feedback['message']); ?></div>
                    <?php else : ?>
                        <div hidden class="alert" role="status" tabindex="-1" data-wvcsn-alert></div>
                    <?php endif; ?>

                    <form class="form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-wvcsn-form <?php echo $form_hidden ? 'hidden' : ''; ?>>
                        <input type="hidden" name="action" value="wvcsn_submit_waitlist">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($tracking['landing_url']); ?>">
                        <input type="hidden" name="landing_url" value="<?php echo esc_url($tracking['landing_url']); ?>">
                        <input type="hidden" name="referrer_host" value="<?php echo esc_attr($tracking['referrer_host']); ?>">
                        <input type="hidden" name="utm_source" value="<?php echo esc_attr($tracking['utm_source']); ?>">
                        <input type="hidden" name="utm_medium" value="<?php echo esc_attr($tracking['utm_medium']); ?>">
                        <input type="hidden" name="utm_campaign" value="<?php echo esc_attr($tracking['utm_campaign']); ?>">
                        <input type="hidden" name="utm_content" value="<?php echo esc_attr($tracking['utm_content']); ?>">
                        <input type="hidden" name="utm_term" value="<?php echo esc_attr($tracking['utm_term']); ?>">
                        <input type="hidden" name="wvcsn_context_time" value="<?php echo esc_attr((string) $form_context_issued_at); ?>">
                        <input type="hidden" name="wvcsn_context_sig" value="<?php echo esc_attr($form_context_signature); ?>">
                        <?php wp_nonce_field(self::NONCE_ACTION_SUBMIT); ?>
                        <div class="field">
                            <label for="wvcsn_name"><?php esc_html_e('Name', 'wvcsn'); ?></label>
                            <input class="input" type="text" id="wvcsn_name" name="wvcsn_name" autocomplete="name" maxlength="<?php echo esc_attr(self::MAX_NAME_LENGTH); ?>" required>
                        </div>
                        <div class="field">
                            <label for="wvcsn_email"><?php esc_html_e('Email', 'wvcsn'); ?></label>
                            <input class="input" type="email" id="wvcsn_email" name="wvcsn_email" autocomplete="email" inputmode="email" maxlength="<?php echo esc_attr(self::MAX_EMAIL_LENGTH); ?>" required>
                        </div>
                        <div class="hp" aria-hidden="true">
                            <label for="wvcsn_company"><?php esc_html_e('Company', 'wvcsn'); ?></label>
                            <input type="text" id="wvcsn_company" name="company" tabindex="-1" autocomplete="off">
                        </div>
                        <button class="button" type="submit" data-wvcsn-submit><?php echo esc_html($button_text); ?></button>
                        <p class="microcopy"><?php echo esc_html($button_microcopy); ?></p>
                    </form>

                    <div class="success-box" tabindex="-1" data-wvcsn-success <?php echo $form_hidden ? '' : 'hidden'; ?>>
                        <h2 data-wvcsn-success-title><?php echo esc_html($success_title); ?></h2>
                        <p data-wvcsn-success-message><?php echo esc_html($success_copy); ?></p>
                    </div>
                </section>
            </main>
            <script>
                (function(){var form=document.querySelector('[data-wvcsn-form]'),alertBox=document.querySelector('[data-wvcsn-alert]'),successBox=document.querySelector('[data-wvcsn-success]'),successTitle=document.querySelector('[data-wvcsn-success-title]'),successMessage=document.querySelector('[data-wvcsn-success-message]'),submitButton=document.querySelector('[data-wvcsn-submit]'),nativeSubmit=false;if(!form||!alertBox||!submitButton||!window.fetch||!window.FormData){return;}function hideAlert(){alertBox.hidden=true;alertBox.className='alert';alertBox.textContent='';}function showAlert(message,typeClass){alertBox.hidden=false;alertBox.className='alert '+typeClass;alertBox.textContent=message;alertBox.focus();}function showSuccess(title,message){hideAlert();if(successTitle){successTitle.textContent=title;}if(successMessage){successMessage.textContent=message;}form.reset();form.hidden=true;successBox.hidden=false;successBox.focus();}form.addEventListener('submit',function(event){if(nativeSubmit){return;}event.preventDefault();successBox.hidden=true;hideAlert();var data=new FormData(form);data.append('wvcsn_async','1');submitButton.disabled=true;submitButton.setAttribute('aria-busy','true');fetch(form.action,{method:'POST',body:data,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}}).then(function(response){if(!response.ok){throw new Error('network');}return response.json();}).then(function(payload){if(!payload||!payload.success||!payload.data){throw new Error('payload');}if(payload.data.replaceForm){showSuccess(payload.data.code==='duplicate'?'You are already on the list.':'Thanks. You are on the list.',payload.data.code==='duplicate'?'We already have this email saved. We will reach out when the site is ready.':'We will email you when the site is ready.');}else{showAlert(payload.data.message,payload.data.type);}submitButton.disabled=false;submitButton.removeAttribute('aria-busy');}).catch(function(){nativeSubmit=true;submitButton.disabled=false;submitButton.removeAttribute('aria-busy');form.submit();});});}());
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    private function process_waitlist_submission() {
        if (!$this->is_enabled()) {
            return $this->build_submission_response('error');
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::NONCE_ACTION_SUBMIT)) {
            return $this->build_submission_response('invalid');
        }

        $honeypot = isset($_POST['company']) ? trim((string) wp_unslash($_POST['company'])) : '';
        if ($honeypot !== '') {
            return $this->build_submission_response('success');
        }

        if ($this->is_rate_limited()) {
            return $this->build_submission_response('rate_limited');
        }
        $this->bump_rate_limit();

        $name = isset($_POST['wvcsn_name']) ? sanitize_text_field(wp_unslash($_POST['wvcsn_name'])) : '';
        $email = isset($_POST['wvcsn_email']) ? $this->normalize_email(sanitize_email(wp_unslash($_POST['wvcsn_email']))) : '';

        if (!$this->is_valid_name($name) || !$this->is_valid_email($email)) {
            return $this->build_submission_response('invalid');
        }

        if (!$this->is_table_ready()) {
            $this->maybe_install_schema();
        }

        if (!$this->is_table_ready()) {
            return $this->build_submission_response('error');
        }

        $result = $this->save_waitlist_entry($name, $email, $this->get_submission_context());

        if ($result === 'success') {
            return $this->build_submission_response('success');
        }

        if ($result === 'duplicate') {
            return $this->build_submission_response('duplicate');
        }

        return $this->build_submission_response('error');
    }

    private function build_submission_response($code) {
        $messages = array(
            'success' => array('message' => __('Thanks. You are on the list.', 'wvcsn'), 'replace_form' => true, 'type' => 'success'),
            'duplicate' => array('message' => __('This email is already on the list.', 'wvcsn'), 'replace_form' => true, 'type' => 'success'),
            'invalid' => array('message' => __('Please enter a real name and a valid email address.', 'wvcsn'), 'replace_form' => false, 'type' => 'error'),
            'rate_limited' => array('message' => __('Too many attempts from this connection. Please wait a few minutes and try again.', 'wvcsn'), 'replace_form' => false, 'type' => 'warning'),
            'error' => array('message' => __('We could not save your details right now. Please try again shortly.', 'wvcsn'), 'replace_form' => false, 'type' => 'error'),
        );

        return array_merge(array('code' => $code), $messages[$code]);
    }

    private function get_feedback_message($status) {
        $allowed = array('success', 'duplicate', 'invalid', 'rate_limited', 'error');

        if (!in_array($status, $allowed, true)) {
            return null;
        }

        return $this->build_submission_response($status);
    }

    private function status_class($status) {
        if ($status === 'success' || $status === 'duplicate') {
            return 'success';
        }

        if ($status === 'rate_limited') {
            return 'warning';
        }

        return 'error';
    }

    private function maybe_install_schema() {
        if (get_option(self::OPTION_SCHEMA_VERSION) === self::SCHEMA_VERSION && $this->is_table_ready() && $this->is_visitor_table_ready()) {
            return;
        }

        $this->create_waitlist_table();
        $this->create_visitor_table();

        if ($this->is_table_ready() && $this->is_visitor_table_ready()) {
            update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false);
        }
    }

    private function create_waitlist_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            name varchar(120) NOT NULL,
            email varchar(190) NOT NULL,
            ip_hash char(64) NOT NULL DEFAULT '',
            landing_url text NOT NULL,
            referrer_host varchar(190) NOT NULL DEFAULT '',
            utm_source varchar(190) NOT NULL DEFAULT '',
            utm_medium varchar(190) NOT NULL DEFAULT '',
            utm_campaign varchar(190) NOT NULL DEFAULT '',
            utm_content varchar(190) NOT NULL DEFAULT '',
            utm_term varchar(190) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
        $this->table_ready = null;
    }

    private function create_visitor_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $this->get_visitor_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            visitor_hash char(64) NOT NULL,
            fingerprint_hash char(64) NOT NULL DEFAULT '',
            first_seen datetime NOT NULL,
            last_seen datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY visitor_hash (visitor_hash),
            KEY fingerprint_hash (fingerprint_hash),
            KEY last_seen (last_seen)
        ) {$charset_collate};";

        dbDelta($sql);
        $this->visitor_table_ready = null;
    }

    private function maybe_define_runtime_compat_flags() {
        if (!$this->is_cache_sensitive_frontend_request()) {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        if (!defined('DONOTCACHEOBJECT')) {
            define('DONOTCACHEOBJECT', true);
        }

        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }

        if (!defined('DONOTMINIFY')) {
            define('DONOTMINIFY', true);
        }

        if (!defined('DONOTCDN')) {
            define('DONOTCDN', true);
        }
    }

    public function maybe_send_runtime_headers() {
        if (!$this->is_enabled() || $this->should_bypass_coming_soon()) {
            return;
        }

        $this->maybe_set_visitor_cookie();
        $this->send_coming_soon_headers();
    }

    private function maybe_set_visitor_cookie() {
        if ($this->get_visitor_token() !== '') {
            return;
        }

        $this->set_runtime_cookie(self::VISITOR_COOKIE, $this->generate_random_token(32), self::VISITOR_COOKIE_TTL);
    }

    private function save_waitlist_entry($name, $email, $context) {
        global $wpdb;

        $query = $wpdb->prepare(
            "INSERT IGNORE INTO {$this->get_table_name()} (created_at, name, email, ip_hash, landing_url, referrer_host, utm_source, utm_medium, utm_campaign, utm_content, utm_term) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
            gmdate('Y-m-d H:i:s'),
            $name,
            $email,
            $this->get_request_ip_hash(),
            $context['landing_url'],
            $context['referrer_host'],
            $context['utm_source'],
            $context['utm_medium'],
            $context['utm_campaign'],
            $context['utm_content'],
            $context['utm_term']
        );

        $result = $wpdb->query($query);

        if ($result === 1) {
            return 'success';
        }

        if ($result === 0) {
            return 'duplicate';
        }

        $this->report_runtime_notice('waitlist_insert_failed', array('db_error' => $wpdb->last_error));

        return 'error';
    }

    private function stream_waitlist_csv($filters = array()) {
        global $wpdb;

        $filters = $this->normalize_export_filters($filters);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->build_export_filename($filters) . '"');
        header('X-Content-Type-Options: nosniff', true);

        $output = fopen('php://output', 'w');
        if (!$output) {
            $this->report_runtime_notice('csv_stream_open_failed');
            wp_safe_redirect($this->get_settings_url('export_error'));
            exit;
        }

        fputcsv($output, array('submitted_at_utc', 'name', 'email', 'landing_url', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'));

        $last_id = 0;
        $limit = self::CSV_BATCH_SIZE;

        do {
            $query_parts = $this->build_waitlist_filter_query_parts($filters, true, $last_id);
            $rows = $wpdb->get_results(
                $this->prepare_query(
                    "SELECT id, created_at, name, email, landing_url, referrer_host, utm_source, utm_medium, utm_campaign, utm_content, utm_term FROM {$this->get_table_name()}{$query_parts['sql']} ORDER BY id ASC LIMIT %d",
                    array_merge($query_parts['params'], array($limit))
                ),
                ARRAY_A
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                fputcsv($output, array($row['created_at'], $row['name'], $row['email'], $row['landing_url'], $row['referrer_host'], $row['utm_source'], $row['utm_medium'], $row['utm_campaign'], $row['utm_content'], $row['utm_term']));
                $last_id = (int) $row['id'];
            }

            if (function_exists('flush')) {
                flush();
            }
        } while (count($rows) === $limit);

        fclose($output);
        exit;
    }

    private function get_admin_notice() {
        $status = isset($_GET['wvcsn_admin_status']) ? sanitize_key(wp_unslash($_GET['wvcsn_admin_status'])) : '';

        $messages = array(
            'empty' => array('message' => __('There is nothing to export yet. Add at least one valid signup, then try the CSV export again.', 'wvcsn'), 'color' => '#8a5800'),
            'empty_filtered' => array('message' => __('No saved contacts matched the export filters. Try a wider date range or leave the source blank.', 'wvcsn'), 'color' => '#8a5800'),
            'storage_error' => array('message' => __('The waitlist table is not ready yet. Please save the page once or reactivate the plugin.', 'wvcsn'), 'color' => '#9b1c1c'),
            'export_error' => array('message' => __('The CSV export could not be generated. Please try again.', 'wvcsn'), 'color' => '#9b1c1c'),
            'cleared' => array('message' => __('The saved contact list is now empty.', 'wvcsn'), 'color' => '#0f7a4d'),
            'clear_error' => array('message' => __('The saved contact list could not be emptied. Please try again.', 'wvcsn'), 'color' => '#9b1c1c'),
            'counts_cleared' => array('message' => __('The saved visitor counts are now empty.', 'wvcsn'), 'color' => '#0f7a4d'),
            'counts_clear_error' => array('message' => __('The saved visitor counts could not be emptied. Please try again.', 'wvcsn'), 'color' => '#9b1c1c'),
        );

        return isset($messages[$status]) ? $messages[$status] : null;
    }

    private function get_settings_url($status = '') {
        $url = admin_url('options-general.php?page=' . self::SETTINGS_SLUG);

        return $status === '' ? $url : add_query_arg('wvcsn_admin_status', $status, $url);
    }

    private function get_submission_redirect() {
        $fallback = home_url('/');

        if (isset($_POST['redirect_to'])) {
            return wp_validate_redirect(esc_url_raw(wp_unslash($_POST['redirect_to'])), $fallback);
        }

        $referer = wp_get_referer();

        if (is_string($referer) && $referer !== '') {
            return wp_validate_redirect($referer, $fallback);
        }

        return $fallback;
    }

    private function should_bypass_coming_soon() {
        if ($this->is_request_context_exempt()) {
            return true;
        }

        if ($this->bypass_current_request) {
            return true;
        }

        if ($this->has_valid_bypass_cookie()) {
            return true;
        }

        return $this->current_request_is_allowlisted(true);
    }

    private function is_cache_sensitive_frontend_request() {
        if (!$this->is_enabled() || is_admin()) {
            return false;
        }

        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        if ((defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) || (defined('WP_CLI') && WP_CLI)) {
            return false;
        }

        if ($this->has_valid_bypass_cookie()) {
            return false;
        }

        return !$this->current_request_is_allowlisted();
    }

    private function send_coming_soon_headers() {
        if (headers_sent($file, $line)) {
            $this->report_runtime_notice('headers_already_sent', array('file' => $file, 'line' => $line));

            return false;
        }

        if ($this->get_mode() === self::MODE_MAINTENANCE) {
            status_header(503);
            header('Retry-After: 3600', true);
        } else {
            status_header(200);
        }

        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT', true);
        header('Surrogate-Control: no-store', true);
        header('X-Robots-Tag: noindex, nofollow', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Vary: Cookie', false);

        return true;
    }

    private function report_runtime_notice($code, $context = array()) {
        do_action('wvcsn_runtime_notice', $code, $context);

        if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG || !function_exists('wp_json_encode')) {
            return;
        }

        error_log('Webvas Coming Soon: ' . $code . ' ' . wp_json_encode($context));
    }

    private function maybe_track_visit() {
        $visitor_identity = $this->get_visit_identity();

        if ($visitor_identity === '') {
            $this->report_runtime_notice('visitor_cookie_missing');
            return;
        }

        if (!$this->is_visitor_table_ready()) {
            $this->maybe_install_schema();
        }

        if (!$this->is_visitor_table_ready()) {
            return;
        }

        $this->record_unique_visitor($visitor_identity);
    }

    private function set_runtime_cookie($name, $value, $ttl) {
        if (headers_sent($file, $line)) {
            $this->report_runtime_notice('runtime_cookie_skipped', array('cookie' => $name, 'file' => $file, 'line' => $line));

            return false;
        }

        $result = setcookie($name, $value, $this->get_runtime_cookie_options(time() + (int) $ttl));

        if ($result) {
            $_COOKIE[$name] = $value;
        }

        return $result;
    }

    private function get_runtime_cookie_options($expires) {
        return array(
            'expires' => $expires,
            'path' => $this->get_runtime_cookie_path(),
            'domain' => $this->get_runtime_cookie_domain(),
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        );
    }

    private function record_unique_visitor($visitor_identity) {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $visitor_hash = hash_hmac('sha256', $visitor_identity, $this->get_runtime_secret('nonce'));
        $fingerprint_hash = $this->get_request_fingerprint_hash();
        $table = $this->get_visitor_table_name();

        $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE visitor_hash = %s LIMIT 1", $visitor_hash));
        if ($existing_id > 0) {
            $updated = $wpdb->update(
                $table,
                array(
                    'fingerprint_hash' => $fingerprint_hash,
                    'last_seen' => $now,
                ),
                array('id' => $existing_id),
                array('%s', '%s'),
                array('%d')
            );

            if ($updated === false) {
                $this->report_runtime_notice('visitor_update_failed', array('db_error' => $wpdb->last_error));
            }

            return;
        }

        if ($fingerprint_hash !== '') {
            $fingerprint_match_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE fingerprint_hash = %s ORDER BY last_seen DESC LIMIT 1", $fingerprint_hash));
            if ($fingerprint_match_id > 0) {
                $updated = $wpdb->update(
                    $table,
                    array(
                        'visitor_hash' => $visitor_hash,
                        'fingerprint_hash' => $fingerprint_hash,
                        'last_seen' => $now,
                    ),
                    array('id' => $fingerprint_match_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );

                if ($updated === false) {
                    $this->report_runtime_notice('visitor_fingerprint_merge_failed', array('db_error' => $wpdb->last_error));
                }

                return;
            }
        }

        $result = $wpdb->insert(
            $table,
            array(
                'visitor_hash' => $visitor_hash,
                'fingerprint_hash' => $fingerprint_hash,
                'first_seen' => $now,
                'last_seen' => $now,
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            $this->report_runtime_notice('visitor_insert_failed', array('db_error' => $wpdb->last_error));
        }
    }

    private function is_rate_limited() {
        return (int) get_transient($this->get_rate_limit_key()) >= self::RATE_LIMIT_MAX_ATTEMPTS;
    }

    private function bump_rate_limit() {
        $key = $this->get_rate_limit_key();
        $attempts = (int) get_transient($key);
        set_transient($key, $attempts + 1, self::RATE_LIMIT_WINDOW);
    }

    private function get_rate_limit_key() {
        return 'wvcsn_rl_' . hash_hmac('sha256', $this->get_visitor_token() . '|' . $this->get_request_ip(), $this->get_runtime_secret('nonce'));
    }

    private function get_request_ip_hash() {
        $ip = $this->get_request_ip();

        return $ip === '' ? '' : hash_hmac('sha256', $ip, $this->get_runtime_secret('auth'));
    }

    private function get_request_ip() {
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '';
    }

    private function is_request_context_exempt() {
        if (is_admin() || $this->current_user_can_manage_plugin()) {
            return true;
        }

        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return true;
        }

        if ((defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) || (defined('WP_CLI') && WP_CLI)) {
            return true;
        }

        return is_feed() || is_trackback() || is_robots();
    }

    private function get_mode() {
        $mode = get_option(self::OPTION_MODE, self::MODE_MAINTENANCE);

        return $mode === self::MODE_COMING_SOON ? self::MODE_COMING_SOON : self::MODE_MAINTENANCE;
    }

    private function get_mode_label() {
        return $this->get_mode() === self::MODE_COMING_SOON ? __('Coming Soon', 'wvcsn') : __('Maintenance', 'wvcsn');
    }

    private function get_bypass_token() {
        $stored = (string) get_option(self::OPTION_BYPASS_TOKEN, '');
        $token = $this->sanitize_bypass_token_option($stored);

        if ($token !== $stored) {
            update_option(self::OPTION_BYPASS_TOKEN, $token, false);
        }

        return $token;
    }

    private function get_bypass_url() {
        return add_query_arg(self::BYPASS_QUERY_ARG, $this->get_bypass_token(), home_url('/'));
    }

    private function get_bypass_cookie_value() {
        return hash_hmac('sha256', $this->get_bypass_token(), $this->get_runtime_secret('secure_auth'));
    }

    private function has_valid_bypass_cookie() {
        if (empty($_COOKIE[self::BYPASS_COOKIE])) {
            return false;
        }

        $cookie_value = sanitize_text_field(wp_unslash($_COOKIE[self::BYPASS_COOKIE]));

        return $cookie_value !== '' && hash_equals($this->get_bypass_cookie_value(), $cookie_value);
    }

    private function get_request_tracking_context() {
        return array(
            'landing_url' => $this->get_current_request_url(array('wvcsn_status', self::BYPASS_QUERY_ARG)),
            'referrer_host' => $this->get_referrer_host_from_server(),
            'utm_source' => $this->sanitize_tracking_value(isset($_GET['utm_source']) ? wp_unslash($_GET['utm_source']) : '', self::MAX_TRACKING_VALUE_LENGTH),
            'utm_medium' => $this->sanitize_tracking_value(isset($_GET['utm_medium']) ? wp_unslash($_GET['utm_medium']) : '', self::MAX_TRACKING_VALUE_LENGTH),
            'utm_campaign' => $this->sanitize_tracking_value(isset($_GET['utm_campaign']) ? wp_unslash($_GET['utm_campaign']) : '', self::MAX_TRACKING_VALUE_LENGTH),
            'utm_content' => $this->sanitize_tracking_value(isset($_GET['utm_content']) ? wp_unslash($_GET['utm_content']) : '', self::MAX_TRACKING_VALUE_LENGTH),
            'utm_term' => $this->sanitize_tracking_value(isset($_GET['utm_term']) ? wp_unslash($_GET['utm_term']) : '', self::MAX_TRACKING_VALUE_LENGTH),
        );
    }

    private function get_submission_context() {
        $context = array(
            'landing_url' => $this->sanitize_submission_url(isset($_POST['landing_url']) ? wp_unslash($_POST['landing_url']) : ''),
            'referrer_host' => $this->sanitize_referrer_host(isset($_POST['referrer_host']) ? wp_unslash($_POST['referrer_host']) : ''),
            'utm_source' => $this->sanitize_tracking_value(isset($_POST['utm_source']) ? wp_unslash($_POST['utm_source']) : '', self::MAX_TRACKING_VALUE_LENGTH),
            'utm_medium' => $this->sanitize_tracking_value(isset($_POST['utm_medium']) ? wp_unslash($_POST['utm_medium']) : '', self::MAX_TRACKING_VALUE_LENGTH),
            'utm_campaign' => $this->sanitize_tracking_value(isset($_POST['utm_campaign']) ? wp_unslash($_POST['utm_campaign']) : '', self::MAX_TRACKING_VALUE_LENGTH),
            'utm_content' => $this->sanitize_tracking_value(isset($_POST['utm_content']) ? wp_unslash($_POST['utm_content']) : '', self::MAX_TRACKING_VALUE_LENGTH),
            'utm_term' => $this->sanitize_tracking_value(isset($_POST['utm_term']) ? wp_unslash($_POST['utm_term']) : '', self::MAX_TRACKING_VALUE_LENGTH),
        );

        if (!$this->tracking_context_is_valid($context)) {
            return array(
                'landing_url' => home_url('/'),
                'referrer_host' => $this->get_referrer_host_from_server(),
                'utm_source' => '',
                'utm_medium' => '',
                'utm_campaign' => '',
                'utm_content' => '',
                'utm_term' => '',
            );
        }

        $context['landing_url'] = $context['landing_url'] !== '' ? $context['landing_url'] : home_url('/');

        return $context;
    }

    private function sanitize_submission_url($url) {
        $clean = esc_url_raw((string) $url, array('http', 'https'));

        if ($clean === '') {
            return '';
        }

        $host = wp_parse_url($clean, PHP_URL_HOST);
        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);

        if (is_string($host) && is_string($site_host) && strtolower($host) !== strtolower($site_host)) {
            return '';
        }

        return $this->substr($clean, 0, self::MAX_URL_LENGTH);
    }

    private function sanitize_tracking_value($value, $max_length) {
        $clean = sanitize_text_field((string) $value);

        if ($clean === '') {
            return '';
        }

        return trim($this->substr($clean, 0, $max_length));
    }

    private function sanitize_referrer_host($host) {
        $host = strtolower($this->sanitize_tracking_value($host, self::MAX_TRACKING_VALUE_LENGTH));
        $host = preg_replace('/[^a-z0-9\.\-]/', '', $host);

        return is_string($host) ? $host : '';
    }

    private function get_referrer_host_from_server() {
        if (empty($_SERVER['HTTP_REFERER'])) {
            return '';
        }

        $referrer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']), array('http', 'https'));
        $host = wp_parse_url($referrer, PHP_URL_HOST);

        return is_string($host) ? $this->sanitize_referrer_host($host) : '';
    }

    private function get_current_request_url($exclude_query_args = array()) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $parsed = wp_parse_url($request_uri);
        $path = isset($parsed['path']) && is_string($parsed['path']) ? $this->to_relative_site_path($parsed['path']) : '/';
        $url = home_url($path);
        $query_args = array();

        if (!empty($parsed['query']) && is_string($parsed['query'])) {
            parse_str($parsed['query'], $raw_args);

            foreach ((array) $raw_args as $key => $value) {
                if (in_array($key, $exclude_query_args, true) || is_array($value)) {
                    continue;
                }

                $clean_key = sanitize_text_field((string) $key);
                if ($clean_key === '') {
                    continue;
                }

                $query_args[$clean_key] = sanitize_text_field((string) $value);
            }
        }

        return empty($query_args) ? $url : add_query_arg($query_args, $url);
    }

    private function current_request_is_allowlisted($respect_missing_content = false) {
        if ($respect_missing_content && $this->request_resolves_to_missing_content()) {
            return false;
        }

        $request_path = $this->get_request_path();

        foreach ($this->get_allowlist_rules() as $rule) {
            $wildcard = substr($rule, -1) === '*';
            $path = $wildcard ? substr($rule, 0, -1) : $rule;

            if ($wildcard && strpos($request_path, $path) === 0) {
                return true;
            }

            if (!$wildcard && $request_path === $path) {
                return true;
            }
        }

        return false;
    }

    private function request_resolves_to_missing_content() {
        return did_action('wp') > 0 && function_exists('is_404') && is_404();
    }

    private function get_allowlist_rules() {
        $value = (string) get_option(self::OPTION_ALLOWLIST_PATHS, '');

        return $value === '' ? array() : array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value)));
    }

    private function get_request_path() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = wp_parse_url($request_uri, PHP_URL_PATH);

        return $this->to_relative_site_path(is_string($path) ? $path : '/');
    }

    private function normalize_request_path($path) {
        $path = trim((string) $path);

        if ($path === '') {
            return '/';
        }

        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path);
        $path = is_string($path) ? $path : '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function to_relative_site_path($path) {
        $normalized = $this->normalize_request_path($path);
        $base_path = $this->get_site_base_path();

        if ($base_path !== '/' && strpos($normalized, $base_path) === 0) {
            $normalized = substr($normalized, strlen($base_path));
            $normalized = $normalized === false || $normalized === '' ? '/' : '/' . ltrim($normalized, '/');
        }

        return $this->normalize_request_path($normalized);
    }

    private function get_site_base_path() {
        $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);

        return $this->normalize_request_path(is_string($home_path) ? $home_path : '/');
    }

    private function get_visitor_token() {
        if (empty($_COOKIE[self::VISITOR_COOKIE])) {
            return '';
        }

        $token = preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_COOKIE[self::VISITOR_COOKIE]));

        return is_string($token) ? $token : '';
    }

    private function get_visit_identity() {
        $visitor_token = $this->get_visitor_token();

        if ($visitor_token !== '') {
            return $visitor_token;
        }

        $this->maybe_set_visitor_cookie();
        $visitor_token = $this->get_visitor_token();

        if ($visitor_token !== '') {
            return $visitor_token;
        }

        return $this->get_request_signature_fallback();
    }

    private function is_valid_name($name) {
        $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);

        return $length >= 2 && $length <= self::MAX_NAME_LENGTH;
    }

    private function is_valid_email($email) {
        if ($email === '') {
            return false;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($email) : strlen($email);

        return $length <= self::MAX_EMAIL_LENGTH && is_email($email);
    }

    private function normalize_email($email) {
        return strtolower(trim((string) $email));
    }

    private function get_visit_count() {
        global $wpdb;

        if (!$this->is_visitor_table_ready()) {
            $this->maybe_install_schema();
        }

        if (!$this->is_visitor_table_ready()) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->get_visitor_table_name()}");
    }

    private function generate_access_token() {
        return $this->generate_random_token(24);
    }

    private function get_signup_count($filters = array()) {
        global $wpdb;

        if (!$this->is_table_ready()) {
            return 0;
        }

        $query_parts = $this->build_waitlist_filter_query_parts($filters);

        return (int) $wpdb->get_var(
            $this->prepare_query(
                "SELECT COUNT(*) FROM {$this->get_table_name()}{$query_parts['sql']}",
                $query_parts['params']
            )
        );
    }

    private function is_table_ready() {
        global $wpdb;

        if ($this->table_ready !== null) {
            return $this->table_ready;
        }

        $table = $this->get_table_name();
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        $this->table_ready = ($found === $table);

        return $this->table_ready;
    }

    private function is_visitor_table_ready() {
        global $wpdb;

        if ($this->visitor_table_ready !== null) {
            return $this->visitor_table_ready;
        }

        $table = $this->get_visitor_table_name();
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        $this->visitor_table_ready = ($found === $table);

        return $this->visitor_table_ready;
    }

    private function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    private function get_visitor_table_name() {
        global $wpdb;

        return $wpdb->prefix . self::VISITOR_TABLE_SUFFIX;
    }

    private function clear_waitlist_entries() {
        global $wpdb;

        $deleted = $wpdb->query("DELETE FROM {$this->get_table_name()}");

        if ($deleted === false) {
            $this->report_runtime_notice('waitlist_clear_failed', array('db_error' => $wpdb->last_error));

            return false;
        }

        return (int) $deleted;
    }

    private function clear_visitor_entries() {
        global $wpdb;

        $deleted = $wpdb->query("DELETE FROM {$this->get_visitor_table_name()}");

        if ($deleted === false) {
            $this->report_runtime_notice('visitor_clear_failed', array('db_error' => $wpdb->last_error));

            return false;
        }

        return (int) $deleted;
    }

    private function get_export_filters_from_request() {
        return $this->normalize_export_filters(
            array(
                'from_date' => isset($_POST['wvcsn_export_from']) ? wp_unslash($_POST['wvcsn_export_from']) : '',
                'to_date' => isset($_POST['wvcsn_export_to']) ? wp_unslash($_POST['wvcsn_export_to']) : '',
                'source' => isset($_POST['wvcsn_export_source']) ? wp_unslash($_POST['wvcsn_export_source']) : '',
            )
        );
    }

    private function get_default_headline() {
        return __('We are getting things ready.', 'wvcsn');
    }

    private function get_default_brand_color() {
        return '#0f43aa';
    }

    private function get_default_description() {
        return __('Leave your name and email. We will let you know as soon as the site is live.', 'wvcsn');
    }

    private function get_default_button_text() {
        return __('Reserve My Spot', 'wvcsn');
    }

    private function get_default_button_microcopy() {
        return __('No spam. Only early access + exclusive updates.', 'wvcsn');
    }

    private function get_default_social_proof_text() {
        return '';
    }

    private function get_frontend_headline() {
        return $this->sanitize_headline_option((string) get_option(self::OPTION_HEADLINE, $this->get_default_headline()));
    }

    private function get_frontend_brand_color() {
        return $this->sanitize_brand_color_option((string) get_option(self::OPTION_BRAND_COLOR, $this->get_default_brand_color()));
    }

    private function get_frontend_description() {
        return $this->sanitize_description_option((string) get_option(self::OPTION_DESCRIPTION, $this->get_default_description()));
    }

    private function get_frontend_button_text() {
        return $this->sanitize_button_text_option((string) get_option(self::OPTION_BUTTON_TEXT, $this->get_default_button_text()));
    }

    private function get_frontend_button_microcopy() {
        return $this->sanitize_button_microcopy_option((string) get_option(self::OPTION_BUTTON_MICROCOPY, $this->get_default_button_microcopy()));
    }

    private function get_social_proof_mode() {
        return $this->sanitize_social_proof_mode_option((string) get_option(self::OPTION_SOCIAL_PROOF_MODE, self::SOCIAL_PROOF_OFF));
    }

    private function get_social_proof_text() {
        return $this->sanitize_social_proof_text_option((string) get_option(self::OPTION_SOCIAL_PROOF_TEXT, $this->get_default_social_proof_text()));
    }

    private function get_social_proof_preview_text($signup_count = null, $visit_count = null) {
        $mode = $this->get_social_proof_mode();

        if ($mode === self::SOCIAL_PROOF_CUSTOM) {
            $custom_text = $this->replace_social_proof_tokens($this->get_social_proof_text(), $signup_count, $visit_count);

            return $custom_text !== '' ? $custom_text : __('Custom text is empty, so nothing will show on the page.', 'wvcsn');
        }

        if ($mode === self::SOCIAL_PROOF_AUTO) {
            $auto_text = $this->build_auto_social_proof_text($signup_count, $visit_count);

            return $auto_text !== '' ? $auto_text : __('Automatic mode is currently hidden because the numbers are still low.', 'wvcsn');
        }

        return __('Social proof is turned off.', 'wvcsn');
    }

    private function get_social_proof_text_for_frontend() {
        $mode = $this->get_social_proof_mode();

        if ($mode === self::SOCIAL_PROOF_CUSTOM) {
            return $this->replace_social_proof_tokens($this->get_social_proof_text());
        }

        if ($mode !== self::SOCIAL_PROOF_AUTO) {
            return '';
        }

        return $this->build_auto_social_proof_text();
    }

    private function replace_social_proof_tokens($text, $signup_count = null, $visit_count = null) {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        $readiness = $this->get_launch_readiness_snapshot($signup_count, $visit_count);

        return strtr(
            $text,
            array(
                '{count}' => number_format_i18n($readiness['signup_count']),
                '{visitors}' => number_format_i18n($readiness['visit_count']),
                '{rate}' => $readiness['conversion_rate_label'],
            )
        );
    }

    private function build_auto_social_proof_text($signup_count = null, $visit_count = null) {
        $signup_count = $signup_count === null ? $this->get_signup_count() : max(0, (int) $signup_count);
        $visit_count = $visit_count === null ? $this->get_visit_count() : max(0, (int) $visit_count);

        if ($signup_count >= 100) {
            return sprintf(__('Join %s people already waiting for this launch.', 'wvcsn'), number_format_i18n($signup_count));
        }

        if ($signup_count >= 25) {
            return sprintf(__('Join %s people already waiting for this launch.', 'wvcsn'), number_format_i18n($signup_count));
        }

        if ($signup_count >= 10 && $visit_count >= 50) {
            return sprintf(__('Join %s early people already waiting for this launch.', 'wvcsn'), number_format_i18n($signup_count));
        }

        if ($visit_count >= 100) {
            return __('Be among the first people to get early access.', 'wvcsn');
        }

        return '';
    }

    private function get_launch_readiness_snapshot($signup_count = null, $visit_count = null) {
        $signup_count = $signup_count === null ? $this->get_signup_count() : max(0, (int) $signup_count);
        $visit_count = $visit_count === null ? $this->get_visit_count() : max(0, (int) $visit_count);
        $conversion_rate = $visit_count > 0 ? ($signup_count / $visit_count) * 100 : 0.0;
        $score = 0;

        if ($signup_count >= 10) {
            $score++;
        }

        if ($signup_count >= 40) {
            $score++;
        }

        if ($visit_count >= 100) {
            $score++;
        }

        if ($visit_count >= 300) {
            $score++;
        }

        if ($conversion_rate >= 2.0) {
            $score++;
        }

        if ($conversion_rate >= 5.0) {
            $score++;
        }

        $snapshot = array(
            'key' => 'early',
            'label' => __('Early', 'wvcsn'),
            'color' => '#9b1c1c',
            'background' => 'rgba(155,28,28,.07)',
            'summary' => __('You are still early. Keep building qualified traffic and growing the waitlist before launch.', 'wvcsn'),
        );

        if ($score >= 5) {
            $snapshot = array(
                'key' => 'ready',
                'label' => __('Ready to Launch', 'wvcsn'),
                'color' => '#0f7a4d',
                'background' => 'rgba(15,122,77,.08)',
                'summary' => __('Momentum looks strong. Plan your launch push while the list is warm and paying attention.', 'wvcsn'),
            );
        } elseif ($score >= 3) {
            $snapshot = array(
                'key' => 'building',
                'label' => __('Building Momentum', 'wvcsn'),
                'color' => '#8a5800',
                'background' => 'rgba(138,88,0,.08)',
                'summary' => __('The numbers are moving in the right direction. Keep improving the message and warming the list.', 'wvcsn'),
            );
        }

        $snapshot['signup_count'] = $signup_count;
        $snapshot['visit_count'] = $visit_count;
        $snapshot['conversion_rate'] = $conversion_rate;
        $snapshot['conversion_rate_label'] = $this->format_percentage($conversion_rate);
        $snapshot['formula'] = __('Signup rate = people on the list divided by unique visitors, multiplied by 100.', 'wvcsn');
        $snapshot['detail'] = sprintf(
            __('Calculated from %1$s people on the list, %2$s unique visitors, and a %3$s signup rate.', 'wvcsn'),
            number_format_i18n($signup_count),
            number_format_i18n($visit_count),
            $snapshot['conversion_rate_label']
        );

        return $snapshot;
    }

    private function build_frontend_title($site_name, $headline) {
        $site_name = trim((string) $site_name);
        $headline = trim((string) $headline);

        if ($headline === '') {
            return $site_name;
        }

        if ($site_name === '' || strcasecmp($headline, $site_name) === 0) {
            return $headline;
        }

        return $headline . ' | ' . $site_name;
    }

    private function build_frontend_meta_description($description) {
        $description = $this->normalize_whitespace(wp_strip_all_tags((string) $description));

        if ($description === '') {
            $description = $this->get_default_description();
        }

        return $this->substr($description, 0, 155);
    }

    private function get_description_allowed_html() {
        return array(
            'a' => array(
                'href' => true,
            ),
            'strong' => array(),
            'em' => array(),
            'b' => array(),
            'i' => array(),
            'br' => array(),
            'code' => array(),
        );
    }

    private function format_frontend_description($description) {
        $description = str_replace(array("\r\n", "\r"), "\n", (string) $description);
        $description = trim($description);

        if ($description === '') {
            $description = $this->get_default_description();
        }

        $description = implode("<br>\n", array_map('trim', explode("\n", $description)));

        return wp_kses($description, $this->get_description_allowed_html(), array('http', 'https', 'mailto'));
    }

    private function normalize_export_filters($filters) {
        $normalized = array(
            'from_date' => $this->sanitize_export_date(isset($filters['from_date']) ? $filters['from_date'] : ''),
            'to_date' => $this->sanitize_export_date(isset($filters['to_date']) ? $filters['to_date'] : ''),
            'source' => $this->sanitize_tracking_value(isset($filters['source']) ? $filters['source'] : '', self::MAX_TRACKING_VALUE_LENGTH),
        );

        if ($normalized['from_date'] !== '' && $normalized['to_date'] !== '' && strcmp($normalized['from_date'], $normalized['to_date']) > 0) {
            $from_date = $normalized['from_date'];
            $normalized['from_date'] = $normalized['to_date'];
            $normalized['to_date'] = $from_date;
        }

        return $normalized;
    }

    private function build_export_filename($filters) {
        $filters = $this->normalize_export_filters($filters);
        $parts = array('webvas-coming-soon-waitlist');

        if ($filters['from_date'] !== '') {
            $parts[] = 'from-' . $filters['from_date'];
        }

        if ($filters['to_date'] !== '') {
            $parts[] = 'to-' . $filters['to_date'];
        }

        if ($filters['source'] !== '') {
            $source_slug = sanitize_title_with_dashes($filters['source']);
            if ($source_slug !== '') {
                $parts[] = 'source-' . $source_slug;
            }
        }

        if (function_exists('current_datetime')) {
            $timestamp = current_datetime()->format('Y-m-d_H-i-s');
        } elseif (function_exists('current_time')) {
            $timestamp = gmdate('Y-m-d_H-i-s', (int) current_time('timestamp'));
        } else {
            $timestamp = gmdate('Y-m-d_H-i-s');
        }

        $parts[] = $timestamp;

        return sanitize_file_name(implode('-', array_filter($parts))) . '.csv';
    }

    private function sanitize_export_date($date) {
        $date = trim((string) $date);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }

    private function filters_are_empty($filters) {
        $filters = $this->normalize_export_filters($filters);

        return $filters['from_date'] === '' && $filters['to_date'] === '' && $filters['source'] === '';
    }

    private function build_waitlist_filter_query_parts($filters, $include_cursor = false, $last_id = 0) {
        $filters = $this->normalize_export_filters($filters);
        $conditions = array();
        $params = array();

        if ($include_cursor) {
            $conditions[] = 'id > %d';
            $params[] = (int) $last_id;
        }

        if ($filters['from_date'] !== '') {
            $conditions[] = 'created_at >= %s';
            $params[] = $filters['from_date'] . ' 00:00:00';
        }

        if ($filters['to_date'] !== '') {
            $conditions[] = 'created_at <= %s';
            $params[] = $filters['to_date'] . ' 23:59:59';
        }

        if ($filters['source'] !== '') {
            $conditions[] = 'utm_source = %s';
            $params[] = $filters['source'];
        }

        return array(
            'sql' => empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
        );
    }

    private function prepare_query($sql, $params = array()) {
        global $wpdb;

        return empty($params) ? $sql : $wpdb->prepare($sql, $params);
    }

    private function append_audit_log($action, $details = array()) {
        $entries = get_option(self::OPTION_AUDIT_LOG, array());
        $entries = is_array($entries) ? $entries : array();
        $user_label = __('System', 'wvcsn');
        $user_id = 0;

        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if (is_object($user) && method_exists($user, 'exists') && $user->exists()) {
                $user_id = (int) $user->ID;
                $user_label = $user->display_name !== '' ? $user->display_name : $user->user_login;
            }
        }

        array_unshift(
            $entries,
            array(
                'time' => gmdate('Y-m-d H:i:s'),
                'action' => sanitize_key((string) $action),
                'user_id' => $user_id,
                'user_label' => sanitize_text_field((string) $user_label),
                'details' => $this->sanitize_audit_details($details),
            )
        );

        update_option(self::OPTION_AUDIT_LOG, array_slice($entries, 0, self::MAX_AUDIT_LOG_ENTRIES), false);
    }

    private function sanitize_audit_details($details) {
        $clean = array();

        foreach ((array) $details as $key => $value) {
            $clean_key = sanitize_key((string) $key);
            if ($clean_key === '') {
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $clean[$clean_key] = $value;
                continue;
            }

            $clean[$clean_key] = sanitize_text_field((string) $value);
        }

        return $clean;
    }

    private function get_audit_log_entries($limit = 8) {
        $entries = get_option(self::OPTION_AUDIT_LOG, array());
        $entries = is_array($entries) ? $entries : array();

        return array_slice($entries, 0, max(0, (int) $limit));
    }

    private function get_audit_action_label($action) {
        $labels = array(
            'toggle_page' => __('Turned page on or off', 'wvcsn'),
            'export_csv' => __('Downloaded CSV', 'wvcsn'),
            'clear_waitlist' => __('Deleted saved contacts', 'wvcsn'),
            'clear_counts' => __('Deleted visitor counts', 'wvcsn'),
        );

        return isset($labels[$action]) ? $labels[$action] : __('Admin action', 'wvcsn');
    }

    private function get_audit_entry_details($details) {
        if (isset($details['enabled'])) {
            return !empty($details['enabled']) ? __('Page turned on', 'wvcsn') : __('Page turned off', 'wvcsn');
        }

        if (isset($details['deleted_entries'])) {
            return sprintf(__('Removed %d saved contacts.', 'wvcsn'), (int) $details['deleted_entries']);
        }

        if (isset($details['cleared_counts'])) {
            return sprintf(__('Removed %d saved visitor counts.', 'wvcsn'), (int) $details['cleared_counts']);
        }

        if (isset($details['matches'])) {
            $parts = array(sprintf(__('Matches: %d', 'wvcsn'), (int) $details['matches']));

            if (!empty($details['from_date'])) {
                $parts[] = sprintf(__('From: %s', 'wvcsn'), (string) $details['from_date']);
            }

            if (!empty($details['to_date'])) {
                $parts[] = sprintf(__('To: %s', 'wvcsn'), (string) $details['to_date']);
            }

            if (!empty($details['source'])) {
                $parts[] = sprintf(__('Source: %s', 'wvcsn'), (string) $details['source']);
            }

            return implode(' | ', $parts);
        }

        return __('No extra details.', 'wvcsn');
    }

    private function get_runtime_secret($scheme) {
        if (function_exists('wp_salt')) {
            return wp_salt($scheme);
        }

        $constants = array(
            'auth' => array('AUTH_SALT', 'AUTH_KEY', 'NONCE_SALT', 'SECURE_AUTH_SALT'),
            'secure_auth' => array('SECURE_AUTH_SALT', 'SECURE_AUTH_KEY', 'AUTH_SALT', 'AUTH_KEY'),
            'nonce' => array('NONCE_SALT', 'NONCE_KEY', 'AUTH_SALT', 'AUTH_KEY'),
        );

        foreach (isset($constants[$scheme]) ? $constants[$scheme] : array() as $constant) {
            if (defined($constant) && is_string(constant($constant)) && constant($constant) !== '') {
                return constant($constant);
            }
        }

        return hash('sha256', ABSPATH . '|' . __FILE__ . '|' . (string) $scheme);
    }

    private function get_runtime_cookie_path() {
        $base_path = $this->get_site_base_path();

        return $base_path === '/' ? '/' : rtrim($base_path, '/') . '/';
    }

    private function get_runtime_cookie_domain() {
        return defined('COOKIE_DOMAIN') && is_string(COOKIE_DOMAIN) && COOKIE_DOMAIN !== '' ? COOKIE_DOMAIN : '';
    }

    private function get_request_fingerprint_hash() {
        $parts = array();
        $keys = array('HTTP_USER_AGENT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT', 'HTTP_SEC_CH_UA', 'HTTP_SEC_CH_UA_MOBILE', 'HTTP_SEC_CH_UA_PLATFORM');

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $parts[] = sanitize_text_field(wp_unslash($_SERVER[$key]));
            }
        }

        if (empty($parts)) {
            return '';
        }

        return hash_hmac('sha256', implode('|', $parts), $this->get_runtime_secret('auth'));
    }

    private function get_request_signature_fallback() {
        $ip = strtolower($this->get_request_ip());
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        if ($ip === '' && $user_agent === '') {
            return '';
        }

        return 'fallback_' . hash_hmac('sha256', $ip . '|' . $user_agent, $this->get_runtime_secret('nonce'));
    }

    private function build_tracking_context_signature($context, $issued_at) {
        $payload = array(
            'landing_url' => isset($context['landing_url']) ? (string) $context['landing_url'] : '',
            'referrer_host' => isset($context['referrer_host']) ? (string) $context['referrer_host'] : '',
            'utm_source' => isset($context['utm_source']) ? (string) $context['utm_source'] : '',
            'utm_medium' => isset($context['utm_medium']) ? (string) $context['utm_medium'] : '',
            'utm_campaign' => isset($context['utm_campaign']) ? (string) $context['utm_campaign'] : '',
            'utm_content' => isset($context['utm_content']) ? (string) $context['utm_content'] : '',
            'utm_term' => isset($context['utm_term']) ? (string) $context['utm_term'] : '',
            'issued_at' => (int) $issued_at,
        );

        return hash_hmac('sha256', $this->encode_context_payload($payload), $this->get_runtime_secret('nonce'));
    }

    private function tracking_context_is_valid($context) {
        $issued_at = isset($_POST['wvcsn_context_time']) ? (int) wp_unslash($_POST['wvcsn_context_time']) : 0;
        $signature = isset($_POST['wvcsn_context_sig']) ? sanitize_text_field(wp_unslash($_POST['wvcsn_context_sig'])) : '';

        if ($issued_at < (time() - DAY_IN_SECONDS) || $issued_at > (time() + 300) || $signature === '') {
            return false;
        }

        return hash_equals($this->build_tracking_context_signature($context, $issued_at), $signature);
    }

    private function encode_context_payload($payload) {
        if (function_exists('wp_json_encode')) {
            return (string) wp_json_encode($payload);
        }

        return (string) json_encode($payload);
    }

    private function generate_random_token($length) {
        $length = max(8, (int) $length);

        if (function_exists('random_bytes')) {
            try {
                return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
            } catch (Exception $exception) {
            }
        }

        return substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, $length);
    }

    private function get_plugin_version() {
        return self::VERSION;
    }

    private function get_developer_url() {
        return $this->normalize_public_url(apply_filters('wvcsn_developer_url', 'https://mywebvas.com/michael'), 'https://mywebvas.com/michael');
    }

    private function get_support_url() {
        return $this->normalize_public_url(apply_filters('wvcsn_support_url', 'https://mywebvas.com/contact'), 'https://mywebvas.com/contact');
    }

    private function get_feature_request_url() {
        return $this->normalize_public_url(apply_filters('wvcsn_feature_request_url', 'https://mywebvas.com/contact'), 'https://mywebvas.com/contact');
    }

    private function get_admin_guide_url() {
        return esc_url_raw(plugins_url('assets/docs/launch-guide.html', __FILE__), array('http', 'https'));
    }

    private function normalize_public_url($url, $fallback) {
        $clean = esc_url_raw((string) $url, array('http', 'https'));

        return $clean !== '' ? $clean : $fallback;
    }

    private function build_logo($title) {
        $title = trim((string) $title);
        $words = preg_split('/\s+/', $title);
        $words = array_values(array_filter((array) $words));

        if (empty($words)) {
            return 'WV';
        }

        if (count($words) === 1) {
            return strtoupper($this->substr($words[0], 0, 2));
        }

        return strtoupper($this->substr($words[0], 0, 1) . $this->substr($words[1], 0, 1));
    }

    private function get_brand_color_strong($hex) {
        return $this->adjust_hex_brightness($hex, -24, '#0b347f');
    }

    private function hex_to_rgb_css($hex, $fallback) {
        $hex = ltrim((string) $hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return $fallback;
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        return $red . ',' . $green . ',' . $blue;
    }

    private function adjust_hex_brightness($hex, $steps, $fallback) {
        $hex = ltrim((string) $hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return $fallback;
        }

        $steps = max(-255, min(255, (int) $steps));
        $colors = array(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );

        foreach ($colors as &$color) {
            $color = max(0, min(255, $color + $steps));
        }
        unset($color);

        return sprintf('#%02x%02x%02x', $colors[0], $colors[1], $colors[2]);
    }

    private function substr($value, $start, $length) {
        if (function_exists('mb_substr')) {
            return mb_substr($value, $start, $length);
        }

        return substr($value, $start, $length);
    }

    private function strlen($value) {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    private function normalize_whitespace($value) {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function format_percentage($value) {
        $value = max(0, (float) $value);

        return number_format_i18n($value, 1) . '%';
    }

    private function is_enabled() {
        return (bool) get_option(self::OPTION_ENABLED, 1);
    }
}

register_activation_hook(__FILE__, array('Webvas_Coming_Soon_Ultra_Tiny', 'activate'));
Webvas_Coming_Soon_Ultra_Tiny::bootstrap();
