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

use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use CRM_Civigiftaid_ExtensionUtil as E;

/**
 * Class CRM_Civigiftaid_Form_Task_AddToBatch
 * This class provides the functionality to add a group of contribution to a batch.
 */
class CRM_Civigiftaid_Form_Task_AddToBatch extends CRM_Contribute_Form_Task {

  /**
   * @var int Existing batch ID
   */
  protected $_id = NULL;

  /**
   * @var string The name for the new batch
   */
  protected $batchName;

  /**
   * @var string The title for the new batch
   */
  protected $batchTitle;

  public function preProcess() {
    parent::preProcess();

    // Generate Gift Aid batch name/title
    $this->batchTitle = 'GiftAid ' . CRM_Batch_BAO_Batch::generateBatchName();
    $this->batchName = CRM_Utils_String::titleToVar($this->batchTitle, 63);

    if ($this->controller instanceof CRM_Contribute_Controller_Search && $this->isSubmitted()) {
      return;
    }

    list($totalContributionCount, $addedContributionIDs, $alreadyAddedContributionIDs, $notValidContributionIDs)
      = CRM_Civigiftaid_Utils_Contribution::validateContributionToBatch($this->_contributionIds);
    $session = new CRM_Core_Session();
    $session->set($this->batchName, $addedContributionIDs, E::SHORT_NAME);
    $this->assign('selectedContributions', $totalContributionCount);
    $this->assign('totalAddedContributions', count($addedContributionIDs));
    $this->assign('alreadyAddedContributions', count($alreadyAddedContributionIDs));
    $this->assign('notValidContributions', count($notValidContributionIDs));

    // get details of contribution that will be added to this batch.
    $contributionsAddedRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($addedContributionIDs);
    $this->assign('contributionsAddedRows', $contributionsAddedRows);

    // get details of contribution thatare already added to this batch.
    $contributionsAlreadyAddedRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($alreadyAddedContributionIDs);
    $this->assign(
      'contributionsAlreadyAddedRows',
      $contributionsAlreadyAddedRows
    );

    // get details of contribution that are not valid for giftaid
    $contributionsNotValid = CRM_Civigiftaid_Utils_Contribution::getContributionDetails($notValidContributionIDs);
    $this->assign('contributionsNotValid', $contributionsNotValid);
  }

  public function buildQuickForm() {
    if ($this->controller instanceof CRM_Contribute_Controller_Search && $this->isSubmitted()) {
      return;
    }

    $attributes = CRM_Core_DAO::getAttribute('CRM_Batch_DAO_Batch');
    $this->add('text', 'title', E::ts('Batch Title'), $attributes['title'], TRUE);

    $defaults = ['title' => $this->batchTitle];
    $this->setDefaults($defaults);

    $this->addRule(
      'title',
      E::ts('Label already exists in Database.'),
      'objectExists',
      ['CRM_Batch_DAO_Batch', $this->_id, 'title']
    );

    $this->add('textarea', 'description', E::ts('Description:') . ' ', $attributes['description']);

    $this->addDefaultButtons(E::ts('Add to batch'), 'next', 'cancel');
  }

  public function postProcess() {
    $batchParams = [];

    // Get submitted Gift Aid batch title
    if (isset($this->_submitValues['title'])) {
      $this->batchTitle = $this->_submitValues['title'];
    }

    if (empty($this->batchTitle)) {
      CRM_Core_Error::statusBounce(E::ts('Missing name for new GiftAid batch - try creating the batch again?'), NULL, E::ts('GiftAid - Add to Batch'));
    }

    $session = new CRM_Core_Session();
    $contributionIDsToAdd = $session->get($this->batchName, E::SHORT_NAME);

    if (empty($contributionIDsToAdd)) {
      CRM_Core_Error::statusBounce(E::ts('No contributions to add to batch!'), NULL, E::ts('GiftAid - Add to Batch'));
    }

    $transaction = new CRM_Core_Transaction();

    try {
      $createdBatch = \Civi\Api4\Batch::create(FALSE)
        ->addValue('title', $this->batchTitle)
        ->addValue('name', $this->batchName)
        ->addValue('description', $this->_submitValues['description'])
        ->addValue('type_id:name', 'Gift Aid')
        ->addValue('created_id', CRM_Core_Session::getLoggedInContactID())
        ->addValue('created_date', date('YmdHis'))
        ->addValue('status_id:name', 'Exported')
        ->addValue('mode_id:name', 'Manual Batch')
        ->execute();

      // Save current settings for the batch
      CRM_Civigiftaid_BAO_BatchSettings::create(['batch_id' => $createdBatch['id']]);

      OptionValue::create(FALSE)
        ->addValue('option_group_id:name', 'giftaid_batch_name')
        ->addValue('value', $this->batchName)
        ->addValue('label', $this->batchTitle)
        ->addValue('name', $this->batchName)
        ->execute();

      list($total, $addedContributionIDs, $notAddedContributionIDs) =
        CRM_Civigiftaid_Utils_Contribution::addContributionToBatch($contributionIDsToAdd, $createdBatch['id']);

      if (count($addedContributionIDs) === 0) {
        // rollback since there were no contributions added, and we might not want to keep an empty batch
        $transaction->rollback();
        $statusType = 'alert';
        $statusMessage = E::ts(
          'Could not create batch "%1", as there were no valid contribution(s) to be added.',
          [1 => $batchParams['title']]
        );
      }
      else {
        $transaction->commit();
        $statusType = 'success';
        $statusMessage = [
          E::ts('Added Contribution(s) to %1', [1 => $batchParams['title']]),
        ];
        $statusMessage[] = E::ts('Contribution IDs added to batch: %1', [1 => implode(', ', $addedContributionIDs)]);
        if (!empty($notAddedContributionIDs)) {
          $statusMessage[] = E::ts('Contribution IDs already in batch or not valid: %1', [1 => implode(', ', $notAddedContributionIDs)]);
        }
        $statusMessage = implode('<br/>', $statusMessage);
      }
    }
    catch (\Exception $e) {
      $transaction->rollback();
      $statusMessage = E::ts('Error adding contributions to batch: %1', [1 => $e->getMessage()]);
      $statusType = 'alert';
    }

    CRM_Core_Session::setStatus($statusMessage, E::ts('Gift Aid'), $statusType, ['expires' => 0]);
  }

}
