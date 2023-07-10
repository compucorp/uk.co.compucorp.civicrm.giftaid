<?php
/*--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
+--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
+--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +-------------------------------------------------------------------*/

use CRM_Civigiftaid_ExtensionUtil as E;

return [
  'civigiftaid_globally_enabled' => [
    'name' => 'civigiftaid_globally_enabled',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => TRUE,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Enable gift aid for line items of any financial type'),
    'html_attributes' => [],
    'settings_pages' => [
      'ukgiftaid' => [
        'weight' => 5,
      ]
    ],
  ],

  // financial_type
  'civigiftaid_financial_types_enabled' => [
    'name' => 'civigiftaid_financial_types_enabled',
    'type' => 'Array',
    'html_type' => 'select',
    'default' => [],
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Enabled Financial Types'),
    'description' => E::ts('Select which financial types are eligible for gift aid.'),
    'html_attributes' => [
      'placeholder' => E::ts('- select -'),
      'class' => 'crm-select2',
      'multiple' => TRUE
    ],
    'pseudoconstant' => ['callback' => 'CRM_Civigiftaid_Settings::allFinancialTypes'],
    'settings_pages' => [
      'ukgiftaid' => [
        'weight' => 10,
      ]
    ],
  ],
];
