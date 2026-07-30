@php
    $currentLocale = app()->getLocale();
    $localeOptions = \App\Models\User::supportedPanelLocaleOptions();
@endphp

<form method="POST" action="{{ route('admin.locale.update') }}" class="hidden md:block">
    @csrf
    <label for="admin-locale-switcher" class="sr-only">{{ __('admin.locale_switcher.label') }}</label>
    <select
        id="admin-locale-switcher"
        name="locale"
        onchange="this.form.submit()"
        class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"
    >
        @foreach ($localeOptions as $value => $label)
            <option value="{{ $value }}" @selected($currentLocale === $value)>{{ $label }}</option>
        @endforeach
    </select>
</form>
