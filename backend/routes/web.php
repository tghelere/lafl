<?php

declare(strict_types=1);

// API REST pura, sem views (ver CLAUDE.md). Nenhuma rota de conteúdo aqui — este arquivo
// existe apenas porque o grupo de middleware 'web' (sessão, cookie, CSRF) é o que sustenta
// o cookie httpOnly do Sanctum em SPA mode; as rotas reais estão em routes/api.php.
