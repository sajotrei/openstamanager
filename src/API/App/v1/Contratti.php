<?php

namespace API\App\v1;

use API\App\AppResource;
use API\Interfaces\RetrieveInterface;
use Carbon\Carbon;

class Contratti extends AppResource implements RetrieveInterface
{
    protected function getCleanupData()
    {
        $query = 'SELECT DISTINCT(co_contratti.id) AS id FROM co_contratti
            INNER JOIN co_staticontratti ON co_staticontratti.id = co_contratti.idstato
        WHERE co_staticontratti.is_pianificabile = 0';
        $records = database()->fetchArray($query);

        $da_stati = array_column($records, 'id');
        $mancanti = $this->getMissingIDs('co_contratti', 'id');

        $results = array_unique(array_merge($da_stati, $mancanti));

        return $results;
    }

    protected function getData($last_sync_at)
    {
        $query = "SELECT DISTINCT(co_contratti.id) AS id FROM co_contratti
            INNER JOIN co_staticontratti ON co_staticontratti.id = co_contratti.idstato
            INNER JOIN an_anagrafiche ON an_anagrafiche.idanagrafica = co_contratti.idanagrafica
            INNER JOIN an_tipianagrafiche_anagrafiche ON an_tipianagrafiche_anagrafiche.idanagrafica = an_anagrafiche.idanagrafica
            INNER JOIN an_tipianagrafiche ON an_tipianagrafiche_anagrafiche.idtipoanagrafica = an_tipianagrafiche.idtipoanagrafica
        WHERE an_tipianagrafiche.descrizione = 'Cliente' AND co_staticontratti.is_pianificabile = 1 AND an_anagrafiche.deleted_at IS NULL";

        // Filtro per data
        if ($last_sync_at) {
            $last_sync = new Carbon($last_sync_at);
            $query .= ' AND co_contratti.updated_at > '.prepare($last_sync);
        }

        $records = database()->fetchArray($query);

        return array_column($records, 'id');
    }

    protected function retrieveRecord($id)
    {
        // Gestione della visualizzazione dei dettagli del record
        $query = 'SELECT co_contratti.id,
            co_contratti.idanagrafica AS id_cliente,
            IF(co_contratti.idsede = 0, NULL, co_contratti.idsede) AS id_sede,
            co_contratti.nome,
            co_contratti.numero,
            co_contratti.data_bozza
        FROM co_contratti
            INNER JOIN co_staticontratti ON co_staticontratti.id = co_contratti.idstato
        WHERE co_contratti.id = '.prepare($id);

        $record = database()->fetchOne($query);

        return $record;
    }
}
