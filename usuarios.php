<?php
require_once 'partials.php';
require_admin();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    try{
        if($action==='create'){
            $username=trim($_POST['user']??'');
            $password=$_POST['senha']??'';
            $role=in_array($_POST['tipo_user']??'', ['admin','organizador','convidado'], true)?$_POST['tipo_user']:'convidado';
            if(!preg_match('/^[A-Za-z0-9._-]{3,120}$/',$username))throw new RuntimeException('Use ao menos 3 caracteres válidos no usuário.');
            if(strlen($password)<8)throw new RuntimeException('A senha deve ter no mínimo 8 caracteres.');
            $email=trim($_POST['email']??'');
            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Informe um e-mail válido.');
            $cpf=digits($_POST['cpf']??'');
            if($cpf!==''&&!valid_cpf($cpf))throw new RuntimeException('Informe um CPF válido ou deixe o campo vazio.');
            db()->prepare('INSERT INTO usuario(`user`,nome,telefone,endereco,email,cpf,senha_crip,tipo_user) VALUES(?,?,?,?,?,?,?,?)')->execute([$username,trim($_POST['nome']??''),trim($_POST['telefone']??''),trim($_POST['endereco']??''),$email,$cpf?:null,password_hash($password,PASSWORD_DEFAULT),$role]);
            flash('success','Usuário criado com o poder selecionado.');
        }elseif($action==='password'){
            $password=$_POST['senha']??'';
            if(strlen($password)<8)throw new RuntimeException('A nova senha deve ter no mínimo 8 caracteres.');
            db()->prepare('UPDATE usuario SET senha_crip=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            flash('success','Senha atualizada.');
        }elseif($action==='role'){
            if($id===(int)$_SESSION['user']['id'])throw new RuntimeException('Você não pode alterar o poder da própria conta.');
            $role=in_array($_POST['tipo_user']??'', ['admin','organizador','convidado'], true)?$_POST['tipo_user']:'convidado';
            db()->prepare('UPDATE usuario SET tipo_user=? WHERE id=?')->execute([$role,$id]);
            flash('success','Poder alterado para '.ucfirst($role).'.');
        }elseif($action==='delete'){
            if($id===(int)$_SESSION['user']['id'])throw new RuntimeException('Você não pode apagar a própria conta.');
            $query=db()->prepare('SELECT tipo_user FROM usuario WHERE id=?');$query->execute([$id]);$target=$query->fetch();
            if(!$target)throw new RuntimeException('Usuário não encontrado.');
            if($target['tipo_user']==='admin')throw new RuntimeException('Rebaixe o Super Admin antes de apagá-lo.');
            db()->prepare('DELETE FROM usuario WHERE id=?')->execute([$id]);
            flash('success','Usuário apagado.');
        }
        header('Location: usuarios.php');exit;
    }catch(PDOException $exception){$error=$exception->getCode()==='23000'?'Esse nome de usuário já existe.':'Não foi possível realizar a operação.';}
    catch(RuntimeException $exception){$error=$exception->getMessage();}
}

$users=db()->query('SELECT id,`user`,nome,telefone,endereco,email,cpf,tipo_user FROM usuario ORDER BY FIELD(tipo_user,\'admin\',\'organizador\',\'convidado\'),`user`')->fetchAll();
page_top('Usuários');
?>
<section class="page-title"><div><span class="eyebrow">SUPER ADMIN</span><h1>Usuários e poderes</h1><p>Convidados veem apenas suas corridas. Organizador e Super Admin veem todas.</p></div></section>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<section class="card form-section"><div class="section-number">+</div><h2>Criar usuário</h2><form method="post" class="admin-form admin-user-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="create"><label>Usuário *<input name="user" required minlength="3" placeholder="CPF ou nome de usuário"></label><label>Senha inicial *<input type="password" name="senha" required minlength="8" placeholder="Mínimo 8 caracteres"></label><label>Nome<input name="nome" placeholder="Opcional"></label><label>CPF<input name="cpf" class="cpf-input" inputmode="numeric" maxlength="14" placeholder="Opcional"></label><label>Telefone<input name="telefone" type="tel" placeholder="Opcional"></label><label>E-mail<input name="email" type="email" placeholder="Opcional"></label><label class="wide">Endereço<input name="endereco" placeholder="Opcional"></label><label>Poder<select name="tipo_user"><option value="convidado">Convidado</option><option value="organizador">Organizador</option><option value="admin">Super Admin</option></select></label><button class="btn primary">Criar usuário</button></form><p class="muted optional-note">Somente usuário e senha são obrigatórios.</p></section>
<div class="user-list">
<?php foreach($users as $user):$roleLabel=match($user['tipo_user']){'admin'=>'Super Admin','organizador'=>'Organizador',default=>'Convidado'};?>
<article class="card user-row"><div><span class="badge <?=$user['tipo_user']==='admin'?'aprovado':''?>"><?=e($roleLabel)?></span><h3><?=e($user['nome']?:$user['user'])?></h3><small><?=e($user['user'])?><?= $user['email']?' · '.e($user['email']):'' ?></small><?php if($user['id']===$_SESSION['user']['id']):?><small>Sua conta</small><?php endif;?></div><div class="user-actions">
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?=$user['id']?>"><input type="password" name="senha" minlength="8" required placeholder="Nova senha"><button class="btn dark">Mudar senha</button></form>
<?php if($user['id']!==$_SESSION['user']['id']):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?=$user['id']?>"><select name="tipo_user"><option value="convidado" <?=$user['tipo_user']==='convidado'?'selected':''?>>Convidado</option><option value="organizador" <?=$user['tipo_user']==='organizador'?'selected':''?>>Organizador</option><option value="admin" <?=$user['tipo_user']==='admin'?'selected':''?>>Super Admin</option></select><button class="btn secondary">Alterar poder</button></form><?php if($user['tipo_user']!=='admin'):?><form method="post" onsubmit="return confirm('Apagar este usuário definitivamente?')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$user['id']?>"><button class="btn danger">Apagar</button></form><?php endif;?><?php endif;?>
</div></article><?php endforeach;?></div><?php page_bottom();?>
