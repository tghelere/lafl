<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactMessageController;
use App\Http\Controllers\Api\V1\ContentMarkerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FormAuditController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PageImageController;
use App\Http\Controllers\Api\V1\PartnershipInquiryController;
use App\Http\Controllers\Api\V1\PickupRequestController;
use App\Http\Controllers\Api\V1\ProgramApplicationController;
use App\Http\Controllers\Api\V1\Public\ContactMessageController as PublicContactMessageController;
use App\Http\Controllers\Api\V1\Public\InstitutionFactsController as PublicInstitutionFactsController;
use App\Http\Controllers\Api\V1\Public\MediaController as PublicMediaController;
use App\Http\Controllers\Api\V1\Public\PageController as PublicPageController;
use App\Http\Controllers\Api\V1\Public\PartnershipInquiryController as PublicPartnershipInquiryController;
use App\Http\Controllers\Api\V1\Public\PickupRequestController as PublicPickupRequestController;
use App\Http\Controllers\Api\V1\Public\ProgramApplicationController as PublicProgramApplicationController;
use App\Http\Controllers\Api\V1\Public\TransparencyDocumentController as PublicTransparencyDocumentController;
use App\Http\Controllers\Api\V1\Public\VolunteerApplicationController as PublicVolunteerApplicationController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TransparencyDocumentController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VolunteerApplicationController;
use Illuminate\Support\Facades\Route;

// Aplicado a toda rota autenticada deste arquivo (ver bootstrap/app.php para o que cada uma
// faz) — 'auth:sanctum' resolve o usuário, 'active' barra quem foi desativado depois do
// login, 'auth.session' barra sessão cuja senha mudou em outro lugar (troca autenticada ou
// definição por link).
$authenticated = ['auth:sanctum', 'active', 'auth.session'];

Route::prefix('auth')->name('auth.')->group(function () use ($authenticated): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    // Pública, de propósito — quem chega até aqui já tem o token do link (ver
    // App\Actions\Users\GeneratePasswordLink). Nenhum dado pessoal na URL, o e-mail vem só
    // no corpo (ver App\Actions\Auth\SetUserPassword).
    Route::post('/set-password', [AuthController::class, 'setPassword'])
        ->middleware('throttle:set-password')
        ->name('set-password');

    Route::middleware($authenticated)->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');
    });
});

