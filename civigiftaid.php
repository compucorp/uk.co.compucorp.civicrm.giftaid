<?php

require_once 'civigiftaid.civix.php';

define('CIVICRM_GIFTAID_ADD_TASKID', 1435);
define('CIVICRM_GIFTAID_REMOVE_TASKID', 1436);

/**
 * Implementation of hook_civicrm_config
 */
function civigiftaid_civicrm_config(&$config) {
  _civigiftaid_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 */
function civigiftaid_civicrm_install() {
  require_once 'CRM/Utils/Migrate/Import.php';
  $import = new CRM_Utils_Migrate_Import();
  $extRoot = dirname(__FILE__) . DIRECTORY_SEPARATOR;
  $op = $extRoot . 'xml' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR
    . 'CustomGroupData.xml';
  $import->run($op);
  // rebuild the menu so our path is picked up
  require_once 'CRM/Core/Invoke.php';
  CRM_Core_Invoke::rebuildMenuAndCaches();
  return _civigiftaid_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 */
function civigiftaid_civicrm_enable() {
  return _civigiftaid_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function civigiftaid_civix_civicrm_entityTypes(&$entityTypes) {
}

/**
 * @param $objectType
 * @param $tasks
 */
function civigiftaid_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contribution') {
    $tasks[CIVICRM_GIFTAID_ADD_TASKID] = array(
      'title'  => ts('Add to Gift Aid batch'),
      'class'  => 'CRM_Civigiftaid_Form_Task_AddToBatch',
      'result' => FALSE
    );
    $tasks[CIVICRM_GIFTAID_REMOVE_TASKID] = array(
      'title'  => ts('Remove from Gift Aid batch'),
      'class'  => 'CRM_Civigiftaid_Form_Task_RemoveFromBatch',
      'result' => FALSE
    );
  }
}

/**
 * Implementation of hook_civicrm_post.
 */
function civigiftaid_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  $hooks = [
    new CRM_Civigiftaid_Hook_Post_SetContributionGiftAidEligibility(),
  ];

  foreach ($hooks as $hook) {
    $hook->run($op, $objectName, $objectId, $objectRef);
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 * To copy the contact's home address to the declaration, when the declaration is created
 * Only for offline contribution
 */
function civigiftaid_civicrm_postProcess($formName, &$form) {
  if ($formName != 'CRM_Contact_Form_CustomData') {
    return;
  }

  $groupID = $form->getVar('_groupID');
  $contactId = $form->getVar('_entityId');
  $tableName = CRM_Core_DAO::getFieldValue(
    'CRM_Core_DAO_CustomGroup',
    $groupID,
    'table_name',
    'id'
  );

  if ($tableName == 'civicrm_value_gift_aid_declaration') {
    //FIXME: dirty hack to get the latest declaration for the contact
    $sql = "
      SELECT MAX(id) FROM civicrm_value_gift_aid_declaration
      WHERE entity_id = %1";

    $params = array(1 => array($contactId, 'Integer'));
    $rowId = CRM_Core_DAO::singleValueQuery($sql, $params);

    // Get the home address of the contact
    $addressDetails = _civigiftaid_civicrm_custom_get_address_and_postal_code(
      $contactId,
      1
    );

    $sql = "
      UPDATE civicrm_value_gift_aid_declaration
      SET  address = %1,
      post_code = %2
      WHERE  id = %3";

    $dao = CRM_Core_DAO::executeQuery(
      $sql,
      array(
        1 => array($addressDetails[0], 'String'),
        2 => array($addressDetails[1], 'String'),
        3 => array($rowId, 'Integer'),
      )
    );
  }
}

/**
 * Implementation of hook_civicrm_custom
 * Create / update Gift Aid declaration records on Individual when
 * "Eligible for Gift Aid" field on Contribution is updated.
 */
function civigiftaid_civicrm_custom($op, $groupID, $entityID, &$params) {
  if ($op != 'create' /* TODO && $op != 'edit' */) {
    return;
  }

  //Do this only for online contributions
  if ($_GET['q'] != 'civicrm/contribute/transact' OR empty($_GET['q'])) {
    return;
  }

  require_once 'CRM/Core/DAO.php';
  $tableName = CRM_Core_DAO::getFieldValue(
    'CRM_Core_DAO_CustomGroup',
    $groupID,
    'table_name',
    'id'
  );

  if ($tableName == 'civicrm_value_gift_aid_submission') {
    // Iterate through $params to get new declaration value
    $newStatus = NULL;
    if (!is_array($params) || empty($params)) {
      return;
    }

    foreach ($params as $field) {
      if ($field['column_name'] == 'eligible_for_gift_aid') {
        $newStatus = $field['value'];
        break;
      }
    }

    if (is_null($newStatus)) {
      return;
    }

    // Get contactID.
    $sql = "
      SELECT contact_id, receive_date
      FROM civicrm_contribution
      WHERE id = %1";

    $dao = CRM_Core_DAO::executeQuery(
      $sql,
      array(1 => array($entityID, 'Integer'))
    );

    if ($dao->fetch()) {
      $contactID = $dao->contact_id;
      $contributionDate = $dao->receive_date;
    }

    if ($contactID) {
      $addressDetails = _civigiftaid_civicrm_custom_get_address_and_postal_code(
        $contactID,
        1
      );

      require_once 'CRM/Civigiftaid/Utils/GiftAid.php';
      $params = array(
        'entity_id'             => $contactID,
        'eligible_for_gift_aid' => $newStatus,
        'start_date'            => $contributionDate,
        'address'               => $addressDetails[0],
        'post_code'             => $addressDetails[1],
      );
      CRM_Civigiftaid_Utils_GiftAid::setDeclaration($params);
    }
  }
}

/**
 * Implementation of hook_civicrm_validate
 * Validate set of Gift Aid declaration records on Individual,
 * from multi-value custom field edit form:
 * - check end > start,
 * - check for overlaps between declarations.
 */
function civigiftaid_civicrm_validate($formName, &$fields, &$files, &$form) {
  $errors = array();

  if ($formName == 'CRM_Contact_Form_CustomData') {
    $groupID = $form->getVar('_groupID');
    $contactID = $form->getVar('_entityId');
    require_once 'CRM/Core/DAO.php';
    $tableName = CRM_Core_DAO::getFieldValue(
      'CRM_Core_DAO_CustomGroup',
      $groupID,
      'table_name',
      'id'
    );

    if ($tableName == 'civicrm_value_gift_aid_declaration') {

      // Assemble multi-value field values from custom_X_Y into
      // array $declarations of sets of values as column_name => value
      $sql = "
        SELECT id, column_name
        FROM civicrm_custom_field
        WHERE custom_group_id = %1";

      $dao = CRM_Core_DAO::executeQuery(
        $sql,
        array(1 => array($groupID, 'Integer'))
      );

      $columnNames = array();
      while ($dao->fetch()) {
        $columnNames[$dao->id] = $dao->column_name;
      }

      $declarations = array();
      foreach ($fields as $name => $value) {
        if (preg_match('/^custom_(\d+)_(-?\d+)$/', $name, $matches)) {
          $columnName = CRM_Utils_Array::value($matches[1], $columnNames);
          if ($columnName) {
            $declarations[$matches[2]][$columnName]['value'] = $value;
            $declarations[$matches[2]][$columnName]['name'] = $name;
          }
        }
      }

      require_once 'CRM/Utils/Date.php';
      // Iterate through each distinct pair of declarations, checking for overlap.
      foreach ($declarations as $id1 => $values1) {
        $start1 = CRM_Utils_Date::processDate($values1['start_date']['value']);
        if ($values1['end_date']['value'] == '') {
          $end1 = '25000101000000';
        }
        else {
          $end1 = CRM_Utils_Date::processDate($values1['end_date']['value']);
        }

        if ($values1['end_date']['value'] != '' && $start1 >= $end1) {
          $errors[$values1['end_date']['name']] =
            'End date must be later than start date.';
          continue;
        }

        $charity1 = NULL;
        if (array_key_exists('charity', $values1)) {
          $charity1 = CRM_Utils_Array::value('value', $values1['charity']);
        }

        foreach ($declarations as $id2 => $values2) {
          $charity2 = NULL;
          if (array_key_exists('charity', $values2)) {
            $charity2 = CRM_Utils_Array::value('value', $values2['charity']);
          }
          if (($id2 <= $id1) || ($charity1 != $charity2)) {
            continue;
          }

          $start2 = CRM_Utils_Date::processDate(
            $values2['start_date']['value']
          );

          if ($values2['end_date']['value'] == '') {
            $end2 = '25000101000000';
          }
          else {
            $end2 = CRM_Utils_Date::processDate($values2['end_date']['value']);
          }

          if ($start1 < $end2 && $end1 > $start2) {
            $message = 'This declaration overlaps with the one from '
              . $values2['start_date']['value'];

            if ($values2['end_date']['value']) {
              $message .= ' to ' . $values2['end_date']['value'];
            }

            $errors[$values1['start_date']['name']] = $message;
            $message = 'This declaration overlaps with the one from '
              . $values1['start_date']['value'];

            if ($values1['end_date']['value']) {
              $message .= ' to ' . $values1['end_date']['value'];
            }

            $errors[$values2['start_date']['name']] = $message;
          }
        }
      }

      // Check if the contact has a home address
      foreach ($declarations as $id3 => $values3) {
        require_once 'api/api.php';
        $address = civicrm_api(
          "Address",
          "get",
          array(
            'version'          => '3',
            'contact_id'       => $contactID,
            'location_type_id' => 1
          )
        );
        if ($address['count'] == 0) {
          $errors[$values3['eligible_for_gift_aid']['name']] =
            ts('You will not be able to create giftaid declaration because there is no home address recorded for this contact. If you want to create a declaration, please add home address for this contact.');
        }
      }
    }
  }

  if (!empty($errors)) {
    return $errors;
  }
}

function civigiftaid_civicrm_giftAidEligible(
  &$isEligible,
  $contactId,
  $date,
  $contributionId
) {
  if (!CRM_Civigiftaid_Form_Admin::isGloballyEnabled()) {
    if($isEligible != 0){
     $isEligible =
       CRM_Civigiftaid_Utils_Contribution::getContribAmtForEnabledFinanceTypes($contributionId) != 0;
    }
  }
}

/**
 * Add navigation for civigiftaid under "Administer" menu
 *
 * @param $params associated array of navigation menus
 */
function civigiftaid_civicrm_navigationMenu(&$params) {
  $result = civicrm_api(
    'OptionValue',
    'getsingle',
    array('version' => 3, 'name' => 'basic_rate_tax')
  );

  if ($result['id']) {
    $ovId = $result['id'];
    $ogId = $result['option_group_id'];
  }

  // get the id of Administer Menu
  $administerMenuId = CRM_Core_DAO::getFieldValue(
    'CRM_Core_BAO_Navigation',
    'Administer',
    'id',
    'name'
  );

  // skip adding menu if there is no administer menu
  if ($administerMenuId) {
    // get the maximum key under administer menu
    $maxAdminMenuKey = max(array_keys($params[$administerMenuId]['child']));
    $nextAdminMenuKey = $maxAdminMenuKey + 1;
    $params[$administerMenuId]['child'][$nextAdminMenuKey] = array(
      'attributes' => array(
        'label'      => ts('CiviGiftAid'),
        'name'       => 'admin_giftaid',
        'permission' => NULL,
        'operator'   => NULL,
        'separator'  => 1,
        'parentID'   => $administerMenuId,
        'navID'      => $nextAdminMenuKey,
        'active'     => 1
      ),
      'child'      => array(
        1 => array(
          'attributes' => array(
            'label'      => ts('GiftAid Basic Rate Tax'),
            'name'       => 'giftaid_basic_rate_tax',
            'url'        => "civicrm/admin/options?action=update&id=$ovId&gid=$ogId&reset=1",
            'permission' => NULL,
            'operator'   => NULL,
            'separator'  => 0,
            'parentID'   => $nextAdminMenuKey,
            'navID'      => 2,
            'active'     => 1
          ),
          'child'      => NULL
        ),
        2 => array(
          'attributes' => array(
            'label'      => ts('Settings'),
            'name'       => 'settings',
            'url'        => "civicrm/admin/gift-aid",
            'permission' => NULL,
            'operator'   => NULL,
            'separator'  => 0,
            'parentID'   => $nextAdminMenuKey,
            'navID'      => 3,
            'active'     => 1
          ),
          'child'      => NULL
        ),
      )
    );
  }
}

/**
 * Function to get full address and postal code for a contact
 */
function _civigiftaid_civicrm_custom_get_address_and_postal_code(
  $contactId,
  $location_type_id = 1
) {
  if (empty($contactId)) {
    return;
  }

  $fullFormatedAddress = 'NULL';
  $postalCode = 'NULL';
  // get Address & Postal Code of the contact
  require_once 'api/api.php';
  require_once 'CRM/Utils/Address.php';
  $address = civicrm_api("Address", "get", array(
    'version'          => '3',
    'contact_id'       => $contactId,
    'location_type_id' => $location_type_id
  ));
  if ($address['count'] > 0) {
    if (!isset($address['id'])) { //check if the contact has more than one home address so use the first one
      $addressValue = array_shift(array_values($address['values']));
      $postalCode = $addressValue['postal_code'];
    }
    else {
      $addressValue = $address['values'][$address['id']];
      $postalCode = $address['values'][$address['id']]['postal_code'];
    }
    $fullFormatedAddress =
      _civigiftaid_civicrm_custom_get_address_and_postal_code_format_address($addressValue);

  }

  return array($fullFormatedAddress, $postalCode);
}

/**
 * Function to format the address , to avoid empty spaces or commas
 */
function _civigiftaid_civicrm_custom_get_address_and_postal_code_format_address(
  $contactAddress
) {
  if (!is_array($contactAddress)) {
    return 'NULL';
  }
  $tempAddressArray = array();
  if (isset($contactAddress['address_name'])
    AND $contactAddress['address_name']
  ) {
    $tempAddressArray[] = $contactAddress['address_name'];
  }
  if (isset($contactAddress['street_address'])
    AND $contactAddress['street_address']
  ) {
    $tempAddressArray[] = $contactAddress['street_address'];
  }
  if (isset($contactAddress['supplemental_address_1'])
    AND $contactAddress['supplemental_address_1']
  ) {
    $tempAddressArray[] = $contactAddress['supplemental_address_1'];
  }
  if (isset($contactAddress['supplemental_address_2'])
    AND $contactAddress['supplemental_address_2']
  ) {
    $tempAddressArray[] = $contactAddress['supplemental_address_2'];
  }
  if (isset($contactAddress['city']) AND $contactAddress['city']) {
    $tempAddressArray[] = $contactAddress['city'];
  }
  require_once 'CRM/Core/PseudoConstant.php';
  if (isset($contactAddress['state_province_id'])
    AND $contactAddress['state_province_id']
  ) {
    $tempAddressArray[] = CRM_Core_PseudoConstant::stateProvince(
      $contactAddress['state_province_id']
    );
  }

  return @implode(', ', $tempAddressArray);
}
