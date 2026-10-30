<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestIuranController;
use App\Http\Controllers\GuestReceiptController;
use App\Http\Controllers\IuranController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SplitBillController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/split-bills', [SplitBillController::class, 'index'])->name('history.index');
    Route::get('/split-bills/create', [SplitBillController::class, 'create'])->name('split-bills.create');
    Route::post('/split-bills', [SplitBillController::class, 'store'])->name('split-bills.store');
    Route::post('/split-bills/scan', [SplitBillController::class, 'scan'])->middleware('throttle:20,1')->name('split-bills.scan');

    Route::get('/split-bills/{splitBill}/validate', [SplitBillController::class, 'showValidate'])->name('split-bills.validate');
    Route::put('/split-bills/{splitBill}/validate', [SplitBillController::class, 'updateValidate'])->name('split-bills.validate.update');
    Route::get('/split-bills/{splitBill}/participants', [SplitBillController::class, 'showParticipants'])->name('split-bills.participants');
    Route::put('/split-bills/{splitBill}/participants', [SplitBillController::class, 'updateParticipants'])->name('split-bills.participants.update');
    Route::get('/split-bills/{splitBill}/allocation', [SplitBillController::class, 'showAllocation'])->name('split-bills.allocation');
    Route::put('/split-bills/{splitBill}/allocation', [SplitBillController::class, 'updateAllocation'])->name('split-bills.allocation.update');
    Route::get('/split-bills/{splitBill}/summary', [SplitBillController::class, 'showSummary'])->name('split-bills.summary');
    Route::put('/split-bills/{splitBill}/rules', [SplitBillController::class, 'updateRules'])->name('split-bills.rules.update');
    Route::post('/split-bills/{splitBill}/confirm', [SplitBillController::class, 'confirm'])->name('split-bills.confirm');
    Route::get('/split-bills/{splitBill}/receipts', [SplitBillController::class, 'showReceipts'])->name('split-bills.receipts');
    Route::patch('/split-bills/{splitBill}/payment-status', [SplitBillController::class, 'updatePaymentStatus'])->name('split-bills.payment.update');
    Route::post('/split-bills/{splitBill}/duplicate', [SplitBillController::class, 'duplicate'])->name('split-bills.duplicate');
    Route::patch('/split-bills/{splitBill}/archive', [SplitBillController::class, 'archive'])->name('split-bills.archive');
    Route::delete('/split-bills/{splitBill}', [SplitBillController::class, 'destroy'])->name('split-bills.destroy');
    Route::get('/split-bills/{splitBill}', [SplitBillController::class, 'show'])->name('split-bills.show');

    Route::get('/iuran', [IuranController::class, 'index'])->name('iuran.index');
    Route::post('/iuran', [IuranController::class, 'store'])->name('iuran.store');
    Route::delete('/iuran/{iuran}', [IuranController::class, 'destroy'])->name('iuran.destroy');
    Route::patch('/iuran/{iuran}/participant-status', [IuranController::class, 'updateParticipantStatus'])->name('iuran.participant.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear', [NotificationController::class, 'clear'])->name('notifications.clear');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/banks', [ProfileController::class, 'storeBank'])->name('profile.banks.store');
    Route::put('/profile/banks/{bank}', [ProfileController::class, 'updateBank'])->name('profile.banks.update');
    Route::patch('/profile/banks/{bank}/default', [ProfileController::class, 'setDefaultBank'])->name('profile.banks.default');
    Route::delete('/profile/banks/{bank}', [ProfileController::class, 'destroyBank'])->name('profile.banks.destroy');
});

Route::get('/iuran/{code}/{token}', [GuestIuranController::class, 'show'])->name('guest.iuran');
Route::post('/iuran/{code}/{token}', [GuestIuranController::class, 'report'])->middleware('throttle:20,1')->name('guest.iuran.report');

Route::get('/receipt/{code}/{token}', [GuestReceiptController::class, 'show'])->name('guest.receipt');
Route::post('/receipt/{code}/{token}/report', [GuestReceiptController::class, 'report'])->middleware('throttle:20,1')->name('guest.receipt.report');
