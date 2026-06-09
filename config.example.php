<?php
/**
 * Modelo de configuração — copie para config.php e ajuste os valores.
 * O arquivo config.php não deve ser versionado (veja .gitignore).
 */
return [
  'para' => 'rh@seudominio.com.br',
  /** Usado só como assunto reserva se o campo “Vaga” vier vazio */
  'assunto' => '[RH] Nova candidatura',
  /**
   * E-mail do seu domínio (obrigatório na Hostinger e na maioria das hospedagens).
   * Crie a conta em hPanel → E-mails → Contas de e-mail.
   */
  'remetente_email' => 'formulario@seudominio.com.br',
  'remetente_nome' => 'Formulário RH',
  /**
   * SMTP autenticado (recomendado na Hostinger). Se 'ativo' => true, o PHPMailer
   * substitui mail(). Use os mesmos dados da conta de e-mail criada no hPanel.
   */
  'smtp' => [
    'ativo' => true,
    'host' => 'smtp.hostinger.com',
    'porta' => 587,
    'usuario' => 'formulario@seudominio.com.br',
    'senha' => 'SUA_SENHA_DO_EMAIL',
    /** 'tls' (porta 587) ou 'ssl' (porta 465) */
    'seguranca' => 'tls',
  ],
];
