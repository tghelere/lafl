<script setup lang="ts">
// Exigência de LGPD (ver docs/estrutura-site.md §1.3), fora do CMS — conteúdo fixo,
// versionado junto do código.
//
// REGRA DESTA PÁGINA: cada afirmação aqui corresponde a uma linha de código, conferida no
// levantamento de docs/relatorio-sessao-20.md. Nada de promessa genérica ("seus dados estão
// seguros", "usamos as melhores práticas") e nada que o sistema não faça hoje. Se uma
// afirmação deixar de ser verdade — um campo passar a ser cifrado, um formulário mudar, o
// Umami ser ligado, o servidor mudar de país — o texto muda junto, e `VERSAO` sobe.
//
// `VERSAO` precisa ser igual a FORM_CONSENT_TERMS_VERSION (ver backend/config/forms.php): é
// o que cada envio de formulário grava em consent_terms_version, e é o que diz qual texto a
// pessoa aceitou. Mudou o texto de forma relevante, muda a data nos dois lugares.
const VERSAO = '2026-09-18'

useSeoMeta({
  title: 'Política de Privacidade — Lar Anália Franco',
  description:
    'O que o Lar Anália Franco coleta pelos formulários deste site, por quanto tempo guarda, '
    + 'como protege e como você exerce seus direitos.',
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
    <p class="policy-version">Versão {{ VERSAO }}</p>

    <div class="prose">
      <p>
        Esta política descreve o que acontece com os dados que você envia pelos formulários
        <strong>deste site</strong>. Ela não cobre o atendimento presencial da instituição,
        que tem regras próprias e não passa por este site.
      </p>

      <h2>Quem trata os seus dados</h2>
      <p>
        <strong>Lar Anália Franco de Londrina</strong>, associação civil beneficente e
        filantrópica, CNPJ 78.614.096/0001-75, com sede na Av. Anália Franco, 33 — Jd.
        Aeroporto, Londrina/PR, telefone (43) 3325-8060. É a instituição, e ninguém mais, quem
        decide o que é feito com o que você envia aqui.
      </p>

      <h2>Nenhum dado de criança ou adolescente é coletado por este site</h2>
      <p>
        Nenhum dos formulários abaixo pede — nem aceita — nome, idade, escola ou qualquer outro
        dado que identifique uma criança ou um adolescente. Em todos, quem preenche é um adulto
        falando por si. A matrícula na Educação Infantil é feita exclusivamente pela Central de
        Vagas da Prefeitura de Londrina, fora deste site, e o formulário do Contraturno é
        apenas um aviso de "me avise quando abrir": pede o nome e o telefone do responsável, e
        mais nada.
      </p>

      <h2>O que cada formulário coleta</h2>
      <p>
        São cinco. Cada um pede o mínimo para o que se propõe a fazer, e nada além disso —
        estes são exatamente os campos que o sistema aceita.
      </p>

      <section class="policy-forms">
        <article class="policy-form">
          <h3>Aviso de interesse no Contraturno</h3>
          <dl>
            <dt>O que pedimos</dt>
            <dd>Seu nome e seu telefone.</dd>
            <dt>Para quê</dt>
            <dd>Avisar você por telefone quando as inscrições do programa abrirem.</dd>
            <dt>Por quanto tempo</dt>
            <dd>12 meses.</dd>
          </dl>
        </article>

        <article class="policy-form">
          <h3>Agendamento de coleta do Bazar</h3>
          <dl>
            <dt>O que pedimos</dt>
            <dd>
              Seu nome, seu telefone, o endereço da coleta, a descrição do que você quer doar e
              quando você costuma estar disponível.
            </dd>
            <dt>Para quê</dt>
            <dd>Combinar com você a retirada da doação no endereço informado.</dd>
            <dt>Por quanto tempo</dt>
            <dd>
              <strong>O endereço é apagado assim que a coleta é marcada como concluída</strong>,
              sem esperar prazo nenhum. O restante do registro é apagado em 6 meses, contados do
              envio — e o endereço junto, se por algum motivo a coleta nunca tiver sido
              concluída.
            </dd>
          </dl>
        </article>

        <article class="policy-form">
          <h3>Voluntariado</h3>
          <dl>
            <dt>O que pedimos</dt>
            <dd>
              Seu nome, seu telefone, seu e-mail, sua disponibilidade, a área de interesse e,
              se você quiser, uma mensagem.
            </dd>
            <dt>Para quê</dt>
            <dd>Entrar em contato sobre a sua candidatura a voluntário.</dd>
            <dt>Por quanto tempo</dt>
            <dd>24 meses.</dd>
          </dl>
        </article>

        <article class="policy-form">
          <h3>Apoiar o projeto (empresas)</h3>
          <dl>
            <dt>O que pedimos</dt>
            <dd>
              Nome da empresa, CNPJ, nome do contato, telefone, e-mail, o tipo de apoio
              pretendido e, se você quiser, uma mensagem.
            </dd>
            <dt>Para quê</dt>
            <dd>Retomar o contato sobre a proposta de parceria.</dd>
            <dt>Por quanto tempo</dt>
            <dd>36 meses.</dd>
          </dl>
        </article>

        <article class="policy-form">
          <h3>Contato</h3>
          <dl>
            <dt>O que pedimos</dt>
            <dd>Seu nome, seu e-mail, o assunto e a mensagem.</dd>
            <dt>Para quê</dt>
            <dd>Responder você.</dd>
            <dt>Por quanto tempo</dt>
            <dd>6 meses.</dd>
          </dl>
        </article>
      </section>

      <p>
        Os prazos acima são contados a partir do envio, e o apagamento é feito por uma rotina
        automática do próprio sistema — não depende de alguém lembrar de apagar. A exclusão é
        definitiva: o registro é removido do banco de dados, não marcado como oculto.
      </p>
      <p>
        Cópias de segurança do banco de dados são feitas diariamente e descartadas em ciclo
        curto e automático. Um registro já apagado pode continuar existindo dentro de uma
        cópia até que ela mesma seja descartada.
      </p>

      <h2>Com base em quê</h2>
      <p>
        No seu consentimento (LGPD, art. 7º, I). Em todos os cinco formulários existe uma
        caixa de seleção que não vem marcada, e sem marcá-la o envio não acontece. O sistema
        grava, junto do seu registro, a data do aceite e a versão desta política que estava no
        ar naquele momento — é por isso que esta página tem número de versão.
      </p>
      <p>
        Você pode retirar o consentimento a qualquer momento, pelos canais da seção "Seus
        direitos". Retirar o consentimento leva ao apagamento do registro.
      </p>

      <h2>Quem vê o que você enviou</h2>
      <p>
        Só a equipe da instituição, no painel administrativo, e apenas quem cuida do assunto:
        cada formulário é visível para a diretoria e para a área responsável por ele — bazar,
        contraturno ou atendimento —, não para todo mundo que tem acesso ao painel. O painel
        exige login individual, e toda vez que alguém abre o seu registro completo isso fica
        registrado em auditoria: quem abriu e quando. O registro de auditoria guarda o acesso,
        nunca o conteúdo do que você escreveu.
      </p>
      <p>
        Quando um formulário chega, o sistema envia um aviso por e-mail para a área
        responsável. <strong>Esse e-mail não contém nenhum dado seu</strong>: só o tipo de
        formulário, a data e um link para o painel. E-mail não é um canal seguro, e por isso
        nada que você escreveu trafega nele.
      </p>
      <p>
        Nada do que você envia é vendido, cedido ou compartilhado com terceiros para
        publicidade, análise de perfil ou qualquer outra finalidade. O único terceiro
        envolvido é a empresa que hospeda o servidor — ver "Onde os dados ficam", abaixo.
      </p>

      <h2>Como protegemos</h2>
      <p>
        Os campos mais sensíveis não ficam legíveis no banco de dados: são criptografados, com
        chave guardada fora do banco e separada da chave da aplicação. Concretamente, ficam
        criptografados:
      </p>
      <ul>
        <li>nome, telefone e e-mail, em todos os formulários em que são pedidos;</li>
        <li>o endereço da coleta;</li>
        <li>o CNPJ da empresa, na proposta de parceria;</li>
        <li>o texto da sua mensagem, no formulário de contato e no de voluntariado;</li>
        <li>qualquer anotação que a equipe faça sobre o seu atendimento.</li>
      </ul>
      <p>
        Não ficam criptografados os campos que não identificam ninguém sozinhos: o assunto da
        mensagem de contato, a descrição dos itens a doar, a janela de disponibilidade, a área
        de interesse do voluntariado, o nome da empresa e o tipo de apoio pretendido. Preferimos
        dizer isso do que prometer uma proteção que não está lá.
      </p>
      <p>
        <strong>O seu endereço IP não é guardado.</strong> No momento do envio ele é convertido
        em um código irreversível, e é esse código que fica no registro — serve para conter
        abuso de formulário, não para identificar você. O IP em si aparece apenas no controle de
        excesso de envios, por poucos minutos, e no registro técnico de acesso do servidor, que
        é descartado automaticamente após 14 dias.
      </p>
      <p>
        Todo o site é servido por conexão criptografada (HTTPS), e o acesso ao painel
        administrativo exige autenticação.
      </p>

      <h2>Cookies e medição de audiência</h2>
      <p>
        <strong>Este site não usa cookies.</strong> Nenhuma página coloca cookie no seu
        navegador, e nada é gravado no armazenamento local dele. É por isso que você não vê
        aqui um aviso de cookies pedindo aceite.
      </p>
      <p>
        Também não há nenhum conteúdo carregado de terceiros: as fontes, as imagens e o mapa da
        página de contato são servidos por este próprio site. Não usamos CAPTCHA de terceiro —
        o bloqueio de envio automatizado é feito pelo próprio sistema. Os links para Google
        Maps e WhatsApp são apenas links: nada é carregado dessas empresas enquanto você não
        clicar.
      </p>
      <p>
        No momento, nenhuma ferramenta de medição de audiência está ativa neste site. Caso
        venha a ser ativada, será uma ferramenta que não usa cookies nem identifica visitantes
        individualmente, e esta página será atualizada antes disso.
      </p>

      <h2>Onde os dados ficam, e a transferência internacional</h2>
      <p>
        O servidor que hospeda este site e guarda o que você envia fica nos
        <strong>Estados Unidos</strong>. A instituição contratou hospedagem pretendendo usar o
        data center brasileiro do provedor, que está indisponível; os Estados Unidos foram a
        alternativa disponível.
      </p>
      <p>
        Na prática, isso significa que os seus dados são armazenados e processados fora do
        Brasil, o que caracteriza <strong>transferência internacional de dados</strong> nos
        termos da LGPD (art. 33). Os Estados Unidos não são reconhecidos pela Autoridade
        Nacional de Proteção de Dados como país de nível de proteção adequado, e a
        transferência se apoia no consentimento que você dá ao enviar o formulário — informado,
        específico e destacado, que é exatamente o que este parágrafo existe para ser.
      </p>
      <p>
        Vale o que está escrito acima: os campos sensíveis viajam e ficam armazenados
        criptografados, e a chave que os abre não fica no banco de dados. A instituição
        pretende trazer a hospedagem para o Brasil quando o data center nacional do provedor
        voltar a estar disponível, e esta página será atualizada quando isso acontecer.
      </p>

      <h2>Seus direitos</h2>
      <p>
        A LGPD garante a você, entre outros, o direito de confirmar que tratamos dados seus, de
        acessá-los, de corrigi-los, de pedir a eliminação e de retirar o consentimento. Para
        exercer qualquer um deles, fale com a instituição:
      </p>
      <ul>
        <li>pelo <NuxtLink to="/contato">formulário de contato</NuxtLink>;</li>
        <li>por telefone, no (43) 3325-8060;</li>
        <li>
          pessoalmente ou por carta, na Av. Anália Franco, 33 — Jd. Aeroporto, Londrina/PR.
        </li>
      </ul>
      <p>
        Um pedido feito pelo formulário de contato gera, ele mesmo, um registro de contato,
        sujeito às regras deste documento. Para localizar o que você enviou, pode ser preciso
        confirmar alguma informação do envio original — não pedimos documento nem dado a mais
        do que o necessário para essa conferência.
      </p>

      <h2>Mudanças nesta política</h2>
      <p>
        Quando o texto mudar de forma relevante, a versão indicada no topo muda junto, e os
        formulários passam a registrar a nova versão. O que você aceitou continua sendo a
        versão que estava no ar no dia do seu envio.
      </p>
    </div>
  </div>
</template>

<style scoped>
.policy-version {
  margin: calc(var(--space-2) * -1) 0 var(--space-6);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

/* Um bloco por formulário em vez de uma tabela de cinco linhas: a tabela anterior tinha três
   colunas de texto corrido e, no celular, virava uma coluna esmagada ou um scroll lateral. */
.policy-forms {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  margin-bottom: var(--space-6);
}

.policy-form {
  padding: var(--space-5);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.policy-form h3 {
  margin-top: 0;
  margin-bottom: var(--space-4);
  font-size: var(--text-lg);
}

.policy-form dl {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: var(--space-2) var(--space-5);
  margin: 0;
}

.policy-form dt {
  font-size: var(--text-sm);
  font-weight: var(--weight-semibold);
  color: var(--color-text-muted);
}

.policy-form dd {
  margin: 0;
}

/* Rótulo acima do valor onde duas colunas não cabem — `max-content` na primeira coluna é o
   que mantém os rótulos alinhados no desktop, e é também o que espremeria o valor aqui. */
@media (max-width: 600px) {
  .policy-form dl {
    grid-template-columns: 1fr;
    gap: var(--space-1);
  }

  .policy-form dd + dt {
    margin-top: var(--space-3);
  }
}
</style>
