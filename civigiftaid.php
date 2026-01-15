<?php

require_once 'civigiftaid.civix.php';

use Civi\Api4\CustomGroup;
use CRM_Civigiftaid_ExtensionUtil as E;

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
  _civigiftaid_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 */
function civigiftaid_civicrm_enable() {
  _civigiftaid_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_check().
 *
 * @throws \CRM_Core_Exception
 */
function civigiftaid_civicrm_check(&$messages) {
  $checks = new CRM_Civigiftaid_Check($messages);
  $messages = $checks->checkRequirements();
}

/**
 * @param $objectType
 * @param $tasks
 */
function civigiftaid_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contribution') {
    $tasks += CRM_Civigiftaid_Controller_Task::tasks();
  }
}

/**
 * Intercept form functions
 */
function civigiftaid_civicrm_buildForm($formName, &$form) {
  switch ($formName) {
    case 'CRM_Admin_Form_Generic':
      if ($form->getSettingPageFilter() !== 'ukgiftaid') {
        return;
      }

      $helpText = E::ts(
        'Configure settings for GiftAid. You can specify which financial types are eligible. Also please read the <a href="%1" target="_blank">documentation</a>.',
        [
          1 => 'https://docs.civicrm.org/ukgiftaid'
        ]
      );
      \Civi::resources()
        ->addMarkup('<div class="help">' . $helpText . '</div>', [
          'weight' => -1,
          'region' => 'page-body'
        ]);

      \Civi::resources()->addScriptFile(E::LONG_NAME, 'js/ukgiftaid.settings.js');
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 *
 * @param string $formName
 * @param \CRM_Core_Form $form
 *
 * @throws \CRM_Core_Exception
 */
function civigiftaid_civicrm_postProcess($formName, &$form) {
  // We save these values in the session so they can be used on the thankyou page of a contribute->confirm->thankyou.
  // Get and store the gift aid declaration value if set for use in civigiftaid_update_declaration_amount
  $session = CRM_Core_Session::singleton();
  if (!$session->get('uktaxpayer', E::LONG_NAME)) {
    // let's get the id for custom field that is used for gift aid declaration
    $ukTaxPayerCustomFieldID = CRM_Civigiftaid_Utils::getCustomByName('eligible_for_gift_aid', 'gift_aid_declaration', FALSE);

    // submittedValue might come in with key `custom_23` or `custom_23_x` as it is a multi-value custom field.
    foreach ($form->getSubmittedValues() as $submittedKey => $submittedValue) {
      // match if custom field id exists in the submitted values
      if (CRM_Core_BAO_CustomField::getKeyID($submittedKey) == $ukTaxPayerCustomFieldID) {
        $ukTaxPayer = $submittedValue;
        break;
      }
    }
    if (isset($ukTaxPayer)) {
      $session->set('uktaxpayer', $ukTaxPayer, E::LONG_NAME);
    }
  }
  // Get the title of the submitted form
  if (!$session->get('postProcessTitle', E::LONG_NAME)) {
    if ($form->getTitle()) {
      $session->set('postProcessTitle', $form->getTitle(), E::LONG_NAME);
    }
  }

  if ($formName != 'CRM_Contact_Form_CustomData') {
    return;
  }

  /** @var \CRM_Contact_Form_CustomData $form */

  // Copy the contact's home address to the declaration, when the declaration is created
  // Only for offline contribution
  $groupID = $form->getVar('_groupID');
  $contactID = $form->getVar('_entityId');
  if (!is_numeric($contactID)) {
    \Civi::log('ukgiftaid')->error('ukgiftaid postProcess ' . $formName . ': Contact ID is not an integer!');
    return;
  }
  $customGroupTableName = CustomGroup::get(FALSE)
    ->addSelect('table_name')
    ->addWhere('id', '=', $groupID)
    ->execute()
    ->first()['table_name'];
  if (($customGroupTableName === 'civicrm_value_gift_aid_declaration') && !empty($contactID)) {
    CRM_Civigiftaid_Declaration::updateDeclarationAddress((int) $contactID);
  }
}

/**
 * If a contribution is created/edited create/edit the declaration.
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 *
 * @throws \CRM_Extension_Exception
 * @throws \CRM_Core_Exception
 */
function civigiftaid_civicrm_postCommit($op, $objectName, $objectId, &$objectRef) {
  switch ($objectName) {
    case 'Batch':
      if ($op !== 'delete') {
        return;
      }
      // We have the batch ID. Check if it's a giftaid batch and delete related stuff
      try {
        $batchOptionValue = \Civi\Api4\OptionValue::get(FALSE)
          ->addSelect('id', 'name')
          ->addWhere('option_group_id:name', '=', 'giftaid_batch_name')
          ->addWhere('value', '=', $objectId)
          ->execute()
          ->first();
        // Clear batch_name from contributions
        $updateSql = "UPDATE civicrm_value_gift_aid_submission SET batch_name = NULL WHERE batch_name=%1";
        $updateSqlParams = [
          1 => [$batchOptionValue['name'], 'String']
        ];
        CRM_Core_DAO::executeQuery($updateSql, $updateSqlParams);
        // Finally we delete the giftaid batch name option value.
        \Civi\Api4\OptionValue::delete(FALSE)
          ->addWhere('id', '=', $batchOptionValue['id'])
          ->execute();
      } catch (Exception $e) {
        \Civi::log()->error('Deleting Gift Aid Batch failed: ' . $e->getMessage());
      }
      break;

    case 'Contribution':
      if ($op === 'edit' || $op === 'create') {
        if (!_civigiftaid_drupal8_postCommitUpdateDeclaration($objectName, $objectId)) {
          if (isset(Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount'])) {
            return;
          }
          Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount'] = TRUE;
          CRM_Civigiftaid_Declaration::update($objectId);
        }
      }
      break;

    case 'Address':
      // We only need this for Drupal8 Webform because Contribution is created before Address
      // so need both to make sure we get latest address
      if ($op === 'edit' || $op === 'create') {
        _civigiftaid_drupal8_postCommitUpdateDeclaration($objectName, $objectId);
      }
  }
}

/**
 * Update necessary information after a Drupal 8 webform submission.
 *
 * @param string $entity The entity type being updated (Address or Contribution)
 * @param int $objectId The ID of the object being updated
 *
 * @return bool True if the update was successful, false otherwise
 *
 * @throws \CRM_Core_Exception
 */
function _civigiftaid_drupal8_postCommitUpdateDeclaration(string $entity, int $objectId): bool {
  if (!_civigiftaid_is_drupal8_webform()) {
    return FALSE;
  }
  switch ($entity) {
    case 'Address':
      Civi::$statics[E::LONG_NAME]['d8webform_address_id'] = $objectId;
      break;

    case 'Contribution':
      Civi::$statics[E::LONG_NAME]['d8webform_contribution_id'] = $objectId;
      break;
  }
  $key = '';
  if (!empty(Civi::$statics[E::LONG_NAME]['d8webform_contribution_id'])) {
    $key = Civi::$statics[E::LONG_NAME]['d8webform_contribution_id'];

    if (!empty(Civi::$statics[E::LONG_NAME]['d8webform_address_id'])) {
      $key = $key . '_' . Civi::$statics[E::LONG_NAME]['d8webform_address_id'];
    }
  }

  // Update the Gift Aid declaration if a contribution ID exists.
  //
  // This handles both scenarios:
  // - If there are no changes in the address submitted by webform, the declaration is still updated
  //   based on existing address of contact.
  // - If the address has changed, the declaration gets updated with the changes.
  //
  // In both cases, we prevent duplicate updates by comparing against the last updated key.
  if ($key) {
    _civigiftaid_drupal8_webform_uktaxpayer();
    if (isset(Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount']) &&
      (Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount'] == $key)
    ) {
      return TRUE;
    }
    Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount'] = $key;
    CRM_Civigiftaid_Declaration::update(Civi::$statics[E::LONG_NAME]['d8webform_contribution_id']);
  }

  return TRUE;
}

/**
 * Check if we are called from drupal8+ webform
 *
 * @return bool
 * @throws \CRM_Core_Exception
 */
function _civigiftaid_is_drupal8_webform(): bool {
  // Only retrieve value from webform if Drupal8+
  if (\CRM_Core_Config::singleton()->userFramework !== 'Drupal8') {
    return FALSE;
  }
  /*
   * For a correctly configured Drupal8+ webform we should see something like this in $_REQUEST:
   * [civicrm_1_contact_1_cg9_custom_32] => Array
       (
         [1] => 1
       )
     [form_id] => webform_submission_mjwtest_make_a_donation_add_form
  */

  // If form_id not set we're not processing a webform submission
  $formID = CRM_Utils_Request::retrieveValue('form_id', 'String', NULL, FALSE, 'REQUEST');
  if (empty($formID)) {
    return FALSE;
  }
  \Civi::log('ukgiftaid')->info('Webform ' . $formID . ' was submitted');
  return TRUE;
}

/**
 * Special handling for Drupal8+ webform as we don't trigger form_postProcess and are triggered directly from postCommit
 * Currently sets the session var uktaxpayer which is read later in CRM_Civigiftaid_Declaration::update
 *
 * @return void
 * @throws \CRM_Core_Exception
 */
function _civigiftaid_drupal8_webform_uktaxpayer() {
  if (!_civigiftaid_is_drupal8_webform()) {
    return;
  }
  // We save these values in the session so they can be used on the thankyou page of a contribute->confirm->thankyou.
  // Get and store the gift aid declaration value if set for use in civigiftaid_update_declaration_amount
  $session = CRM_Core_Session::singleton();
  // clear any old existing session var.
  $session->set('uktaxpayer', NULL, E::LONG_NAME);
  $sessionuktaxpayer = $session->get('uktaxpayer', E::LONG_NAME);

  if (!$session->get('uktaxpayer', E::LONG_NAME)) {
    // let's get the id for custom field that is used for gift aid declaration
    $ukTaxPayerCustomFieldName = CRM_Civigiftaid_Utils::getCustomByName('eligible_for_gift_aid', 'gift_aid', TRUE);
    /*
 * For a correctly configured Drupal8+ webform we should see something like this in $_REQUEST:
 * [civicrm_1_contact_1_cg9_custom_32] => Array
     (
       [1] => 1
     )
   [form_id] => webform_submission_mjwtest_make_a_donation_add_form
*/
    $requestKeys = array_keys($_REQUEST);
    foreach ($requestKeys as $key) {
      if (str_ends_with($key, $ukTaxPayerCustomFieldName)) {
        $ukTaxPayerFormKey = $key;
        break;
      }
    }
    if (isset($ukTaxPayerFormKey)) {
      $ukTaxPayer = (int) CRM_Utils_Type::validate($_REQUEST[$ukTaxPayerFormKey], 'Int');
      $session->set('uktaxpayer', $ukTaxPayer, E::LONG_NAME);
      \Civi::log()->info('ukTaxPayer set to ' . $ukTaxPayer);
    }
  }

}

/**
 * Implementation of hook_civicrm_validateForm
 * Validate set of Gift Aid declaration records on Individual,
 * from multi-value custom field edit form:
 * - check end > start,
 * - check for overlaps between declarations.
 *
 * @param string $formName
 * @param array $fields
 * @param array $files
 * @param CRM_Core_Form $form
 * @param array $errors
 *
 * @throws \CRM_Core_Exception
 */
function civigiftaid_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName === 'CRM_Contact_Form_CustomData') {
    $groupID = $form->getVar('_groupID');
    $contactID = $form->getVar('_entityId');
    if (!is_numeric($contactID)) {
      \Civi::log('ukgiftaid')->error('ukgiftaid validateForm ' . $formName . ': Contact ID is not an integer!');
      return;
    }
    $tableName = CustomGroup::get(FALSE)
      ->addSelect('table_name')
      ->addWhere('id', '=', $groupID)
      ->execute()
      ->first()['table_name'];

    if ($tableName === 'civicrm_value_gift_aid_declaration') {
      // Assemble multi-value field values from custom_X_Y into
      // array $declarations of sets of values as column_name => value
      $columnNames = \Civi\Api4\CustomField::get(FALSE)
        ->addSelect('column_name')
        ->addWhere('custom_group_id', '=', $groupID)
        ->execute()
        ->indexBy('id')
        ->column('column_name');

      $declarations = [];
      foreach ($fields as $name => $value) {
        if (preg_match('/^custom_(\d+)_(-?\d+)$/', $name, $matches)) {
          $columnName = $columnNames[$matches[1]] ?? NULL;
          if ($columnName) {
            $declarations[$matches[2]][$columnName]['value'] = $value;
            $declarations[$matches[2]][$columnName]['name'] = $name;
          }
        }
      }

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

        foreach ($declarations as $id2 => $values2) {
          if ($id2 <= $id1) {
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
      foreach ($declarations as $values3) {
        [$fullFormattedAddress, $postcode] = CRM_Civigiftaid_Declaration::getAddressAndPostalCode($contactID);
        if (empty($fullFormattedAddress)) {
          $errors[$values3['eligible_for_gift_aid']['name']] =
            E::ts('You will not be able to create a giftaid declaration because there is no primary address recorded for this contact. If you want to create a declaration, please add a primary address for this contact.');
        }
      }
    }
  }
}

/**
 * This hook is called to alter Custom field value before its displayed.
 *
 * @param string $displayValue
 * @param mixed $value
 * @param int $entityId
 * @param array $fieldInfo
 */
function civigiftaid_civicrm_alterCustomFieldDisplayValue(&$displayValue, $value, $entityId, $fieldInfo) {
  // Gift Aid batch name is stored as "name" but we want to display "label".
  if ($fieldInfo['name'] === 'batch_name' && $fieldInfo['column_name'] === 'batch_name' && !empty($value)) {
    try {
      if (is_array($value)) {
        $displayValues = \Civi\Api4\OptionValue::get(FALSE)
          ->addSelect('label')
          ->addWhere('option_group_id:name', '=', 'giftaid_batch_name')
          ->addWhere('name', 'IN', $value)
          ->execute()
          ->column('label');
        $displayValue = implode(', ', $displayValues);
      }
      else {
        $displayValue = \Civi\Api4\OptionValue::get(FALSE)
          ->addSelect('label')
          ->addWhere('option_group_id:name', '=', 'giftaid_batch_name')
          ->addWhere('name', '=', $value)
          ->execute()
          ->first()['label'];
      }
    }
    catch (Exception $e) {
      // Do nothing, we'll use the existing displayValue
      // This will fail for older batches which stored the label instead of the name for the batch_name field.
    }
  }
}

function civigiftaid_civicrm_customFieldOptions($fieldID, &$options, $detailedFormat = false ) {
  $customField = \Civi\Api4\CustomField::get(FALSE)
    ->addSelect('custom_group_id:name', 'name')
    ->addWhere('id', '=', $fieldID)
    ->addWhere('custom_group_id:name', '=', 'gift_aid')
    ->execute()
    ->first();

  if (empty($customField) || $customField['name'] != 'batch_name') {
    return ;
  }

  //transform options from id => label to name => label
  if (!$detailedFormat) {
    $optionValues = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('name', 'id', 'value', 'label')
      ->addWhere('option_group_id:name', '=', 'giftaid_batch_name')
      ->execute()
      ->getArrayCopy();

    $options = array_combine(array_column($optionValues, 'name'), array_column($optionValues, 'label'));
  }
}
