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

    $assert(ProviderFactory::CONNECTOR_VERSION === '1.0.4-test', 'unexpected connector version');
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

    $assert(
        !defined(ProviderSettings::class.'::SETTING_HS_POLL_MINUTES'),
        'provider must not expose a second generic polling scheduler setting'
    );

    $decoded = Base64Document::decode(base64_encode('<xml />'));
    $assert($decoded === '<xml />', 'base64 document decode failed');

    try {
        Base64Document::decode('not-valid-base64!');
        $assert(false, 'invalid base64 must throw');
    } catch (\InvalidArgumentException) {
        $assert(true, 'invalid base64 throws');
    }

    // Regression statiche di architettura/sicurezza. Sono intenzionalmente
    // semplici: falliscono se vengono reintrodotti comportamenti rimossi.
    $transactionSource = file_get_contents(__DIR__.'/../../plugins/exportFE/src/Providers/ProviderTransactionRepository.php');
    $hostingSource = file_get_contents(__DIR__.'/../../plugins/exportFE/src/Providers/HostingSolutionsProvider.php');
    $receiptSource = file_get_contents(__DIR__.'/../../plugins/receiptFE/custom/src/Interaction.php');
    $importSource = file_get_contents(__DIR__.'/../../plugins/importFE/custom/src/Interaction.php');

    $assert(!str_contains($transactionSource, 'next_poll_at'), 'generic provider polling field must not return');
    $assert(str_contains($transactionSource, 'recoverStaleSending'), 'stale SENDING recovery is required');
    $assert(str_contains($transactionSource, 'STATUS_FINAL'), 'final duplicate guard is required');
    $assert(str_contains($hostingSource, 'findOpenByReceiptFilename'), 'mock receipts must be bound to tracked transactions');
    $assert(str_contains($hostingSource, 'uncertainResult'), 'ambiguous transport failures must map to uncertain');
    $assert(str_contains($receiptSource, 'sanitizeRemoteName'), 'receipt remote filenames must be sanitized');
    $assert(str_contains($importSource, 'sanitizeRemoteName'), 'passive remote filenames must be sanitized');

    echo 'provider smoke ok'.PHP_EOL;
}
