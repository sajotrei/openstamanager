-- Integrazione provider Fatturazione Elettronica per OpenSTAManager 2.10.4.
-- I campi traducibili delle impostazioni sono registrati in zz_settings_lang,
-- secondo il modello Setting nativo OSM.

INSERT IGNORE INTO `zz_settings` (`nome`, `valore`, `tipo`, `editable`, `sezione`, `order`) VALUES
('Fatturazione Elettronica Provider', 'osmcloud', 'list[osmcloud,hosting_solutions]', 1, 'Fatturazione Elettronica', 12),
('Hosting Solutions FE Abilitato', '0', 'boolean', 1, 'Fatturazione Elettronica', 13),
('Hosting Solutions FE Modalita mock', '1', 'boolean', 1, 'Fatturazione Elettronica', 14),
('Hosting Solutions FE Mock Scenario', 'wait', 'list[send_ok,wait,delivered,not_delivered,rejected,timeout,http_4xx,http_5xx,malformed,passive_invoice,duplicate]', 1, 'Fatturazione Elettronica', 15);

-- Allinea installazioni che abbiano applicato una revisione precedente dello stesso sviluppo.
UPDATE `zz_settings`
SET `tipo` = 'list[osmcloud,hosting_solutions]', `editable` = 1, `sezione` = 'Fatturazione Elettronica', `order` = 12
WHERE `nome` = 'Fatturazione Elettronica Provider';

UPDATE `zz_settings`
SET `tipo` = 'boolean', `editable` = 1, `sezione` = 'Fatturazione Elettronica', `order` = 13
WHERE `nome` = 'Hosting Solutions FE Abilitato';

UPDATE `zz_settings`
SET `tipo` = 'boolean', `editable` = 1, `sezione` = 'Fatturazione Elettronica', `order` = 14
WHERE `nome` = 'Hosting Solutions FE Modalita mock';

UPDATE `zz_settings`
SET `tipo` = 'list[send_ok,wait,delivered,not_delivered,rejected,timeout,http_4xx,http_5xx,malformed,passive_invoice,duplicate]', `editable` = 1, `sezione` = 'Fatturazione Elettronica', `order` = 15
WHERE `nome` = 'Hosting Solutions FE Mock Scenario';

-- Traduzioni italiane.
INSERT INTO `zz_settings_lang` (`id_lang`, `id_record`, `title`, `help`)
SELECT 1, s.`id`, 'Provider Fatturazione Elettronica', 'Seleziona il canale di trasporto. OSMCloud mantiene il comportamento nativo; Hosting Solutions usa il relativo adapter. La scelta non modifica il contenuto XML.'
FROM `zz_settings` s
WHERE s.`nome` = 'Fatturazione Elettronica Provider'
  AND NOT EXISTS (SELECT 1 FROM `zz_settings_lang` l WHERE l.`id_lang` = 1 AND l.`id_record` = s.`id`);

INSERT INTO `zz_settings_lang` (`id_lang`, `id_record`, `title`, `help`)
SELECT 1, s.`id`, 'Abilita Hosting Solutions', 'Abilita il provider Hosting Solutions per questa singola installazione/azienda.'
FROM `zz_settings` s
WHERE s.`nome` = 'Hosting Solutions FE Abilitato'
  AND NOT EXISTS (SELECT 1 FROM `zz_settings_lang` l WHERE l.`id_lang` = 1 AND l.`id_record` = s.`id`);

INSERT INTO `zz_settings_lang` (`id_lang`, `id_record`, `title`, `help`)
SELECT 1, s.`id`, 'Modalità simulazione Hosting Solutions', 'Mantieni attiva la simulazione finché non sono disponibili e validate le API ufficiali e l’azienda Hosting Solutions in TEST.'
FROM `zz_settings` s
WHERE s.`nome` = 'Hosting Solutions FE Modalita mock'
  AND NOT EXISTS (SELECT 1 FROM `zz_settings_lang` l WHERE l.`id_lang` = 1 AND l.`id_record` = s.`id`);

