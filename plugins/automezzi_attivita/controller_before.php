<?php

// Il caricatore componenti di OSM 2.10.4 non valorizza il campo `option` dei plugin.
// Forziamo il tipo custom in memoria già dalla prima apertura e persistiamo la configurazione.
$type = 'custom';

if (($structure['option'] ?? '') !== 'custom' || ($structure['version'] ?? '') !== '1.2.0') {
    $dbo->update('zz_plugins', [
        'option' => 'custom',
        'version' => '1.2.0',
        'compatibility' => '2.10.4',
        'position' => 'tab',
    ], [
        'id' => $id_plugin,
    ]);
}

// Schema proprietario del plugin: nessuna modifica alle tabelle core OSM.
$table = 'zz_automezzi_attivita_sessioni';
if (!$dbo->tableExists($table)) {
    $dbo->query("CREATE TABLE `{$table}` (
        `id_sessione` INT NOT NULL,
        `id_automezzo` INT NULL,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_sessione`),
        INDEX `idx_automezzo` (`id_automezzo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
