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

class CRM_Civigiftaid_Settings {

  CONST TITLE = E::SHORT_NAME;

  /**
   * Get settings prefix name for this extension
   * @return string
   */
  public static function getPrefix() {
    return E::SHORT_NAME . '_';
  }

  /**
   * Get filter of valid settings for this extension
   * @return array
   */
  public static function getFilter() {
    return ['group' => E::SHORT_NAME];
  }

  /**
   * Get name of setting
   * @param: setting name
   * @prefix: Boolean
   * @return: string
   */
  public static function getName($name, $prefix = false) {
    $ret = str_replace(self::getPrefix(),'',$name);
    if ($prefix) {
      $ret = self::getPrefix().$ret;
    }
    return $ret;
  }

  /**
   * Save settings. Accepts an array of name=>value pairs.  Name can be with or without prefix (it will be added if missing).
   * @param array $values Array of settings and values with or without prefix (eg. array(smartdebit_username => 'test')) to save
   */
  public static function save($settings) {
    foreach ($settings as $name => $value) {
      $prefixedSettings[self::getName($name, TRUE)] = $value;
    }
    civicrm_api3('setting', 'create', $prefixedSettings);
  }

  /**
   * Read setting that has prefix in database and return single value
   *
   * @param string $name
   *
   * @return mixed
   */
  public static function getValue($name) {
    return \Civi::settings()->get(E::SHORT_NAME . "_{$name}");
  }

  /**
   * Get settings
   * @param array $settings of settings (eg. array(username, password))
   *
   * @return array
   */
  public static function get($settings) {
    if ((!is_array($settings) || empty($settings))) {
      return [];
    }

    $domainID = CRM_Core_Config::domainID();

    foreach ($settings as $name) {
      $prefixedSettings[] = self::getName($name, TRUE);
    }
    $settingsResult = civicrm_api3('setting', 'get', ['return' => $prefixedSettings]);
    if (isset($settingsResult['values'][$domainID])) {
      foreach ($settingsResult['values'][$domainID] as $name => $value) {
        $unprefixedSettings[self::getName($name)] = $value;
      }
      return empty($unprefixedSettings) ? NULL : $unprefixedSettings;
    }
    return [];
  }

  /**
   * Used by civigiftaid_financial_types_enabled setting.
   *
   * This is because we want to include inactive financial types, since
   * a recurring contribution could be set up with a ft that is later set
   * inactive (as in not used for new recurs) but the contributions still
   * roll in.
   *
   * Inactive types are listed after the active ones, and are prefixed (Inactive) to help the UI.
   */
  public static function allFinancialTypes() {
    if (!isset(Civi::$statics[__METHOD__])) {
      $rows = \Civi\Api4\FinancialType::get(FALSE)
      ->addSelect('name', 'is_active')
      ->addWhere('is_active', 'IN', [false, true])
      ->addOrderBy('is_active', 'DESC')
      ->addOrderBy('name', 'ASC')
      ->execute()->indexBy('id');
      $map = [];
      foreach ($rows as $id => $row) {
        $map[$id] =
          ($row['is_active'] ? '' : '(Inactive) ')
          . $row['name'];
      }

      Civi::$statics[__METHOD__] = $map;
    }
    return Civi::$statics[__METHOD__];
  }

}
