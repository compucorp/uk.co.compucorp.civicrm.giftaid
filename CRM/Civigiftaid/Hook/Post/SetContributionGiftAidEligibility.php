<?php

use CRM_Civigiftaid_Utils_GiftAid as GiftAidUtil;

/**
 * Class CRM_Civigiftaid_Hook_Post_SetContributionGiftAidEligibility.
 */
class CRM_Civigiftaid_Hook_Post_SetContributionGiftAidEligibility {

  /**
   * Set the gift aid eligibility for a contribution.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   * @param mixed $objectId
   *   Object ID.
   * @param object $objectRef
   *   Object reference.
   */
  public function run($op, $objectName, $objectId, &$objectRef) {
    if (!$this->shouldRun($op, $objectName)) {
      return;
    }

    $this->setGiftAidEligibilityStatus($objectId);
  }

  /**
   * Sets gift aid eligibility status for a contribution.
   *
   * We are mainly concerned about contribution added from Events
   * or the membership pages by the admin and not the Add new contribution screen as
   * this screen already has a form widget to set gift aid eleigibility status.
   *
   * This function checks if the contribution is eligible and automatically sets
   * the status to yes, else, it sets it to No.
   *
   * @param int $contributionId
   *   Contribution Id.
   */
  private function setGiftAidEligibilityStatus($contributionId) {
    $currentPath = CRM_Utils_System::currentPath();
    if (!$this->isAllowedPath($currentPath)) {
      return;
    }

    $eligibilityFieldId = $this->getEligibilityFieldId();
    $eligibilityField = 'custom_' . $eligibilityFieldId;

    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'return' => ['financial_type_id', 'contact_id'],
      'id' => $contributionId,
    ]);

    $allFinancialTypesEnabled = CRM_Civigiftaid_Form_Admin::isGloballyEnabled();
    if ($allFinancialTypesEnabled || $this->financialTypeIsEligible($contribution['financial_type_id'])) {
      $eligibility = GiftAidUtil::DECLARATION_IS_YES;
    }
    else {
      $eligibility = GiftAidUtil::DECLARATION_IS_NO;
    }

    civicrm_api3('Contribution', 'create', [
      $eligibilityField => $eligibility,
      'id' => $contributionId,
    ]);

    $contributionContact = $contribution['contact_id'];
    if (!GiftAidUtil::getDeclaration($contributionContact) && $eligibility == GiftAidUtil::DECLARATION_IS_YES) {
      CRM_Core_Session::setStatus(ts($this->getMissingGiftAidDeclarationMessage($contributionContact)), 'Gift Aid Declaration', 'success');
    }
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
  private function getMissingGiftAidDeclarationMessage($contactId) {
    $message = "This contribution has been automatically marked as Eligible for Gift Aid.
      This is because the administrator has indicated that it's financial type is Eligible for Gift Aid.
      However this contact does not have a valid Gift Aid Declaration. You can add one of these %s .";
    $giftAidDeclarationGroupId = $this->getGiftAidDeclarationGroupId();
    $selectedTab = 'custom_' . $giftAidDeclarationGroupId;
    $link = "<a href='/civicrm/contact/view/?reset=1&gid={$giftAidDeclarationGroupId}&cid={$contactId}&selectedChild={$selectedTab}'> Here </a>";

    return sprintf($message, $link);
  }

  /**
   * Returns the gift aid declaration custom group Id.
   *
   * @return int
   *   Custom group Id.
   */
  private function getGiftAidDeclarationGroupId() {
    try {
      $customGroup = civicrm_api3('CustomGroup', 'getsingle', [
        'return' => ['id'],
        'name' => 'Gift_Aid_Declaration',
      ]);

      return $customGroup['id'];
    }
    catch (Exception $e) {}
  }

  /**
   * Checks if the contribution financial type is eligible for gift aid.
   *
   * @param mixed $financialType
   *   Financial type.
   *
   * @return bool
   *   Whether eligible or not.
   */
  private function financialTypeIsEligible($financialType) {
    $eligibleFinancialTypes = CRM_Civigiftaid_Form_Admin::getFinancialTypesEnabled();

    return in_array($financialType, $eligibleFinancialTypes);
  }


  /**
   * Returns the eligibility custom field Id.
   *
   * @return int
   *  Eligibility field Id.
   */
  private function getEligibilityFieldId() {
    try {
      $customField = civicrm_api3('CustomField', 'getsingle', [
        'return' => ['id'],
        'name' => 'eligible_for_gift_aid',
        'custom_group_id' => 'Gift_Aid',
      ]);

      return $customField['id'];
    }
    catch (Exception $e) {}
  }

  /**
   * Checks if the given path requires giftaid processing.
   *
   * @param $path
   *   Path to be evaluated.
   *
   * @return bool
   *   If the path is one of the ones that needs to be processed for giftaid
   *   info.
   */
  private function isAllowedPath($path) {
    $allowedPaths = [
      'civicrm/member/add',               // Add membership page
      'civicrm/contact/view/membership',  // Add membership from contact view page
      'civicrm/participant/add',          // Register event participant page
      'civicrm/contact/view/participant', // Add participant from contact view page
      'civicrm/contribute/transact',      // Create contribution from contribution page
    ];

    return in_array($path, $allowedPaths);
  }

  /**
   * Determines if the hook should run or not.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   *
   * @return bool
   *   returns a boolean to determine if hook will run or not.
   */
  private function shouldRun($op, $objectName) {
    return $op == 'create' && $objectName == 'Contribution';
  }

}
