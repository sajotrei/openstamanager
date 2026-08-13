<?php

namespace Plugins\ExportFE\Providers;

class ProviderFactory
{
    public const OSMCLOUD = 'osmcloud';
    public const HOSTING_SOLUTIONS = 'hosting_solutions';

    public static function make(?string $provider = null): ProviderInterface
    {
        $provider = $provider ?: ProviderSettings::selectedProvider();

        if ($provider === self::HOSTING_SOLUTIONS) {
            return new HostingSolutionsProvider();
        }

        return new OSMCloudProvider();
    }

    public static function available(): array
    {
        return [
            self::OSMCLOUD,
            self::HOSTING_SOLUTIONS,
        ];
    }
}
