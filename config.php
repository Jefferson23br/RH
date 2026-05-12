<?php
/**
 * Copie este arquivo para config.php e preencha.
 * Não commite config.php se o repositório for público.
 */
return [
    'para' => 'administrativo2@quatropontos.com.br',
    /** Usado só como assunto reserva se o campo “Vaga” vier vazio (não deve ocorrer com o formulário atual) */
    'assunto' => '[RH] Nova candidatura',
    /**
     * Opcional: use um e-mail do mesmo domínio do site para reduzir risco de spam.
     * Se null, será usado noreply@ + nome do servidor.
     */
    'remetente_email' => null,
    'remetente_nome' => 'Formulário RH',
];
