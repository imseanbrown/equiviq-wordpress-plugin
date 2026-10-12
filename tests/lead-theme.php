<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
define('EQW_VERSION', '0.2.7');
define('EQW_URL', 'https://example.test/wp-content/plugins/equiviq-wordpress/');
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function get_option($key, $default = false) {
    return $key === 'eqw_lead_ctas' ? array('qa' => array(
        'title' => 'Contact us', 'cta_text' => 'Tell us more', 'privacy_url' => 'https://example.test/privacy',
        'fields' => array('given_name' => array('visible' => true), 'surname' => array('visible' => true),
            'email' => array('visible' => true, 'required' => true), 'phone' => array('visible' => true)),
    )) : $default;
}
function wp_enqueue_script(...$args) {}
function get_privacy_policy_url() { return 'https://example.test/privacy'; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce($action) { return 'qa-nonce'; }
require dirname(__DIR__) . '/equiviq-wordpress/includes/class-eqw-leads.php';
$html = EQW_Leads::shortcode(array('id' => 'qa'));
foreach (array('data-eqw-version="0.2.7"', '<h2 class="wp-block-heading">Contact us</h2>',
    '<button class="wp-element-button" type="submit">Submit</button>', 'eqw-lead-name-row', 'eqw-lead-phone') as $expected) {
    if (!str_contains($html, $expected)) throw new RuntimeException('Missing theme markup: ' . $expected);
}
$css = file_get_contents(dirname(__DIR__) . '/equiviq-wordpress/assets/leads.css');
if (str_contains($css, 'max-width: 38rem') || preg_match('/(?:font-size|background|border-color|#[0-9a-f]{3,8})\s*:/i', $css)) {
    throw new RuntimeException('Lead layout stylesheet still overrides theme presentation');
}
echo "WORDPRESS_LEAD_THEME_QA_OK\n";
