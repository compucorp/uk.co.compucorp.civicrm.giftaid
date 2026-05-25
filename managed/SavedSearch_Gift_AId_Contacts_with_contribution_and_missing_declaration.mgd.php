<?php
use CRM_Civigiftaid_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_Gift_AId_Contacts_with_contribution_and_missing_declaration',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Gift_AId_Contacts_with_contribution_and_missing_declaration',
        'label' => E::ts('Gift Aid: Contacts with contribution and missing declaration'),
        'api_entity' => 'Contact',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'sort_name',
            'MAX(Contact_Contribution_contact_id_01.receive_date) AS MAX_',
            'GROUP_CONCAT(DISTINCT Contact_Contribution_contact_id_01.contribution_status_id:label) AS GROUP_CONCAT_',
          ],
          'orderBy' => [],
          'where' => [
            [
              'Contact_Contribution_contact_id_01.receive_date',
              'BETWEEN',
              ['now - 4 year', 'now'],
            ],
          ],
          'groupBy' => ['id'],
          'join' => [
            [
              'Custom_gift_aid_declaration AS Contact_Custom_gift_aid_declaration_entity_id_01',
              'EXCLUDE',
              [
                'id',
                '=',
                'Contact_Custom_gift_aid_declaration_entity_id_01.entity_id',
              ],
            ],
            [
              'Contribution AS Contact_Contribution_contact_id_01',
              'INNER',
              [
                'id',
                '=',
                'Contact_Contribution_contact_id_01.contact_id',
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'SavedSearch_Gift_AId_Contacts_with_contribution_and_missing_declaration_SearchDisplay_Gift_AId_Contacts_with_contribution_and_missing_declaration_Table_1',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Gift_AId_Contacts_with_contribution_and_missing_declaration_Table_1',
        'label' => E::ts('Gift Aid: Contacts with contribution and missing declaration Table 1'),
        'saved_search_id.name' => 'Gift_AId_Contacts_with_contribution_and_missing_declaration',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [
            ['MAX_', 'DESC'],
          ],
          'limit' => 50,
          'pager' => [],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'sort_name',
              'label' => E::ts('Name'),
              'sortable' => TRUE,
              'link' => [
                'path' => '',
                'entity' => 'Contact',
                'action' => 'view',
                'join' => '',
                'target' => '',
                'task' => '',
              ],
              'title' => E::ts('View Contact'),
            ],
            [
              'type' => 'field',
              'key' => 'MAX_',
              'label' => E::ts('Last Contribution Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_',
              'label' => E::ts('Contribution Status'),
              'sortable' => TRUE,
            ],
          ],
          'actions' => TRUE,
          'classes' => ['table', 'table-striped'],
          'columnMode' => 'custom',
          'actions_display_mode' => 'menu',
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];
