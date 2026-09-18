> **Modelo recomendado: Opus**

# 08 — Política de privacidade fiel ao sistema

Leia `docs/tarefas/README.md` e `docs/protecao-de-dados.md`. Rode depois da 01.

## Problema

`/politica-de-privacidade` descreve um sistema que não existe mais: lista o formulário de
interesse em matrícula (removido), a inscrição no contraturno com e-mail, idade e escola (hoje é
só nome e telefone do responsável), fala de dados de criança "coletados presencialmente junto
do termo", e abre com "Este texto é um rascunho de trabalho" visível ao público. Promete
apagamento automático, que depende de scheduler rodando (tarefa 07).

## Regra desta tarefa

**Cada afirmação da política precisa corresponder ao código.** Nada de promessa genérica.

## Etapas

1. Levantar do código, não de documento: quais formulários existem e estão alcançáveis
   (incluindo os `noindex`, que coletam dado mesmo fora do menu), campos de cada FormRequest,
   prazo de `retentionMonths()` de cada model, expurgo de endereço de coleta, quais campos são
   de fato cifrados (`FieldEncrypted`), quem recebe notificação e o que ela contém, cookies que
   o site público e o painel efetivamente definem (conferir no Firefox), Umami cookieless.
   Registrar esse levantamento no relatório.
2. Reescrever a página a partir do levantamento: controlador identificado (nome, CNPJ,
   endereço), o que é coletado e por quê em cada formulário, base legal, prazos, proteção
   (só o que for verdade), direitos do titular e canal para exercê-los, nenhum dado de criança
   coletado pelo site. Sem "rascunho" no texto público.
3. Atualizar `FORM_CONSENT_TERMS_VERSION` (data da nova versão) e o valor padrão em
   `config/forms.php`.
4. Registrar no roadmap que a política ainda exige validação jurídica e Encarregado/DPO
   nomeado antes da produção — como pendência interna, nunca no texto público.

## Verificação

Build/generate do site, e2e completo, conferência da página no Firefox.
