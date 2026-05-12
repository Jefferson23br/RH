<?php
declare(strict_types=1);

/**
 * Recebe o POST do formulário e envia e-mail pelo servidor (sem Formspree).
 * Requer PHP com função mail() configurada (comum em hospedagem Linux).
 * GitHub Pages e servidores estáticos não executam PHP — use hospedagem com PHP.
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
$boundary = 'bnd_' . bin2hex(random_bytes(16));
$replyTo = $emailCandidato;

$dominio = preg_replace('/^www\./', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
$fromAddr = $remetenteEmail ?: ('noreply@' . $dominio);

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'From: ' . encodeHeader($remetenteNome) . ' <' . $fromAddr . '>';
$headers[] = 'Reply-To: ' . $replyTo;
$headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

$body = "--{$boundary}\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
$body .= quoted_printable_encode($textoPlano) . "\r\n";

if (!empty($_FILES['anexo']['tmp_name']) && is_uploaded_file($_FILES['anexo']['tmp_name'])) {
    $err = (int) ($_FILES['anexo']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_OK) {
        $size = (int) ($_FILES['anexo']['size'] ?? 0);
        if ($size > 0 && $size <= $maxBytes) {
            $origName = $_FILES['anexo']['name'] ?? 'anexo';
            $origName = is_string($origName) ? basename($origName) : 'anexo';
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt, true)) {
                $bin = file_get_contents($_FILES['anexo']['tmp_name']);
                if ($bin !== false && $bin !== '') {
                    $mime = mime_content_type($_FILES['anexo']['tmp_name']) ?: 'application/octet-stream';
                    $body .= "--{$boundary}\r\n";
                    $body .= 'Content-Type: ' . $mime . '; name="' . encodeHeader($origName) . "\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n";
                    $body .= 'Content-Disposition: attachment; filename="' . encodeHeader($origName) . "\"\r\n\r\n";
                    $body .= chunk_split(base64_encode($bin)) . "\r\n";
                }
            }
        }
    }
}

$body .= "--{$boundary}--\r\n";

$tituloEmail = assuntoSeguro($vaga, $assuntoBase);
$assunto = encodeHeader($tituloEmail);
$ok = @mail($para, $assunto, $body, implode("\r\n", $headers));

header('Location: index.html?' . ($ok ? 'enviado=1' : 'erro=envio'));
exit;

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
