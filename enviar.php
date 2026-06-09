<?php
declare(strict_types=1);

/**
 * Recebe o POST do formulário e envia e-mail pelo servidor.
 * Usa SMTP autenticado (PHPMailer) quando configurado; senão mail() nativo.
 */

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    header('Location: index.html?erro=config');
    exit;
}

$config = require $configPath;
$para = filter_var($config['para'] ?? '', FILTER_VALIDATE_EMAIL);
$assuntoBase = is_string($config['assunto'] ?? null) ? $config['assunto'] : '[RH] Nova avaliação';
$remetenteNome = is_string($config['remetente_nome'] ?? null) ? $config['remetente_nome'] : 'Formulário RH';
$smtpConfig = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
$smtpAtivo = !empty($smtpConfig['ativo']);

$remetenteEmail = $config['remetente_email'] ?? null;
$remetenteEmail = is_string($remetenteEmail) ? filter_var($remetenteEmail, FILTER_VALIDATE_EMAIL) : false;

if (!$para) {
    http_response_code(500);
    header('Location: index.html?erro=config');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$maxBytes = 8 * 1024 * 1024; // 8 MB
$allowedExt = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'webp'];

function campo(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim(strip_tags($v)) : '';
}

$nome = campo('Nome_Candidato');
$vaga = campo('Vaga');
$emailCandidato = filter_var(campo('Email'), FILTER_VALIDATE_EMAIL);
$telefone = campo('Telefone');
$cpf = campo('CPF');
$bairro = campo('Bairro');
$cidadeEstado = campo('Cidade_Estado');
$pretensao = campo('Pretensao_Salarial');
$perfil = campo('PERFIL_DOMINANTE_FINAL');
$scoreA = campo('SCORE_D_DOMINANCIA');
$scoreB = campo('SCORE_I_INFLUENCIA');
$scoreC = campo('SCORE_S_ESTABILIDADE');
$scoreD = campo('SCORE_C_CONFORMIDADE');

if ($nome === '' || $vaga === '' || !$emailCandidato || $telefone === '') {
    header('Location: index.html?erro=campos');
    exit;
}

$linhas = [
    'Vaga: ' . $vaga,
    'Nome: ' . $nome,
    'E-mail: ' . $emailCandidato,
    'Telefone: ' . $telefone,
    'CPF: ' . $cpf,
    'Bairro: ' . $bairro,
    'Cidade / Estado: ' . $cidadeEstado,
    'Pretensão salarial: ' . $pretensao,
    '',
    'Perfil dominante: ' . $perfil,
    'Scores D/I/S/C: ' . $scoreA . ' / ' . $scoreB . ' / ' . $scoreC . ' / ' . $scoreD,
    '',
    '--- Respostas ---',
];

for ($i = 1; $i <= 30; $i++) {
    $k = 'Q' . $i;
    if (isset($_POST[$k]) && is_string($_POST[$k])) {
        $linhas[] = $k . ': ' . trim($_POST[$k]);
    }
}

$textoPlano = implode("\n", $linhas);
$replyTo = $emailCandidato;
$tituloEmail = assuntoSeguro($vaga, $assuntoBase);

$dominio = preg_replace('/^www\./', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
$fromAddr = $remetenteEmail ?: ('noreply@' . $dominio);

if (!$smtpAtivo && !$remetenteEmail) {
    header('Location: index.html?erro=config');
    exit;
}

$anexoPath = null;
$anexoNome = null;

if (!empty($_FILES['anexo']['tmp_name']) && is_uploaded_file($_FILES['anexo']['tmp_name'])) {
    $err = (int) ($_FILES['anexo']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_OK) {
        $size = (int) ($_FILES['anexo']['size'] ?? 0);
        if ($size > 0 && $size <= $maxBytes) {
            $origName = $_FILES['anexo']['name'] ?? 'anexo';
            $origName = is_string($origName) ? basename($origName) : 'anexo';
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt, true)) {
                $anexoPath = $_FILES['anexo']['tmp_name'];
                $anexoNome = $origName;
            }
        }
    }
}

