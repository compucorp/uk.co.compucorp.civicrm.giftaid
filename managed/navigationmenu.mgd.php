<?php

use CRM_Civigiftaid_ExtensionUtil as E;

return [
  [
    'name' => 'navmenu_admin_giftaid',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('GiftAid'),
        'name' => 'admin_giftaid',
        'url' => NULL,
        'permission' => 'access CiviContribute',
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviContribute',
        'is_active' => TRUE,
        'has_separator' => 1,
        'weight' => 90,
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'navmenu_giftaid_settings',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Settings'),
        'name' => 'giftaid_settings',
        'url' => 'civicrm/admin/setting/ukgiftaid',
        'permission' => 'access CiviContribute',
        'permission_operator' => 'OR',
        'parent_id.name' => 'admin_giftaid',
        'is_active' => TRUE,
        'has_separator' => 0,
        'weight' => 90,
      ],
      'match' => ['name'],
    ],
  ],
];
