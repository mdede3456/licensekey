<?php

use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Transaction\SalesReturnController;
use Illuminate\Support\Facades\Route;
use Mdhpos\Licensekey\Controllers\AnalyticController;
use Mdhpos\Licensekey\Controllers\ExpenseController;
use Mdhpos\Licensekey\Controllers\LicenseController;
use Mdhpos\Licensekey\Controllers\MobileController;
use Mdhpos\Licensekey\Controllers\SettingsController;
use Mdhpos\Licensekey\Controllers\TransactionController;

Route::group(['prefix' => 'license-key', 'namespace' => 'Mdhpos\Licensekey\Controllers', 'middleware' => ['web', 'is_license']], function () {
    Route::get('/', [LicenseController::class, 'welcome'])->name('license');
    Route::get('/insert-key', [LicenseController::class, 'validation'])->name('license.validation');
    Route::post('/store-license', [LicenseController::class, 'checkValidation'])->name('license.store');
});

Route::prefix('mdhpos-license')->middleware(['web', 'auth'])->group(function () {
    Route::get('update', [LicenseController::class, 'updateLicense'])->name('license.update');
    Route::post('store-update', [LicenseController::class, 'update']);
});

Route::prefix('mobile')->middleware(['web', 'auth', 'device_mobile', 'store'])->group(function () {
    Route::get("/home", [MobileController::class, 'index'])->name('m.index');
    Route::get('sell-week', [MobileController::class, 'analyticSale']);

    Route::prefix('transaction')->group(function () {

        // Sell & Return Sell
        Route::get("/", [MobileController::class, 'transaction'])->name('m.transaction');
        Route::get('sale-transaction', [TransactionController::class, 'sale'])->name('m.sale');
        Route::get('detail-sale/{id}', [TransactionController::class, 'saleInvoice'])->name('m.sale_invoice');
        Route::get("sale-detail", [TransactionController::class, 'saleDetail'])->name('m.sale_detail');
        Route::get("return-dom/{id}", [SalesReturnController::class, 'domItem']);
        Route::post("store-return", [TransactionController::class, 'createReturn']);
        Route::get("sales-return", [TransactionController::class, 'saleReturn'])->name('m.return_sales');

        // Purchase & Return PO
        Route::get("purchase", [TransactionController::class, 'purchase'])->name('m.purchase');
        Route::get("purchase-invoice/{id}", [TransactionController::class, 'purchaseInvoice'])->name('m.purchase_invoice');
        Route::get('purchase-detail', [TransactionController::class, 'purchaseDetail'])->name('m.purchase_detail');
        Route::get('return-po-dom/{id}', [TransactionController::class, 'domPoItem']);
        Route::post('store-po-return', [TransactionController::class, 'poreturnStore']);
        Route::get('return-po', [TransactionController::class, 'returnPoDetail'])->name('m.return_po');

        // Due Customer
        Route::get("due", [TransactionController::class, 'dueCustomer'])->name('m.due');
        Route::get("due-detail/{id}", [TransactionController::class, 'dueDetail'])->name('m.due_detail');
        Route::post("add-payment", [TransactionController::class, 'addPay'])->name('m.add_pay');
        Route::post("update-status", [TransactionController::class, 'updatePay'])->name('m.update_status');

        // Stock Transfer
        Route::get("transfer", [TransactionController::class, 'transfer'])->name('m.transfer');
        Route::get('transfer-detail/{id}', [TransactionController::class, 'transferDetail'])->name('m.transfer_detail');

        // Adjustment
        Route::get("adjustment", [TransactionController::class, 'adjustment'])->name('m.adjustment');
        Route::get('adjustment-detail/{id}', [TransactionController::class, 'adjustmentDetail'])->name('m.adjustment_detail');
    });

    Route::prefix('analityc')->group(function () {
        Route::get("/", [MobileController::class, 'analytic'])->name('m.analytic');
        Route::get('all-stock', [AnalyticController::class, 'stock'])->name('m.stock');
        Route::get('profit', [AnalyticController::class, 'profitExpense'])->name('m.profit');
        Route::get("transaction", [AnalyticController::class, 'transaction'])->name('m.chart_transaksi');
        Route::get('trend-produk', [AnalyticController::class, 'trendProduct'])->name('m.trend_produk');
        Route::get("today", [AnalyticController::class, 'forToday'])->name('m.today');
        Route::get('close-shift',[AnalyticController::class,'closeRegister'])->name('m.today_close');
    });

    Route::prefix('expense')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('m.expense');
        Route::get('create', [ExpenseController::class, 'create'])->name('m.expense.create');
        Route::get('edit/{id}', [ExpenseController::class, 'update'])->name('m.expense.update');
        Route::get('delete/{id}', [ExpenseController::class, 'delete'])->name('m.expense.delete');
        Route::post('store/{any}', [ExpenseController::class, 'store'])->name('m.expense.store');
    });

    Route::prefix('setting')->group(function () {
        Route::get("/", [MobileController::class, 'settings'])->name('m.setting');
        Route::get("turn-off-mobile",[SettingsController::class,'settMobile']);
        Route::get("option-shift/{id}",[SettingsController::class,'settShiftRegister']);
        Route::get('setting-store',[SettingsController::class,'setting'])->name('m.store');
        Route::post("store",[SettingsController::class,'storeSett']);

        Route::get("profile",[SettingsController::class,'updateProfile'])->name('m.profile');
        Route::post("profile-store",[SettingsController::class,'updateProfile']);
        Route::get("password",[SettingsController::class,'updatePass'])->name('m.password');
        Route::post("password-store",[SettingsController::class,'updatePass']);
    });
});
