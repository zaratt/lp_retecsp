<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/options.php';

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

function admin_proxima_acao_por_status(string $status): string
{
    if ($status === 'Venda Perdida') {
        return 'Pos-Perda';
    }
    if ($status === 'Em negociacao') {
        return 'Acompanhar Follow-up';
    }
    if ($status === 'Venda Realizada') {
        return 'Pos-Venda';
    }
    if ($status === 'Venda Cancelada') {
        return 'Pos-Perda';
    }

    return '';
}

function admin_data_fim_por_status(string $status): ?string
{
    if (in_array($status, ['Venda Perdida', 'Venda Realizada', 'Venda Cancelada'], true)) {
        return date('Y-m-d');
    }
    return null;
}

function admin_dashboard_range(string $periodo): array
{
    $today = new DateTimeImmutable('today');
    $year = (int)$today->format('Y');
    $month = (int)$today->format('n');

    if ($periodo === 'anual') {
        return [
            'inicio' => sprintf('%04d-01-01', $year),
            'fim' => sprintf('%04d-12-31', $year),
            'label' => 'Anual',
        ];
    }

    if ($periodo === 'semestral') {
        $isSecondSemester = $month >= 7;
        return [
            'inicio' => sprintf('%04d-%02d-01', $year, $isSecondSemester ? 7 : 1),
            'fim' => sprintf('%04d-%02d-%02d', $year, $isSecondSemester ? 12 : 6, $isSecondSemester ? 31 : 30),
            'label' => 'Semestral',
        ];
    }

    if ($periodo === 'trimestral') {
        $quarterStartMonth = (int)(floor(($month - 1) / 3) * 3) + 1;
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $quarterStartMonth));
        $end = $start->modify('+2 months')->modify('last day of this month');
        return [
            'inicio' => $start->format('Y-m-d'),
            'fim' => $end->format('Y-m-d'),
            'label' => 'Trimestral',
        ];
    }

    $start = new DateTimeImmutable($today->format('Y-m-01'));
    $end = $start->modify('last day of this month');
    return [
        'inicio' => $start->format('Y-m-d'),
        'fim' => $end->format('Y-m-d'),
        'label' => 'Mensal',
    ];
}

function admin_normalize_cep(string $raw): string
{
    $digits = preg_replace('/\D+/', '', trim($raw));
    if (!is_string($digits) || strlen($digits) !== 8) {
        return '';
    }
    return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
}

