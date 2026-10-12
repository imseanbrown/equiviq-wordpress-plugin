<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
require dirname(__DIR__) . '/equiviq-wordpress/includes/class-eqw-leads.php';

$method = new ReflectionMethod(EQW_Leads::class, 'block_free_email_domains');
if ($method->invoke(null) !== true) throw new RuntimeException('Existing sites must block free domains by default');
$GLOBALS['options']['eqw_block_free_email_domains'] = 'no';
if ($method->invoke(null) !== false) throw new RuntimeException('No setting was not applied');
$GLOBALS['options']['eqw_block_free_email_domains'] = 'yes';
if ($method->invoke(null) !== true) throw new RuntimeException('Yes setting was not applied');
echo "WORDPRESS_LEAD_POLICY_QA_OK\n";
