<?php
require_once 'partials.php'; require_login();
$query=db()->prepare('SELECT * FROM corrida WHERE id=?');$query->execute([(int)($_GET['id']??0)]);$event=$query->fetch();
if(!$event||guest()&&(int)$event['usuario_id']!==(int)$_SESSION['user']['id']){http_response_code(404);exit('Corrida não encontrada.');}
$distance=(int)round((float)$event['percurso_km']);page_top($event['nome_evento']);?>
<section class="page-title"><div><span class="eyebrow">PROTOCOLO <?=e($event['protocolo'])?></span><h1><?=e($event['nome_evento'])?></h1><p><?=e($event['categoria'])?> · <?=$distance?> km · Organizador: <?=e($event['organizador'])?></p></div><a class="btn secondary" href="index.php">← Voltar</a></section>
<section class="card event-detail"><div class="distance-highlight"><span>Distância total do percurso</span><strong><?=$distance?> km</strong></div><p><?=nl2br(e($event['desc_corrida']))?></p><div id="routeView" class="map large" data-start="<?=e($event['local_ini'])?>" data-end="<?=e($event['local_fin'])?>"></div><script type="application/json" id="savedRoute"><?=route_json_for_html($event['trajeto_json']??null)?></script><div class="route-legend"><span><i class="start"></i><b>Largada</b> <?=e($event['local_ini'])?></span><span><i class="finish"></i><b>Chegada</b> <?=e($event['local_fin'])?></span></div></section><?php page_bottom();?>
