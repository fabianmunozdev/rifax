<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminLocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', User::SUPPORTED_PANEL_LOCALES)],
        ]);

        $locale = $validated['locale'];
        $user = $request->user();

        if ($user instanceof User) {
            $user->forceFill([
                'preferred_locale' => $locale,
            ])->save();
        }

        $request->session()->put('admin_locale', $locale);
        app()->setLocale($locale);

        return back();
    }
}
