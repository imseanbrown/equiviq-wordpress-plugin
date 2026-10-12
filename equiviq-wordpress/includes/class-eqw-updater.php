<?php
if (!defined('ABSPATH')) { exit; }

/** Supplies GitHub release metadata to WordPress's standard plugin updater. */
final class EQW_Updater {
    private const REPOSITORY = 'imseanbrown/equiviq-wordpress-plugin';
    private const UPDATE_URI = 'https://github.com/imseanbrown/equiviq-wordpress-plugin';

    public static function check($update, array $plugin_data, string $plugin_file, array $locales) {
        if ($plugin_file !== plugin_basename(EQW_PATH . 'equiviq-wordpress.php') ||
            ($plugin_data['UpdateURI'] ?? '') !== self::UPDATE_URI) { return $update; }

        $release = get_transient('eqw_latest_release');
        if (!is_array($release)) {
            $response = wp_remote_get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest', array(
                'timeout' => 8, 'redirection' => 0,
                'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'equiviq-wordpress/' . EQW_VERSION),
            ));
            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) { return false; }
            $release = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($release)) { return false; }
            set_transient('eqw_latest_release', $release, 6 * HOUR_IN_SECONDS);
        }

        $tag = (string) ($release['tag_name'] ?? '');
        if (!preg_match('/^v?(\d+\.\d+\.\d+)$/D', $tag, $match)) { return false; }
        $version = $match[1];
        if (!version_compare($version, EQW_VERSION, '>')) { return false; }
        $package = '';
        foreach ((array) ($release['assets'] ?? array()) as $asset) {
            if (!is_array($asset) || ($asset['name'] ?? '') !== 'equiviq-wordpress-' . $version . '.zip') { continue; }
            $candidate = (string) ($asset['browser_download_url'] ?? '');
            if (preg_match('~^https://github\.com/' . preg_quote(self::REPOSITORY, '~') . '/releases/download/[^/]+/equiviq-wordpress-' . preg_quote($version, '~') . '\.zip$~D', $candidate)) {
                $package = $candidate;
                break;
            }
        }
        if (!$package) { return false; }

        return array(
            'id' => self::UPDATE_URI,
            'slug' => 'equiviq-wordpress',
            'version' => $version,
            'url' => self::UPDATE_URI . '/releases/latest',
            'package' => $package,
            'requires_php' => '8.1',
        );
    }
}
