<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/options.php';

function admin_validate_date(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

function admin_parse_money(string $raw): ?float
{
    $value = trim($raw);
    if ($value === '') {
        return 0.0;
    }

    $value = str_replace(['R$', ' '], '', $value);
    if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (strpos($value, ',') !== false) {
        $value = str_replace(',', '.', $value);
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round((float)$value, 2);
}

function admin_value_in_list(string $value, array $list): bool
{
    return in_array($value, $list, true);
}

$section = (string)($_GET['sec'] ?? 'dashboard');
if (!in_array($section, ['dashboard', 'comercial'], true)) {
    $section = 'dashboard';
}

$currentUser = admin_current_user();
$isLogged = is_array($currentUser);
$loginError = '';
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'login') {
        $csrfToken = (string)($_POST['csrf_token'] ?? '');
        if (!admin_csrf_validate('login_form', $csrfToken)) {
            $loginError = 'Sessao invalida. Recarregue e tente novamente.';
        } else {
            $username = strtolower(trim((string)($_POST['username'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $nextSection = (string)($_POST['next_section'] ?? 'dashboard');
            if (!in_array($nextSection, ['dashboard', 'comercial'], true)) {
                $nextSection = 'dashboard';
            }

            if ($username === '' || $password === '') {
                $loginError = 'Informe usuario e senha.';
            } else {
                try {
                    $user = admin_authenticate($username, $password);
                    if (!$user) {
                        admin_log_event('Tentativa de login invalida para username=' . $username);
                        $loginError = 'Credenciais invalidas.';
                    } else {
                        admin_login_user($user);
                        admin_log_event('Login realizado por username=' . $username);
                        admin_redirect('/admin/painel.php?sec=' . urlencode($nextSection));
                    }
                } catch (Throwable $e) {
                    admin_log_event('Erro no login para username=' . $username . ' detalhes=' . $e->getMessage());
                    $loginError = 'Nao foi possivel autenticar agora. Verifique as credenciais do banco e tente novamente.';
                }
            }
        }
    }

    if ($action === 'create_negocio') {
        if (!$isLogged || !$currentUser) {
            admin_set_flash('error', 'Sessao expirada. Faca login novamente.');
            admin_redirect('/admin/painel.php?sec=comercial');
        }

        if (!admin_can_access($currentUser, 'comercial')) {
            admin_set_flash('error', 'Seu perfil nao tem permissao para a area Comercial.');
            admin_redirect('/admin/painel.php?sec=comercial');
        }

        $csrfToken = (string)($_POST['csrf_token'] ?? '');
        if (!admin_csrf_validate('deal_form', $csrfToken)) {
            $formErrors[] = 'Sessao invalida. Recarregue e tente novamente.';
        }

        $origem = trim((string)($_POST['origem'] ?? ''));
        $formaContato = trim((string)($_POST['forma_contato'] ?? ''));
        $dataInicio = trim((string)($_POST['data_inicio'] ?? ''));
        $dataFim = trim((string)($_POST['data_fim'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $proximaAcao = trim((string)($_POST['proxima_acao'] ?? ''));
        $motivoPerda = trim((string)($_POST['motivo_perda'] ?? ''));
        $regiao = trim((string)($_POST['regiao'] ?? ''));
        $perfil = trim((string)($_POST['perfil'] ?? ''));
        $nomeCliente = trim((string)($_POST['nome'] ?? ''));
        $contato = trim((string)($_POST['contato'] ?? ''));
        $servico = trim((string)($_POST['servico'] ?? ''));
        $observacao = trim((string)($_POST['observacao'] ?? ''));
        $totalCacambasRaw = trim((string)($_POST['total_cacambas'] ?? '0'));
        $valorTotalRaw = trim((string)($_POST['valor_total'] ?? '0'));
        $valorPerdidoRaw = trim((string)($_POST['valor_perdido'] ?? '0'));

        if (!admin_value_in_list($origem, RETEC_ORIGENS)) {
            $formErrors[] = 'Origem invalida.';
        }
        if (!admin_value_in_list($formaContato, RETEC_FORMAS_CONTATO)) {
            $formErrors[] = 'Forma de contato invalida.';
        }
        if (!admin_value_in_list($status, RETEC_STATUS)) {
            $formErrors[] = 'Status invalido.';
        }
        if (!admin_value_in_list($proximaAcao, RETEC_PROXIMA_ACAO)) {
            $formErrors[] = 'Proxima acao invalida.';
        }
        if (!admin_value_in_list($perfil, RETEC_PERFIS)) {
            $formErrors[] = 'Perfil invalido.';
        }
        if (!admin_value_in_list($servico, RETEC_SERVICOS)) {
            $formErrors[] = 'Servico invalido. Use somente 4m ou 26m.';
        }

        if ($dataInicio === '' || !admin_validate_date($dataInicio)) {
            $formErrors[] = 'Data de inicio invalida. Use formato de data.';
        }
        if ($dataFim !== '' && !admin_validate_date($dataFim)) {
            $formErrors[] = 'Data de fim invalida. Use formato de data.';
        }

        if ($status === 'Venda Perdida') {
            if (!admin_value_in_list($motivoPerda, RETEC_MOTIVOS_PERDA)) {
                $formErrors[] = 'Motivo da perda obrigatorio para venda perdida.';
            }
        } else {
            $motivoPerda = '';
        }

        if ($nomeCliente === '' || strlen($nomeCliente) > 150) {
            $formErrors[] = 'Nome invalido.';
        }
        if ($contato === '' || strlen($contato) > 120) {
            $formErrors[] = 'Contato invalido.';
        }
        if ($regiao !== '' && strlen($regiao) > 120) {
            $formErrors[] = 'Regiao deve ter no maximo 120 caracteres.';
        }

        $totalCacambas = filter_var($totalCacambasRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($totalCacambas === false) {
            $formErrors[] = 'Total de cacambas deve ser numero inteiro maior ou igual a zero.';
        }

        $valorTotal = admin_parse_money($valorTotalRaw);
        $valorPerdido = admin_parse_money($valorPerdidoRaw);
        if ($valorTotal === null || $valorTotal < 0) {
            $formErrors[] = 'Valor total invalido.';
        }
        if ($valorPerdido === null || $valorPerdido < 0) {
            $formErrors[] = 'Valor perdido invalido.';
        }

        if ($status !== 'Venda Perdida') {
            $valorPerdido = 0.0;
        }

        if (!$formErrors) {
            try {
                $stmt = admin_db()->prepare(
                    'INSERT INTO negocios_comerciais (
                        vendedor_id, vendedor_nome, origem, forma_contato, data_inicio, data_fim,
                        status, proxima_acao, motivo_perda, regiao, perfil, nome_cliente, contato,
                        servico, total_cacambas, valor_total, valor_perdido, observacao
                    ) VALUES (
                        :vendedor_id, :vendedor_nome, :origem, :forma_contato, :data_inicio, :data_fim,
                        :status, :proxima_acao, :motivo_perda, :regiao, :perfil, :nome_cliente, :contato,
                        :servico, :total_cacambas, :valor_total, :valor_perdido, :observacao
                    )'
                );

                $stmt->execute([
                    'vendedor_id' => (int)$currentUser['id'],
                    'vendedor_nome' => (string)$currentUser['display_name'],
                    'origem' => $origem,
                    'forma_contato' => $formaContato,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim !== '' ? $dataFim : null,
                    'status' => $status,
                    'proxima_acao' => $proximaAcao,
                    'motivo_perda' => $motivoPerda !== '' ? $motivoPerda : null,
                    'regiao' => $regiao !== '' ? $regiao : null,
                    'perfil' => $perfil,
                    'nome_cliente' => $nomeCliente,
                    'contato' => $contato,
                    'servico' => $servico,
                    'total_cacambas' => (int)$totalCacambas,
                    'valor_total' => $valorTotal,
                    'valor_perdido' => $valorPerdido,
                    'observacao' => $observacao !== '' ? $observacao : null,
                ]);

                admin_set_flash('success', 'Negocio registrado com sucesso.');
                admin_redirect('/admin/painel.php?sec=comercial');
            } catch (Throwable $e) {
                admin_log_event('Erro ao inserir negocio: ' . $e->getMessage());
                $formErrors[] = 'Nao foi possivel salvar no banco. Verifique se as tabelas foram criadas.';
            }
        }
    }
}

$currentUser = admin_current_user();
$isLogged = is_array($currentUser);
$accessDenied = false;

if ($isLogged && $currentUser && !admin_can_access($currentUser, $section)) {
    $accessDenied = true;
}

$flash = admin_get_flash();

$dashboard = [
    'total_negocios' => 0,
    'total_cacambas' => 0,
    'valor_total' => 0,
    'valor_perdido' => 0,
    'em_negociacao' => 0,
    'venda_realizada' => 0,
    'venda_perdida' => 0,
    'venda_cancelada' => 0,
];

$recentDeals = [];

if ($isLogged && $currentUser) {
    try {
        $whereClause = '';
        $queryParams = [];
        if ($currentUser['role'] === 'comercial') {
            $whereClause = ' WHERE vendedor_id = :vendedor_id ';
            $queryParams['vendedor_id'] = (int)$currentUser['id'];
        }

        $stmtDash = admin_db()->prepare(
            'SELECT
                COUNT(*) AS total_negocios,
                COALESCE(SUM(total_cacambas), 0) AS total_cacambas,
                COALESCE(SUM(valor_total), 0) AS valor_total,
                COALESCE(SUM(valor_perdido), 0) AS valor_perdido,
                SUM(CASE WHEN status = "Em negociacao" THEN 1 ELSE 0 END) AS em_negociacao,
                SUM(CASE WHEN status = "Venda Realizada" THEN 1 ELSE 0 END) AS venda_realizada,
                SUM(CASE WHEN status = "Venda Perdida" THEN 1 ELSE 0 END) AS venda_perdida,
                SUM(CASE WHEN status = "Venda Cancelada" THEN 1 ELSE 0 END) AS venda_cancelada
            FROM negocios_comerciais' . $whereClause
        );
        $stmtDash->execute($queryParams);
        $dashData = $stmtDash->fetch();
        if (is_array($dashData)) {
            $dashboard = array_merge($dashboard, $dashData);
        }

        $stmtRecent = admin_db()->prepare(
            'SELECT
                vendedor_nome,
                nome_cliente,
                status,
                servico,
                total_cacambas,
                valor_total,
                valor_perdido,
                data_inicio,
                created_at
            FROM negocios_comerciais ' . $whereClause . '
            ORDER BY id DESC
            LIMIT 12'
        );
        $stmtRecent->execute($queryParams);
        $recentDeals = $stmtRecent->fetchAll();
    } catch (Throwable $e) {
        admin_log_event('Erro ao carregar dashboard/listagem: ' . $e->getMessage());
        if ($section === 'dashboard') {
            $flash = [
                'type' => 'error',
                'message' => 'Nao foi possivel carregar os dados. Verifique se o banco foi configurado.',
            ];
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel RETEC</title>
    <style>
        :root {
            --blue-900: #00263d;
            --blue-700: #003352;
            --green-600: #22ac65;
            --yellow-500: #f1c40f;
            --red-500: #c0392b;
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #1b2f40;
            --muted: #5a6f82;
            --line: #dce4ec;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: radial-gradient(circle at top right, #e7f0f8 0, #f4f7fb 45%, #f7fafc 100%);
            color: var(--text);
        }

        .wrap {
            max-width: 1240px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }

        .topbar {
            background: linear-gradient(135deg, var(--blue-900), var(--blue-700));
            color: #fff;
            border-radius: 14px;
            padding: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .title {
            margin: 0;
            font-size: 1.3rem;
            letter-spacing: 0.2px;
        }

        .subtitle {
            margin: 4px 0 0;
            color: #d8e9f7;
            font-size: 0.92rem;
        }

        .nav-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            cursor: pointer;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            transition: transform .2s ease, opacity .2s ease;
        }
        .btn:hover { transform: translateY(-1px); opacity: .95; }
        .btn-dashboard { background: #0f6ea7; }
        .btn-comercial { background: #1f9f5a; }
        .btn-logout { background: #9b1f2a; }

        .panel {
            margin-top: 16px;
            background: var(--card);
            border-radius: 14px;
            border: 1px solid var(--line);
            padding: 18px;
            box-shadow: 0 8px 28px rgba(16, 50, 76, 0.08);
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
            border: 1px solid transparent;
        }
        .alert.error { background: #ffeceb; color: #6a1c15; border-color: #ffc9c6; }
        .alert.success { background: #ebfff3; color: #0f5c34; border-color: #bcefd1; }

        .login-grid {
            display: grid;
            grid-template-columns: 1fr;
            max-width: 420px;
            gap: 10px;
        }

        label { font-size: 0.88rem; color: var(--muted); font-weight: 600; }
        input, select, textarea {
            width: 100%;
            border: 1px solid #c7d3df;
            border-radius: 8px;
            padding: 10px 11px;
            font: inherit;
            color: var(--text);
            background: #fff;
        }

        .submit {
            border: 0;
            border-radius: 8px;
            padding: 11px 13px;
            background: var(--blue-700);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .cards {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 14px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
            background: #fff;
        }
        .card .k { font-size: .8rem; color: var(--muted); display: block; }
        .card .v { font-size: 1.35rem; font-weight: 700; margin-top: 4px; }

        .card.primary { border-left: 5px solid #0f6ea7; }
        .card.green { border-left: 5px solid var(--green-600); }
        .card.yellow { border-left: 5px solid var(--yellow-500); }
        .card.red { border-left: 5px solid var(--red-500); }

        .section-title {
            margin: 4px 0 12px;
            font-size: 1.08rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .span-2 { grid-column: span 2; }
        .span-3 { grid-column: span 3; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.9rem;
        }

        th, td {
            text-align: left;
            border-bottom: 1px solid var(--line);
            padding: 9px 6px;
        }
        th { color: var(--muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: .3px; }

        .hint {
            color: var(--muted);
            font-size: 0.82rem;
            margin-top: 6px;
        }

        @media (max-width: 980px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .span-3 { grid-column: span 2; }
        }

        @media (max-width: 640px) {
            .cards { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .span-2, .span-3 { grid-column: span 1; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <h1 class="title">Painel Interno RETEC</h1>
                <p class="subtitle">Area restrita para equipe interna. Dashboard com dados internos e secao Comercial.</p>
            </div>
            <div class="nav-actions">
                <a class="btn btn-dashboard" href="/admin/painel.php?sec=dashboard">Dashboard</a>
                <a class="btn btn-comercial" href="/admin/painel.php?sec=comercial">Comercial</a>
                <?php if ($isLogged): ?>
                    <a class="btn btn-logout" href="/admin/logout.php">Sair</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <?php if ($flash): ?>
                <div class="alert <?php echo admin_h((string)$flash['type']); ?>">
                    <?php echo admin_h((string)$flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (!$isLogged): ?>
                <?php if ($loginError !== ''): ?>
                    <div class="alert error"><?php echo admin_h($loginError); ?></div>
                <?php endif; ?>

                <h2 class="section-title">Login necessario para acessar <?php echo admin_h(ucfirst($section)); ?></h2>
                <form method="post" class="login-grid" action="/admin/painel.php?sec=<?php echo admin_h($section); ?>">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="next_section" value="<?php echo admin_h($section); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_h(admin_csrf_token('login_form')); ?>">

                    <div>
                        <label for="username">Usuario</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div>
                        <label for="password">Senha</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button class="submit" type="submit">Entrar</button>
                    <p class="hint">Primeiro acesso: usuario admretec.</p>
                </form>
            <?php else: ?>
                <p class="hint">
                    Usuario: <strong><?php echo admin_h((string)$currentUser['display_name']); ?></strong>
                    (<?php echo admin_h((string)$currentUser['role']); ?>)
                </p>

                <?php if ($accessDenied): ?>
                    <div class="alert error">Seu perfil nao possui acesso para a secao <?php echo admin_h($section); ?>.</div>
                <?php elseif ($section === 'dashboard'): ?>
                    <h2 class="section-title">Dashboard</h2>
                    <div class="cards">
                        <div class="card primary"><span class="k">Total negocios</span><div class="v"><?php echo admin_h((string)$dashboard['total_negocios']); ?></div></div>
                        <div class="card green"><span class="k">Total cacambas</span><div class="v"><?php echo admin_h((string)$dashboard['total_cacambas']); ?></div></div>
                        <div class="card yellow"><span class="k">Valor total (R$)</span><div class="v"><?php echo admin_h(number_format((float)$dashboard['valor_total'], 2, ',', '.')); ?></div></div>
                        <div class="card red"><span class="k">Valor perdido (R$)</span><div class="v"><?php echo admin_h(number_format((float)$dashboard['valor_perdido'], 2, ',', '.')); ?></div></div>
                    </div>

                    <div class="cards">
                        <div class="card"><span class="k">Em negociacao</span><div class="v"><?php echo admin_h((string)$dashboard['em_negociacao']); ?></div></div>
                        <div class="card"><span class="k">Venda realizada</span><div class="v"><?php echo admin_h((string)$dashboard['venda_realizada']); ?></div></div>
                        <div class="card"><span class="k">Venda perdida</span><div class="v"><?php echo admin_h((string)$dashboard['venda_perdida']); ?></div></div>
                        <div class="card"><span class="k">Venda cancelada</span><div class="v"><?php echo admin_h((string)$dashboard['venda_cancelada']); ?></div></div>
                    </div>

                    <p class="hint">Integracao Google Ads: estrutura preparada para etapa seguinte.</p>
                <?php else: ?>
                    <h2 class="section-title">Comercial - Registro de negocios</h2>

                    <?php if ($formErrors): ?>
                        <div class="alert error">
                            <?php foreach ($formErrors as $error): ?>
                                <div><?php echo admin_h((string)$error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="/admin/painel.php?sec=comercial">
                        <input type="hidden" name="action" value="create_negocio">
                        <input type="hidden" name="csrf_token" value="<?php echo admin_h(admin_csrf_token('deal_form')); ?>">

                        <div class="form-grid">
                            <div>
                                <label>Vendedor (usuario logado)</label>
                                <input type="text" value="<?php echo admin_h((string)$currentUser['display_name']); ?>" readonly>
                            </div>

                            <div>
                                <label for="origem">Origem</label>
                                <select id="origem" name="origem" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_ORIGENS as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="forma_contato">Forma de contato</label>
                                <select id="forma_contato" name="forma_contato" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_FORMAS_CONTATO as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="data_inicio">Data inicio</label>
                                <input type="date" id="data_inicio" name="data_inicio" required>
                            </div>

                            <div>
                                <label for="data_fim">Data fim</label>
                                <input type="date" id="data_fim" name="data_fim">
                            </div>

                            <div>
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_STATUS as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="proxima_acao">Proxima acao</label>
                                <select id="proxima_acao" name="proxima_acao" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_PROXIMA_ACAO as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="span-2">
                                <label for="motivo_perda">Motivo perda</label>
                                <select id="motivo_perda" name="motivo_perda">
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_MOTIVOS_PERDA as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="regiao">Regiao</label>
                                <input type="text" id="regiao" name="regiao" maxlength="120">
                            </div>

                            <div>
                                <label for="perfil">Perfil</label>
                                <select id="perfil" name="perfil" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_PERFIS as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="nome">Nome</label>
                                <input type="text" id="nome" name="nome" maxlength="150" required>
                            </div>

                            <div>
                                <label for="contato">Contato</label>
                                <input type="text" id="contato" name="contato" maxlength="120" required>
                            </div>

                            <div>
                                <label for="servico">Servico</label>
                                <select id="servico" name="servico" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_SERVICOS as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="total_cacambas">Total cacambas</label>
                                <input type="number" id="total_cacambas" name="total_cacambas" min="0" step="1" value="0" required>
                            </div>

                            <div>
                                <label for="valor_total">Valor total (R$)</label>
                                <input type="text" id="valor_total" name="valor_total" inputmode="decimal" placeholder="0,00" required>
                            </div>

                            <div>
                                <label for="valor_perdido">Valor perdido (R$)</label>
                                <input type="text" id="valor_perdido" name="valor_perdido" inputmode="decimal" placeholder="0,00" required>
                            </div>

                            <div class="span-3">
                                <label for="observacao">Observacao</label>
                                <textarea id="observacao" name="observacao" rows="4"></textarea>
                            </div>

                            <div class="span-3">
                                <button type="submit" class="submit">Salvar negocio</button>
                            </div>
                        </div>
                    </form>

                    <h3 class="section-title" style="margin-top:22px;">Ultimos registros</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Vendedor</th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Servico</th>
                                <th>Cacambas</th>
                                <th>Valor total</th>
                                <th>Valor perdido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recentDeals): ?>
                                <tr><td colspan="8">Nenhum registro encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentDeals as $deal): ?>
                                    <tr>
                                        <td><?php echo admin_h((string)$deal['data_inicio']); ?></td>
                                        <td><?php echo admin_h((string)$deal['vendedor_nome']); ?></td>
                                        <td><?php echo admin_h((string)$deal['nome_cliente']); ?></td>
                                        <td><?php echo admin_h((string)$deal['status']); ?></td>
                                        <td><?php echo admin_h((string)$deal['servico']); ?></td>
                                        <td><?php echo admin_h((string)$deal['total_cacambas']); ?></td>
                                        <td>R$ <?php echo admin_h(number_format((float)$deal['valor_total'], 2, ',', '.')); ?></td>
                                        <td>R$ <?php echo admin_h(number_format((float)$deal['valor_perdido'], 2, ',', '.')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