function admin_find_localidade_by_cep(string $cep): ?array
{
    $normalized = admin_normalize_cep($cep);
    if ($normalized === '') {
        return null;
    }

    $stmt = admin_db()->prepare(
        'SELECT id, cep, bairro, municipio
         FROM localidades_cache
         WHERE REPLACE(cep, "-", "") = :cep
         LIMIT 1'
    );
    $stmt->execute(['cep' => str_replace('-', '', $normalized)]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function admin_find_localidade_by_bairro(string $bairro, ?string $municipio = null): ?array
{
    $bairro = trim($bairro);
    if ($bairro === '') {
        return null;
    }

    if ($municipio !== null && trim($municipio) !== '') {
        $stmt = admin_db()->prepare(
            'SELECT id, cep, bairro, municipio
             FROM localidades_cache
             WHERE LOWER(bairro) = LOWER(:bairro)
               AND LOWER(municipio) = LOWER(:municipio)
             LIMIT 1'
        );
        $stmt->execute([
            'bairro' => $bairro,
            'municipio' => trim($municipio),
        ]);
    } else {
        $stmt = admin_db()->prepare(
            'SELECT id, cep, bairro, municipio
             FROM localidades_cache
             WHERE LOWER(bairro) = LOWER(:bairro)
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute(['bairro' => $bairro]);
    }

    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function admin_localidade_exists_municipio(string $municipio): bool
{
    $municipio = trim($municipio);
    if ($municipio === '') {
        return false;
    }

    $stmt = admin_db()->prepare(
        'SELECT 1
         FROM localidades_cache
         WHERE LOWER(municipio) = LOWER(:municipio)
         LIMIT 1'
    );
    $stmt->execute(['municipio' => $municipio]);
    return (bool)$stmt->fetchColumn();
}

$section = (string)($_GET['sec'] ?? 'dashboard');
if (!in_array($section, ['dashboard', 'comercial', 'clientes'], true)) {
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
            if (!in_array($nextSection, ['dashboard', 'comercial', 'clientes'], true)) {
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
                        if (!admin_is_admin($user)) {
                            $nextSection = 'comercial';
                        }
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

    if ($action === 'create_cliente') {
        if (!$isLogged || !$currentUser || !admin_is_admin($currentUser)) {
            admin_set_flash('error', 'Acesso negado para cadastro de clientes.');
            admin_redirect('/admin/painel.php?sec=comercial');
        }

        $csrfToken = (string)($_POST['csrf_token'] ?? '');
        if (!admin_csrf_validate('cliente_form', $csrfToken)) {
            admin_set_flash('error', 'Sessao invalida. Recarregue e tente novamente.');
            admin_redirect('/admin/painel.php?sec=clientes');
        }

        $nome = trim((string)($_POST['nome'] ?? ''));
        $bairro = trim((string)($_POST['bairro'] ?? ''));
        $cep = trim((string)($_POST['cep'] ?? ''));

        if ($nome === '' || strlen($nome) > 150 || $bairro === '' || strlen($bairro) > 120 || $cep === '' || strlen($cep) > 10) {
            admin_set_flash('error', 'Preencha Nome, Bairro e CEP corretamente.');
            admin_redirect('/admin/painel.php?sec=clientes');
        }

        try {
            $stmt = admin_db()->prepare('INSERT INTO clientes (nome, bairro, cep) VALUES (:nome, :bairro, :cep)');
            $stmt->execute([
                'nome' => $nome,
                'bairro' => $bairro,
                'cep' => $cep,
            ]);
            admin_set_flash('success', 'Cliente cadastrado com sucesso.');
        } catch (Throwable $e) {
            admin_log_event('Erro ao cadastrar cliente: ' . $e->getMessage());
            admin_set_flash('error', 'Nao foi possivel cadastrar cliente agora.');
        }

        admin_redirect('/admin/painel.php?sec=clientes');
    }

    if ($action === 'delete_cliente') {
        if (!$isLogged || !$currentUser || !admin_is_admin($currentUser)) {
            admin_set_flash('error', 'Acesso negado para exclusao de clientes.');
            admin_redirect('/admin/painel.php?sec=comercial');
        }

        $csrfToken = (string)($_POST['csrf_token'] ?? '');
        if (!admin_csrf_validate('delete_cliente_form', $csrfToken)) {
            admin_set_flash('error', 'Sessao invalida. Recarregue e tente novamente.');
            admin_redirect('/admin/painel.php?sec=clientes');
        }

        $clienteId = filter_var((string)($_POST['cliente_id'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($clienteId === false) {
            admin_set_flash('error', 'Cliente invalido.');
            admin_redirect('/admin/painel.php?sec=clientes');
        }

        try {
            $stmt = admin_db()->prepare('DELETE FROM clientes WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int)$clienteId]);
            admin_set_flash('success', 'Cliente excluido com sucesso.');
        } catch (Throwable $e) {
            admin_log_event('Erro ao excluir cliente id=' . (string)$clienteId . ' detalhes=' . $e->getMessage());
            admin_set_flash('error', 'Nao foi possivel excluir cliente. Verifique se ele esta vinculado a negocios.');
        }

        admin_redirect('/admin/painel.php?sec=clientes');
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
        $cep = admin_normalize_cep((string)($_POST['cep'] ?? ''));
        $bairro = trim((string)($_POST['bairro'] ?? ''));
        $municipio = trim((string)($_POST['municipio'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $motivoPerda = trim((string)($_POST['motivo_perda'] ?? ''));
        $perfil = trim((string)($_POST['perfil'] ?? ''));
        $nomeCliente = trim((string)($_POST['nome'] ?? ''));
        $clienteIdRaw = trim((string)($_POST['cliente_id'] ?? ''));
        $telefone = trim((string)($_POST['telefone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
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
        if (!admin_value_in_list($perfil, RETEC_PERFIS)) {
            $formErrors[] = 'Perfil invalido.';
        }
        if (!admin_value_in_list($servico, RETEC_SERVICOS)) {
            $formErrors[] = 'Servico invalido. Use somente 4m ou 26m.';
        }

        if ($bairro !== '' && strlen($bairro) > 120) {
            $formErrors[] = 'Bairro deve ter no maximo 120 caracteres.';
        }
        if ($municipio !== '' && strlen($municipio) > 120) {
            $formErrors[] = 'Municipio deve ter no maximo 120 caracteres.';
        }
        if ($telefone !== '' && strlen($telefone) > 120) {
            $formErrors[] = 'Telefone invalido.';
        }
        if ($email !== '' && (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $formErrors[] = 'Email invalido.';
        }

        // Regras de integracao CEP/Bairro/Municipio com selecao estrita da lista.
        if ($cep !== '') {
            try {
                $localidadeCep = admin_find_localidade_by_cep($cep);
                if (!$localidadeCep) {
                    $formErrors[] = 'CEP invalido. Selecione um CEP valido da lista.';
                } else {
                    $bairro = trim((string)($localidadeCep['bairro'] ?? ''));
                    $municipio = trim((string)($localidadeCep['municipio'] ?? ''));
                }
            } catch (Throwable $e) {
                admin_log_event('Erro ao validar CEP no create_negocio: ' . $e->getMessage());
                $formErrors[] = 'Nao foi possivel validar o CEP agora.';
            }
        } elseif ($bairro !== '') {
            // Se Bairro for informado, CEP deve ficar vazio e Municipio deve vir da selecao valida.
            $cep = '';
            try {
                $localidadeBairro = admin_find_localidade_by_bairro($bairro, $municipio !== '' ? $municipio : null);
                if (!$localidadeBairro) {
                    $formErrors[] = 'Bairro invalido. Selecione um Bairro da lista.';
                } else {
                    $bairro = trim((string)($localidadeBairro['bairro'] ?? $bairro));
                    if ($municipio === '') {
                        $municipio = trim((string)($localidadeBairro['municipio'] ?? ''));
                    }
                }
            } catch (Throwable $e) {
                admin_log_event('Erro ao validar Bairro no create_negocio: ' . $e->getMessage());
                $formErrors[] = 'Nao foi possivel validar o Bairro agora.';
            }
        } elseif ($municipio !== '') {
            try {
                if (!admin_localidade_exists_municipio($municipio)) {
                    $formErrors[] = 'Municipio invalido. Selecione um Municipio da lista.';
                }
            } catch (Throwable $e) {
                admin_log_event('Erro ao validar Municipio no create_negocio: ' . $e->getMessage());
                $formErrors[] = 'Nao foi possivel validar o Municipio agora.';
            }
        }

        $clienteId = null;
        if ($origem === 'Cliente') {
            $clienteIdValue = filter_var($clienteIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($clienteIdValue === false) {
                $formErrors[] = 'Selecione um cliente valido na lista.';
            } else {
                try {
                    $stmtCliente = admin_db()->prepare('SELECT id, nome FROM clientes WHERE id = :id LIMIT 1');
                    $stmtCliente->execute(['id' => (int)$clienteIdValue]);
                    $cliente = $stmtCliente->fetch();
                    if (!is_array($cliente)) {
                        $formErrors[] = 'Cliente selecionado nao encontrado.';
                    } else {
                        $clienteId = (int)$cliente['id'];
                        $nomeCliente = (string)$cliente['nome'];
                    }
                } catch (Throwable $e) {
                    admin_log_event('Erro ao validar cliente no create_negocio: ' . $e->getMessage());
                    $formErrors[] = 'Nao foi possivel validar cliente agora.';
                }
            }
        } else {
            if ($nomeCliente === '' || strlen($nomeCliente) > 150) {
                $formErrors[] = 'Nome invalido.';
            }
        }

        $proximaAcao = admin_proxima_acao_por_status($status);
        if ($proximaAcao === '' || !admin_value_in_list($proximaAcao, RETEC_PROXIMA_ACAO)) {
            $formErrors[] = 'Nao foi possivel determinar a proxima acao para o status selecionado.';
        }

        if ($status === 'Venda Perdida') {
            if (!admin_value_in_list($motivoPerda, RETEC_MOTIVOS_PERDA)) {
                $formErrors[] = 'Motivo da perda obrigatorio para venda perdida.';
            }
        } else {
            $motivoPerda = '';
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

        if (in_array($status, ['Venda Perdida', 'Venda Cancelada'], true)) {
            $valorPerdido = $valorTotal ?? 0.0;
        } else {
            $valorPerdido = 0.0;
        }

        $valorPorCacamba = 0.0;
        if ($totalCacambas && $valorTotal !== null && $totalCacambas > 0) {
            $valorPorCacamba = round($valorTotal / $totalCacambas, 2);
        }

        $dataInicio = date('Y-m-d');
        $dataFim = admin_data_fim_por_status($status);

        if (!$formErrors) {
            try {
                $stmt = admin_db()->prepare(
                    'INSERT INTO negocios_comerciais (
                        vendedor_id, vendedor_nome, origem, forma_contato, data_inicio, data_fim,
                        status, proxima_acao, motivo_perda, cep, bairro, municipio, perfil, nome_cliente, cliente_id,
                        telefone, email, servico, total_cacambas, valor_total, valor_por_cacamba,
                        valor_perdido, observacao
                    ) VALUES (
                        :vendedor_id, :vendedor_nome, :origem, :forma_contato, :data_inicio, :data_fim,
                        :status, :proxima_acao, :motivo_perda, :cep, :bairro, :municipio, :perfil, :nome_cliente, :cliente_id,
                        :telefone, :email, :servico, :total_cacambas, :valor_total, :valor_por_cacamba,
                        :valor_perdido, :observacao
                    )'
                );

                $stmt->execute([
                    'vendedor_id' => (int)$currentUser['id'],
                    'vendedor_nome' => (string)$currentUser['display_name'],
                    'origem' => $origem,
                    'forma_contato' => $formaContato,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'status' => $status,
                    'proxima_acao' => $proximaAcao,
                    'motivo_perda' => $motivoPerda !== '' ? $motivoPerda : null,
                    'cep' => $cep !== '' ? $cep : null,
                    'bairro' => $bairro !== '' ? $bairro : null,
                    'municipio' => $municipio !== '' ? $municipio : null,
                    'perfil' => $perfil,
                    'nome_cliente' => $nomeCliente,
                    'cliente_id' => $clienteId,
                    'telefone' => $telefone !== '' ? $telefone : null,
                    'email' => $email !== '' ? $email : null,
                    'servico' => $servico,
                    'total_cacambas' => (int)$totalCacambas,
                    'valor_total' => $valorTotal,
                    'valor_por_cacamba' => $valorPorCacamba,
                    'valor_perdido' => $valorPerdido,
                    'observacao' => $observacao !== '' ? $observacao : null,
                ]);

                admin_set_flash('success', 'Registro salvo com sucesso.');
                admin_redirect('/admin/painel.php?sec=comercial');
            } catch (Throwable $e) {
                admin_log_event('Erro ao inserir negocio: ' . $e->getMessage());
                $formErrors[] = 'Nao foi possivel salvar no banco. Verifique se as tabelas foram atualizadas.';
            }
        }
    }

    if ($action === 'update_negocio') {
        if (!$isLogged || !$currentUser || !admin_can_access($currentUser, 'comercial')) {
            admin_set_flash('error', 'Acesso negado.');
            admin_redirect('/admin/painel.php?sec=comercial');
        }

        $csrfToken = (string)($_POST['csrf_token'] ?? '');
        if (!admin_csrf_validate('deal_update_form', $csrfToken)) {
            admin_set_flash('error', 'Sessao invalida. Recarregue e tente novamente.');
            admin_redirect('/admin/painel.php?sec=comercial');
        }

        $negocioId = filter_var((string)($_POST['negocio_id'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($negocioId === false) {
            admin_set_flash('error', 'Negocio invalido.');
            admin_redirect('/admin/painel.php?sec=comercial');
        }

        try {
            $query = 'SELECT * FROM negocios_comerciais WHERE id = :id';
            $params = ['id' => (int)$negocioId];
            if (!admin_is_admin($currentUser)) {
                $query .= ' AND vendedor_id = :vendedor_id';
                $params['vendedor_id'] = (int)$currentUser['id'];
            }
            $query .= ' LIMIT 1';

            $stmt = admin_db()->prepare($query);
            $stmt->execute($params);
            $existing = $stmt->fetch();
            if (!is_array($existing)) {
                admin_set_flash('error', 'Registro nao encontrado para edicao.');
                admin_redirect('/admin/painel.php?sec=comercial');
            }

            if ((string)$existing['status'] !== 'Em negociacao') {
                admin_set_flash('error', 'Somente registros em negociacao podem ser editados.');
                admin_redirect('/admin/painel.php?sec=comercial');
            }

            $origem = trim((string)($_POST['origem'] ?? ''));
            $formaContato = trim((string)($_POST['forma_contato'] ?? ''));
            $cep = admin_normalize_cep((string)($_POST['cep'] ?? ''));
            $bairro = trim((string)($_POST['bairro'] ?? ''));
            $municipio = trim((string)($_POST['municipio'] ?? ''));
            $status = trim((string)($_POST['status'] ?? ''));
            $motivoPerda = trim((string)($_POST['motivo_perda'] ?? ''));
            $perfil = trim((string)($_POST['perfil'] ?? ''));
            $nomeCliente = trim((string)($_POST['nome'] ?? ''));
            $clienteIdRaw = trim((string)($_POST['cliente_id'] ?? ''));
            $telefone = trim((string)($_POST['telefone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $servico = trim((string)($_POST['servico'] ?? ''));
            $observacao = trim((string)($_POST['observacao'] ?? ''));
            $totalCacambasRaw = trim((string)($_POST['total_cacambas'] ?? '0'));
            $valorTotalRaw = trim((string)($_POST['valor_total'] ?? '0'));
            $valorPerdidoRaw = trim((string)($_POST['valor_perdido'] ?? '0'));

            $editErrors = [];
            if (!admin_value_in_list($origem, RETEC_ORIGENS)) {
                $editErrors[] = 'Origem invalida.';
            }
            if (!admin_value_in_list($formaContato, RETEC_FORMAS_CONTATO)) {
                $editErrors[] = 'Forma de contato invalida.';
            }
            if (!in_array($status, ['Venda Perdida', 'Venda Realizada', 'Venda Cancelada'], true)) {
                $editErrors[] = 'Status de fechamento invalido.';
            }
            if (!admin_value_in_list($perfil, RETEC_PERFIS)) {
                $editErrors[] = 'Perfil invalido.';
            }
            if (!admin_value_in_list($servico, RETEC_SERVICOS)) {
                $editErrors[] = 'Servico invalido.';
            }
            if ($bairro !== '' && strlen($bairro) > 120) {
                $editErrors[] = 'Bairro deve ter no maximo 120 caracteres.';
            }
            if ($municipio !== '' && strlen($municipio) > 120) {
                $editErrors[] = 'Municipio deve ter no maximo 120 caracteres.';
            }
            if ($telefone !== '' && strlen($telefone) > 120) {
                $editErrors[] = 'Telefone invalido.';
            }
            if ($email !== '' && (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $editErrors[] = 'Email invalido.';
            }

            if ($cep !== '') {
                try {
                    $localidadeCep = admin_find_localidade_by_cep($cep);
                    if (!$localidadeCep) {
                        $editErrors[] = 'CEP invalido. Selecione um CEP valido da lista.';
                    } else {
                        $bairro = trim((string)($localidadeCep['bairro'] ?? ''));
                        $municipio = trim((string)($localidadeCep['municipio'] ?? ''));
                    }
                } catch (Throwable $e) {
                    admin_log_event('Erro ao validar CEP no update_negocio: ' . $e->getMessage());
                    $editErrors[] = 'Nao foi possivel validar o CEP agora.';
                }
            } elseif ($bairro !== '') {
                $cep = '';
                try {
                    $localidadeBairro = admin_find_localidade_by_bairro($bairro, $municipio !== '' ? $municipio : null);
                    if (!$localidadeBairro) {
                        $editErrors[] = 'Bairro invalido. Selecione um Bairro da lista.';
                    } else {
                        $bairro = trim((string)($localidadeBairro['bairro'] ?? $bairro));
                        if ($municipio === '') {
                            $municipio = trim((string)($localidadeBairro['municipio'] ?? ''));
                        }
                    }
                } catch (Throwable $e) {
                    admin_log_event('Erro ao validar Bairro no update_negocio: ' . $e->getMessage());
                    $editErrors[] = 'Nao foi possivel validar o Bairro agora.';
                }
            } elseif ($municipio !== '') {
                try {
                    if (!admin_localidade_exists_municipio($municipio)) {
                        $editErrors[] = 'Municipio invalido. Selecione um Municipio da lista.';
                    }
                } catch (Throwable $e) {
                    admin_log_event('Erro ao validar Municipio no update_negocio: ' . $e->getMessage());
                    $editErrors[] = 'Nao foi possivel validar o Municipio agora.';
                }
            }

            $clienteId = null;
            if ($origem === 'Cliente') {
                $clienteIdValue = filter_var($clienteIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($clienteIdValue === false) {
                    $editErrors[] = 'Selecione um cliente valido na lista.';
                } else {
                    $stmtCliente = admin_db()->prepare('SELECT id, nome FROM clientes WHERE id = :id LIMIT 1');
                    $stmtCliente->execute(['id' => (int)$clienteIdValue]);
                    $cliente = $stmtCliente->fetch();
                    if (!is_array($cliente)) {
                        $editErrors[] = 'Cliente selecionado nao encontrado.';
                    } else {
                        $clienteId = (int)$cliente['id'];
                        $nomeCliente = (string)$cliente['nome'];
                    }
                }
            } else {
                if ($nomeCliente === '' || strlen($nomeCliente) > 150) {
                    $editErrors[] = 'Nome invalido.';
                }
            }

            $proximaAcao = admin_proxima_acao_por_status($status);
            if ($proximaAcao === '' || !admin_value_in_list($proximaAcao, RETEC_PROXIMA_ACAO)) {
                $editErrors[] = 'Nao foi possivel determinar a proxima acao para o status selecionado.';
            }

            if ($status === 'Venda Perdida') {
                if (!admin_value_in_list($motivoPerda, RETEC_MOTIVOS_PERDA)) {
                    $editErrors[] = 'Motivo da perda obrigatorio para venda perdida.';
                }
            } else {
                $motivoPerda = '';
            }

            $totalCacambas = filter_var($totalCacambasRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($totalCacambas === false) {
                $editErrors[] = 'Total de cacambas invalido.';
            }

            $valorTotal = admin_parse_money($valorTotalRaw);
            $valorPerdido = admin_parse_money($valorPerdidoRaw);
            if ($valorTotal === null || $valorTotal < 0) {
                $editErrors[] = 'Valor total invalido.';
            }
            if ($valorPerdido === null || $valorPerdido < 0) {
                $editErrors[] = 'Valor perdido invalido.';
            }
            if (in_array($status, ['Venda Perdida', 'Venda Cancelada'], true)) {
                $valorPerdido = $valorTotal ?? 0.0;
            } else {
                $valorPerdido = 0.0;
            }

            $valorPorCacamba = 0.0;
            if ($totalCacambas && $valorTotal !== null && $totalCacambas > 0) {
                $valorPorCacamba = round($valorTotal / $totalCacambas, 2);
            }

            if ($editErrors) {
                admin_set_flash('error', implode(' | ', $editErrors));
                admin_redirect('/admin/painel.php?sec=comercial');
            }

            $stmtUpdate = admin_db()->prepare(
                'UPDATE negocios_comerciais
                SET
                    origem = :origem,
                    forma_contato = :forma_contato,
                    status = :status,
                    proxima_acao = :proxima_acao,
                    motivo_perda = :motivo_perda,
                    cep = :cep,
                    bairro = :bairro,
                    municipio = :municipio,
                    perfil = :perfil,
                    nome_cliente = :nome_cliente,
                    cliente_id = :cliente_id,
                    telefone = :telefone,
                    email = :email,
                    servico = :servico,
                    total_cacambas = :total_cacambas,
                    valor_total = :valor_total,
                    valor_por_cacamba = :valor_por_cacamba,
                    valor_perdido = :valor_perdido,
                    observacao = :observacao,
                    data_fim = :data_fim,
                    updated_at = NOW()
                WHERE id = :id'
            );

            $stmtUpdate->execute([
                'origem' => $origem,
                'forma_contato' => $formaContato,
                'status' => $status,
                'proxima_acao' => $proximaAcao,
                'motivo_perda' => $motivoPerda !== '' ? $motivoPerda : null,
                'cep' => $cep !== '' ? $cep : null,
                'bairro' => $bairro !== '' ? $bairro : null,
                'municipio' => $municipio !== '' ? $municipio : null,
                'perfil' => $perfil,
                'nome_cliente' => $nomeCliente,
                'cliente_id' => $clienteId,
                'telefone' => $telefone !== '' ? $telefone : null,
                'email' => $email !== '' ? $email : null,
                'servico' => $servico,
                'total_cacambas' => (int)$totalCacambas,
                'valor_total' => $valorTotal,
                'valor_por_cacamba' => $valorPorCacamba,
                'valor_perdido' => $valorPerdido,
                'observacao' => $observacao !== '' ? $observacao : null,
                'data_fim' => date('Y-m-d'),
                'id' => (int)$negocioId,
            ]);

            admin_set_flash('success', 'Registro atualizado com sucesso.');
            admin_redirect('/admin/painel.php?sec=comercial');
        } catch (Throwable $e) {
            admin_log_event('Erro ao atualizar negocio id=' . (string)$negocioId . ' detalhes=' . $e->getMessage());
            admin_set_flash('error', 'Nao foi possivel atualizar o registro agora.');
            admin_redirect('/admin/painel.php?sec=comercial');
        }
    }
}

$currentUser = admin_current_user();
$isLogged = is_array($currentUser);
$accessDenied = false;

if ($isLogged && $currentUser && $section === 'dashboard' && !admin_is_admin($currentUser)) {
    admin_set_flash('error', 'Acesso restrito. O Dashboard e exclusivo para perfil Admin.');
    admin_redirect('/admin/painel.php?sec=comercial');
}

if ($isLogged && $currentUser && $section === 'clientes' && !admin_is_admin($currentUser)) {
    admin_set_flash('error', 'Acesso restrito. A secao Clientes e exclusiva para perfil Admin.');
    admin_redirect('/admin/painel.php?sec=comercial');
}

if ($isLogged && $currentUser && !admin_can_access($currentUser, $section)) {
    $accessDenied = true;
}

$flash = admin_get_flash();

$dashboardPeriodo = (string)($_GET['periodo'] ?? 'mensal');
if (!in_array($dashboardPeriodo, ['mensal', 'trimestral', 'semestral', 'anual'], true)) {
    $dashboardPeriodo = 'mensal';
}
$dashboardRange = admin_dashboard_range($dashboardPeriodo);

$comercialStatusFiltro = trim((string)($_GET['filtro_status'] ?? ''));
if ($comercialStatusFiltro !== '' && !in_array($comercialStatusFiltro, RETEC_STATUS, true)) {
    $comercialStatusFiltro = '';
}

$dashboard = [
    'total_negocios' => 0,
    'total_cacambas' => 0,
    'valor_total' => 0,
    'em_negociacao' => 0,
    'venda_realizada' => 0,
    'venda_perdida' => 0,
    'venda_cancelada' => 0,
    'valor_total_realizada' => 0,
    'cacambas_realizada' => 0,
    'valor_total_perdida' => 0,
    'cacambas_perdida' => 0,
    'valor_total_cancelada' => 0,
    'cacambas_cancelada' => 0,
];

$financeiro = [
    'realizadas_total' => 0.0,
    'realizadas_media' => 0.0,
    'perdidas_total' => 0.0,
    'perdidas_media' => 0.0,
    'canceladas_total' => 0.0,
    'canceladas_media' => 0.0,
];

$operacional = [
    'forma_contato' => [],
    'origem' => [],
    'motivo_perda' => [],
    'perfil' => [],
    'servico' => [],
    'mensal_status' => [],
    'bairro_status' => [],
];

$recentDeals = [];
$clientes = [];

if ($isLogged && $currentUser) {
    try {
        $dashboardWhere = [
            'data_inicio >= :dash_inicio',
            'data_inicio <= :dash_fim',
        ];
        $dashboardParams = [
            'dash_inicio' => $dashboardRange['inicio'],
            'dash_fim' => $dashboardRange['fim'],
        ];
        if (!admin_is_admin($currentUser)) {
            $dashboardWhere[] = 'vendedor_id = :dash_vendedor_id';
            $dashboardParams['dash_vendedor_id'] = (int)$currentUser['id'];
        }
        $dashboardWhereClause = ' WHERE ' . implode(' AND ', $dashboardWhere) . ' ';

        $recentWhereParts = [];
        $recentQueryParams = [];
        if (!admin_is_admin($currentUser)) {
            $recentWhereParts[] = 'vendedor_id = :vendedor_id';
            $recentQueryParams['vendedor_id'] = (int)$currentUser['id'];
        }
        if ($section === 'comercial' && $comercialStatusFiltro !== '') {
            $recentWhereParts[] = 'status = :recent_status';
            $recentQueryParams['recent_status'] = $comercialStatusFiltro;
        }
        $recentWhereClause = $recentWhereParts ? (' WHERE ' . implode(' AND ', $recentWhereParts) . ' ') : '';

        $stmtDash = admin_db()->prepare(
            'SELECT
                COUNT(*) AS total_negocios,
                COALESCE(SUM(total_cacambas), 0) AS total_cacambas,
                COALESCE(SUM(valor_total), 0) AS valor_total,
                SUM(CASE WHEN status = "Em negociacao" THEN 1 ELSE 0 END) AS em_negociacao,
                SUM(CASE WHEN status = "Venda Realizada" THEN 1 ELSE 0 END) AS venda_realizada,
                SUM(CASE WHEN status = "Venda Perdida" THEN 1 ELSE 0 END) AS venda_perdida,
                SUM(CASE WHEN status = "Venda Cancelada" THEN 1 ELSE 0 END) AS venda_cancelada,
                COALESCE(SUM(CASE WHEN status = "Venda Realizada" THEN valor_total ELSE 0 END), 0) AS valor_total_realizada,
                COALESCE(SUM(CASE WHEN status = "Venda Realizada" THEN total_cacambas ELSE 0 END), 0) AS cacambas_realizada,
                COALESCE(SUM(CASE WHEN status = "Venda Perdida" THEN valor_total ELSE 0 END), 0) AS valor_total_perdida,
                COALESCE(SUM(CASE WHEN status = "Venda Perdida" THEN total_cacambas ELSE 0 END), 0) AS cacambas_perdida,
                COALESCE(SUM(CASE WHEN status = "Venda Cancelada" THEN valor_total ELSE 0 END), 0) AS valor_total_cancelada,
                COALESCE(SUM(CASE WHEN status = "Venda Cancelada" THEN total_cacambas ELSE 0 END), 0) AS cacambas_cancelada
            FROM negocios_comerciais' . $dashboardWhereClause
        );
        $stmtDash->execute($dashboardParams);
        $dashData = $stmtDash->fetch();
        if (is_array($dashData)) {
            $dashboard = array_merge($dashboard, $dashData);
        }

        $financeiro['realizadas_total'] = (float)$dashboard['valor_total_realizada'];
        $financeiro['realizadas_media'] = ((float)$dashboard['cacambas_realizada'] > 0)
            ? round(((float)$dashboard['valor_total_realizada'] / (float)$dashboard['cacambas_realizada']), 2)
            : 0.0;

        $financeiro['perdidas_total'] = (float)$dashboard['valor_total_perdida'];
        $financeiro['perdidas_media'] = ((float)$dashboard['cacambas_perdida'] > 0)
            ? round(((float)$dashboard['valor_total_perdida'] / (float)$dashboard['cacambas_perdida']), 2)
            : 0.0;

        $financeiro['canceladas_total'] = (float)$dashboard['valor_total_cancelada'];
        $financeiro['canceladas_media'] = ((float)$dashboard['cacambas_cancelada'] > 0)
            ? round(((float)$dashboard['valor_total_cancelada'] / (float)$dashboard['cacambas_cancelada']), 2)
            : 0.0;

        $stmtFormaContato = admin_db()->prepare(
            'SELECT forma_contato, COUNT(*) AS total
             FROM negocios_comerciais' . $dashboardWhereClause . '
             GROUP BY forma_contato
             ORDER BY total DESC'
        );
        $stmtFormaContato->execute($dashboardParams);
        foreach ($stmtFormaContato->fetchAll() as $row) {
            $operacional['forma_contato'][(string)$row['forma_contato']] = (int)$row['total'];
        }

        $stmtOrigem = admin_db()->prepare(
            'SELECT origem, COUNT(*) AS total
             FROM negocios_comerciais' . $dashboardWhereClause . '
             GROUP BY origem
             ORDER BY total DESC'
        );
        $stmtOrigem->execute($dashboardParams);
        foreach ($stmtOrigem->fetchAll() as $row) {
            $operacional['origem'][(string)$row['origem']] = (int)$row['total'];
        }

        $stmtMotivoPerda = admin_db()->prepare(
            'SELECT motivo_perda, COUNT(*) AS total
             FROM negocios_comerciais' . $dashboardWhereClause . ' AND TRIM(COALESCE(motivo_perda, "")) <> ""
             GROUP BY motivo_perda
             ORDER BY total DESC'
        );
        $stmtMotivoPerda->execute($dashboardParams);
        foreach ($stmtMotivoPerda->fetchAll() as $row) {
            $operacional['motivo_perda'][(string)$row['motivo_perda']] = (int)$row['total'];
        }

        $stmtPerfil = admin_db()->prepare(
            'SELECT perfil, COUNT(*) AS total
             FROM negocios_comerciais' . $dashboardWhereClause . '
             GROUP BY perfil
             ORDER BY total DESC'
        );
        $stmtPerfil->execute($dashboardParams);
        foreach ($stmtPerfil->fetchAll() as $row) {
            $operacional['perfil'][(string)$row['perfil']] = (int)$row['total'];
        }

        $stmtServico = admin_db()->prepare(
            'SELECT servico, COUNT(*) AS total
             FROM negocios_comerciais' . $dashboardWhereClause . '
             GROUP BY servico
             ORDER BY total DESC'
        );
        $stmtServico->execute($dashboardParams);
        foreach ($stmtServico->fetchAll() as $row) {
            $operacional['servico'][(string)$row['servico']] = (int)$row['total'];
        }

        $mensalStatusWhere = [
            'data_fim IS NOT NULL',
            'data_fim >= :ms_inicio',
            'data_fim <= :ms_fim',
        ];
        $mensalStatusParams = [
            'ms_inicio' => $dashboardRange['inicio'],
            'ms_fim' => $dashboardRange['fim'],
        ];
        if (!admin_is_admin($currentUser)) {
            $mensalStatusWhere[] = 'vendedor_id = :ms_vendedor_id';
            $mensalStatusParams['ms_vendedor_id'] = (int)$currentUser['id'];
        }

        $stmtMensalStatus = admin_db()->prepare(
            'SELECT DATE_FORMAT(data_fim, "%Y-%m") AS ano_mes, status, COUNT(*) AS total
             FROM negocios_comerciais
             WHERE ' . implode(' AND ', $mensalStatusWhere) . '
             GROUP BY ano_mes, status
             ORDER BY ano_mes ASC, status ASC'
        );
        $stmtMensalStatus->execute($mensalStatusParams);
        foreach ($stmtMensalStatus->fetchAll() as $row) {
            $mes = (string)($row['ano_mes'] ?? '');
            $status = trim((string)($row['status'] ?? ''));
            $total = (int)($row['total'] ?? 0);
            if ($mes === '' || $status === '') {
                continue;
            }
            $operacional['mensal_status'][] = [
                'mes' => $mes,
                'status' => $status,
                'total' => $total,
            ];
        }

        $mensalSemFechamentoWhere = [
            'status = "Em negociacao"',
            'data_fim IS NULL',
            'data_inicio >= :mf_inicio',
            'data_inicio <= :mf_fim',
        ];
        $mensalSemFechamentoParams = [
            'mf_inicio' => $dashboardRange['inicio'],
            'mf_fim' => $dashboardRange['fim'],
        ];
        if (!admin_is_admin($currentUser)) {
            $mensalSemFechamentoWhere[] = 'vendedor_id = :mf_vendedor_id';
            $mensalSemFechamentoParams['mf_vendedor_id'] = (int)$currentUser['id'];
        }

        $stmtMensalSemFechamento = admin_db()->prepare(
            'SELECT DATE_FORMAT(data_inicio, "%Y-%m") AS ano_mes, COUNT(*) AS total
             FROM negocios_comerciais
             WHERE ' . implode(' AND ', $mensalSemFechamentoWhere) . '
             GROUP BY ano_mes
             ORDER BY ano_mes ASC'
        );
        $stmtMensalSemFechamento->execute($mensalSemFechamentoParams);
        foreach ($stmtMensalSemFechamento->fetchAll() as $row) {
            $mes = (string)($row['ano_mes'] ?? '');
            $total = (int)($row['total'] ?? 0);
            if ($mes === '') {
                continue;
            }
            $operacional['mensal_status'][] = [
                'mes' => $mes,
                'status' => 'Sem fechamento',
                'total' => $total,
            ];
        }

        $stmtBairroStatus = admin_db()->prepare(
            'SELECT bairro, status, COUNT(*) AS total
             FROM negocios_comerciais' . $dashboardWhereClause . ' AND TRIM(COALESCE(bairro, "")) <> ""
             GROUP BY bairro, status
             ORDER BY bairro ASC, status ASC'
        );
        $stmtBairroStatus->execute($dashboardParams);
        foreach ($stmtBairroStatus->fetchAll() as $row) {
            $bairro = trim((string)($row['bairro'] ?? ''));
            $status = trim((string)($row['status'] ?? ''));
            $total = (int)($row['total'] ?? 0);
            if ($bairro === '' || $status === '') {
                continue;
            }
            $operacional['bairro_status'][] = [
                'bairro' => $bairro,
                'status' => $status,
                'total' => $total,
            ];
        }

        $stmtRecent = admin_db()->prepare(
            'SELECT
                id,
                vendedor_id,
                vendedor_nome,
                origem,
                forma_contato,
                data_inicio,
                data_fim,
                status,
                proxima_acao,
                motivo_perda,
                cep,
                bairro,
                municipio,
                perfil,
                nome_cliente,
                cliente_id,
                telefone,
                email,
                servico,
                total_cacambas,
                valor_total,
                valor_por_cacamba,
                valor_perdido,
                observacao,
                created_at
            FROM negocios_comerciais ' . $recentWhereClause . '
            ORDER BY id DESC
            LIMIT 12'
        );
        $stmtRecent->execute($recentQueryParams);
        $recentDeals = $stmtRecent->fetchAll();

        if (admin_is_admin($currentUser)) {
            $stmtClientes = admin_db()->query('SELECT id, nome, bairro, cep, created_at FROM clientes ORDER BY nome ASC');
            $clientes = $stmtClientes->fetchAll();
        }
    } catch (Throwable $e) {
        admin_log_event('Erro ao carregar dashboard/listagem: ' . $e->getMessage());
        if (in_array($section, ['dashboard', 'comercial', 'clientes'], true)) {
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
            max-width: 1400px;
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
        .btn-clientes { background: #7c3aed; }
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

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .filter-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f7fafc;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px 10px;
        }

        .filter-inline label {
            margin: 0;
            font-size: 0.82rem;
            color: #334155;
            font-weight: 700;
        }

        .filter-inline select {
            min-width: 160px;
            padding: 7px 9px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .span-2 { grid-column: span 2; }
        .span-3 { grid-column: span 3; }
        .span-4 { grid-column: span 4; }

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
            vertical-align: top;
        }
        th { color: var(--muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; }

        .records-table-wrap {
            width: 100%;
            max-width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 420px;
            background: #fff;
            margin-top: 12px;
        }

        .records-table {
            margin-top: 0;
            min-width: 2200px;
        }

        .records-table thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background: #fff;
        }

        .records-table th:first-child,
        .records-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background: #fff;
        }

        .records-table thead th:first-child {
            z-index: 4;
        }

        .hint {
            color: var(--muted);
            font-size: 0.82rem;
            margin-top: 6px;
        }

        .hidden {
            display: none !important;
        }

        .row-actions {
            display: flex;
            gap: 8px;
        }

        .btn-inline {
            border: 0;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-edit { background: #0f6ea7; color: #fff; }
        .btn-delete { background: #b42318; color: #fff; }

        .inline-edit-wrap {
            background: #f8fbff;
            border: 1px solid #dce4ec;
            border-radius: 10px;
            padding: 12px;
        }

        .money-readonly {
            background: #f1f5f9;
        }

        .money-loss-highlight {
            background: #fff1f2;
            color: #9f1239;
            font-weight: 700;
            border-radius: 6px;
            display: inline-block;
            padding: 2px 6px;
        }

        .cards-finance {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 10px;
        }

        .chart-grid-donuts {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-top: 10px;
        }

        .chart-grid-analytics {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 12px;
        }

        .chart-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
            background: #fff;
        }

        .chart-title {
            margin: 0 0 10px;
            font-size: 0.95rem;
            color: #334155;
        }

        .chart-wrap {
            max-width: 260px;
            height: 240px;
            margin: 0 auto;
        }

        .chart-wrap-wide {
            max-width: 100%;
            min-height: 300px;
        }

        .chart-card-wide {
            grid-column: span 2;
        }

        .matrix-wrap {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: auto;
            background: #fff;
            margin-top: 12px;
            max-height: 360px;
        }

        .matrix-table {
            margin-top: 0;
            min-width: 860px;
            font-size: 0.86rem;
        }

        .matrix-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
        }

        .matrix-table .is-total {
            font-weight: 700;
            background: #f1f5f9;
        }

        .matrix-table tr.top-bairro td {
            background: #eef9f2;
        }

        .matrix-table tr.top-bairro td:first-child {
            font-weight: 700;
            color: #11623f;
        }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .submit-secondary {
            background: #475569;
            color: #fff;
        }

        @media (max-width: 1200px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .span-4 { grid-column: span 2; }
            .cards-finance { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .chart-grid-donuts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .chart-grid-analytics { grid-template-columns: 1fr; }
            .chart-card-wide { grid-column: span 1; }
        }

        @media (max-width: 640px) {
            .cards { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .span-2, .span-3, .span-4 { grid-column: span 1; }
            .cards-finance { grid-template-columns: 1fr; }
            .chart-grid-donuts { grid-template-columns: 1fr; }
            .chart-grid-analytics { grid-template-columns: 1fr; }
            .chart-card-wide { grid-column: span 1; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            .records-table-wrap .records-table { display: table; white-space: nowrap; }
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
                <?php if (!$isLogged || ($isLogged && $currentUser && admin_is_admin($currentUser))): ?>
                    <a class="btn btn-dashboard" href="/admin/painel.php?sec=dashboard">Dashboard</a>
                    <a class="btn btn-clientes" href="/admin/painel.php?sec=clientes">Clientes</a>
                <?php endif; ?>
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
                    <div class="section-head">
                        <h2 class="section-title">Dashboard</h2>
                        <form method="get" class="filter-inline">
                            <input type="hidden" name="sec" value="dashboard">
                            <label for="periodo">Filtro</label>
                            <select id="periodo" name="periodo" onchange="this.form.submit()">
                                <option value="mensal" <?php echo $dashboardPeriodo === 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                                <option value="trimestral" <?php echo $dashboardPeriodo === 'trimestral' ? 'selected' : ''; ?>>Trimestral</option>
                                <option value="semestral" <?php echo $dashboardPeriodo === 'semestral' ? 'selected' : ''; ?>>Semestral</option>
                                <option value="anual" <?php echo $dashboardPeriodo === 'anual' ? 'selected' : ''; ?>>Anual</option>
                            </select>
                        </form>
                    </div>
                    <p class="hint">Periodo aplicado: <?php echo admin_h((string)$dashboardRange['label']); ?> (<?php echo admin_h((string)$dashboardRange['inicio']); ?> ate <?php echo admin_h((string)$dashboardRange['fim']); ?>)</p>

                    <div class="cards">
                        <div class="card primary"><span class="k">Total negocios</span><div class="v"><?php echo admin_h((string)$dashboard['total_negocios']); ?></div></div>
                        <div class="card green"><span class="k">Total cacambas</span><div class="v"><?php echo admin_h((string)$dashboard['total_cacambas']); ?></div></div>
                        <div class="card yellow"><span class="k">Valor total Cacambas</span><div class="v"><?php echo admin_h(number_format((float)$dashboard['valor_total'], 2, ',', '.')); ?></div></div>
                    </div>

                    <div class="cards">
                        <div class="card"><span class="k">Em negociacao</span><div class="v"><?php echo admin_h((string)$dashboard['em_negociacao']); ?></div></div>
                        <div class="card"><span class="k">Venda realizada</span><div class="v"><?php echo admin_h((string)$dashboard['venda_realizada']); ?></div></div>
                        <div class="card"><span class="k">Venda perdida</span><div class="v"><?php echo admin_h((string)$dashboard['venda_perdida']); ?></div></div>
                        <div class="card"><span class="k">Venda cancelada</span><div class="v"><?php echo admin_h((string)$dashboard['venda_cancelada']); ?></div></div>
                    </div>

                    <h3 class="section-title" style="margin-top: 18px;">Financeiro</h3>
                    <div class="cards-finance">
                        <div class="card green"><span class="k">Vendas Realizadas - Total (R$)</span><div class="v"><?php echo admin_h(number_format((float)$financeiro['realizadas_total'], 2, ',', '.')); ?></div></div>
                        <div class="card"><span class="k">Valor Medio (Realizadas) - R$</span><div class="v"><?php echo admin_h(number_format((float)$financeiro['realizadas_media'], 2, ',', '.')); ?></div></div>

                        <div class="card red"><span class="k">Vendas Perdidas - Total (R$)</span><div class="v"><?php echo admin_h(number_format((float)$financeiro['perdidas_total'], 2, ',', '.')); ?></div></div>
                        <div class="card"><span class="k">Valor Medio (Perdido) - R$</span><div class="v"><?php echo admin_h(number_format((float)$financeiro['perdidas_media'], 2, ',', '.')); ?></div></div>

                        <div class="card yellow"><span class="k">Vendas Canceladas - Total (R$)</span><div class="v"><?php echo admin_h(number_format((float)$financeiro['canceladas_total'], 2, ',', '.')); ?></div></div>
                        <div class="card"><span class="k">Valor Medio (Cancelado) - R$</span><div class="v"><?php echo admin_h(number_format((float)$financeiro['canceladas_media'], 2, ',', '.')); ?></div></div>
                    </div>

                    <h3 class="section-title" style="margin-top: 18px;">Operacional</h3>
                    <div class="chart-grid-donuts">
                        <div class="chart-card">
                            <h4 class="chart-title">Forma de Contato</h4>
                            <div class="chart-wrap"><canvas id="chartFormaContato"></canvas></div>
                        </div>
                        <div class="chart-card">
                            <h4 class="chart-title">Origem (Canais)</h4>
                            <div class="chart-wrap"><canvas id="chartOrigem"></canvas></div>
                        </div>
                        <div class="chart-card">
                            <h4 class="chart-title">Tipo de Perfil</h4>
                            <div class="chart-wrap"><canvas id="chartPerfil"></canvas></div>
                        </div>
                        <div class="chart-card">
                            <h4 class="chart-title">Serviço (Tipo de Caçamba)</h4>
                            <div class="chart-wrap"><canvas id="chartServico"></canvas></div>
                        </div>
                    </div>

                    <div class="chart-grid-analytics">
                        <div class="chart-card chart-card-wide">
                            <h4 class="chart-title">Negócios por Mês x Status (linha)</h4>
                            <div class="chart-wrap-wide"><canvas id="chartMensalStatusLinha"></canvas></div>
                        </div>
                        <div class="chart-card chart-card-wide">
                            <h4 class="chart-title">Comparativo por Status (area)</h4>
                            <div class="chart-wrap-wide"><canvas id="chartMensalStatusArea"></canvas></div>
                        </div>
                        <div class="chart-card chart-card-wide">
                            <h4 class="chart-title">Motivo da Perda</h4>
                            <div class="chart-wrap-wide"><canvas id="chartMotivoPerda"></canvas></div>
                        </div>
                    </div>

                    <?php
                        $statusHeader = ['Em negociacao', 'Venda Realizada', 'Venda Perdida', 'Venda Cancelada'];
                        $bairroMatrix = [];
                        foreach ($operacional['bairro_status'] as $item) {
                            $bairro = (string)($item['bairro'] ?? '');
                            $status = (string)($item['status'] ?? '');
                            $total = (int)($item['total'] ?? 0);
                            if ($bairro === '' || $status === '') {
                                continue;
                            }
                            if (!array_key_exists($bairro, $bairroMatrix)) {
                                $bairroMatrix[$bairro] = array_fill_keys($statusHeader, 0);
                            }
                            if (!array_key_exists($status, $bairroMatrix[$bairro])) {
                                $bairroMatrix[$bairro][$status] = 0;
                                if (!in_array($status, $statusHeader, true)) {
                                    $statusHeader[] = $status;
                                }
                            }
                            $bairroMatrix[$bairro][$status] = (int)$bairroMatrix[$bairro][$status] + $total;
                        }
                        $bairroMatrixRows = [];
                        foreach ($bairroMatrix as $bairroNome => $statusValues) {
                            $rowTotal = 0;
                            foreach ($statusHeader as $status) {
                                $rowTotal += (int)($statusValues[$status] ?? 0);
                            }
                            $bairroMatrixRows[] = [
                                'bairro' => $bairroNome,
                                'values' => $statusValues,
                                'total' => $rowTotal,
                            ];
                        }
                        usort($bairroMatrixRows, static function (array $a, array $b): int {
                            if ((int)$a['total'] === (int)$b['total']) {
                                return strcasecmp((string)$a['bairro'], (string)$b['bairro']);
                            }
                            return ((int)$b['total'] <=> (int)$a['total']);
                        });
                    ?>

                    <h3 class="section-title" style="margin-top: 18px;">Bairros x Status (quantidades)</h3>
                    <div class="matrix-wrap">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>Bairro</th>
                                    <?php foreach ($statusHeader as $status): ?>
                                        <th><?php echo admin_h($status); ?></th>
                                    <?php endforeach; ?>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$bairroMatrixRows): ?>
                                    <tr><td colspan="<?php echo admin_h((string)(count($statusHeader) + 2)); ?>">Sem dados para o período selecionado.</td></tr>
                                <?php else: ?>
                                    <?php
                                        $colTotals = array_fill_keys($statusHeader, 0);
                                        $grandTotal = 0;
                                    ?>
                                    <?php foreach ($bairroMatrixRows as $rowIndex => $rowItem): ?>
                                        <?php
                                            $bairroNome = (string)$rowItem['bairro'];
                                            $row = (array)$rowItem['values'];
                                            $rowTotal = 0;
                                            $isTopBairro = $rowIndex < 5;
                                        ?>
                                        <tr class="<?php echo $isTopBairro ? 'top-bairro' : ''; ?>">
                                            <td><?php echo admin_h((string)$bairroNome); ?></td>
                                            <?php foreach ($statusHeader as $status): ?>
                                                <?php
                                                    $value = (int)($row[$status] ?? 0);
                                                    $rowTotal += $value;
                                                    $colTotals[$status] = (int)$colTotals[$status] + $value;
                                                ?>
                                                <td><?php echo admin_h((string)$value); ?></td>
                                            <?php endforeach; ?>
                                            <?php $grandTotal += $rowTotal; ?>
                                            <td class="is-total"><?php echo admin_h((string)$rowTotal); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td class="is-total">Total geral</td>
                                        <?php foreach ($statusHeader as $status): ?>
                                            <td class="is-total"><?php echo admin_h((string)$colTotals[$status]); ?></td>
                                        <?php endforeach; ?>
                                        <td class="is-total"><?php echo admin_h((string)$grandTotal); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($section === 'clientes'): ?>
                    <h2 class="section-title">Clientes</h2>

                    <form method="post" action="/admin/painel.php?sec=clientes" class="form-grid" style="margin-bottom: 18px;">
                        <input type="hidden" name="action" value="create_cliente">
                        <input type="hidden" name="csrf_token" value="<?php echo admin_h(admin_csrf_token('cliente_form')); ?>">

                        <div>
                            <label for="cliente_nome">Nome</label>
                            <input type="text" id="cliente_nome" name="nome" maxlength="150" required>
                        </div>
                        <div>
                            <label for="cliente_bairro">Bairro</label>
                            <input type="text" id="cliente_bairro" name="bairro" maxlength="120" required>
                        </div>
                        <div>
                            <label for="cliente_cep">CEP</label>
                            <input type="text" id="cliente_cep" name="cep" maxlength="10" placeholder="00000-000" required>
                        </div>
                        <div>
                            <label>&nbsp;</label>
                            <button type="submit" class="submit">Salvar Cliente</button>
                        </div>
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Bairro</th>
                                <th>CEP</th>
                                <th>Criado em</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$clientes): ?>
                                <tr><td colspan="6">Nenhum cliente cadastrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><?php echo admin_h((string)$cliente['id']); ?></td>
                                        <td><?php echo admin_h((string)$cliente['nome']); ?></td>
                                        <td><?php echo admin_h((string)$cliente['bairro']); ?></td>
                                        <td><?php echo admin_h((string)$cliente['cep']); ?></td>
                                        <td><?php echo admin_h((string)$cliente['created_at']); ?></td>
                                        <td>
                                            <form method="post" action="/admin/painel.php?sec=clientes" onsubmit="return confirm('Excluir este cliente?');">
                                                <input type="hidden" name="action" value="delete_cliente">
                                                <input type="hidden" name="csrf_token" value="<?php echo admin_h(admin_csrf_token('delete_cliente_form')); ?>">
                                                <input type="hidden" name="cliente_id" value="<?php echo admin_h((string)$cliente['id']); ?>">
                                                <button type="submit" class="btn-inline btn-delete">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <h2 class="section-title">Comercial - Registro de negocios</h2>

                    <?php if ($formErrors): ?>
                        <div class="alert error">
                            <?php foreach ($formErrors as $error): ?>
                                <div><?php echo admin_h((string)$error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="/admin/painel.php?sec=comercial" class="deal-form" data-mode="create">
                        <input type="hidden" name="action" value="create_negocio">
                        <input type="hidden" name="csrf_token" value="<?php echo admin_h(admin_csrf_token('deal_form')); ?>">

                        <div class="form-grid">
                            <div>
                                <label>Vendedor (usuario logado)</label>
                                <input type="text" value="<?php echo admin_h((string)$currentUser['display_name']); ?>" readonly>
                            </div>

                            <div>
                                <label for="origem">Origem</label>
                                <select id="origem" name="origem" data-role="origem" required>
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
                                <label for="cep">CEP</label>
                                <input type="text" id="cep" name="cep" data-role="cep" list="ceps_datalist_create" maxlength="10" placeholder="00000-000" inputmode="numeric" autocomplete="off">
                                <datalist id="ceps_datalist_create"></datalist>
                            </div>

                            <div>
                                <label for="bairro">Bairro</label>
                                <input type="text" id="bairro" name="bairro" data-role="bairro" list="bairros_datalist_create" maxlength="120" autocomplete="off">
                                <datalist id="bairros_datalist_create"></datalist>
                            </div>

                            <div>
                                <label for="municipio">Municipio</label>
                                <input type="text" id="municipio" name="municipio" data-role="municipio" list="municipios_datalist_create" maxlength="120" autocomplete="off">
                                <datalist id="municipios_datalist_create"></datalist>
                            </div>

                            <div>
                                <label>Nome</label>
                                <input type="hidden" name="nome" data-role="nome-hidden">
                                <input type="hidden" name="cliente_id" data-role="cliente-id-hidden">

                                <div data-role="nome-text-wrap">
                                    <input type="text" data-role="nome-text" maxlength="150" placeholder="Digite o nome" autocomplete="off">
                                </div>

                                <div data-role="nome-client-wrap" class="hidden">
                                    <input type="text" data-role="nome-client-search" list="clientes_datalist_create" maxlength="150" placeholder="Digite para buscar cliente" autocomplete="off">
                                    <datalist id="clientes_datalist_create"></datalist>
                                    <div class="hint">Digite ao menos 2 letras para buscar cliente cadastrado.</div>
                                </div>
                            </div>

                            <div>
                                <label for="telefone">Telefone</label>
                                <input type="text" id="telefone" name="telefone" maxlength="120">
                            </div>

                            <div>
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" maxlength="150">
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
                                <input type="number" id="total_cacambas" name="total_cacambas" data-role="total-cacambas" min="0" step="1" value="0" required>
                            </div>

                            <div>
                                <label for="valor_total">Valor total cacambas (R$)</label>
                                <input type="text" id="valor_total" name="valor_total" data-role="valor-total" inputmode="decimal" placeholder="0,00" required>
                            </div>

                            <div>
                                <label for="valor_por_cacamba">Valor por cacamba (R$)</label>
                                <input type="text" id="valor_por_cacamba" data-role="valor-por-cacamba" class="money-readonly" inputmode="decimal" placeholder="0,00" readonly>
                            </div>

                            <div data-role="valor-perdido-wrap" class="hidden">
                                <label for="valor_perdido">Valor perdido (R$)</label>
                                <input type="text" id="valor_perdido" name="valor_perdido" data-role="valor-perdido" inputmode="decimal" placeholder="0,00">
                            </div>

                            <div class="span-4">
                                <label for="observacao">Observacao</label>
                                <textarea id="observacao" name="observacao" rows="3"></textarea>
                            </div>

                            <div>
                                <label for="status">Status</label>
                                <select id="status" name="status" data-role="status" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_STATUS as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div data-role="motivo-wrap" class="hidden">
                                <label for="motivo_perda">Motivo da perda</label>
                                <select id="motivo_perda" name="motivo_perda" data-role="motivo-perda">
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_MOTIVOS_PERDA as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div data-role="proxima-wrap" class="hidden">
                                <label for="proxima_acao">Proxima acao</label>
                                <select id="proxima_acao" name="proxima_acao" data-role="proxima-acao">
                                    <option value="">Selecione</option>
                                    <?php foreach (RETEC_PROXIMA_ACAO as $opt): ?>
                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="span-4 form-actions">
                                <button type="submit" class="submit">Salvar Registro</button>
                                <button type="button" class="submit submit-secondary" data-role="clear-form">Limpar</button>
                            </div>
                        </div>
                    </form>

                    <div class="section-head" style="margin-top:22px;">
                        <h3 class="section-title" style="margin:0;">Últimos Registros</h3>
                        <form method="get" class="filter-inline">
                            <input type="hidden" name="sec" value="comercial">
                            <label for="filtro_status">Status</label>
                            <select id="filtro_status" name="filtro_status" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <?php foreach (RETEC_STATUS as $opt): ?>
                                    <option value="<?php echo admin_h($opt); ?>" <?php echo $comercialStatusFiltro === $opt ? 'selected' : ''; ?>><?php echo admin_h($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Acoes</th>
                                <th>Vendedor</th>
                                <th>Data Inicio</th>
                                <th>Data Fim</th>
                                <th>Origem</th>
                                <th>Forma Contato</th>
                                <th>CEP</th>
                                <th>Bairro</th>
                                <th>Municipio</th>
                                <th>Nome</th>
                                <th>Telefone</th>
                                <th>Email</th>
                                <th>Perfil</th>
                                <th>Servico</th>
                                <th>Cacambas</th>
                                <th>Valor Total</th>
                                <th>Valor/Cacamba</th>
                                <th>Valor Perdido</th>
                                <th>Observacao</th>
                                <th>Status</th>
                                <th>Motivo Perda</th>
                                <th>Proxima Acao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recentDeals): ?>
                                <tr><td colspan="22">Nenhum registro encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentDeals as $deal): ?>
                                    <tr>
                                        <td>
                                            <?php if ((string)$deal['status'] === 'Em negociacao'): ?>
                                                <button type="button" class="btn-inline btn-edit" data-toggle-edit="edit-<?php echo admin_h((string)$deal['id']); ?>">Editar</button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo admin_h((string)$deal['vendedor_nome']); ?></td>
                                        <td><?php echo admin_h((string)$deal['data_inicio']); ?></td>
                                        <td><?php echo admin_h((string)$deal['data_fim']); ?></td>
                                        <td><?php echo admin_h((string)$deal['origem']); ?></td>
                                        <td><?php echo admin_h((string)$deal['forma_contato']); ?></td>
                                        <td><?php echo admin_h((string)$deal['cep']); ?></td>
                                        <td><?php echo admin_h((string)$deal['bairro']); ?></td>
                                        <td><?php echo admin_h((string)$deal['municipio']); ?></td>
                                        <td><?php echo admin_h((string)$deal['nome_cliente']); ?></td>
                                        <td><?php echo admin_h((string)$deal['telefone']); ?></td>
                                        <td><?php echo admin_h((string)$deal['email']); ?></td>
                                        <td><?php echo admin_h((string)$deal['perfil']); ?></td>
                                        <td><?php echo admin_h((string)$deal['servico']); ?></td>
                                        <td><?php echo admin_h((string)$deal['total_cacambas']); ?></td>
                                        <td>R$ <?php echo admin_h(number_format((float)$deal['valor_total'], 2, ',', '.')); ?></td>
                                        <td>R$ <?php echo admin_h(number_format((float)$deal['valor_por_cacamba'], 2, ',', '.')); ?></td>
                                        <td>
                                            <?php $hasValorPerdido = ((float)$deal['valor_perdido']) > 0; ?>
                                            <span class="<?php echo $hasValorPerdido ? 'money-loss-highlight' : ''; ?>">
                                                R$ <?php echo admin_h(number_format((float)$deal['valor_perdido'], 2, ',', '.')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo admin_h((string)$deal['observacao']); ?></td>
                                        <td><?php echo admin_h((string)$deal['status']); ?></td>
                                        <td><?php echo admin_h((string)$deal['motivo_perda']); ?></td>
                                        <td><?php echo admin_h((string)$deal['proxima_acao']); ?></td>
                                    </tr>
                                    <?php if ((string)$deal['status'] === 'Em negociacao'): ?>
                                        <tr id="edit-<?php echo admin_h((string)$deal['id']); ?>" class="hidden">
                                            <td colspan="22">
                                                <div class="inline-edit-wrap">
                                                    <form method="post" action="/admin/painel.php?sec=comercial" class="deal-form" data-mode="edit">
                                                        <input type="hidden" name="action" value="update_negocio">
                                                        <input type="hidden" name="csrf_token" value="<?php echo admin_h(admin_csrf_token('deal_update_form')); ?>">
                                                        <input type="hidden" name="negocio_id" value="<?php echo admin_h((string)$deal['id']); ?>">

                                                        <div class="form-grid">
                                                            <div>
                                                                <label>Origem</label>
                                                                <select name="origem" data-role="origem" required>
                                                                    <?php foreach (RETEC_ORIGENS as $opt): ?>
                                                                        <option value="<?php echo admin_h($opt); ?>" <?php echo ((string)$deal['origem'] === $opt) ? 'selected' : ''; ?>><?php echo admin_h($opt); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <div>
                                                                <label>Forma de contato</label>
                                                                <select name="forma_contato" required>
                                                                    <?php foreach (RETEC_FORMAS_CONTATO as $opt): ?>
                                                                        <option value="<?php echo admin_h($opt); ?>" <?php echo ((string)$deal['forma_contato'] === $opt) ? 'selected' : ''; ?>><?php echo admin_h($opt); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <div>
                                                                <label>CEP</label>
                                                                <?php $cepListId = 'ceps_datalist_edit_' . (string)$deal['id']; ?>
                                                                <input type="text" name="cep" data-role="cep" list="<?php echo admin_h($cepListId); ?>" maxlength="10" inputmode="numeric" value="<?php echo admin_h((string)$deal['cep']); ?>" autocomplete="off">
                                                                <datalist id="<?php echo admin_h($cepListId); ?>"></datalist>
                                                            </div>

                                                            <div>
                                                                <label>Bairro</label>
                                                                <?php $bairroListId = 'bairros_datalist_edit_' . (string)$deal['id']; ?>
                                                                <input type="text" name="bairro" data-role="bairro" list="<?php echo admin_h($bairroListId); ?>" maxlength="120" value="<?php echo admin_h((string)$deal['bairro']); ?>" autocomplete="off">
                                                                <datalist id="<?php echo admin_h($bairroListId); ?>"></datalist>
                                                            </div>

                                                            <div>
                                                                <label>Municipio</label>
                                                                <?php $municipioListId = 'municipios_datalist_edit_' . (string)$deal['id']; ?>
                                                                <input type="text" name="municipio" data-role="municipio" list="<?php echo admin_h($municipioListId); ?>" maxlength="120" value="<?php echo admin_h((string)$deal['municipio']); ?>" autocomplete="off">
                                                                <datalist id="<?php echo admin_h($municipioListId); ?>"></datalist>
                                                            </div>

                                                            <div>
                                                                <label>Nome</label>
                                                                <input type="hidden" name="nome" data-role="nome-hidden" value="<?php echo admin_h((string)$deal['nome_cliente']); ?>">
                                                                <input type="hidden" name="cliente_id" data-role="cliente-id-hidden" value="<?php echo admin_h((string)$deal['cliente_id']); ?>">

                                                                <div data-role="nome-text-wrap" class="<?php echo ((string)$deal['origem'] === 'Cliente') ? 'hidden' : ''; ?>">
                                                                    <input type="text" data-role="nome-text" maxlength="150" value="<?php echo admin_h((string)$deal['nome_cliente']); ?>">
                                                                </div>

                                                                <div data-role="nome-client-wrap" class="<?php echo ((string)$deal['origem'] === 'Cliente') ? '' : 'hidden'; ?>">
                                                                    <?php $datalistId = 'clientes_datalist_edit_' . (string)$deal['id']; ?>
                                                                    <input type="text" data-role="nome-client-search" list="<?php echo admin_h($datalistId); ?>" maxlength="150" value="<?php echo admin_h((string)$deal['nome_cliente']); ?>">
                                                                    <datalist id="<?php echo admin_h($datalistId); ?>"></datalist>
                                                                </div>
                                                            </div>

                                                            <div>
                                                                <label>Telefone</label>
                                                                <input type="text" name="telefone" maxlength="120" value="<?php echo admin_h((string)$deal['telefone']); ?>">
                                                            </div>

                                                            <div>
                                                                <label>Email</label>
                                                                <input type="email" name="email" maxlength="150" value="<?php echo admin_h((string)$deal['email']); ?>">
                                                            </div>

                                                            <div>
                                                                <label>Perfil</label>
                                                                <select name="perfil" required>
                                                                    <?php foreach (RETEC_PERFIS as $opt): ?>
                                                                        <option value="<?php echo admin_h($opt); ?>" <?php echo ((string)$deal['perfil'] === $opt) ? 'selected' : ''; ?>><?php echo admin_h($opt); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <div>
                                                                <label>Servico</label>
                                                                <select name="servico" required>
                                                                    <?php foreach (RETEC_SERVICOS as $opt): ?>
                                                                        <option value="<?php echo admin_h($opt); ?>" <?php echo ((string)$deal['servico'] === $opt) ? 'selected' : ''; ?>><?php echo admin_h($opt); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <div>
                                                                <label>Total cacambas</label>
                                                                <input type="number" name="total_cacambas" data-role="total-cacambas" min="0" step="1" value="<?php echo admin_h((string)$deal['total_cacambas']); ?>" required>
                                                            </div>

                                                            <div>
                                                                <label>Valor total (R$)</label>
                                                                <input type="text" name="valor_total" data-role="valor-total" inputmode="decimal" value="<?php echo admin_h(number_format((float)$deal['valor_total'], 2, ',', '.')); ?>" required>
                                                            </div>

                                                            <div>
                                                                <label>Valor por cacamba (R$)</label>
                                                                <input type="text" data-role="valor-por-cacamba" class="money-readonly" value="<?php echo admin_h(number_format((float)$deal['valor_por_cacamba'], 2, ',', '.')); ?>" readonly>
                                                            </div>

                                                            <div data-role="valor-perdido-wrap" class="hidden">
                                                                <label>Valor perdido (R$)</label>
                                                                <input type="text" name="valor_perdido" data-role="valor-perdido" inputmode="decimal" value="<?php echo admin_h(number_format((float)$deal['valor_perdido'], 2, ',', '.')); ?>">
                                                            </div>

                                                            <div class="span-4">
                                                                <label>Observacao</label>
                                                                <textarea name="observacao" rows="2"><?php echo admin_h((string)$deal['observacao']); ?></textarea>
                                                            </div>

                                                            <div>
                                                                <label>Status final</label>
                                                                <select name="status" data-role="status" required>
                                                                    <option value="Venda Perdida">Venda Perdida</option>
                                                                    <option value="Venda Realizada">Venda Realizada</option>
                                                                    <option value="Venda Cancelada">Venda Cancelada</option>
                                                                </select>
                                                            </div>

                                                            <div data-role="motivo-wrap" class="hidden">
                                                                <label>Motivo da perda</label>
                                                                <select name="motivo_perda" data-role="motivo-perda">
                                                                    <option value="">Selecione</option>
                                                                    <?php foreach (RETEC_MOTIVOS_PERDA as $opt): ?>
                                                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <div data-role="proxima-wrap" class="hidden">
                                                                <label>Proxima acao</label>
                                                                <select name="proxima_acao" data-role="proxima-acao">
                                                                    <?php foreach (RETEC_PROXIMA_ACAO as $opt): ?>
                                                                        <option value="<?php echo admin_h($opt); ?>"><?php echo admin_h($opt); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <div class="span-4 row-actions">
                                                                <button type="submit" class="submit">Salvar Atualizacao</button>
                                                                <button type="button" class="btn-inline btn-delete" data-toggle-edit="edit-<?php echo admin_h((string)$deal['id']); ?>">Cancelar</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        const dashboardSectionActive = <?php echo ($section === 'dashboard') ? 'true' : 'false'; ?>;
        const dashboardFormaContatoData = <?php echo json_encode($operacional['forma_contato'], JSON_UNESCAPED_UNICODE); ?>;
        const dashboardOrigemData = <?php echo json_encode($operacional['origem'], JSON_UNESCAPED_UNICODE); ?>;
        const dashboardPerfilData = <?php echo json_encode($operacional['perfil'], JSON_UNESCAPED_UNICODE); ?>;
        const dashboardServicoData = <?php echo json_encode($operacional['servico'], JSON_UNESCAPED_UNICODE); ?>;
        const dashboardMotivoPerdaData = <?php echo json_encode($operacional['motivo_perda'], JSON_UNESCAPED_UNICODE); ?>;
        const dashboardMensalStatusData = <?php echo json_encode($operacional['mensal_status'], JSON_UNESCAPED_UNICODE); ?>;

        function renderDashboardDonut(canvasId, rawData) {
            if (!dashboardSectionActive || typeof Chart === 'undefined') {
                return;
            }

            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                return;
            }

            let labels = Object.keys(rawData || {});
            let values = Object.values(rawData || {}).map((value) => Number(value || 0));
            if (!labels.length) {
                labels = ['Sem dados'];
                values = [1];
            }

            const zipped = labels.map((label, index) => ({ label, value: values[index] || 0 }));
            zipped.sort((a, b) => b.value - a.value);
            labels = zipped.map((item) => item.label);
            values = zipped.map((item) => item.value);

            const total = values.reduce((sum, value) => sum + value, 0);

            const palette = ['#0ea5e9', '#22c55e', '#f97316', '#a855f7', '#eab308', '#ef4444', '#14b8a6', '#64748b'];

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: labels.map((_, index) => palette[index % palette.length]),
                        borderWidth: 1,
                        borderColor: '#ffffff',
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                generateLabels(chart) {
                                    return labels.map((label, index) => {
                                        const value = values[index] || 0;
                                        const pct = total > 0 ? ((value / total) * 100) : 0;
                                        return {
                                            text: label + ' (' + pct.toFixed(1).replace('.', ',') + '%)',
                                            fillStyle: palette[index % palette.length],
                                            strokeStyle: '#ffffff',
                                            lineWidth: 1,
                                            hidden: false,
                                            index,
                                        };
                                    });
                                },
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    const value = Number(context.parsed || 0);
                                    const pct = total > 0 ? ((value / total) * 100) : 0;
                                    return context.label + ': ' + value + ' (' + pct.toFixed(1).replace('.', ',') + '%)';
                                },
                            },
                        },
                    },
                },
            });
        }

        function buildMonthlyStatusSeries(rawRows) {
            const rows = Array.isArray(rawRows) ? rawRows : [];
            const orderedStatus = ['Em negociacao', 'Venda Realizada', 'Venda Perdida', 'Venda Cancelada', 'Sem fechamento'];
            const monthSet = new Set();

            rows.forEach((row) => {
                const mes = String((row && row.mes) || '').trim();
                if (mes !== '') {
                    monthSet.add(mes);
                }
            });

            const months = Array.from(monthSet).sort();
            const statusMap = new Map();
            orderedStatus.forEach((status) => {
                statusMap.set(status, new Array(months.length).fill(0));
            });

            rows.forEach((row) => {
                const mes = String((row && row.mes) || '').trim();
                const status = String((row && row.status) || '').trim();
                const total = Number((row && row.total) || 0);
                const monthIndex = months.indexOf(mes);
                if (monthIndex === -1) {
                    return;
                }
                if (!statusMap.has(status)) {
                    statusMap.set(status, new Array(months.length).fill(0));
                }
                statusMap.get(status)[monthIndex] = Number.isFinite(total) ? total : 0;
            });

            const labels = months.map((month) => {
                const parts = month.split('-');
                if (parts.length !== 2) {
                    return month;
                }
                return parts[1] + '/' + parts[0];
            });

            return {
                labels,
                datasets: Array.from(statusMap.entries()).map(([status, data]) => ({ status, data })),
            };
        }

        function renderDashboardStatusLine(canvasId, rawRows) {
            if (!dashboardSectionActive || typeof Chart === 'undefined') {
                return;
            }

            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                return;
            }

            const series = buildMonthlyStatusSeries(rawRows);
            const linePalette = {
                'Em negociacao': '#0ea5e9',
                'Venda Realizada': '#22c55e',
                'Venda Perdida': '#ef4444',
                'Venda Cancelada': '#f59e0b',
                'Sem fechamento': '#8b5cf6',
            };

            const datasets = (series.datasets.length ? series.datasets : [{ status: 'Sem dados', data: [0] }]).map((item) => ({
                label: item.status,
                data: item.data,
                borderColor: linePalette[item.status] || '#64748b',
                backgroundColor: linePalette[item.status] || '#64748b',
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 4,
                fill: false,
                tension: 0.25,
            }));

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: series.labels.length ? series.labels : ['Sem dados'],
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                },
            });
        }

        function renderDashboardStatusArea(canvasId, rawRows) {
            if (!dashboardSectionActive || typeof Chart === 'undefined') {
                return;
            }

            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                return;
            }

            const series = buildMonthlyStatusSeries(rawRows);
            const areaPalette = {
                'Em negociacao': 'rgba(14, 165, 233, 0.25)',
                'Venda Realizada': 'rgba(34, 197, 94, 0.25)',
                'Venda Perdida': 'rgba(239, 68, 68, 0.25)',
                'Venda Cancelada': 'rgba(245, 158, 11, 0.25)',
                'Sem fechamento': 'rgba(139, 92, 246, 0.25)',
            };
            const areaBorder = {
                'Em negociacao': '#0ea5e9',
                'Venda Realizada': '#22c55e',
                'Venda Perdida': '#ef4444',
                'Venda Cancelada': '#f59e0b',
                'Sem fechamento': '#8b5cf6',
            };

            const datasets = (series.datasets.length ? series.datasets : [{ status: 'Sem dados', data: [0] }]).map((item) => ({
                label: item.status,
                data: item.data,
                borderColor: areaBorder[item.status] || '#64748b',
                backgroundColor: areaPalette[item.status] || 'rgba(100, 116, 139, 0.25)',
                borderWidth: 2,
                fill: true,
                tension: 0.45,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointHitRadius: 10,
            }));

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: series.labels.length ? series.labels : ['Sem dados'],
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    elements: {
                        line: {
                            cubicInterpolationMode: 'monotone',
                        },
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(148, 163, 184, 0.12)',
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(148, 163, 184, 0.18)',
                            },
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                },
            });
        }

        function renderDashboardHorizontalBar(canvasId, rawData) {
            if (!dashboardSectionActive || typeof Chart === 'undefined') {
                return;
            }

            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                return;
            }

            let labels = Object.keys(rawData || {});
            let values = Object.values(rawData || {}).map((value) => Number(value || 0));
            if (!labels.length) {
                labels = ['Sem dados'];
                values = [0];
            }

            const palette = ['#2563eb', '#0ea5e9', '#22c55e', '#f97316', '#eab308', '#ef4444', '#a855f7', '#14b8a6', '#64748b'];

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Quantidade',
                        data: values,
                        backgroundColor: labels.map((_, index) => palette[index % palette.length]),
                        borderRadius: 8,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                },
            });
        }

        function parseMoney(value) {
            if (!value) {
                return 0;
            }
            let normalized = String(value).trim().replace(/R\$/g, '').replace(/\s/g, '');
            if (normalized.includes(',') && normalized.includes('.')) {
                normalized = normalized.replace(/\./g, '').replace(',', '.');
            } else if (normalized.includes(',')) {
                normalized = normalized.replace(',', '.');
            }
            const num = Number(normalized);
            return Number.isFinite(num) ? num : 0;
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function autoActionByStatus(status) {
            if (status === 'Venda Perdida') return 'Pos-Perda';
            if (status === 'Em negociacao') return 'Acompanhar Follow-up';
            if (status === 'Venda Realizada') return 'Pos-Venda';
            if (status === 'Venda Cancelada') return 'Pos-Perda';
            return '';
        }

        function wireDealForm(form) {
            const origem = form.querySelector('[data-role="origem"]');
            const nomeTextWrap = form.querySelector('[data-role="nome-text-wrap"]');
            const nomeClientWrap = form.querySelector('[data-role="nome-client-wrap"]');
            const nomeText = form.querySelector('[data-role="nome-text"]');
            const nomeClientSearch = form.querySelector('[data-role="nome-client-search"]');
            const nomeHidden = form.querySelector('[data-role="nome-hidden"]');
            const clienteIdHidden = form.querySelector('[data-role="cliente-id-hidden"]');

            const totalCacambas = form.querySelector('[data-role="total-cacambas"]');
            const valorTotal = form.querySelector('[data-role="valor-total"]');
            const valorPorCacamba = form.querySelector('[data-role="valor-por-cacamba"]');

            const status = form.querySelector('[data-role="status"]');
            const motivoWrap = form.querySelector('[data-role="motivo-wrap"]');
            const proximaWrap = form.querySelector('[data-role="proxima-wrap"]');
            const motivoPerda = form.querySelector('[data-role="motivo-perda"]');
            const proximaAcao = form.querySelector('[data-role="proxima-acao"]');
            const valorPerdidoWrap = form.querySelector('[data-role="valor-perdido-wrap"]');
            const valorPerdidoInput = form.querySelector('[data-role="valor-perdido"]');

            const cepInput = form.querySelector('[data-role="cep"]');
            const bairroInput = form.querySelector('[data-role="bairro"]');
            const municipioInput = form.querySelector('[data-role="municipio"]');

            const clientMap = new Map();
            const cepMap = new Map();
            const bairroToMunicipioMap = new Map();
            const municipioSet = new Set();

            let debounceTimer = null;
            let bairroTimer = null;
            let municipioTimer = null;
            let cepTimer = null;

            let cepValidated = !cepInput || String(cepInput.value || '').trim() === '';
            let bairroValidated = !bairroInput || String(bairroInput.value || '').trim() === '';
            let municipioValidated = !municipioInput || String(municipioInput.value || '').trim() === '';

            function normalizeCepDigits(value) {
                return String(value || '').replace(/\D/g, '');
            }

            function formatCep(value) {
                const digits = normalizeCepDigits(value);
                if (digits.length !== 8) {
                    return String(value || '');
                }
                return digits.replace(/(\d{5})(\d{3})/, '$1-$2');
            }

            function syncNome() {
                if (!nomeHidden) {
                    return;
                }
                if (origem && origem.value === 'Cliente') {
                    nomeHidden.value = nomeClientSearch ? nomeClientSearch.value.trim() : '';
                } else {
                    nomeHidden.value = nomeText ? nomeText.value.trim() : '';
                    if (clienteIdHidden) {
                        clienteIdHidden.value = '';
                    }
                }
            }

            function refreshNomeMode() {
                if (!origem) {
                    return;
                }
                const isCliente = origem.value === 'Cliente';
                if (nomeTextWrap) {
                    nomeTextWrap.classList.toggle('hidden', isCliente);
                }
                if (nomeClientWrap) {
                    nomeClientWrap.classList.toggle('hidden', !isCliente);
                }
                syncNome();
            }

            function refreshValorPorCacamba() {
                if (!totalCacambas || !valorTotal || !valorPorCacamba) {
                    return;
                }
                const qtd = parseInt(totalCacambas.value || '0', 10);
                const total = parseMoney(valorTotal.value || '0');
                const calc = qtd > 0 ? (total / qtd) : 0;
                valorPorCacamba.value = formatMoney(calc);

                if (status && valorPerdidoInput && (status.value === 'Venda Perdida' || status.value === 'Venda Cancelada')) {
                    valorPerdidoInput.value = formatMoney(total);
                }
            }

            function refreshStatusRules() {
                if (!status) {
                    return;
                }
                const value = status.value;
                const next = autoActionByStatus(value);

                if (value === 'Venda Perdida') {
                    if (motivoWrap) motivoWrap.classList.remove('hidden');
                    if (proximaWrap) proximaWrap.classList.remove('hidden');
                    if (motivoPerda) motivoPerda.required = true;
                    if (valorPerdidoWrap) valorPerdidoWrap.classList.remove('hidden');
                    if (valorPerdidoInput) {
                        valorPerdidoInput.required = true;
                        valorPerdidoInput.readOnly = true;
                        valorPerdidoInput.value = formatMoney(parseMoney(valorTotal ? valorTotal.value : '0'));
                    }
                } else if (value === 'Venda Cancelada') {
                    if (motivoWrap) motivoWrap.classList.add('hidden');
                    if (proximaWrap) proximaWrap.classList.remove('hidden');
                    if (motivoPerda) {
                        motivoPerda.required = false;
                        motivoPerda.value = '';
                    }
                    if (valorPerdidoWrap) valorPerdidoWrap.classList.remove('hidden');
                    if (valorPerdidoInput) {
                        valorPerdidoInput.required = true;
                        valorPerdidoInput.readOnly = true;
                        valorPerdidoInput.value = formatMoney(parseMoney(valorTotal ? valorTotal.value : '0'));
                    }
                } else if (value === 'Em negociacao' || value === 'Venda Realizada') {
                    if (motivoWrap) motivoWrap.classList.add('hidden');
                    if (proximaWrap) proximaWrap.classList.remove('hidden');
                    if (motivoPerda) {
                        motivoPerda.required = false;
                        motivoPerda.value = '';
                    }
                    if (valorPerdidoWrap) valorPerdidoWrap.classList.add('hidden');
                    if (valorPerdidoInput) {
                        valorPerdidoInput.required = false;
                        valorPerdidoInput.readOnly = false;
                        valorPerdidoInput.value = '0,00';
                    }
                } else {
                    if (motivoWrap) motivoWrap.classList.add('hidden');
                    if (proximaWrap) proximaWrap.classList.add('hidden');
                    if (motivoPerda) {
                        motivoPerda.required = false;
                        motivoPerda.value = '';
                    }
                    if (valorPerdidoWrap) valorPerdidoWrap.classList.add('hidden');
                    if (valorPerdidoInput) {
                        valorPerdidoInput.required = false;
                        valorPerdidoInput.readOnly = false;
                        valorPerdidoInput.value = '0,00';
                    }
                }

                if (proximaAcao && next !== '') {
                    proximaAcao.value = next;
                }
            }

            function applyLocalidade(cepValue, bairroValue, municipioValue, mode) {
                if (cepInput) {
                    cepInput.value = cepValue !== '' ? formatCep(cepValue) : '';
                }
                if (bairroInput) {
                    bairroInput.value = bairroValue || '';
                }
                if (municipioInput) {
                    municipioInput.value = municipioValue || '';
                }

                if (mode === 'cep') {
                    cepValidated = true;
                    bairroValidated = String(bairroValue || '').trim() !== '';
                    municipioValidated = String(municipioValue || '').trim() !== '';
                    return;
                }

                if (mode === 'bairro') {
                    if (cepInput) {
                        cepInput.value = '';
                    }
                    cepValidated = true;
                    bairroValidated = String(bairroValue || '').trim() !== '';
                    municipioValidated = String(municipioValue || '').trim() !== '';
                    return;
                }

                if (mode === 'municipio') {
                    cepValidated = !cepInput || String(cepInput.value || '').trim() === '';
                    bairroValidated = !bairroInput || String(bairroInput.value || '').trim() === '';
                    municipioValidated = String(municipioValue || '').trim() !== '';
                }
            }

            async function searchClientes(term) {
                if (!nomeClientSearch) {
                    return;
                }
                const listId = nomeClientSearch.getAttribute('list');
                if (!listId) {
                    return;
                }
                const datalist = document.getElementById(listId);
                if (!datalist) {
                    return;
                }

                if (term.length < 2) {
                    datalist.innerHTML = '';
                    clientMap.clear();
                    if (clienteIdHidden) {
                        clienteIdHidden.value = '';
                    }
                    return;
                }

                try {
                    const response = await fetch('/admin/clientes_search.php?q=' + encodeURIComponent(term));
                    if (!response.ok) {
                        return;
                    }
                    const data = await response.json();
                    const items = Array.isArray(data.items) ? data.items : [];

                    clientMap.clear();
                    datalist.innerHTML = '';

                    items.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.nome || '';
                        option.label = [item.bairro || '', item.cep || ''].filter(Boolean).join(' - ');
                        datalist.appendChild(option);
                        clientMap.set(option.value, String(item.id || ''));
                    });

                    const selectedId = clientMap.get(nomeClientSearch.value.trim()) || '';
                    if (clienteIdHidden) {
                        clienteIdHidden.value = selectedId;
                    }
                } catch (e) {
                    // Sem throw para nao quebrar o fluxo do formulario.
                }
            }

            async function searchLocalidades(type, term) {
                const minLength = type === 'cep' ? 3 : 2;
                if (term.length < minLength) {
                    return [];
                }

                try {
                    const response = await fetch('/admin/localidades_search.php?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(term));
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return Array.isArray(data.items) ? data.items : [];
                } catch (e) {
                    return [];
                }
            }

            async function searchCeps(term) {
                if (!cepInput) {
                    return;
                }
                const listId = cepInput.getAttribute('list');
                if (!listId) {
                    return;
                }
                const datalist = document.getElementById(listId);
                if (!datalist) {
                    return;
                }

                const queryDigits = normalizeCepDigits(term);
                if (queryDigits.length < 3) {
                    datalist.innerHTML = '';
                    cepMap.clear();
                    return;
                }

                const items = await searchLocalidades('cep', queryDigits);
                datalist.innerHTML = '';
                cepMap.clear();

                items.forEach((item) => {
                    const cepValue = formatCep(item.cep || '');
                    if (cepValue === '') {
                        return;
                    }
                    const bairro = String(item.bairro || '').trim();
                    const municipio = String(item.municipio || '').trim();

                    const option = document.createElement('option');
                    option.value = cepValue;
                    option.label = [bairro, municipio].filter(Boolean).join(' - ');
                    datalist.appendChild(option);

                    cepMap.set(cepValue, {
                        cep: cepValue,
                        bairro,
                        municipio,
                    });
                });

                const currentCep = formatCep(String(cepInput.value || '').trim());
                cepValidated = currentCep === '' || cepMap.has(currentCep);
            }

            async function searchBairros(term) {
                if (!bairroInput) {
                    return;
                }
                const listId = bairroInput.getAttribute('list');
                if (!listId) {
                    return;
                }
                const datalist = document.getElementById(listId);
                if (!datalist) {
                    return;
                }

                if (term.length < 2) {
                    datalist.innerHTML = '';
                    bairroToMunicipioMap.clear();
                    return;
                }

                const items = await searchLocalidades('bairro', term);
                datalist.innerHTML = '';
                bairroToMunicipioMap.clear();

                items.forEach((item) => {
                    const bairro = String(item.bairro || '').trim();
                    const municipio = String(item.municipio || '').trim();
                    if (bairro === '') {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = bairro;
                    option.label = municipio !== '' ? municipio : 'Municipio nao informado';
                    datalist.appendChild(option);

                    if (municipio !== '') {
                        bairroToMunicipioMap.set(bairro.toLowerCase(), municipio);
                    }
                });

                const currentBairro = String(bairroInput.value || '').trim().toLowerCase();
                if (currentBairro !== '' && bairroToMunicipioMap.has(currentBairro)) {
                    bairroValidated = true;
                    if (municipioInput && String(municipioInput.value || '').trim() === '') {
                        municipioInput.value = bairroToMunicipioMap.get(currentBairro) || '';
                    }
                }
            }

            async function searchMunicipios(term) {
                if (!municipioInput) {
                    return;
                }
                const listId = municipioInput.getAttribute('list');
                if (!listId) {
                    return;
                }
                const datalist = document.getElementById(listId);
                if (!datalist) {
                    return;
                }

                if (term.length < 2) {
                    datalist.innerHTML = '';
                    municipioSet.clear();
                    return;
                }

                const items = await searchLocalidades('municipio', term);
                datalist.innerHTML = '';
                municipioSet.clear();

                items.forEach((item) => {
                    const municipio = String(item.municipio || '').trim();
                    if (municipio === '') {
                        return;
                    }
                    const option = document.createElement('option');
                    option.value = municipio;
                    datalist.appendChild(option);
                    municipioSet.add(municipio.toLowerCase());
                });

                const currentMunicipio = String(municipioInput.value || '').trim().toLowerCase();
                municipioValidated = currentMunicipio === '' || municipioSet.has(currentMunicipio);
            }

            async function lookupCepInBackend(cepRaw) {
                const cepDigits = normalizeCepDigits(cepRaw);
                if (cepDigits.length !== 8) {
                    return null;
                }

                try {
                    const response = await fetch('/admin/cep_lookup.php?cep=' + encodeURIComponent(cepDigits));
                    if (!response.ok) {
                        return null;
                    }
                    const data = await response.json();
                    const item = data && data.item ? data.item : null;
                    if (!item) {
                        return null;
                    }

                    return {
                        cep: formatCep(item.cep || cepDigits),
                        bairro: String(item.bairro || '').trim(),
                        municipio: String(item.municipio || '').trim(),
                    };
                } catch (e) {
                    return null;
                }
            }

            if (origem) {
                origem.addEventListener('change', refreshNomeMode);
            }

            if (nomeText) {
                nomeText.addEventListener('input', syncNome);
            }

            if (nomeClientSearch) {
                nomeClientSearch.addEventListener('input', function () {
                    syncNome();
                    if (clienteIdHidden) {
                        clienteIdHidden.value = clientMap.get(nomeClientSearch.value.trim()) || '';
                    }

                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function () {
                        searchClientes(nomeClientSearch.value.trim());
                    }, 300);
                });
                nomeClientSearch.addEventListener('change', function () {
                    syncNome();
                    if (clienteIdHidden) {
                        clienteIdHidden.value = clientMap.get(nomeClientSearch.value.trim()) || '';
                    }
                });
            }

            if (cepInput) {
                cepInput.addEventListener('input', function () {
                    cepValidated = String(cepInput.value || '').trim() === '';
                    clearTimeout(cepTimer);
                    cepTimer = setTimeout(function () {
                        searchCeps(String(cepInput.value || '').trim());
                    }, 250);
                });

                cepInput.addEventListener('change', async function () {
                    const typed = formatCep(String(cepInput.value || '').trim());
                    if (typed !== '') {
                        cepInput.value = typed;
                    }

                    const cached = cepMap.get(typed);
                    if (cached) {
                        applyLocalidade(cached.cep, cached.bairro, cached.municipio, 'cep');
                        return;
                    }

                    const item = await lookupCepInBackend(typed);
                    if (item) {
                        applyLocalidade(item.cep, item.bairro, item.municipio, 'cep');
                        return;
                    }

                    cepValidated = String(cepInput.value || '').trim() === '';
                });
            }

            if (bairroInput) {
                bairroInput.addEventListener('input', function () {
                    bairroValidated = String(bairroInput.value || '').trim() === '';
                    if (cepInput) {
                        cepInput.value = '';
                    }
                    cepValidated = true;

                    clearTimeout(bairroTimer);
                    bairroTimer = setTimeout(function () {
                        searchBairros(String(bairroInput.value || '').trim());
                    }, 250);
                });

                bairroInput.addEventListener('change', function () {
                    const selectedMunicipio = bairroToMunicipioMap.get(String(bairroInput.value || '').trim().toLowerCase()) || '';
                    if (selectedMunicipio !== '') {
                        applyLocalidade('', String(bairroInput.value || '').trim(), selectedMunicipio, 'bairro');
                        return;
                    }
                    bairroValidated = String(bairroInput.value || '').trim() === '';
                });
            }

            if (municipioInput) {
                municipioInput.addEventListener('input', function () {
                    municipioValidated = String(municipioInput.value || '').trim() === '';

                    clearTimeout(municipioTimer);
                    municipioTimer = setTimeout(function () {
                        searchMunicipios(String(municipioInput.value || '').trim());
                    }, 250);
                });

                municipioInput.addEventListener('change', function () {
                    const typed = String(municipioInput.value || '').trim().toLowerCase();
                    if (typed !== '' && municipioSet.has(typed)) {
                        municipioValidated = true;
                        return;
                    }
                    municipioValidated = String(municipioInput.value || '').trim() === '';
                });
            }

            if (totalCacambas) {
                totalCacambas.addEventListener('input', refreshValorPorCacamba);
            }
            if (valorTotal) {
                valorTotal.addEventListener('input', refreshValorPorCacamba);
            }

            if (status) {
                status.addEventListener('change', refreshStatusRules);
            }

            const clearBtn = form.querySelector('[data-role="clear-form"]');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    form.reset();
                    if (nomeHidden) nomeHidden.value = '';
                    if (clienteIdHidden) clienteIdHidden.value = '';
                    if (cepInput) cepInput.value = '';
                    if (bairroInput) bairroInput.value = '';
                    if (municipioInput) municipioInput.value = '';
                    cepValidated = true;
                    bairroValidated = true;
                    municipioValidated = true;

                    refreshNomeMode();
                    refreshValorPorCacamba();
                    refreshStatusRules();
                    syncNome();
                });
            }

            form.addEventListener('submit', function (event) {
                syncNome();

                if (origem && origem.value === 'Cliente' && clienteIdHidden && clienteIdHidden.value === '') {
                    event.preventDefault();
                    alert('Selecione um cliente valido na lista para a origem Cliente.');
                    return;
                }

                if (origem && origem.value !== 'Cliente' && nomeHidden && nomeHidden.value.trim() === '') {
                    event.preventDefault();
                    alert('Informe o nome do cliente.');
                    return;
                }

                if (bairroInput && String(bairroInput.value || '').trim() !== '' && !bairroValidated) {
                    event.preventDefault();
                    alert('Selecione um Bairro valido na lista.');
                    return;
                }

                if (municipioInput && String(municipioInput.value || '').trim() !== '' && !municipioValidated) {
                    event.preventDefault();
                    alert('Selecione um Municipio valido na lista.');
                    return;
                }

                if (cepInput && String(cepInput.value || '').trim() !== '' && !cepValidated) {
                    event.preventDefault();
                    alert('Informe um CEP valido para preencher localidade.');
                }
            });

            refreshNomeMode();
            refreshValorPorCacamba();
            refreshStatusRules();
            syncNome();

            if (origem && origem.value === 'Cliente' && nomeClientSearch && nomeClientSearch.value.trim().length >= 2) {
                searchClientes(nomeClientSearch.value.trim());
            }

            if (cepInput && normalizeCepDigits(cepInput.value).length >= 3) {
                searchCeps(cepInput.value);
            }
            if (bairroInput && String(bairroInput.value || '').trim().length >= 2) {
                searchBairros(String(bairroInput.value || '').trim());
            }
            if (municipioInput && String(municipioInput.value || '').trim().length >= 2) {
                searchMunicipios(String(municipioInput.value || '').trim());
            }
        }

        document.querySelectorAll('.deal-form').forEach(wireDealForm);

        document.querySelectorAll('[data-toggle-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const rowId = btn.getAttribute('data-toggle-edit');
                if (!rowId) {
                    return;
                }
                const row = document.getElementById(rowId);
                if (!row) {
                    return;
                }
                row.classList.toggle('hidden');
            });
        });

        renderDashboardDonut('chartFormaContato', dashboardFormaContatoData);
        renderDashboardDonut('chartOrigem', dashboardOrigemData);
        renderDashboardDonut('chartPerfil', dashboardPerfilData);
        renderDashboardDonut('chartServico', dashboardServicoData);
        renderDashboardHorizontalBar('chartMotivoPerda', dashboardMotivoPerdaData);
        renderDashboardStatusLine('chartMensalStatusLinha', dashboardMensalStatusData);
        renderDashboardStatusArea('chartMensalStatusArea', dashboardMensalStatusData);
    </script>
</body>
</html>
