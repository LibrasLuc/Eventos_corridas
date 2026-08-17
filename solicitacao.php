<?php
require_once 'partials.php';
require_login();
$id=(int)($_GET['id']??$_POST['id']??0);
$query=db()->prepare('SELECT c.*,u.`user` solicitante FROM corrida c LEFT JOIN usuario u ON u.id=c.usuario_id WHERE c.id=?');
$query->execute([$id]);
$ev=$query->fetch();
if(!$ev||(guest()&&(int)$ev['usuario_id']!==(int)$_SESSION['user']['id'])){http_response_code(404);exit('Solicitação não encontrada.');}

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='delete'){
    require_admin();check_csrf();$protocol=$ev['protocolo']??('#'.$id);
    db()->prepare('DELETE FROM corrida WHERE id=?')->execute([$id]);
    flash('success','Protocolo '.$protocol.' excluído definitivamente.');header('Location:index.php?aba=geral');exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'&&reviewer()){
    check_csrf();$status=$_POST['status']??'';$allowed=['em_analise','alteracao_solicitada','aprovada','rejeitada'];$message=trim($_POST['mensagem']??'');
    if(in_array($status,$allowed,true)){
        if($status==='alteracao_solicitada'&&$message==='')flash('error','Informe quais alterações são necessárias.');
        else{
            db()->prepare('UPDATE corrida SET status=?,retorno=? WHERE id=?')->execute([$status,$message?:null,$id]);
            db()->prepare('INSERT INTO solicitacao_historico(corrida_id,usuario_id,status,mensagem) VALUES(?,?,?,?)')->execute([$id,$_SESSION['user']['id'],$status,$message?:null]);
            flash('success','Solicitação atualizada.');
        }
        header('Location:solicitacao.php?id='.$id);exit;
    }
}

$historyQuery=db()->prepare('SELECT h.*,u.`user` autor FROM solicitacao_historico h LEFT JOIN usuario u ON u.id=h.usuario_id WHERE h.corrida_id=? ORDER BY h.criado_em DESC,h.id DESC');
$historyQuery->execute([$id]);$history=$historyQuery->fetchAll();
$documentReady=!empty($ev['alvara_enviado_em'])&&$ev['alvara_status']!=='indeferido';
$isApproved=$ev['status']==='aprovada';
$progressStep=$isApproved?4:($documentReady?3:2);
page_top('Andamento');
?>
<section class="page-title"><div><span class="eyebrow">PROTOCOLO <?=e($ev['protocolo']??('#'.$id))?></span><h1><?=e($ev['nome_evento'])?></h1><p><span class="badge <?=e($ev['status'])?>"><?=e(str_replace('_',' ',$ev['status']))?></span> · <?=e($ev['solicitante']??$ev['organizador'])?></p></div><div class="page-actions"><a class="btn secondary" href="index.php">← Voltar</a><a class="btn secondary" href="novo-evento.php?id=<?=$id?>">Editar dados e rota</a><?php if(internal()):?><form method="post" onsubmit="return confirm('Excluir definitivamente este protocolo e todo o histórico?')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="delete"><button class="btn danger">Excluir protocolo</button></form><?php endif;?></div></section>

<ol class="race-progress" aria-label="Andamento da corrida"><li class="done"><i>1</i><span>Criação</span></li><li class="<?=$progressStep>=2?'done':''?>"><i>2</i><span>Validação e análise</span></li><li class="<?=$progressStep>=3?'done':''?>"><i>3</i><span>Documentos necessários</span></li><li class="<?=$isApproved?'done':''?>"><i>4</i><span>Conclusão</span></li></ol>

<div class="detail-grid"><section class="card event-detail"><h2>Dados da corrida</h2><p><?=nl2br(e($ev['desc_corrida']))?></p><p><b>Período:</b> <?=date('d/m/Y',strtotime($ev['dia_ini']))?> a <?=date('d/m/Y',strtotime($ev['dia_fin']))?></p><?php if($ev['hora_ini']||$ev['hora_fin']):?><p><b>Horário:</b> <?=e(substr($ev['hora_ini']??'',0,5))?> a <?=e(substr($ev['hora_fin']??'',0,5))?></p><?php endif;?><p><b>Categoria:</b> <?=e($ev['categoria'])?></p><a class="btn secondary" href="visualizar-corrida.php?id=<?=$id?>">Visualizar trajeto</a></section>
<?php if(reviewer()):?><section class="card form-section"><h2>Avaliação</h2><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$id?>"><label>Situação<select name="status"><option value="em_analise">Em análise</option><option value="aprovada">Aprovar</option><option value="alteracao_solicitada">Devolver para alterações</option><option value="rejeitada">Rejeitar</option></select></label><label>Mensagem ao convidado<textarea name="mensagem" rows="5" placeholder="Explique a decisão ou as mudanças necessárias"></textarea></label><button class="btn primary">Enviar retorno</button></form></section><?php endif;?></div>

<section class="section-head"><div><span class="eyebrow">HISTÓRICO</span><h2>Andamento</h2></div></section><div class="timeline"><?php foreach($history as $item):?><article><b><?=e(str_replace('_',' ',$item['status']))?></b><small><?=date('d/m/Y H:i',strtotime($item['criado_em']))?> · <?=e($item['autor']??'Sistema')?></small><?php if($item['mensagem']):?><p><?=nl2br(e($item['mensagem']))?></p><?php endif;?></article><?php endforeach;?></div>
<?php page_bottom();?>
