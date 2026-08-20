<script setup lang="ts">
// Endereço e CNPJ vêm de docs/contexto.md — dados confirmados, sem marca [CONFIRMAR]. Não
// inventar número novo aqui; qualquer outro dado institucional (telefone, e-mail, PIX) fica
// para quando /api/v1/public/settings existir (ver docs/estrutura-site.md §3.1).
const year = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="container">
      <div class="site-footer__grid">
        <div>
          <h2>Instituição</h2>
          <ul>
            <li><NuxtLink to="/quem-somos">Quem somos</NuxtLink></li>
            <li><NuxtLink to="/quem-somos/nossa-historia">Nossa história</NuxtLink></li>
            <li><NuxtLink to="/quem-somos/governanca">Governança</NuxtLink></li>
            <li><NuxtLink to="/quem-somos/o-lar-hoje">O Lar hoje</NuxtLink></li>
            <li><NuxtLink to="/contato">Contato</NuxtLink></li>
          </ul>
        </div>

        <div>
          <h2>O que fazemos</h2>
          <ul>
            <li><NuxtLink to="/educacao-infantil">Educação infantil</NuxtLink></li>
            <li><NuxtLink to="/contraturno">Contraturno</NuxtLink></li>
            <li><NuxtLink to="/bazar">Bazar beneficente</NuxtLink></li>
          </ul>
        </div>

        <div>
          <h2>Como ajudar</h2>
          <ul>
            <li><NuxtLink to="/como-ajudar/doar">Doar</NuxtLink></li>
            <li><NuxtLink to="/bazar/agendar-coleta">Doar itens</NuxtLink></li>
            <li><NuxtLink to="/como-ajudar/voluntariado">Voluntariado</NuxtLink></li>
          </ul>
        </div>

        <div>
          <h2>Transparência</h2>
          <ul>
            <li><NuxtLink to="/transparencia">Prestação de contas</NuxtLink></li>
            <li><NuxtLink to="/transparencia/documentos">Documentos</NuxtLink></li>
          </ul>
        </div>
      </div>

      <div class="site-footer__base">
        <p>
          Lar Anália Franco de Londrina — Av. Anália Franco, 33, Bairro Aeroporto, Londrina/PR.
          CNPJ 78.614.096/0001-75.
        </p>
        <p>
          © {{ year }} Lar Anália Franco.
          <NuxtLink to="/politica-de-privacidade">Política de privacidade</NuxtLink>
        </p>
      </div>
    </div>
  </footer>
</template>
