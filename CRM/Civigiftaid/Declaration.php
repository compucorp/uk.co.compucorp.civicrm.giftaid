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
 * Class CRM_Civigiftaid_Declaration
 */
class CRM_Civigiftaid_Declaration {

  public const DECLARATION_IS_YES = 1;
  public const DECLARATION_IS_PAST_4_YEARS = 3;
  public const DECLARATION_IS_NO = 0;

  /**
   * @param int $contributionID
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function update($contributionID) {
    $contributionCustomGiftAidEligibleFieldName = CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid');
    $contributionCustomGiftAidBatchNameFieldName = CRM_Civigiftaid_Utils::getCustomByName('Batch_Name', 'Gift_Aid');

    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'id' => $contributionID,
      'return' => ['contact_id',
        'receive_date',
        $contributionCustomGiftAidEligibleFieldName,
        $contributionCustomGiftAidBatchNameFieldName,
        'contribution_recur_id'
      ]
    ]);

    // If declaration updated via contribution page etc. it will have been set in postProcess
    $session = CRM_Core_Session::singleton();
    if ($session->get('uktaxpayer', E::LONG_NAME)) {
      $contactGiftAidEligibleStatus = $session->get('uktaxpayer', E::LONG_NAME);
    }

    // Get the gift aid eligible status
    // If it's not a valid number don't do any further processing
    if (!isset($contactGiftAidEligibleStatus)) {
      return;
    }

    list($addressDetails, $postCode) = self::getAddressAndPostalCode($contribution['contact_id']);

    $declarationParams = [
      'entity_id' => $contribution['contact_id'],
      'start_date' => CRM_Utils_Date::isoToMysql($contribution['receive_date']),
      'given_date' => CRM_Utils_Date::isoToMysql($contribution['receive_date']),
      'address' => $addressDetails,
      'post_code' => $postCode,
      'eligible_for_gift_aid' => $contactGiftAidEligibleStatus,
    ];
    self::setDeclaration($declarationParams);
  }

  /**
   * Function to get full address and postal code for a contact
   *
   * @param int $contactID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function getAddressAndPostalCode($contactID) {
    if (empty($contactID)) {
      // @fixme Maybe this should throw an exception as it's unclear what happens if we don't have a contact ID here
      return ['', ''];
    }

    $fullFormattedAddress = $postalCode = '';

    // get Address & Postal Code of the contact
    $address = civicrm_api3('Address', 'get', [
      'contact_id' => $contactID,
      'is_primary' => 1,
    ])['values'];
    if (!empty($address)) {
      $address = reset($address);
      $postalCode = $address['postal_code'] ?? '';
      $fullFormattedAddress = self::getFormattedAddress($address);
    }

    return [$fullFormattedAddress, $postalCode];
  }

  /**
   * Function to format the address, to avoid empty spaces or commas
   *
   * @param array $contactAddress
   *
   * @return string
   */
  private static function getFormattedAddress($contactAddress) {
    if (!is_array($contactAddress)) {
      return 'NULL';
    }
    $tempAddressArray = [];
    if (!empty($contactAddress['address_name'])) {
      $tempAddressArray[] = $contactAddress['address_name'];
    }
    if (!empty($contactAddress['street_address'])) {
      $tempAddressArray[] = $contactAddress['street_address'];
    }
    if (!empty($contactAddress['supplemental_address_1'])) {
      $tempAddressArray[] = $contactAddress['supplemental_address_1'];
    }
    if (!empty($contactAddress['supplemental_address_2'])) {
      $tempAddressArray[] = $contactAddress['supplemental_address_2'];
    }
    if (!empty($contactAddress['city'])) {
      $tempAddressArray[] = $contactAddress['city'];
    }
    if (!empty($contactAddress['state_province_id'])) {
      $tempAddressArray[] = CRM_Core_PseudoConstant::stateProvince($contactAddress['state_province_id']);
    }

    return implode(', ', $tempAddressArray);
  }

  /**
   * Get all gift aid declarations made by a contact.
   *
   * @param int $contactID
   *
   * @return array
   */
  public static function getAllDeclarations($contactID) {
    if (!isset(Civi::$statics[__CLASS__][$contactID]['declarations'])) {
      $sql = "SELECT id, entity_id, eligible_for_gift_aid, start_date, given_date, end_date, reason_ended, source, notes
              FROM civicrm_value_gift_aid_declaration
              WHERE  entity_id = %1";
      $sqlParams[1] = [$contactID, 'Integer'];

      $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
      Civi::$statics[__CLASS__][$contactID]['declarations'] = $dao->fetchAll();
    }

    return Civi::$statics[__CLASS__][$contactID]['declarations'];
  }

