<?php
require_once 'partials.php';
if (logged()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $login = trim($_POST['user'] ?? '');
    $cpf = digits($login);
    $password = $_POST['senha'] ?? '';
    $stmt = db()->prepare('SELECT id,`user`,nome,senha_crip,tipo_user FROM usuario WHERE `user`=? OR nome=? OR cpf=?');
    $stmt->execute([$login, $login, strlen($cpf) === 11 ? $cpf : '']);
    $u = false;
    foreach ($stmt->fetchAll() as $candidate) {
        $passwordOk = password_verify($password, $candidate['senha_crip']) ||
            (str_starts_with($candidate['senha_crip'], '{SHA256}') && hash_equals(substr($candidate['senha_crip'], 8), hash('sha256', $password)));
        if ($passwordOk) {
            $u = $candidate;
            break;
        }
    }
    if ($u) {
        if (str_starts_with($u['senha_crip'], '{SHA256}')) {
            db()->prepare('UPDATE usuario SET senha_crip=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        unset($u['senha_crip']);
        session_regenerate_id(true);
        $_SESSION['user']=$u;
        $_SESSION['show_login_loader']=true;
        header('Location: index.php'); exit;
    }
    $error='Usuário ou senha inválidos.';
}
page_top('Entrar'); ?>
<section class="login-layout"><div class="login-race-ambient" aria-hidden="true"><svg viewBox="0 0 1600 620" preserveAspectRatio="none"><path id="ambientRaceRoute" class="ambient-track-progress" d="M265 510 C225 372 300 245 505 142 C705 42 965 35 1155 128 C1335 216 1340 376 1515 474 C1360 566 1090 548 835 586"/><circle class="ambient-point start" cx="265" cy="510" r="8"/><circle class="ambient-point finish" cx="835" cy="586" r="8"/><g class="ambient-runner"><circle r="10"><animate attributeName="r" values="9;12;9" dur=".7s" repeatCount="indefinite"/></circle><animateMotion dur="14s" repeatCount="indefinite" calcMode="spline" keyTimes="0;.22;.48;.74;1" keyPoints="0;.22;.48;.74;1" keySplines=".42 0 .58 1;.35 0 .65 1;.42 0 .58 1;.35 0 .65 1"><mpath href="#ambientRaceRoute"/></animateMotion></g></svg></div><div class="login-intro"><span class="eyebrow">SERVIÇO DIGITAL</span><h1>Corridas que movem<br><em>a nossa cidade.</em></h1><p>Solicite datas, acompanhe a análise e consulte o calendário municipal de eventos esportivos.</p><div class="feature-row"><span>✓ Processo transparente</span><span>✓ Agenda sem conflitos</span></div></div>
<form class="card login-card" method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><span class="eyebrow">ACESSO AO PORTAL</span><h2>Boas-vindas</h2><p class="muted">Entre com suas credenciais para continuar.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><label>Nome, CPF ou usuário<input name="user" required autocomplete="username" placeholder="Digite seu nome, CPF ou usuário"></label><label>Senha<input type="password" name="senha" required autocomplete="current-password" placeholder="Sua senha"></label><button class="btn primary" type="submit">Entrar no portal →</button><a class="btn secondary guest-entry" href="cadastro-convidado.php">Criar acesso como convidado</a></form></section>
<?php page_bottom(); ?>
