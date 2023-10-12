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
 * Class CRM_Civigiftaid_Check
 */
class CRM_Civigiftaid_Check {

  /**
   * @var string
   */
  const MIN_VERSION_GIFTAIDONLINE = '1.9';

  /**
   * @var array
   */
  private $messages;

  /**
   * CRM_Stripe_Check constructor.
   *
   * @param $messages
   */
  public function __construct($messages) {
    $this->messages = $messages;
  }

  /**
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public function checkRequirements() {
    $this->checkExtensionGiftaidOnline();
    return $this->messages;
  }

  /**
   * @param string $extensionName
   * @param string $minVersion
   * @param string $actualVersion
   */
  private function requireExtensionMinVersion($extensionName, $minVersion, $actualVersion) {
    $actualVersionModified = $actualVersion;
    if (substr($actualVersion, -4) === '-dev') {
      $message = new CRM_Utils_Check_Message(
        __FUNCTION__ . $extensionName . E::SHORT_NAME . '_requirements_dev',
        E::ts('You are using a development version of %1 extension.',
          [1 => $extensionName]),
        E::ts('%1: Development version', [1 => $extensionName]),
        \Psr\Log\LogLevel::WARNING,
        'fa-code'
      );
      $this->messages[] = $message;
      $actualVersionModified = substr($actualVersion, 0, -4);
    }

    if (version_compare($actualVersionModified, $minVersion) === -1) {
      $message = new CRM_Utils_Check_Message(
        __FUNCTION__ . $extensionName . E::SHORT_NAME . '_requirements',
        E::ts('The %1 extension requires the %2 extension version %3 or greater but your system has version %4.',
          [
            1 => ucfirst(E::SHORT_NAME),
            2 => $extensionName,
            3 => $minVersion,
            4 => $actualVersion
          ]),
        E::ts('%1: Missing Requirements', [1 => ucfirst(E::SHORT_NAME)]),
        \Psr\Log\LogLevel::ERROR,
        'fa-exclamation-triangle'
      );
      $message->addAction(
        E::ts('Upgrade now'),
        NULL,
        'href',
        ['path' => 'civicrm/admin/extensions', 'query' => ['action' => 'update', 'id' => $extensionName, 'key' => $extensionName]]
      );
      $this->messages[] = $message;
    }
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  private function checkExtensionGiftaidOnline() {
    // giftaidonline: required. Requires min version
    $extensionName = 'uk.co.vedaconsulting.module.giftaidonline';
    $extensions = civicrm_api3('Extension', 'get', [
      'full_name' => $extensionName,
    ]);

    if (isset($extensions['id']) && $extensions['values'][$extensions['id']]['status'] === 'installed') {
      $this->requireExtensionMinVersion($extensionName, self::MIN_VERSION_GIFTAIDONLINE, $extensions['values'][$extensions['id']]['version']);
    }
  }

}
