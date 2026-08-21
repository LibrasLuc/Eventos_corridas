<?php
require_once 'partials.php';
if (logged()) { header('Location:index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // Normalizar uma vez deixa a validação e a gravação usando exatamente os mesmos dados.
    $cpf = digits($_POST['cpf'] ?? '');
    $password = $_POST['senha'] ?? '';
    $confirmation = $_POST['confirmar_senha'] ?? '';
    $name = trim($_POST['nome'] ?? '');
    $phone = trim($_POST['telefone'] ?? '');
    $address = trim($_POST['endereco'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    try {
        if (!valid_cpf($cpf)) throw new RuntimeException('Informe um CPF válido.');
        if ($name === '' || text_length($name) > 180) throw new RuntimeException('Informe um nome com até 180 caracteres.');
        if ($phone === '' || text_length($phone) > 30) throw new RuntimeException('Informe um telefone válido.');
        if ($address === '' || text_length($address) > 255) throw new RuntimeException('Informe um endereço com até 255 caracteres.');
        if (text_length($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido.');
        if (strlen($password) < 8) throw new RuntimeException('A senha deve ter no mínimo 8 caracteres.');
        if ($password !== $confirmation) throw new RuntimeException('As senhas informadas não são iguais.');

        $query = db()->prepare("INSERT INTO usuario(`user`,nome,telefone,endereco,email,cpf,senha_crip,tipo_user) VALUES(?,?,?,?,?,?,?,'convidado')");
        $query->execute([$cpf, $name, $phone, $address, $email, $cpf, password_hash($password, PASSWORD_DEFAULT)]);

        // Trocar o ID impede que uma sessão criada antes do login seja reutilizada.
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) db()->lastInsertId(), 'user' => $cpf, 'nome' => $name, 'tipo_user' => 'convidado'];
        $_SESSION['show_login_loader'] = true;
        header('Location:index.php'); exit;
    } catch (PDOException $exception) {
        $error = $exception->getCode() === '23000' ? 'Já existe uma conta cadastrada com este CPF.' : 'Não foi possível criar a conta.';
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}
page_top('Cadastro de convidado');
?>
<section class="login-layout"><div class="login-intro"><span class="eyebrow">SOLICITANTE</span><h1>Solicite sua<br><em>corrida.</em></h1><p>Crie seu acesso para cadastrar e acompanhar o andamento da solicitação. Seu CPF será utilizado para entrar no sistema.</p></div>
<form class="card login-card signup-card" method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><h2>Criar acesso</h2><?php if($error):?><div class="alert error" role="alert"><?=e($error)?></div><?php endif;?>
<div class="fields"><label class="wide">Nome completo<input name="nome" required maxlength="180" value="<?=e($_POST['nome']??'')?>" autocomplete="name"></label><label>CPF (seu usuário)<input name="cpf" class="cpf-input" inputmode="numeric" maxlength="14" required value="<?=e($_POST['cpf']??'')?>" placeholder="000.000.000-00"></label><label>Telefone<input name="telefone" type="tel" required maxlength="30" value="<?=e($_POST['telefone']??'')?>" autocomplete="tel" placeholder="(37) 99999-9999"></label><label class="wide">Endereço<input name="endereco" required maxlength="255" value="<?=e($_POST['endereco']??'')?>" autocomplete="street-address"></label><label class="wide">E-mail<input name="email" type="email" required maxlength="180" value="<?=e($_POST['email']??'')?>" autocomplete="email"></label><label>Senha<input type="password" name="senha" required minlength="8" autocomplete="new-password"></label><label>Confirmar senha<input type="password" name="confirmar_senha" required minlength="8" autocomplete="new-password"></label></div>
<button class="btn primary">Criar e entrar →</button><a class="btn secondary guest-entry" href="login.php">Voltar ao login</a></form></section><?php page_bottom();?>
