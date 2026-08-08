<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Models\WebsiteMonitor;
use App\Services\WebsiteMonitorChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class WebsiteMonitorController extends Controller
{
    public function index(): View
    {
        return view('website-monitors.index', [
            'monitors' => WebsiteMonitor::query()->latest()->get(),
            'mailReady' => (bool) MailSetting::query()->where('is_enabled', true)->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return redirect()->to(route('settings.index').'#uptime-monitoring')
                ->withErrors($validator, 'uptimeMonitor')
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['is_active'] = $request->boolean('is_active');
        $validated['check_ssl'] = $request->boolean('check_ssl');
        WebsiteMonitor::query()->create($validated);

        return redirect()->to(route('settings.index').'#uptime-monitoring')->with('status', 'Website monitor created. It will be checked within one minute.');
    }

    public function edit(WebsiteMonitor $websiteMonitor): View
    {
        return view('website-monitors.edit', compact('websiteMonitor'));
    }

    public function update(Request $request, WebsiteMonitor $websiteMonitor): RedirectResponse
    {
        $websiteMonitor->update($this->validated($request));

        return redirect()->route('website-monitors.index')->with('status', 'Website monitor updated.');
    }

    public function check(WebsiteMonitor $websiteMonitor, WebsiteMonitorChecker $checker): RedirectResponse
    {
        try {
            $checker->check($websiteMonitor);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('website-monitors.index')
                ->with('error', "The manual check for {$websiteMonitor->name} could not be completed.");
        }

        $websiteMonitor->refresh();
        $result = $websiteMonitor->is_up
            ? 'operational'
            : ($websiteMonitor->last_status_code ? "returning HTTP {$websiteMonitor->last_status_code}" : 'unreachable');

        return redirect()->route('website-monitors.index')
            ->with('status', "Manual check complete: {$websiteMonitor->name} is {$result}.");
    }

    public function destroy(WebsiteMonitor $websiteMonitor): RedirectResponse
    {
        $websiteMonitor->delete();

        return redirect()->route('website-monitors.index')->with('status', 'Website monitor deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate($this->rules());
        $validated['is_active'] = $request->boolean('is_active');
        $validated['check_ssl'] = $request->boolean('check_ssl');

        return $validated;
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'alert_email' => ['required', 'email:rfc', 'max:255'],
            'is_active' => ['nullable', Rule::in(['0', '1'])],
            'check_ssl' => ['nullable', Rule::in(['0', '1'])],
        ];
    }
}
