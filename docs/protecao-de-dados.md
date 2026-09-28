# Proteção de Dados

> **Aviso:** este documento é orientação de engenharia, não parecer jurídico. Antes de entrar
> em produção com dados reais, a instituição precisa de validação por advogado e de um
> Encarregado (DPO) nomeado.

## Premissa

Todos os assistidos são **crianças e adolescentes**. São titulares hipervulneráveis. Cada
decisão de schema, autorização e exposição de dado parte disso.

## Base legal

**LGPD art. 14** — tratamento de dados de criança e adolescente exige consentimento
**específico e em destaque** de ao menos um dos pais ou do responsável legal, e todo
tratamento deve atender ao **melhor interesse** da criança.

**LGPD art. 14, §3º** — é vedado condicionar a participação da criança ao fornecimento de
dados além do estritamente necessário. Minimização aqui é obrigação, não boa prática.

**LGPD art. 5º, II** — dados sensíveis (saúde, origem racial, religião, biometria) têm regime
próprio.

**LGPD art. 16, I** — obrigação legal de guarda pode prevalecer sobre pedido de eliminação.
Prestação de contas a órgãos públicos e ao convênio com a Secretaria Municipal de Educação
entra aqui.

**ECA art. 17 e 18** — direito ao respeito, à imagem e à identidade.

## Matriz de classificação de dados

Antes de criar qualquer coluna com dado pessoal, classifique aqui.

| Dado | Tratamento | Buscável? |
|---|---|---|
| Nome do assistido | Criptografado + blind index (normalizado e por tokens) | Palavra exata |
| CPF, RG, CNS, NIS, certidão | Criptografado + blind index HMAC | Igualdade exata |
| Endereço, escola | Criptografado, Policy restrita | Não |
| Alergia, restrição alimentar, medicação de uso contínuo | Criptografado, **tabela separada** | Não |
| Dados de responsável legal | Criptografado + blind index | Igualdade exata |
| Data de nascimento | Texto puro (necessária para faixa etária e maioridade) | Sim |
| Código interno, iniciais | Texto puro | Sim |
| Foto | Arquivo protegido, EXIF removido, servido por rota autenticada | — |

### Implementação

**Criptografia:** cast `encrypted` do Eloquent (AES-256-GCM). Coluna sempre `text`, nunca
`varchar(n)` — o valor cifrado cresce cerca de 1,4x.

**Blind index para igualdade** (documentos):

```
cpf              text        -- encrypted
cpf_hash         char(64)    -- HMAC-SHA256 do valor normalizado, indexado
cpf_last_digits  char(3)     -- texto puro, para exibição mascarada
```

O hash permite busca exata e checagem de duplicidade — os únicos usos reais de um CPF — sem
descriptografar nada.

**Blind index para nome:**

```
name             text        -- encrypted
name_hash        char(64)    -- HMAC do nome completo normalizado
name_tokens      char(64)[]  -- HMAC de cada token, índice GIN
```

Permite busca por palavra inteira. **Não permite** `LIKE '%mar%'` — isso é limitação aceita
conscientemente, não bug a corrigir. Dificultar consulta em massa é proteção.

Normalização antes do hash: minúsculas, sem acento, espaços colapsados. A função de
normalização precisa ser única e centralizada, senão o índice quebra silenciosamente.

A normalização (`App\Support\StringNormalizer`) é autocontida e congelada por design —
não depende de tabela de transliteração de nenhum pacote de terceiro, só de uma tabela
própria e da Política de Estabilidade Unicode. Mudar essa normalização exige re-hash de
toda a base já gravada; ver `docs/decisoes/0001-normalizacao-blind-index-autocontida.md`.

**Chaves:**

- Chave de campo **separada do `APP_KEY`**, fora do repositório, em gerenciador de segredos
- Chave do HMAC dos blind indexes separada da chave de criptografia
- Rotação planejada desde o início (`APP_PREVIOUS_KEYS` + comando de re-encriptação em lote)
- Backup da chave em local distinto do backup do banco — **perder a chave é perder os dados**

**Convenção de escrita:** campo cifrado é escrito **apenas via instância de model**, nunca
por query builder (`Model::query()->update()`, `upsert()`, `insert()` em massa) — esses
caminhos não disparam o hook que sincroniza o blind index, e ainda gravariam texto puro por
não passarem pelo cast.

## Padrão de exposição na interface

- **Listagens:** código interno + iniciais + idade. Nunca nome completo.
- **Ficha individual:** nome completo, com acesso registrado em auditoria.
- **Relatórios e exportações:** agregados ou anonimizados por padrão; nominal exige permissão
  específica e fica logado.
- **Site público:** nunca nome completo de assistido, em nenhuma hipótese.

## Consentimento

Consentimento é **registro no banco**, não pasta de papel.

Entidade `consents`:

| Campo | Descrição |
|---|---|
| `assisted_minor_id` | titular |
| `guardian_id` | quem consentiu |
| `purpose` | enum: `care`, `image_use`, `institutional_communication` |
| `terms_version` | versão do texto aceito |
| `granted_at` | data |
| `revoked_at`, `revocation_reason` | revogação — nullable |
| `evidence_path` | termo assinado digitalizado, se houver |

Regras:

- Finalidades **separadas e independentes**. Consentir com atendimento não autoriza uso de
  imagem.
- Termos **versionados**: mudou o texto, o consentimento anterior não cobre a nova versão.
- Consentimento é **revogável a qualquer tempo**, e a revogação precisa ter efeito no sistema
  imediatamente (foto sai do ar, comunicação para).
- **Regra travada na API:** imagem sem consentimento de uso vigente não é publicável. Não é
  validação de interface.

