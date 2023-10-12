-- /*******************************************************
-- * civicrm_civigiftaid_batchsettings
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_civigiftaid_batchsettings` (


                                                     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique BatchSettings ID',
                                                     `batch_id` int unsigned    COMMENT 'FK to Batch',
                                                     `financial_types_enabled` text    COMMENT 'Financial type enabled for this batch',
                                                     `globally_enabled` tinyint    COMMENT 'Globally enabled for this batch',
                                                     `basic_rate_tax` decimal(4,2) NOT NULL   COMMENT 'Basic rate tax for the batch.'
    ,
                                                     PRIMARY KEY (`id`)


    ,          CONSTRAINT FK_civicrm_civigiftaid_batchsettings_batch_id FOREIGN KEY (`batch_id`) REFERENCES `civicrm_batch`(`id`) ON DELETE CASCADE
)    ;