$ok = $smtpAtivo
    ? enviarPorSmtp($smtpConfig, $para, $tituloEmail, $textoPlano, $fromAddr, $remetenteNome, $replyTo, $anexoPath, $anexoNome)
    : enviarPorMail($para, $tituloEmail, $textoPlano, $fromAddr, $remetenteNome, $replyTo, $anexoPath, $anexoNome);

header('Location: index.html?' . ($ok ? 'enviado=1' : 'erro=envio'));
exit;

function enviarPorMail(
    string $para,
    string $assunto,
    string $textoPlano,
    string $fromAddr,
    string $remetenteNome,
    string $replyTo,
    ?string $anexoPath,
    ?string $anexoNome
): bool {
    $boundary = 'bnd_' . bin2hex(random_bytes(16));

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . encodeHeader($remetenteNome) . ' <' . $fromAddr . '>';
    $headers[] = 'Reply-To: ' . $replyTo;
    $headers[] = 'Return-Path: ' . $fromAddr;
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $body .= quoted_printable_encode($textoPlano) . "\r\n";

    if ($anexoPath !== null && $anexoNome !== null) {
        $bin = file_get_contents($anexoPath);
        if ($bin !== false && $bin !== '') {
            $mime = mime_content_type($anexoPath) ?: 'application/octet-stream';
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Type: ' . $mime . '; name="' . encodeHeader($anexoNome) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . encodeHeader($anexoNome) . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($bin)) . "\r\n";
        }
    }

    $body .= "--{$boundary}--\r\n";

    $assuntoEnc = encodeHeader($assunto);
    $params = '-f' . $fromAddr;

    return @mail($para, $assuntoEnc, $body, implode("\r\n", $headers), $params);
}

function enviarPorSmtp(
    array $smtp,
    string $para,
    string $assunto,
    string $textoPlano,
    string $fromAddr,
    string $remetenteNome,
    string $replyTo,
    ?string $anexoPath,
    ?string $anexoNome
): bool {
    $lib = __DIR__ . '/lib/PHPMailer-6.9.3/src';
    if (!is_file($lib . '/PHPMailer.php')) {
        return false;
    }

    require $lib . '/Exception.php';
    require $lib . '/PHPMailer.php';
    require $lib . '/SMTP.php';

    $host = is_string($smtp['host'] ?? null) ? trim($smtp['host']) : '';
    $usuario = is_string($smtp['usuario'] ?? null) ? trim($smtp['usuario']) : '';
    $senha = is_string($smtp['senha'] ?? null) ? $smtp['senha'] : '';
    $porta = (int) ($smtp['porta'] ?? 587);
    $seguranca = is_string($smtp['seguranca'] ?? null) ? strtolower($smtp['seguranca']) : 'tls';

    if ($host === '' || $usuario === '' || $senha === '') {
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $usuario;
        $mail->Password = $senha;
        $mail->Port = $porta;
        $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;

        if ($seguranca === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($fromAddr, $remetenteNome);
        $mail->addAddress($para);
        $mail->addReplyTo($replyTo);
        $mail->Subject = $assunto;
        $mail->Body = $textoPlano;

        if ($anexoPath !== null && $anexoNome !== null) {
            $mail->addAttachment($anexoPath, $anexoNome);
        }

        return $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        return false;
    }
}

function assuntoSeguro(string $texto, string $fallback): string
{
    $texto = preg_replace('/[\r\n\x00]+/', ' ', $texto);
    $texto = trim(strip_tags($texto));
    if ($texto === '') {
        return $fallback;
    }
    if (strlen($texto) > 250) {
        return substr($texto, 0, 250);
    }
    return $texto;
}

function encodeHeader(string $s): string
{
    if (preg_match('/[^\x20-\x7E]/', $s)) {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }
    return $s;
}
