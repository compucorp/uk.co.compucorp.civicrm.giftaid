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
 * Class CRM_Civigiftaid_Hook_Post_SetContributionGiftAidEligibility.
 */
class CRM_Civigiftaid_SetContributionGiftAidEligibility {

  /**
   * Set the gift aid eligibility for a contribution if it has an eligible financial type.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function runCallback($event) {
    if (($event->entity !== 'Contribution') || !in_array($event->action, ['create', 'edit'])) {
      return;
    }

    if (self::setGiftAidEligibilityStatus($event->id, $event->action)) {
      $event->object->find();
      if (!CRM_Civigiftaid_Declaration::getDeclaration($event->object->contact_id)) {
        if (CRM_Core_Session::getLoggedInContactID() && CRM_Core_Permission::check('access CiviContribute')) {
          // Only show message if we have a logged in contact with permissions to access CiviContribute
          CRM_Core_Session::setStatus(self::getMissingGiftAidDeclarationMessage($event->object->contact_id), E::ts('Gift Aid Declaration'), 'info');
        }
      }
    }
  }

  /**
   * Sets gift aid eligibility status for a contribution.
   *
   * We are mainly concerned about contribution added from Events
   * or the membership pages by the admin and not the Add new contribution screen as
   * this screen already has a form widget to set gift aid eligibility status.
   *
   * This function checks if the contribution is eligible and automatically sets
   * the status to yes, else, it sets it to No.
   *
   * Note that this is designed to be called as part of specific workflows and is not suited
   * to general purpose use. For example, it will never recalculate existing eligibility against
   * the current configured financial types.
   *
   * @param int $contributionID
   *   Contribution Id.
   * @param string $action
   *   The action - eg. create/edit (If action=create then batch_name is cleared)
   *
   * @return bool
   *   Whether the contribution was eligible
   *
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function setGiftAidEligibilityStatus($contributionID, $action = 'edit') {
    $contributionEligibleGiftAidFieldName = CRM_Civigiftaid_Utils::getCustomByName('eligible_for_gift_aid', 'gift_aid');
    $contributionBatchNameFieldName = CRM_Civigiftaid_Utils::getCustomByName('batch_name', 'gift_aid');

    $contribution = civicrm_api3('Order', 'getsingle', [
      'id' => $contributionID,
      'return' => [
        'financial_type_id',
        'contact_id',
        'contribution_recur_id',
        $contributionEligibleGiftAidFieldName,
        $contributionBatchNameFieldName,
        'line_items',
      ]
    ]);

    if (isset($contribution[$contributionEligibleGiftAidFieldName]) && ($contribution[$contributionEligibleGiftAidFieldName] !== '')) {
      $eligibility = (int) $contribution[$contributionEligibleGiftAidFieldName];
    }
    // If the "Eligible for gift-aid" field is already set don't try to set it.
    if (!isset($eligibility)) {
      // We need to set the Eligible for gift-aid field.
      $allFinancialTypesEnabled = (bool) \Civi::settings()->get('civigiftaid_globally_enabled');

      if ($allFinancialTypesEnabled) {
        $eligibility = 1;
      }
      else {
        // Assume not eligible until proven eligible.
        $eligibility = 0;
        // We need to look through line items to determine if any of them are eligible.
        if (empty($contribution['line_items'])) {
          // Issue #9: Sometimes line_itmes are not returned!
          if (self::financialTypeIsEligible($contribution['financial_type_id'])) {
            $eligibility = 1;
          }
        }
        else {
          foreach ($contribution['line_items'] as $lineItem) {
            if (self::financialTypeIsEligible($lineItem['financial_type_id'])) {
              $eligibility = 1;
              break;
            }
          }
        }
      }
    }

    // Now update the giftaid fields on the contribution and (re-)do calculations for amounts.
    if (!empty($contribution['contribution_recur_id']) && ($action === 'create')) {
      // As it is a copy of a contribution we need to clear the batch name field
      CRM_Civigiftaid_Utils_Contribution::updateGiftAidFields($contributionID, $eligibility, '', TRUE);
    }
    else {
      // This will not touch the contribution if it is part of a batch
      CRM_Civigiftaid_Utils_Contribution::updateGiftAidFields($contributionID, $eligibility, $contribution[$contributionBatchNameFieldName]);
    }

    return (bool) $eligibility;
  }

  /**
   * Returns a warning message about missing gift aid declaration for
   * contribution contact.
   *
   * @param int $contactId
   *   Contact Id.
   *
   * @return string
   *
   */
  private static function getMissingGiftAidDeclarationMessage($contactId) {
    $giftAidDeclarationGroupId = self::getGiftAidDeclarationGroupId();
    $selectedTab = 'custom_' . $giftAidDeclarationGroupId;
    $link = CRM_Utils_System::url(
      'civicrm/contact/view',
      [
        'reset' => 1,
        'gid' => $giftAidDeclarationGroupId,
        'cid' => $contactId,
        'selectedChild' => $selectedTab,
      ]
    );
    $link = "<a href='{$link}'>" . E::ts('here') . "</a>";

    return E::ts("This contribution has been automatically marked as Eligible for Gift Aid.
      This is because the administrator has indicated that it's financial type is Eligible for Gift Aid.
      However this contact does not have a valid Gift Aid Declaration. You can add one of these %1.", [1 => $link]);
  }

  /**
   * Returns the gift aid declaration custom group Id.
   *
   * @return int
   *   Custom group Id.
   */
  private static function getGiftAidDeclarationGroupId() {
    try {
      $customGroup = civicrm_api3('CustomGroup', 'getsingle', [
        'return' => ['id'],
        'name' => 'gift_aid_declaration',
      ]);

      return $customGroup['id'];
    }
    catch (Exception $e) {}
  }

  /**
   * Checks if the contribution financial type is eligible for gift aid.
   *
   * @param int $financialType
   *   Financial type.
   *
   * @return bool
   *   Whether eligible or not.
   */
  private static function financialTypeIsEligible($financialType) {
    $eligibleFinancialTypes = \Civi::settings()->get('civigiftaid_financial_types_enabled');
    return in_array($financialType, $eligibleFinancialTypes);
  }

}
