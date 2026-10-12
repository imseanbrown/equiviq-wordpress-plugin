<?php
if (!defined('ABSPATH')) { exit; }

final class EQW_Connection {
    private const OPTION = 'eqw_connection';

    public static function settings(): array {
        $data = get_option(self::OPTION, array());
        return is_array($data) ? $data : array();
    }

    public static function valid_url(string $raw): bool {
        $parts = wp_parse_url($raw);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) { return false; }
        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['port'])) { return false; }
        return (bool) preg_match('~^/api/public/plugins/equiviq-wordpress/[a-z0-9-]+/guides/?$~D', $parts['path'] ?? '');
    }

    private static function cipher_key(): string {
        return hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
    }

    public static function encrypt(string $value): string {
        if (!function_exists('openssl_encrypt')) { return ''; }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', self::cipher_key(), OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($ciphertext) ? base64_encode($iv . $tag . $ciphertext) : '';
    }

    private static function decrypt(string $value): string {
        if (!function_exists('openssl_decrypt')) { return ''; }
        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) < 29) { return ''; }
        $plain = openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', self::cipher_key(), OPENSSL_RAW_DATA, substr($decoded, 0, 12), substr($decoded, 12, 16));
        return is_string($plain) ? $plain : '';
    }

    public static function api_url(): string {
        $settings = self::settings();
        $raw = defined('EQW_API_URL') ? EQW_API_URL : ($settings['api_url'] ?? '');
        return is_string($raw) && self::valid_url($raw) ? untrailingslashit($raw) : '';
    }

    public static function api_key(): string {
        if (defined('EQW_API_KEY') && is_string(EQW_API_KEY)) { return EQW_API_KEY; }
        $settings = self::settings();
        return self::decrypt((string) ($settings['api_key'] ?? ''));
    }

    public static function save(string $url, string $new_key, int $primary_page): bool {
        if (!self::valid_url($url)) { return false; }
        $settings = self::settings();
        $settings['api_url'] = untrailingslashit($url);
        if ($new_key !== '') {
            if (strlen($new_key) > 256 || !preg_match('/^eqw_[a-f0-9]{64}$/', $new_key)) { return false; }
            $encrypted = self::encrypt($new_key);
            if ($encrypted === '') { return false; }
            $settings['api_key'] = $encrypted;
        }
        $old_primary = (int) ($settings['primary_page'] ?? 0);
        if ($old_primary && $old_primary !== $primary_page) {
            $old_path = trim(get_page_uri($old_primary), '/');
            if ($old_path !== '') { $settings['legacy_paths'] = array_values(array_unique(array_merge((array) ($settings['legacy_paths'] ?? array()), array($old_path)))); }
        }
        $settings['primary_page'] = $primary_page;
        $settings['primary_path'] = $primary_page ? trim(get_page_uri($primary_page), '/') : '';
        update_option(self::OPTION, $settings, false);
        return true;
    }

    public static function request(string $path = '', string $query = '', string $method = 'GET', ?array $data = null, bool $cache = true) {
        $base = self::api_url();
        $key = self::api_key();
        if ($base === '' || $key === '' || !preg_match('~^(?:[a-z0-9-]+(?:/[a-z0-9-]+)*)?$~D', $path)) { return null; }
        $url = $base . ($path !== '' ? '/' . $path : '');
        if ($query !== '') { $url = add_query_arg('q', $query, $url); }
        $cache_key = 'eqw_' . substr(hash('sha256', get_current_blog_id() . '|' . $url . '|' . hash('sha256', $key)), 0, 36);
        if ($cache && $method === 'GET') {
            $stored = get_transient($cache_key);
            if (is_array($stored)) { return $stored; }
        }
        $args = array('method' => $method, 'timeout' => 7, 'redirection' => 0, 'sslverify' => true,
            'headers' => array('Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json'));
        if ($data !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($data);
        }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) { return null; }
        $status = wp_remote_retrieve_response_code($response);
        if ($status === 204) { return array('ok' => true); }
        if ($status !== 200) { return null; }
        $body = wp_remote_retrieve_body($response);
        if (strlen($body) > 10 * 1024 * 1024) { return null; }
        $value = json_decode($body, true);
        if (!is_array($value)) { return null; }
        if ($cache && $method === 'GET') { set_transient($cache_key, $value, 30); }
        return $value;
    }

    public static function image(string $article, string $image) {
        $uuid = '/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/iD';
        if (!preg_match($uuid, $article) || !preg_match($uuid, $image)) { return null; }
        $base = self::api_url();
        $key = self::api_key();
        if ($base === '' || $key === '') { return null; }
        $response = wp_remote_get($base . '/' . $article . '/images/' . $image, array('timeout' => 7, 'redirection' => 0, 'sslverify' => true, 'headers' => array('Authorization' => 'Bearer ' . $key)));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return null; }
        $mime = wp_remote_retrieve_header($response, 'content-type');
        $body = wp_remote_retrieve_body($response);
        return in_array($mime, array('image/png', 'image/jpeg', 'image/gif'), true) && strlen($body) <= 8 * 1024 * 1024 ? array($mime, $body) : null;
    }
}
