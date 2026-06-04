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

include_once __DIR__.'/../../../core.php';
use Modules\Preventivi\Preventivo;
use Modules\Preventivi\Stato;

$rs = Preventivo::with(['agente', 'descrizioni', 'righe', 'articoli', 'sconti'])
    ->where('id_stato', '=', Stato::where('name', 'In lavorazione')->first()->id)
    ->where('default_revision', '=', 1)
    ->get();

if ($rs->isNotEmpty()) {
    echo "
<table class='table table-hover'>
    <tr>
        <th>".tr('Preventivo')."</th>
        <th>".tr('Cliente')."</th>
        <th>".tr('Agente')."</th>
        <th class='text-right'>".tr('Valore')."</th>
        <th class='text-center'>".tr('Data inizio')."</th>
        <th class='text-center'>".tr('Data conclusione')."</th>
    </tr>";

    foreach ($rs as $preventivo) {
        $data_accettazione = ($preventivo->data_accettazione != '0000-00-00') ? Translator::dateToLocale($preventivo->data_accettazione) : '';
        $data_conclusione = ($preventivo->data_conclusione != '0000-00-00') ? Translator::dateToLocale($preventivo->data_conclusione) : '';

        if ($data_conclusione != '' && strtotime((string) $preventivo->data_conclusione) < strtotime(date('Y-m-d'))) {
            $attr = ' class="danger"';
        } else {
            $attr = '';
        }

        $cliente = $preventivo->anagrafica;
        $agente = $preventivo->agente;

        echo '<tr '.$attr.'>';
        echo '<td>'.Modules::link('Preventivi', $preventivo->id, $preventivo->nome, true, null, false).'</td>';
        echo '<td>'.(!empty($cliente) ? Modules::link('Anagrafiche', $cliente->id, $cliente->ragione_sociale, true, null, false) : '').'</td>';
        echo '<td>'.(!empty($agente) ? Modules::link('Anagrafiche', $agente->id, $agente->ragione_sociale, true, null, false) : '').'</td>';
        echo '<td class="text-right">'.moneyFormat($preventivo->totale, 2).'</td>';
        echo '<td class="text-center">'.$data_accettazione.'</td>';
        echo '<td class="text-center">'.$data_conclusione.'</td>';
        echo '</tr>';
    }

    echo '
</table>';
} else {
    echo '
<p>'.tr('Non ci sono preventivi in lavorazione').'.</p>';
}