INSERT INTO `zz_settings_lang` (`id_lang`, `id_record`, `title`, `help`)
SELECT 1, s.`id`, 'Scenario simulazione Hosting Solutions', 'Scenario usato esclusivamente nei test del provider mock; non produce invii reali a SDI.'
FROM `zz_settings` s
WHERE s.`nome` = 'Hosting Solutions FE Mock Scenario'
  AND NOT EXISTS (SELECT 1 FROM `zz_settings_lang` l WHERE l.`id_lang` = 1 AND l.`id_record` = s.`id`);

-- Traduzioni inglesi.
INSERT INTO `zz_settings_lang` (`id_lang`, `id_record`, `title`, `help`)
SELECT 2, s.`id`, 'Electronic Invoicing Provider', 'Select the transport channel. OSMCloud keeps the native behaviour; Hosting Solutions uses its adapter. This setting does not alter the invoice XML.'
FROM `zz_settings` s
WHERE s.`nome` = 'Fatturazione Elettronica Provider'
  AND NOT EXISTS (SELECT 1 FROM `zz_settings_lang` l WHERE l.`id_lang` = 2 AND l.`id_record` = s.`id`);

INSERT INTO `zz_settings_lang` (`id_lang`, `id_record`, `title`, `help`)
SELECT 2, s.`id`, 'Enable Hosting Solutions', 'Enable the Hosting Solutions provider for this installation/company.'
FROM `zz_settings` s
WHERE s.`nome` = 'Hosting Solutions FE Abilitato'
  AND NOT EXISTS (SELECT 1 FROM `zz_settings_lang` l WHERE l.`id_lang` = 2 AND l.`id_record` = s.`id`);

INSERT INTO `zz_settings_lang` (`id_lang`, `id_record`, `title`, `help`)
SELECT 2, s.`id`, 'Hosting Solutions simulation mode', 'Keep simulation enabled until the official APIs and the Hosting Solutions TEST company have been validated.'
FROM `zz_settings` s
WHERE s.`nome` = 'Hosting Solutions FE Modalita mock'
  AND NOT EXISTS (SELECT 1 FROM `zz_settings_lang` l WHERE l.`id_lang` = 2 AND l.`id_record` = s.`id`);

INSERT INTO `zz_settings_lang` (`id_lang`, `id_record`, `title`, `help`)
SELECT 2, s.`id`, 'Hosting Solutions simulation scenario', 'Scenario used only by the mock provider during tests; it does not send documents to SDI.'
FROM `zz_settings` s
WHERE s.`nome` = 'Hosting Solutions FE Mock Scenario'
  AND NOT EXISTS (SELECT 1 FROM `zz_settings_lang` l WHERE l.`id_lang` = 2 AND l.`id_record` = s.`id`);

-- Rimuove l'impostazione di polling introdotta in una revisione preliminare:
-- i flussi ordinari devono sfruttare esclusivamente le task native OSM.
DELETE l FROM `zz_settings_lang` l
INNER JOIN `zz_settings` s ON s.`id` = l.`id_record`
WHERE s.`nome` = 'Hosting Solutions FE Minuti polling';
DELETE FROM `zz_settings` WHERE `nome` = 'Hosting Solutions FE Minuti polling';

CREATE TABLE IF NOT EXISTS `fe_provider_transactions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_documento` INT NULL,
    `provider` VARCHAR(64) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `xml_hash` CHAR(64) NOT NULL,
    `remote_id` VARCHAR(255) NULL,
    `remote_status` VARCHAR(64) NULL,
    `status` VARCHAR(32) NOT NULL,
    `attempt` INT NOT NULL DEFAULT 0,
    `last_error` TEXT NULL,
    `last_request_at` TIMESTAMP NULL,
    `last_response_at` TIMESTAMP NULL,
    `next_poll_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `fe_provider_transactions_document_provider_hash` (`id_documento`, `provider`, `xml_hash`),
    KEY `fe_provider_transactions_status` (`provider`, `status`),
    CONSTRAINT `fe_provider_transactions_document` FOREIGN KEY (`id_documento`) REFERENCES `co_documenti` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Se una revisione precedente aveva registrato una task generica di polling provider,
-- la disabilita: invio, ricevute e passive sfruttano le task native OSM.
UPDATE `zz_tasks`
SET `enabled` = 0
WHERE `class` = 'Plugins\\ExportFE\\ProviderPollingTask';
