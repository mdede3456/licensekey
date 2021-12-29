<?php

use Illuminate\Support\Facades\Route;
use Mdhpos\Licensekey\Controllers\LicenseController;

Route::group(['prefix' => 'license-key', 'as' => 'Licensekey::', 'namespace' => 'Mdhpos\Licensekey\Controllers', 'middleware' => ['web', 'is_license']], function () {
    Route::get('/', [LicenseController::class,'welcome'])->name('license'); 
});

 
