<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CopilotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NewsAlertController;
use App\Http\Controllers\RiskBrokerController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TimeMachineController;
use App\Http\Controllers\TradePlanController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/live-prices', [ScannerController::class, 'livePrices'])->name('live-prices');

    Route::prefix('scanner')->name('scanner.')->group(function () {
        Route::get('/', [ScannerController::class, 'index'])->name('index');
        Route::get('/run', fn () => redirect()->route('scanner.index'));
        Route::post('/run', [ScannerController::class, 'run'])->name('run');
        Route::post('/analyze', [ScannerController::class, 'analyze'])->name('analyze');
    });

    Route::prefix('plans')->name('plans.')->group(function () {
        Route::get('/', [TradePlanController::class, 'index'])->name('index');
        Route::post('/', [TradePlanController::class, 'store'])->name('store');
        Route::post('/{id}/analyze', [TradePlanController::class, 'analyze'])->name('analyze');
    });

    Route::prefix('timemachine')->name('timemachine.')->group(function () {
        Route::get('/', [TimeMachineController::class, 'index'])->name('index');
        Route::post('/run', [TimeMachineController::class, 'run'])->name('run');
        Route::post('/{id}/analyze', [TimeMachineController::class, 'analyze'])->name('analyze');
    });

    Route::prefix('broker')->name('broker.')->group(function () {
        Route::get('/', [RiskBrokerController::class, 'index'])->name('index');
        Route::post('/size', [RiskBrokerController::class, 'sizeAndCheck'])->name('size');
        Route::post('/{id}/execute', [RiskBrokerController::class, 'execute'])->name('execute');
        Route::post('/{id}/close', [RiskBrokerController::class, 'close'])->name('close');
    });

    Route::prefix('news')->name('news.')->group(function () {
        Route::get('/', [NewsAlertController::class, 'news'])->name('index');
        Route::post('/fetch', [NewsAlertController::class, 'fetchNews'])->name('fetch');
    });

    Route::prefix('alerts')->name('alerts.')->group(function () {
        Route::get('/', [NewsAlertController::class, 'alerts'])->name('index');
        Route::post('/', [NewsAlertController::class, 'storeAlert'])->name('store');
        Route::post('/check', [NewsAlertController::class, 'checkAlerts'])->name('check');
        Route::post('/{id}/dismiss', [NewsAlertController::class, 'dismissAlert'])->name('dismiss');
    });

    Route::prefix('copilot')->name('copilot.')->group(function () {
        Route::get('/', [CopilotController::class, 'index'])->name('index');
        Route::get('/history', [CopilotController::class, 'history'])->name('history');
        Route::post('/send', [CopilotController::class, 'send'])->name('send');
        Route::post('/clear', [CopilotController::class, 'clear'])->name('clear');
    });

    Route::middleware(\App\Http\Middleware\EnsureAdmin::class)->group(function () {
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::post('/', [SettingsController::class, 'update'])->name('update');
            Route::post('/test-gemini', [SettingsController::class, 'testGemini'])->name('test-gemini');
            Route::post('/test-telegram', [SettingsController::class, 'testTelegram'])->name('test-telegram');
            Route::post('/run-monitor', [SettingsController::class, 'runMonitor'])->name('run-monitor');
        });

        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::post('/profile', [AccountController::class, 'updateProfile'])->name('profile');
            Route::post('/{id}/toggle', [AccountController::class, 'toggle'])->name('toggle');
            Route::delete('/{id}', [AccountController::class, 'destroy'])->name('destroy');
        });
    });
});

// Token-protected webhook for external cron (e.g. cron-job.org) when container sleeps on free tier
Route::get('/cron/monitor', function (\Illuminate\Http\Request $request, \App\Services\ForexAutoMonitorService $monitor) {
    $expected = (string) config('services.cron.token', '');
    $provided = (string) ($request->query('token') ?? $request->header('X-Cron-Token', ''));

    if ($expected === '' || !hash_equals($expected, $provided)) {
        abort(403, 'Invalid or unconfigured CRON_SECRET_TOKEN.');
    }

    return response()->json(['ok' => true, 'summary' => $monitor->run()]);
})->name('cron.monitor');
