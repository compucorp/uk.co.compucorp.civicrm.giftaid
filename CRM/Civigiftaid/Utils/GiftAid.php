<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                               |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
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
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Core/DAO.php';
require_once 'CRM/Core/Error.php';
require_once 'CRM/Utils/Array.php';
require_once 'CRM/Utils/Date.php';
require_once 'CRM/Civigiftaid/Utils/Hook.php';

class CRM_Civigiftaid_Utils_GiftAid {

    /**
     * How long a positive Gift Aid declaration is valid for under HMRC rules (years).
     */
    const DECLARATION_LIFETIME = 3;

    // Giftaid Declaration Options.
    const DECLARATION_IS_YES = 1;
    const DECLARATION_IS_NO = 0;
    const DECLARATION_IS_PAST_4_YEARS = 3;

    /**
     * Get Gift Aid declaration record for Individual.
     *
     * @param int    $contactID - the Individual for whom we retrieve declaration
     * @param date   $date      - date for which we retrieve declaration (in ISO date format)
     *							- e.g. the date for which you would like to check if the contact has a valid
* 								  declaration
     * @return array            - declaration record as associative array,
     *                            else empty array.
     * @access public
     * @static
     */
    static function getDeclaration( $contactID, $date = null, $charity = null ) {
        static $charityColumnExists = null;

        if ( is_null($date) ) {
            $date = date('Y-m-d H:i:s');
        }

        //$date = date('Y-m-d H:i:s');

        if ( $charityColumnExists === NULL ) {
            $charityColumnExists = CRM_Core_DAO::checkFieldExists( 'civicrm_value_gift_aid_declaration', 'charity' );
        }
        $charityClause = '';
        if ( $charityColumnExists ) {
            $charityClause = $charity ? " AND charity='{$charity}'" : " AND ( charity IS NULL OR charity = '' )";
        }

        // Get current declaration: start_date in past, end_date in future or null
        // - if > 1, pick latest end_date
        $currentDeclaration = array();
        $sql = "
        SELECT id, entity_id, eligible_for_gift_aid, start_date, end_date, reason_ended, source, notes
        FROM   civicrm_value_gift_aid_declaration
        WHERE  entity_id = %1 AND start_date <= %2 AND (end_date > %2 OR end_date IS NULL) {$charityClause}
        ORDER BY end_date DESC";
        $sqlParams = array( 1 => array($contactID, 'Integer'),
                            2 => array(CRM_Utils_Date::isoToMysql($date), 'Timestamp'),
                            );
        // allow query to be modified via hook
        CRM_Civigiftaid_Utils_Hook::alterDeclarationQuery( $sql, $sqlParams );
        $dao = CRM_Core_DAO::executeQuery( $sql, $sqlParams );
        if ( $dao->fetch() ) {
            $currentDeclaration['id'] = $dao->id;
            $currentDeclaration['entity_id'] = $dao->entity_id;
            $currentDeclaration['eligible_for_gift_aid'] = $dao->eligible_for_gift_aid;
            $currentDeclaration['start_date'] = $dao->start_date;
            $currentDeclaration['end_date'] = $dao->end_date;
            $currentDeclaration['reason_ended'] = $dao->reason_ended;
            $currentDeclaration['source'] = $dao->source;
            $currentDeclaration['notes'] = $dao->notes;
        }
        //CRM_Core_Error::debug('currentDeclaration', $currentDeclaration);
        return $currentDeclaration;
    }

    static function isEligibleForGiftAid( $contactID, $date = null, $contributionID = null ) {
        $isEligible = FALSE;
        $charity = null;
        if ( $contributionID &&
             CRM_Core_DAO::checkFieldExists( 'civicrm_value_gift_aid_submission', 'charity' ) ) {
            $charity =
                CRM_Core_DAO::singleValueQuery( 'SELECT charity FROM civicrm_value_gift_aid_submission WHERE entity_id = %1',
                                                array( 1 => array( $contributionID, 'Integer' ) ) );
        }

        if(isset($contributionID)) {
          $isContributionEligible = self::isContributionEligible($contactID, $contributionID);
          $isContributionSubmitted = self::isContributionSubmitted($contributionID);
          $isEligible = ($isContributionEligible && $isContributionSubmitted);
        }
        // hook can alter the eligibility if needed
        CRM_Civigiftaid_Utils_Hook::giftAidEligible( $isEligible, $contactID, $date, $contributionID );

        return $isEligible;
    }

