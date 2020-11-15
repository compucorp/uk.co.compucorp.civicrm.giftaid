<?php

require_once 'civigiftaid.civix.php';
use CRM_Civigiftaid_ExtensionUtil as E;

define('CIVICRM_GIFTAID_ADD_TASKID', 1435);
define('CIVICRM_GIFTAID_REMOVE_TASKID', 1436);

/**
 * Implementation of hook_civicrm_config
 */
function civigiftaid_civicrm_config(&$config) {
  _civigiftaid_civix_civicrm_config($config);

  if (isset(Civi::$statics[__FUNCTION__])) { return; }
  Civi::$statics[__FUNCTION__] = 1;

  // Symfony hook priorities - see https://docs.civicrm.org/dev/en/latest/hooks/usage/symfony/#priorities
  // Add listeners for CiviCRM hooks that might need altering by other scripts
  Civi::dispatcher()->addListener('hook_civicrm_post', 'CRM_Civigiftaid_SetContributionGiftAidEligibility::run');

  // Run before giftaidonline (-100)
  \Civi::dispatcher()->addListener('hook_civicrm_navigationMenu', 'civigiftaid_symfony_civicrm_navigationMenu', 0);

}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function civigiftaid_civicrm_xmlMenu(&$files) {
  _civigiftaid_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function civigiftaid_civicrm_install() {
  _civigiftaid_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_postInstall
 */
function civigiftaid_civicrm_postInstall() {
  _civigiftaid_civix_civicrm_postInstall();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function civigiftaid_civicrm_uninstall() {
  _civigiftaid_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function civigiftaid_civicrm_enable() {
  _civigiftaid_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function civigiftaid_civicrm_disable() {
  _civigiftaid_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op    string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function civigiftaid_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _civigiftaid_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function civigiftaid_civicrm_managed(&$entities) {
  _civigiftaid_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function civigiftaid_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _civigiftaid_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Add navigation for GiftAid under "Administer/CiviContribute" menu
 *
 * @param \Civi\Core\Event\GenericHookEvent $event
 * @param string $hookName
 *
 * @throws \CiviCRM_API3_Exception
 */
function civigiftaid_symfony_civicrm_navigationMenu($event, $hookName) {
  // Get optionvalue ID for basic rate tax setting
  $result = civicrm_api3('OptionValue', 'getsingle', ['name' => 'basic_rate_tax']);
  if ($result['id']) {
    $ovId = $result['id'];
    $ogId = $result['option_group_id'];
  }

  $item[] =  [
    'label' => E::ts('GiftAid'),
    'name'       => 'admin_giftaid',
    'url'        => NULL,
    'permission' => 'access CiviContribute',
    'operator'   => NULL,
    'separator'  => 1,
  ];
  _civigiftaid_civix_insert_navigation_menu($event->params, 'Administer/CiviContribute', $item[0]);

  $item[] = [
    'label' => E::ts('GiftAid Basic Rate Tax'),
    'name'       => 'giftaid_basic_rate_tax',
    'url'        => "civicrm/admin/options?action=update&id=$ovId&gid=$ogId&reset=1",
    'permission' => 'access CiviContribute',
    'operator'   => NULL,
    'separator'  => NULL,
  ];
  _civigiftaid_civix_insert_navigation_menu($event->params, 'Administer/CiviContribute/admin_giftaid', $item[1]);

  $item[] = [
    'label'      => E::ts('Settings'),
    'name'       => 'settings',
    'url'        => "civicrm/admin/setting/ukgiftaid",
    'permission' => 'access CiviContribute',
    'operator'   => NULL,
    'separator'  => NULL,
  ];
  _civigiftaid_civix_insert_navigation_menu($event->params, 'Administer/CiviContribute/admin_giftaid', $item[2]);

  _civigiftaid_civix_navigationMenu($event->params);
}

/**
 * @param $objectType
 * @param $tasks
 */
function civigiftaid_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contribution') {
    $tasks[CIVICRM_GIFTAID_ADD_TASKID] = [
      'title'  => E::ts('Add to Gift Aid batch'),
      'class'  => 'CRM_Civigiftaid_Form_Task_AddToBatch',
      'result' => FALSE
    ];
    $tasks[CIVICRM_GIFTAID_REMOVE_TASKID] = [
      'title'  => E::ts('Remove from Gift Aid batch'),
      'class'  => 'CRM_Civigiftaid_Form_Task_RemoveFromBatch',
      'result' => FALSE
    ];
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
      // addMarkup was added in CiviCRM 5.32 via https://github.com/civicrm/civicrm-core/commit/e564eac67d3fcfee8fad1760e928f6af81e13d22
      if (method_exists(\Civi::resources(), 'addMarkup')) {
        \Civi::resources()
          ->addMarkup('<div class="help">' . $helpText . '</div>', [
            'weight' => -1,
            'region' => 'page-body'
          ]);
      }

      \Civi::resources()->addScriptFile(E::LONG_NAME, 'js/ukgiftaid.settings.js');
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 *
 * @param string $formName
 * @param \CRM_Core_Form $form
 *
 * @throws \CiviCRM_API3_Exception
 */
function civigiftaid_civicrm_postProcess($formName, &$form) {
  // We save these values in the session so they can be used on the thankyou page of a contribute->confirm->thankyou.
  // Get and store the gift aid declaration value if set for use in civigiftaid_update_declaration_amount
  $session = CRM_Core_Session::singleton();
  if (!$session->get('uktaxpayer', E::LONG_NAME)) {
    $ukTaxPayerField = CRM_Civigiftaid_Utils::getCustomByName('eligible_for_gift_aid', 'Gift_Aid_Declaration');
    if (isset($form->_submitValues[$ukTaxPayerField])) {
      $session->set('uktaxpayer', $form->_submitValues[$ukTaxPayerField], E::LONG_NAME);
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

  // Copy the contact's home address to the declaration, when the declaration is created
  // Only for offline contribution
  $groupID = $form->getVar('_groupID');
  $contactID = $form->getVar('_entityId');
  $customGroupTableName = civicrm_api3('CustomGroup', 'getvalue', [
    'id' => $groupID,
    'return' => 'table_name',
  ]);

  if ($customGroupTableName == 'civicrm_value_gift_aid_declaration') {
    $declarationUpdateParams = ['entity_id' => $contactID];
    CRM_Civigiftaid_Declaration::updateDeclarationAddress($declarationUpdateParams);
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
 * @throws \CRM_Core_Exception
 * @throws \CRM_Extension_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civigiftaid_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  switch ($objectName) {
    case 'Batch':
      if ($op !== 'delete') {
        return;
      }
      // We have the batch ID. Check if it's a giftaid batch and delete related stuff
      try {
        $optionGroupID = civicrm_api3('OptionGroup', 'getvalue', [
          'name' => 'giftaid_batch_name',
          'return' => 'id'
        ]);
        $batchOptionValue = civicrm_api3('OptionValue', 'getsingle', [
          'option_group_id' => $optionGroupID,
          'value' => $objectId,
        ]);
        // Clear batch_name from contributions
        $updateSql = "UPDATE civicrm_value_gift_aid_submission SET batch_name = NULL WHERE batch_name=%1";
        $updateSqlParams = [
          1 => [$batchOptionValue['name'], 'String']
        ];
        CRM_Core_DAO::executeQuery($updateSql, $updateSqlParams);
        // Finally we delete the giftaid batch name option value.
        civicrm_api3('OptionValue', 'delete', [
          'id' => $batchOptionValue['id'],
        ]);
      } catch (Exception $e) {
        \Civi::log()
          ->error('Deleting Gift Aid Batch failed: ' . $e->getMessage());
      }
      break;

    case 'Contribution':
      if ($op == 'edit' || $op == 'create') {
        $callbackParams = [
          'entity' => $objectName,
          'op' => $op,
          'id' => $objectId,
          'details' => $objectRef,
        ];
        if (CRM_Core_Transaction::isActive()) {
          CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, 'civigiftaid_callback_civicrm_post_contribution', [$callbackParams]);
        }
        else {
          civigiftaid_callback_civicrm_post_contribution($callbackParams);
        }
      }
      break;
  }
}

/**
 * Callback for hook_civicrm_post_contribution
 *
 * @param array $params
 *
 * @throws \CRM_Core_Exception
 * @throws \CRM_Extension_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civigiftaid_callback_civicrm_post_contribution($params) {
  if (isset(Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount'])) {
    return;
  }
  Civi::$statics[E::LONG_NAME]['updatedDeclarationAmount'] = TRUE;
  CRM_Civigiftaid_Declaration::update($params['id']);
}

/**
 * Implementation of hook_civicrm_validateForm
 * Validate set of Gift Aid declaration records on Individual,
 * from multi-value custom field edit form:
 * - check end > start,
 * - check for overlaps between declarations.
 */
function civigiftaid_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  $errors = [];

  if ($formName == 'CRM_Contact_Form_CustomData') {
    $groupID = $form->getVar('_groupID');
    $contactID = $form->getVar('_entityId');
    $tableName = civicrm_api3('CustomGroup', 'getvalue', [
      'return' => 'table_name',
      'id' => $groupID,
    ]);

    if ($tableName == 'civicrm_value_gift_aid_declaration') {
      // Assemble multi-value field values from custom_X_Y into
      // array $declarations of sets of values as column_name => value
      $columnNames = civicrm_api3('CustomField', 'get', [
        'return' => ["column_name"],
        'custom_group_id' => $groupID,
      ]);
      $columnNames = CRM_Utils_Array::collect('column_name', CRM_Utils_Array::value('values', $columnNames));

      $declarations = [];
      foreach ($fields as $name => $value) {
        if (preg_match('/^custom_(\d+)_(-?\d+)$/', $name, $matches)) {
          $columnName = CRM_Utils_Array::value($matches[1], $columnNames);
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
        list($fullFormattedAddress, $postcode) = CRM_Civigiftaid_Declaration::getAddressAndPostalCode($contactID);
        if (empty($fullFormattedAddress)) {
          $errors[$values3['eligible_for_gift_aid']['name']] =
            E::ts('You will not be able to create a giftaid declaration because there is no primary address recorded for this contact. If you want to create a declaration, please add a primary address for this contact.');
        }
      }
    }
  }

  if (!empty($errors)) {
    return $errors;
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
  if ($fieldInfo['name'] === 'Batch_Name' && $fieldInfo['column_name'] === 'batch_name' && !empty($value)) {
    try {
      $optionGroupID = civicrm_api3('OptionGroup', 'getvalue', ['name' => 'giftaid_batch_name', 'return' => 'id']);
      $displayValue = civicrm_api3('OptionValue', 'getvalue', [
        'option_group_id' => $optionGroupID,
        'name' => $value,
        'return' => 'label',
      ]);
    }
    catch (Exception $e) {
      // Do nothing, we'll use the existing displayValue
      // This will fail for older batches which stored the label instead of the name for the batch_name field.
    }
  }
}
