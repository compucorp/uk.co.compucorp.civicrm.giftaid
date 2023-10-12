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

/**
 * Class CRM_Civigiftaid_Utils_Hook
 */
abstract class CRM_Civigiftaid_Utils_Hook extends CRM_Utils_Hook {

  /**
   * This hook allows filtering contributions for gift-aid
   *
   * @param bool $isEligible
   *   eligibilty already detected if getDeclaration() method.
   * @param int $contactID
   *   contact being checked
   * @param date $date
   *   date gift-aid declaration was made on
   * @param int $contributionID
   *   contribution id if any being referred
   *
   * @return mixed
   */
  public static function giftAidEligible(&$isEligible, $contactID, $date = NULL, $contributionID = NULL) {
    return self::singleton()->invoke(['isEligible', 'contactID', 'date', 'contributionID'], $isEligible, $contactID, $date, $contributionID, self::$_nullObject, self::$_nullObject, 'civicrm_giftAidEligible');
  }

  /**
   * This hook allows doing any extra processing for contributions that are added to a batch.
   *
   * @param int $batchID
   * @param array $contributionsAdded
   *   Contribution ids that have been batched
   *
   * @return mixed
   */
  public static function batchContributions($batchID, $contributionsAdded) {
    return self::singleton()->invoke(['batchID', 'contributionsAdded'], $batchID, $contributionsAdded, self::$_nullObject, self::$_nullObject, self::$_nullObject, self::$_nullObject, 'civicrm_batchContributions');
  }

  /**
   * This hook allows altering getDeclaration() query
   * @param string $query
   *   declaration query
   * @param array $queryParams
   *   params required by query
   *
   * @return mixed
   */
  public static function alterDeclarationQuery(&$query, &$queryParams) {
    return self::singleton()->invoke(['query', 'queryParams'], $query, $queryParams, self::$_nullObject, self::$_nullObject, self::$_nullObject, self::$_nullObject, 'civicrm_alterDeclarationQuery');
  }

}
