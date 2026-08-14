<?php
require_once __DIR__.'/config.php';
function page_top(string $title): void { $user = $_SESSION['user'] ?? null; $flash = pull_flash(); $notice=db()->query('SELECT mensagem FROM aviso WHERE id=1 AND ativo=1')->fetchColumn(); ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> · Agenda Corridas</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/workflow.css"><link rel="stylesheet" href="assets/tabs.css"><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"></head><body>
<header class="topbar"><a class="brand" href="index.php"><span class="brand-mark">S</span><span><b>SEMEJ</b><small>Agenda de Corridas</small></span></a>
<?php if ($user): ?><button class="menu-button" onclick="document.querySelector('.nav').classList.toggle('open')">☰</button><nav class="nav">
<a href="index.php"><?=guest()?'Minhas solicitações':'Solicitações'?></a><?php if(guest()):?><a href="novo-evento.php">Cadastrar corrida</a><?php else:?><a href="nova-corrida-organizador.php">Nova corrida</a><?php endif;?><?php if(internal()):?><a href="usuarios.php">Usuários</a><a href="aviso.php">Aviso</a><?php endif;?>
<span class="user-chip"><?=e($user['user'])?> · <?=internal()?'Super Admin':(guest()?'Convidado':'Organizador')?></span><a href="logout.php">Sair</a></nav><?php endif; ?></header>
<?php if($notice):?><div class="global-notice">⚠ <?=e($notice)?></div><?php endif;?><main class="container"><?php if ($flash): ?><div class="alert <?=$flash[0]?>"><?=e($flash[1])?></div><?php endif; ?>
<?php }
function page_bottom(): void { ?></main><footer>Prefeitura Municipal · Secretaria Municipal de Esporte e Juventude</footer><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script><script src="https://cdn.jsdelivr.net/npm/flatpickr"></script><script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script><script src="assets/app.js"></script><script src="assets/permit.js"></script></body></html><?php }
