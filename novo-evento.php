<?php
require_once 'partials.php';
require_login();

$error = '';
$editId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$event = null;
if ($editId) {
    $query = db()->prepare('SELECT * FROM corrida WHERE id=?');
    $query->execute([$editId]);
    $event = $query->fetch();
    // Convidados só podem alterar pedidos próprios ainda não concluídos. A equipe
    // interna mantém acesso para corrigir solicitações durante a análise.
    $isOwner = $event && (int)$event['usuario_id'] === (int)$_SESSION['user']['id'];
    $canEdit = $event && (internal() || ($isOwner && $event['status'] !== 'aprovada'));
    if (!$canEdit) {
        http_response_code(403);
        exit($event && $event['status'] === 'aprovada'
            ? 'Após a conclusão, somente o Super Admin pode editar a corrida.'
            : 'Você só pode editar as suas próprias corridas.');
    }
} elseif (!guest()) { http_response_code(403); exit('Use o cadastro interno para criar uma corrida.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $route = json_decode($_POST['trajeto_json'] ?? '', true);
    $start = $_POST['dia_ini'] ?? '';
    $end = $_POST['dia_fin'] ?? '';
    $startTime = $_POST['hora_ini'] ?? '';
    $endTime = $_POST['hora_fin'] ?? '';
    $coordinate = '/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/';

    if (!$start || !$end || $end < $start) $error = 'Informe um período válido.';
    elseif (!$startTime || !$endTime || ($start===$end && $endTime<=$startTime)) $error = 'Informe horários de início e término válidos.';
    elseif (!preg_match($coordinate, $_POST['local_ini'] ?? '') || !preg_match($coordinate, $_POST['local_fin'] ?? '')) $error = 'Marque largada e chegada no mapa.';
    elseif (!is_array($route) || !valid_route($route)) $error = 'O trajeto informado é inválido.';
    else {
        // Dois períodos se cruzam quando um começa antes do término do outro.
        // O próprio registro é ignorado para que a edição não conflite consigo mesma.
        $conflict = db()->prepare("SELECT nome_evento FROM corrida WHERE dia_ini<=? AND dia_fin>=? AND status='aprovada' AND id<>? LIMIT 1");
        $conflict->execute([$end, $start, $editId]);
        $occupied = $conflict->fetch();
        $belowNinetyDays = $start < date('Y-m-d', strtotime('+90 days'));
        $reasons = [];
        if ($occupied) $reasons[] = 'Já existe uma corrida aprovada neste período: '.$occupied['nome_evento'].'.';
        if ($belowNinetyDays) $reasons[] = 'A solicitação deverá ser feita com 90 dias de antecedência.';
        $status = $reasons ? 'rejeitada' : 'enviada';
        $reason = $reasons ? implode(' ', $reasons) : null;
        $protocol = $event['protocolo'] ?? new_protocol();
        $routeJson=json_encode($route);$distance=route_distance_km($routeJson);
        $values = [$protocol, trim($_POST['nome_evento']), trim($_POST['local_ini']), trim($_POST['local_fin']), trim($_POST['categoria']), trim($_POST['desc_corrida']), trim($_POST['organizador']), $routeJson, $distance, $start, $end, $startTime, $endTime, $status, $reason];

        if ($editId) {
            // Uma edição comum não deve reabrir silenciosamente uma decisão já tomada.
            // Só pedidos devolvidos para alteração voltam a passar pela regra automática.
            if ($event['status'] !== 'alteracao_solicitada') { $values[count($values)-2]=$event['status']; $values[count($values)-1]=$event['retorno']; }
            $values[] = $editId;
            $editCondition = '';
            if (!internal()) {
                $editCondition = " AND usuario_id=? AND status<>'aprovada'";
                $values[] = $_SESSION['user']['id'];
            }
            $update = db()->prepare('UPDATE corrida SET protocolo=?,nome_evento=?,local_ini=?,local_fin=?,categoria=?,desc_corrida=?,organizador=?,trajeto_json=?,percurso_km=?,dia_ini=?,dia_fin=?,hora_ini=?,hora_fin=?,status=?,retorno=? WHERE id=?'.$editCondition);
            $update->execute($values);
            if ($update->rowCount() !== 1) {
                $stillEditable = db()->prepare('SELECT 1 FROM corrida WHERE id=?'.$editCondition);
                $checkValues = [$editId];
                if (!internal()) $checkValues[] = $_SESSION['user']['id'];
                $stillEditable->execute($checkValues);
                if (!$stillEditable->fetchColumn()) { http_response_code(409); exit('A corrida foi concluída ou não pertence mais a você e não pode ser editada.'); }
            }
            $status=$values[13];
            $id = $editId;
        } else {
            $values[] = $_SESSION['user']['id'];
            db()->prepare('INSERT INTO corrida(protocolo,nome_evento,local_ini,local_fin,categoria,desc_corrida,organizador,trajeto_json,percurso_km,dia_ini,dia_fin,hora_ini,hora_fin,status,retorno,usuario_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int) db()->lastInsertId();
        }
        $message = $editId ? 'Dados e trajeto da corrida atualizados.' : ($reason ?: 'Solicitação enviada para avaliação.');
        db()->prepare('INSERT INTO solicitacao_historico(corrida_id,usuario_id,status,mensagem) VALUES(?,?,?,?)')->execute([$id,$_SESSION['user']['id'],$status,$message]);
        $flashError=!$editId&&!empty($reasons);
        flash($flashError?'error':'success',($editId?'Corrida atualizada com sucesso.':($reasons?'Solicitação registrada e encaminhada para Negadas; a equipe pode aprovar como exceção.':'Solicitação enviada para análise.')).' Protocolo: '.$protocol);
        header('Location: solicitacao.php?id='.$id); exit;
    }
}

$value = fn(string $key) => e($_POST[$key] ?? $event[$key] ?? '');
$organizerDefault = trim((string)($_SESSION['user']['nome'] ?? ''));
if ($organizerDefault === '') {
    $nameQuery = db()->prepare('SELECT nome FROM usuario WHERE id=?');
    $nameQuery->execute([$_SESSION['user']['id']]);
    $organizerDefault = trim((string)$nameQuery->fetchColumn());
}
if ($organizerDefault === '') $organizerDefault = (string)$_SESSION['user']['user'];
page_top($editId ? 'Editar solicitação' : 'Cadastrar corrida');
?>
<section class="page-title"><div><span class="eyebrow">SOLICITAÇÃO</span><h1><?= $editId?'Editar e reenviar':'Nova corrida' ?></h1><p>Todas as datas futuras podem ser solicitadas. As regras serão verificadas após o envio.</p></div></section>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$editId?>"><input type="hidden" id="routeJson" name="trajeto_json" value='<?=e($_POST['trajeto_json']??$event['trajeto_json']??'')?>'>
<section class="card form-section"><div class="section-number">01</div><h2>Dados</h2><div class="fields"><label class="wide">Nome do evento<input name="nome_evento" required value="<?=$value('nome_evento')?>"></label><label>Categoria<input name="categoria" required value="<?=$value('categoria')?>" placeholder="Ex.: 5 km, 10 km, meia maratona, corrida infantil"></label><label>Organizador<input name="organizador" required value="<?=$value('organizador')?:e($organizerDefault)?>"></label><label>Dia inicial<input type="date" id="diaIni" name="dia_ini" min="<?=date('Y-m-d')?>" required value="<?=$value('dia_ini')?>"></label><label>Dia final<input type="date" id="diaFin" name="dia_fin" min="<?=date('Y-m-d')?>" required value="<?=$value('dia_fin')?>"></label><label>Hora de início<input type="time" name="hora_ini" required value="<?=$value('hora_ini')?>"></label><label>Hora de término<input type="time" name="hora_fin" required value="<?=$value('hora_fin')?>"></label><div class="availability wide">A solicitação deverá ser feita com 90 dias de antecedência.</div><label class="wide">Descrição<textarea name="desc_corrida" rows="4" required><?=$value('desc_corrida')?></textarea></label></div></section>
<section class="card form-section"><div class="section-number">02</div><h2>Trajeto em Divinópolis</h2><p class="map-help">Marque largada, pontos de passagem e chegada.</p><div id="routePicker" class="map" data-start="<?=$value('local_ini')?>" data-end="<?=$value('local_fin')?>" data-route='<?=e($_POST['trajeto_json']??$event['trajeto_json']??'')?>'></div><div id="routeStatus" class="availability">Aguardando os pontos.</div><div class="fields"><label>Largada<input id="localIni" name="local_ini" readonly required value="<?=$value('local_ini')?>"></label><label>Chegada<input id="localFin" name="local_fin" readonly required value="<?=$value('local_fin')?>"></label><div class="wide"><button type="button" class="btn secondary" id="resetRoute">Refazer marcações</button></div></div></section>
<aside class="submit-bar"><p><b>Enviar para avaliação</b><br><span>Um protocolo único será gerado.</span></p><button class="btn primary">Enviar solicitação →</button></aside></form>
<script>window.EDIT_REQUEST_ID=<?=$editId?>;</script><?php page_bottom();?>
