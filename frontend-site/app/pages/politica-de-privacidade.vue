<script setup lang="ts">
// Exigência de LGPD (ver docs/estrutura-site.md §1.3), fora do CMS — conteúdo fixo, versionado
// junto do código. Rascunho de trabalho: docs/protecao-de-dados.md registra explicitamente
// que este documento precisa de validação jurídica antes de produção; a versão aceita em cada
// formulário é gravada em consent_terms_version (ver config/forms.php no backend).
useSeoMeta({
  title: 'Política de Privacidade — Lar Anália Franco',
  description: 'Como o Lar Anália Franco trata os dados enviados pelos formulários do site.',
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><span aria-current="page">Política de Privacidade</span></li>
      </ol>
    </nav>

    <h1>Política de Privacidade</h1>
    <div class="prose">
      <p>
        <strong>Este texto é um rascunho de trabalho</strong>, ainda sem validação jurídica.
        Descreve, de forma honesta, o que o Lar Anália Franco faz hoje com os dados enviados
        pelos formulários deste site.
      </p>

      <h2>O que coletamos e por quê</h2>
      <p>
        Nenhum formulário público deste site coleta dado identificável de criança ou
        adolescente. Nos formulários de matrícula e de inscrição no contraturno, o titular dos
        dados é sempre o responsável adulto — os dados da criança ou do adolescente são
        coletados presencialmente, junto do termo de consentimento assinado.
      </p>

      <table>
        <thead>
          <tr>
            <th>Formulário</th>
            <th>O que pedimos</th>
            <th>Por quanto tempo guardamos</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Interesse em matrícula</td>
            <td>Nome, telefone, e-mail, faixa etária da criança, período pretendido</td>
            <td>12 meses</td>
          </tr>
          <tr>
            <td>Inscrição no contraturno</td>
            <td>Nome, telefone, e-mail, idade do adolescente, escola (opcional)</td>
            <td>12 meses</td>
          </tr>
          <tr>
            <td>Agendamento de coleta (bazar)</td>
            <td>Nome, telefone, endereço, itens a doar</td>
            <td>
              O endereço é apagado assim que a coleta é concluída; o restante do registro,
              em até 6 meses
            </td>
          </tr>
          <tr>
            <td>Voluntariado</td>
            <td>Nome, telefone, e-mail, disponibilidade, área de interesse</td>
            <td>24 meses</td>
          </tr>
          <tr>
            <td>Proposta de parceria</td>
            <td>Empresa, CNPJ, nome do contato, telefone, e-mail</td>
            <td>36 meses</td>
          </tr>
          <tr>
            <td>Contato</td>
            <td>Nome, e-mail, assunto, mensagem</td>
            <td>6 meses</td>
          </tr>
        </tbody>
      </table>

      <h2>Como protegemos</h2>
      <ul>
        <li>Dados pessoais ficam criptografados no banco de dados.</li>
        <li>O apagamento ao fim do prazo é automático, não manual.</li>
        <li>
          Nenhum e-mail de notificação interna contém dado pessoal de quem preencheu um
          formulário.
        </li>
        <li>Não usamos CAPTCHA de terceiro nem cookies de rastreamento neste site.</li>
      </ul>

      <h2>Seus direitos</h2>
      <p>
        Você pode pedir acesso, correção ou eliminação dos seus dados a qualquer momento,
        entrando em contato pelo <NuxtLink to="/contato">formulário de contato</NuxtLink>. Um
        canal e um responsável formal pela proteção de dados (Encarregado/DPO) ainda serão
        definidos e publicados aqui.
      </p>

      <h2>Base legal</h2>
      <p>
        O envio de qualquer um dos formulários deste site depende do seu consentimento
        explícito, dado no próprio formulário. Você pode retirar esse consentimento a
        qualquer momento pelo mesmo canal de contato.
      </p>
    </div>
  </div>
</template>
