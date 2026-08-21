const loadStylesheet = (href) => {
  const stylesheet = document.createElement('link');
  stylesheet.rel = 'stylesheet';
  stylesheet.href = href;
  document.head.appendChild(stylesheet);
};

loadStylesheet('assets/permit.css');

const pageName = location.pathname.split('/').pop() || 'index.php';
const isHomePage = pageName === 'index.php';
const isProtocolPage = pageName === 'solicitacao.php';

const hiddenInput = (name, value) =>
  `<input type="hidden" name="${name}" value="${value}">`;

function buildPermitStatus(info, requestId) {
  const status = info.alvara_status || 'pendente';
  let content = '';

  if (status === 'indeferido') {
    content += `
      <div class="permit-rejected">
        <b>Documento indeferido</b>
        <p>${info.alvara_motivo || 'O documento anexado não foi aceito.'}</p>
        <small>Substitua o PDF pelo documento correto.</small>
      </div>`;
  }

  if (info.alvara_enviado_em && status !== 'indeferido') {
    const sentAt = new Date(info.alvara_enviado_em.replace(' ', 'T'))
      .toLocaleString('pt-BR');

    content += `<p class="permit-ok">✓ Entrega confirmada em ${sentAt}</p>`;
    content += info.alvara_arquivo
      ? `<a class="btn secondary" href="${info.alvara_arquivo}" target="_blank">Visualizar PDF salvo</a>`
      : '<p><b>Confirmado sem documento anexado.</b></p>';
  }

  // O formulário de envio continua disponível para permitir a troca de um PDF já enviado.
  content += `
    <form action="alvara-action.php" method="post" enctype="multipart/form-data" class="permit-upload">
      ${hiddenInput('csrf', info.csrf)}
      ${hiddenInput('id', requestId)}
      ${hiddenInput('permit_action', 'upload')}
      <input type="file" name="alvara" accept="application/pdf,.pdf" required>
      <button class="btn primary">${info.alvara_arquivo ? 'Substituir PDF' : 'Anexar PDF'}</button>
    </form>`;

  if (!info.alvara_enviado_em) {
    content += `
      <form action="alvara-action.php" method="post" class="permit-no-file"
            onsubmit="return confirm('Confirmar a documentação sem anexar um arquivo?')">
        ${hiddenInput('csrf', info.csrf)}
        ${hiddenInput('id', requestId)}
        ${hiddenInput('permit_action', 'confirm_without_file')}
        <button class="btn secondary">Confirmar sem anexo</button>
      </form>`;
  }

  const reviewerCanReject = info.reviewer
    && (status === 'enviado' || status === 'confirmado_sem_anexo');

  if (reviewerCanReject) {
    content += `
      <form action="alvara-action.php" method="post" class="permit-reject">
        ${hiddenInput('csrf', info.csrf)}
        ${hiddenInput('id', requestId)}
        ${hiddenInput('permit_action', 'reject')}
        <label>
          Motivo do indeferimento
          <input name="motivo" required placeholder="Ex.: documento incorreto ou ilegível">
        </label>
        <button class="btn danger">Indeferir documento</button>
      </form>`;
  }

  const title = status === 'indeferido'
    ? 'Correção necessária'
    : info.alvara_enviado_em
      ? 'Documentação confirmada'
      : 'Envio pendente';

  return `
    <section class="card permit-section">
      <span class="eyebrow">DOCUMENTOS NECESSÁRIOS</span>
      <h2>${title}</h2>
      ${content}
    </section>`;
}

function showPermitStatus() {
  if (!isProtocolPage) return;

  loadStylesheet('assets/alvara.css');
  const requestId = new URLSearchParams(location.search).get('id');

  fetch(`api-status-alvara.php?id=${encodeURIComponent(requestId || '')}`)
    .then((response) => response.json())
    .then((info) => {
      const details = document.querySelector('.detail-grid');
      if (!details) return;

      details.insertAdjacentHTML('beforebegin', buildPermitStatus(info, requestId));
    })
    // O restante da página deve continuar funcionando mesmo se o aviso não carregar.
    .catch(() => {});
}

function buildReminder(events) {
  const eventLinks = events.map((event) => `
    <a href="solicitacao.php?id=${event.id}">
      <b>${event.protocolo} · ${event.nome_evento}</b>
      <span>${event.dias} dia(s)</span>
    </a>`).join('');

  return `
    <div class="permit-modal">
      <div class="permit-dialog">
        <button class="permit-close" aria-label="Fechar lembrete">×</button>
        <span class="permit-icon">!</span>
        <span class="eyebrow">DOCUMENTAÇÃO OBRIGATÓRIA</span>
        <h2>Envio de documentos necessário</h2>
        <p>Há evento(s) em menos de 90 dias com documentos necessários pendentes ou indeferidos.</p>
        <div class="permit-events">${eventLinks}</div>
        <label class="permit-today">
          <input type="checkbox" class="permit-hide-today">
          Não mostrar este lembrete novamente hoje
        </label>
        <div class="permit-buttons">
          <a class="btn primary" href="documentos/modelo-provisorio-alvara.html" download>
            Baixar modelo de documento
          </a>
          <button class="btn secondary permit-understood">Entendi</button>
        </div>
      </div>
    </div>`;
}

function showDocumentReminder() {
  const canShowHere = isHomePage || isProtocolPage;
  if (!document.body || document.querySelector('.login-layout') || !canShowHere) return;

  // A chave inclui a data para que a opção "não mostrar hoje" expire automaticamente amanhã.
  const today = new Date().toISOString().slice(0, 10);
  const reminderKey = `document-reminder-${today}`;
  if (localStorage.getItem(reminderKey) === 'hidden') return;

  const requestId = isProtocolPage
    ? new URLSearchParams(location.search).get('id')
    : '';
  const endpoint = `api-alerta-alvara.php${requestId ? `?id=${encodeURIComponent(requestId)}` : ''}`;

  fetch(endpoint)
    .then((response) => response.json())
    .then((events) => {
      if (!events.length) return;

      document.body.insertAdjacentHTML('afterbegin', buildReminder(events));

      const closeReminder = () => {
        if (document.querySelector('.permit-hide-today')?.checked) {
          localStorage.setItem(reminderKey, 'hidden');
        }
        document.querySelector('.permit-modal')?.remove();
      };

      document.querySelector('.permit-close').onclick = closeReminder;
      document.querySelector('.permit-understood').onclick = closeReminder;
    })
    .catch(() => {});
}

showPermitStatus();
showDocumentReminder();
