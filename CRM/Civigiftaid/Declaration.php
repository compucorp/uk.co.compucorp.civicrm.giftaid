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

use Civi\Api4\Contribution;
use Civi\Api4\CustomValue;
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
   */
  public static function update(int $contributionID) {
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

    $contribution = Contribution::get(FALSE)
      ->addSelect('*', 'custom.*')
      ->addWhere('id', '=', $contributionID)
      ->execute()
      ->first();

    $startDate = $givenDate = $contribution['receive_date'];
    $contactID = $contribution['contact_id'];
    list($addressDetails, $postCode) = self::getAddressAndPostalCode($contactID);

    $declarationParams = [
      'entity_id' => $contactID,
      'start_date' => CRM_Utils_Date::isoToMysql($startDate),
      'given_date' => CRM_Utils_Date::isoToMysql($givenDate),
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
   * @throws \CRM_Core_Exception
   */
  public static function getAddressAndPostalCode(int $contactID) {
    $fullFormattedAddress = $postalCode = '';

    // get Address & Postal Code of the contact
    $address = civicrm_api3('Address', 'get', [
      'contact_id' => $contactID,
      'is_primary' => 1,
    ])['values'];
    if (!empty($address)) {
      $address = reset($address);
      // 1226 = United Kingdom
      if (!empty($address['country_id']) && ((int)$address['country_id'] !== 1226)) {
        // "Overseas" address
        $postalCode = 'X';
      }
      else {
        $postalCode = $address['postal_code'] ?? '';
      }
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
    return CustomValue::get('gift_aid_declaration', FALSE)
      ->addWhere('entity_id', '=', $contactID)
      ->execute()
      ->getArrayCopy();
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
  private static function getPartialDeclaration(int $contactID): array {
    $giftAidDeclaration = CustomValue::get('gift_aid_declaration', FALSE)
      ->addWhere('entity_id', '=', $contactID)
      ->addWhere('post_code', 'IS NULL')
      ->addWhere('start_date', 'IS NULL')
      ->addOrderBy('id', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();
    if (!empty($giftAidDeclaration)) {
      return [
        'id' => $giftAidDeclaration['id'],
        'eligible_for_gift_aid' => $giftAidDeclaration['eligible_for_gift_aid'],
      ];
    }
    return [];
  }

  /**
   * Update the address on the current declaration for a contact
   * This is usually triggered when creating/editing a declaration from the contact record
   *
   * @param int $contactID
   *
   * @throws \CRM_Core_Exception
   */
  public static function updateDeclarationAddress(int $contactID) {
    // Get the current declaration for the contact
    $currentDeclaration = CRM_Civigiftaid_Declaration::getDeclaration($contactID);

    // Only update address if empty. Get current address/postcode formatted for giftaid.
    if (empty($currentDeclaration['address']) || empty($currentDeclaration['post_code'])) {
      // Get the home address of the contact
      list($updateParams['address'], $updateParams['post_code']) = CRM_Civigiftaid_Declaration::getAddressAndPostalCode($contactID);
    }
    if (!empty($updateParams)) {
      $updateParams['id'] = $currentDeclaration['id'];
      CRM_Civigiftaid_Declaration::insertDeclaration($updateParams);
    }
  }

  /**
   * Insert a declaration record
   *
   * @param array $params
   */
  public static function insertDeclaration(array $params) {
    if (isset($params['id'])) {
      $customValueAPI = CustomValue::update('gift_aid_declaration', FALSE)
        ->addWhere('id', '=', $params['id']);
    }
    else {
      $customValueAPI = CustomValue::create('gift_aid_declaration', FALSE);

      // We will create a new record.
      if (empty($params['given_date'])) {
        // We should store a given date. Default to the start_date, but if that's not
        // given, assume now.
        $params['given_date'] = $params['start_date'] ?? date('YmdHis');
      }
    }

    if (isset($params['entity_id'])) {
      $customValueAPI->addValue('entity_id', $params['entity_id']);
    }
    if (isset($params['eligible_for_gift_aid'])) {
      $customValueAPI->addValue('eligible_for_gift_aid', $params['eligible_for_gift_aid']);
    }
    if (isset($params['address'])) {
      $customValueAPI->addValue('address', $params['address']);
    }
    if (isset($params['post_code'])) {
      $customValueAPI->addValue('post_code', $params['post_code']);
    }
    if (isset($params['start_date'])) {
      $customValueAPI->addValue('start_date', $params['start_date']);
    }
    if (isset($params['given_date'])) {
      $customValueAPI->addValue('given_date', $params['given_date']);
    }
    if (isset($params['end_date'])) {
      $customValueAPI->addValue('end_date', $params['end_date']);
    }
    if (isset($params['reason_ended'])) {
      $customValueAPI->addValue('reason_ended', $params['reason_ended']);
    }
    if (isset($params['source'])) {
      $customValueAPI->addValue('source', $params['source']);
    }
    if (isset($params['notes'])) {
      $customValueAPI->addValue('notes', $params['notes']);
    }

    // Insert or update.
    $customValueAPI->execute()->first()['id'];
  }

  /**
   * Delete all declarations that are missing a postcode and a start_date for
   * the given contact.
   *
   * @param int $contactID
   */
  public static function deletePartialDeclaration(int $contactID) {
    CustomValue::delete('gift_aid_declaration', FALSE)
      ->addWhere('entity_id', '=', $contactID)
      ->addWhere('post_code', 'IS NULL')
      ->addWhere('start_date', 'IS NULL')
      ->execute();
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
          CRM_Civigiftaid_Declaration::insertDeclaration($updateParams);
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

          // Allow updating address/postcode on existing declarations
          if (!empty($newParams['address']) && $newParams['address'] !== $currentDeclaration['address']) {
            $updateParams['address'] = $newParams['address'];
          }
          if (!empty($newParams['post_code']) && $newParams['post_code'] !== $currentDeclaration['post_code']) {
            $updateParams['post_code'] = $newParams['post_code'];
          }

          if (!empty($updateParams)) {
            $updateParams['id'] = $currentDeclaration['id'];
            CRM_Civigiftaid_Declaration::insertDeclaration($updateParams);
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
          CRM_Civigiftaid_Declaration::insertDeclaration($updateParams);
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
   *       - If you pass NULL it will look for a declaration with date = now.
   *       - If you pass '' it will look for any declaration matching the contact ID.
   *       - The first declaration it finds will always be returned.
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
    $dateClause = '';
    if (!empty($date)) {
      $dateClause = "AND start_date <= %2 AND (end_date > %2 OR end_date IS NULL)";
    }
    $sql = "
        SELECT id, entity_id, eligible_for_gift_aid, start_date, given_date, end_date, reason_ended, source, notes, address, post_code
        FROM   civicrm_value_gift_aid_declaration
        WHERE  entity_id = %1 {$dateClause}
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
      $currentDeclaration['given_date'] = CRM_Utils_Date::isoToMysql($dao->given_date);
      $currentDeclaration['end_date'] = CRM_Utils_Date::isoToMysql($dao->end_date);
      $currentDeclaration['reason_ended'] = (string) $dao->reason_ended;
      $currentDeclaration['source'] = (string) $dao->source;
      $currentDeclaration['notes'] = (string) $dao->notes;
      $currentDeclaration['address'] = (string) $dao->address;
      $currentDeclaration['post_code'] = (string) $dao->post_code;
    }
    return $currentDeclaration;
  }

  /**
   * Add the "Contact Declined" param to the array of declaration params
   *
   * @param array $params
   *
   * @return array
   * @throws \CRM_Core_Exception
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
   * @throws \CRM_Core_Exception
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
   * Return a formatted postcode. We use "X" for Overseas
   *
   * @param string $postcode
   *
   * @return string
   */
  private static function getPostCode($postcode) {
    // make uppercase
    $postcode = strtoupper($postcode);
    if ($postcode === 'X') {
      // Overseas
      return $postcode;
    }

    // remove non alphanumeric characters
    $cleanPostcode = preg_replace("/[^A-Za-z0-9]/", '', $postcode);
    // insert space
    $postcode = substr($cleanPostcode, 0, -3) . " " . substr($cleanPostcode, -3);
    return $postcode;
  }

  /**
   * Get the donor address formatted for manual (report) or online submission
   *
   * @param int $contributionID
   *
   * @return array
   */
  public static function getDonorAddress(int $contributionID): array {
    $contribution = Contribution::get(FALSE)
      ->addSelect('*', 'custom.*')
      ->addWhere('id', '=', $contributionID)
      ->execute()
      ->first();
    $declaration = CRM_Civigiftaid_Utils_Contribution::getDeclarationForContribution($contribution);
    if ($declaration) {
      $aAddress['address'] = $declaration['address'];
      $aAddress['house'] = substr(explode(',', $declaration['address'])[0] ?? '', 0, 40);
      $aAddress['postcode'] = self::getPostCode($declaration['post_code']);
    }
    return $aAddress ?? [];
  }

  /**
   * Get an donor address from a declaration made in the 4 years after the
   * contribution with a eligibility of type:
   *
   * > "Yes, and for donations made in the past 4 years"
   *
   * @param int $contactID
   * @param string $contributionReceiveDate
   *
   * @return mixed
   */
  private static function getDonorAddressIn4YearsDeclaration($contactID, $contributionReceiveDate) {
    $contributionReceiveDate = date('Ymd', strtotime($contributionReceiveDate)) . '235959';
    $aAddress = [];

    $sSql = "
        SELECT
            address AS address,
            post_code AS postcode
        FROM civicrm_value_gift_aid_declaration
        WHERE
            entity_id = %1 AND
            start_date <= %2 AND (
                end_date IS NULL OR
                DATE_ADD( end_date, INTERVAL 4 YEAR ) >= %2
            ) AND
            eligible_for_gift_aid = 3
        ORDER BY start_date ASC
        LIMIT  1
";
    $aParams = [
      1 => [$contactID, 'Integer'],
      2 => [$contributionReceiveDate, 'Timestamp']
    ];

    $oDao = CRM_Core_DAO::executeQuery($sSql, $aParams);
    if ($oDao->fetch()) {
      $aAddress['address'] = $oDao->address;
      $aAddress['house'] = substr(explode(',', $oDao->address)[0] ?? '', 0, 40);
      $aAddress['postcode'] = self::getPostCode($oDao->postcode);
    }

    return $aAddress;
  }

  /**
   * Reformat a declaration address to current expectations
   *
   * Old declarations may have addresses stored in other formats.
   * - comma separated with postcode in $addressDetails, not separate
   * - \n separated fields in $addressDetails
   *
   * @param string $addressDetails
   * @param string $postCode
   *
   * @return array
   */
  public static function reformatAddress(string $addressDetails, string $postCode) : array {
    // Convert various separators to ", "
    $addressDetails = implode(", ", array_map('trim', preg_split("/(, ?|\r|\n)+/", $addressDetails, 0, PREG_SPLIT_NO_EMPTY)));

    // Existing postcode?
    if ($postCode) {
      // regex adapted from https://en.wikipedia.org/wiki/Postcodes_in_the_United_Kingdom#Validation
      // Try to make sure it has the space in the right place
      if (preg_match('/([A-Z]{1,2}\d[A-Z\d]?) ?(\d[A-Z]{2})$/i', $postCode, $p)) {
        $postCode = $p[1] . " " . $p[2];
      }
    }
    else { // Try to find it in the address
      // regex adapted from https://en.wikipedia.org/wiki/Postcodes_in_the_United_Kingdom#Validation
      if (preg_match('/^(.*) ([A-Z]{1,2}\d[A-Z\d]?) ?(\d[A-Z]{2})$/i', $addressDetails, $p)) {
        $addressDetails = rtrim($p[1], ",");  // Remove trailing comma if present
        $postCode = $p[2] . " " . $p[3];
      }
    }
    $postCode = strtoupper($postCode);
    return [$addressDetails, $postCode];
  }

}
