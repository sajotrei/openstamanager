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

namespace Modules\Articoli\Import;

use Carbon\Carbon;
use Importer\CSVImporter;
use Modules\Anagrafiche\Sede;
use Modules\Articoli\Articolo;
use Modules\Articoli\Categoria;
use Modules\Iva\Aliquota;

/**
 * Struttura per la gestione delle operazioni di importazione (da CSV) degli Articoli.
 *
 * @since 2.4.17
 */
class CSV extends CSVImporter
{
    public function getAvailableFields()
    {
        return [
            [
                'field' => 'codice',
                'label' => 'Codice',
                'primary_key' => true,
            ],
            [
                'field' => 'descrizione',
                'label' => 'Descrizione',
            ],
            [
                'field' => 'qta',
                'label' => 'Quantità',
            ],
            [
                'field' => 'um',
                'label' => 'Unit&agrave; di misura',
                'names' => [
                    'Unità di misura',
                    'Unità misura',
                    'Unit` di misura',
                    'um',
                ],
            ],
            [
                'field' => 'prezzo_acquisto',
                'label' => 'Prezzo acquisto',
            ],
            [
                'field' => 'prezzo_vendita',
                'label' => 'Prezzo vendita',
            ],
            [
                'field' => 'peso_lordo',
                'label' => 'Peso lordo (KG)',
                'names' => [
                    'Peso lordo (KG)',
                    'Peso',
                ],
            ],
            [
                'field' => 'volume',
                'label' => 'Volume (M3)',
                'names' => [
                    'Volume (M3)',
                    'Volume',
                ],
            ],
            [
                'field' => 'id_categoria',
                'label' => 'Categoria',
                'names' => [
                    'Categoria',
                    'categoria',
                    'idcategoria',
                ],
            ],
            [
                'field' => 'id_sottocategoria',
                'label' => 'Sottocategoria',
                'names' => [
                    'Sottocategoria',
                    'sottocategoria',
                    'idsottocategoria',
                ],
            ],
            [
                'field' => 'barcode',
                'label' => 'Barcode',
                'names' => [
                    'barcode',
                    'Barcode',
                    'EAN',
                ],
            ],
            [
                'field' => 'id_fornitore',
                'label' => 'Fornitore',
                'names' => [
                    'id_fornitore',
                    'Id Fornitore',
                    'Fornitore',
                ],
            ],
            [
                'field' => 'codice_iva_vendita',
                'label' => 'Codice IVA vendita',
                'names' => [
                    'Codice IVA vendita',
                    'codice_iva_vendita',
                ],
            ],
            [
                'field' => 'ubicazione',
                'label' => 'Ubicazione',
            ],
            [
                'field' => 'note',
                'label' => 'Note',
            ],
        ];
    }

    public function import($record)
    {
        $database = database();
        $primary_key = $this->getPrimaryKey();
        $anagrafica_azienda = Anagrafica::find(setting('Azienda predefinita'));

        // Fix per campi con contenuti derivati da query implicite
        if (!empty($record['id_fornitore'])) {
            $record['id_fornitore'] = $database->fetchOne('SELECT idanagrafica AS id FROM an_anagrafiche WHERE LOWER(ragione_sociale) = LOWER('.prepare($record['id_fornitore']).')')['id'];
        }

        // Gestione categoria e sottocategoria
        if (!empty($record['id_categoria'])) {
            // Categoria
            $categoria = Categoria::where('nome', $record['id_categoria'])->first();
            if (empty($categoria)) {
                $categoria = Categoria::build($record['id_categoria']);
            }

            // Sotto-categoria
            $sottocategoria = null;
            if (!empty($record['id_sottocategoria'])) {
                $sottocategoria = Categoria::where('nome', $record['id_sottocategoria'])
                    ->where('parent', $categoria->id)
                    ->first();

                if (empty($sottocategoria)) {
                    $sottocategoria = Categoria::build($record['id_categoria']);
                    $sottocategoria->parent()->associate($categoria);
                    $sottocategoria->save();
                }
            }
        }
        unset($record['id_categoria']);
        unset($record['id_sottocategoria']);

        // Individuazione dell'IVA di vendita tramite il relativo Codice
        $aliquota = null;
        if (!empty($record['codice_iva_vendita'])) {
            $aliquota = Aliquota::where('codice', $record['codice_iva_vendita'])->first();
        }
        unset($record['codice_iva_vendita']);

        // Individuazione articolo e generazione
        $articolo = Articolo::where($primary_key, $record[$primary_key])->first();
        if (empty($articolo)) {
            $articolo = Articolo::build($record['codice'], $record['descrizione'], $categoria, $sottocategoria);
        }

        $articolo->idiva_vendita = $aliquota->id;
        $articolo->attivo = 1;

        // Prezzo di vendita
        $articolo->setPrezzoVendita($record['prezzo_vendita'], ($aliquota->id ? $aliquota->id : setting('Iva predefinita')));
        unset($record['prezzo_vendita']);

        // Esportazione della quantità indicata
        $qta_registrata = (float) ($record['qta']);
        $nome_sede = $record['$nome_sede'];
        unset($record['qta']);
        unset($record['nome_sede']);

        // Salvataggio delle informazioni generali
        $articolo->fill($record);
        $articolo->save();

        // Movimentazione della quantità registrata
        $giacenze = $articolo->getGiacenze();
        $id_sede = 0;
        if (!empty($nome_sede)) {
            $sede = Sede::where('nomesede', $nome_sede)
                ->where('idanagrafica', $anagrafica_azienda->id)
                ->first();
            $id_sede = $sede->id;
        }

        $qta_movimento = $qta_registrata - $giacenze[$id_sede];

        $articolo->movimenta($qta_movimento, tr('Movimento da importazione'), new Carbon(), false, [
            'idsede_azienda' => $id_sede,
            'idsede_controparte' => 0,
        ]);
    }

    public static function getExample()
    {
        return [
            ['Codice', 'Barcode', 'Descrizione', 'Fornitore', 'Quantità', 'Unità di misura', 'Prezzo acquisto', 'Prezzo vendita', 'Peso lordo (KG)', 'Volume (M3)', 'Categoria', 'Sottocategoria', 'Ubicazione', 'Note'],
            ['00004', '719376861871', 'Articolo', 'Mario Rossi', '10', 'Kg', '5,25', '12,72', '10,2', '500', 'Categoria4', 'Sottocategoria2', 'Scaffale 1', 'Articolo di prova'],
        ];
    }
}
