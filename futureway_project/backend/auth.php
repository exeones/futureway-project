<?php
require_once __DIR__ . '/utils.php';

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function create_jwt(array $payload, string $secret): string {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $segments = [
        base64url_encode(json_encode($header)),
        base64url_encode(json_encode($payload)),
    ];
    $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
    $segments[] = base64url_encode($signature);
    return implode('.', $segments);
}

function verify_jwt(string $token, string $secret): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$encodedHeader.$encodedPayload", $secret, true));
    if (!hash_equals($expected, $encodedSignature)) return null;
    $payload = json_decode(base64url_decode($encodedPayload), true);
    if (!is_array($payload)) return null;
    if (($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

function bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return $matches[1];
    }
    return null;
}

function require_admin(): array {
    $config = require __DIR__ . '/config.php';
    $token = bearer_token();
    if (!$token) json_response(['message' => 'Unauthorized'], 401);
    $payload = verify_jwt($token, $config['app']['jwt_secret']);
    if (!$payload) json_response(['message' => 'Invalid token'], 401);
    return $payload;
}

function rate_limit_key(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function check_rate_limit(): void {
    $config = require __DIR__ . '/config.php';
    $file = $config['app']['rate_limit_file'];
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    if (!file_exists($file)) file_put_contents($file, '{}');

    $window = $config['app']['rate_limit_window_seconds'];
    $maxAttempts = $config['app']['rate_limit_max_attempts'];
    $key = rate_limit_key();
    $data = json_decode(file_get_contents($file), true) ?: [];
    $attempts = array_filter($data[$key] ?? [], fn($ts) => $ts > time() - $window);
    if (count($attempts) >= $maxAttempts) {
        json_response(['message' => 'Too many login attempts. Please try later.'], 429);
    }
}

function register_failed_attempt(): void {
    $config = require __DIR__ . '/config.php';
    $file = $config['app']['rate_limit_file'];
    if (!file_exists($file)) file_put_contents($file, '{}');
    $key = rate_limit_key();
    $data = json_decode(file_get_contents($file), true) ?: [];
    $data[$key] = array_values(array_filter($data[$key] ?? [], fn($ts) => $ts > time() - $config['app']['rate_limit_window_seconds']));
    $data[$key][] = time();
    file_put_contents($file, json_encode($data));
}

function clear_failed_attempts(): void {
    $config = require __DIR__ . '/config.php';
    $file = $config['app']['rate_limit_file'];
    if (!file_exists($file)) return;
    $key = rate_limit_key();
    $data = json_decode(file_get_contents($file), true) ?: [];
    unset($data[$key]);
    file_put_contents($file, json_encode($data));
}
