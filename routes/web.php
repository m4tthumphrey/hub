<?php

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => response(null, 444));

Route::name('wealth.')->prefix('wealth')->group(function() {
    Route::get('/', [Controller::class, 'index'])->name('index');
    Route::post('/update', [Controller::class, 'update'])->name('update');
    Route::post('/screenshot', [Controller::class, 'screenshot'])->name('screenshot');
});
