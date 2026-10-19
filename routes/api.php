<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/menu', [MenuController::class, 'index']);
    Route::patch('/menu-items/{menuItem}/sold-out', [MenuController::class, 'setSoldOut']);

    Route::get('/floor', [TableController::class, 'floor']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/lines', [OrderController::class, 'addLine']);
    Route::post('/orders/{order}/fire', [OrderController::class, 'fire']);
    Route::post('/order-lines/{line}/void', [OrderController::class, 'voidLine']);

    Route::get('/stations/{station}/tickets', [TicketController::class, 'index']);
    Route::post('/tickets/{ticket}/bump', [TicketController::class, 'bump']);
    Route::post('/tickets/{ticket}/recall', [TicketController::class, 'recall']);
});
