-- Integrazione provider Fatturazione Elettronica per fork Hosting Solutions.
-- Da applicare sulle installazioni basate su OpenSTAManager 2.10.4.

INSERT IGNORE INTO `zz_settings` (`nome`, `valore`, `tipo`, `editable`, `sezione`, `order`, `help`) VALUES
('Fatturazione Elettronica Provider', 'osmcloud', 'list[osmcloud,hosting_solutions]', 1, 'Fatturazione Elettronica', 12, 'Provider di trasporto FE. OSMCloud mantiene il comportamento nativo; Hosting Solutions utilizza il nuovo adapter. Il provider non modifica il contenuto XML.'),
('Hosting Solutions FE Abilitato', '0', 'boolean', 1, 'Fatturazione Elettronica', 13, 'Abilita il provider Hosting Solutions per questa installazione/azienda.'),
('Hosting Solutions FE Modalita mock', '1', 'boolean', 1, 'Fatturazione Elettronica', 14, 'Usa scenari simulati finche non sono disponibili documentazione API ufficiale e azienda in TEST.'),
('Hosting Solutions FE Mock Scenario', 'wait', 'list[send_ok,wait,delivered,not_delivered,rejected,timeout,http_4xx,http_5xx,malformed,passive_invoice,duplicate]', 1, 'Fatturazione Elettronica', 15, 'Scenario utilizzato esclusivamente dalla modalita simulazione Hosting Solutions.'),
('Hosting Solutions FE Minuti polling', '30', 'integer', 1, 'Fatturazione Elettronica', 16, 'Intervallo minimo tra due controlli provider. Valore minimo applicato dal codice: 15 minuti.');

-- Allinea eventuali installazioni che hanno applicato una revisione precedente dell'update.
UPDATE `zz_settings`
SET `tipo` = 'list[osmcloud,hosting_solutions]',
    `help` = 'Provider di trasporto FE. OSMCloud mantiene il comportamento nativo; Hosting Solutions utilizza il nuovo adapter. Il provider non modifica il contenuto XML.'
WHERE `nome` = 'Fatturazione Elettronica Provider';

UPDATE `zz_settings`
SET `tipo` = 'list[send_ok,wait,delivered,not_delivered,rejected,timeout,http_4xx,http_5xx,malformed,passive_invoice,duplicate]',
    `help` = 'Scenario utilizzato esclusivamente dalla modalita simulazione Hosting Solutions.'
WHERE `nome` = 'Hosting Solutions FE Mock Scenario';

UPDATE `zz_settings`
SET `tipo` = 'integer'
WHERE `nome` = 'Hosting Solutions FE Minuti polling';

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
    KEY `fe_provider_transactions_poll` (`provider`, `status`, `next_poll_at`),
    CONSTRAINT `fe_provider_transactions_document` FOREIGN KEY (`id_documento`) REFERENCES `co_documenti` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `zz_tasks` (`name`, `class`, `expression`, `enabled`)
SELECT 'Polling provider Fatturazione Elettronica', 'Plugins\\ExportFE\\ProviderPollingTask', '*/30 * * * *', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `zz_tasks` WHERE `class` = 'Plugins\\ExportFE\\ProviderPollingTask'
);

INSERT INTO `zz_tasks_lang` (`id_lang`, `id_record`, `title`)
SELECT `zz_langs`.`id`, `zz_tasks`.`id`, IF(`zz_langs`.`id` = 1, 'Polling provider Fatturazione Elettronica', 'Electronic Invoicing Provider Polling')
FROM `zz_tasks`
INNER JOIN `zz_langs`
WHERE `zz_tasks`.`class` = 'Plugins\\ExportFE\\ProviderPollingTask'
  AND NOT EXISTS (
      SELECT 1 FROM `zz_tasks_lang`
      WHERE `zz_tasks_lang`.`id_lang` = `zz_langs`.`id`
        AND `zz_tasks_lang`.`id_record` = `zz_tasks`.`id`
  );
