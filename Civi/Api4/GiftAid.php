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

namespace Civi\Api4;

/**
 * Gift Aid actions.
 *
 * @searchable none
 * @package Civi\Api4
 */
class GiftAid extends Generic\AbstractEntity {

  /**
   * Creates a Gift Aid declaration for a contact, unless the contact already
   * has a valid one.
   *
   * @param bool $checkPermissions
   *
   * @return \Civi\Api4\Action\GiftAid\CreateDeclaration
   */
  public static function createDeclaration($checkPermissions = TRUE) {
    return (new Action\GiftAid\CreateDeclaration(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   *
   * @return \Civi\Api4\Generic\BasicGetFieldsAction
   */
  public static function getFields($checkPermissions = TRUE) {
    return (new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function () {
      return [];
    }))->setCheckPermissions($checkPermissions);
  }

  /**
   * A declaration is custom data on the Individual, so creating one is
   * editing the contact.
   *
   * @return array
   */
  public static function permissions() {
    return [
      'meta' => ['access CiviCRM'],
      'default' => ['administer CiviCRM'],
      'createDeclaration' => ['edit all contacts'],
    ];
  }

}
