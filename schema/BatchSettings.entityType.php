<?php
use CRM_Civigiftaid_ExtensionUtil as E;
return [
  'name' => 'BatchSettings',
  'table' => 'civicrm_civigiftaid_batchsettings',
  'class' => 'CRM_Civigiftaid_DAO_BatchSettings',
  'getInfo' => fn() => [
    'title' => E::ts('Batch Settings'),
    'title_plural' => E::ts('Batch Settingses'),
    'description' => E::ts('FIXME'),
    'log' => TRUE,
    'add' => '4.4',
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique BatchSettings ID'),
      'add' => '4.4',
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'batch_id' => [
      'title' => E::ts('Batch ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Batch'),
      'add' => '4.4',
      'entity_reference' => [
        'entity' => 'Batch',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'financial_types_enabled' => [
      'title' => E::ts('Financial Types Enabled'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'description' => E::ts('Financial type enabled for this batch'),
      'add' => '4.4',
    ],
    'globally_enabled' => [
      'title' => E::ts('Globally Enabled'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'description' => E::ts('Globally enabled for this batch'),
      'add' => '4.4',
    ],
    'basic_rate_tax' => [
      'title' => E::ts('Basic Rate Tax'),
      'sql_type' => 'decimal(4,2)',
      'input_type' => NULL,
      'required' => TRUE,
      'description' => E::ts('Basic rate tax for the batch.'),
      'add' => '4.4',
    ],
  ],
];
