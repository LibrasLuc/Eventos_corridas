<?php
require_once 'partials.php';
require_login();
if (!guest()) { http_response_code(403); exit('Somente convidados podem enviar solicitaÃ§Ãµes.'); }

$error = '';
$editId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$event = null;
if ($editId) {
    $query = db()->prepare("SELECT * FROM corrida WHERE id=? AND usuario_id=? AND status='alteracao_solicitada'");
    $query->execute([$editId, $_SESSION['user']['id']]);
    $event = $query->fetch();
    if (!$event) { http_response_code(403); exit('Esta solicitaÃ§Ã£o nÃ£o pode ser editada.'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $route = json_decode($_POST['trajeto_json'] ?? '', true);
    $start = $_POST['dia_ini'] ?? '';
    $end = $_POST['dia_fin'] ?? '';
    $coordinate = '/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/';

    if (!$start || !$end || $end < $start) $error = 'Informe um perÃ­odo vÃ¡lido.';
    elseif (!preg_match($coordinate, $_POST['local_ini'] ?? '') || !preg_match($coordinate, $_POST['local_fin'] ?? '')) $error = 'Marque largada e chegada no mapa.';
    elseif (!is_array($route) || count($route) < 2) $error = 'Aguarde o cÃ¡lculo do trajeto.';
    else {
        $conflict = db()->prepare("SELECT nome_evento FROM corrida WHERE dia_ini<=? AND dia_fin>=? AND status='aprovada' AND id<>? LIMIT 1");
        $conflict->execute([$end, $start, $editId]);
        $occupied = $conflict->fetch();
        $withinNinetyDays = $start <= date('Y-m-d', strtotime('+90 days'));
        $reasons = [];
        if ($occupied) $reasons[] = 'JÃ¡ existe uma corrida aprovada neste perÃ­odo: '.$occupied['nome_evento'].'.';
        if ($withinNinetyDays) $reasons[] = 'A corrida foi solicitada com antecedÃªncia de 90 dias ou menos.';
        $status = $reasons ? 'rejeitada' : 'enviada';
        $reason = $reasons ? implode(' ', $reasons) : null;
        $protocol = $event['protocolo'] ?? new_protocol();
        $routeJson=json_encode($route);$distance=route_distance_km($routeJson);
        $values = [$protocol, trim($_POST['nome_evento']), trim($_POST['local_ini']), trim($_POST['local_fin']), trim($_POST['categoria']), trim($_POST['desc_corrida']), trim($_POST['organizador']), $routeJson, $distance, $start, $end, $status, $reason];

        if ($editId) {
            array_push($values, $editId, $_SESSION['user']['id']);
            db()->prepare('UPDATE corrida SET protocolo=?,nome_evento=?,local_ini=?,local_fin=?,categoria=?,desc_corrida=?,organizador=?,trajeto_json=?,percurso_km=?,dia_ini=?,dia_fin=?,status=?,retorno=? WHERE id=? AND usuario_id=?')->execute($values);
            $id = $editId;
        } else {
            $values[] = $_SESSION['user']['id'];
            db()->prepare('INSERT INTO corrida(protocolo,nome_evento,local_ini,local_fin,categoria,desc_corrida,organizador,trajeto_json,percurso_km,dia_ini,dia_fin,status,retorno,usuario_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int) db()->lastInsertId();
        }
        $message = $reason ?: 'SolicitaÃ§Ã£o enviada para avaliaÃ§Ã£o.';
        db()->prepare('INSERT INTO solicitacao_historico(corrida_id,usuario_id,status,mensagem) VALUES(?,?,?,?)')->execute([$id,$_SESSION['user']['id'],$status,$message]);
        flash($reasons?'error':'success',($reasons?'SolicitaÃ§Ã£o automaticamente negada; a equipe pode aprovar como exceÃ§Ã£o.':'SolicitaÃ§Ã£o enviada.').' Protocolo: '.$protocol);
        header('Location: solicitacao.php?id='.$id); exit;
    }
}

$value = fn(string $key) => e($_POST[$key] ?? $event[$key] ?? '');
page_top($editId ? 'Editar solicitaÃ§Ã£o' : 'Cadastrar corrida');
?>
<section class="page-title"><div><span class="eyebrow">SOLICITAÃ‡ÃƒO</span><h1><?= $editId?'Editar e reenviar':'Nova corrida' ?></h1><p>Todas as datas futuras podem ser solicitadas. As regras serÃ£o verificadas apÃ³s o envio.</p></div></section>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$editId?>"><input type="hidden" id="routeJson" name="trajeto_json" value='<?=e($_POST['trajeto_json']??$event['trajeto_json']??'')?>'>
<section class="card form-section"><div class="section-number">01</div><h2>Dados</h2><div class="fields"><label class="wide">Nome do evento<input name="nome_evento" required value="<?=$value('nome_evento')?>"></label><label>Categoria<input name="categoria" required value="<?=$value('categoria')?>"></label><label>Organizador<input name="organizador" required value="<?=$value('organizador')?:e($_SESSION['user']['user'])?>"></label><label>Dia inicial<input type="date" id="diaIni" name="dia_ini" min="<?=date('Y-m-d')?>" required value="<?=$value('dia_ini')?>"></label><label>Dia final<input type="date" id="diaFin" name="dia_fin" min="<?=date('Y-m-d')?>" required value="<?=$value('dia_fin')?>"></label><div class="availability wide">Datas ocupadas ou acima de 90 dias sÃ£o aceitas, mas entram inicialmente como negadas para revisÃ£o interna.</div><label class="wide">DescriÃ§Ã£o<textarea name="desc_corrida" rows="4" required><?=$value('desc_corrida')?></textarea></label></div></section>
<section class="card form-section"><div class="section-number">02</div><h2>Trajeto em DivinÃ³polis</h2><p class="map-help">Marque largada, pontos de passagem e chegada.</p><div id="routePicker" class="map" data-start="<?=$value('local_ini')?>" data-end="<?=$value('local_fin')?>" data-route='<?=e($_POST['trajeto_json']??$event['trajeto_json']??'')?>'></div><div id="routeStatus" class="availability">Aguardando os pontos.</div><div class="fields"><label>Largada<input id="localIni" name="local_ini" readonly required value="<?=$value('local_ini')?>"></label><label>Chegada<input id="localFin" name="local_fin" readonly required value="<?=$value('local_fin')?>"></label><div class="wide"><button type="button" class="btn secondary" id="resetRoute">Refazer marcaÃ§Ãµes</button></div></div></section>
<aside class="submit-bar"><p><b>Enviar para avaliaÃ§Ã£o</b><br><span>Um protocolo Ãºnico serÃ¡ gerado.</span></p><button class="btn primary">Enviar solicitaÃ§Ã£o â†’</button></aside></form>
<script>window.EDIT_REQUEST_ID=<?=$editId?>;</script><?php page_bottom();?>

