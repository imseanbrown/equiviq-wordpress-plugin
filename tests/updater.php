<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
define('EQW_PATH', dirname(__DIR__) . '/equiviq-wordpress/');
$plugin_source = file_get_contents(dirname(__DIR__) . '/equiviq-wordpress/equiviq-wordpress.php');
if (!preg_match("/define\('EQW_VERSION', '([^']+)'\)/", $plugin_source, $version_match)) throw new RuntimeException('Plugin version missing');
define('EQW_VERSION', $version_match[1]);
define('HOUR_IN_SECONDS', 3600);
function plugin_basename($path) { return 'equiviq-wordpress/equiviq-wordpress.php'; }
function get_transient($key) { return $GLOBALS['release_cache'] ?? false; }
function set_transient($key, $value, $expiry) { $GLOBALS['release_cache'] = $value; }
function is_wp_error($value) { return false; }
function wp_remote_retrieve_response_code($value) { return $value['status']; }
function wp_remote_retrieve_body($value) { return $value['body']; }
function wp_remote_get($url, $options) {
    if ($url !== 'https://api.github.com/repos/imseanbrown/equiviq-wordpress-plugin/releases/latest') throw new RuntimeException('Unexpected update source');
    return array('status' => 200, 'body' => json_encode($GLOBALS['remote_release']));
}
require dirname(__DIR__) . '/equiviq-wordpress/includes/class-eqw-updater.php';
$plugin = array('UpdateURI' => 'https://github.com/imseanbrown/equiviq-wordpress-plugin');
$file = 'equiviq-wordpress/equiviq-wordpress.php';
$newer = '99.99.99';
$GLOBALS['remote_release'] = array('tag_name' => 'v' . $newer, 'assets' => array(array(
    'name' => 'equiviq-wordpress-' . $newer . '.zip',
    'browser_download_url' => 'https://github.com/imseanbrown/equiviq-wordpress-plugin/releases/download/v' . $newer . '/equiviq-wordpress-' . $newer . '.zip',
)));
$update = EQW_Updater::check(false, $plugin, $file, array('en_US'));
if (!is_array($update) || $update['version'] !== $newer || !str_ends_with($update['package'], '.zip')) throw new RuntimeException('A newer release was not offered');
if (EQW_Updater::check(false, $plugin, 'other-plugin/main.php', array()) !== false) throw new RuntimeException('Updater claimed another plugin');
$GLOBALS['release_cache'] = array('tag_name' => 'v' . EQW_VERSION, 'assets' => $GLOBALS['remote_release']['assets']);
if (EQW_Updater::check(false, $plugin, $file, array()) !== false) throw new RuntimeException('Current version was offered as an update');
$GLOBALS['release_cache'] = array('tag_name' => 'v' . $newer, 'assets' => array(array('name' => 'equiviq-wordpress-' . $newer . '.zip', 'browser_download_url' => 'https://example.test/bad.zip')));
if (EQW_Updater::check(false, $plugin, $file, array()) !== false) throw new RuntimeException('Untrusted ZIP host was accepted');
echo "WORDPRESS_UPDATE_QA_OK\n";
