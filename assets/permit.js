const permitStyle=document.createElement('link');permitStyle.rel='stylesheet';permitStyle.href='assets/permit.css';document.head.appendChild(permitStyle);
const pageName=location.pathname.split('/').pop()||'index.php',isHome=pageName==='index.php',isProtocol=pageName==='solicitacao.php';

if(isProtocol){
 const style=document.createElement('link');style.rel='stylesheet';style.href='assets/alvara.css';document.head.appendChild(style);
 const id=new URLSearchParams(location.search).get('id');
 fetch(`api-status-alvara.php?id=${encodeURIComponent(id||'')}`).then(r=>r.json()).then(info=>{
  const anchor=document.querySelector('.detail-grid');if(!anchor)return;
  const status=info.alvara_status||'pendente';let body='';
  if(status==='indeferido')body+=`<div class="permit-rejected"><b>Documento indeferido</b><p>${info.alvara_motivo||'O documento anexado não foi aceito.'}</p><small>Substitua o PDF pelo documento correto.</small></div>`;
  if(info.alvara_enviado_em&&status!=='indeferido'){
   const date=new Date(info.alvara_enviado_em.replace(' ','T')).toLocaleString('pt-BR');
   body+=`<p class="permit-ok">✓ Entrega confirmada em ${date}</p>${info.alvara_arquivo?`<a class="btn secondary" href="${info.alvara_arquivo}" target="_blank">Visualizar PDF salvo</a>`:'<p><b>Confirmado sem documento anexado.</b></p>'}`;
  }
  body+=`<form action="alvara-action.php" method="post" enctype="multipart/form-data" class="permit-upload"><input type="hidden" name="csrf" value="${info.csrf}"><input type="hidden" name="id" value="${id}"><input type="hidden" name="permit_action" value="upload"><input type="file" name="alvara" accept="application/pdf,.pdf" required><button class="btn primary">${info.alvara_arquivo?'Substituir PDF':'Anexar PDF'}</button></form>`;
  if(!info.alvara_enviado_em)body+=`<form action="alvara-action.php" method="post" class="permit-no-file" onsubmit="return confirm('Confirmar a documentação sem anexar um arquivo?')"><input type="hidden" name="csrf" value="${info.csrf}"><input type="hidden" name="id" value="${id}"><input type="hidden" name="permit_action" value="confirm_without_file"><button class="btn secondary">Confirmar sem anexo</button></form>`;
  if(info.reviewer&&(status==='enviado'||status==='confirmado_sem_anexo'))body+=`<form action="alvara-action.php" method="post" class="permit-reject"><input type="hidden" name="csrf" value="${info.csrf}"><input type="hidden" name="id" value="${id}"><input type="hidden" name="permit_action" value="reject"><label>Motivo do indeferimento<input name="motivo" required placeholder="Ex.: documento incorreto ou ilegível"></label><button class="btn danger">Indeferir documento</button></form>`;
  anchor.insertAdjacentHTML('beforebegin',`<section class="card permit-section"><span class="eyebrow">DOCUMENTOS NECESSÁRIOS</span><h2>${status==='indeferido'?'Correção necessária':info.alvara_enviado_em?'Documentação confirmada':'Envio pendente'}</h2>${body}</section>`);
 }).catch(()=>{});
}

const reminderKey=`document-reminder-${new Date().toISOString().slice(0,10)}`;
if(document.body&&!document.querySelector('.login-layout')&&(isHome||isProtocol)&&localStorage.getItem(reminderKey)!=='hidden'){
 const protocolId=isProtocol?new URLSearchParams(location.search).get('id'):'';
 fetch(`api-alerta-alvara.php${protocolId?`?id=${encodeURIComponent(protocolId)}`:''}`).then(r=>r.json()).then(events=>{
  if(!events.length)return;const rows=events.map(e=>`<a href="solicitacao.php?id=${e.id}"><b>${e.protocolo} · ${e.nome_evento}</b><span>${e.dias} dia(s)</span></a>`).join('');
  document.body.insertAdjacentHTML('afterbegin',`<div class="permit-modal"><div class="permit-dialog"><button class="permit-close">×</button><span class="permit-icon">!</span><span class="eyebrow">DOCUMENTAÇÃO OBRIGATÓRIA</span><h2>Envio de documentos necessário</h2><p>Há evento(s) em menos de 90 dias com documentos necessários pendentes ou indeferidos.</p><div class="permit-events">${rows}</div><label class="permit-today"><input type="checkbox" class="permit-hide-today"> Não mostrar este lembrete novamente hoje</label><div class="permit-buttons"><a class="btn primary" href="documentos/modelo-provisorio-alvara.html" download>Baixar modelo de documento</a><button class="btn secondary permit-understood">Entendi</button></div></div></div>`);
  const close=()=>{if(document.querySelector('.permit-hide-today')?.checked)localStorage.setItem(reminderKey,'hidden');document.querySelector('.permit-modal')?.remove()};document.querySelector('.permit-close').onclick=close;document.querySelector('.permit-understood').onclick=close;
 }).catch(()=>{});
}
