<?php

namespace App\Http\Middleware;

use App\Models\CompanySetting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);
        $request->setLocale($locale);

        if ($request->hasSession() && $request->session()->get('admin_locale') !== $locale) {
            $request->session()->put('admin_locale', $locale);
        }

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $userLocale = $request->user() instanceof User
            ? $request->user()->preferredPanelLocale()
            : null;

        $sessionLocale = $request->hasSession()
            ? $request->session()->get('admin_locale')
            : null;
        $companyLocale = CompanySetting::query()->value('default_locale');

        foreach ([$userLocale, $sessionLocale, $companyLocale, config('app.locale'), config('app.fallback_locale')] as $candidate) {
            if (is_string($candidate) && in_array($candidate, User::SUPPORTED_PANEL_LOCALES, true)) {
                return $candidate;
            }
        }

        return 'en';
    }
}
