<?php

use Illuminate\Support\Facades\Route;
use Sellissimo\Core\Http\PrivateResponse;
use Sellissimo\FlatRate\ZoneController;

Route::prefix('sellissimo/shipping-zones')->middleware(['can:manage sellissimo shipping', PrivateResponse::class])->name('sellissimo-shipping.')->group(function () {
    Route::get('/', [ZoneController::class, 'index'])->name('zones');
    Route::post('/', [ZoneController::class, 'save'])->name('save');
    Route::post('/preview', [ZoneController::class, 'preview'])->name('preview');
});
