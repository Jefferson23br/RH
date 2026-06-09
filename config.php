<?php
/**
 * Configuração local — não commite este arquivo em repositórios públicos.
 * Substitua os valores abaixo pelos dados reais do seu domínio na Hostinger.
 */
return [
  /** E-mail que recebe as candidaturas */
  'para' => 'rh@seudominio.com.br',
  'assunto' => '[RH] Nova candidatura',
  /** Mesmo e-mail usado no SMTP abaixo */
  'remetente_email' => 'formulario@seudominio.com.br',
  'remetente_nome' => 'Formulário RH',
  'smtp' => [
    'ativo' => true,
    'host' => 'smtp.hostinger.com',
    'porta' => 587,
    'usuario' => 'formulario@seudominio.com.br',
    'senha' => 'SUA_SENHA_DO_EMAIL',
    'seguranca' => 'tls',
  ],
];