    /**
     * Create / update Gift Aid declaration records on Individual when
     * "Eligible for Gift Aid" field on Contribution is updated.
     * See http://wiki.civicrm.org/confluence/display/CRM/Gift+aid+implementation
     *
     * TODO change arguments to single $param array
     * @param array  $params    - fields to store in declaration:
     *               - entity_id:  the Individual for whom we will create/update declaration
     *               - eligible_for_gift_aid: 1 for positive declaration, 0 for negative
     *               - start_date: start date of declaration (in ISO date format)
     *               - end_date:   end date of declaration (in ISO date format)
     *
     * @return array   TODO
     * @access public
     * @static
     */
    static function setDeclaration( $params ) {
        static $charityColumnExists = null;

        if ( !CRM_Utils_Array::value('entity_id', $params) ) {
            return( array(
                'is_error' => 1,
                'error_message' => 'entity_id is required',
            ) );
        }
        $charity = CRM_Utils_Array::value('charity', $params);

        // Retrieve existing declarations for this user.
        $currentDeclaration = CRM_Civigiftaid_Utils_GiftAid::getDeclaration($params['entity_id'],
                                                                    $params['start_date'],
                                                                    $charity);

        $charityClause = '';
        if ( $charityColumnExists === NULL ) {
            $charityColumnExists = CRM_Core_DAO::checkFieldExists( 'civicrm_value_gift_aid_declaration', 'charity' );
        }
        if ( $charityColumnExists ) {
            $charityClause = $charity ? " AND charity='{$charity}'" : " AND ( charity IS NULL OR charity = '' )";
        }

        // Get future declarations: start_date in future, end_date in future or null
        // - if > 1, pick earliest start_date
        $futureDeclaration = array();
        $sql = "
        SELECT id, eligible_for_gift_aid, start_date, end_date
        FROM   civicrm_value_gift_aid_declaration
        WHERE  entity_id = %1 AND start_date > %2 AND (end_date > %2 OR end_date IS NULL) {$charityClause}
        ORDER BY start_date";
        $dao = CRM_Core_DAO::executeQuery( $sql, array(
            1 => array($params['entity_id'], 'Integer'),
            2 => array(CRM_Utils_Date::isoToMysql($params['start_date']), 'Timestamp'),
        ) );
        if ( $dao->fetch() ) {
            $futureDeclaration['id'] = $dao->id;
            $futureDeclaration['eligible_for_gift_aid'] = $dao->eligible_for_gift_aid;
            $futureDeclaration['start_date'] = $dao->start_date;
            $futureDeclaration['end_date'] = $dao->end_date;
        }
       #CRM_Core_Error::debug('futureDeclaration', $futureDeclaration);

        $specifiedEndTimestamp = null;
        if ( CRM_Utils_Array::value('end_date', $params) ) {
            $specifiedEndTimestamp = strtotime( CRM_Utils_Array::value('end_date', $params) );
        }

        // Calculate new_end_date for negative declaration
        // - new_end_date =
        //   if end_date specified then (specified end_date)
        //   else (start_date of first future declaration if any, else null)
        $futureTimestamp = null;
        if ( CRM_Utils_Array::value('start_date', $futureDeclaration) ) {
            $futureTimestamp = strtotime( CRM_Utils_Array::value('start_date', $futureDeclaration) );
        }

        if ( $specifiedEndTimestamp ) {
            $endTimestamp = $specifiedEndTimestamp;
        } else if ( $futureTimestamp ) {
            $endTimestamp = $futureTimestamp;
        } else {
            $endTimestamp = null;
        }

        if ( $params['eligible_for_gift_aid'] == 1 ) {

            if ( !$currentDeclaration ) {
              // There are cases when CiviCRM creates a new decalaration with null start_date
              // before the appropriate hook that uses this function is called, if that happens
              // we delete such decalaration before creating a new one.
              CRM_Civigiftaid_Utils_GiftAid::_deleteDecarationWithNullStartDate($params['entity_id']);
              CRM_Civigiftaid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );
            } else if ( $currentDeclaration['eligible_for_gift_aid'] == 1 && $endTimestamp ) {
                //   - if current positive, extend its end_date to new_end_date.
                $updateParams = array(
                                      'id' => $currentDeclaration['id'],
                                      'end_date' => date('YmdHis', $endTimestamp),
                                      );
                CRM_Civigiftaid_Utils_GiftAid::_updateDeclaration( $updateParams );

            } else if ( $currentDeclaration['eligible_for_gift_aid'] == 0 || $currentDeclaration['eligible_for_gift_aid'] == 3 ) {
                //   - if current negative, set its end_date to now and create new ending new_end_date.
                $updateParams = array(
                                      'id' => $currentDeclaration['id'],
                                      'end_date' => CRM_Utils_Date::isoToMysql($params['start_date']),
                                      );
                CRM_Civigiftaid_Utils_GiftAid::_updateDeclaration( $updateParams );
                CRM_Civigiftaid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );
            }

        } else if ( $params['eligible_for_gift_aid'] == 3 ) {

            if ( !$currentDeclaration ) {
                // There is no current declaration so create new.
               CRM_Civigiftaid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );

            } else if ( $currentDeclaration['eligible_for_gift_aid'] == 3 && $endTimestamp ) {
                //   - if current positive, extend its end_date to new_end_date.
                $updateParams = array(
                                      'id' => $currentDeclaration['id'],
                                      'end_date' => date('YmdHis', $endTimestamp),
                                      );
                CRM_Civigiftaid_Utils_GiftAid::_updateDeclaration( $updateParams );

            } else if ( $currentDeclaration['eligible_for_gift_aid'] == 0 || $currentDeclaration['eligible_for_gift_aid'] == 1 ) {
                //   - if current negative, set its end_date to now and create new ending new_end_date.
                $updateParams = array(
                                      'id' => $currentDeclaration['id'],
                                      'end_date' => CRM_Utils_Date::isoToMysql($params['start_date']),
                                      );
                CRM_Civigiftaid_Utils_GiftAid::_updateDeclaration( $updateParams );
                CRM_Civigiftaid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );
            }

        } else if ( $params['eligible_for_gift_aid'] == 0 ) {

            if ( !$currentDeclaration ) {
                // There is no current declaration so create new.
                CRM_Civigiftaid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );

            } else if ( $currentDeclaration['eligible_for_gift_aid'] == 1 || $currentDeclaration['eligible_for_gift_aid'] == 3 ) {
                //   - if current positive, set its end_date to now and create new ending new_end_date.
                $updateParams = array(
                                      'id' => $currentDeclaration['id'],
                                      'end_date' => CRM_Utils_Date::isoToMysql($params['start_date']),
                                      );
                CRM_Civigiftaid_Utils_GiftAid::_updateDeclaration( $updateParams );
                CRM_Civigiftaid_Utils_GiftAid::_insertDeclaration( $params, $endTimestamp );
            }
            //   - if current negative, leave as is.
        }

        return array (
            'is_error' => 0,
            // TODO 'inserted' => array(id => A, entity_id = B, ...),
            // TODO 'updated'  => array(id => A, entity_id = B, ...),
        );
    }

    /*
     * Private helper function for setDeclaration
     * - update a declaration record.
     */
    static function _updateDeclaration( $params ) {
        // Update (currently we only need to update end_date but can make generic)
        // $params['end_date'] should by in date('YmdHis') format
        $sql = "
        UPDATE civicrm_value_gift_aid_declaration
        SET    end_date = %1
        WHERE  id = %2";
        $dao = CRM_Core_DAO::executeQuery( $sql, array(
            1 => array($params['end_date'], 'Timestamp'),
            2 => array($params['id'], 'Integer'),
        ) );
    }

    /*
     * Private helper function for setDeclaration
     * - insert a declaration record.
     */
    static function _insertDeclaration( $params, $endTimestamp ) {
        static $charityColumnExists = null;
        $charityClause = '';
        if ( $charityColumnExists === NULL ) {
            $charityColumnExists = CRM_Core_DAO::checkFieldExists( 'civicrm_value_gift_aid_declaration', 'charity' );
        }
        if ( !CRM_Utils_Array::value('charity', $params) ) {
            $charityColumnExists = false;
        }

        if ( $charityColumnExists ) {
            $charityCol = ', charity';
            $charityVal = ', %10';
        }

        // Insert
        $sql = "
        INSERT INTO civicrm_value_gift_aid_declaration (entity_id, eligible_for_gift_aid, address , post_code , start_date, end_date, reason_ended, source, notes {$charityCol})
        VALUES (%1, %2, %3, %4, %5, %6, %7 , %8 , %9 {$charityVal})";
        $queryParams = array(
                             1 => array($params['entity_id'], 'Integer'),
                             2 => array($params['eligible_for_gift_aid'], 'Integer'),
                             3 => array(CRM_Utils_Array::value('address', $params, ''), 'String'),
                             4 => array(CRM_Utils_Array::value('post_code', $params, ''), 'String'),
                             5 => array(CRM_Utils_Date::isoToMysql($params['start_date']), 'Timestamp'),
                             6 => array(($endTimestamp ? date('YmdHis', $endTimestamp) : ''), 'Timestamp'),
                             7 => array(CRM_Utils_Array::value('reason_ended', $params, ''), 'String'),
                             8 => array(CRM_Utils_Array::value('source', $params, ''), 'String'),
                             9 => array(CRM_Utils_Array::value('notes', $params, ''), 'String'),
                             );
        if ( $charityColumnExists ) {
            $queryParams[10] = array(CRM_Utils_Array::value('charity', $params, ''), 'String');
        }

        $dao = CRM_Core_DAO::executeQuery( $sql, $queryParams );
    }

    /**
     * Deletes a contact declaration with null start date.
     * 
     * @param int $id
     *  ID of the Contact for whom we're deleting decalaration.
     */
    static function _deleteDecarationWithNullStartDate($contactID) {
      CRM_Utils_SQL_Delete::from('civicrm_value_gift_aid_declaration')
        ->where('entity_id = #contact', ['contact' => $contactID])
        ->where('start_date IS NULL')
        ->execute();
    }

    static function getContactsWithDeclarations() {
      $contactsWithDeclarations = [];
      $sql = "
        SELECT id, eligible_for_gift_aid, entity_id
        FROM   civicrm_value_gift_aid_declaration
        GROUP BY entity_id";

      $dao = CRM_Core_DAO::executeQuery( $sql );
      foreach($dao->fetchAll() as $row){
        $contactsWithDeclarations[] = $row['entity_id'];
      }

      return $contactsWithDeclarations;
    }

    static function getCurrentDeclarations($contacts, $date = null, $charity = null) {
      $currentDeclarations = array();

      foreach($contacts as $contactId) {
        $currentDeclarations[] = self::getDeclaration($contactId);
      }

      return $currentDeclarations;
    }

    static function setSubmission( $params ) {
      $sql = "SELECT * FROM civicrm_value_gift_aid_submission where entity_id = %1";
      $sqlParams = array( 1 => array($params['entity_id'], 'Integer') );
      $dao = CRM_Core_DAO::executeQuery( $sql, $sqlParams );
      $count = count($dao->fetchAll());

      if ($count == 0) {
        // Insert
        $sql = "
        INSERT INTO civicrm_value_gift_aid_submission (entity_id, eligible_for_gift_aid, amount, gift_aid_amount, batch_name)
        VALUES (%1, %2, NULL, NULL, NULL)";
      }
      else {
        // Update
        $sql = "
        UPDATE civicrm_value_gift_aid_submission
        SET eligible_for_gift_aid = %2
        WHERE entity_id = %1";
      }

      $queryParams = array(
        1 => array($params['entity_id'], 'Integer'),
        2 => array($params['eligible_for_gift_aid'], 'Integer'),
      );
      $dao = CRM_Core_DAO::executeQuery( $sql, $queryParams );
    }

    static function getContributionsByDeclarations($declarations = array(), $limit = 100) {
      $contributionsToSubmit = array();

      foreach($declarations as $declaration) {
        $dateRange = array();

        $contactId = $declaration['entity_id'];
        $startDate = $declaration['start_date'];
        $dateRange[0] = self::dateFourYearsAgo($startDate);
        $dateRange[1] = $startDate;
        $contributions = self::getContributionsByDateRange($contactId, $dateRange);
        $contributionsToSubmit = array_merge($contributions, $contributionsToSubmit);

        if(count($contributionsToSubmit) >= $limit) {
          $contributionsToSubmit = array_slice($contributionsToSubmit, 0, $limit);
          break;
        }
      }
      return $contributionsToSubmit;
    }

    static function dateFourYearsAgo($startDate) {
      $date = new DateTime($startDate);
      $dateFourYearsAgo = $date->modify('-4 year')->format('Y-m-d H:i:s');
      return $dateFourYearsAgo;
    }

    static function getContributionsByDateRange($contactId, $dateRange) {
      if(CRM_Civigiftaid_Form_Admin::isGloballyEnabled()) {
        $result = civicrm_api3('Contribution', 'get', array(
          'sequential' => 1,
          'return' => "financial_type_id,id",
          'contact_id' => $contactId,
          'id' => array('NOT IN' => self::submittedContributions()),
          'receive_date' => array('BETWEEN' => $dateRange),
        ));
      }else if($financialTypes = CRM_Civigiftaid_Form_Admin::getFinancialTypesEnabled()) {
        $result = civicrm_api3('Contribution', 'get', array(
          'sequential' => 1,
          'return' => "financial_type_id,id",
          'contact_id' => $contactId,
          'financial_type_id' => $financialTypes,
          'id' => array('NOT IN' => self::submittedContributions()),
          'receive_date' => array('BETWEEN' => $dateRange),
        ));
      }
      return $result['values'];
    }

    static function submittedContributions() {
      $submittedContributions = array();
      $sql = "
        SELECT entity_id
        FROM   civicrm_value_gift_aid_submission";

      $dao = CRM_Core_DAO::executeQuery( $sql );
      foreach($dao->fetchAll() as $row){
        $submittedContributions[] = $row['entity_id'];
      }

      return $submittedContributions;
    }

    static function isContributionSubmitted($contributionID) {
      $sql = "SELECT * FROM civicrm_value_gift_aid_submission where entity_id = %1";
      $sqlParams = array( 1 => array($contributionID, 'Integer') );

      $dao = CRM_Core_DAO::executeQuery( $sql, $sqlParams );

      $count = count($dao->fetchAll());
      if(!$count || $dao->eligible_for_gift_aid == 0) {
        return FALSE;
      }
      return TRUE;
    }

    /**
     * Get all gift aid declarations made by a contact.
     *
     * @param int $contactId
     * @return bool|array
     */
    static function getAllDeclarations($contactID) {
      $declarations = array();
      $sql = "SELECT id, entity_id, eligible_for_gift_aid, start_date, end_date, reason_ended, source, notes
              FROM civicrm_value_gift_aid_declaration
              WHERE  entity_id = %1";
      $sqlParams = array(
                     1 => array($contactID, 'Integer')
                   );

      $dao = CRM_Core_DAO::executeQuery( $sql, $sqlParams );

      if($declarations = $dao->fetchAll()) {
        return $declarations;
      }

      return FALSE;
    }

    /**
     * Check if Eligibility criteria for Contribution is met.
     *
     * @param Int $contactID
     * @param Int $contributionID
     * @return boolean
     */
    static function isContributionEligible($contactID, $contributionID) {
      $contributionDeclarationDateMatchFound = FALSE;
      $declarations = self::getAllDeclarations($contactID);

      $eligibilityFieldId = civicrm_api3('CustomField', 'getsingle', array(
                        'return' => array("id"),
                        'name' => "eligible_for_gift_aid",
                        'custom_group_id' => "Gift_Aid",
                      ))['id'];
      $eligibilityFieldCol = 'custom_' . $eligibilityFieldId;
      $contribution = civicrm_api3('Contribution', 'getsingle', array(
                        'return' => array($eligibilityFieldCol, "receive_date"),
                        'id' => $contributionID,
                      ));

      if($contribution[$eligibilityFieldCol] == self::DECLARATION_IS_NO) {
        return FALSE;
      }

      foreach($declarations as $declaration) {
        if($declaration['eligible_for_gift_aid'] == self::DECLARATION_IS_PAST_4_YEARS) {
          $declaration['start_date'] = self::dateFourYearsAgo($declaration['start_date']);
        }

        // Convert dates to timestamps.
        $startDateTS = strtotime($declaration['start_date']);
        $endDateTS = !empty($declaration['end_date']) ? strtotime($declaration['end_date']) : NULL;
        $contributionDateTS = strtotime($contribution['receive_date']);

        /**
         * Check between which date the contribution's receive date falls.
         */
        if(!empty($endDateTS)) {
          $contributionDeclarationDateMatchFound =
            ($contributionDateTS >= $startDateTS) && ($contributionDateTS < $endDateTS);
        }
        else {
          $contributionDeclarationDateMatchFound = ($contributionDateTS >= $startDateTS);
        }

        if($contributionDeclarationDateMatchFound == TRUE) {
          return ((bool) $declaration['eligible_for_gift_aid']);
        }
      }
    }
}
