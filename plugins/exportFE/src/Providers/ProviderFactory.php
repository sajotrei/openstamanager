<?php

namespace Plugins\ExportFE\Providers;

class ProviderFactory
{
    public const OSMCLOUD = 'osmcloud';
    public const HOSTING_SOLUTIONS = 'hosting_solutions';

    public static function make(string $provider = self::OSMCLOUD): ProviderInterface
    {
        if ($provider === self::HOSTING_SOLUTIONS) {
            return new HostingSolutionsProvider();
        }

        return new OSMCloudProvider();
    }
}
