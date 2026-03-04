<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BaseStream API Routes
|--------------------------------------------------------------------------
|
| Prefixo: /api
|
| Endpoints principais:
| POST   /api/register          → Criar conta
| POST   /api/login             → Login + Sanctum token
| POST   /api/logout            → Logout
| GET    /api/me                → Perfil do usuário
|
| GET    /api/tokens            → Lista tokens do usuário
| POST   /api/tokens            → Criar token nomeado
| DELETE /api/tokens/{id}       → Revogar token
|
| GET    /api/stream?id=X       → Resolve stream (token auth)
| GET    /api/streams           → Lista streams disponíveis
| GET    /api/stream/proxy?url= → HLS proxy com CORS
*/

// ─── Public routes ───
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ─── Sanctum protected routes ───
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Token management
    Route::get('/tokens', [TokenController::class, 'index']);
    Route::post('/tokens', [TokenController::class, 'store']);
    Route::delete('/tokens/{id}', [TokenController::class, 'destroy']);

    // Streams (lista - precisa Sanctum)
    Route::get('/streams', [StreamController::class, 'list']);
});

// ─── Stream endpoints (custom token auth via bs_xxx) ───
Route::middleware('throttle:stream')->group(function () {
    Route::get('/stream', [StreamController::class, 'resolve']);
    Route::get('/stream/proxy', [StreamController::class, 'proxy']);
});
