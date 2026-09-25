<?php
include_once __DIR__.'/../../../core.php';
require_once __DIR__.'/src/Esercizio.php';

use Models\Module;
use Modules\Partitario\Esercizio;

if (!in_array(post('op'), ['apri-bilancio', 'chiudi-bilancio'], true)) {
    include __DIR__.'/../actions.php';
    return;
}

Permissions::check('rw');

try {
    $pdcModule = Module::where('name', 'Piano dei conti')->first();
    if (empty($pdcModule) || Modules::getPermission($pdcModule->id) !== 'rw') {
        throw new DomainException(tr('Permesso di scrittura sul Piano dei conti non disponibile.'));
    }

    $operation = post('op') === 'apri-bilancio' ? 'apertura' : 'chiusura';
    $postedOperation = (string) post('operation');
    $periodStart = (string) post('period_start');
    $periodEnd = (string) post('period_end');
    $registrationDate = (string) post('registration_date');
    $fingerprint = (string) post('fingerprint');

    Esercizio::assertValidPeriod($periodStart, $periodEnd);
    Esercizio::assertValidDate($registrationDate, tr('Data di registrazione non valida.'));

    if (
        $postedOperation !== $operation
        || $periodStart !== (string) ($_SESSION['period_start'] ?? '')
        || $periodEnd !== (string) ($_SESSION['period_end'] ?? '')
    ) {
        throw new DomainException(tr('L\'anteprima è cambiata. Riapri Gestione esercizio e verifica nuovamente i dati.'));
    }

    $esercizio = new Esercizio($periodStart, $periodEnd);
    $preview = $esercizio->getPreview($operation);
    if ($registrationDate !== $preview['date'] || $fingerprint === '' || !hash_equals($preview['fingerprint'], $fingerprint)) {
        throw new DomainException(tr('L\'anteprima è cambiata. Riapri Gestione esercizio e verifica nuovamente i dati.'));
    }

    $id_mastrino = $esercizio->execute($operation, $fingerprint, $periodStart, $periodEnd);
    $message = $operation === 'apertura'
        ? tr('Apertura esercizio completata.')
        : tr('Chiusura esercizio completata.');

    // Questo link era già corretto nella RC6: non modificarne il comportamento.
    $link = Modules::link('Prima nota', $id_mastrino, tr('Visualizza Mastrino'));
    flash()->info('<i class="fa fa-check-circle"></i> '.$message.' '.tr('Mastrino').': #'.$id_mastrino.' '.$link);
} catch (DomainException $e) {
    flash()->warning($e->getMessage());
} catch (Throwable $e) {
    flash()->error(tr('Operazione non completata. Nessuna scrittura parziale è stata mantenuta.'));
    error_log('[Partitario - Gestione esercizio RC7] '.$e->getMessage());
}
