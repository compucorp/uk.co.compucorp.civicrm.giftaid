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
  $contributions->execute()->indexBy('id');
  if (empty($contributions)) {
    return civicrm_api3_create_error('No contributions found or all have Eligible flag set!');
  }

  // Disable logging
  $loggingSchema = new \CRM_Logging_Schema();
  $loggingEnabled = $loggingSchema->isEnabled();
  if ($loggingEnabled) {
    $loggingSchema->disableLogging();
    Civi::settings()->set('logging', '0');
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
    if ($loggingEnabled) {
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
  $contributions->execute()->indexBy('id');
  if (empty($contributions)) {
    return civicrm_api3_create_error('No contributions found or none have Eligible flag set!');
  }

  // Disable logging
  $loggingSchema = new \CRM_Logging_Schema();
  $loggingEnabled = $loggingSchema->isEnabled();
  if ($loggingEnabled) {
    $loggingSchema->disableLogging();
    Civi::settings()->set('logging', '0');
  }

  try {
    foreach ($contributions as $contributionID => $contributionDetail) {
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
    if ($loggingEnabled) {
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
  $params['has_start_date']['title'] = 'Existing declaration has start date?';
  $params['has_start_date']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $params['has_start_date']['description'] = 'Choose whether to update declarations which have/have not a start_date set';
  $params['has_start_date']['api.default'] = FALSE;
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

  $startDate = '';
  if (isset($params['start_date'])) {
    $startDate = $params['start_date'];
  }
  if (isset($params['given_date'])) {
    $givenDate = $params['given_date'];
  }

  // Disable logging
  $loggingSchema = new \CRM_Logging_Schema();
  $loggingEnabled = $loggingSchema->isEnabled();
  if ($loggingEnabled) {
    $loggingSchema->disableLogging();
    Civi::settings()->set('logging', '0');
  }

  try {
    foreach ($contactIDs as $contactID) {
      list($addressDetails, $postCode) = CRM_Civigiftaid_Declaration::getAddressAndPostalCode($contactID);

      $currentDeclaration = CRM_Civigiftaid_Declaration::getDeclaration($contactID, '');
      if (empty($currentDeclaration)) {
        $updated['contactIDNoDeclaration'][] = $contactID;
        continue;
      }

      // Check if we should update declaration based api param if it has a start date
      if (empty($currentDeclaration['start_date'])) {
        if ($params['has_start_date']) {
          // Current declaration has no start date but we require one
          $updated['contactIDSkippedNoStartDate'][] = $contactID;
          continue;
        }
      }
      elseif (!empty($currentDeclaration['start_date'])) {
        if (!$params['has_start_date']) {
          // Current declaration has a start date and we are only updating ones that don't
          $updated['contactIDSkippedHasStartDate'][] = $contactID;
          continue;
        }
      }

      if (!empty($currentDeclaration['given_date'])) {
        $givenDate = $currentDeclaration['given_date'];
      }
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

      $declarationParams = [
        'id' => $currentDeclaration['id'],
        'entity_id' => $contactID,
        'start_date' => CRM_Utils_Date::isoToMysql($startDate),
        'given_date' => CRM_Utils_Date::isoToMysql($givenDate),
        'address' => $addressDetails,
        'post_code' => $postCode,
      ];
      if (empty($currentDeclaration['source']) && isset($params['source'])) {
        $declarationParams['source'] = $params['source'];
      }
      CRM_Civigiftaid_Declaration::insertDeclaration($declarationParams);

      $updated['contactIDs'][] = $contactID;
    }
  }
  finally {
    // Re-enable logging
    if ($loggingEnabled) {
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
