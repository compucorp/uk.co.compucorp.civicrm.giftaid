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
 * Class CRM_Civigiftaid_Form_Task_RemoveFromBatch
 *
 * This class provides the functionality to delete a group of  contribution from batch.
 */
class CRM_Civigiftaid_Form_Task_RemoveFromBatch extends CRM_Contribute_Form_Task {

  public function preProcess() {
    parent::preProcess();

    if ($this->isSubmitted()) {
      return;
    }

    list($totalCount, $toRemoveContributionIDs, $notInBatchContributionIDs, $alreadySubmittedContributionIDs)
      = CRM_Civigiftaid_Utils_Contribution::validationRemoveContributionFromBatch($this->_contributionIds);
    $session = new CRM_Core_Session();
    $session->set('contributionIDsToRemoveFromBatch', $toRemoveContributionIDs, E::SHORT_NAME);
    $this->assign('selectedContributions', $totalCount);
    $this->assign('totalToRemoveContributions', count($toRemoveContributionIDs));
    $this->assign('notInBatchContributions', count($notInBatchContributionIDs));
    $this->assign('alreadySubmitedContributions', count($alreadySubmittedContributionIDs));
    $this->assign('onlineSubmissionExtensionInstalled', CRM_Civigiftaid_Utils_Contribution::isOnlineSubmissionExtensionInstalled());

    $contributionsToRemoveRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($toRemoveContributionIDs);
    $this->assign('contributionsToRemoveRows', $contributionsToRemoveRows);

    $contributionsAlreadySubmitedRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($alreadySubmittedContributionIDs);
    $this->assign('contributionsAlreadySubmitedRows', $contributionsAlreadySubmitedRows);

    $contributionsNotInBatchRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($notInBatchContributionIDs);
    $this->assign('contributionsNotInBatchRows', $contributionsNotInBatchRows);
  }

  public function buildQuickForm() {
    if ($this->isSubmitted()) {
      return;
    }

    $this->addDefaultButtons(E::ts('Remove from batch'), 'next', 'cancel');
  }

  public function postProcess() {
    $session = new CRM_Core_Session();
    $contributionIDsToRemove = $session->get('contributionIDsToRemoveFromBatch', E::SHORT_NAME);

    $transaction = new CRM_Core_Transaction();

    list($total, $removedContributionIDs, $notRemovedContributionIDs) =
      CRM_Civigiftaid_Utils_Contribution::removeContributionFromBatch($contributionIDsToRemove);

    if (count($removedContributionIDs) === 0) {
      $status = E::ts('Could not removed contribution from batch, as there were no valid contribution(s) to be removed.');
    } else {
      $transaction->commit();
      $status = E::ts('Total Selected Contribution(s): %1', [1 => $total]);
      CRM_Core_Session::setStatus($status);

      if ($removedContributionIDs) {
        $status = E::ts('Contribution IDs removed from batch: %1', [1 => implode(', ', $removedContributionIDs)]);
      }
      if ($notRemovedContributionIDs) {
        $status = E::ts('Contribution IDs not removed from batch: %1', [1 => implode(', ', $notRemovedContributionIDs)]);
      }
    }
    CRM_Core_Session::setStatus($status);
  }

}
