<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/auth.php';

$config = require __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: ' . $config['app']['cors_origin']);
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = route_path();
$segments = $path === '' ? [] : explode('/', $path);

try {
    if (($segments[0] ?? '') === 'api') array_shift($segments);

    if ($segments === ['questions'] && $method === 'GET') {
        handle_questions_public();
    }

    if ($segments === ['results'] && $method === 'POST') {
        handle_create_result();
    }

    if (($segments[0] ?? '') === 'results' && isset($segments[1]) && $method === 'GET') {
        handle_get_result($segments[1]);
    }

    if ($segments === ['payments', 'create'] && $method === 'POST') {
        handle_create_payment();
    }

    if ($segments === ['payments', 'webhook'] && $method === 'POST') {
        handle_payment_webhook();
    }

    if ($segments === ['admin', 'login'] && $method === 'POST') {
        handle_admin_login();
    }

    if ($segments === ['admin', 'stats'] && $method === 'GET') {
        require_admin();
        handle_admin_stats();
    }

    if (($segments[0] ?? '') === 'admin' && isset($segments[1])) {
        require_admin();
        $entity = $segments[1];
        $id = $segments[2] ?? null;
        handle_admin_crud($entity, $method, $id);
    }

    json_response(['message' => 'Not found'], 404);
} catch (Throwable $e) {
    json_response(['message' => $e->getMessage()], 500);
}

function handle_questions_public(): void {
    $pdo = db();
    $questions = $pdo->query('SELECT * FROM questions WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
    $stmt = $pdo->query('SELECT * FROM question_options ORDER BY id ASC');
    $options = $stmt->fetchAll();
    $byQuestion = [];
    foreach ($options as $option) {
        $byQuestion[$option['question_id']][] = $option;
    }
    foreach ($questions as &$question) {
        $question['options'] = $byQuestion[$question['id']] ?? [];
    }
    json_response(['data' => $questions]);
}

function handle_create_result(): void {
    $pdo = db();
    $input = get_json_input();

    $gradeLevel = (string)($input['grade_level'] ?? '');
    $lang = ($input['lang'] ?? 'ru') === 'ro' ? 'ro' : 'ru';
    $academicScore = (float)($input['academic_score'] ?? 0);
    $answers = $input['answers'] ?? [];

    if (!validate_grade_level($gradeLevel)) {
        json_response(['message' => 'Invalid grade level'], 422);
    }
    if (!$answers || !is_array($answers)) {
        json_response(['message' => 'Answers are required'], 422);
    }

    $questionIds = array_map('intval', array_keys($answers));
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $stmt = $pdo->prepare("SELECT q.id, q.category, qo.id AS option_id, qo.value
        FROM questions q
        JOIN question_options qo ON qo.question_id = q.id
        WHERE q.id IN ($placeholders)");
    $stmt->execute($questionIds);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $map[$row['id']]['category'] = $row['category'];
        $map[$row['id']]['options'][$row['option_id']] = (int)$row['value'];
    }

    $rawScores = ['analytical' => 0, 'technical' => 0, 'social' => 0, 'creative' => 0];
    $countPerCategory = ['analytical' => 0, 'technical' => 0, 'social' => 0, 'creative' => 0];
    $answersPayload = [];

    foreach ($answers as $questionId => $optionId) {
        $questionId = (int)$questionId;
        $optionId = (int)$optionId;
        if (!isset($map[$questionId])) continue;
        $category = $map[$questionId]['category'];
        $value = (int)($map[$questionId]['options'][$optionId] ?? 0);
        $rawScores[$category] += $value;
        $countPerCategory[$category] += 1;
        $answersPayload[] = ['question_id' => $questionId, 'category' => $category, 'option_id' => $optionId, 'value' => $value];
    }

    $scores = [];
    foreach ($rawScores as $category => $total) {
        $max = max(1, $countPerCategory[$category] * 5);
        $scores[$category] = (int)round(($total / $max) * 100);
    }

    $professions = $pdo->query('SELECT * FROM professions WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
    $academicFactor = 0.85 + academic_to_scale_0_1($academicScore) * 0.3;
    $recommendationsFull = [];
    foreach ($professions as $profession) {
        $raw =
            $scores['analytical'] * (float)$profession['weight_analytical'] +
            $scores['technical'] * (float)$profession['weight_technical'] +
            $scores['social'] * (float)$profession['weight_social'] +
            $scores['creative'] * (float)$profession['weight_creative'];
        $weightSum = (float)$profession['weight_analytical'] + (float)$profession['weight_technical'] + (float)$profession['weight_social'] + (float)$profession['weight_creative'];
        $weightSum = $weightSum > 0 ? $weightSum : 1;
        $normalized = ($raw / ($weightSum * 100)) * 100;
        $match = (int)round(min(100, $normalized * $academicFactor));
        $profession['match_percent'] = $match;
        $profession['reason'] = dominant_reason($scores, $lang);
        $recommendationsFull[] = $profession;
    }

    usort($recommendationsFull, fn($a, $b) => $b['match_percent'] <=> $a['match_percent']);

    $recommendationsFree = array_map(function($item) {
        return [
            'id' => $item['id'],
            'title_ru' => $item['title_ru'],
            'title_ro' => $item['title_ro'],
            'short_desc_ru' => $item['short_desc_ru'],
            'short_desc_ro' => $item['short_desc_ro'],
        ];
    }, array_slice($recommendationsFull, 0, 5));

    $uuid = uuidv4();
    $stmt = $pdo->prepare('INSERT INTO results (uuid, grade_level, lang, academic_score, answers_json, scores_json, recommendations_free_json, recommendations_full_json, is_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)');
    $stmt->execute([
        $uuid,
        $gradeLevel,
        $lang,
        $academicScore,
        json_encode($answersPayload, JSON_UNESCAPED_UNICODE),
        json_encode($scores, JSON_UNESCAPED_UNICODE),
        json_encode($recommendationsFree, JSON_UNESCAPED_UNICODE),
        json_encode($recommendationsFull, JSON_UNESCAPED_UNICODE),
    ]);

    json_response(['data' => ['uuid' => $uuid]], 201);
}

function fetch_institutions_by_professions(array $professionIds, string $gradeLevel): array {
    if (!$professionIds) return [];
    $pdo = db();
    $type = $gradeLevel === '9' ? 'college' : 'university';
    $placeholders = implode(',', array_fill(0, count($professionIds), '?'));
    $sql = "SELECT DISTINCT i.* FROM institutions i
            JOIN institution_professions ip ON ip.institution_id = i.id
            WHERE i.type = ? AND ip.profession_id IN ($placeholders)
            ORDER BY i.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$type], $professionIds));
    return $stmt->fetchAll();
}

