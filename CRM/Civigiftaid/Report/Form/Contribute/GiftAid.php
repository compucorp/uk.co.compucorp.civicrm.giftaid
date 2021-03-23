<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use CRM_Civigiftaid_ExtensionUtil as E;

/**
 * Class CRM_Civigiftaid_Report_Form_Contribute_GiftAid
 */
class CRM_Civigiftaid_Report_Form_Contribute_GiftAid extends CRM_Report_Form {

  protected $_addressField = FALSE;
  protected $_customGroupExtends = ['Contribution'];

  /**
   * @var int
   */
  protected $batchID;

  public function __construct() {
    $this->_columns = [
      'civicrm_entity_batch' => [
        'dao' => 'CRM_Batch_DAO_EntityBatch',
        'filters' => [
          'batch_id' => [
            'title'        => E::ts('Batch'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options'      => CRM_Civigiftaid_Utils_Contribution::getBatchIdTitle(),
            'type' => CRM_Utils_Type::T_INT,
          ],
        ],
        'fields' => [
          'batch_id' => [
            'name'       => 'batch_id',
            'title'      => E::ts('Batch ID'),
            'no_display' => TRUE,
            'required'   => TRUE,
          ]
        ]
      ],
      'civicrm_contact' => [
        'dao'    => 'CRM_Contact_DAO_Contact',
        'fields' => [
          'prefix_id' => [
            'name'       => 'prefix_id',
            'title'      => E::ts('Title'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
          'first_name'      => [
            'name'       => 'first_name',
            'title'      => E::ts('First Name'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
          'last_name'    => [
            'name'       => 'last_name',
            'title'      => E::ts('Last Name'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
        ],
      ],
      'civicrm_contribution' => [
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' => [
          'contribution_id' => [
            'name' => 'id',
            'title' => E::ts('Contribution ID'),
            'required' => FALSE,
          ],
          'contact_id' => [
            'name' => 'contact_id',
            'title' => E::ts('Donor Name'),
            'no_display' => TRUE,
            'required' => TRUE,
          ],
          'receive_date' => [
            'name' => 'receive_date',
            'title' => E::ts('Donation Date'),
            'type' => CRM_Utils_Type::T_STRING,
            'required' => TRUE,
          ],
          'contribution_amount' => [
            'name' => 'total_amount',
            'title' => E::ts('Donation Amount'),
            'type' => CRM_Utils_Type::T_INT,
            'no_display' => TRUE,
            'required' => TRUE,
          ]
        ],
      ],
      'civicrm_address' => [
        'dao'      => 'CRM_Core_DAO_Address',
        'grouping' => 'contact-fields',
        'fields'   => [
          'street_address'    => [
            'name'       => 'street_address',
            'title'      => E::ts('Street Address'),
            'no_display' => TRUE,
            'required'   => TRUE,
          ],
          'postal_code'       => [
            'name'       => 'postal_code',
            'title'      => E::ts('Postcode'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
        ],
      ],
    ];

    parent::__construct();

    // set defaults
    if (is_array($this->_columns['civicrm_value_gift_aid_submission'])) {
      foreach ($this->_columns['civicrm_value_gift_aid_submission']['fields'] as $field => $values) {
        $this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['default'] = TRUE;
        if ($values['dataType'] === 'Money') {
          $this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['dataType'] = 'Integer';
          $this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['type'] = CRM_Utils_Type::T_INT;
        }
      }
      // Remove the Gift Aid Batch name filter - it doesn't work and we have batch_id via civicrm_entity_batch which does
      if (isset($this->_columns['civicrm_value_gift_aid_submission']['filters'][CRM_Civigiftaid_Utils::getCustomByName('batch_name', 'Gift_Aid')])) {
        unset($this->_columns['civicrm_value_gift_aid_submission']['filters'][CRM_Civigiftaid_Utils::getCustomByName('batch_name', 'Gift_Aid')]);
      }
    }
  }

  public function select() {
    $select = [];

    $this->_columnHeaders = [];
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field)
            || CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            if ($tableName == 'civicrm_address') {
              $this->_addressField = TRUE;
            }
            else {
              if ($tableName == 'civicrm_email') {
                $this->_emailField = TRUE;
              }
            }

            // only include statistics columns if set
            if (CRM_Utils_Array::value('statistics', $field)) {
              foreach ($field['statistics'] as $stat => $label) {
                switch (strtolower($stat)) {
                  case 'sum':
                    $select[] =
                      "SUM({$field['dbAlias']}) as {$tableName}_{$fieldName}_{$stat}";
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] =
                      $label;
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] =
                      $field['type'];
                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                    break;

                  case 'count':
                    $select[] =
                      "COUNT({$field['dbAlias']}) as {$tableName}_{$fieldName}_{$stat}";
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] =
                      $label;
                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                    break;

                  case 'avg':
                    $select[] =
                      "ROUND(AVG({$field['dbAlias']}),2) as {$tableName}_{$fieldName}_{$stat}";
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] =
                      $field['type'];
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] =
                      $label;
                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                    break;
                }
              }
            }
            else {
              $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
              $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] =
                $field['title'];
              $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] =
                CRM_Utils_Array::value('type', $field);
            }
          }
        }
      }
    }

    $this->_columnHeaders['civicrm_address_house'] = [
      'title' => 'House name or number',
    ];

    /**
     * HMRC Gift Aid spreadsheet requires columns for Aggregated Donations and Sponsored Events.
     * Normally blank, these are included here so the CiviCRM csv file matches the HMRC format.
     */
    $this->_columnHeaders['aggregated_donations'] = [
      'title' => 'Aggregated Donations',
    ];
    $this->_columnHeaders['sponsored_event'] = [
      'title' => 'Sponsored Event',
    ];


    $this->reorderColumns();

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  public function from() {
    $this->_from = "
      FROM civicrm_entity_batch {$this->_aliases['civicrm_entity_batch']}
      INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
      ON {$this->_aliases['civicrm_entity_batch']}.entity_table = 'civicrm_contribution'
        AND {$this->_aliases['civicrm_entity_batch']}.entity_id = {$this->_aliases['civicrm_contribution']}.id
      INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
      ON {$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_contact']}.id
      LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
      ON ({$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_address']}.contact_id
        AND {$this->_aliases['civicrm_address']}.is_primary = 1)";
  }

  public function where() {
    $this->_whereClauses[] = "{$this->_aliases['civicrm_value_gift_aid_submission']}.amount IS NOT NULL";
    $this->_whereClauses[] = "{$this->_aliases['civicrm_contact']}.contact_type = 'Individual'";
    if ($this->batchID) {
      $this->_whereClauses[] = "{$this->_aliases['civicrm_entity_batch']}.batch_id IN ({$this->batchID})";
    }
    parent::where();
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $totals = [
      'contribution' => 0,
      'eligibleAmount' => 0,
      'giftAidAmount' => 0,
    ];
    $giftAidEligibleAmountField = 'civicrm_value_gift_aid_submission_' . CRM_Civigiftaid_Utils::getCustomByName('amount', 'Gift_Aid');
    $giftAidAmountField = 'civicrm_value_gift_aid_submission_' . CRM_Civigiftaid_Utils::getCustomByName('gift_aid_amount', 'Gift_Aid');

    foreach ($rows as $row) {
      $totals['contribution'] += $row['civicrm_contribution_contribution_amount'];
      $totals['eligibleAmount'] += $row[$giftAidEligibleAmountField];
      $totals['giftAidAmount'] += $row[$giftAidAmountField];
    }

    foreach ($totals as $key => $value) {
      $totals[$key] = number_format($value, 2);
    }

    $select = "
      SELECT SUM({$this->_aliases['civicrm_value_gift_aid_submission']}.amount) as amount,
        SUM({$this->_aliases['civicrm_value_gift_aid_submission']}.gift_aid_amount) as gift_aid_amount
      ";

    $sql = "{$select} {$this->_from} {$this->_where}";

    $dao = CRM_Core_DAO::executeQuery($sql);

    if ($dao->fetch()) {
      $statistics['counts']['amount'] = [
        'value' => $dao->amount,
        'title' => E::ts('Total Amount'),
        'type' => CRM_Utils_Type::T_MONEY
      ];
      $statistics['counts']['giftaidamount'] = [
        'value' => $dao->gift_aid_amount,
        'title' => E::ts('Total Gift Aid Amount'),
        'type' => CRM_Utils_Type::T_MONEY
      ];
    }

    return $statistics;
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function preProcess() {
    $this->batchID = CRM_Utils_Request::retrieveValue('batch_id', 'Positive', NULL, FALSE, 'GET');
    if ($this->batchID) {
      $this->_force = 1;
    }
    parent::preProcess();
  }

  /**
   * Post process function.
   */
  public function postProcess() {
    // get ready with post process params
    $this->beginPostProcess();

    // build query
    $sql = $this->buildQuery();

    // build array of result based on column headers. This method also allows
    // modifying column headers before using it to build result set i.e $rows.
    $rows = [];
    $this->buildRows($sql, $rows);

    $this->assign('statistics', $this->statistics($rows));

    // format result set.
    $this->formatDisplay($rows);

    // assign variables to templates
    $this->assign_by_ref('columnHeaders', $this->_columnHeaders);
    $this->assign_by_ref('rows', $rows);

    // do print / pdf / instance stuff if needed
    $this->endPostProcess($rows);
  }

  /**
   * Alter the rows for display
   *
   * @param array $rows
   */
  public function alterDisplay(&$rows) {
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {
      if (array_key_exists('civicrm_contact_first_name', $row)) {
        list($contactName, $errors) = CRM_Civigiftaid_Declaration::getFilteredDonorName($row['civicrm_contact_first_name'], $row['civicrm_contact_last_name']);
        $rows[$rowNum]['civicrm_contact_first_name'] = $contactName[0];
        $rows[$rowNum]['civicrm_contact_last_name'] = $contactName[1];
      }
      if (array_key_exists('civicrm_contribution_contact_id', $row)) {
        if ($value = $row['civicrm_contribution_contact_id']) {
          $contact = new CRM_Contact_DAO_Contact();
          $contact->id = $value;
          $contact->find(TRUE);
          $rows[$rowNum]['civicrm_contribution_contact_id'] =
            $contact->display_name;
          $url = CRM_Utils_System::url("civicrm/contact/view",
            'reset=1&cid=' . $value,
            $this->_absoluteUrl);
          $rows[$rowNum]['civicrm_contribution_contact_id_link'] = $url;
          $rows[$rowNum]['civicrm_contribution_contact_id_hover'] =
            ts("View Contact Summary for this Contact.");
        }
        $entryFound = TRUE;
      }
      if (array_key_exists('civicrm_contribution_contribution_id', $row)) {
        $url = CRM_Utils_System::url("civicrm/contact/view/contribution",
          "reset=1&cid={$row['civicrm_contribution_contact_id']}&id={$row['civicrm_contribution_contribution_id']}&action=view&context=contribution",
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contribution_contribution_id_link'] = $url;
        $rows[$rowNum]['civicrm_contribution_contribution_id_hover'] = ts('View contribution');
      }


      if (array_key_exists('civicrm_address_street_address', $row)) {
        $address = CRM_Civigiftaid_Declaration::getDonorAddress($row['civicrm_contribution_contact_id'], $row['civicrm_contribution_receive_date']);
        $rows[$rowNum]['civicrm_address_house'] = $address['house'];
        $rows[$rowNum]['civicrm_address_street_address'] = $address['address'];
        $rows[$rowNum]['civicrm_address_postal_code'] = $address['postcode'];
      }

      // handle Contact Title
      if (array_key_exists('civicrm_contact_prefix_id', $row)) {
        if ($value = $row['civicrm_contact_prefix_id']) {
          $rows[$rowNum]['civicrm_contact_prefix_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'prefix_id', $value);
        }
        $entryFound = TRUE;
      }

      // handle donation date
      if (array_key_exists('civicrm_contribution_receive_date', $row)) {
        if ($value = $row['civicrm_contribution_receive_date']) {
          $rows[$rowNum]['civicrm_contribution_receive_date'] = date("d/m/y", strtotime($value));
        }
        $entryFound = TRUE;
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }

  private function reorderColumns() {
    $columnTitleOrder = [
      'title',
      'first name',
      'last name',
      'house name or number',
      'street address',
      'city',
      'county',
      'postcode',
      'aggregated donations',
      'sponsored event',
      'country',
      'donation date',
      'amount',
      'donor name',
      'item',
      'description',
      'quantity',
      'eligible for gift aid?',
      'donation amount',
      'eligible amount',
      'gift aid amount',
      'batch name',
      'contribution id',
      'line item id',
    ];

    $compare = function ($a, $b) use (&$columnTitleOrder) {
      $titleA = strtolower($a['title']);
      $titleB = strtolower($b['title']);

      $posA = array_search($titleA, $columnTitleOrder);
      $posB = array_search($titleB, $columnTitleOrder);

      if ($posA === FALSE) {
        $columnTitleOrder[] = $titleA;
      }
      if ($posB === FALSE) {
        $columnTitleOrder[] = $titleB;
      }

      if ($posA > $posB || $posA === FALSE) {
        return 1;
      }
      if ($posA < $posB || $posB === FALSE) {
        return -1;
      }

      return 0;
    };

    $orderedColumnHeaders = $this->_columnHeaders;
    uasort($orderedColumnHeaders, $compare);

    $this->_columnHeaders = $orderedColumnHeaders;
  }

}

