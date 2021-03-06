UPDATE `zz_modules` SET `name` = 'Piani di sconto/rincaro' WHERE `name` = 'Listini';

-- Creazione modulo Listini
INSERT INTO `zz_modules` (`id`, `name`, `title`, `directory`, `options`, `options2`, `icon`, `version`, `compatibility`, `order`, `parent`, `default`, `enabled`) VALUES (NULL, 'Listini', 'Listini', 'listini', 'SELECT |select|
FROM mg_prezzi_articoli
    INNER JOIN an_anagrafiche ON an_anagrafiche.idanagrafica = mg_prezzi_articoli.id_anagrafica
    INNER JOIN mg_articoli ON mg_articoli.id = mg_prezzi_articoli.id_articolo
WHERE 1=1 AND mg_articoli.deleted_at IS NULL AND an_anagrafiche.deleted_at IS NULL
ORDER BY an_anagrafiche.ragione_sociale', '', 'fa fa-file-text-o', '2.4', '2.4', '1', NULL, '1', '1');
UPDATE `zz_modules` `t1` INNER JOIN `zz_modules` `t2` ON (`t1`.`name` = 'Listini' AND `t2`.`name` = 'Magazzino') SET `t1`.`parent` = `t2`.`id`;

INSERT INTO `zz_views` (`id_module`, `name`, `query`, `order`, `search`, `slow`, `default`, `visible`) VALUES
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'id', 'mg_prezzi_articoli.id', 1, 1, 0, 1, 0),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Minimo', 'mg_prezzi_articoli.minimo', 4, 1, 0, 1, 1),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Massimo', 'mg_prezzi_articoli.massimo', 5, 1, 0, 1, 1),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Prezzo unitario', 'mg_prezzi_articoli.prezzo_unitario', 6, 1, 0, 1, 1),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Sconto percentuale', 'mg_prezzi_articoli.sconto_percentuale', 7, 1, 0, 1, 1),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Articolo', 'CONCAT(mg_articoli.codice, '' - '', mg_articoli.descrizione)', 2, 1, 0, 1, 1),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Ragione sociale', 'an_anagrafiche.ragione_sociale', 3, 1, 0, 1, 1),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), '_link_module_', '(SELECT id FROM zz_modules WHERE name = ''Articoli'')', 1, 1, 0, 1, 0),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), '_link_record_', 'mg_articoli.id', 1, 1, 0, 1, 0);

-- Aggiunta impstazione per alert occupazione tecnici
INSERT INTO `zz_settings` (`id`, `nome`, `valore`, `tipo`, `editable`, `sezione`) VALUES (NULL, 'Alert occupazione tecnici', '1', 'boolean', '1', 'Attività');

-- Aggiunta supporto riferimento_amministrazione per Anagrafiche
ALTER TABLE `an_anagrafiche` ADD `riferimento_amministrazione` VARCHAR(255) AFTER `codicerea`;

-- Fix dimensioni campi descrittivi
ALTER TABLE `co_contratti` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `co_contratti` CHANGE `esclusioni` `esclusioni` TEXT NULL;
ALTER TABLE `co_documenti` CHANGE `note` `note` TEXT NULL;
ALTER TABLE `co_documenti` CHANGE `note_aggiuntive` `note_aggiuntive` TEXT NULL;
ALTER TABLE `co_movimenti` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `co_preventivi` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `co_preventivi` CHANGE `esclusioni` `esclusioni` TEXT NULL;
ALTER TABLE `co_righe_contratti` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `co_righe_documenti` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `co_righe_preventivi` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `dt_righe_ddt` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `in_interventi` CHANGE `richiesta` `richiesta` TEXT NULL;
ALTER TABLE `in_interventi` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `in_interventi` CHANGE `informazioniaggiuntive` `informazioniaggiuntive` TEXT NULL;
ALTER TABLE `mg_articoli` CHANGE `contenuto` `contenuto` TEXT NULL;
ALTER TABLE `my_impianto_componenti` CHANGE `contenuto` `contenuto` TEXT NULL;
ALTER TABLE `or_righe_ordini` CHANGE `descrizione` `descrizione` TEXT NULL;
ALTER TABLE `zz_modules` CHANGE `options` `options` TEXT NULL;
ALTER TABLE `zz_modules` CHANGE `options2` `options2` TEXT NULL;
ALTER TABLE `zz_widgets` CHANGE `query` `query` TEXT NULL;
ALTER TABLE `zz_widgets` CHANGE `text` `text` TEXT NULL;



