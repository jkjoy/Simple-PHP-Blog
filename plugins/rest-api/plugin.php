<?php
declare(strict_types=1);

const SBLOG_REST_API_VERSION = '1.0.2';
const SBLOG_REST_API_ACTION = 'sblog_rest_api';

require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/resources.php';
require_once __DIR__ . '/includes/admin.php';

// Active plugins load before the core pretty-route parser, so the plugin can
// claim /wp-json without requiring a core edit or server-specific rewrite.
sblog_rest_api_claim_request();

add_plugin_action('plugins_loaded', 'sblog_rest_api_install');
add_plugin_action('request', 'sblog_rest_api_handle_request', -1000);
