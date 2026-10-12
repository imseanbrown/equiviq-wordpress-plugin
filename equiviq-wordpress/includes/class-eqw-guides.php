<?php
if (!defined('ABSPATH')) { exit; }

final class EQW_Guides {
    private static ?array $current = null;
    private static int $current_page = 0;

    private static function knowledge_presets(): array {
        $presets = get_option('eqw_knowledge_entries', array());
        return is_array($presets) ? $presets : array();
    }

    private static function preset_attributes(array $attributes): array {
        $id = sanitize_key($attributes['preset'] ?? '');
        $preset = self::knowledge_presets()[$id] ?? null;
        return is_array($preset) ? $preset : $attributes;
    }

    public static function admin_presets(): void {
        if (!current_user_can('manage_options')) { return; }
        $presets = self::knowledge_presets();
        $edit = sanitize_key(wp_unslash($_GET['edit_knowledge'] ?? ''));
        $entry = $presets[$edit] ?? array();
        echo '<div class="card" style="max-width:900px;padding:24px;margin-top:20px"><h2>Knowledge shortcode entries</h2><p>Create a display entry with its own filters, then place the generated shortcode on a page.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Heading</th><th>Shortcode</th><th>Actions</th></tr></thead><tbody>';
        foreach ($presets as $id => $preset) {
            echo '<tr><td>' . esc_html($preset['heading'] ?? '') . '</td><td><code>[equiviq_guides preset="' . esc_attr($id) . '"]</code></td><td><a class="button" href="' . esc_url(add_query_arg('edit_knowledge', $id, admin_url('admin.php?page=equiviq-wordpress'))) . '">Edit</a> ';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
            wp_nonce_field('eqw_delete_knowledge_' . $id);
            echo '<input type="hidden" name="action" value="eqw_delete_knowledge"><input type="hidden" name="id" value="' . esc_attr($id) . '"><button class="button">Delete</button></form></td></tr>';
        }
        echo '</tbody></table><h3>' . ($edit ? 'Edit' : 'Add') . ' display entry</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eqw_save_knowledge');
        echo '<input type="hidden" name="action" value="eqw_save_knowledge"><input type="hidden" name="id" value="' . esc_attr($edit) . '"><table class="form-table"><tbody>';
        echo '<tr><th><label for="eqw-knowledge-heading">Heading</label></th><td><input id="eqw-knowledge-heading" name="heading" class="regular-text" required value="' . esc_attr($entry['heading'] ?? '') . '"></td></tr>';
        foreach (array('folder', 'category', 'tag', 'company') as $kind) {
            foreach (array('Include', 'Exclude') as $mode) {
                $name = $kind . $mode;
                echo '<tr><th><label for="eqw-' . esc_attr($name) . '">' . esc_html(ucfirst($kind) . ' ' . strtolower($mode)) . '</label></th><td><input id="eqw-' . esc_attr($name) . '" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr($entry['filters'][$name] ?? '') . '"><p class="description">Comma-separated names</p></td></tr>';
            }
        }
        echo '<tr><th>Callout style</th><td><select name="calloutStyle"><option value="equiviq" ' . selected($entry['calloutStyle'] ?? 'equiviq', 'equiviq', false) . '>Equiviq</option><option value="theme" ' . selected($entry['calloutStyle'] ?? '', 'theme', false) . '>Site theme</option></select></td></tr>';
        echo '</tbody></table><button class="button button-primary">Save display entry</button></form></div>';
    }

    public static function save_preset(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        check_admin_referer('eqw_save_knowledge');
        $id = sanitize_key(wp_unslash($_POST['id'] ?? '')) ?: wp_generate_uuid4();
        $heading = sanitize_text_field(wp_unslash($_POST['heading'] ?? ''));
        if (!$heading) { wp_die('Heading is required.', '', 400); }
        $filters = array();
        foreach (array('folder', 'category', 'tag', 'company') as $kind) {
            foreach (array('Include', 'Exclude') as $mode) {
                $name = $kind . $mode;
                $filters[$name] = substr(sanitize_text_field(wp_unslash($_POST[$name] ?? '')), 0, 500);
            }
        }
        $presets = self::knowledge_presets();
        $presets[$id] = array('heading' => substr($heading, 0, 120), 'filters' => $filters,
            'calloutStyle' => ($_POST['calloutStyle'] ?? '') === 'theme' ? 'theme' : 'equiviq');
        update_option('eqw_knowledge_entries', $presets, false);
        wp_safe_redirect(admin_url('admin.php?page=equiviq-wordpress')); exit;
    }

    public static function delete_preset(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        $id = sanitize_key(wp_unslash($_POST['id'] ?? ''));
        check_admin_referer('eqw_delete_knowledge_' . $id);
        $presets = self::knowledge_presets(); unset($presets[$id]);
        update_option('eqw_knowledge_entries', $presets, false);
        wp_safe_redirect(admin_url('admin.php?page=equiviq-wordpress')); exit;
    }

    public static function init(): void {
        register_block_type(EQW_PATH . 'block', array('render_callback' => array(__CLASS__, 'render_block')));
        add_shortcode('equiviq_guides', array(__CLASS__, 'shortcode'));
        self::add_routes();
    }

    public static function activate(): void { self::add_routes(); flush_rewrite_rules(); }
    public static function deactivate(): void { flush_rewrite_rules(); }
    public static function flush_routes(): void { self::add_routes(); flush_rewrite_rules(); }

    private static function placement_pages(): array {
        $pages = get_pages(array('post_status' => array('publish', 'private'), 'number' => 0));
        return array_values(array_filter($pages, static function ($page) {
            return has_block('equiviq-wordpress/guides', $page) || has_shortcode($page->post_content, 'equiviq_guides');
        }));
    }

    private static function add_routes(): void {
        foreach (self::placement_pages() as $page) {
            $path = trim(get_page_uri($page), '/');
            if ($path === '') { continue; }
            add_rewrite_rule('^' . preg_quote($path, '~') . '/([a-z0-9-]+-[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})/?$',
                'index.php?page_id=' . $page->ID . '&eqw_guide=$matches[1]', 'top');
        }
        $settings = EQW_Connection::settings();
        $primary = (int) ($settings['primary_page'] ?? 0);
        if ($primary && self::page_has_placement($primary)) {
            foreach ((array) ($settings['legacy_paths'] ?? array()) as $path) {
                if (!is_string($path) || !preg_match('~^[a-z0-9/-]{1,200}$~D', $path)) { continue; }
                add_rewrite_rule('^' . preg_quote(trim($path, '/'), '~') . '/([a-z0-9-]+-[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})/?$',
                    'index.php?page_id=' . $primary . '&eqw_guide=$matches[1]&eqw_legacy=1', 'top');
            }
        }
    }

    public static function page_saved(int $post_id, $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
        $settings = EQW_Connection::settings();
        if ($post_id === (int) ($settings['primary_page'] ?? 0)) {
            $previous = (string) ($settings['primary_path'] ?? '');
            $current = trim(get_page_uri($post_id), '/');
            if ($previous !== '' && $previous !== $current) {
                $settings['legacy_paths'] = array_values(array_unique(array_merge((array) ($settings['legacy_paths'] ?? array()), array($previous))));
                $settings['primary_path'] = $current;
                update_option('eqw_connection', $settings, false);
            }
        }
        self::flush_routes();
    }

    public static function query_vars(array $vars): array { $vars[] = 'eqw_guide'; $vars[] = 'eqw_legacy'; return $vars; }

    public static function assets(): void {
        wp_enqueue_style('equiviq-wordpress-guides', EQW_URL . 'assets/guides.css', array(), EQW_VERSION);
        wp_enqueue_script('equiviq-wordpress-view', EQW_URL . 'assets/view.js', array(), EQW_VERSION, true);
        $view_config = 'window.eqwViewEndpoint=' . wp_json_encode(rest_url('equiviq-wordpress/v1/view')) . ';';
        if (is_user_logged_in()) { $view_config .= 'window.eqwViewNonce=' . wp_json_encode(wp_create_nonce('wp_rest')) . ';'; }
        wp_add_inline_script('equiviq-wordpress-view', $view_config, 'before');
    }

    public static function editor_assets(): void {
        wp_enqueue_script('equiviq-wordpress-editor', EQW_URL . 'block/editor.js', array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch'), EQW_VERSION, true);
    }

    public static function rest_routes(): void {
        register_rest_route('equiviq-wordpress/v1', '/facets', array('methods' => 'GET', 'callback' => array(__CLASS__, 'rest_facets'), 'permission_callback' => static function () { return current_user_can('edit_pages'); }));
        register_rest_route('equiviq-wordpress/v1', '/image/(?P<article>[^/]+)/(?P<image>[^/]+)', array('methods' => 'GET', 'callback' => array(__CLASS__, 'rest_image'), 'permission_callback' => '__return_true'));
        register_rest_route('equiviq-wordpress/v1', '/view', array('methods' => 'POST', 'callback' => array(__CLASS__, 'rest_view'), 'permission_callback' => '__return_true'));
    }

    public static function rest_facets() {
        $url = EQW_Connection::api_url();
        if ($url === '') { return new WP_Error('eqw_unconfigured', 'Configure Equiviq first.', array('status' => 503)); }
        $facets_url = preg_replace('~/guides/?$~', '/guide-facets', $url);
        $key = EQW_Connection::api_key();
        $response = wp_remote_get($facets_url, array('timeout' => 7, 'redirection' => 0, 'sslverify' => true, 'headers' => array('Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json')));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return new WP_Error('eqw_unavailable', 'Could not load guide filters.', array('status' => 503)); }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : new WP_Error('eqw_invalid', 'Invalid guide filters.', array('status' => 502));
    }

    private static function uuid(string $value): bool {
        return (bool) preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/iD', $value);
    }

    public static function rest_image($request) {
        $article = (string) $request['article']; $image = (string) $request['image'];
        if (!self::uuid($article) || !self::uuid($image)) { return new WP_Error('eqw_not_found', 'Not found.', array('status' => 404)); }
        $result = EQW_Connection::image($article, $image);
        if (!$result) { return new WP_Error('eqw_not_found', 'Not found.', array('status' => 404)); }
        $response = new WP_REST_Response($result[1], 200);
        $response->header('Content-Type', $result[0]);
        $response->header('Cache-Control', 'private, max-age=30');
        return $response;
    }

    public static function serve_image($served, $result, $request, $server) {
        if ($served || !preg_match('~^/equiviq-wordpress/v1/image/[0-9a-f-]{36}/[0-9a-f-]{36}$~iD', $request->get_route()) || $result->get_status() !== 200) { return $served; }
        header('Content-Type: ' . $result->get_headers()['Content-Type']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=30');
        echo $result->get_data();
        return true;
    }

    public static function rest_view($request) {
        $slug = (string) $request->get_param('slug');
        $page = absint($request->get_param('page'));
        $event = (string) $request->get_param('event_id');
        if (!self::valid_slug($slug) || !self::uuid($event) || !$page || !self::page_has_placement($page)) { return new WP_Error('eqw_not_found', 'Not found.', array('status' => 404)); }
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        $dedupe = 'eqwv_' . substr(hash('sha256', get_current_blog_id() . '|' . $event), 0, 38);
        if (get_transient($dedupe)) { return new WP_REST_Response(null, 204); }
        $rate_key = 'eqwr_' . substr(hash('sha256', get_current_blog_id() . '|' . $ip . '|' . gmdate('YmdHi')), 0, 38);
        $rate = (int) get_transient($rate_key);
        if ($rate >= 30) { return new WP_Error('eqw_rate_limited', 'Too many view events.', array('status' => 429)); }
        $guide = EQW_Connection::request($slug, '', 'GET', null, false);
        if (!self::valid_guide($guide) || !self::matches_any_placement($guide, $page)) { return new WP_Error('eqw_not_found', 'Not found.', array('status' => 404)); }
        $result = EQW_Connection::request($slug . '/views', '', 'POST', array('event_id' => $event, 'referrer' => self::article_url($page, $slug)), false);
        if ($result === null) { return new WP_Error('eqw_unavailable', 'Could not record view.', array('status' => 503)); }
        set_transient($dedupe, 1, HOUR_IN_SECONDS);
        set_transient($rate_key, $rate + 1, MINUTE_IN_SECONDS);
        return new WP_REST_Response(null, 204);
    }

    private static function valid_slug(string $slug): bool {
        return (bool) preg_match('/^[a-z0-9-]{2,164}-[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/D', $slug);
    }

    private static function valid_guide($guide): bool {
        return is_array($guide) && ($guide['public_access'] ?? false) === true && !empty($guide['visibility_label']) && isset($guide['sections']) && is_array($guide['sections']);
    }

    private static function page_has_placement(int $page): bool {
        $post = get_post($page);
        if (!$post || $post->post_type !== 'page' || !(has_block('equiviq-wordpress/guides', $post) || has_shortcode($post->post_content, 'equiviq_guides'))) { return false; }
        return $post->post_status === 'publish' || ($post->post_status === 'private' && current_user_can('read_post', $page));
    }

    private static function blocks(int $page): array {
        $post = get_post($page);
        if (!$post) { return array(); }
        $found = array();
        $walk = static function (array $blocks) use (&$walk, &$found): void {
            foreach ($blocks as $block) {
                if (($block['blockName'] ?? '') === 'equiviq-wordpress/guides') { $found[] = $block['attrs'] ?? array(); }
                if (!empty($block['innerBlocks'])) { $walk($block['innerBlocks']); }
            }
        };
        $walk(parse_blocks($post->post_content));
        if (preg_match_all('/\[equiviq_guides\b([^\]]*)\]/', $post->post_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = shortcode_parse_atts($match[1]);
                $found[] = self::preset_attributes(is_array($attributes) ? $attributes : array());
            }
        }
        return $found;
    }

    private static function filter_values($value): array {
        if (is_array($value)) { $items = $value; }
        else { $items = array_map('trim', explode(',', (string) $value)); }
        return array_values(array_filter(array_map(static fn($item) => strtolower(trim((string) $item)), $items)));
    }

    private static function allowed(array $items, $include, $exclude): bool {
        $normalized = array_map(static fn($item) => strtolower(trim((string) $item)), $items);
        $included = self::filter_values($include);
        $excluded = self::filter_values($exclude);
        if (array_intersect($normalized, $excluded)) { return false; }
        return !$included || (bool) array_intersect($normalized, $included);
    }

    private static function matches(array $guide, array $attributes): bool {
        $filters = $attributes['filters'] ?? array();
        if (!is_array($filters)) { return true; }
        foreach (array('folder' => $guide['folders'] ?? array(), 'category' => array($guide['category'] ?? ''), 'tag' => $guide['tags'] ?? array(), 'company' => array($guide['company'] ?? '')) as $name => $items) {
            if (!self::allowed((array) $items, $filters[$name . 'Include'] ?? array(), $filters[$name . 'Exclude'] ?? array())) { return false; }
        }
        return true;
    }

    private static function matches_any_placement(array $guide, int $page): bool {
        foreach (self::blocks($page) as $attributes) { if (self::matches($guide, $attributes)) { return true; } }
        return false;
    }

    public static function render_block(array $attributes): string {
        if (get_query_var('eqw_guide')) { return self::render_current(); }
        return self::render_index($attributes);
    }

    public static function shortcode($attributes): string {
        if (get_query_var('eqw_guide')) { return self::render_current(); }
        return self::render_index(self::preset_attributes(is_array($attributes) ? $attributes : array()));
    }

    private static function list_guides(string $query): ?array {
        $response = EQW_Connection::request('', $query);
        return is_array($response) && isset($response['guides']) && is_array($response['guides']) ? $response['guides'] : null;
    }

    private static function render_index(array $attributes): string {
        $query = isset($_GET['eqw_q']) ? substr(trim(sanitize_text_field(wp_unslash($_GET['eqw_q']))), 0, 120) : '';
        $guides = self::list_guides($query);
        $base = get_permalink();
        $heading = trim((string) ($attributes['heading'] ?? 'Guides & Resources'));
        $html = '<div class="equiviq-guide-shell"><section class="equiviq-guide-card equiviq-guide-index"><p class="equiviq-guide-eyebrow">Equiviq Knowledge</p><h1>' . esc_html($heading) . '</h1><p class="equiviq-guide-intro">Search guides from Equiviq.</p>';
        $html .= '<form action="' . esc_url($base) . '" method="get" role="search" class="equiviq-guide-search"><label for="eqw-guide-query">Search guides</label><div>';
        if (!get_option('permalink_structure')) { $html .= '<input type="hidden" name="page_id" value="' . esc_attr((string) get_the_ID()) . '">'; }
        $html .= '<input id="eqw-guide-query" name="eqw_q" type="search" value="' . esc_attr($query) . '" maxlength="120" placeholder="Search titles and article content"><button type="submit">Search</button></div></form>';
        if ($guides === null) { return $html . '<p class="equiviq-guide-empty">Guides are temporarily unavailable.</p></section></div>'; }
        if ($query !== '') { $html .= '<div class="equiviq-guide-result-heading"><h2>Results for “' . esc_html($query) . '”</h2><a href="' . esc_url($base) . '">Clear search</a></div>'; }
        $html .= '<ul class="equiviq-guide-list">'; $count = 0;
        foreach ($guides as $guide) {
            if (!is_array($guide) || ($guide['public_access'] ?? false) !== true || !self::matches($guide, $attributes) || !self::valid_slug((string) ($guide['slug'] ?? ''))) { continue; }
            $count++;
            $link = self::article_url((int) get_the_ID(), (string) $guide['slug']);
            $html .= '<li><a href="' . esc_url($link) . '"><strong>' . esc_html($guide['title'] ?? '') . '</strong><span>' . esc_html($guide['document_number'] ?? '') . ' · Updated ' . esc_html(self::date($guide['updated_at'] ?? '')) . '</span></a></li>';
        }
        $html .= '</ul>';
        if (!$count) { $html .= '<p class="equiviq-guide-empty">' . ($query ? 'Try another term or browse all guides.' : 'No guides are available yet.') . '</p>'; }
        return $html . '</section></div>';
    }

    private static function date(string $value): string { $stamp = $value !== '' ? strtotime($value) : false; return $stamp !== false ? wp_date('M j, Y', $stamp) : ''; }

    private static function article(): ?array {
        if (self::$current !== null) { return self::$current; }
        $slug = (string) get_query_var('eqw_guide');
        if (!self::valid_slug($slug)) { return null; }
        $page = get_queried_object_id();
        if (!self::page_has_placement($page)) { return null; }
        $guide = EQW_Connection::request($slug, '', 'GET', null, false);
        if (!self::valid_guide($guide) || !self::matches_any_placement($guide, $page)) { return null; }
        self::$current = $guide; self::$current_page = $page;
        return $guide;
    }

    public static function template_redirect(): void {
        if (!get_query_var('eqw_guide')) { return; }
        if (!self::article()) { global $wp_query; $wp_query->set_404(); status_header(404); nocache_headers(); return; }
        if (get_query_var('eqw_legacy') || !isset($_GET['eqw_guide'])) { wp_safe_redirect(self::canonical(self::$current), 301); exit; }
        status_header(200);
    }

    public static function redirect_canonical($redirect, $requested) { return get_query_var('eqw_guide') ? false : $redirect; }

    public static function title_parts(array $parts): array {
        $article = self::article();
        if ($article) { $parts['title'] = (string) $article['title']; }
        return $parts;
    }

    private static function article_url(int $page, string $slug): string {
        return add_query_arg('eqw_guide', $slug, get_permalink($page));
    }

    private static function canonical(array $article): string {
        $settings = EQW_Connection::settings();
        $primary = (int) ($settings['primary_page'] ?? 0);
        $page = $primary && self::page_has_placement($primary) && self::matches_any_placement($article, $primary) ? $primary : self::$current_page;
        return self::article_url($page, (string) $article['slug']);
    }

    public static function head(): void {
        $article = self::article();
        if (!$article) { return; }
        $canonical = self::canonical($article);
        echo '<meta name="description" content="' . esc_attr($article['description'] ?? '') . '"><link rel="canonical" href="' . esc_url($canonical) . '">';
        echo '<meta property="og:type" content="article"><meta property="og:title" content="' . esc_attr($article['title']) . '"><meta property="og:description" content="' . esc_attr($article['description'] ?? '') . '">';
        $structured = self::structured_data($article, $canonical);
        echo '<script type="application/ld+json">' . str_replace('<', '\\u003c', wp_json_encode($structured)) . '</script>';
    }

    private static function structured_data(array $article, string $canonical): array {
        $faq = array();
        foreach ($article['sections'] as $section) {
            if (strcasecmp(trim((string) ($section['title'] ?? '')), 'Frequently Asked Questions') !== 0) { continue; }
            $parts = preg_split('/^#{2,3}\s+(.+\?)\s*$/m', (string) ($section['markdown'] ?? ''), -1, PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 1; $i + 1 < count($parts); $i += 2) {
                if (trim($parts[$i]) !== '' && trim($parts[$i + 1]) !== '') { $faq[] = array('@type' => 'Question', 'name' => trim($parts[$i]), 'acceptedAnswer' => array('@type' => 'Answer', 'text' => trim(strip_tags($parts[$i + 1])))); }
            }
        }
        if ($faq) { return array('@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq); }
        return array('@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $article['title'], 'description' => $article['description'] ?? '', 'datePublished' => $article['published_at'] ?? '', 'dateModified' => $article['updated_at'] ?? '', 'mainEntityOfPage' => $canonical);
    }

    public static function the_content(string $content): string {
        if (!get_query_var('eqw_guide') || !in_the_loop() || !is_main_query()) { return $content; }
        return self::render_current();
    }

    private static function render_current(): string {
        $article = self::article();
        if (!$article) { return ''; }
        foreach (self::blocks(self::$current_page) as $attributes) {
            if (self::matches($article, $attributes)) { return self::render_article($article, $attributes); }
        }
        return '';
    }

    private static function visible(array $group, string $key): bool { return ($group['show'] ?? true) !== false && ($group[$key] ?? true) !== false; }

    private static function detail(string $label, $value): string {
        if (is_array($value)) { $value = implode(', ', $value); }
        return $value === '' || $value === null ? '' : '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html((string) $value) . '</dd></div>';
    }

    private static function render_article(array $article, array $attributes): string {
        $classification = is_array($attributes['classification'] ?? null) ? $attributes['classification'] : array();
        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : array();
        $base = get_permalink(self::$current_page);
        $canonical = self::canonical($article);
        $callout_style = ($attributes['calloutStyle'] ?? 'equiviq') === 'theme' ? 'theme' : 'equiviq';
        $colors = $article['callout_colors'] ?? array();
        $style = '';
        foreach (array('note', 'tip', 'important', 'warning') as $name) {
            if (preg_match('/^#[a-f0-9]{6}$/iD', (string) ($colors[$name] ?? ''))) { $style .= '--equiviq-callout-' . $name . ':' . $colors[$name] . ';'; }
        }
        $html = '<div class="equiviq-guide-shell"><article class="equiviq-guide-card equiviq-guide-article" style="' . esc_attr($style) . '"><header class="equiviq-guide-heading"><a class="equiviq-guide-back" href="' . esc_url($base) . '">← All guides</a><p class="equiviq-guide-eyebrow">Guides &amp; Resources</p><h1>' . esc_html($article['title']) . '</h1>';
        if (self::visible($classification, 'visibility')) { $html .= '<span class="equiviq-guide-status">' . esc_html($article['visibility_label']) . '</span>'; }
        $html .= '</header>';
        if (($classification['show'] ?? true) !== false) {
            $html .= '<section class="equiviq-guide-classification" aria-label="Article details"><dl>';
            foreach (array('folders' => 'Folders', 'category' => 'Category', 'tags' => 'Tags') as $key => $label) { if (self::visible($classification, $key)) { $html .= self::detail($label, $article[$key] ?? ''); } }
            $html .= '</dl><dl>';
            foreach (array('articleId' => array('Article ID', 'document_number'), 'version' => array('Version', 'version'), 'updated' => array('Updated', 'updated_at'), 'visibility' => array('Visibility', 'visibility_label')) as $key => $pair) {
                if (self::visible($classification, $key)) { $value = $article[$pair[1]] ?? ''; if ($key === 'version') { $value = 'v' . $value; } if ($key === 'updated') { $value = self::date((string) $value); } $html .= self::detail($pair[0], $value); }
            }
            $html .= '</dl></section>';
        }
        $html .= '<div class="equiviq-guide-content">';
        foreach ($article['sections'] as $section) {
            if (!is_array($section)) { continue; }
            $html .= '<section class="equiviq-guide-section"><h2>' . esc_html($section['title'] ?? '') . '</h2>' . self::markdown((string) ($section['markdown'] ?? ''), (string) $article['id'], $callout_style) . '</section>';
        }
        $html .= '</div></article>';
        if (($metadata['show'] ?? true) !== false) {
            $html .= '<section class="equiviq-guide-card equiviq-guide-metadata"><h2>Article metadata</h2><dl>';
            foreach (array('author' => array('Author', 'author'), 'updated' => array('Updated', 'updated_at'), 'published' => array('Published', 'published_at'), 'created' => array('Created', 'created_at'), 'approved' => array('Approved', 'approved_at')) as $key => $pair) {
                if (self::visible($metadata, $key)) {
                    $value = $article[$pair[1]] ?? '';
                    if ($key !== 'author') {
                        $actor = $article[$key . '_by'] ?? '';
                        if ($key === 'created') { $actor = $article['created_by'] ?? ''; }
                        $value = self::date((string) $value) . ($actor ? ' · ' . $actor : '');
                    }
                    $html .= self::detail($pair[0], $value);
                }
            }
            if (self::visible($metadata, 'permalink')) { $html .= '<div><dt>Permalink</dt><dd><a href="' . esc_url($canonical) . '">' . esc_html($canonical) . '</a></dd></div>'; }
            $html .= '</dl></section>';
        }
        $html .= '<span class="eqw-view-marker" data-slug="' . esc_attr($article['slug']) . '" data-page="' . esc_attr((string) self::$current_page) . '" hidden></span></div>';
        return $html;
    }

    private static function markdown(string $markdown, string $article, string $style): string {
        $markdown = preg_replace_callback('~(/knowledge/article-images/guides-resources/)([0-9a-f-]{36})~i', static function ($match) use ($article) {
            return rest_url('equiviq-wordpress/v1/image/' . $article . '/' . $match[2]);
        }, $markdown);
        $parser = new Parsedown(); $parser->setSafeMode(true);
        $lines = preg_split('/\r?\n/', $markdown);
        $output = ''; $normal = array();
        $flush = static function () use (&$normal, &$output, $parser): void {
            if ($normal) { $output .= wp_kses_post($parser->text(implode("\n", $normal))); $normal = array(); }
        };
        for ($i = 0; $i < count($lines); $i++) {
            if (!preg_match('/^\s*>\s*(?:\[!(NOTE|INFO|TIP|IMPORTANT|WARNING|CAUTION)\]|(?:\*\*|__)(NOTE|INFO|TIP|IMPORTANT|WARNING|CAUTION)(?:\*\*|__))\s*$/i', $lines[$i], $marker)) { $normal[] = $lines[$i]; continue; }
            $flush(); $kind = strtoupper($marker[1] ?: $marker[2]); $body = array();
            while (++$i < count($lines) && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $line)) { $body[] = $line[1]; }
            $i--;
            $icon = $kind === 'TIP' ? 'lightbulb' : ($kind === 'IMPORTANT' || $kind === 'CAUTION' ? 'hand-right' : ($kind === 'WARNING' ? 'warning' : 'pin'));
            $output .= '<blockquote class="equiviq-guide-callout equiviq-guide-callout-' . esc_attr(strtolower($kind)) . ($style === 'theme' ? ' equiviq-guide-callout-theme' : '') . '">';
            if ($style !== 'theme') { $output .= '<span class="equiviq-guide-callout-icon" aria-hidden="true"><span class="equiviq-guide-callout-icon-' . esc_attr($icon) . '"></span></span>'; }
            $output .= '<div class="equiviq-guide-callout-content"><p class="equiviq-guide-callout-label">' . esc_html($kind) . '</p>' . wp_kses_post($parser->text(implode("\n", $body))) . '</div></blockquote>';
        }
        $flush();
        return $output;
    }
}

