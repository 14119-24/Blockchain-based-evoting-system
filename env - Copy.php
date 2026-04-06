<?php

function app_env($key, $default = null) {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }

    return $default;
}

function app_env_bool($key, $default = false) {
    $value = app_env($key, null);
    if ($value === null) {
        return $default;
    }

    $normalized = strtolower(trim((string) $value));

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function app_env_int($key, $default) {
    $value = app_env($key, null);
    if ($value === null || $value === '') {
        return $default;
    }

    return (int) $value;
}

function app_normalize_path_prefix($path) {
    $path = trim(str_replace('\\', '/', (string) $path));
    if ($path === '' || $path === '/') {
        return '';
    }

    return '/' . trim($path, '/');
}

function app_base_url() {
    $configured = trim((string) app_env('APP_BASE_URL', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $renderUrl = trim((string) app_env('RENDER_EXTERNAL_URL', ''));
    if ($renderUrl !== '') {
        return rtrim($renderUrl, '/');
    }

    $renderHost = trim((string) app_env('RENDER_EXTERNAL_HOSTNAME', ''));
    if ($renderHost !== '') {
        return 'https://' . $renderHost;
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = app_env_bool('APP_FORCE_HTTPS', false)
        || $forwardedProto === 'https'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    return ($isHttps ? 'https' : 'http') . '://' . $host;
}

function app_path_prefix_from_script() {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName === '') {
        return '';
    }

    if (preg_match('~^(.*)/api/[^/]+$~', $scriptName, $matches)) {
        return rtrim($matches[1], '/');
    }

    return rtrim(dirname($scriptName), '/');
}

function app_public_path_prefix() {
    $configured = app_normalize_path_prefix(app_env('APP_PUBLIC_PATH_PREFIX', ''));
    if ($configured !== '') {
        return $configured;
    }

    $prefix = app_path_prefix_from_script();
    return $prefix === '' ? '' : $prefix . '/public';
}

function app_public_url($path = '') {
    $baseUrl = app_base_url();
    $publicPrefix = app_public_path_prefix();
    $suffix = $path === '' ? '' : '/' . ltrim($path, '/');

    return $baseUrl . $publicPrefix . $suffix;
}
