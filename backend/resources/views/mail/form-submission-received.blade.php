@component('mail::message')
# Novo formulário recebido

**Tipo:** {{ $typeLabel }}
**Recebido em:** {{ $submittedAt }}

Este e-mail não contém nenhum dado pessoal de quem enviou — os detalhes completos estão só no
painel administrativo.

@component('mail::button', ['url' => $adminUrl])
Ver no painel
@endcomponent

Se o botão não funcionar, copie e cole este link no navegador:
{{ $adminUrl }}
@endcomponent
