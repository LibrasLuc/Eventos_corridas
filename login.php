<?php
require_once 'partials.php';
if (logged()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $stmt = db()->prepare('SELECT id,`user`,senha_crip,tipo_user FROM usuario WHERE `user`=? LIMIT 1');
    $stmt->execute([trim($_POST['user'] ?? '')]); $u = $stmt->fetch();
    $passwordOk = $u && (password_verify($_POST['senha'] ?? '', $u['senha_crip']) ||
        (str_starts_with($u['senha_crip'], '{SHA256}') && hash_equals(substr($u['senha_crip'], 8), hash('sha256', $_POST['senha'] ?? ''))));
    if ($passwordOk) {
        if (str_starts_with($u['senha_crip'], '{SHA256}')) {
            db()->prepare('UPDATE usuario SET senha_crip=? WHERE id=?')->execute([password_hash($_POST['senha'], PASSWORD_DEFAULT), $u['id']]);
        }
        unset($u['senha_crip']); $_SESSION['user']=$u; header('Location: index.php'); exit;
    }
    $error='Usuário ou senha inválidos.';
}
page_top('Entrar'); ?>
<section class="login-layout"><div class="login-intro"><span class="eyebrow">SERVIÇO DIGITAL</span><h1>Corridas que movem<br><em>a nossa cidade.</em></h1><p>Solicite datas, acompanhe a análise e consulte o calendário municipal de eventos esportivos.</p><div class="feature-row"><span>✓ Processo transparente</span><span>✓ Agenda sem conflitos</span></div></div>
<form class="card login-card" method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><span class="eyebrow">ACESSO AO PORTAL</span><h2>Boas-vindas</h2><p class="muted">Entre com suas credenciais para continuar.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><label>Usuário<input name="user" required placeholder="Seu usuário"></label><label>Senha<input type="password" name="senha" required placeholder="Sua senha"></label><button class="btn primary" type="submit">Entrar no portal →</button><a class="btn secondary guest-entry" href="cadastro-convidado.php">Entrar como convidado</a><div class="demo"><b>Acesso da equipe</b><br>Administrador: admin / <code>Admin@123</code><br>Organizador: organizador / <code>Teste@123</code></div></form></section>
<?php page_bottom(); ?>