function handle_get_result(string $uuid): void {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM results WHERE uuid = ? LIMIT 1');
    $stmt->execute([$uuid]);
    $result = $stmt->fetch();
    if (!$result) json_response(['message' => 'Result not found'], 404);

    $scores = parse_json_column($result['scores_json'], []);
    $free = parse_json_column($result['recommendations_free_json'], []);
    $full = parse_json_column($result['recommendations_full_json'], []);
    $recommendations = (int)$result['is_paid'] === 1 ? $full : $free;
    $institutions = [];
    if ((int)$result['is_paid'] === 1) {
        $professionIds = array_values(array_unique(array_map(fn($item) => (int)($item['id'] ?? 0), $full)));
        $institutions = fetch_institutions_by_professions($professionIds, (string)$result['grade_level']);
    }

    json_response(['data' => [
        'uuid' => $result['uuid'],
        'grade_level' => $result['grade_level'],
        'lang' => $result['lang'],
        'academic_score' => $result['academic_score'],
        'scores' => $scores,
        'recommendations' => $recommendations,
        'institutions' => $institutions,
        'is_paid' => (int)$result['is_paid'] === 1,
        'created_at' => $result['created_at'],
    ]]);
}

function handle_create_payment(): void {
    $pdo = db();
    $input = get_json_input();
    $resultUuid = $input['result_uuid'] ?? null;
    if (!$resultUuid) json_response(['message' => 'result_uuid is required'], 422);

    $check = $pdo->prepare('SELECT uuid, is_paid FROM results WHERE uuid = ? LIMIT 1');
    $check->execute([$resultUuid]);
    $result = $check->fetch();
    if (!$result) json_response(['message' => 'Result not found'], 404);
    if ((int)$result['is_paid'] === 1) json_response(['message' => 'Already paid'], 422);

    $providerPaymentId = 'paynet_sandbox_' . bin2hex(random_bytes(6));
    $sandboxToken = hash_hmac('sha256', $providerPaymentId . '|' . $resultUuid, (require __DIR__ . '/config.php')['app']['sandbox_webhook_secret']);

    $stmt = $pdo->prepare('INSERT INTO payments (result_uuid, provider, amount, currency, status, provider_payment_id) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$resultUuid, 'paynet', 2.00, 'USD', 'pending', $providerPaymentId]);

    json_response(['data' => [
        'result_uuid' => $resultUuid,
        'provider' => 'paynet',
        'provider_payment_id' => $providerPaymentId,
        'status' => 'pending',
        'amount' => 2.00,
        'currency' => 'USD',
        'sandbox_token' => $sandboxToken,
    ]], 201);
}

function handle_payment_webhook(): void {
    $pdo = db();
    $input = get_json_input();
    $resultUuid = $input['result_uuid'] ?? '';
    $providerPaymentId = $input['provider_payment_id'] ?? '';
    $status = $input['status'] ?? 'pending';
    $sandboxToken = $input['sandbox_token'] ?? '';

    if (!$resultUuid || !$providerPaymentId) json_response(['message' => 'Missing webhook data'], 422);

    $expected = hash_hmac('sha256', $providerPaymentId . '|' . $resultUuid, (require __DIR__ . '/config.php')['app']['sandbox_webhook_secret']);
    if (!hash_equals($expected, $sandboxToken)) {
        json_response(['message' => 'Invalid webhook signature'], 403);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE payments SET status = ?, updated_at = NOW() WHERE result_uuid = ? AND provider_payment_id = ?');
        $stmt->execute([$status, $resultUuid, $providerPaymentId]);
        if ($status === 'paid') {
            $stmt = $pdo->prepare('UPDATE results SET is_paid = 1 WHERE uuid = ?');
            $stmt->execute([$resultUuid]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_response(['data' => ['ok' => true]]);
}

function handle_admin_login(): void {
    check_rate_limit();
    $input = get_json_input();
    $config = require __DIR__ . '/config.php';

    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($email !== $config['app']['admin_email'] || $password !== $config['app']['admin_password']) {
        register_failed_attempt();
        json_response(['message' => 'Invalid credentials'], 401);
    }

    clear_failed_attempts();
    $token = create_jwt([
        'sub' => $email,
        'role' => 'admin',
        'iat' => time(),
        'exp' => time() + 60 * 60 * 24,
    ], $config['app']['jwt_secret']);

    json_response(['data' => ['token' => $token]]);
}

function handle_admin_stats(): void {
    $pdo = db();
    $tests = (int)$pdo->query('SELECT COUNT(*) FROM results')->fetchColumn();
    $payments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'paid'")->fetchColumn();
    $revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'")->fetchColumn();
    json_response(['data' => [
        'tests_count' => $tests,
        'payments_count' => $payments,
        'revenue_total' => number_format($revenue, 2, '.', ''),
    ]]);
}

function handle_admin_crud(string $entity, string $method, ?string $id): void {
    $pdo = db();
    $allowed = ['questions', 'professions', 'institutions'];
    if (!in_array($entity, $allowed, true)) json_response(['message' => 'Entity not allowed'], 404);

    if ($entity === 'questions') {
        if ($method === 'GET') {
            $questions = $pdo->query('SELECT * FROM questions ORDER BY sort_order ASC, id ASC')->fetchAll();
            $options = $pdo->query('SELECT * FROM question_options ORDER BY id ASC')->fetchAll();
            $byQuestion = [];
            foreach ($options as $option) $byQuestion[$option['question_id']][] = $option;
            foreach ($questions as &$question) $question['options'] = $byQuestion[$question['id']] ?? [];
            json_response(['data' => $questions]);
        }
        $input = get_json_input();
        if ($method === 'POST') {
            $stmt = $pdo->prepare('INSERT INTO questions (category, text_ru, text_ro, is_active, sort_order) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $input['category'] ?? 'analytical',
                $input['text_ru'] ?? '',
                $input['text_ro'] ?? '',
                (int)($input['is_active'] ?? 1),
                (int)($input['sort_order'] ?? 0),
            ]);
            $questionId = (int)$pdo->lastInsertId();
            foreach (($input['options'] ?? []) as $option) {
                $stmt = $pdo->prepare('INSERT INTO question_options (question_id, text_ru, text_ro, value) VALUES (?, ?, ?, ?)');
                $stmt->execute([$questionId, $option['text_ru'] ?? '', $option['text_ro'] ?? '', (int)($option['value'] ?? 1)]);
            }
            json_response(['data' => ['id' => $questionId]], 201);
        }
        if ($method === 'PUT' && $id) {
            $stmt = $pdo->prepare('UPDATE questions SET category = ?, text_ru = ?, text_ro = ?, is_active = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([
                $input['category'] ?? 'analytical',
                $input['text_ru'] ?? '',
                $input['text_ro'] ?? '',
                (int)($input['is_active'] ?? 1),
                (int)($input['sort_order'] ?? 0),
                (int)$id,
            ]);
            if (array_key_exists('options', $input)) {
                $pdo->prepare('DELETE FROM question_options WHERE question_id = ?')->execute([(int)$id]);
                foreach (($input['options'] ?? []) as $option) {
                    $stmt = $pdo->prepare('INSERT INTO question_options (question_id, text_ru, text_ro, value) VALUES (?, ?, ?, ?)');
                    $stmt->execute([(int)$id, $option['text_ru'] ?? '', $option['text_ro'] ?? '', (int)($option['value'] ?? 1)]);
                }
            }
            json_response(['data' => ['id' => (int)$id]]);
        }
        if ($method === 'DELETE' && $id) {
            $pdo->prepare('DELETE FROM questions WHERE id = ?')->execute([(int)$id]);
            json_response(['data' => ['deleted' => true]]);
        }
    }

    if ($entity === 'professions') {
        if ($method === 'GET') json_response(['data' => $pdo->query('SELECT * FROM professions ORDER BY id ASC')->fetchAll()]);
        $input = get_json_input();
        $columns = ['title_ru','title_ro','short_desc_ru','short_desc_ro','full_desc_ru','full_desc_ro','weight_analytical','weight_technical','weight_social','weight_creative','is_active'];
        if ($method === 'POST') {
            $stmt = $pdo->prepare('INSERT INTO professions (title_ru, title_ro, short_desc_ru, short_desc_ro, full_desc_ru, full_desc_ro, weight_analytical, weight_technical, weight_social, weight_creative, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(array_map(fn($key) => $input[$key] ?? (str_starts_with($key, 'weight_') ? 0 : ($key === 'is_active' ? 1 : '')), $columns));
            json_response(['data' => ['id' => (int)$pdo->lastInsertId()]], 201);
        }
        if ($method === 'PUT' && $id) {
            $stmt = $pdo->prepare('UPDATE professions SET title_ru=?, title_ro=?, short_desc_ru=?, short_desc_ro=?, full_desc_ru=?, full_desc_ro=?, weight_analytical=?, weight_technical=?, weight_social=?, weight_creative=?, is_active=? WHERE id=?');
            $values = array_map(fn($key) => $input[$key] ?? (str_starts_with($key, 'weight_') ? 0 : ($key === 'is_active' ? 1 : '')), $columns);
            $values[] = (int)$id;
            $stmt->execute($values);
            json_response(['data' => ['id' => (int)$id]]);
        }
        if ($method === 'DELETE' && $id) {
            $pdo->prepare('DELETE FROM professions WHERE id = ?')->execute([(int)$id]);
            json_response(['data' => ['deleted' => true]]);
        }
    }

    if ($entity === 'institutions') {
        if ($method === 'GET') json_response(['data' => $pdo->query('SELECT * FROM institutions ORDER BY id ASC')->fetchAll()]);
        $input = get_json_input();
        if ($method === 'POST') {
            $stmt = $pdo->prepare('INSERT INTO institutions (type, name_ru, name_ro, city, website_url, description_ru, description_ro) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $input['type'] ?? 'college',
                $input['name_ru'] ?? '',
                $input['name_ro'] ?? '',
                $input['city'] ?? '',
                $input['website_url'] ?? null,
                $input['description_ru'] ?? null,
                $input['description_ro'] ?? null,
            ]);
            json_response(['data' => ['id' => (int)$pdo->lastInsertId()]], 201);
        }
        if ($method === 'PUT' && $id) {
            $stmt = $pdo->prepare('UPDATE institutions SET type=?, name_ru=?, name_ro=?, city=?, website_url=?, description_ru=?, description_ro=? WHERE id=?');
            $stmt->execute([
                $input['type'] ?? 'college',
                $input['name_ru'] ?? '',
                $input['name_ro'] ?? '',
                $input['city'] ?? '',
                $input['website_url'] ?? null,
                $input['description_ru'] ?? null,
                $input['description_ro'] ?? null,
                (int)$id,
            ]);
            json_response(['data' => ['id' => (int)$id]]);
        }
        if ($method === 'DELETE' && $id) {
            $pdo->prepare('DELETE FROM institutions WHERE id = ?')->execute([(int)$id]);
            json_response(['data' => ['deleted' => true]]);
        }
    }

    json_response(['message' => 'Method not allowed'], 405);
}
