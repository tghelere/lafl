<script setup lang="ts">
// Endereços, telefones e CNPJ vêm de docs/contexto.md — dados confirmados, sem marca
// [CONFIRMAR]. Sede/CEI e Bazar são dois locais distintos, não um só (ver docs/contexto.md).
// Não inventar número novo aqui; qualquer outro dado institucional (e-mail, PIX) fica para
// quando /api/v1/public/settings existir (ver docs/estrutura-site.md §3.1).
//
// Links vêm de app/config/navigation.ts — mesma fonte do header e da gaveta (ver
// docs/design/navegacao.md §3). Uma coluna por item de topo: o próprio item primeiro, filhos
// em seguida. Nenhum link solto aqui além destes.
import { MapPin, Phone } from '@lucide/vue'
import { navigation } from '~/config/navigation'

const year = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="container">
      <div class="site-footer__grid">
        <div v-for="item in navigation" :key="item.to">
          <h2>{{ item.label }}</h2>
          <ul>
            <li><NuxtLink :to="item.to">{{ item.label }}</NuxtLink></li>
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
      </div>
    </div>
  </footer>
</template>
