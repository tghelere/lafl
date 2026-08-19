# 0001 — Normalização do blind index é autocontida e congelada

## Contexto

`App\Support\StringNormalizer::normalize()` produz o valor que alimenta todo blind
index (HMAC) descrito em `docs/protecao-de-dados.md`. Antes desta decisão, a
transliteração (remoção de acento) delegava para `Illuminate\Support\Str::ascii()`,
que por sua vez usa a tabela do pacote `voku/portable-ascii`.

Isso é um risco de correção, não só de estilo: se um `composer update` trouxer uma
versão nova dessa tabela e ela mapear algum caractere de forma diferente, o hash HMAC
de um valor gravado antes deixa de bater com o hash do mesmo valor normalizado depois.
Não há erro, exceção ou log — o blind index simplesmente para de encontrar o registro.
Como nenhuma entidade real (assistido, responsável) usa blind index em produção ainda,
este é o momento de eliminar essa dependência transitiva mutável antes que o custo de
corrigir vire um re-hash de base real.

## Decisão

1. **Transliteração própria**, sem depender de pacote de terceiro: uma tabela fixa
   (`StringNormalizer::TRANSLITERATION_MAP`) cobrindo vogais acentuadas minúsculas e
   `ç` — o que importa para nomes em português. Caractere fora da tabela **passa
   inalterado**, nunca é removido ou substituído por placeholder: descartar caracteres
   desconhecidos criaria risco de colisão (dois valores diferentes normalizando para o
   mesmo hash), que é pior para um blind index do que preservar a distinção. Isso é
   uma mudança de contrato deliberada em relação ao `Str::ascii()` antigo, que descarta
   caracteres sem mapeamento.

2. **Ordem das operações em `normalize()`:**
   1. `trim()`
   2. Colapsar espaços (`preg_replace('/\s+/u', ' ', ...)`)
   3. `mb_strtolower($value, 'UTF-8')`
   4. `Normalizer::normalize($value, Normalizer::FORM_C) ?: $value`
   5. `strtr($value, TRANSLITERATION_MAP)`

   O NFC (`ext-intl`) roda **depois** do case-folding e **imediatamente antes** do
   `strtr`, por robustez: qualquer transformação de string que rodasse depois do NFC
   poderia, em tese, desnormalizar a saída de volta para uma forma decomposta antes de
   chegar à tabela. Colocar o NFC no último ponto possível antes da consulta à tabela
   elimina essa classe de problema por construção, sem depender de mapear caso a caso
   quais transformações preservam forma normalizada e quais não. `trim()` e o colapso
   de espaço operam só sobre espaço ASCII e são seguros antes do NFC.

   Não fechamos, dentro desta decisão, um exemplo concreto de caractere relevante ao
   português onde a ordem (NFC antes vs. depois do `mb_strtolower`) muda o resultado
   final — a instrução de robustez acima vale por si, independente de existir hoje um
   caso que a torne observável. Fica anotado aqui para não reabrir a investigação sem
   necessidade; se alguém encontrar tal caso, é auto-evidente pelo golden test.

3. **NFC não reintroduz o problema original.** A Política de Estabilidade Unicode
   garante que as formas de normalização (NFC/NFD/NFKC/NFKD) não mudam para caracteres
   já atribuídos — é garantia normativa do padrão, não conteúdo arbitrário de
   biblioteca sujeito a mudar num `composer update`. `ext-intl` já está instalada no
   `docker/php/Dockerfile` e habilitada no CI (`.github/workflows/ci.yml`); foi
   adicionada como `"ext-intl": "*"` em `backend/composer.json` para que a ausência da
   extensão falhe alto no `composer install`, não silenciosamente em runtime.

4. **Golden test** em `backend/tests/Unit/Support/StringNormalizerGoldenHashTest.php`:
   corpus fixo com hash HMAC esperado escrito literalmente (chave fixa definida no
   próprio teste, independente da chave de produção), cobrindo acento, cedilha, trema,
   maiúscula, espaços, hífen, apóstrofo, número e o par NFC/NFD. Existe para que
   qualquer regressão futura na normalização quebre o CI de forma óbvia — nunca deve
   ser "consertado" recalculando os valores esperados.

## Alternativas descartadas

- **Pin de versão do `voku/portable-ascii`.** Não impede alguém de rodar
  `composer update voku/portable-ascii` deliberadamente, não sobrevive a um lockfile
  regenerado do zero, e deixa a garantia de imutabilidade fora do arquivo que ela
  protege — ninguém vai pensar em checar uma trava de versão de dependência transitiva
  ao revisar `StringNormalizer.php`.
- **Manter `Str::ascii()` aceitando o risco.** É justamente o problema que esta
  decisão resolve.

## Consequências

- A normalização do blind index não depende mais de nenhuma tabela de terceiro; sua
  única dependência é a Política de Estabilidade Unicode (via `ext-intl`) e a tabela
  própria neste arquivo.
- Ampliar o suporte de idiomas/caracteres no futuro exige editar
  `TRANSLITERATION_MAP` manualmente e **obriga re-hash de qualquer dado já gravado**
  cujo hash dependa do caractere alterado — isso não é um custo incidental, é o
  propósito da normalização ser congelada.
- O golden test é a trava de regressão para toda esta decisão, incluindo o par
  NFC/NFD; qualquer mudança nos valores esperados deve ser tratada como incidente, não
  como atualização de teste.
