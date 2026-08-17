<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_NAME = 'semej_corridas';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
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
            if (!isset($existing[$column])) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
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

function e(?string $text): string { return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8'); }
function digits(string $value): string { return preg_replace('/\D+/', '', $value); }
function valid_cpf(string $value): bool {
    $cpf = digits($value);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
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
function coordinates_to_place(string $coordinates): string {
    if (!preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $coordinates, $m)) return $coordinates;
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
function valid_route(array $points): bool {
    if (count($points) < 2 || count($points) > 10000) return false;
    foreach ($points as $point) {
        if (!is_array($point) || count($point) !== 2 || !is_numeric($point[0]) || !is_numeric($point[1])) return false;
        $lat = (float) $point[0];
        $lon = (float) $point[1];
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return false;
    }
    return true;
}
function route_json_for_html(?string $routeJson): string {
    $points = json_decode($routeJson ?? '', true);
    if (!is_array($points) || !valid_route($points)) return 'null';
    return json_encode($points, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}
function route_distance_km(string $routeJson): float {
    $points=json_decode($routeJson,true); if(!is_array($points)||!valid_route($points))return 0.0;$total=0.0;
    for($i=1;$i<count($points);$i++){[$lat1,$lon1]=$points[$i-1];[$lat2,$lon2]=$points[$i];$dLat=deg2rad($lat2-$lat1);$dLon=deg2rad($lon2-$lon1);$a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;$total+=6371*2*atan2(sqrt($a),sqrt(1-$a));}
    return (float) round($total);
}