  /**
   * Update a declaration record.
   *
   * @param array $params
   */
  private static function updateDeclaration($params) {
    self::insertDeclaration($params);
  }

  /**
   * Get the (table) ID and the eligibility of the last added (may not be most recent) gift aid declaration for a contact
   *
   * When submitting a profile with a (Individual) eligible_for_gift_aid field we get a partial declaration created
   * but we want to create a full declaration
   *
   * @param int $contactID
   *
   * @return array
   */
  private static function getPartialDeclaration($contactID): array {
    $sql = "SELECT id as id, start_date, eligible_for_gift_aid
              FROM civicrm_value_gift_aid_declaration
              WHERE  entity_id = %1 ORDER BY id DESC LIMIT 1";
    $sqlParams = [
      1 => [$contactID, 'Integer']
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    $dao->fetch();
    if (!empty($dao->id) && empty($dao->start_date)) {
      return [
        'id' => (int) $dao->id,
        'eligible_for_gift_aid' => (int) $dao->eligible_for_gift_aid,
      ];
    }

    return [];
  }

  /**
   * Insert a declaration record
   *
   * @param array $params
   */
  private static function insertDeclaration($params) {
    $cols = [
      'entity_id' => 'Integer',
      'eligible_for_gift_aid' => 'Integer',
      'address' => 'String',
      'post_code' => 'String',
      'start_date' => 'Timestamp',
      'given_date' => 'Timestamp',
      'end_date' => 'Timestamp',
      'reason_ended' => 'String',
      'source' => 'String',
      'notes' => 'String',
    ];

    if (CRM_Utils_Array::value('id', $params)) {
      // We will update an existing record.
      $keyVals = [];
      $queryParams[1] = [$params['id'], 'Integer'];
      $count = 2;
      foreach ($cols as $colName => $colType) {
        if (isset($params[$colName])) {
          $keyVals[] = "{$colName}=%{$count}";
          $queryParams[$count] = [
            CRM_Utils_Array::value($colName, $params, ''),
            $colType
          ];
        }
        $count++;
      }
      $keyValsString = implode(',', $keyVals);
      $query = "UPDATE civicrm_value_gift_aid_declaration SET {$keyValsString} WHERE id=%1";
    }
    else {
      // We will create a new record.
      if (empty($params['given_date'])) {
        // We should store a given date. Default to the start_date, but if that's not
        // given, assume now.
        $params['given_date'] = $params['start_date'] ?? date('Y-m-d H:i:s');
      }
      $count = 1;
      foreach ($cols as $colName => $colType) {
        $insertVals[$colName] = CRM_Utils_Array::value($colName, $params, '');
        $values[] = "%{$count}";
        $queryParams[$count] = [
          CRM_Utils_Array::value($colName, $params, ''),
          $colType
        ];
        $count++;
      }

      $query = "INSERT INTO civicrm_value_gift_aid_declaration (" . implode(',', array_keys($insertVals)) . ") VALUES (" . implode(',', $values) . ")";
    }

    // Insert or update.
    CRM_Core_DAO::executeQuery($query, $queryParams);
  }

  /**
   * Delete all declarations that are missing a postcode and a start_date for
   * the given contact.
   *
   * @param int $contactID
   */
  public static function deletePartialDeclaration($contactID) {
    $sql = "DELETE FROM civicrm_value_gift_aid_declaration
              WHERE entity_id = %1 AND post_code IS NULL AND start_date IS NULL";
    $sqlParams = [
      1 => [$contactID, 'Integer'],
    ];

    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }

  /**
   * Get all contacts that have a giftaid declaration
   *
   * @return array
   */
  public static function getContactsWithDeclarations() {
    $contactsWithDeclarations = [];
    $sql = "
        SELECT   entity_id
        FROM     civicrm_value_gift_aid_declaration
        GROUP BY entity_id";

    $contactsWithDeclarations = CRM_Core_DAO::executeQuery($sql)
      ->fetchMap('entity_id', 'entity_id');

    return $contactsWithDeclarations;
  }

  /**
   * Create / update Gift Aid declaration records on Individual when
   * "Eligible for Gift Aid" field on Contribution is updated.
   *
   * @param array  $newParams    - fields to store in declaration:
   *               - entity_id:  the Individual for whom we will create/update declaration
   *               - eligible_for_gift_aid: 3=Yes+past 4 years,1=Yes,0=No
   *               - start_date: start date of declaration (in ISO date format)
   *               - given_date: date declaration was provided (in ISO date format)
   *               - end_date:   end date of declaration (in ISO date format)
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function setDeclaration($newParams) {
    if (empty($newParams['entity_id'])) {
      throw new CRM_Core_Exception('GiftAid setDeclaration: entity_id is required');
    }

    // Retrieve existing declarations for this user.
    $currentDeclaration = CRM_Civigiftaid_Declaration::getDeclaration($newParams['entity_id'], $newParams['start_date']);
    $partialDeclaration = CRM_Civigiftaid_Declaration::getPartialDeclaration($newParams['entity_id']);
    if (!empty($partialDeclaration)) {
      // Merge the "new" params in (eg. new selection for eligible_for_gift_aid)
      $newParams = array_merge($newParams, $partialDeclaration);
    }
    if (!empty($currentDeclaration)) {
      if (!empty($partialDeclaration)) {
        // We've got partial declarations (no post_code, no start_date) and a current (valid) declaration so delete the partials
        CRM_Civigiftaid_Declaration::deletePartialDeclaration($newParams['entity_id']);
        // We want the ID of the currentDeclaration, not the partial one we just deleted
        unset($newParams['id']);
      }
      // Now merge the current declaration into the params (params overwrite current values if they exist)
      $newParams = array_merge($currentDeclaration, $newParams);
    }

    // Get future declarations: start_date in future, end_date in future or NULL
    // - if > 1, pick earliest start_date
    $futureDeclaration = [];
    $sql = "
        SELECT id, eligible_for_gift_aid, start_date, end_date
        FROM   civicrm_value_gift_aid_declaration
        WHERE  entity_id = %1 AND start_date > %2 AND (end_date > %2 OR end_date IS NULL)
        ORDER BY start_date";
    $dao = CRM_Core_DAO::executeQuery($sql, [
      1 => [$newParams['entity_id'], 'Integer'],
      2 => [$newParams['start_date'], 'Timestamp'],
    ]);
    if ($dao->fetch()) {
      $futureDeclaration['id'] = (int) $dao->id;
      $futureDeclaration['eligible_for_gift_aid'] = (int) $dao->eligible_for_gift_aid;
      $futureDeclaration['start_date'] = $dao->start_date;
      $futureDeclaration['end_date'] = $dao->end_date;
    }

    // Calculate new_end_date for negative declaration
    // - new_end_date =
    //   if end_date specified then (specified end_date)
    //   else (start_date of first future declaration if any, else NULL)
    $endTimestamp = NULL;
    if (isset($newParams['end_date'])) {
      $endTimestamp = strtotime($newParams['end_date']);
    }
    else {
      if (isset($futureDeclaration['start_date'])) {
        $endTimestamp = strtotime($futureDeclaration['start_date']);
      }
    }

    // Order of preference: PAST_4_YEARS > YES > YES
    switch ($newParams['eligible_for_gift_aid']) {
      case self::DECLARATION_IS_YES:
      case self::DECLARATION_IS_PAST_4_YEARS:
        // If there is no current declaration, create new.
        if (empty($currentDeclaration)) {
          if (empty($newParams['source'])) {
            $newParams['source'] = CRM_Core_Session::singleton()->get('postProcessTitle', E::LONG_NAME);
          }
          CRM_Civigiftaid_Declaration::insertDeclaration($newParams);
        }
        // If the contact has a current declaration stating that "No" they are not eligible for giftaid
        //   close it and create new one that is "Yes".
        elseif ($currentDeclaration['eligible_for_gift_aid'] === self::DECLARATION_IS_NO) {
          // Set its end_date to now and create a new declaration.
          $updateParams = [
            'id' => $currentDeclaration['id'],
            'end_date' => $newParams['start_date'],
          ];
          $updateParams = self::addReasonEndedContactDeclined($updateParams);
          CRM_Civigiftaid_Declaration::updateDeclaration($updateParams);
          unset($newParams['id'], $newParams['end_date']);
          if (empty($newParams['source'])) {
            $newParams['source'] = CRM_Core_Session::singleton()->get('postProcessTitle', E::LONG_NAME);
          }
          CRM_Civigiftaid_Declaration::insertDeclaration($newParams);
        }
        else {
          $updateParams = [];
          // Yes past 4 years is "better" than Yes! Update the declaration if:
          // - Contact selected past 4 years
          // - current start_date is less than 4 years ago
          if ($newParams['eligible_for_gift_aid'] === self::DECLARATION_IS_PAST_4_YEARS) {
            if (strtotime($currentDeclaration['start_date']) >= strtotime('-4 year')) {
              $updateParams['eligible_for_gift_aid'] = self::DECLARATION_IS_PAST_4_YEARS;
              $updateParams['start_date'] = date('YmdHis', strtotime('now'));
            }
          }
          if ($endTimestamp && (in_array($currentDeclaration['eligible_for_gift_aid'], [
              self::DECLARATION_IS_YES,
              self::DECLARATION_IS_PAST_4_YEARS
            ]))) {
            // If current declaration is "Yes/Yes past 4 years" extend its end_date to new_end_date.
            $newEndDate = date('YmdHis', $endTimestamp);
            if ($newEndDate !== $currentDeclaration['end_date']) {
              $updateParams['end_date'] = $newEndDate;
            }
          }
          if (!empty($updateParams)) {
            $updateParams['id'] = $currentDeclaration['id'];
            CRM_Civigiftaid_Declaration::updateDeclaration($updateParams);
          }
        }
        break;

      case self::DECLARATION_IS_NO:
        if (empty($currentDeclaration)) {
          // There is no current declaration so create new.
          if (empty($newParams['source'])) {
            $newParams['source'] = CRM_Core_Session::singleton()->get('postProcessTitle', E::LONG_NAME);
          }
          CRM_Civigiftaid_Declaration::insertDeclaration($newParams);
        }
        elseif (in_array($currentDeclaration['eligible_for_gift_aid'], [
          self::DECLARATION_IS_YES,
          self::DECLARATION_IS_PAST_4_YEARS
        ])) {
          // If current declaration is "Yes/Past 4 years" we need to keep that information
          // set its end_date to now and create new ending new_end_date.
          $updateParams = [
            'id' => $currentDeclaration['id'],
            'end_date' => $newParams['start_date'],
          ];
          $updateParams = self::addReasonEndedContactDeclined($updateParams);
          CRM_Civigiftaid_Declaration::updateDeclaration($updateParams);
          unset($newParams['id'], $newParams['end_date']);
          if (empty($newParams['source'])) {
            $newParams['source'] = CRM_Core_Session::singleton()->get('postProcessTitle', E::LONG_NAME);
          }
          CRM_Civigiftaid_Declaration::insertDeclaration($newParams);
        }
        break;
    }
  }

  /**
   * @param array $contacts
   *
   * @return array
   */
  public static function getCurrentDeclarations($contacts) {
    $currentDeclarations = [];

    foreach ($contacts as $contactId) {
      $currentDeclarations[] = self::getDeclaration($contactId);
    }

    return $currentDeclarations;
  }

  /**
   * Get Gift Aid declaration record for Individual.
   *
   * @param int    $contactID - the Individual for whom we retrieve declaration
   * @param date   $date      - date for which we retrieve declaration (in ISO date format)
   *       - e.g. the date for which you would like to check if the contact has a valid declaration
   *
   * @return array            - declaration record as associative array, else empty array.
   */
  public static function getDeclaration($contactID, $date = NULL) {
    if (empty($contactID)) {
      \Civi::log()->debug('CRM_Civigiftaid_Declaration::getDeclaration called with empty contact ID!');
      return [];
    }
    if (is_null($date)) {
      $date = date('YmdHis');
    }

    // Get current declaration: start_date in past, end_date in future or NULL
    // If > 1, pick latest end_date
    // Note that a record with an end_date will be chosen over one with a NULL
    // end_date, since ORDER BY end_date DESC will put NULL values last.
    $currentDeclaration = [];
    $sql = "
        SELECT id, entity_id, eligible_for_gift_aid, start_date, end_date, reason_ended, source, notes
        FROM   civicrm_value_gift_aid_declaration
        WHERE  entity_id = %1 AND start_date <= %2 AND (end_date > %2 OR end_date IS NULL)
        ORDER BY end_date DESC";
    $sqlParams = [
      1 => [$contactID, 'Integer'],
      2 => [$date, 'Timestamp'],
    ];
    // allow query to be modified via hook
    CRM_Civigiftaid_Utils_Hook::alterDeclarationQuery($sql, $sqlParams);
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if ($dao->fetch()) {
      $currentDeclaration['id'] = (int) $dao->id;
      $currentDeclaration['entity_id'] = (int) $dao->entity_id;
      $currentDeclaration['eligible_for_gift_aid'] = (int) $dao->eligible_for_gift_aid;
      $currentDeclaration['start_date'] = CRM_Utils_Date::isoToMysql($dao->start_date);
      $currentDeclaration['end_date'] = CRM_Utils_Date::isoToMysql($dao->end_date);
      $currentDeclaration['reason_ended'] = (string) $dao->reason_ended;
      $currentDeclaration['source'] = (string) $dao->source;
      $currentDeclaration['notes'] = (string) $dao->notes;
    }
    return $currentDeclaration;
  }

  /**
   * Add the "Contact Declined" param to the array of declaration params
   *
   * @param array $params
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function addReasonEndedContactDeclined($params): array {
    $contactDeclined = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => "reason_ended",
      'name' => "Contact_Declined",
    ]);
    if (!empty($contactDeclined['id'])) {
      $params['reason_ended'] = $contactDeclined['values'][$contactDeclined['id']]['value'];
    }
    return $params;
  }

  /**
   * Returns the contact name in a format accepted by HMRC.
   * Each Name must be 1-35 characters Alphabetic including the single quote, dot, and hyphen symbol.
   * First name cannot have spaces
   *
   * @param string $firstName
   * @param string $lastName
   *
   * @return array [[firstname,lastname], errors]
   * @throws \CiviCRM_API3_Exception
   */
  public static function getFilteredDonorName($firstName, $lastName) {
    $errors = [];
    if (empty($firstName)) {
      $errors[] = 'First name cannot be empty.';
    }
    if (empty($lastName)) {
      $errors[] = 'Last name cannot be empty.';
    }

    $contactName = [];
    if (empty($errors)) {
      $currentLocale = setlocale(LC_CTYPE, 0);
      setlocale(LC_CTYPE, "en_GB.utf8");
      $nameParts = [
        $firstName => "/[^A-Za-z'.-]/",
        $lastName => "/[^A-Za-z '.-]/"
      ];
      foreach ($nameParts as $name => $regex) {
        $filteredName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $filteredName = preg_replace($regex, '', $filteredName);
        $contactName[] = substr($filteredName, 0, 35);
      }
      setlocale(LC_CTYPE, $currentLocale);
    }

    return [$contactName, $errors];
  }

  /**
   * Return a formatted postcode
   *
   * @param string $postcode
   *
   * @return string
   */
  private static function getPostCode($postcode) {
    // remove non alphanumeric characters
    $cleanPostcode = preg_replace("/[^A-Za-z0-9]/", '', $postcode);
    // make uppercase
    $cleanPostcode = strtoupper($cleanPostcode);
    // insert space
    $postcode = substr($cleanPostcode, 0, -3) . " " . substr($cleanPostcode, -3);
    return $postcode;
  }

  public static function getDonorAddress($p_contact_id, $p_contribution_receive_date) {
    $aAddress['id']       = NULL;
    $aAddress['address']  = NULL;
    $aAddress['postcode'] = NULL;
    $aAddress['house_number'] = NULL;

    // We need to get the declaration that was current at the time that the contribution was made.
    // Look for a declaration that:
    //   - was eligible (ie. eligible_for_gift_aid is 1 or 3 and not 0).
    //   - contribution receive date was between start and end date for declaration.
    $sSql =<<<SQL
              SELECT   id         AS id
              ,        address    AS address
              ,        post_code  AS postcode
              FROM     civicrm_value_gift_aid_declaration
              WHERE    entity_id  =  %1
              AND      start_date <= %2
              AND      (end_date IS NULL OR end_date >= %2)
              AND      eligible_for_gift_aid > 0
              ORDER BY start_date ASC
              LIMIT  1
SQL;
    $aParams = [
      1 => [$p_contact_id, 'Integer'],
      2 => [$p_contribution_receive_date, 'Timestamp']
    ];

    $oDao = CRM_Core_DAO::executeQuery( $sSql
      , $aParams
      , $abort         = TRUE
      , $daoName       = NULL
      , $freeDAO       = FALSE
      , $i18nRewrite   = TRUE
      , $trapException = TRUE /* This must be explicitly set to TRUE for the code below to handle any Exceptions */
    );
    if (!(is_a($oDao, 'DB_Error'))) {
      if ($oDao->fetch()) {
        $aAddress['id']       = $oDao->id;
        $aAddress['address']  = $oDao->address;
        $aAddress['house_number'] = self::getHouseNo($oDao->address);
        $aAddress['postcode'] = self::getPostCode($oDao->postcode);
      }
    }

    return $aAddress;
  }

  /**
   * split the phrase by any number of commas or space characters,
   * which include " ", \r, \t, \n and \f
   * @param string $p_address_line
   *
   * @return string|null
   */
  private static function getHouseNo($p_address_line) {
    $aAddress = preg_split("/[,\s]+/", $p_address_line);
    if (empty($aAddress)) {
      return NULL;
    } else {
      return $aAddress[0];
    }
  }

}
