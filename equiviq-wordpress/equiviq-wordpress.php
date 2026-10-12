<?php
/**
 * Plugin Name: Equiviq for Wordpress
 * Description: Wordpress CMS integration with Equiviq core modules
 * Version: 0.2.2
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Equiviq
 * Text Domain: equiviq-wordpress
 * Update URI: https://github.com/imseanbrown/equiviq-wordpress-plugin
 */

if (!defined('ABSPATH')) { exit; }

define('EQW_VERSION', '0.2.2');
define('EQW_PATH', plugin_dir_path(__FILE__));
define('EQW_URL', plugin_dir_url(__FILE__));

require_once EQW_PATH . 'vendor/Parsedown.php';
require_once EQW_PATH . 'includes/class-eqw-connection.php';
require_once EQW_PATH . 'includes/class-eqw-admin.php';
require_once EQW_PATH . 'includes/class-eqw-guides.php';
require_once EQW_PATH . 'includes/class-eqw-leads.php';
require_once EQW_PATH . 'includes/class-eqw-updater.php';

add_action('init', array('EQW_Guides', 'init'));
add_action('init', array('EQW_Leads', 'init'));
add_filter('update_plugins_github.com', array('EQW_Updater', 'check'), 10, 4);
add_action('admin_menu', array('EQW_Admin', 'menu'));
add_action('admin_menu', array('EQW_Leads', 'menu'));
add_action('admin_post_eqw_save_cta', array('EQW_Leads', 'save_cta'));
add_action('admin_post_eqw_delete_cta', array('EQW_Leads', 'delete_cta'));
add_action('admin_post_eqw_retry_leads', array('EQW_Leads', 'retry_now'));
add_action('wp_ajax_eqw_submit_lead', array('EQW_Leads', 'submit'));
add_action('wp_ajax_nopriv_eqw_submit_lead', array('EQW_Leads', 'submit'));
add_action('wp_ajax_eqw_lead_nonce', array('EQW_Leads', 'nonce'));
add_action('wp_ajax_nopriv_eqw_lead_nonce', array('EQW_Leads', 'nonce'));
add_action('eqw_retry_leads', array('EQW_Leads', 'retry'));
add_action('eqw_retry_leads_once', array('EQW_Leads', 'retry'));
add_filter('plugin_action_links_' . plugin_basename(__FILE__), array('EQW_Admin', 'plugin_links'));
add_action('admin_post_eqw_save_settings', array('EQW_Admin', 'save'));
add_action('admin_post_eqw_test_connection', array('EQW_Admin', 'test'));
add_action('admin_post_eqw_save_knowledge', array('EQW_Guides', 'save_preset'));
add_action('admin_post_eqw_delete_knowledge', array('EQW_Guides', 'delete_preset'));
add_action('rest_api_init', array('EQW_Guides', 'rest_routes'));
add_action('wp_enqueue_scripts', array('EQW_Guides', 'assets'));
add_action('wp_enqueue_scripts', array('EQW_Leads', 'assets'));
add_action('enqueue_block_editor_assets', array('EQW_Guides', 'editor_assets'));
add_filter('query_vars', array('EQW_Guides', 'query_vars'));
add_filter('the_content', array('EQW_Guides', 'the_content'), 1);
add_filter('rest_pre_serve_request', array('EQW_Guides', 'serve_image'), 10, 4);
add_filter('document_title_parts', array('EQW_Guides', 'title_parts'));
add_filter('redirect_canonical', array('EQW_Guides', 'redirect_canonical'), 10, 2);
add_action('wp_head', array('EQW_Guides', 'head'), 2);
add_action('template_redirect', array('EQW_Guides', 'template_redirect'), 1);
add_action('save_post_page', array('EQW_Guides', 'page_saved'), 20, 3);
register_activation_hook(__FILE__, function () { EQW_Guides::activate(); EQW_Leads::activate(); });
register_deactivation_hook(__FILE__, array('EQW_Guides', 'deactivate'));
