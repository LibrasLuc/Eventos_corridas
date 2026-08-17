<?php
require_once 'partials.php';
require_login();

if (!reviewer()) {
    http_response_code(403);
    exit('Acesso exclusivo do organizador.');
}

$error = '';
$step = 'form';

function organizer_payload(array $source): array
{
    return [
        'nome_evento' => trim($source['nome_evento'] ?? ''),
        'categoria' => trim($source['categoria'] ?? ''),
        'organizador' => trim($source['organizador'] ?? ''),
        'dia_ini' => $source['dia_ini'] ?? '',
        'dia_fin' => $source['dia_fin'] ?? '',
        'hora_ini' => $source['hora_ini'] ?? '',
        'hora_fin' => $source['hora_fin'] ?? '',
        'desc_corrida' => trim($source['desc_corrida'] ?? ''),
        'local_ini' => trim($source['local_ini'] ?? ''),
        'local_fin' => trim($source['local_fin'] ?? ''),
        'trajeto_json' => $source['trajeto_json'] ?? '',
    ];
}

function validate_organizer_payload(array $data): ?string
{
    $coordinate = '/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/';
    $route = json_decode($data['trajeto_json'], true);

    if ($data['nome_evento'] === '' || $data['categoria'] === '' || $data['desc_corrida'] === '') {
        return 'Preencha todos os dados da corrida.';
    }
    if (!$data['dia_ini'] || !$data['dia_fin'] || $data['dia_fin'] < $data['dia_ini']) {
        return 'Informe um período válido.';
    }
    if (!$data['hora_ini'] || !$data['hora_fin'] || ($data['dia_ini']===$data['dia_fin'] && $data['hora_fin']<=$data['hora_ini'])) {
        return 'Informe horários de início e término válidos.';
    }
    if (!preg_match($coordinate, $data['local_ini']) || !preg_match($coordinate, $data['local_fin'])) {
        return 'Marque a largada e a chegada no mapa.';
    }
    if (!is_array($route) || !valid_route($route)) {
        return 'Aguarde o cálculo do trajeto pelas ruas.';
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'review';

    if ($action === 'review') {
        $draft = organizer_payload($_POST);
        $error = validate_organizer_payload($draft) ?? '';
        if ($error === '') {
            $_SESSION['organizer_draft'] = $draft;
            $step = 'review';
        }
    }

    if ($action === 'confirm') {
        $draft = $_SESSION['organizer_draft'] ?? null;
        $error = $draft ? (validate_organizer_payload($draft) ?? '') : 'A revisão expirou. Preencha novamente.';

        if ($error === '') {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $pdo->query("SELECT GET_LOCK('agenda_corridas', 10)");

                $insert = $pdo->prepare(
                    "INSERT INTO corrida
                    (protocolo, usuario_id, nome_evento, local_ini, local_fin, categoria, desc_corrida,
                     organizador, trajeto_json, percurso_km, dia_ini, dia_fin, hora_ini, hora_fin, status, retorno)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aprovada', ?)"
                );
                $insert->execute([
                    new_protocol(), $_SESSION['user']['id'], $draft['nome_evento'], $draft['local_ini'],
                    $draft['local_fin'], $draft['categoria'], $draft['desc_corrida'],
                    $draft['organizador'], $draft['trajeto_json'], route_distance_km($draft['trajeto_json']), $draft['dia_ini'],
                    $draft['dia_fin'], $draft['hora_ini'], $draft['hora_fin'], 'Cadastrada e revisada pelo organizador.',
                ]);

                $eventId = (int) $pdo->lastInsertId();
                $history = $pdo->prepare(
                    "INSERT INTO solicitacao_historico
                     (corrida_id, usuario_id, status, mensagem)
                     VALUES (?, ?, 'aprovada', ?)"
                );
                $history->execute([$eventId, $_SESSION['user']['id'], 'Cadastro interno revisado e confirmado.']);

                $pdo->commit();
                $pdo->query("SELECT RELEASE_LOCK('agenda_corridas')");
                unset($_SESSION['organizer_draft']);
                flash('success', 'Corrida revisada, aprovada e publicada.');
                header('Location: solicitacao.php?id='.$eventId);
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $pdo->query("SELECT RELEASE_LOCK('agenda_corridas')");
                $error = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Não foi possível cadastrar a corrida.';
                $step = 'review';
            }
        }
    }
}

