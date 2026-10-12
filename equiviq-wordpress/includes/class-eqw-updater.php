<?php
if (!defined('ABSPATH')) { exit; }

/** Supplies GitHub release metadata to WordPress's standard plugin updater. */
final class EQW_Updater {
    private const REPOSITORY = 'imseanbrown/equiviq-wordpress-plugin';
    private const UPDATE_URI = 'https://github.com/imseanbrown/equiviq-wordpress-plugin';

    public static function check($update, array $plugin_data, string $plugin_file, array $locales) {
        if ($plugin_file !== plugin_basename(EQW_PATH . 'equiviq-wordpress.php') ||
            ($plugin_data['UpdateURI'] ?? '') !== self::UPDATE_URI) { return $update; }

        // WordPress already caches plugin update checks. A second release cache
        // makes its "Check again" action return stale data for hours.
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest', array(
            'timeout' => 8, 'redirection' => 0,
            'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'equiviq-wordpress/' . EQW_VERSION),
        ));
        $release = !is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200
            ? json_decode(wp_remote_retrieve_body($response), true) : null;
        if (!is_array($release)) {
            // GitHub's public latest-release redirect avoids API rate limits.
            $response = wp_remote_get(self::UPDATE_URI . '/releases/latest', array(
                'timeout' => 8, 'redirection' => 0,
                'headers' => array('User-Agent' => 'equiviq-wordpress/' . EQW_VERSION),
            ));
            if (is_wp_error($response) || !in_array((int) wp_remote_retrieve_response_code($response), array(301, 302, 303, 307, 308), true)) { return false; }
            $location = (string) wp_remote_retrieve_header($response, 'location');
            if (!preg_match('~^' . preg_quote(self::UPDATE_URI, '~') . '/releases/tag/(v?\d+\.\d+\.\d+)$~D', $location, $redirect_match)) { return false; }
            $tag = $redirect_match[1];
            $version = ltrim($tag, 'v');
            $release = array('tag_name' => $tag, 'assets' => array(array(
                'name' => 'equiviq-wordpress-' . $version . '.zip',
                'browser_download_url' => self::UPDATE_URI . '/releases/download/' . $tag . '/equiviq-wordpress-' . $version . '.zip',
            )));
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
