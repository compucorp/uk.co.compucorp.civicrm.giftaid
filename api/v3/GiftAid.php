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
 * @param array $params
 * @see https://lab.civicrm.org/extensions/ukgiftaid/-/issues/20
 */
function _civicrm_api3_gift_aid_getcontributioneligibility_spec(&$params) {
  $params['contribution_id']['title'] = 'Contribution ID';
  $params['contribution_id']['description'] = 'Optional contribution ID to update';
  $params['contribution_id']['type'] = CRM_Utils_Type::T_INT;
  $params['contribution_id']['api.required'] = FALSE;
}

/**
 * @param array $params
 *
 * @return array
 * @throws \CRM_Extension_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_gift_aid_getcontributioneligibility($params) {
  $contribution = civicrm_api3('Contribution', 'getsingle', [
    'id' => $params['contribution_id'],
  ]);

  $receiveDate = (new DateTime($contribution['receive_date']))->format('YmdHis');

  $declaration = CRM_Civigiftaid_Declaration::getDeclaration(
    $contribution['contact_id'],
    $receiveDate
  );

  return civicrm_api3_create_success($declaration, $params, 'GiftAid', 'getcontributioneligibility');
}

/**
 * @param array $params
 */
function _civicrm_api3_gift_aid_updateeligiblecontributions_spec(&$params) {
  $params['contribution_id']['title'] = 'Contribution ID';
  $params['contribution_id']['description'] = 'Optional contribution ID to update';
  $params['contribution_id']['type'] = CRM_Utils_Type::T_INT;
  $params['contribution_id']['api.required'] = FALSE;
  $params['recalculate_amount']['title'] = 'Recalculate amounts';
  $params['recalculate_amount']['description'] = 'Recalculate Gift Aid amounts even if they already have the eligible flag set. This will not touch contributions already in a batch.';
  $params['recalculate_amount']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['no_log']['title'] = 'Disable logging for these changes?';
  $params['no_log']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['no_log']['description'] = 'Choose whether to disable logging when doing these updates';
  $params['no_log']['api.default'] = FALSE;
}

/**
 * @param array $params
 *
 * @return array
 * @throws \CRM_Extension_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_gift_aid_updateeligiblecontributions($params) {
  $params['options']['limit'] = $params['options']['limit'] ?? 0;

  $contributions = \Civi\Api4\Contribution::get(FALSE)
    ->addSelect('*', 'custom.*');

  if (empty($params['recalculate_amount'])) {
    // Only retrieve contributions that do not have eligibility set
    $contributions->addWhere('gift_aid.eligible_for_gift_aid', 'IS NULL');
  }
  else {
    // Retrieve all contributions that are eligible for gift aid
    $contributions->addWhere('gift_aid.eligible_for_gift_aid', '=', 1);
  }
  if (!empty($params['contribution_id'])) {
    $contributions->addWhere('id', '=', $params['contribution_id']);
  }
  if (!empty($params['options']['limit'])) {
    $contributions->setLimit($params['options']['limit']);
  }
  if (!empty($params['options']['offset'])) {
    $contributions->setLimit($params['options']['offset']);
  }
  $contributions = $contributions->execute()->indexBy('id');
  if (empty($contributions)) {
    return civicrm_api3_create_error('No contributions found or all have Eligible flag set!');
  }

  // Disable logging
  if ($params['no_log']) {
    $loggingSchema = new \CRM_Logging_Schema();
    $loggingEnabled = $loggingSchema->isEnabled();
    if ($loggingEnabled) {
      $loggingSchema->disableLogging();
      Civi::settings()->set('logging', '0');
    }
  }


  try {
    foreach ($contributions as $contributionID => $contributionDetail) {
      // Check batch name here because it may be NULL or empty string and we can't check that using API3.
      if (!empty($contributionDetail['gift_aid.batch_name'])) {
        // Contribution is part of a batch so we must not change/process it.
        continue;
      }
      CRM_Civigiftaid_SetContributionGiftAidEligibility::setGiftAidEligibilityStatus($contributionID);
      $updatedIDs[] = $contributionID;
    }
  }
  finally {
    // Re-enable logging
    if ($params['no_log'] && $loggingEnabled) {
      $loggingSchema->enableLogging();
      Civi::settings()->set('logging', '1');
    }
  }

  return civicrm_api3_create_success($updatedIDs ?? [], $params, 'GiftAid', 'updateeligiblecontributions');
}

/**
 * @param array $params
 */
function _civicrm_api3_gift_aid_recalculatecontributionamounts_spec(&$params) {
  $params['contribution_id']['title'] = 'Contribution ID';
  $params['contribution_id']['description'] = 'Optional contribution ID to update';
  $params['contribution_id']['type'] = CRM_Utils_Type::T_INT;
  $params['contribution_id']['api.required'] = FALSE;
  $params['batch_name']['title'] = 'The batch name (optional)';
  $params['batch_name']['description'] = 'If specified, amounts will be recalculated for contributions with this batch name only. Otherwise only contributions with no batch name will be recalculated.';
  $params['batch_name']['type'] = CRM_Utils_Type::T_STRING;
  $params['no_log']['title'] = 'Disable logging for these changes?';
  $params['no_log']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['no_log']['description'] = 'Choose whether to disable logging when doing these updates';
  $params['no_log']['api.default'] = FALSE;
}

