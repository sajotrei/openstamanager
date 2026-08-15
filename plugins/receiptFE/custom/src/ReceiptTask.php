<?php

namespace Plugins\ReceiptFE;

use Models\Cache;
use Tasks\Manager;

/**
 * Importazione automatica delle ricevute con comportamento compatibile con il
 * task nativo, ma tollerante a cache mancanti e dati provider non validi.
 */
class ReceiptTask extends Manager
{
    private const BATCH_SIZE = 25;

    public function execute()
    {
        $result = [
            'response' => 1,
            'message' => tr('Ricevute importate correttamente!'),
        ];

        if (!Interaction::isEnabled()) {
            return [
                'response' => 1,
                'message' => tr('Canale di fatturazione elettronica non attivo'),
            ];
        }

        $todo_cache = $this->cache('Ricevute Elettroniche');
        $completed_cache = $this->cache('Ricevute Elettroniche importate');

        try {
            $list = Interaction::getRemoteList();
        } catch (\Throwable $e) {
            $this->task->log('error', 'Errore recupero elenco ricevute FE', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'response' => 2,
                'message' => tr('Impossibile recuperare l’elenco delle ricevute'),
            ];
        }

        $todo_cache->set(is_array($list) ? array_values($list) : []);
        $completed_cache->set([]);

        $todo = is_array($todo_cache->content) ? array_values($todo_cache->content) : [];
        if (empty($todo)) {
            return [
                'response' => 1,
                'message' => tr('Nessuna ricevuta da importare'),
            ];
        }

        $completed = [];
        $errors = 0;
        $processed = 0;

        foreach (array_slice($todo, 0, self::BATCH_SIZE) as $index => $element) {
            $name = is_array($element) ? ($element['name'] ?? null) : $element;
            if (empty($name)) {
                ++$errors;
                continue;
            }

            try {
                $fattura = Ricevuta::process($name);
                if ($fattura !== null) {
                    $completed[] = is_array($element) ? $element : ['name' => $name];
                    unset($todo[$index]);
                    ++$processed;
                } else {
                    ++$errors;
                    $this->task->log('warning', 'Ricevuta FE non associata a una fattura', [
                        'file' => $name,
                    ]);
                }
            } catch (\Throwable $e) {
                ++$errors;
                $this->task->log('error', 'Errore importazione ricevuta FE', [
                    'file' => $name,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $todo_cache->set(array_values($todo));
        $completed_cache->set($completed);

        if ($errors > 0) {
            $result['response'] = 2;
        }

        $result['message'] = tr('Ricevute FE: _DONE_ importate, _PENDING_ ancora da elaborare, _ERRORS_ errori', [
            '_DONE_' => $processed,
            '_PENDING_' => count($todo),
            '_ERRORS_' => $errors,
        ]);

        return $result;
    }

    private function cache(string $name): Cache
    {
        $cache = Cache::where('name', $name)->first();

        return $cache ?: Cache::build($name);
    }
}
