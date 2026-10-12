<?php
if (!defined('ABSPATH')) { exit; }

/** Lead forms stay on the WordPress site. Only the server sends the existing key to Equiviq. */
final class EQW_Leads {
    private const OPTION = 'eqw_lead_ctas';
    private const FIELDS = array('given_name', 'surname', 'email', 'organization', 'phone');
    private const LABELS = array('given_name' => 'Given name', 'surname' => 'Surname', 'email' => 'Email', 'organization' => 'Organization', 'phone' => 'Phone', 'phone_country' => 'Country code');

    public static function init(): void {
        add_shortcode('equiviq_lead', array(__CLASS__, 'shortcode'));
        if (get_option('eqw_lead_schema_version') !== '1') { self::ensure_schema(); }
    }

    public static function assets(): void {
        wp_enqueue_style('eqw-lead-form', EQW_URL . 'assets/leads.css', array(), EQW_VERSION);
    }

    public static function activate(): void {
        self::ensure_schema();
        if (!wp_next_scheduled('eqw_retry_leads')) { wp_schedule_event(time() + 120, 'hourly', 'eqw_retry_leads'); }
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'eqw_lead_queue';
    }

    private static function ensure_schema(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            submission_id varchar(36) NOT NULL,
            body longtext NOT NULL,
            attempts int NOT NULL DEFAULT 0,
            last_error varchar(200) NOT NULL DEFAULT '',
            needs_review tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (submission_id)
        ) $charset;");
        update_option('eqw_lead_schema_version', '1', false);
    }

    public static function menu(): void {
        add_submenu_page('equiviq-wordpress', 'Knowledge', 'Knowledge', 'manage_options', 'equiviq-wordpress', array('EQW_Admin', 'page'));
        add_submenu_page('equiviq-wordpress', 'Lead Generation', 'Lead Generation', 'manage_options', 'equiviq-leads', array(__CLASS__, 'admin_page'));
    }

    private static function ctas(): array {
        $value = get_option(self::OPTION, array());
        return is_array($value) ? $value : array();
    }

    private static function lead_url(string $suffix): string {
        $guides = EQW_Connection::api_url();
        return $guides && preg_match('~/guides$~', $guides) ? substr($guides, 0, -6) . $suffix : '';
    }

    private static function send(string $suffix, array $body): array {
        $url = self::lead_url($suffix);
        $key = EQW_Connection::api_key();
        if (!$url || !$key) { return array('status' => 503, 'message' => 'The Equiviq connection is not configured.'); }
        $result = wp_remote_post($url, array('timeout' => 12, 'redirection' => 0, 'sslverify' => true,
            'headers' => array('Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json', 'Accept' => 'application/json'),
            'body' => wp_json_encode($body)));
        if (is_wp_error($result)) { return array('status' => 503, 'message' => 'Equiviq is temporarily unavailable.'); }
        $status = (int) wp_remote_retrieve_response_code($result);
        $decoded = json_decode(wp_remote_retrieve_body($result), true);
        return array('status' => $status, 'message' => is_array($decoded) && is_string($decoded['message'] ?? null) ? $decoded['message'] : '',
            'data' => is_array($decoded) ? $decoded : array());
    }

    public static function admin_page(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        $ctas = self::ctas();
        $edit = sanitize_key(wp_unslash($_GET['edit'] ?? ''));
        $cta = $ctas[$edit] ?? array();
        echo '<div class="wrap"><h1>Equiviq Lead Generation</h1><p>Configure a call to action, then place its shortcode on a page. WordPress hosts the form and forwards submissions to Equiviq using the saved Knowledge API key.</p>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Call to action saved.</p></div>'; }
        echo '<h2>Calls to action</h2><table class="widefat striped"><thead><tr><th>Name</th><th>Shortcode</th><th>Actions</th></tr></thead><tbody>';
        foreach ($ctas as $id => $entry) {
            echo '<tr><td>' . esc_html($entry['title']) . '</td><td><code>[equiviq_lead id="' . esc_attr($id) . '"]</code></td><td><a class="button" href="' . esc_url(add_query_arg('edit', $id, admin_url('admin.php?page=equiviq-leads'))) . '">Edit</a> ';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
            wp_nonce_field('eqw_delete_cta_' . $id);
            echo '<input type="hidden" name="action" value="eqw_delete_cta"><input type="hidden" name="id" value="' . esc_attr($id) . '"><button class="button" type="submit">Delete</button></form></td></tr>';
        }
        echo '</tbody></table><h2>' . ($edit ? 'Edit' : 'Add') . ' call to action</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eqw_save_cta');
        echo '<input type="hidden" name="action" value="eqw_save_cta"><input type="hidden" name="id" value="' . esc_attr($edit) . '"><table class="form-table"><tbody>';
        foreach (array('title' => 'Name', 'acquisition_source' => 'Acquisition Source/Campaign', 'privacy_url' => 'Privacy policy URL override', 'success_message' => 'Success message', 'error_message' => 'Error message', 'queued_message' => 'Queued message') as $key => $label) {
            echo '<tr><th><label for="eqw-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="eqw-' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($cta[$key] ?? '') . '" ' . ($key === 'title' ? 'required' : '') . '></td></tr>';
        }
        foreach (self::FIELDS as $field) {
            $visible = $field === 'email' || !empty($cta['fields'][$field]['visible']);
            $required = $field === 'email' || $field === 'organization' || !empty($cta['fields'][$field]['required']);
            echo '<tr><th>' . esc_html(self::LABELS[$field]) . '</th><td><label><input type="checkbox" name="fields[' . esc_attr($field) . '][visible]" value="1" ' . checked($visible, true, false) . ($field === 'email' ? ' disabled' : '') . '> Show</label> &nbsp; <label><input type="checkbox" name="fields[' . esc_attr($field) . '][required]" value="1" ' . checked($required, true, false) . ($field === 'email' || $field === 'organization' ? ' disabled' : '') . '> Required</label> &nbsp; <label>Label override <input type="text" name="fields[' . esc_attr($field) . '][label]" value="' . esc_attr($cta['fields'][$field]['label'] ?? '') . '" placeholder="' . esc_attr(self::LABELS[$field]) . '" maxlength="100"></label></td></tr>';
        }
        echo '<tr><th>Country code</th><td><label>Label override <input type="text" name="fields[phone_country][label]" value="' . esc_attr($cta['fields']['phone_country']['label'] ?? '') . '" placeholder="Country code" maxlength="100"></label></td></tr>';
        echo '</tbody></table><p>Email is always required. When Organization is shown, it is required. The default privacy policy is the WordPress site policy.</p><p><button class="button button-primary">Save call to action</button></p></form>';
        global $wpdb;
        $queue_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table());
        echo '<h2>Undelivered submissions</h2><p>' . esc_html((string) $queue_count) . ' pending. WordPress retries after submission and hourly. Each retry uses the original submission ID.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="eqw_retry_leads">';
        wp_nonce_field('eqw_retry_leads');
        echo '<button class="button">Retry now</button></form><table class="widefat striped"><thead><tr><th>Submission ID</th><th>Attempts</th><th>Last error</th><th>Status</th></tr></thead><tbody>';
        $pending = $wpdb->get_results('SELECT submission_id, attempts, last_error, needs_review FROM ' . self::table() . ' ORDER BY created_at DESC LIMIT 100', ARRAY_A);
        foreach ((array) $pending as $item) {
            echo '<tr><td><code>' . esc_html($item['submission_id']) . '</code></td><td>' . esc_html((string) $item['attempts']) . '</td><td>' . esc_html($item['last_error']) . '</td><td>' . ($item['needs_review'] ? 'Needs review' : 'Queued') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function save_cta(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        check_admin_referer('eqw_save_cta');
        $id = sanitize_key(wp_unslash($_POST['id'] ?? ''));
        if (!$id) { $id = wp_generate_uuid4(); }
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        if (!$title) { wp_die('A name is required.', '', 400); }
        $privacy = esc_url_raw(wp_unslash($_POST['privacy_url'] ?? ''));
        if ($privacy && !wp_http_validate_url($privacy)) { wp_die('Enter a valid privacy policy URL.', '', 400); }
        $fields = array();
        $posted = is_array($_POST['fields'] ?? null) ? wp_unslash($_POST['fields']) : array();
        foreach (self::FIELDS as $field) {
            $visible = $field === 'email' || !empty($posted[$field]['visible']);
            $fields[$field] = array('visible' => $visible, 'required' => $field === 'email' || ($field === 'organization' && $visible) || ($visible && !empty($posted[$field]['required'])),
                'label' => substr(sanitize_text_field($posted[$field]['label'] ?? ''), 0, 100));
        }
        $fields['phone_country'] = array('label' => substr(sanitize_text_field($posted['phone_country']['label'] ?? ''), 0, 100));
        $ctas = self::ctas();
        $ctas[$id] = array('title' => $title, 'fields' => $fields, 'privacy_url' => $privacy,
            'acquisition_source' => substr(sanitize_text_field(wp_unslash($_POST['acquisition_source'] ?? '')), 0, 200),
            'success_message' => sanitize_text_field(wp_unslash($_POST['success_message'] ?? '')) ?: 'Thank you. Your information was received.',
            'error_message' => sanitize_text_field(wp_unslash($_POST['error_message'] ?? '')) ?: 'Please check your information and try again.',
            'queued_message' => sanitize_text_field(wp_unslash($_POST['queued_message'] ?? '')) ?: 'Your information is queued for delivery.');
        update_option(self::OPTION, $ctas, false);
        wp_safe_redirect(admin_url('admin.php?page=equiviq-leads&saved=1')); exit;
    }

    public static function delete_cta(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        $id = sanitize_key(wp_unslash($_POST['id'] ?? ''));
        check_admin_referer('eqw_delete_cta_' . $id);
        $ctas = self::ctas(); unset($ctas[$id]); update_option(self::OPTION, $ctas, false);
        wp_safe_redirect(admin_url('admin.php?page=equiviq-leads')); exit;
    }

    public static function shortcode($attributes = array()): string {
        $attributes = is_array($attributes) ? $attributes : array();
        $id = sanitize_key($attributes['id'] ?? '');
        $cta = self::ctas()[$id] ?? null;
        if (!$cta) { return ''; }
        wp_enqueue_script('eqw-leads', EQW_URL . 'assets/leads.js', array(), EQW_VERSION, true);
        $privacy = $cta['privacy_url'] ?: get_privacy_policy_url();
        if (!$privacy) { return '<p>Lead form unavailable: configure a privacy policy URL.</p>'; }
        $html = '<form class="eqw-lead-form" method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '"><h2>' . esc_html($cta['title']) . '</h2>';
        $html .= '<input type="hidden" name="action" value="eqw_submit_lead"><input type="hidden" name="cta_id" value="' . esc_attr($id) . '"><input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('eqw_lead_' . $id)) . '"><input type="hidden" name="started" value="' . esc_attr((string) time()) . '"><input type="hidden" name="source_url" class="eqw-source-url" value=""><div class="eqw-honeypot" aria-hidden="true" style="position:absolute;left:-9999px"><label>Leave this blank<input name="website" tabindex="-1" autocomplete="off"></label></div>';
        $name_fields = '';
        $other_fields = '';
        foreach (self::FIELDS as $field) {
            if (empty($cta['fields'][$field]['visible'])) { continue; }
            $label = trim((string) ($cta['fields'][$field]['label'] ?? '')) ?: self::LABELS[$field];
            $required = !empty($cta['fields'][$field]['required']) ? ' required' : '';
            if ($field === 'phone') {
                $country_label = trim((string) ($cta['fields']['phone_country']['label'] ?? '')) ?: self::LABELS['phone_country'];
                $other_fields .= '<div class="eqw-lead-field eqw-lead-phone"><select name="phone_country" aria-label="' . esc_attr($country_label) . '" title="' . esc_attr($country_label) . '"><option value="US">US +1</option></select><input type="hidden" name="phone_e164" value="">';
                $other_fields .= '<input name="phone" type="tel" placeholder="' . esc_attr($label) . '" aria-label="' . esc_attr($label) . '" autocomplete="tel"' . $required . ' maxlength="120"></div>';
                continue;
            }
            $input = '<div class="eqw-lead-field"><input name="' . esc_attr($field) . '" type="' . ($field === 'email' ? 'email' : 'text') . '" placeholder="' . esc_attr($label) . '" aria-label="' . esc_attr($label) . '" autocomplete="' . esc_attr(array('given_name' => 'given-name', 'surname' => 'family-name', 'email' => 'email', 'organization' => 'organization')[$field]) . '"' . $required . ' maxlength="' . ($field === 'organization' ? '240' : ($field === 'email' ? '254' : '120')) . '"></div>';
            if ($field === 'given_name' || $field === 'surname') { $name_fields .= $input; } else { $other_fields .= $input; }
        }
        if ($name_fields) { $html .= '<div class="eqw-lead-name-row">' . $name_fields . '</div>'; }
        $html .= $other_fields;
        if ($privacy) { $html .= '<p>By submitting, you opt in to contact. <a href="' . esc_url($privacy) . '" target="_blank" rel="noopener noreferrer">Privacy policy and opt-out</a></p>'; }
        $html .= '<button type="submit">Submit</button><p class="eqw-lead-message" role="status" aria-live="polite"></p></form>';
        return $html;
    }

    private static function queue(array $body): bool {
        global $wpdb;
        $inserted = $wpdb->insert(self::table(), array('submission_id' => $body['submission_id'], 'body' => wp_json_encode($body),
            'attempts' => 0, 'last_error' => '', 'needs_review' => 0, 'created_at' => current_time('mysql', true)),
            array('%s', '%s', '%d', '%s', '%d', '%s'));
        if (!$inserted) { return false; }
        if (!wp_next_scheduled('eqw_retry_leads')) { wp_schedule_event(time() + 120, 'hourly', 'eqw_retry_leads'); }
        if (!wp_next_scheduled('eqw_retry_leads_once')) { wp_schedule_single_event(time() + 120, 'eqw_retry_leads_once'); }
        return true;
    }

    public static function retry_now(): void {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', 403); }
        check_admin_referer('eqw_retry_leads');
        self::retry();
        wp_safe_redirect(admin_url('admin.php?page=equiviq-leads')); exit;
    }

    public static function retry(): void {
        global $wpdb;
        $table = self::table();
        $items = $wpdb->get_results("SELECT submission_id, body, attempts FROM $table WHERE needs_review = 0 ORDER BY created_at ASC LIMIT 100", ARRAY_A);
        foreach ((array) $items as $item) {
            $body = json_decode($item['body'], true);
            if (!is_array($body)) { $wpdb->update($table, array('needs_review' => 1, 'last_error' => 'Invalid queued submission'), array('submission_id' => $item['submission_id'])); continue; }
            $result = self::send('leads', $body);
            if ($result['status'] === 200) { $wpdb->delete($table, array('submission_id' => $item['submission_id']), array('%s')); continue; }
            $wpdb->update($table, array('attempts' => (int) $item['attempts'] + 1, 'last_error' => substr($result['message'], 0, 200),
                'needs_review' => !in_array($result['status'], array(503, 429), true) ? 1 : 0), array('submission_id' => $item['submission_id']));
        }
    }

    public static function nonce(): void {
        $id = sanitize_key(wp_unslash($_POST['cta_id'] ?? ''));
        if (!isset(self::ctas()[$id])) { wp_send_json_error(array('message' => 'This form is unavailable.'), 404); }
        wp_send_json_success(array('nonce' => wp_create_nonce('eqw_lead_' . $id)));
    }

    public static function submit(): void {
        $id = sanitize_key(wp_unslash($_POST['cta_id'] ?? ''));
        $cta = self::ctas()[$id] ?? null;
        if (!$cta || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'eqw_lead_' . $id)) { wp_send_json_error(array('message' => 'This form is unavailable.'), 403); }
        $elapsed = time() - (int) ($_POST['started'] ?? 0);
        if (!empty($_POST['website']) || $elapsed < 2 || $elapsed > DAY_IN_SECONDS) { wp_send_json_error(array('message' => $cta['error_message']), 422); }
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (!is_email($email)) { wp_send_json_error(array('message' => 'Enter a valid work email address.'), 422); }
        $checked = self::send('lead-email-check', array('email' => $email));
        if ($checked['status'] === 200 && empty($checked['data']['allowed'])) { wp_send_json_error(array('message' => 'Please use your work email address'), 422); }
        if ($checked['status'] !== 200) { wp_send_json_error(array('message' => $checked['message'] ?: $cta['error_message']), 503); }
        $body = array('submission_id' => wp_generate_uuid4(), 'cta_id' => $id, 'email' => $email,
            'acquisition_source' => $cta['acquisition_source']);
        foreach (array('given_name', 'surname') as $field) {
            if (empty($cta['fields'][$field]['visible'])) { continue; }
            $value = sanitize_text_field(wp_unslash($_POST[$field] ?? ''));
            if (!empty($cta['fields'][$field]['required']) && !$value) { wp_send_json_error(array('message' => $field . ' is required.'), 422); }
            if ($value) { $body[$field] = $value; }
        }
        if (!empty($cta['fields']['organization']['visible'])) {
            $name = sanitize_text_field(wp_unslash($_POST['organization'] ?? ''));
            if (!$name) { wp_send_json_error(array('message' => 'Organization is required.'), 422); }
            $body['organization'] = array('name' => $name);
        }
        if (!empty($cta['fields']['phone']['visible'])) {
            $phone = trim((string) wp_unslash($_POST['phone'] ?? ''));
            if (!empty($cta['fields']['phone']['required']) && !$phone) { wp_send_json_error(array('message' => 'Phone is required.'), 422); }
            if ($phone) {
                $normalized = trim((string) wp_unslash($_POST['phone_e164'] ?? ''));
                if (!preg_match('/^\+[1-9][0-9]{1,14}$/D', $normalized)) { wp_send_json_error(array('message' => 'Enter a valid international phone number.'), 422); }
                $body['phone_e164'] = $normalized;
            }
        }
        $source = esc_url_raw(wp_unslash($_POST['source_url'] ?? ''));
        $parts = wp_parse_url($source);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', array('http', 'https'), true) || empty($parts['host']) || !self::source_matches_site($parts)) { wp_send_json_error(array('message' => $cta['error_message']), 422); }
        $body['source_url'] = strtok($source, '#');
        $result = self::send('leads', $body);
        if ($result['status'] === 200) { wp_send_json_success(array('message' => $cta['success_message'])); }
        if ($result['status'] === 503 || $result['status'] === 429) {
            if (self::queue($body)) { wp_send_json_success(array('message' => $cta['queued_message'], 'queued' => true)); }
            wp_send_json_error(array('message' => 'Your submission could not be saved. Please try again.'), 503);
        }
        wp_send_json_error(array('message' => $result['message'] ?: $cta['error_message']), 422);
    }

    private static function source_matches_site(array $parts): bool {
        $site = wp_parse_url(home_url());
        return is_array($site) && strtolower($parts['host']) === strtolower($site['host'] ?? '');
    }
}
