-- Destinazioni multiple per i backup
CREATE TABLE IF NOT EXISTS `zz_backup_destinations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `id_adapter` INT(11) NOT NULL,
    `path` VARCHAR(255) NOT NULL DEFAULT 'backups',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `retention` INT(11) NOT NULL DEFAULT 10,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_backup_destination_adapter` (`id_adapter`),
    KEY `idx_backup_destination_enabled` (`enabled`),
    CONSTRAINT `fk_backup_destination_adapter`
        FOREIGN KEY (`id_adapter`) REFERENCES `zz_storage_adapters` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
