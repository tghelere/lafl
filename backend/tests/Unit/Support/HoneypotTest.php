<?php

declare(strict_types=1);

use App\Support\Honeypot;
use Illuminate\Http\Request;

test('honeypot não dispara quando o campo está vazio', function (): void {
    $request = Request::create('/x', 'POST', ['website' => '']);

    expect(Honeypot::triggered($request))->toBeFalse();
});

test('honeypot não dispara quando o campo está ausente', function (): void {
    $request = Request::create('/x', 'POST', []);

    expect(Honeypot::triggered($request))->toBeFalse();
});

test('honeypot dispara quando o campo vem preenchido', function (): void {
    $request = Request::create('/x', 'POST', ['website' => 'https://bot.example']);

    expect(Honeypot::triggered($request))->toBeTrue();
});
