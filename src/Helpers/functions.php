<?php

// require Kint debugging library on local only (only include it if it exists)
$global_file = env('CONFIG_MAP');
$path = pathinfo(base_path($global_file));
if (file_exists($path['dirname'] . '/../lab/kint_init.php')) {
    require_once($path['dirname'] . '/../lab/kint_init.php');
}

/**
 * Return an array from our config map file
 */
function get_config_map($org = null) {
    $global_file = env('CONFIG_MAP');
    if (!file_exists(base_path($global_file))) {
        die('No global config map found');
    }
    $config_map = include(base_path($global_file));
    if ($org != null) {
        $path = pathinfo(base_path($global_file));
        $org_file = $path['dirname'] . '/' . $org . '.php';
        if (!file_exists($org_file)) {
            die('No org config map found');
        }
        $org_config = include($org_file);
        $db_credentials = array_merge($config_map['db_credentials'], $org_config['db_credentials']);
        $config_map['db_credentials'] = $db_credentials;
    }
    return $config_map;
}

/**
 * Get our db credentials from our config map
 */
function get_db_creds($service, $org = '') {
    $config_map = get_config_map($org);
    if ( isset($config_map['db_credentials'][$service]) ) {
        $service_creds = $config_map['db_credentials'][$service];
        if ( isset($service_creds['DB_DATABASE']) ) {
            $creds = $service_creds;
        } elseif ( isset($service_creds[$org]) ) {
            $creds = $service_creds[$org];
        }
    }
    return isset($creds) ? $creds : null;
}

/**
 * Get list of orgs from our config files
 */
function get_orgs_list() {
    $orgs = [];
    $global_file = env('CONFIG_MAP');
    $path = pathinfo(base_path($global_file));
    foreach (glob($path['dirname'] . '/*.php') as $filename) {
        $file = basename($filename);
        if ($file != 'global.php' && $file != 'org_template.php') {
            $orgs[] = preg_replace('/\.php/', '', $file);
        }
    }
    return $orgs;
}

/**
 * Generate an absoulte URL to a microservice URL
 */
function api_url($service, $path = '') {
    $config_map = get_config_map();
    if (isset($_SERVER['HTTP_HOST'])) {
        $host_parts = explode('.', $_SERVER['HTTP_HOST']);
        $host_count = count($host_parts);
        if ( $service == null && isset($host_parts[$host_count - 4]) ) {
            $service = $host_parts[$host_count - 4];
        }
        $base = $host_parts[$host_count - 2] . '.' . $host_parts[$host_count - 1];
        $url = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . $service . '.' . $config_map['server'] . '.' . $base . (preg_match('/^\//', $path) ? '' : '/') . $path;
    } else {
        $url = (env('APP_ENV') == 'production' ? 'https' : 'http') . '://' . $service . '.' . $config_map['server'] . '.' . $config_map['domain'] . (preg_match('/^\//', $path) ? '' : '/') . $path;
    }
    return $url;
}

/**
 * Abort with custom message and status code
 */
function fail($message, $status_code = 403) {
    abort($status_code, $message);
}

/**
 * Get our current auth token value
 */
function get_current_token() {
    $request = \Illuminate\Http\Request::capture();
    $token = preg_replace('/^Token\s/', '', $request->header('authorization'));
    return $token;
}

/**
 * Check if current user has permission for key
 */
function has_permission($key) {
    if (isset(app('user')['super_admin_enabled']) && app('user')['super_admin_enabled']) {
        return true;
    }
    $permissions = app('permissions');
    return is_array($permissions) && array_key_exists($key, $permissions) ? true : false;
}

/**
 * Convert our permissions collection into a flat array for cache
 */
function prepare_cache_permissions($permissions) {
    $perms = [];
    foreach ($permissions as $perm) {
        $perms[$perm['key']] = $perm['value'];
    }
    return $perms;
}