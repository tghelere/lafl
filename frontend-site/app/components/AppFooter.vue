<script setup lang="ts">
// Endereços, telefones e CNPJ vêm de docs/contexto.md — dados confirmados, sem marca
// [CONFIRMAR]. Sede/CEI e Bazar são dois locais distintos, não um só (ver docs/contexto.md).
// Não inventar número novo aqui; qualquer outro dado institucional (e-mail, PIX) fica para
// quando /api/v1/public/settings existir (ver docs/estrutura-site.md §3.1).
//
// Links vêm de app/config/navigation.ts — mesma fonte do header e da gaveta (ver
// docs/design/navegacao.md §3). Uma coluna por item de topo: o título da coluna é o próprio
// link para a seção (não repete como primeiro item da lista abaixo — duplicava "Quem somos"
// dentro de "Quem somos"). Item sem filhos (Transparência, Contato) fica só com o título
// clicável, sem lista. Nenhum link solto aqui além destes.
import { MapPin, Phone } from '@lucide/vue'
import { navigation } from '~/config/navigation'

import logoHorizontal from '../../../shared/brand/lar-analia-franco/lar-analia-franco-horizontal.svg'
import softhingLogo from '../../../shared/brand/softhing/softhing-fundo-escuro.svg'
import { useUmamiTrack } from '~/composables/useUmamiTrack'

const year = new Date().getFullYear()

const { track } = useUmamiTrack()

function trackCreditoSofthing(): void {
  track('credito-softhing:site')
}
</script>

<template>
  <footer class="site-footer">
    <div class="container">
      <NuxtLink to="/" class="site-footer__brand" aria-label="Lar Anália Franco — página inicial">
        <img
          :src="logoHorizontal"
          width="86"
          height="40"
          alt=""
          class="site-footer__logo"
        >
      </NuxtLink>

      <div class="site-footer__grid">
        <div v-for="item in navigation" :key="item.to">
          <h2><NuxtLink :to="item.to">{{ item.label }}</NuxtLink></h2>
          <ul v-if="item.children?.length">
            <li v-for="child in item.children" :key="child.to">
              <NuxtLink :to="child.to">{{ child.label }}</NuxtLink>
            </li>
          </ul>
        </div>
      </div>

      <div class="site-footer__base">
        <p>LAR ANÁLIA FRANCO DE LONDRINA — CNPJ 78.614.096/0001-75.</p>
        <p class="site-footer__line">
          <MapPin :size="14" aria-hidden="true" />
          Sede / CEI: Av. Anália Franco, 33 — Jd. Aeroporto, Londrina/PR
          <Phone :size="14" aria-hidden="true" />
          (43) 3325-8060
        </p>
        <p class="site-footer__line">
          <MapPin :size="14" aria-hidden="true" />
          Bazar: Rua Rosa Siqueira, 152 — Jd. Aeroporto, Londrina/PR
          <Phone :size="14" aria-hidden="true" />
          (43) 3322-2373
        </p>
        <p>
          © {{ year }} Lar Anália Franco.
          <NuxtLink to="/politica-de-privacidade">Política de privacidade</NuxtLink>
        </p>

        <p class="site-footer__credit">
          Desenvolvido por
          <a
            href="https://softhing.com.br/?utm_source=lar-analia-franco&utm_medium=referral&utm_campaign=credito-site"
            target="_blank"
            rel="noopener"
            aria-label="Softhing — abre o site da desenvolvedora em nova aba"
            @click="trackCreditoSofthing"
          >
            <img
              :src="softhingLogo"
              width="73"
              height="20"
              alt=""
              class="site-footer__credit-logo"
            >
          </a>
        </p>
      </div>
    </div>
  </footer>
</template>