-- Aggiunto HAVING 2=2 nel modulo listini
UPDATE `zz_modules` SET `options` = 'SELECT |select|\r\nFROM mg_prezzi_articoli\r\n INNER JOIN an_anagrafiche ON an_anagrafiche.idanagrafica = mg_prezzi_articoli.id_anagrafica\r\n INNER JOIN mg_articoli ON mg_articoli.id = mg_prezzi_articoli.id_articolo\r\nWHERE 1=1 AND mg_articoli.deleted_at IS NULL AND an_anagrafiche.deleted_at IS NULL HAVING 2=2\r\nORDER BY an_anagrafiche.ragione_sociale' WHERE `zz_modules`.`id` = (SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini');

-- Aggiunti segmenti nel modulo listini
INSERT INTO `zz_segments` (`id_module`, `name`, `clause`, `position`, `pattern`, `note`, `predefined`, `predefined_accredito`, `predefined_addebito`, `is_fiscale`) VALUES
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Tutti', '1=1', 'WHR', '####', '', 1, 0, 0, 0),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Fornitori', 'mg_prezzi_articoli.dir=\"uscita\"', 'WHR', '####', '', 0, 0, 0, 0),
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini'), 'Clienti', 'mg_prezzi_articoli.dir=\"entrata\"', 'WHR', '####', '', 0, 0, 0, 0);

-- Aggiunto formattabile nel modulo listini ai campi numerici
UPDATE `zz_views` SET `format` = '1' WHERE `id` = (SELECT `id` FROM `zz_views` WHERE `name` = 'Prezzo unitario');
UPDATE `zz_views` SET `format` = '1' WHERE `id` = (SELECT `id` FROM `zz_views` WHERE `name` = 'Sconto percentuale');
UPDATE `zz_views` SET `format` = '1' WHERE `id` = (SELECT `id` FROM `zz_views` WHERE `name` = 'Minimo');
UPDATE `zz_views` SET `format` = '1' WHERE `id` = (SELECT `id` FROM `zz_views` WHERE `name` = 'Massimo');


-- Sostituito icona Listini con ">"
UPDATE `zz_modules` SET `icon` = 'fa fa-angle-right' WHERE `zz_modules`.`id` = (SELECT `id` FROM `zz_modules` WHERE `name` = 'Listini');

-- Modificato nome plugin dettagli in Prezzi specifici
UPDATE `zz_plugins` SET `name` = 'Prezzi specifici articolo', `title` = 'Prezzi specifici' WHERE `zz_plugins`.`id` = (SELECT `id` FROM `zz_plugins` WHERE `name` = 'Dettagli articolo');

-- Impostazione soft quota
INSERT INTO `zz_settings` (`id`, `nome`, `valore`, `tipo`, `editable`, `sezione`, `order`, `help`) VALUES (NULL, 'Soft quota', '', 'integer', '0', 'Generali', NULL, 'Soft quota in MB');

-- Relativo hook per il calcolo dello spazio utilizzato
INSERT INTO `zz_hooks` (`id`, `name`, `class`,  `enabled`, `id_module`, `processing_at`, `processing_token`) VALUES (NULL, 'Spazio', 'Modules\\StatoServizi\\SpaceHook', '1', (SELECT `id` FROM `zz_modules` WHERE `name`='Stato dei servizi'), NULL, NULL);

INSERT INTO `zz_cache` (`id`, `name`, `content`, `valid_time`, `expire_at`) VALUES
(NULL, 'Spazio utilizzato', '', '15 minute', NOW());

-- Introduzione hook per informazioni su Services
INSERT INTO `zz_hooks` (`id`, `name`, `class`,  `enabled`, `id_module`, `processing_at`, `processing_token`) VALUES (NULL, 'Informazioni su Services', 'Modules\\StatoServizi\\ServicesHook', '1', (SELECT `id` FROM `zz_modules` WHERE `name`='Stato dei servizi'), NULL, NULL);

INSERT INTO `zz_cache` (`id`, `name`, `content`, `valid_time`, `expire_at`) VALUES
(NULL, 'Informazioni su Services', '', '7 days', NOW()),
(NULL, 'Informazioni su spazio FE', '', '7 days', NOW());

-- Aggiunta colonna Tecnici assegnati in Attività
UPDATE `zz_modules` SET `options` = 'SELECT |select| FROM `in_interventi`
INNER JOIN `an_anagrafiche` ON `in_interventi`.`idanagrafica` = `an_anagrafiche`.`idanagrafica`
LEFT JOIN `in_interventi_tecnici` ON `in_interventi_tecnici`.`idintervento` = `in_interventi`.`id`
LEFT JOIN `in_interventi_tecnici_assegnati` ON `in_interventi_tecnici_assegnati`.`id_intervento` = `in_interventi`.`id`
LEFT JOIN `in_statiintervento` ON `in_interventi`.`idstatointervento`=`in_statiintervento`.`idstatointervento`
LEFT JOIN (
    SELECT an_sedi.id, CONCAT(an_sedi.nomesede, ''<br>'',an_sedi.telefono, ''<br>'',an_sedi.cellulare,''<br>'',an_sedi.citta, '' - '', an_sedi.indirizzo) AS info FROM an_sedi
) AS sede_destinazione ON sede_destinazione.id = in_interventi.idsede_destinazione
LEFT JOIN (
    SELECT co_righe_documenti.idintervento, CONCAT(''Fatt. '', co_documenti.numero_esterno, '' del '', DATE_FORMAT(co_documenti.data, ''%d/%m/%Y'')) AS info FROM co_documenti INNER JOIN co_righe_documenti ON co_documenti.id = co_righe_documenti.iddocumento
) AS fattura ON fattura.idintervento = in_interventi.id
WHERE 1=1 |date_period(`orario_inizio`,`data_richiesta`)|
GROUP BY `in_interventi`.`id`
HAVING 2=2
ORDER BY IFNULL(`orario_fine`, `data_richiesta`) DESC' WHERE `name` = 'Interventi';
INSERT INTO `zz_views` (`id_module`, `name`, `query`, `order`, `search`, `format`, `default`, `visible`) VALUES
((SELECT `id` FROM `zz_modules` WHERE `name` = 'Interventi'), 'Tecnici assegnati', 'GROUP_CONCAT((SELECT DISTINCT(ragione_sociale) FROM an_anagrafiche WHERE idanagrafica = in_interventi_tecnici_assegnati.id_tecnico) SEPARATOR '', '')', 14, 1, 0, 1, 1);

UPDATE `zz_views` SET `default` = 1 WHERE `zz_views`.`id_module` = (SELECT `zz_modules`.`id` FROM `zz_modules` WHERE `zz_modules`.`name` = 'Interventi') AND (`zz_views`.`name` = 'Tecnici' OR `zz_views`.`name` = 'Rif. fattura');