function civicrm_api3_gift_aid_recalculatecontributionamounts($params) {
  $params['options']['limit'] = $params['options']['limit'] ?? 0;

  $contributions = \Civi\Api4\Contribution::get(FALSE)
    ->addSelect('*', 'custom.*');
  if (!empty($params['options']['limit'])) {
    $contributions->setLimit($params['options']['limit']);
  }
  if (!empty($params['options']['offset'])) {
    $contributions->setLimit($params['options']['offset']);
  }

  // Retrieve all contributions that are eligible for gift aid
  $contributions->addWhere('gift_aid.eligible_for_gift_aid', '=', 1);

  if (!empty($params['contribution_id'])) {
    $contributions->addWhere('id', '=', $params['contribution_id']);
  }
  $contributionsResult = $contributions->execute()->indexBy('id');
  if ($contributionsResult->count() < 1) {
    return civicrm_api3_create_error('No contributions found or none have Eligible flag set!');
  }

  // Disable logging
  if ($params['no_log']) {
    $loggingSchema = new \CRM_Logging_Schema();
    $loggingEnabled = $loggingSchema->isEnabled();
    if ($loggingEnabled) {
      $loggingSchema->disableLogging();
      Civi::settings()->set('logging', '0');
    }
  }

  try {
    foreach ($contributionsResult as $contributionID => $contributionDetail) {
      // Check batch name here because it may be NULL or empty string and we can't check that using API3.
      if (!empty($params['batch_name']) && ($params['batch_name'] !== $contributionDetail['gift_aid.batch_name'])) {
        // We specified a specific batch name to process and this contribution is not part of that batch
        continue;
      }
      elseif (empty($params['batch_name']) && !empty($contributionDetail['gift_aid.batch_name'])) {
        // Contribution is part of a batch so we must not change/process it.
        continue;
      }
      CRM_Civigiftaid_Utils_Contribution::updateGiftAidFields($contributionID,
        $contributionDetail['gift_aid.eligible_for_gift_aid'],
        $contributionDetail['gift_aid.batch_name'],
        TRUE
      );
      $updatedIDs[] = $contributionID;
    }
  }
  finally {
    // Re-enable logging
    if ($params['no_log'] && $loggingEnabled) {
      $loggingSchema->enableLogging();
      Civi::settings()->set('logging', '1');
    }
  }

  return civicrm_api3_create_success($updatedIDs ?? [], $params, 'GiftAid', 'recalculatecontributionamounts');
}

function _civicrm_api3_gift_aid_updatedeclarations_spec(&$params) {
  $params['contact_id']['title'] = 'Contact ID';
  $params['start_date']['title'] = 'Declaration start date';
  $params['start_date']['description'] = 'Start date - if not set defaults to existing date or contact created_date';
  $params['given_date']['title'] = 'Declaration given date';
  $params['given_date']['description'] = 'Given date - if not set defaults to existing date or start_date or contact created_date';
  $params['source']['title'] = 'Source text';
  $params['source']['description'] = 'Source text if existing declaration source text is empty.';
  $params['has_start_date']['title'] = 'Declaration has start date?';
  $params['has_start_date']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['has_start_date']['description'] = 'Only update declarations that have a start date set.';
  $params['has_start_date']['api.default'] = FALSE;
  $params['no_start_date']['title'] = 'Declaration has no start date?';
  $params['no_start_date']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['no_start_date']['description'] = 'Only update declarations that do not have a start date set.';
  $params['no_start_date']['api.default'] = FALSE;
  $params['no_log']['title'] = 'Disable logging for these changes?';
  $params['no_log']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['no_log']['description'] = 'Choose whether to disable logging when doing these updates';
  $params['no_log']['api.default'] = FALSE;
  $params['replace_address']['title'] = 'Replace the declaration address with the current address?';
  $params['replace_address']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['replace_address']['description'] = 'By default, only missing addresses are updated. Use this to update existing addresses as well';
  $params['replace_address']['api.default'] = FALSE;
  $params['reformat_address']['title'] = 'Reformat address on declaration?';
  $params['reformat_address']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['reformat_address']['description'] = 'Choose whether to reformat the address to current expected format';
  $params['reformat_address']['api.default'] = FALSE;
  $params['all']['title'] = "Apply updates to all of a contact's declarations?";
  $params['all']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['all']['description'] = "By default, changes are only applied to a contact's first declaration.  If true, update all declarations";
  $params['all']['api.default'] = FALSE;
}