## Maioridade

Ao completar 18 anos, o regime muda: o consentimento do responsável deixa de valer e é
preciso recoletar do próprio titular.

Implementar como **job agendado** que gera alerta antecipado (sugestão: 30 dias antes), nunca
como controle manual.

## Retenção e eliminação

- Prazo de retenção definido **por tipo de registro**, no inventário de dados
- Job de descarte automatizado, não exclusão manual
- Soft delete para reversão de erro; hard delete real ao fim do prazo legal
- Pedido de eliminação não é automático: verificar se há obrigação legal de guarda (art. 16, I)
- Backups seguem o mesmo prazo de retenção dos dados

## Direitos do titular

Acesso, correção, eliminação, portabilidade e informação sobre compartilhamento.

Implementação inicial: tela de exportação por titular (JSON e PDF), acessível apenas a papel
autorizado, com o ato registrado em auditoria.

## Auditoria

`spatie/laravel-activitylog`, registrando:

- Quem acessou ficha de assistido, quando, de qual IP
- Toda alteração de dado pessoal (campo alterado, não o valor)
- Toda exportação de dados
- Toda alteração de permissão

**O log nunca registra valor descriptografado.** Caso contrário vira uma cópia em texto puro
de tudo que se tentou proteger.

### Onde os acessos são registrados hoje

Tudo na tabela `activity_log`. Quatro origens, e nenhuma delas guarda valor de campo pessoal:

| Origem | `log_name` | `event` | Grava IP? |
|---|---|---|---|
| `App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess` — chamado em todo `show()` administrativo de formulário | `forms` | `viewed` | sim, em `properties.ip` |
| `App\Actions\Forms\MarkSubmissionAsUnread` | `forms` | `marked_unread` | sim |
| `LogsActivity` dos models de formulário (`App\Models\Concerns\IsFormSubmission`) | `default` | `created`, `updated` | não — não há requisição no evento de model |
| Contas e sessão (`App\Actions\Users\*`, `App\Listeners\Auth\*`) | `users`, `auth` | vários | conforme a ação |

Leitura: `GET /api/v1/audit-logs` e a tela **Auditoria** do painel, só `super_admin` (ver
`App\Policies\ActivityPolicy`). A tela mostra apenas as entradas cujo sujeito é um formulário
recebido — auditoria de conta é outra tela, quando existir.

**O que a tela nunca mostra:** o `attribute_changes` do spatie. Ela diz QUE houve alteração, não o
quê. Uma tela de auditoria que exibisse o diff seria a cópia em texto puro que a criptografia
existe para evitar. Também não expõe o id da linha de log, que é sequencial.

O detalhe de cada formulário traz, para `super_admin`, a mesma informação recortada para aquele
registro ("Histórico de acessos", recolhido). Para todos os papéis, o rodapé do registro traz uma
linha discreta dizendo que os acessos ficam na auditoria — nota, não alerta: é verdade permanente
sobre todo registro, e um banner destacado em toda tela deixa de ser lido.

## Uploads de imagem

1. Validar MIME real, não extensão
2. **Remover EXIF integralmente** — metadado de geolocalização em foto de criança é o
   vazamento mais comum em site institucional
3. Converter para WebP, gerar thumbnails em fila
4. Armazenar fora do webroot
5. Servir por rota autenticada com Policy; foto de assistido nunca tem URL pública direta
6. Foto no site público: apenas com consentimento vigente e **sem nome completo associado**

## Transferência internacional

**O servidor fica nos Estados Unidos** (Hostinger; `whois` do IP devolve `country: US`). Não foi
escolha de arquitetura: o data center brasileiro do provedor está indisponível, e essa foi a
alternativa. Vale para homologação e, enquanto a situação não mudar, valerá para produção.

Isso é **transferência internacional de dados** (LGPD art. 33). Consequências práticas, não
formais:

- Os Estados Unidos não constam de decisão de adequação da ANPD. A transferência se apoia no
  consentimento do titular (art. 33, VIII), que precisa ser **específico e destacado** — é por
  isso que `/politica-de-privacidade` tem seção própria sobre isso, e não uma linha perdida no
  meio de outro parágrafo.
- Consentimento como base de transferência é o fundamento mais frágil dos disponíveis: é
  revogável, e não cobre tratamento sem consentimento. **Nenhum dado de assistido pode ir para
  esse servidor** enquanto o domínio de assistidos existir só no papel — quando existir, a
  decisão de onde ele roda volta à mesa, e a resposta provavelmente não é "no mesmo lugar".
- A cifra de campo (`FieldEncrypted`) e a chave fora do banco continuam sendo a única proteção
  que independe de jurisdição. Isso é argumento a favor de cifrar mais campos, não de relaxar.
- Voltar a hospedagem para o Brasil é mudança de política pública: exige atualizar
  `/politica-de-privacidade` e subir `FORM_CONSENT_TERMS_VERSION`.

## Resposta a incidente

Plano documentado, com responsável nomeado e prazo de comunicação à ANPD e aos titulares.
Ensaiar pelo menos uma vez antes de produção.

## Pendências com a instituição

- [ ] Preencher `docs/lgpd/inventario-de-dados.md`: cada campo pessoal, finalidade, base
      legal, prazo de retenção
- [ ] Nomear Encarregado (DPO) e publicar contato no site
- [ ] Redigir e versionar termos de consentimento (atendimento, imagem, comunicação)
- [x] Publicar política de privacidade — `/politica-de-privacidade`, reescrita na sessão 20 a
      partir do código. Continua dependendo dos dois itens acima antes de produção
- [ ] Validação jurídica antes de produção
- [ ] Definir prazos legais de guarda por tipo de documento
