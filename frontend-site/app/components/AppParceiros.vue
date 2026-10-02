<script setup lang="ts">
// Grade de parceiros da página de parceiros (abaixo do texto editável). Cartões de tamanho
// igual: caixa da logo com proporção fixa e a logo INTEIRA visível (object-fit: contain), nome
// embaixo. Com link, o cartão inteiro é o link e abre em outra aba; sem link, é só um cartão —
// sem cursor, sem hover e sem foco, para não parecer clicável.
//
// Sem parceiros, nada é desenhado: nem título, nem mensagem de lista vazia.
const { data: parceiros } = await usePublicPartners()
</script>

<template>
  <section v-if="parceiros && parceiros.length > 0" class="parceiros" aria-label="Nossos parceiros">
    <ul class="parceiros__grade">
      <li v-for="parceiro in parceiros" :key="parceiro.logo.src" class="parceiros__item">
        <component
          :is="parceiro.url ? 'a' : 'div'"
          class="parceiros__cartao"
          :class="{ 'parceiros__cartao--link': parceiro.url }"
          :href="parceiro.url || undefined"
          :target="parceiro.url ? '_blank' : undefined"
          :rel="parceiro.url ? 'noopener' : undefined"
        >
          <span class="parceiros__logo">
            <img
              :src="parceiro.logo.src"
              :srcset="parceiro.logo.srcset"
              sizes="(min-width: 64rem) 25vw, (min-width: 40rem) 33vw, 50vw"
              :width="parceiro.logo.width"
              :height="parceiro.logo.height"
              :alt="parceiro.name"
              loading="lazy"
              decoding="async"
            />
          </span>
          <span class="parceiros__nome">
            {{ parceiro.name }}
            <span v-if="parceiro.url" class="visually-hidden"> (abre em nova aba)</span>
          </span>
        </component>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.parceiros {
  margin-block: var(--space-7) 0;
}

/* Sem margem lateral nem max-width próprios: a grade ocupa a largura útil do .container e
   fica alinhada às bordas do texto e das imagens do resto do site. */
.parceiros__grade {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
  margin: 0;
  padding: 0;
  list-style: none;
}

@media (min-width: 40rem) {
  .parceiros__grade {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--space-5);
  }
}

@media (min-width: 64rem) {
  .parceiros__grade {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

/* A <li> estica o cartão: todos os cartões de uma linha têm a mesma altura, mesmo com nome de
   duas linhas num e de uma noutro. */
.parceiros__item {
  display: flex;
  margin: 0;
}

.parceiros__cartao {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-raised);
  color: var(--color-text);
  text-decoration: none;
}

/* Proporção fixa: a caixa é a mesma para logo larga e para logo quadrada. O respiro interno
   fica no padding; a imagem ocupa o que sobra e nunca é cortada. */
.parceiros__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 3 / 2;
  padding: var(--space-3);
  border-radius: var(--radius-sm);
  background: #fff;
}

.parceiros__logo img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.parceiros__nome {
  font-size: var(--text-sm);
  font-weight: var(--weight-semibold);
  line-height: var(--leading-snug, 1.35);
  text-align: center;
  overflow-wrap: anywhere;
}

.parceiros__cartao--link {
  cursor: pointer;
  transition: border-color var(--duration-fast), box-shadow var(--duration-fast);
}

.parceiros__cartao--link:hover {
  border-color: var(--color-focus);
  box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
}

/* O anel global de :focus-visible (base.css) cobre o cartão; o recuo evita que o raio do
   cartão o corte. */
.parceiros__cartao--link:focus-visible {
  outline-offset: 2px;
}
</style>
