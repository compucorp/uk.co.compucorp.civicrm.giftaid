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

use Civi\Api4\CustomField;

/**
 * Class CRM_Civigiftaid_Utils
 */
class CRM_Civigiftaid_Utils {

  /**
   * Return the field ID for $fieldName custom field
   *
   * @param string $fieldName
   * @param string $fieldGroup
   * @param bool $fullString
   *
   * @return mixed
   * @throws \CRM_Core_Exception
   */
  public static function getCustomByName(string $fieldName, string $fieldGroup, bool $fullString = TRUE) {
    if (!isset(Civi::$statics[__CLASS__][$fieldGroup][$fieldName])) {
      $field = CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', $fieldGroup)
        ->addWhere('name', '=', $fieldName)
        ->execute()
        ->first();

      if (!empty($field['id'])) {
        Civi::$statics[__CLASS__][$fieldGroup][$fieldName]['id'] = $field['id'];
        Civi::$statics[__CLASS__][$fieldGroup][$fieldName]['string'] = 'custom_' . $field['id'];
      }
    }

    if ($fullString) {
      return Civi::$statics[__CLASS__][$fieldGroup][$fieldName]['string'];
    }
    return Civi::$statics[__CLASS__][$fieldGroup][$fieldName]['id'];
  }

}
