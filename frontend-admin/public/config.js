/**
 * Configuração do painel em tempo de EXECUÇÃO.
 *
 * Este arquivo é servido como está, fora do bundle, e reescrito no servidor a cada
 * publicação com os valores do ambiente (ver infra/criar-ambiente.sh e
 * docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md). É o que permite o mesmo
 * `painel/` do pacote atender homologação e produção — sem ele, `VITE_API_URL` ficaria
 * gravada dentro do bundle e "promover para produção o pacote já validado em homologação"
 * publicaria um painel apontando para a API de homologação.
 *
 * Valor vazio significa "usa o do build" (as VITE_* de `.env`), que é o que vale em
 * desenvolvimento e na bateria de ponta a ponta. Ver src/config.ts.
 */
window.__LAF_CONFIG__ = {
  apiUrl: '',
  siteUrl: '',
  sessionIdleTimeoutMinutes: '',
}
