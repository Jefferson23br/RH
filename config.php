<?php
/**
 * Configuração local — não commite este arquivo em repositórios públicos.
 * Use config.example.php como base.
 */
return [
  'para' => 'rh@seudominio.com.br',
  /** Usado só como assunto reserva se o campo “Vaga” vier vazio */
  'assunto' => '[RH] Nova candidatura',
  /**
   * Opcional: use um e-mail do mesmo domínio do site para reduzir risco de spam.
   * Se null, será usado noreply@ + nome do servidor.
   */
  'remetente_email' => null,
  'remetente_nome' => 'Formulário RH',
];
