<?php

use App\Http\Controllers\LiabilityController;
use App\Http\Controllers\WealthController;
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
    Route::name('wealth.')->prefix('wealth')->controller(WealthController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/data', 'data')->name('data');
        Route::get('/history', 'history')->name('history');
        Route::post('/update', 'update')->name('update');
        Route::post('/screenshot', 'screenshot')->name('screenshot');
    });

    Route::name('wealth.liabilities.')->prefix('wealth/liabilities')->controller(LiabilityController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/preview', 'preview')->name('preview');
        Route::post('/{liability}/what-if', 'whatIf')->name('what-if');
        Route::post('/{liability}/records', 'storeRecord')->name('records.store');
        Route::put('/{liability}/values/{value}', 'updateValue')->name('values.update');
        Route::post('/{liability}/overpayments', 'storeOverpayment')->name('overpayments.store');
        Route::delete('/{liability}/overpayments/{overpayment}', 'destroyOverpayment')->name('overpayments.destroy');
    });
});
