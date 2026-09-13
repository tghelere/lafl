# 0007 — Manifestação de interesse: nenhum formulário público coleta dado de menor

## Contexto

Dois dos seis formulários públicos (matrícula do CEI e inscrição no contraturno) se referem a
uma criança ou adolescente, mas são preenchidos por um site sem autenticação, sem 2FA, sem as
proteções que cercam o painel administrativo — exatamente o tipo de superfície onde um
titular hipervulnerável não deveria ter dado identificável exposto (`CLAUDE.md`).

## Decisão

Os formulários de matrícula e de inscrição no contraturno coletam apenas a **manifestação de
interesse do responsável adulto**: nome e contato dele, **faixa etária** da criança ou
adolescente (nunca data de nascimento), período pretendido. Nome, nascimento, documento,
endereço e escola da criança são coletados **presencialmente**, no momento da matrícula
efetiva, junto do termo de consentimento assinado — nunca pela web.

Consequências de modelagem já registradas em `docs/estrutura-site.md` §2.1 e `docs/dominio.md`:

- O titular do registro é o responsável adulto, não a criança — o art. 14 da LGPD (consentimento
  específico de responsável) não se aplica a esses dois registros, o que simplifica o
  consentimento web para eles.
- Faixa etária, nunca data de nascimento: data identifica, faixa não.
- O rótulo do campo de mensagem livre precisa avisar explicitamente que dado da criança é
  coletado presencialmente, para desencorajar o preenchimento espontâneo do nome dela.
- Mesmo assim, o campo `message` de ambos os formulários é criptografado por precaução —
  alguém vai escrever o nome do filho ali de qualquer forma.
- Nenhuma tabela de assistido é alimentada por endpoint público, em nenhuma hipótese.

## Alternativas descartadas

- **Coletar dado completo da criança no formulário web** (nome, data de nascimento,
  documento), como um cadastro de matrícula faria num sistema sem essa restrição. Colocaria
  dado identificável de menor atrás de um formulário público sem autenticação nem 2FA — o
  oposto do padrão de rigor que `docs/contexto.md` exige justamente por causa do histórico de
  2022.
- **Coletar data de nascimento em vez de faixa etária**, sob o argumento de que ajudaria a
  triagem. Data de nascimento singulariza o indivíduo; faixa etária não. A informação que a
  triagem realmente precisa nesta etapa é a faixa, não o dado exato.

## Consequências

- A Fase 2 (`assisted_minors`, `guardians`, cadastro efetivo) permanece bloqueada até o
  preenchimento de `docs/lgpd/inventario-de-dados.md`, sem que isso trave o site público —
  manifestação de interesse não depende da entidade de assistido existir.
- Teste Pest deve cobrir que os endpoints `POST /api/v1/public/enrollment-interests` e
  `POST /api/v1/public/program-applications` rejeitam ou não expõem qualquer schema que
  aceite data de nascimento, documento ou nome de criança como campo de primeira classe.
- Se alguém digitar o nome da criança no campo `message`, o registro passa a conter dado de
  menor de fato — por isso `message` é cifrado nesses dois formulários mesmo sendo, em tese,
  um formulário de titular adulto.

## Atualização (13/09/2026)

Os dois formulários que esta decisão previu deixaram de existir na forma descrita acima:

- **Matrícula do CEI:** o formulário de manifestação de interesse (`enrollment_interests`)
  foi **removido por completo**. A matrícula é feita exclusivamente pela Central de Vagas da
  Prefeitura de Londrina — a instituição não recebe pedido de vaga diretamente, então
  qualquer contato coletado pelo site careceria de finalidade (ver `docs/contexto.md`).
- **Inscrição no contraturno:** o programa ainda não abriu inscrições (`docs/contexto.md`).
  O que existe hoje (`program_applications`) não é mais uma manifestação de interesse sobre
  a criança — é um aviso de "me avise quando abrir", que nem pede faixa etária: só nome e
  telefone do responsável. A principal salvaguarda desta ADR (nenhum dado da criança) segue
  valendo, agora por um caminho mais simples — o formulário não pergunta nada sobre ela.
