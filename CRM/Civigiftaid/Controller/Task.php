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
 * Class CRM_Civigiftaid_Controller_Task
 *
 * This class just provides a wrapper on CRM_Contribute_Form_Task
 * to enable loading contribution IDs from request instead of
 * search controller if on a standalone page
 * (TODO: move upstream into CRM_Contribute_Form_Task?)
 */
class CRM_Civigiftaid_Controller_Task extends CRM_Contribute_Controller_Task {


  public const CIVICRM_GIFTAID_ADD_TASKID = 1435;
  public const CIVICRM_GIFTAID_REMOVE_TASKID = 1436;
  /**
   * Get the available tasks
   *
   * @return array
   */
  public function getAvailableTasks(): array {
    return self::tasks();
  }

  /**
   * Get the available tasks
   *
   * @return array
   */
  public static function tasks(): array {
    return [
      self::CIVICRM_GIFTAID_ADD_TASKID => [
        'key'    => 'addtobatch',
        'title'  => E::ts('Add to Gift Aid batch'),
        'class'  => 'CRM_Civigiftaid_Form_Task_AddToBatch',
        'url'    => 'civicrm/ukgiftaid/task?task_item=addtobatch',
        'result' => FALSE
      ],
      self::CIVICRM_GIFTAID_REMOVE_TASKID => [
        'key'    => 'removefrombatch',
        'title'  => E::ts('Remove from Gift Aid batch'),
        'class'  => 'CRM_Civigiftaid_Form_Task_RemoveFromBatch',
        'url'    => 'civicrm/ukgiftaid/task?task_item=removefrombatch',
        'result' => FALSE
      ],
    ];
  }
}
