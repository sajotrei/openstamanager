<?php

namespace Plugins\ExportFE\Providers;

class ProviderSettings
{
    public const SETTING_PROVIDER = 'Fatturazione Elettronica Provider';
    public const SETTING_HS_ENABLED = 'Hosting Solutions FE Abilitato';
    public const SETTING_HS_MOCK = 'Hosting Solutions FE Modalita mock';
    public const SETTING_HS_MOCK_SCENARIO = 'Hosting Solutions FE Mock Scenario';
    public const SETTING_HS_POLL_MINUTES = 'Hosting Solutions FE Minuti polling';

    public static function selectedProvider(): string
    {
        $provider = trim((string) self::get(self::SETTING_PROVIDER, ProviderFactory::OSMCLOUD));

        return in_array($provider, ProviderFactory::available(), true) ? $provider : ProviderFactory::OSMCLOUD;
    }

    public static function isHostingSolutionsEnabled(): bool
    {
        return self::enabled(self::SETTING_HS_ENABLED);
    }

    public static function isHostingSolutionsMockEnabled(): bool
    {
        return self::enabled(self::SETTING_HS_MOCK);
    }

    public static function hostingSolutionsMockScenario(): string
    {
        $scenario = trim((string) self::get(self::SETTING_HS_MOCK_SCENARIO, HostingSolutionsProvider::SCENARIO_WAIT));

        return $scenario !== '' ? $scenario : HostingSolutionsProvider::SCENARIO_WAIT;
    }

    public static function pollingMinutes(): int
    {
        $minutes = (int) self::get(self::SETTING_HS_POLL_MINUTES, 30);

        return max(15, $minutes);
    }

    private static function enabled(string $name): bool
    {
        $value = self::get($name, '0');

        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private static function get(string $name, $default = null)
    {
        if (!function_exists('setting')) {
            return $default;
        }

        $value = setting($name);

        return $value === null || $value === '' ? $default : $value;
    }
}
