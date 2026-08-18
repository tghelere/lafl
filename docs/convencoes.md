# Convenções de Código

## Backend

### Estrutura

```
backend/app/
├── Actions/            # regra de negócio, uma classe por operação
├── Enums/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/       # FormRequests
│   ├── Resources/      # API Resources
│   └── Middleware/
├── Models/
├── Policies/
├── Services/           # integrações externas, criptografia, blind index
├── Events/ Listeners/
├── Jobs/
└── Support/            # helpers puros (normalização de string, etc.)
```

### Actions

Uma classe, uma operação, um método público `handle()`. Nome descreve a intenção:
`RegisterAssistedMinor`, `RevokeImageConsent`, `PublishPost`.

Actions não conhecem HTTP. Recebem DTO ou parâmetros tipados, nunca `Request`.
Transação dentro da Action, não no controller.

### Controllers

```php
public function store(StoreAssistedMinorRequest $request, RegisterAssistedMinor $action)
{
    $minor = $action->handle($request->toDto());

    return new AssistedMinorResource($minor);
}
```

Se passar de ~15 linhas, tem regra de negócio no lugar errado.

### FormRequests

- Toda entrada, sem exceção
- `authorize()` delega à Policy, não repete regra
- Mensagens em português
- Regras de formato aqui; regras de domínio na Action

### API Resources

- Toda saída, sem exceção
- Campo pessoal só aparece se a Policy permitir — usar `when()` com checagem explícita
- Nunca consultar banco dentro de Resource (N+1)
- Documento sempre mascarado por padrão; valor completo é endpoint separado e auditado

### Enums

```php
enum ConsentPurpose: string
{
    case Care = 'care';
    case ImageUse = 'image_use';
    case InstitutionalCommunication = 'institutional_communication';

    public function label(): string { /* português, para exibição */ }
}
```

Espelhar com constraint `CHECK` na migration.

### Migrations

- Sempre reversíveis
- Nenhuma regra de negócio dentro
- Índice explícito para toda FK
- Coluna cifrada como `text`
- Comentário na coluna indicando a classificação do dado

### Testes (Pest)

Obrigatórios para:
- Todo endpoint de escrita
- Todo endpoint que toque dado de assistido
- Toda Policy
- Fluxo de consentimento e revogação
- Blind index: garantir que a normalização é estável

Nunca usar dado real em fixture ou seeder. Factories geram dados sintéticos.

### Qualidade

- Pint com preset Laravel
- Larastan em nível alto
- `declare(strict_types=1)` em todo arquivo
- Tipagem em parâmetros e retornos, sempre

## Frontend (ambos)

### Regra central

**Zero regra de negócio.** O front:
- valida formato para feedback imediato (e-mail parece e-mail, campo obrigatório preenchido)
- exibe ou esconde elementos conforme permissão recebida da API
- **nunca** decide se algo pode ser publicado, se consentimento é válido, se prazo expirou

A resposta da API é a verdade, inclusive nas mensagens de erro exibidas ao usuário.

### Organização

```
src/
├── components/     # apresentação, sem lógica de dados
├── composables/    # lógica reutilizável
├── services/       # camada única de acesso HTTP
├── stores/         # Pinia
├── views/ ou pages/
└── types/
```

- Componentes pequenos e de responsabilidade única
- Uma camada de serviço HTTP centralizada, com interceptors para CSRF, erro e sessão expirada
- Nenhum `fetch` ou `axios` solto em componente
- TypeScript, com tipos gerados a partir do OpenAPI quando possível
- Composition API com `<script setup>`

### Painel admin

- `noindex` em tudo
- Timeout de sessão por inatividade
- Confirmação explícita em ação destrutiva
- Listagem de assistidos exibe código interno + iniciais + idade; nunca nome completo
- Nenhum dado pessoal em URL, query string ou `localStorage`

### Site público

- SSR/SSG; conteúdo institucional não pode depender de JS
- Meta tags vindas da API por página
- Imagens com `alt` sempre presente
- Eventos Umami nos CTAs relevantes

## Git

- Conventional Commits, em português: `feat: cadastro de responsável legal`
- Branch por feature, a partir de `develop`
- PR exige CI verde
- Nunca commitar `.env`, chave, dump ou dado real

## O que não fazer

- `$request->all()`
- ID sequencial em rota
- Regra de negócio em controller, model, migration ou componente Vue
- Query dentro de Resource ou de laço
- Listagem sem paginação
- String mágica onde cabe Enum
- Checagem de papel inline em vez de Policy
- Comentário explicando o óbvio; comentar o **porquê**, não o **o quê**
- Dependência nova sem justificativa