// Sem autenticação, só conteúdo publicado (ver docs/estrutura-site.md, Parte 3). `{slug}`
// aceita barra porque pages é plana com slug de até dois níveis (ex.:
// "quem-somos/nossa-historia") — ver decisão de formato de slug em docs/roadmap.md.
Route::prefix('public')->name('public.')->group(function (): void {
    // Antes da rota de slug, que casa com qualquer coisa: "/pages" sem slug é a listagem.
    Route::get('/pages', [PublicPageController::class, 'index'])->name('pages.index');

    Route::get('/pages/{slug}', [PublicPageController::class, 'show'])
        ->where('slug', '.*')
        ->name('pages.show');

    // Os mesmos números que os marcadores do CMS resolvem, para as páginas cujo conteúdo é
    // fixo no .vue (ver App\Enums\ContentMarker e frontend-site/app/pages/index.vue). Nada
    // aqui é dado pessoal — são datas do estatuto e a contagem do acervo já público.
    Route::get('/institution-facts', [PublicInstitutionFactsController::class, 'show'])
        ->name('institution-facts.show');

    Route::get('/transparency-documents', [PublicTransparencyDocumentController::class, 'index'])
        ->name('transparency-documents.index');

    // Espelha, segmento a segmento, a URL pública do site (/transparencia/documentos/{ano}/
    // {slug}.pdf, ver App\Support\Transparency\DocumentUrl): a rota do Nitro que a serve é
    // proxy puro, sem traduzir nada pelo caminho. É aqui, e não no site, que se decide 404,
    // 301 de ano trocado e contagem de download (regra 1 do CLAUDE.md).
    Route::get('/transparency-documents/{year}/{slug}.pdf', [PublicTransparencyDocumentController::class, 'file'])
        ->where(['year' => '[0-9]{4}', 'slug' => '[a-z0-9-]+'])
        ->name('transparency-documents.file');

    // Imagem da biblioteca para o site — espelha /midia/{uuid}[/{largura}.webp], servida pelo
    // proxy do Nitro (ver App\Support\Media\MediaUrl). Só derivada webp; a original não sai.
    Route::get('/media/{uuid}/{variant?}', [PublicMediaController::class, 'file'])
        ->where(['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}', 'variant' => '[0-9]{2,4}\.webp'])
        ->name('media.file');

    // Endereço antigo das fotos fixas do site — só 301 para /midia/ (ver o docblock).
    Route::get('/legacy-photos/{section}/{file}', [PublicMediaController::class, 'legacy'])
        ->where(['section' => '[a-z-]+', 'file' => '[a-z0-9-]+-[0-9]{2,4}\.(webp|jpg)'])
        ->name('media.legacy');

    // Endereço antigo — só 301 para o de cima (ver o docblock do controller).
    Route::get('/transparency-documents/{uuid}/download', [PublicTransparencyDocumentController::class, 'download'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('transparency-documents.download');

    // Cinco formulários públicos (ver docs/estrutura-site.md §3.2). Rate limit por IP e
    // honeypot em vez de CAPTCHA de terceiro (ver docs/roadmap.md e App\Support\Honeypot).
    Route::middleware('throttle:public-forms')->group(function (): void {
        Route::post('/program-applications', [PublicProgramApplicationController::class, 'store'])
            ->name('program-applications.store');
        Route::post('/pickup-requests', [PublicPickupRequestController::class, 'store'])
            ->name('pickup-requests.store');
        Route::post('/volunteer-applications', [PublicVolunteerApplicationController::class, 'store'])
            ->name('volunteer-applications.store');
        Route::post('/partnership-inquiries', [PublicPartnershipInquiryController::class, 'store'])
            ->name('partnership-inquiries.store');
        Route::post('/contact-messages', [PublicContactMessageController::class, 'store'])
            ->name('contact-messages.store');
    });
});

Route::middleware($authenticated)->group(function (): void {
    Route::apiResource('pages', PageController::class)->parameters(['pages' => 'page']);

    // "Imagens desta página": capa, galeria e as do texto, pela porta da página (ver
    // docs/decisoes/0025-imagens-da-pagina.md). Enviar põe no fim da galeria ou na capa
    // (`role`); o PUT de /cover troca a capa por imagem da biblioteca; o DELETE tira da capa ou
    // da galeria (`role`) e deixa a imagem na biblioteca.
    Route::get('/pages/{page}/images', [PageImageController::class, 'index'])->name('pages.images.index');
    Route::post('/pages/{page}/images', [PageImageController::class, 'store'])->name('pages.images.store');
    Route::put('/pages/{page}/images/cover', [PageImageController::class, 'setCover'])->name('pages.images.cover');
    Route::post('/pages/{page}/images/{media}/move', [PageImageController::class, 'move'])->name('pages.images.move');
    Route::delete('/pages/{page}/images/{media}', [PageImageController::class, 'destroy'])->name('pages.images.destroy');

    // Marcadores que o editor de páginas oferece, com o valor de agora (ver
    // App\Enums\ContentMarker). Sem paginação: é um enum de cinco casos, mesmo caso de
    // /roles.
    Route::get('/content-markers', [ContentMarkerController::class, 'index'])
        ->name('content-markers.index');
    Route::apiResource('transparency-documents', TransparencyDocumentController::class)
        ->parameters(['transparency-documents' => 'transparencyDocument']);

    // Biblioteca de imagens do conteúdo (ver docs/decisoes/0024-biblioteca-de-midia.md).
    // Substituir o arquivo é rota própria, em POST (multipart só é lido pelo PHP em POST), e
    // não um campo opcional do update: trocar a imagem e corrigir o texto alternativo são
    // atos diferentes, com eventos de auditoria diferentes.
    Route::apiResource('media', MediaController::class)->parameters(['media' => 'media']);
    Route::post('/media/{media}/file', [MediaController::class, 'replaceFile'])->name('media.replace-file');
    Route::get('/media/{media}/file/{variant}', [MediaController::class, 'file'])
        ->where('variant', 'original|[0-9]{2,4}\.webp')
        ->name('media.file');

    // Gestão de usuários — só super_admin (ver App\Policies\UserPolicy). Sem destroy: contas
    // só desativam, nunca se apagam (ver docs/levantamento-painel.md, item 2).
    Route::apiResource('users', UserController::class)
        ->parameters(['users' => 'user'])
        ->except(['destroy']);
    Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::post('/users/{user}/password-link', [UserController::class, 'generatePasswordLink'])->name('users.password-link');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard.show');

    // Auditoria dos formulários recebidos — só leitura, e só super_admin (ver
    // App\Policies\ActivityPolicy). Serve à tela "Auditoria" e, com `?record={uuid}`, à seção
    // "Histórico de acessos" do detalhe de um registro.
    Route::get('/audit-logs', [FormAuditController::class, 'index'])->name('audit-logs.index');
    // A aba "Imagens" da mesma tela: envio, troca, exclusão, marcação e a posição nas páginas.
    Route::get('/audit-logs/media', [FormAuditController::class, 'media'])->name('audit-logs.media');

    // Leitura administrativa dos cinco formulários recebidos (ver docs/estrutura-site.md
    // §4.5): listagem, detalhe, mudança de status com anotação e o desfazer da leitura — nada
    // de criação/exclusão aqui, os registros só nascem pelo formulário público (ver Parte 3
    // daquele documento).
    //
    // Não existe rota para MARCAR como lido: abrir o detalhe é a leitura (ver
    // docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md). `DELETE .../read`
    // desfaz — a leitura é o recurso, e marcar como não lido é apagá-la.
    Route::apiResource('program-applications', ProgramApplicationController::class)
        ->only(['index', 'show'])
        ->parameters(['program-applications' => 'programApplication']);
    Route::patch('/program-applications/{programApplication}/status', [ProgramApplicationController::class, 'updateStatus'])
        ->name('program-applications.status');
    Route::delete('/program-applications/{programApplication}/read', [ProgramApplicationController::class, 'markUnread'])
        ->name('program-applications.read.destroy');

    Route::apiResource('pickup-requests', PickupRequestController::class)
        ->only(['index', 'show'])
        ->parameters(['pickup-requests' => 'pickupRequest']);
    Route::patch('/pickup-requests/{pickupRequest}/status', [PickupRequestController::class, 'updateStatus'])
        ->name('pickup-requests.status');
    Route::delete('/pickup-requests/{pickupRequest}/read', [PickupRequestController::class, 'markUnread'])
        ->name('pickup-requests.read.destroy');

    Route::apiResource('volunteer-applications', VolunteerApplicationController::class)
        ->only(['index', 'show'])
        ->parameters(['volunteer-applications' => 'volunteerApplication']);
    Route::patch('/volunteer-applications/{volunteerApplication}/status', [VolunteerApplicationController::class, 'updateStatus'])
        ->name('volunteer-applications.status');
    Route::delete('/volunteer-applications/{volunteerApplication}/read', [VolunteerApplicationController::class, 'markUnread'])
        ->name('volunteer-applications.read.destroy');

    Route::apiResource('partnership-inquiries', PartnershipInquiryController::class)
        ->only(['index', 'show'])
        ->parameters(['partnership-inquiries' => 'partnershipInquiry']);
    Route::patch('/partnership-inquiries/{partnershipInquiry}/status', [PartnershipInquiryController::class, 'updateStatus'])
        ->name('partnership-inquiries.status');
    Route::delete('/partnership-inquiries/{partnershipInquiry}/read', [PartnershipInquiryController::class, 'markUnread'])
        ->name('partnership-inquiries.read.destroy');

    Route::apiResource('contact-messages', ContactMessageController::class)
        ->only(['index', 'show'])
        ->parameters(['contact-messages' => 'contactMessage']);
    Route::patch('/contact-messages/{contactMessage}/status', [ContactMessageController::class, 'updateStatus'])
        ->name('contact-messages.status');
    Route::delete('/contact-messages/{contactMessage}/read', [ContactMessageController::class, 'markUnread'])
        ->name('contact-messages.read.destroy');
});
