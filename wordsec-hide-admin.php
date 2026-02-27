<?php
/**
 * Plugin Name: WordSec Hide Admin
 * Description: Menyembunyikan endpoint login WordPress dan mengarahkan akses wp-admin sesuai pengaturan.
 * Version: 1.0.0
 * Author: WordSec
 * License: GPL-2.0-or-later
 * Text Domain: wordsec-hide-admin
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WordSec_Hide_Admin_Plugin
{
    const OPTION_KEY = 'wordsec_hide_admin_options';

    /**
     * @var WordSec_Hide_Admin_Plugin|null
     */
    private static $instance = null;

    /**
     * @return WordSec_Hide_Admin_Plugin
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'register_rewrite_rule'));
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this, 'maybe_render_custom_login'), 1);
        add_action('init', array($this, 'maybe_block_default_paths'), 1);

        add_filter('login_url', array($this, 'filter_login_url'), 10, 3);

        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * @return array<string, mixed>
     */
    public function get_options()
    {
        $saved = get_option(self::OPTION_KEY, array());

        if (!is_array($saved)) {
            $saved = array();
        }

        return wp_parse_args(
            $saved,
            array(
                'feature_enabled' => '1',
                'login_slug' => 'inibukanlogin',
                'admin_redirect_mode' => '404',
                'admin_redirect_url' => home_url('/404'),
            )
        );
    }

    /**
     * @return string
     */
    public function get_login_slug()
    {
        $options = $this->get_options();
        $slug = sanitize_title($options['login_slug']);

        if ('' === $slug || in_array($slug, array('wp-admin', 'wp-login.php'), true)) {
            return 'inibukanlogin';
        }

        return $slug;
    }

    /**
     * @return string
     */
    public function get_custom_login_url()
    {
        return home_url('/' . $this->get_login_slug() . '/');
    }

    /**
     * @return bool
     */
    public function is_feature_enabled()
    {
        $options = $this->get_options();
        return isset($options['feature_enabled']) && '1' === (string) $options['feature_enabled'];
    }

    public function register_rewrite_rule()
    {
        add_rewrite_rule('^' . preg_quote($this->get_login_slug(), '/') . '/?$', 'index.php?wordsec_login=1', 'top');
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public function register_query_vars($vars)
    {
        $vars[] = 'wordsec_login';
        return $vars;
    }

    public function maybe_render_custom_login()
    {
        if (!$this->is_feature_enabled()) {
            return;
        }

        if ((int) get_query_var('wordsec_login') !== 1) {
            return;
        }

        global $pagenow;
        $pagenow = 'wp-login.php';

        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    public function maybe_block_default_paths()
    {
        if (!$this->is_feature_enabled()) {
            return;
        }

        if (is_user_logged_in()) {
            return;
        }

        $request_path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
        $request_path = trim((string) $request_path, '/');

        if ('' === $request_path) {
            return;
        }

        if ('wp-login.php' === $request_path) {
            $this->redirect_to_not_found();
        }

        if (0 === strpos($request_path, 'wp-admin')) {
            if ('wp-admin/admin-ajax.php' === $request_path || 'wp-admin/admin-post.php' === $request_path) {
                return;
            }

            $this->redirect_admin_access();
        }
    }

    public function redirect_admin_access()
    {
        $options = $this->get_options();

        if ('custom' === $options['admin_redirect_mode']) {
            $target = esc_url_raw($options['admin_redirect_url']);
            if (!empty($target)) {
                wp_safe_redirect($target, 302);
                exit;
            }
        }

        $this->redirect_to_not_found();
    }

    public function redirect_to_not_found()
    {
        $target = home_url('/404');
        wp_safe_redirect($target, 302);
        exit;
    }

    /**
     * @param string $login_url
     * @param string $redirect
     * @param bool   $force_reauth
     * @return string
     */
    public function filter_login_url($login_url, $redirect, $force_reauth)
    {
        if (!$this->is_feature_enabled()) {
            return $login_url;
        }

        $custom_login_url = $this->get_custom_login_url();

        if (!empty($redirect)) {
            $custom_login_url = add_query_arg('redirect_to', rawurlencode($redirect), $custom_login_url);
        }

        if ($force_reauth) {
            $custom_login_url = add_query_arg('reauth', '1', $custom_login_url);
        }

        return $custom_login_url;
    }

    public function register_admin_page()
    {
        add_options_page(
            __('WordSec Hide Admin', 'wordsec-hide-admin'),
            __('WordSec Hide Admin', 'wordsec-hide-admin'),
            'manage_options',
            'wordsec-hide-admin',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings()
    {
        register_setting(
            'wordsec_hide_admin_group',
            self::OPTION_KEY,
            array($this, 'sanitize_options')
        );

        add_settings_section(
            'wordsec_hide_admin_main',
            __('Pengaturan Utama', 'wordsec-hide-admin'),
            '__return_false',
            'wordsec-hide-admin'
        );

        add_settings_field(
            'feature_enabled',
            __('Aktifkan Fitur Hide Admin', 'wordsec-hide-admin'),
            array($this, 'render_feature_enabled_field'),
            'wordsec-hide-admin',
            'wordsec_hide_admin_main'
        );

        add_settings_field(
            'login_slug',
            __('Login Slug Baru', 'wordsec-hide-admin'),
            array($this, 'render_login_slug_field'),
            'wordsec-hide-admin',
            'wordsec_hide_admin_main'
        );

        add_settings_field(
            'admin_redirect_mode',
            __('Mode Redirect wp-admin', 'wordsec-hide-admin'),
            array($this, 'render_redirect_mode_field'),
            'wordsec-hide-admin',
            'wordsec_hide_admin_main'
        );

        add_settings_field(
            'admin_redirect_url',
            __('Custom Redirect URL', 'wordsec-hide-admin'),
            array($this, 'render_redirect_url_field'),
            'wordsec-hide-admin',
            'wordsec_hide_admin_main'
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function sanitize_options($input)
    {
        $old = $this->get_options();

        $clean = array(
            'feature_enabled' => isset($input['feature_enabled']) ? '1' : '0',
            'login_slug' => isset($input['login_slug']) ? sanitize_title((string) $input['login_slug']) : 'inibukanlogin',
            'admin_redirect_mode' => (isset($input['admin_redirect_mode']) && 'custom' === $input['admin_redirect_mode']) ? 'custom' : '404',
            'admin_redirect_url' => isset($input['admin_redirect_url']) ? esc_url_raw((string) $input['admin_redirect_url']) : home_url('/404'),
        );

        if ('' === $clean['login_slug'] || in_array($clean['login_slug'], array('wp-admin', 'wp-login.php'), true)) {
            $clean['login_slug'] = 'inibukanlogin';
        }

        if ('custom' !== $clean['admin_redirect_mode']) {
            $clean['admin_redirect_url'] = home_url('/404');
        }

        if ($old['login_slug'] !== $clean['login_slug']) {
            $this->register_rewrite_rule();
            flush_rewrite_rules();
        }

        return $clean;
    }

    public function render_feature_enabled_field()
    {
        $options = $this->get_options();
        ?>
        <label>
            <input
                type="checkbox"
                name="<?php echo esc_attr(self::OPTION_KEY); ?>[feature_enabled]"
                value="1"
                <?php checked(isset($options['feature_enabled']) ? $options['feature_enabled'] : '1', '1'); ?>
            />
            <?php esc_html_e('Aktifkan perlindungan hide login dan redirect wp-admin.', 'wordsec-hide-admin'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Jika dinonaktifkan, WordPress akan kembali menggunakan endpoint login default.', 'wordsec-hide-admin'); ?>
        </p>
        <?php
    }

    public function render_login_slug_field()
    {
        $options = $this->get_options();
        ?>
        <input
            type="text"
            name="<?php echo esc_attr(self::OPTION_KEY); ?>[login_slug]"
            value="<?php echo esc_attr($options['login_slug']); ?>"
            class="regular-text"
            placeholder="inibukanlogin"
        />
        <p class="description">
            <?php esc_html_e('Contoh URL login baru: https://domainanda.com/inibukanlogin', 'wordsec-hide-admin'); ?>
        </p>
        <?php
    }

    public function render_redirect_mode_field()
    {
        $options = $this->get_options();
        ?>
        <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[admin_redirect_mode]">
            <option value="404" <?php selected($options['admin_redirect_mode'], '404'); ?>>
                <?php esc_html_e('Arahkan ke /404', 'wordsec-hide-admin'); ?>
            </option>
            <option value="custom" <?php selected($options['admin_redirect_mode'], 'custom'); ?>>
                <?php esc_html_e('Arahkan ke URL custom', 'wordsec-hide-admin'); ?>
            </option>
        </select>
        <?php
    }

    public function render_redirect_url_field()
    {
        $options = $this->get_options();
        ?>
        <input
            type="url"
            name="<?php echo esc_attr(self::OPTION_KEY); ?>[admin_redirect_url]"
            value="<?php echo esc_attr($options['admin_redirect_url']); ?>"
            class="regular-text"
            placeholder="https://domainanda.com/404"
        />
        <p class="description">
            <?php esc_html_e('Dipakai jika mode redirect diatur ke URL custom.', 'wordsec-hide-admin'); ?>
        </p>
        <?php
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WordSec Hide Admin', 'wordsec-hide-admin'); ?></h1>
            <p><?php esc_html_e('Kelola slug login baru dan redirect untuk akses wp-admin yang tidak sah.', 'wordsec-hide-admin'); ?></p>

            <form action="options.php" method="post">
                <?php
                settings_fields('wordsec_hide_admin_group');
                do_settings_sections('wordsec-hide-admin');
                submit_button(__('Simpan Pengaturan', 'wordsec-hide-admin'));
                ?>
            </form>

            <hr />
            <p>
                <strong><?php esc_html_e('Login URL aktif:', 'wordsec-hide-admin'); ?></strong>
                <code><?php echo esc_html($this->is_feature_enabled() ? $this->get_custom_login_url() : wp_login_url()); ?></code>
            </p>
        </div>
        <?php
    }

    public static function activate()
    {
        $plugin = self::instance();

        if (!get_option(self::OPTION_KEY)) {
            update_option(
                self::OPTION_KEY,
                array(
                    'feature_enabled' => '1',
                    'login_slug' => 'inibukanlogin',
                    'admin_redirect_mode' => '404',
                    'admin_redirect_url' => home_url('/404'),
                )
            );
        }

        $plugin->register_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }
}

WordSec_Hide_Admin_Plugin::instance();
register_activation_hook(__FILE__, array('WordSec_Hide_Admin_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('WordSec_Hide_Admin_Plugin', 'deactivate'));
