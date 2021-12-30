<?php

use Illuminate\Support\Facades\Route;
use Mdhpos\Licensekey\Controllers\LicenseController;

Route::group(['prefix' => 'license-key', 'namespace' => 'Mdhpos\Licensekey\Controllers', 'middleware' => ['web', 'is_license']], function () {
    Route::get('/', [LicenseController::class,'welcome'])->name('license'); 
    Route::get('/insert-key',[LicenseController::class,'validation'])->name('license.validation');
    Route::post('/store-license',[LicenseController::class,'checkValidation'])->name('license.store');
});

Route::prefix('mdhpos-license')->middleware(['web','auth'])->group(function() {
    Route::get('update',[LicenseController::class,'updateLicense'])->name('license.update');
    Route::post('store-update',[LicenseController::class,'update']);
});

 
