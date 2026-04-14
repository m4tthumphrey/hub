<?php

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('withings/callback', function(Request $request) {
    $state = 'abc123';

    if (!$request->has('code')) {
        return redirect(sprintf(
            config('services.withings.authorization_url'),
            config('services.withings.client_id'),
            config('services.withings.redirect_url'),
            $state
        ));
    }

    if ($request->input('state') === $state) {
        return response()->json($request->query());
    }

    return response()->noContent();
});

Route::get('/', fn() => response(null, 444));

Route::group(['middleware' => 'auth.basic'], function () {
    Route::name('wealth.')->prefix('wealth')->group(function () {
        Route::get('/', [Controller::class, 'index'])->name('index');
        Route::post('/update', [Controller::class, 'update'])->name('update');
        Route::post('/screenshot', [Controller::class, 'screenshot'])->name('screenshot');
    });
});
