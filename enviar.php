<?php
/**
 * enviar.php — Backend de contato RETEC SP
 *
 * Segurança:  CSRF token · rate limiting · allowlist de departamentos
 *             htmlspecialchars · filter_var · sem dados sensíveis em erros
 * LGPD:       Sem armazenamento do conteúdo da mensagem
 *             Log mínimo: timestamp | departamento | hash(email) | hash(IP)
 * Email:      PHPMailer + SMTP TLS · Reply-To do remetente · template HTML RETEC
 */

// ── Ocultar erros PHP do cliente ──────────────────────────────────────────
ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ── Aceitar apenas POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método não permitido']));
}

// ── Sessão (CSRF + rate limiting) ─────────────────────────────────────────
session_start();

// ── Validar CSRF ──────────────────────────────────────────────────────────
$csrfPost    = trim($_POST['csrf_token'] ?? '');
$csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($csrfPost) || empty($csrfSession) || !hash_equals($csrfSession, $csrfPost)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Requisição inválida. Recarregue a página e tente novamente.']));
}

// Invalida o token após uso (one-time token)
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Rate limiting: máx. 3 envios por sessão em 10 minutos ─────────────────
$agora = time();
$_SESSION['form_sends'] = array_values(
    array_filter(
        $_SESSION['form_sends'] ?? [],
        fn($t) => ($agora - $t) < 600
    )
);

