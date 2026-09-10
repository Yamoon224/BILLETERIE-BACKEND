<?php

use App\Domains\Shared\Http\Controllers\DocumentationController;
use App\Domains\Shared\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes web
|--------------------------------------------------------------------------
|
| Le backend est une API : les seules pages HTML servies sont la page de
| presentation et la documentation Swagger. L'interface voyageur et le
| tableau de bord vivent dans le projet Next.js (`web/`).
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/docs', [DocumentationController::class, 'ui'])->name('docs');
Route::get('/docs/openapi.json', [DocumentationController::class, 'specification'])->name('docs.openapi');