$draft = $_SESSION['organizer_draft'] ?? [];
$form = ($_GET['editar'] ?? '') === '1' ? $draft : [];
page_top('Nova corrida do organizador');
?>
<section class="page-title"><div><span class="eyebrow">CADASTRO INTERNO</span><h1><?= $step === 'review' ? 'Revise a corrida' : 'Nova corrida' ?></h1><p>O organizador revisa os dados antes da publicação imediata.</p></div></section>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<?php if ($step === 'review'): ?>
<section class="card event-detail review-document">
    <span class="badge em_analise">Revisão final</span>
    <h2><?= e($draft['nome_evento']) ?></h2>
    <dl><dt>Categoria</dt><dd><?= e($draft['categoria']) ?></dd><dt>Período</dt><dd><?= date('d/m/Y', strtotime($draft['dia_ini'])) ?> a <?= date('d/m/Y', strtotime($draft['dia_fin'])) ?></dd><dt>Horário</dt><dd><?=e($draft['hora_ini'])?> a <?=e($draft['hora_fin'])?></dd><dt>Organizador</dt><dd><?= e($draft['organizador']) ?></dd><dt>Descrição</dt><dd><?= nl2br(e($draft['desc_corrida'])) ?></dd><dt>Largada</dt><dd><?= e($draft['local_ini']) ?></dd><dt>Chegada</dt><dd><?= e($draft['local_fin']) ?></dd></dl>
    <div id="routeView" class="map" data-start="<?= e($draft['local_ini']) ?>" data-end="<?= e($draft['local_fin']) ?>"></div>
    <script type="application/json" id="savedRoute"><?= route_json_for_html($draft['trajeto_json']) ?></script>
<div class="review-buttons"><form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="confirm"><button class="btn primary">Confirmar e publicar</button></form><button class="btn secondary" type="button" onclick="history.back()">Voltar e corrigir</button></div>
</section>
<?php else: ?>
<form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="review"><input type="hidden" id="routeJson" name="trajeto_json"><section class="card form-section"><div class="section-number">01</div><h2>Dados da corrida</h2><div class="fields"><label class="wide">Nome do evento<input name="nome_evento" required></label><label>Categoria<input name="categoria" required placeholder="Ex.: 5 km, 10 km, meia maratona, corrida infantil"></label><label>Organizador<input name="organizador" required value="<?= e($_SESSION['user']['user']) ?>"></label><label>Dia inicial<input type="date" id="diaIni" name="dia_ini" required></label><label>Dia final<input type="date" id="diaFin" name="dia_fin" required></label><label>Hora de início<input type="time" name="hora_ini" required></label><label>Hora de término<input type="time" name="hora_fin" required></label><div id="dateStatus" class="availability wide">Carregando datas disponíveis...</div><label class="wide">Descrição<textarea name="desc_corrida" rows="4" required></textarea></label></div></section><section class="card form-section"><div class="section-number">02</div><h2>Trajeto</h2><div id="routePicker" class="map"></div><div id="routeStatus" class="availability">Marque a largada e a chegada.</div><div class="fields"><label>Largada<input id="localIni" name="local_ini" readonly required></label><label>Chegada<input id="localFin" name="local_fin" readonly required></label><div class="wide"><button type="button" id="resetRoute" class="btn secondary">Refazer marcações</button></div></div></section><aside class="submit-bar"><p><b>Próximo passo</b><br><span>Você revisará os dados antes de publicar.</span></p><button class="btn primary">Revisar corrida →</button></aside></form>
<?php endif; ?>
<?php page_bottom(); ?>