if (count($_SESSION['form_sends']) >= 3) {
    http_response_code(429);
    exit(json_encode(['error' => 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.']));
}

// ── Validar campos obrigatórios ───────────────────────────────────────────
$camposObrigatorios = ['nome', 'email', 'telefone', 'departamento', 'mensagem'];
foreach ($camposObrigatorios as $campo) {
    if (empty(trim($_POST[$campo] ?? ''))) {
        http_response_code(400);
        exit(json_encode(['error' => 'Preencha todos os campos obrigatórios.']));
    }
}

// ── Sanitização e validação ───────────────────────────────────────────────
$nome         = htmlspecialchars(trim($_POST['nome']),         ENT_QUOTES, 'UTF-8');
$emailRaw     = trim($_POST['email']);
$email        = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
$telefone     = htmlspecialchars(trim($_POST['telefone']),     ENT_QUOTES, 'UTF-8');
$departamento = htmlspecialchars(trim($_POST['departamento']), ENT_QUOTES, 'UTF-8');
$mensagem     = htmlspecialchars(trim($_POST['mensagem']),     ENT_QUOTES, 'UTF-8');

if (!$email) {
    http_response_code(400);
    exit(json_encode(['error' => 'Endereço de e-mail inválido.']));
}

// Limite de tamanho para prevenir abuso
if (strlen($mensagem) > 4000 || strlen($nome) > 200 || strlen($telefone) > 30) {
    http_response_code(400);
    exit(json_encode(['error' => 'Dados inválidos.']));
}

// ── Allowlist de departamentos (previne injeção de e-mail) ────────────────
$destinos = [
    'SAC'        => 'logistica@retecsp.com.br',
    'Financeiro' => 'financeiro@retecsp.com.br',
    'Comercial'  => 'comercial@retecsp.com.br',
];

if (!array_key_exists($departamento, $destinos)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Departamento inválido.']));
}

$destinatario = $destinos[$departamento];

// ── Credenciais SMTP (config.php protegido pelo .htaccess) ────────────────
require __DIR__ . '/config.php';

// ── PHPMailer ─────────────────────────────────────────────────────────────
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Template HTML do e-mail ───────────────────────────────────────────────
$corDeptMap = [
    'SAC'        => '#22ac65',
    'Financeiro' => '#d6b030',
    'Comercial'  => '#003352',
];
$corDept = $corDeptMap[$departamento] ?? '#003352';

$htmlEmail = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Novo contato RETEC</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.12);">

        <!-- Cabeçalho -->
        <tr><td style="background:#003352;padding:24px 32px;">
          <h2 style="margin:0;color:#ffffff;font-size:1rem;font-weight:700;letter-spacing:.5px;">
            Novo contato via site &mdash; RETEC SP
          </h2>
        </td></tr>

        <!-- Badge departamento -->
        <tr><td style="background:#003352;padding:0 32px 20px;">
          <span style="display:inline-block;background:{$corDept};color:#fff;font-size:.8rem;font-weight:700;padding:4px 14px;border-radius:20px;letter-spacing:.5px;">
            {$departamento}
          </span>
        </td></tr>

        <!-- Corpo -->
        <tr><td style="background:#ffffff;padding:28px 32px;">
          <table width="100%" cellpadding="0" cellspacing="0" style="font-size:.9rem;color:#2c3e50;border-collapse:collapse;">
            <tr>
              <td style="padding:10px 0;font-weight:700;width:120px;border-bottom:1px solid #eef0f3;color:#555;">Nome</td>
              <td style="padding:10px 0;border-bottom:1px solid #eef0f3;">{$nome}</td>
            </tr>
            <tr>
              <td style="padding:10px 0;font-weight:700;border-bottom:1px solid #eef0f3;color:#555;">E-mail</td>
              <td style="padding:10px 0;border-bottom:1px solid #eef0f3;">
                <a href="mailto:{$email}" style="color:#003352;text-decoration:none;">{$email}</a>
              </td>
            </tr>
            <tr>
              <td style="padding:10px 0;font-weight:700;border-bottom:1px solid #eef0f3;color:#555;">Telefone</td>
              <td style="padding:10px 0;border-bottom:1px solid #eef0f3;">{$telefone}</td>
            </tr>
          </table>

          <p style="margin:20px 0 8px;font-weight:700;color:#2c3e50;">Mensagem:</p>
          <div style="background:#f8f9fa;border:1px solid #dce3ea;border-left:4px solid {$corDept};border-radius:6px;padding:16px 18px;line-height:1.7;color:#3a4a5a;font-size:.9rem;">
            {$mensagem}
          </div>

          <p style="margin:20px 0 0;font-size:.8rem;color:#888;">
            Para responder a este contato, utilize o botão <strong>Responder</strong> no seu cliente de e-mail
            — a resposta será enviada diretamente para <strong>{$email}</strong>.
          </p>
        </td></tr>

        <!-- Rodapé -->
        <tr><td style="background:#003352;padding:14px 32px;text-align:center;">
          <p style="margin:0;color:rgba(255,255,255,.45);font-size:.72rem;">
            retecsp.com.br &mdash; mensagem automática, não responda a este endereço.
          </p>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

$altEmail = "Departamento: {$departamento}\nNome: {$nome}\nE-mail: {$email}\nTelefone: {$telefone}\n\nMensagem:\n{$mensagem}";

// ── Envio ─────────────────────────────────────────────────────────────────
$mail = new PHPMailer(true);

try {
    // Configuração SMTP
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Remetente / destinatário
    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress($destinatario);
    $mail->addReplyTo($email, $nome);   // facilita resposta direta ao cliente

    // Conteúdo
    $mail->Subject = "[{$departamento}] Novo contato via site — {$nome}";
    $mail->isHTML(true);
    $mail->Body    = $htmlEmail;
    $mail->AltBody = $altEmail;

    $mail->send();

    // ── Log de consentimento LGPD ─────────────────────────────────────────
    // Armazena APENAS: timestamp · departamento · hash(email) · hash(IP)
    // NÃO armazena: nome, mensagem, telefone, e-mail em claro.
    $logDir  = __DIR__ . '/logs';
    $logFile = $logDir . '/consentimento.log';

    if (is_dir($logDir) && is_writable($logDir)) {
        $ipHash    = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $emailHash = hash('sha256', strtolower($emailRaw));
        $linha     = implode(' | ', [
            date('Y-m-d H:i:s'),
            $departamento,
            $emailHash,
            'ip=' . $ipHash,
            'consentimento=sim',
        ]) . PHP_EOL;
        file_put_contents($logFile, $linha, FILE_APPEND | LOCK_EX);
    }

    // Registra timestamp para rate limiting
    $_SESSION['form_sends'][] = $agora;

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Nunca expõe detalhes técnicos ao cliente
    error_log('RETEC enviar.php — erro SMTP: ' . $mail->ErrorInfo);
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível enviar a mensagem. Tente novamente ou ligue para (11)&nbsp;3712-2416.']);
}
