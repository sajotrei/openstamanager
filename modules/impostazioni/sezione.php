<?php

/*
 * OpenSTAManager: il software gestionale open source per l'assistenza tecnica e la fatturazione
 * Copyright (C) DevCode s.r.l.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Models\Setting;

include_once __DIR__.'/../../core.php';

$sezione = filter('sezione');
$impostazioni = Setting::where('sezione', $sezione)
    ->get();

if ($sezione === 'Fatturazione Elettronica') {
    $provider_status = base_dir().'/plugins/exportFE/provider_status.php';
    if (file_exists($provider_status)) {
        include $provider_status;
    }
}

$provider_setting_id = null;
$hs_mock_setting_id = null;

foreach ($impostazioni as $impostazione) {
    $setting_name = (string) $impostazione->nome;
    $input_html = null;

    if ($sezione === 'Fatturazione Elettronica' && $setting_name === 'Fatturazione Elettronica Provider') {
        $provider_setting_id = $impostazione->id;
        $input_html = input([
            'type' => 'select',
            'label' => $impostazione->getTranslation('title'),
            'name' => 'setting['.$impostazione->id.']',
            'values' => [
                ['id' => 'osmcloud', 'text' => 'OSMCloud'],
                ['id' => 'hosting_solutions', 'text' => 'Hosting Solutions'],
            ],
            'value' => $impostazione->valore,
            'help' => $impostazione->getTranslation('help'),
        ]);
    } elseif ($sezione === 'Fatturazione Elettronica' && $setting_name === 'Hosting Solutions FE Mock Scenario') {
        $input_html = input([
            'type' => 'select',
            'label' => $impostazione->getTranslation('title'),
            'name' => 'setting['.$impostazione->id.']',
            'values' => [
                ['id' => 'send_ok', 'text' => tr('Invio acquisito')],
                ['id' => 'wait', 'text' => tr('In attesa')],
                ['id' => 'delivered', 'text' => tr('Consegnata')],
                ['id' => 'not_delivered', 'text' => tr('Mancata consegna')],
                ['id' => 'rejected', 'text' => tr('Scartata')],
                ['id' => 'timeout', 'text' => tr('Timeout / esito incerto')],
                ['id' => 'http_4xx', 'text' => tr('Errore richiesta')],
                ['id' => 'http_5xx', 'text' => tr('Errore servizio')],
                ['id' => 'malformed', 'text' => tr('Risposta non valida')],
                ['id' => 'passive_invoice', 'text' => tr('Fattura passiva disponibile')],
                ['id' => 'duplicate', 'text' => tr('Documento duplicato')],
            ],
            'value' => $impostazione->valore,
            'help' => $impostazione->getTranslation('help'),
        ]);
    }

    if ($setting_name === 'Hosting Solutions FE Modalita mock') {
        $hs_mock_setting_id = $impostazione->id;
    }

    echo '\n    <div class="col-md-4 fe-setting" data-setting-name="'.prepareToField($setting_name).'">\n        '.($input_html ?? Settings::input($impostazione->id)).'\n    </div>\n\n    <script>';

    if ($impostazione->tipo == 'time' || $impostazione->tipo == 'date') {
        echo '\n    input("setting['.$impostazione->id.']");\n    $(document).on("blur", "#setting'.$impostazione->id.'", function (e) {\n      salvaImpostazione('.$impostazione->id.', $("#setting'.$impostazione->id.'").val());\n    });\n    ';
    } else {
        echo '\n\n    input("setting['.$impostazione->id.']").change(function (){\n        salvaImpostazione('.$impostazione->id.', input(this).get());\n    });';
    }

    echo '\n    </script>';
}

?>

<script>
    init();

<?php if ($sezione === 'Fatturazione Elettronica' && !empty($provider_setting_id)) { ?>
    function aggiornaVisibilitaProviderFE() {
        var provider = input("setting[<?php echo $provider_setting_id; ?>]").get() || 'osmcloud';
        var hosting = provider === 'hosting_solutions';

        var hsNames = [
            'Hosting Solutions FE Abilitato',
            'Hosting Solutions FE Modalita mock',
            'Hosting Solutions FE Mock Scenario'
        ];
        var osmCloudNames = [
            'OSMCloud Services API Token',
            'OSMCloud Services API URL',
            'OSMCloud Services API Version'
        ];

        hsNames.forEach(function(name) {
            $('.fe-setting[data-setting-name="' + name + '"]').toggle(hosting);
        });
        osmCloudNames.forEach(function(name) {
            $('.fe-setting[data-setting-name="' + name + '"]').toggle(!hosting);
        });

<?php if (!empty($hs_mock_setting_id)) { ?>
        if (hosting) {
            var mock = input("setting[<?php echo $hs_mock_setting_id; ?>]").get();
            var mockEnabled = mock === true || mock === 1 || mock === '1' || mock === 'true';
            $('.fe-setting[data-setting-name="Hosting Solutions FE Mock Scenario"]').toggle(mockEnabled);
        }
<?php } ?>
    }

    $(document).ready(function () {
        aggiornaVisibilitaProviderFE();

        input("setting[<?php echo $provider_setting_id; ?>]").change(function () {
            setTimeout(aggiornaVisibilitaProviderFE, 50);
        });
<?php if (!empty($hs_mock_setting_id)) { ?>
        input("setting[<?php echo $hs_mock_setting_id; ?>]").change(function () {
            setTimeout(aggiornaVisibilitaProviderFE, 50);
        });
<?php } ?>
    });
<?php } ?>
</script>