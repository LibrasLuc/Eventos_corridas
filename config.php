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
    }
    return $pdo;
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function e(?string $text): string { return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8'); }
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
function route_distance_km(string $routeJson): float {
    $points=json_decode($routeJson,true); if(!is_array($points)||count($points)<2)return 0.0;$total=0.0;
    for($i=1;$i<count($points);$i++){[$lat1,$lon1]=$points[$i-1];[$lat2,$lon2]=$points[$i];$dLat=deg2rad($lat2-$lat1);$dLon=deg2rad($lon2-$lon1);$a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;$total+=6371*2*atan2(sqrt($a),sqrt(1-$a));}
    return (float) round($total);
}
