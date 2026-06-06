<?php

use FelixMuhoro\MpesaCheckout\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

// STK Push initiation
Route::post('initiate', [CheckoutController::class, 'initiate'])
    ->name('initiate');

// JS polling endpoint
Route::get('poll/{sessionId}', [CheckoutController::class, 'poll'])
    ->name('poll');

// M-Pesa STK Push callback / confirmation
Route::post('webhook', [CheckoutController::class, 'webhook'])
    ->name('webhook')
    ->withoutMiddleware(['web', \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Cancel a pending session
Route::delete('{sessionId}', [CheckoutController::class, 'cancel'])
    ->name('cancel');

// Standalone redirect-flow checkout page
Route::get('pay', [CheckoutController::class, 'page'])
    ->name('page');
