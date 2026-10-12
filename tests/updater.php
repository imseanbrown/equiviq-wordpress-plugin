<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
define('EQW_PATH', dirname(__DIR__) . '/equiviq-wordpress/');
$plugin_source = file_get_contents(dirname(__DIR__) . '/equiviq-wordpress/equiviq-wordpress.php');
if (!preg_match("/define\('EQW_VERSION', '([^']+)'\)/", $plugin_source, $version_match)) throw new RuntimeException('Plugin version missing');
define('EQW_VERSION', $version_match[1]);
function plugin_basename($path) { return 'equiviq-wordpress/equiviq-wordpress.php'; }
function get_transient($key) { throw new RuntimeException('Updater must not use a second release cache'); }
function set_transient($key, $value, $expiry) { throw new RuntimeException('Updater must not use a second release cache'); }
function is_wp_error($value) { return false; }
function wp_remote_retrieve_response_code($value) { return $value['status']; }
function wp_remote_retrieve_body($value) { return $value['body']; }
function wp_remote_retrieve_header($value, $name) { return $value['headers'][$name] ?? ''; }
function wp_remote_get($url, $options) {
    $GLOBALS['request_count'] = ($GLOBALS['request_count'] ?? 0) + 1;
    if ($url === 'https://api.github.com/repos/imseanbrown/wordpress-equiviq/releases/latest') {
        return array('status' => $GLOBALS['api_status'] ?? 200, 'body' => json_encode($GLOBALS['remote_release']));
    }
    if ($url === 'https://github.com/imseanbrown/wordpress-equiviq/releases/latest') {
        return array('status' => 302, 'headers' => array('location' => $GLOBALS['redirect_location'] ?? ''));
    }
    throw new RuntimeException('Unexpected update source');
}
require dirname(__DIR__) . '/equiviq-wordpress/includes/class-eqw-updater.php';
$plugin = array('UpdateURI' => 'https://github.com/imseanbrown/wordpress-equiviq');
$file = 'equiviq-wordpress/equiviq-wordpress.php';
$newer = '99.99.99';
$GLOBALS['remote_release'] = array('tag_name' => 'v' . $newer, 'assets' => array(array(
    'name' => 'equiviq-wordpress-' . $newer . '.zip',
    'browser_download_url' => 'https://github.com/imseanbrown/wordpress-equiviq/releases/download/v' . $newer . '/equiviq-wordpress-' . $newer . '.zip',
)));
$update = EQW_Updater::check(false, $plugin, $file, array('en_US'));
if (!is_array($update) || $update['version'] !== $newer || !str_ends_with($update['package'], '.zip')) throw new RuntimeException('A newer release was not offered');
if (EQW_Updater::check(false, $plugin, 'other-plugin/main.php', array()) !== false) throw new RuntimeException('Updater claimed another plugin');
$GLOBALS['remote_release']['tag_name'] = 'v' . EQW_VERSION;
if (EQW_Updater::check(false, $plugin, $file, array()) !== false) throw new RuntimeException('Current version was offered as an update');
$GLOBALS['remote_release'] = array('tag_name' => 'v' . $newer, 'assets' => array(array('name' => 'equiviq-wordpress-' . $newer . '.zip', 'browser_download_url' => 'https://example.test/bad.zip')));
if (EQW_Updater::check(false, $plugin, $file, array()) !== false) throw new RuntimeException('Untrusted ZIP host was accepted');
$GLOBALS['api_status'] = 403;
$GLOBALS['redirect_location'] = 'https://github.com/imseanbrown/wordpress-equiviq/releases/tag/v' . $newer;
$update = EQW_Updater::check(false, $plugin, $file, array());
if (!is_array($update) || $update['version'] !== $newer) throw new RuntimeException('GitHub redirect fallback did not offer the release');
$GLOBALS['redirect_location'] = 'https://example.test/releases/tag/v' . $newer;
if (EQW_Updater::check(false, $plugin, $file, array()) !== false) throw new RuntimeException('Untrusted redirect was accepted');
if (($GLOBALS['request_count'] ?? 0) < 7) throw new RuntimeException('A stale update response was cached');
echo "WORDPRESS_UPDATE_QA_OK\n";
