<?php
if (!defined('ABSPATH')) { exit; }

final class EQW_Admin {
    public static function menu(): void {
        add_menu_page('Equiviq for Wordpress', 'Equiviq', 'manage_options', 'equiviq-wordpress', array(__CLASS__, 'page'), EQW_URL . 'assets/plugin-icon.png', 58);
        add_submenu_page('equiviq-wordpress', 'Settings', 'Settings', 'manage_options', 'equiviq-wordpress', array(__CLASS__, 'page'));
    }

    public static function menu_icon_style(): void {
        echo '<style>#toplevel_page_equiviq-wordpress .wp-menu-image img{box-sizing:border-box;width:20px!important;height:20px!important;max-width:20px!important;max-height:20px!important;object-fit:contain;padding:0!important;margin:8px!important}</style>';
    }

    public static function plugin_links(array $links): array {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=equiviq-wordpress')) . '">Settings &amp; setup</a>');
        return $links;
    }

    private static function notice(string $code): string {
        $messages = array(
            'saved' => 'Connection settings saved. The API key remains hidden.',
            'invalid' => 'Check the Equiviq HTTPS Guides API URL and API key, then try again.',
            'connected' => 'Connected to Equiviq. Public guides are available.',
            'failed' => 'Could not connect. Check the API URL, key, and Equiviq plugin status.',
        );
        return $messages[$code] ?? '';
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You cannot manage these settings.', 'equiviq-wordpress')); }
        $settings = EQW_Connection::settings();
        $notice = sanitize_key($_GET['eqw_notice'] ?? '');
        $configured = EQW_Connection::api_url() !== '' && EQW_Connection::api_key() !== '';
        echo '<div class="wrap"><h1><img src="' . esc_url(EQW_URL . 'assets/equiviq-logo.png') . '" alt="" width="38" height="38" style="display:inline-block;width:38px!important;height:38px!important;max-width:38px!important;max-height:38px!important;object-fit:contain;vertical-align:middle;margin-right:10px">Equiviq Settings</h1><p>Connect this WordPress site to Equiviq. The same API key is used for Knowledge and Lead Generation and is never sent to visitors.</p>';
        if (self::notice($notice) !== '') { echo '<div class="notice ' . ($notice === 'saved' || $notice === 'connected' ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>' . esc_html(self::notice($notice)) . '</p></div>'; }
        echo '<div class="card" style="max-width:900px;padding:24px"><h2>Setup</h2><p><strong>1.</strong> In Equiviq, open <strong>Settings → Plugins → Wordpress CMS Integration → Settings</strong>. Copy the displayed API endpoint.</p><p><strong>2.</strong> Create an API key there, give it a label for this WordPress site, and copy it immediately. Equiviq shows the key only once.</p><p><strong>3.</strong> Paste both values below, save, then click <strong>Test connection</strong>. Add the Equiviq Guides block to a WordPress page to publish the index.</p><p><strong>Connection:</strong> ' . ($configured ? '<span style="color:#008a20">Configured</span>' : '<span style="color:#b32d2e">Not configured</span>') . '</p></div>';
        echo '<div class="card" style="max-width:900px;padding:24px;margin-top:20px"><h2>Connection settings</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eqw_save_settings');
        echo '<input type="hidden" name="action" value="eqw_save_settings"><table class="form-table"><tbody>';
        echo '<tr><th><label for="eqw-api-url">Equiviq API endpoint URL</label></th><td><input class="regular-text code" style="width:100%" id="eqw-api-url" name="api_url" type="url" required value="' . esc_attr($settings['api_url'] ?? '') . '" placeholder="https://dev.equiviq.com/api/public/plugins/equiviq-wordpress/tenant/guides"><p class="description">Paste the full endpoint from Equiviq. Do not add a page path or query string.</p></td></tr>';
        echo '<tr><th><label for="eqw-api-key">API key</label></th><td><input class="regular-text code" id="eqw-api-key" name="api_key" type="password" autocomplete="new-password" value="" placeholder="' . (!empty($settings['api_key']) ? 'Key saved — leave blank to keep it' : 'Paste a new key') . '"><p class="description">A new key replaces the saved key. It is encrypted and will not be displayed again.</p></td></tr>';
        echo '</tbody></table><p><button class="button button-primary">Save settings</button></p></form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eqw_test_connection');
        echo '<input type="hidden" name="action" value="eqw_test_connection"><button class="button">Test connection</button></form></div></div>';
    }

    public static function knowledge_page(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You cannot manage these settings.', 'equiviq-wordpress')); }
        $settings = EQW_Connection::settings();
        echo '<div class="wrap"><h1>Equiviq Knowledge</h1><p>Place an Equiviq Guides block or <code>[equiviq_guides]</code> shortcode on a WordPress page.</p>';
        echo '<div class="card" style="max-width:900px;padding:24px"><h2>Primary guide page</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eqw_save_primary_page');
        echo '<input type="hidden" name="action" value="eqw_save_primary_page"><select id="eqw-primary-page" name="primary_page"><option value="0">Choose after placing a Guides block</option>';
        $pages = get_pages(array('post_status' => array('publish', 'private'), 'number' => 0));
        foreach ($pages as $page) {
            if ((!has_block('equiviq-wordpress/guides', $page) && !has_shortcode($page->post_content, 'equiviq_guides')) || ($page->post_status === 'private' && !current_user_can('read_post', $page->ID))) { continue; }
            echo '<option value="' . esc_attr((string) $page->ID) . '" ' . selected((int) ($settings['primary_page'] ?? 0), $page->ID, false) . '>' . esc_html(get_the_title($page)) . ' — ' . esc_html(get_page_uri($page)) . ($page->post_status === 'private' ? ' (Private)' : '') . '</option>';
        }
        echo '</select><p class="description">This page supplies canonical article URLs. Other pages may also contain Guides blocks.</p><p><button class="button button-primary">Save knowledge settings</button></p></form></div>';
        EQW_Guides::admin_presets();
        echo '</div>';
    }

    private static function redirect(string $code): void {
        wp_safe_redirect(add_query_arg('eqw_notice', $code, admin_url('admin.php?page=equiviq-wordpress')));
        exit;
    }

    public static function save(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        check_admin_referer('eqw_save_settings');
        $url = trim((string) wp_unslash($_POST['api_url'] ?? ''));
        $key = trim((string) wp_unslash($_POST['api_key'] ?? ''));
        $page = (int) (EQW_Connection::settings()['primary_page'] ?? 0);
        if (!EQW_Connection::save($url, $key, $page)) { self::redirect('invalid'); }
        EQW_Guides::flush_routes();
        self::redirect('saved');
    }

    public static function save_primary_page(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        check_admin_referer('eqw_save_primary_page');
        $page = absint($_POST['primary_page'] ?? 0);
        if ($page && (!get_post($page) || get_post_status($page) !== 'publish' || !has_block('equiviq-wordpress/guides', $page))) { self::redirect('invalid'); }
        EQW_Connection::save_primary_page($page);
        EQW_Guides::flush_routes();
        wp_safe_redirect(add_query_arg('eqw_notice', 'saved', admin_url('admin.php?page=equiviq-knowledge')));
        exit;
    }

    public static function test(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        check_admin_referer('eqw_test_connection');
        $data = EQW_Connection::request('', '', 'GET', null, false);
        self::redirect(is_array($data) && isset($data['guides']) && is_array($data['guides']) ? 'connected' : 'failed');
    }
}
