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

use Civi\Api4\CustomGroup;
use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoSubscriber;
use CRM_Civigiftaid_ExtensionUtil as E;

/**
 * Class CRM_Civigiftaid_Hook_Post_SetContributionGiftAidEligibility.
 */
class CRM_Civigiftaid_SetContributionGiftAidEligibility extends AutoSubscriber {

  public static function getSubscribedEvents() {
    return ['hook_civicrm_postCommit' => 'runCallback'];
  }

  /**
   * Set the gift aid eligibility for a contribution if it has an eligible financial type.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *
   * @throws \CRM_Extension_Exception
   * @throws \CRM_Core_Exception
   */
  public static function runCallback(GenericHookEvent $event) {
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
   * @throws \CRM_Core_Exception
   */
  public static function setGiftAidEligibilityStatus(int $contributionID, string $action = 'edit'): bool {
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

    if (!empty($contribution[$contributionBatchNameFieldName])) {
      // If contribution is already in a batch don't touch!
      return (bool) $contribution[$contributionEligibleGiftAidFieldName];
    }

    // If unset, eligibility field is NULL or '' so we should calculate
    // If set to 1 we recalculate (you can't force it to be eligible)
    // If set to 0 we accept user choice to set it as "not eligible"
    $eligibility = $contribution[$contributionEligibleGiftAidFieldName];
    switch ($eligibility) {
      case 0:
        return FALSE;

      case NULL:
      case '':
      case 1:
      default:
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
  private static function getMissingGiftAidDeclarationMessage(int $contactId): string {
    $giftAidDeclarationGroupId = CustomGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'gift_aid_declaration')
      ->execute()
      ->first()['id'];
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
   * Checks if the contribution financial type is eligible for gift aid.
   *
   * @param int $financialType
   *   Financial type.
   *
   * @return bool
   *   Whether eligible or not.
   */
  private static function financialTypeIsEligible(int $financialType): bool {
    $eligibleFinancialTypes = \Civi::settings()->get('civigiftaid_financial_types_enabled');
    return in_array($financialType, $eligibleFinancialTypes);
  }

}
