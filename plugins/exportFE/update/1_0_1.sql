-- Hosting Solutions FE Connector - configurazione pre-API.
-- La modalità reale non deve poter essere selezionata finché l'adapter HTTP
-- non è implementato e collaudato con documentazione ufficiale.

UPDATE `zz_settings`
SET `valore` = '1', `editable` = 0
WHERE `nome` = 'Hosting Solutions FE Modalita mock';

UPDATE `zz_settings_lang` l
INNER JOIN `zz_settings` s ON s.`id` = l.`id_record`
SET l.`help` = CASE
    WHEN l.`id_lang` = 1 THEN 'La simulazione resta obbligatoriamente attiva finché non sono disponibili e validate le API ufficiali Hosting Solutions.'
    WHEN l.`id_lang` = 2 THEN 'Simulation remains mandatory until the official Hosting Solutions APIs are available and validated.'
    ELSE l.`help`
END
WHERE s.`nome` = 'Hosting Solutions FE Modalita mock';
