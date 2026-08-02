<?php

namespace App\Http\Controllers;

use App\Models\BrowserProject;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'browserProjects' => BrowserProject::query()->orderBy('name')->get(),
            'mailSetting' => MailSetting::query()->first(),
        ]);
    }

    public function mail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
        $setting = MailSetting::query()->first();
        if (($validated['password'] ?? '') === '') {
            unset($validated['password']);
        }
        $validated['is_enabled'] = $request->boolean('is_enabled');
        $setting ? $setting->update($validated) : MailSetting::query()->create($validated);

        return redirect()->to(route('settings.index').'#email-delivery')->with('status', 'Email delivery settings saved.');
    }
}
