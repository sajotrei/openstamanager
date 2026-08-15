<?php

namespace API {
    class Services
    {
    }
}

namespace {
    function tr($string, $params = [])
    {
        return strtr($string, $params);
    }

    $settings = [];

    function setting($name)
    {
        global $settings;

        return $settings[$name] ?? null;
    }

    require __DIR__.'/../../plugins/exportFE/src/Providers/ProviderInterface.php';
    require __DIR__.'/../../plugins/exportFE/src/Providers/ProviderSettings.php';
    require __DIR__.'/../../plugins/exportFE/src/Providers/ProviderFactory.php';
    require __DIR__.'/../../plugins/exportFE/src/Providers/Base64Document.php';
    require __DIR__.'/../../plugins/exportFE/src/Providers/ProviderTransactionRepository.php';
    require __DIR__.'/../../plugins/exportFE/src/Providers/InvoicePayload.php';
    require __DIR__.'/../../plugins/exportFE/src/Providers/OSMCloudProvider.php';
    require __DIR__.'/../../plugins/exportFE/src/Providers/HostingSolutionsProvider.php';

    use Plugins\ExportFE\Providers\Base64Document;
    use Plugins\ExportFE\Providers\HostingSolutionsProvider;
    use Plugins\ExportFE\Providers\OSMCloudProvider;
    use Plugins\ExportFE\Providers\ProviderFactory;
    use Plugins\ExportFE\Providers\ProviderSettings;

    $assert = function ($condition, $message) {
        if (!$condition) {
            fwrite(STDERR, $message.PHP_EOL);
            exit(1);
        }
    };

    $assert(ProviderSettings::selectedProvider() === ProviderFactory::OSMCLOUD, 'default provider must be OSMCloud');
    $assert(ProviderFactory::make() instanceof OSMCloudProvider, 'factory must default to OSMCloud');

    $settings[ProviderSettings::SETTING_PROVIDER] = 'invalid';
    $assert(ProviderSettings::selectedProvider() === ProviderFactory::OSMCLOUD, 'invalid provider must fall back to OSMCloud');

    $settings[ProviderSettings::SETTING_PROVIDER] = ProviderFactory::HOSTING_SOLUTIONS;
    $settings[ProviderSettings::SETTING_HS_ENABLED] = '1';
    $settings[ProviderSettings::SETTING_HS_MOCK] = '1';
    $settings[ProviderSettings::SETTING_HS_MOCK_SCENARIO] = HostingSolutionsProvider::SCENARIO_WAIT;

    $provider = ProviderFactory::make();
    $assert($provider instanceof HostingSolutionsProvider, 'factory must select Hosting Solutions');
    $assert($provider->isEnabled() === true, 'Hosting Solutions mock provider must be enabled with explicit settings');

    $settings[ProviderSettings::SETTING_HS_MOCK_SCENARIO] = 'invalid-scenario';
    $assert(
        ProviderSettings::hostingSolutionsMockScenario() === HostingSolutionsProvider::SCENARIO_WAIT,
        'invalid mock scenario must fall back to wait'
    );

    $settings[ProviderSettings::SETTING_HS_POLL_MINUTES] = '1';
    $assert(ProviderSettings::pollingMinutes() === 15, 'polling must enforce minimum 15 minutes');

    $settings[ProviderSettings::SETTING_HS_POLL_MINUTES] = '99999';
    $assert(ProviderSettings::pollingMinutes() === 1440, 'polling must enforce maximum 1440 minutes');

    $settings[ProviderSettings::SETTING_HS_POLL_MINUTES] = '30';
    $assert(ProviderSettings::pollingMinutes() === 30, 'polling must preserve valid configured interval');

    $decoded = Base64Document::decode(base64_encode('<xml />'));
    $assert($decoded === '<xml />', 'base64 document decode failed');

    try {
        Base64Document::decode('not-valid-base64!');
        $assert(false, 'invalid base64 must throw');
    } catch (\InvalidArgumentException) {
        $assert(true, 'invalid base64 throws');
    }

    echo 'provider smoke ok'.PHP_EOL;
}
