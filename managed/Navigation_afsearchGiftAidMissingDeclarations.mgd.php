<?php
use CRM_Civigiftaid_ExtensionUtil as E;

return [
  [
    'name' => 'Navigation_afsearchGiftAidMissingDeclarations',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Gift aid: Missing declarations'),
        'name' => 'afsearchGiftAidMissingDeclarations',
        'url' => 'civicrm/gift-aid-missing-declarations',
        'icon' => 'crm-i fa-list-alt',
        'permission' => ['access CiviCRM'],
        'permission_operator' => 'AND',
        'parent_id.name' => 'admin_giftaid',
        'weight' => 3,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
];
