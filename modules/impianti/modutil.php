<?php
/*
 * OpenSTAManager: il software gestionale open source per l'assistenza tecnica e la fatturazione
 * Copyright (C) DevCode s.n.c.
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

include_once __DIR__.'/../../core.php';

function crea_form_componente($contenuto)
{
    $fields = \Util\Ini::getFields($contenuto);
    $title = array_shift($fields);

    foreach ($fields as $key => $value) {
        $fields[$key] = '<div class="col-md-4">'.$value.'</div>';
    }

    echo $title;
    echo '<div class="row">';
    echo PHP_EOL.implode(PHP_EOL, $fields).PHP_EOL.'<script>start_inputmask( "#info_componente" );</script>';
    echo '</div>';
}