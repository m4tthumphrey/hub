<?php

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

Route::get('/', [Controller::class, 'index']);
Route::post('/update', [Controller::class, 'update']);
Route::post('/screenshot', [Controller::class, 'screenshot']);
