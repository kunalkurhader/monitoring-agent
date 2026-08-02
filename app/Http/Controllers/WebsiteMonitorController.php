<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Models\WebsiteMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

    public function destroy(WebsiteMonitor $websiteMonitor): RedirectResponse
    {
        $websiteMonitor->delete();

        return redirect()->route('website-monitors.index')->with('status', 'Website monitor deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate($this->rules());
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'alert_email' => ['required', 'email:rfc', 'max:255'],
            'is_active' => ['nullable', Rule::in(['0', '1'])],
        ];
    }
}
