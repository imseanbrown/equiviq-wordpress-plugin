<?php
declare(strict_types=1);

$script = file_get_contents(dirname(__DIR__) . '/equiviq-wordpress/assets/leads.js');
if ($script === false) { throw new RuntimeException('Lead script is missing'); }

// A hidden input named "action" masks HTMLFormElement.action in browsers.
// Both the nonce request and submission must use the form attribute.
if (substr_count($script, 'fetch(d.getAttribute("action"),') !== 2 ||
    str_contains($script, 'fetch(d.action,')) {
    throw new RuntimeException('Lead requests do not target the form action URL');
}

echo "WORDPRESS_LEAD_ROUTE_QA_OK\n";
