<?php
declare(strict_types=1);

// Os valores locais continuam funcionando, mas em produção as credenciais devem
// vir do ambiente para não ficarem salvas no repositório.
define('DB_HOST', getenv('SEMEJ_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('SEMEJ_DB_PORT') ?: '3306');
define('DB_NAME', getenv('SEMEJ_DB_NAME') ?: 'semej_corridas');
define('DB_USER', getenv('SEMEJ_DB_USER') ?: 'root');
define('DB_PASS', getenv('SEMEJ_DB_PASS') ?: '');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        ensure_schema($pdo);
    }
    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    // Migração leve para instalações antigas. Novas instalações já recebem
    // essas colunas pelo database.sql e não executam nenhum ALTER TABLE.
    $columns = [
        'usuario' => [
            'nome' => 'VARCHAR(180) NULL', 'telefone' => 'VARCHAR(30) NULL',
            'endereco' => 'VARCHAR(255) NULL', 'email' => 'VARCHAR(180) NULL', 'cpf' => 'VARCHAR(11) NULL',
        ],
        'corrida' => ['hora_ini' => 'TIME NULL', 'hora_fin' => 'TIME NULL'],
    ];
    foreach ($columns as $table => $definitions) {
        $query = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        $query->execute([DB_NAME, $table]);
        $existing = array_flip($query->fetchAll(PDO::FETCH_COLUMN));
        foreach ($definitions as $column => $definition) {
            if (!isset($existing[$column])) {
                // Os identificadores vêm somente da lista fixa acima, nunca da requisição.
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        }
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Revalida os dados de autorização a cada requisição. Assim, alterações feitas
// pelo administrador passam a valer imediatamente para usuários já conectados.
if (isset($_SESSION['user']['id'])) {
    $sessionUserQuery = db()->prepare('SELECT id,`user`,nome,tipo_user FROM usuario WHERE id=? LIMIT 1');
    $sessionUserQuery->execute([(int)$_SESSION['user']['id']]);
    $sessionUser = $sessionUserQuery->fetch();
    if ($sessionUser) $_SESSION['user'] = $sessionUser;
    else unset($_SESSION['user']);
}

function e(string|int|float|null $text): string
{
    return htmlspecialchars((string) ($text ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function text_length(string $value): int
{
    // A instalação portátil não exige mbstring, mas aproveita a extensão quando disponível.
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function valid_cpf(string $value): bool
{
    $cpf = digits($value);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    // Cada volta calcula um dos dois dígitos verificadores do CPF.
    for ($position = 9; $position <= 10; $position++) {
        $sum = 0;
        for ($i = 0; $i < $position; $i++) $sum += (int)$cpf[$i] * (($position + 1) - $i);
        $digit = (10 * $sum) % 11;
        if ($digit === 10) $digit = 0;
        if ($digit !== (int)$cpf[$position]) return false;
    }
    return true;
}
function logged(): bool { return isset($_SESSION['user']); }
function internal(): bool { return ($_SESSION['user']['tipo_user'] ?? '') === 'admin'; }
function reviewer(): bool { return in_array($_SESSION['user']['tipo_user'] ?? '', ['admin','organizador'], true); }
function guest(): bool { return ($_SESSION['user']['tipo_user'] ?? '') === 'convidado'; }
function coordinates_to_place(string $coordinates): string
{
    if (!preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $coordinates, $m)) return $coordinates;
    // Evita repetir a consulta externa quando as mesmas coordenadas aparecem na sessão.
    $key = 'geo_'.md5($coordinates);
    if (isset($_SESSION[$key])) return $_SESSION[$key];
    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='.rawurlencode($m[1]).'&lon='.rawurlencode($m[2]);
    $context = stream_context_create(['http'=>['timeout'=>3,'header'=>"User-Agent: SEMEJ-Corridas/1.0\r\nAccept-Language: pt-BR\r\n"]]);
    $json = @file_get_contents($url, false, $context); $data = $json ? json_decode($json, true) : null;
    return $_SESSION[$key] = ($data['display_name'] ?? $coordinates);
}
function require_login(): void { if (!logged()) { header('Location: login.php'); exit; } }
function require_admin(): void { require_login(); if (!internal()) { http_response_code(403); exit('Acesso exclusivo do administrador.'); } }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Sessão expirada. Volte e tente novamente.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function pull_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function new_protocol(): string { return 'COR-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3))); }
function valid_route(array $points): bool
{
    if (count($points) < 2 || count($points) > 10000) return false;
    foreach ($points as $point) {
        if (!is_array($point) || count($point) !== 2 || !is_numeric($point[0]) || !is_numeric($point[1])) return false;
        $lat = (float) $point[0];
        $lon = (float) $point[1];
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return false;
    }
    return true;
}
function route_json_for_html(?string $routeJson): string
{
    $points = json_decode($routeJson ?? '', true);
    if (!is_array($points) || !valid_route($points)) return 'null';
    return json_encode($points, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}
function route_distance_km(string $routeJson): float
{
    $points = json_decode($routeJson, true);
    if (!is_array($points) || !valid_route($points)) return 0.0;

    $total = 0.0;
    // Haversine considera a curvatura da Terra. Somamos cada pequeno trecho
    // para chegar à distância aproximada do percurso completo.
    for ($i = 1, $count = count($points); $i < $count; $i++) {
        [$lat1, $lon1] = $points[$i - 1];
        [$lat2, $lon2] = $points[$i];
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);
        $haversine = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLon / 2) ** 2;
        $total += 6371 * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }

    return round($total, 2);
}
