<?php
require_once 'config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
check_csrf();

$id=(int)($_POST['id']??0);
$query=db()->prepare('SELECT id,usuario_id,protocolo,status FROM corrida WHERE id=?');
$query->execute([$id]);$event=$query->fetch();
if(!$event||guest()&&(int)$event['usuario_id']!==(int)$_SESSION['user']['id']){http_response_code(403);exit('Sem permissão.');}

$action=$_POST['permit_action']??'';
if($action==='reject'){
    if(!reviewer()){http_response_code(403);exit('Somente a equipe pode indeferir documentos.');}
    $reason=trim($_POST['motivo']??'');
    if($reason===''){flash('error','Informe o motivo do indeferimento.');header('Location:solicitacao.php?id='.$id);exit;}
    db()->prepare("UPDATE corrida SET alvara_status='indeferido',alvara_motivo=?,alvara_enviado_em=NULL WHERE id=?")->execute([$reason,$id]);
    db()->prepare("INSERT INTO solicitacao_historico(corrida_id,usuario_id,status,mensagem) VALUES(?,?,?,?)")->execute([$id,$_SESSION['user']['id'],$event['status'],'Documento indeferido: '.$reason]);
    flash('error','Documento indeferido. O responsável poderá substituir o anexo.');header('Location:solicitacao.php?id='.$id);exit;
}
if($action==='confirm_without_file'){
    db()->prepare("UPDATE corrida SET alvara_arquivo=NULL,alvara_enviado_em=NOW(),alvara_usuario_id=?,alvara_status='confirmado_sem_anexo',alvara_motivo=NULL WHERE id=?")->execute([$_SESSION['user']['id'],$id]);
    db()->prepare("INSERT INTO solicitacao_historico(corrida_id,usuario_id,status,mensagem) VALUES(?,?,?,'Entrega da documentação confirmada sem arquivo anexado.')")->execute([$id,$_SESSION['user']['id'],$event['status']]);
    flash('success','Entrega da documentação confirmada sem anexo.');
    header('Location:solicitacao.php?id='.$id);exit;
}

$file=$_FILES['alvara']??null;
if(!$file||$file['error']!==UPLOAD_ERR_OK)flash('error','Selecione um arquivo PDF válido.');
elseif($file['size']>10485760)flash('error','O PDF deve ter no máximo 10 MB.');
else{
    $extension=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    $handle=fopen($file['tmp_name'],'rb');$signature=$handle?fread($handle,5):'';if($handle)fclose($handle);
    if($extension!=='pdf'||$signature!=='%PDF-')flash('error','O arquivo selecionado não é um PDF válido.');
    else{
        $directory=__DIR__.'/uploads/alvaras';if(!is_dir($directory))mkdir($directory,0775,true);
        $name=preg_replace('/[^A-Za-z0-9_-]/','_',$event['protocolo']).'-'.bin2hex(random_bytes(4)).'.pdf';
        if(move_uploaded_file($file['tmp_name'],$directory.'/'.$name)){
            db()->prepare("UPDATE corrida SET alvara_arquivo=?,alvara_enviado_em=NOW(),alvara_usuario_id=?,alvara_status='enviado',alvara_motivo=NULL WHERE id=?")->execute(['uploads/alvaras/'.$name,$_SESSION['user']['id'],$id]);
            db()->prepare("INSERT INTO solicitacao_historico(corrida_id,usuario_id,status,mensagem) VALUES(?,?,?,'Documento necessário enviado e anexado em PDF.')")->execute([$id,$_SESSION['user']['id'],$event['status']]);
            flash('success','Documento anexado ou substituído e salvo no protocolo.');
        }else flash('error','Não foi possível salvar o arquivo.');
    }
}
header('Location:solicitacao.php?id='.$id);
