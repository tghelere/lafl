import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from './App.vue'
import router from './router'

// Ordem importa, mesma disciplina do site público: fontes antes de tokens de marca
// (compartilhados — cor e tipografia, única fonte de verdade com frontend-site), tokens de
// marca antes de tokens locais, tokens antes de base, base antes de componentes (ver
// frontend-site/nuxt.config.ts).
import './assets/css/fonts.css'
import '../../shared/design-tokens/tokens.css'
import './assets/css/tokens.css'
import './assets/css/base.css'
import './assets/css/components.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
