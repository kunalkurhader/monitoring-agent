<?php

use App\Http\Controllers\AgentDownloadController;
use App\Http\Controllers\AgentInstallController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandingLogoController;
use App\Http\Controllers\BrowserMonitoringController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FleetDashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WebsiteMonitorController;
use App\Http\Middleware\EnsureApplicationIsNotInstalled;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return SetupController::installed()
        ? redirect()->route('dashboard')
        : redirect()->route('setup.database');
});

Route::get('/downloads/agent.jar', AgentDownloadController::class)->name('agent.download');
Route::get('/branding/logo', BrandingLogoController::class)->name('branding.logo');

Route::middleware(EnsureApplicationIsNotInstalled::class)->group(function (): void {
    Route::get('/setup', [SetupController::class, 'database'])->name('setup.database');
    Route::post('/setup/database', [SetupController::class, 'storeDatabase'])->name('setup.database.store');
    Route::get('/setup/connection', [SetupController::class, 'connection'])->name('setup.connection');
    Route::post('/setup/connection', [SetupController::class, 'storeConnection'])->name('setup.connection.store');
    Route::get('/setup/admin', [SetupController::class, 'admin'])->name('setup.admin');
    Route::post('/setup/admin', [SetupController::class, 'finish'])->name('setup.finish');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [FleetDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [FleetDashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/monitors', [DashboardController::class, 'index'])->name('monitors.index');
    Route::get('/monitors/data', [DashboardController::class, 'data'])->name('monitors.data');
    Route::get('/monitors/processes', [DashboardController::class, 'processes'])->name('monitors.processes');
    Route::get('/monitors/storage', [DashboardController::class, 'storage'])->name('monitors.storage');
    Route::get('/monitors/logs', [DashboardController::class, 'logs'])->name('monitors.logs');
    Route::get('/browser-monitoring', [BrowserMonitoringController::class, 'index'])->name('browser-monitoring.index');
    Route::get('/website-monitors', [WebsiteMonitorController::class, 'index'])->name('website-monitors.index');
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::middleware('admin')->group(function (): void {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings/mail', [SettingsController::class, 'mail'])->name('settings.mail.update');
        Route::patch('/settings/branding', [SettingsController::class, 'branding'])->name('settings.branding.update');
        Route::delete('/settings/factory-reset', [SettingsController::class, 'factoryReset'])
            ->middleware('throttle:3,1')
            ->name('settings.factory-reset');
        Route::get('/agents/install', fn () => redirect()->to(route('settings.index').'#server-agent'))->name('agents.install');
        Route::post('/agents/tokens', [AgentInstallController::class, 'token'])->name('agents.tokens.store');
        Route::post('/browser-monitoring', [BrowserMonitoringController::class, 'store'])->name('browser-monitoring.store');
        Route::post('/website-monitors', [WebsiteMonitorController::class, 'store'])->name('website-monitors.store');
        Route::get('/website-monitors/{website_monitor}/edit', [WebsiteMonitorController::class, 'edit'])->name('website-monitors.edit');
        Route::match(['put', 'patch'], '/website-monitors/{website_monitor}', [WebsiteMonitorController::class, 'update'])->name('website-monitors.update');
        Route::delete('/website-monitors/{website_monitor}', [WebsiteMonitorController::class, 'destroy'])->name('website-monitors.destroy');
        Route::post('/team/invitations', [TeamController::class, 'invite'])->name('team.invitations.store');
        Route::patch('/team/users/{user}/role', [TeamController::class, 'updateRole'])->name('team.users.role');
    });
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
