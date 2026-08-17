<?php
require_once 'partials.php';
require_login();
reject_expired_open_protocols();

$allowedTabs = ['geral', 'pendentes', 'aprovadas', 'negadas'];
$tab = $_GET['aba'] ?? 'geral';
if (!in_array($tab, $allowedTabs, true)) $tab = 'geral';
$search = trim($_GET['q'] ?? '');

$statusFilter = match ($tab) {
    'pendentes' => "c.status IN ('enviada','em_analise','alteracao_solicitada')",
    'aprovadas' => "c.status='aprovada'",
    'negadas' => "c.status='rejeitada'",
    default => '1=1',
};

$conditions = [$statusFilter];
$parameters = [];
if (guest()) {
    $conditions[] = 'c.usuario_id=?';
    $parameters[] = $_SESSION['user']['id'];
}
if ($search !== '') {
    $conditions[] = "(c.protocolo LIKE ? OR c.nome_evento LIKE ? OR c.organizador LIKE ? OR u.`user` LIKE ?)";
    $term = '%'.$search.'%';
    array_push($parameters, $term, $term, $term, $term);
}

$sql = "SELECT c.*,u.`user` solicitante FROM corrida c LEFT JOIN usuario u ON u.id=c.usuario_id WHERE ".implode(' AND ', $conditions)." ORDER BY c.id DESC";
$query = db()->prepare($sql);
$query->execute($parameters);
$events = $query->fetchAll();

$countSql = guest()
    ? 'SELECT status,COUNT(*) total FROM corrida WHERE usuario_id=? GROUP BY status'
    : 'SELECT status,COUNT(*) total FROM corrida GROUP BY status';
$countQuery = db()->prepare($countSql);
$countQuery->execute(guest() ? [$_SESSION['user']['id']] : []);
$counts = [];
foreach ($countQuery as $row) $counts[$row['status']] = (int) $row['total'];
$pending = ($counts['enviada'] ?? 0) + ($counts['em_analise'] ?? 0) + ($counts['alteracao_solicitada'] ?? 0);
$total = array_sum($counts);

function tab_url(string $tab, string $search): string {
    return '?'.http_build_query(['aba'=>$tab,'q'=>$search]);
}

page_top('Solicitações');
?>
<section class="hero dashboard-hero">
    <div><span class="eyebrow"><?=guest()?'ACOMPANHAMENTO':'CENTRAL DE EVENTOS'?></span><h1><?=guest()?'Minhas solicitações':'Corridas e solicitações'?></h1><p>Pesquise pelo protocolo, acompanhe situações e encontre rapidamente cada evento.</p></div>
    <a class="btn primary" href="<?=guest()?'novo-evento.php':'nova-corrida-organizador.php'?>">+ Nova corrida</a>
</section>

<section class="summary-cards">
    <article><span>Total</span><strong><?=$total?></strong></article>
    <article><span>Pendentes</span><strong><?=$pending?></strong></article>
    <article><span>Aprovadas</span><strong><?=$counts['aprovada']??0?></strong></article>
    <article><span>Negadas</span><strong><?=$counts['rejeitada']??0?></strong></article>
</section>

<section class="search-panel card">
    <form method="get"><input type="hidden" name="aba" value="<?=$tab?>"><div class="search-field"><span>⌕</span><input name="q" value="<?=e($search)?>" placeholder="Pesquisar protocolo, evento, solicitante ou organizador"><button class="btn dark">Pesquisar</button><?php if($search!==''):?><a class="btn secondary" href="?aba=<?=$tab?>">Limpar</a><?php endif;?></div></form>
</section>

<nav class="request-tabs">
    <a class="<?=$tab==='geral'?'active':''?>" href="<?=tab_url('geral',$search)?>">Geral <b><?=$total?></b></a>
    <a class="<?=$tab==='pendentes'?'active':''?>" href="<?=tab_url('pendentes',$search)?>">Pendentes <b><?=$pending?></b></a>
    <a class="<?=$tab==='aprovadas'?'active':''?>" href="<?=tab_url('aprovadas',$search)?>">Aprovadas <b><?=$counts['aprovada']??0?></b></a>
    <a class="<?=$tab==='negadas'?'active':''?>" href="<?=tab_url('negadas',$search)?>">Negadas <b><?=$counts['rejeitada']??0?></b></a>
</nav>

<?php if($search!==''):?><p class="result-label"><b><?=count($events)?></b> resultado(s) para “<?=e($search)?>”</p><?php endif;?>
<div class="request-grid">
<?php foreach($events as $event):?>
    <article class="card request-card">
        <div class="request-main">
            <div class="request-meta"><span class="protocol"><?=e($event['protocolo']??'Sem protocolo')?></span><span class="badge <?=e($event['status'])?>"><?=e(str_replace('_',' ',$event['status']))?></span></div>
            <h3><?=e($event['nome_evento'])?></h3>
            <p class="event-data"><span>📅 <?=!empty($event['dia_ini'])?date('d/m/Y',strtotime($event['dia_ini'])).' — '.date('d/m/Y',strtotime($event['dia_fin'])):'Não informado'?></span><span>🏃 <?=e($event['categoria'])?></span><span>👤 <?=e($event['solicitante']??$event['organizador'])?></span></p>
            <?php if($event['retorno']):?><div class="review-note"><b>Último retorno</b><br><?=nl2br(e($event['retorno']))?></div><?php endif;?>
        </div>
        <div class="request-actions"><a class="btn secondary" href="solicitacao.php?id=<?=$event['id']?>">Abrir detalhes →</a><?php if(guest()&&$event['status']==='alteracao_solicitada'):?><a class="btn primary" href="novo-evento.php?id=<?=$event['id']?>">Editar</a><?php endif;?></div>
    </article>
<?php endforeach;?>
<?php if(!$events):?><section class="card empty-state"><div>⌕</div><h3>Nenhum resultado encontrado</h3><p>Tente pesquisar outro protocolo ou consulte uma aba diferente.</p><?php if($search!==''):?><a class="btn secondary" href="?aba=<?=$tab?>">Limpar pesquisa</a><?php endif;?></section><?php endif;?>
</div>
<?php page_bottom();?>
