<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use UnitEnum;

abstract class BaseResource extends Resource
{
    protected static ?string $translationKey = null;

    protected static ?string $navigationGroupTranslationKey = null;

    public static function getNavigationLabel(): string
    {
        return static::translateResource('navigation', parent::getNavigationLabel());
    }

    public static function getModelLabel(): string
    {
        return static::translateResource('singular', parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return static::translateResource('plural', parent::getPluralModelLabel());
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        if (blank(static::$navigationGroupTranslationKey)) {
            return parent::getNavigationGroup();
        }

        return __(static::$navigationGroupTranslationKey);
    }

    protected static function translateResource(string $key, string $fallback): string
    {
        if (blank(static::$translationKey)) {
            return $fallback;
        }

        $translationKey = 'admin.resources.' . static::$translationKey . '.' . $key;
        $translated = __($translationKey);

        return $translated === $translationKey ? $fallback : $translated;
    }
}
