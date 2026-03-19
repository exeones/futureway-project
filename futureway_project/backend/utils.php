<?php
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function route_path(): string {
    $path = $_GET['path'] ?? '';
    return trim($path, '/');
}

function uuidv4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function now_iso(): string {
    return date('c');
}

function parse_json_column($value, $fallback = []) {
    if ($value === null || $value === '') return $fallback;
    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
}

function validate_grade_level($value): bool {
    return in_array((string)$value, ['9', '12'], true);
}

function academic_to_scale_0_1($value): float {
    $num = (float)$value;
    if ($num > 10) $num /= 100;
    else $num /= 10;
    return max(0, min(1, $num));
}

function dominant_reason(array $scores, string $lang): string {
    arsort($scores);
    $top = array_key_first($scores);
    $map = [
        'ru' => [
            'analytical' => 'У вас сильное аналитическое мышление.',
            'technical' => 'У вас выраженный технический интерес.',
            'social' => 'У вас сильные коммуникативные качества.',
            'creative' => 'У вас ярко выраженная креативность.',
        ],
        'ro' => [
            'analytical' => 'Ai o gândire analitică puternică.',
            'technical' => 'Ai un interes tehnic pronunțat.',
            'social' => 'Ai abilități bune de comunicare.',
            'creative' => 'Ai o creativitate pronunțată.',
        ]
    ];
    return $map[$lang][$top] ?? '';
}