function civicrm_api3_gift_aid_updatedeclarations($params) {
  if (empty($params['contact_id'])) {
    $contacts = civicrm_api3('Contact', 'get', [
      'return' => ["id"],
      'contact_type' => "Individual",
      'options' => $params['options'] ?? [],
      'is_deleted' => 0,
    ])['values'];
    $contactIDs = array_column($contacts, 'id');
  }
  elseif (isset($params['contact_id']['IN'])) {
    $contactIDs = $params['contact_id']['IN'];
  }
  else {
    $contactIDs[] = $params['contact_id'];
  }

  // Disable logging
  if ($params['no_log']) {
    $loggingSchema = new \CRM_Logging_Schema();
    $loggingEnabled = $loggingSchema->isEnabled();
    if ($loggingEnabled) {
      $loggingSchema->disableLogging();
      Civi::settings()->set('logging', '0');
    }
  }

  try {
    foreach ($contactIDs as $contactID) {
      if ($params['all']) {
        $declarations = CRM_Civigiftaid_Declaration::getAllDeclarations($contactID);
        if (empty($declarations)) {
          $updated['contactIDNoDeclaration'][] = $contactID;
          continue;
        }
      }
      else {
        $declaration = CRM_Civigiftaid_Declaration::getDeclaration($contactID, '');
        if (empty($declaration)) {
          $updated['contactIDNoDeclaration'][] = $contactID;
          continue;
        }
        $declarations = [$declaration];
      }

      foreach ($declarations as $currentDeclaration) {
        // Check if we should update declaration based on start date api params
        if (empty($currentDeclaration['start_date'])) {
          if ($params['has_start_date']) {
            // Current declaration has no start date but we require one
            $updated['contactIDSkippedNoStartDate'][] = $contactID;
            continue;
          }
        }
        else {  // start_date not empty
          if ($params['no_start_date']) {
            // Current declaration has a start date and we are only updating ones that don't
            $updated['contactIDSkippedHasStartDate'][] = $contactID;
            continue;
          }
        }

        // Initialise from api params
        $startDate = $givenDate = '';
        if (isset($params['start_date'])) {
          $startDate = $params['start_date'];
        }
        if (isset($params['given_date'])) {
          $givenDate = $params['given_date'];
        }

        // Keep the given_date if already set
        if (!empty($currentDeclaration['given_date'])) {
          $givenDate = $currentDeclaration['given_date'];
        }

        // Keep the start_date if already set
        if (!empty($currentDeclaration['start_date'])) {
          $startDate = $currentDeclaration['start_date'];
        }
        elseif (empty($currentDeclaration['start_date']) && empty($startDate)) {
          // Set start date to contact create date
          $startDate = civicrm_api3('Contact', 'getvalue', [
            'id' => $contactID,
            'return' => 'created_date'
          ]);
        }
        if (empty($givenDate)) {
          $givenDate = $startDate;
        }

        // Handle address
        $address = $currentDeclaration['address'] ?? '';
        $postCode = $currentDeclaration['post_code'] ?? '';
        // Check if we should reformat the address
        if ($params['reformat_address']) {
          list($address, $postCode) = CRM_Civigiftaid_Declaration::reformatAddress($address, $postCode);
        }

        // Set to current address if address is missing or we are replacing all addresses
        if (empty($address) || $params['replace_address']) {
          list($address, $postCode) = CRM_Civigiftaid_Declaration::getAddressAndPostalCode($contactID);
        }

        $declarationParams = [
          'id' => $currentDeclaration['id'],
          'entity_id' => $contactID,
          'start_date' => $startDate,
          'given_date' => $givenDate,
          'address' => $address,
          'post_code' => $postCode,
        ];
        if (empty($currentDeclaration['source']) && isset($params['source'])) {
          $declarationParams['source'] = $params['source'];
        }

        if (!empty(array_diff_assoc($declarationParams, $currentDeclaration))) {
          $declarationParams['start_date'] = CRM_Utils_Date::isoToMysql($declarationParams['start_date']);
          $declarationParams['given_date'] = CRM_Utils_Date::isoToMysql($declarationParams['given_date']);
          CRM_Civigiftaid_Declaration::insertDeclaration($declarationParams);
          $updated['contactIDs'][] = $contactID;
        }
        else {
          $updated['contactIDNoChange'][] = $contactID;
        }
      }
    }
  }
  finally {
    // Re-enable logging
    if ($params['no_log'] && $loggingEnabled) {
      $loggingSchema->enableLogging();
      Civi::settings()->set('logging', '1');
    }
  }

  return civicrm_api3_create_success($updated, $params, 'GiftAid', 'Updatedeclarations');
}

function civicrm_api3_gift_aid_ensuredatastructures($params) {
  $upgrader = new CRM_Civigiftaid_Upgrader(E::LONG_NAME, E::path());
  $upgrader->ensureDataStructures();
  return civicrm_api3_create_success([], $params, 'GiftAid', 'Ensuredatastructures');
}
