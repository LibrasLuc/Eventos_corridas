<?php
require_once 'config.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT id,protocolo,nome_evento,dia_ini,DATEDIFF(dia_ini,CURDATE()) dias
        FROM corrida
        WHERE status <> 'rejeitada'
          AND (alvara_enviado_em IS NULL OR alvara_status='indeferido')
          AND dia_ini BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY)";
$parameters = [];

if (guest()) {
    $sql .= ' AND usuario_id=?';
    $parameters[] = $_SESSION['user']['id'];
}

$protocolId = (int) ($_GET['id'] ?? 0);
if ($protocolId) {
    $sql .= ' AND id=?';
    $parameters[] = $protocolId;
}

$sql .= ' ORDER BY dia_ini';
$query = db()->prepare($sql);
$query->execute($parameters);
echo json_encode($query->fetchAll(), JSON_UNESCAPED_UNICODE);
