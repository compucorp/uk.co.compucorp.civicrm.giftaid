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

namespace Civi\Api4\Action\GiftAid;

use Civi\Api4\Contact;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use CRM_Civigiftaid_Declaration as Declaration;

/**
 * Creates a Gift Aid declaration for a contact, unless the contact already
 * has a valid one.
 *
 * A valid declaration is one in force on the start date that already says
 * the same: "Yes" (or "Yes, and for donations made in the past 4 years")
 * when "Yes" is declared, "No" when "No" is declared. A declaration in force
 * that says the opposite is ended on the start date and replaced (e.g. a
 * donor withdrawing their declaration).
 *
 * A partial declaration (no start date and no postcode, as saved by a
 * profile with the Gift Aid field) is superseded: it is deleted.
 *
 * The address and postcode are the given ones, or else the contact's
 * primary address.
 *
 * Returns one record:
 *  - result: "created", "noop" (the contact already has a valid declaration)
 *    or "error" (invalid parameters or any other error, nothing is written).
 *  - message: the error message, otherwise empty.
 *  - id: the id of the created or existing valid declaration, otherwise NULL.
 *
 * @method $this setContactId(int $contactId)
 * @method int|null getContactId()
 * @method $this setEligibleForGiftAid(int $eligibleForGiftAid)
 * @method int|null getEligibleForGiftAid()
 * @method $this setStartDate(string $startDate)
 * @method string|null getStartDate()
 * @method $this setGivenDate(string $givenDate)
 * @method string|null getGivenDate()
 * @method $this setSource(string $source)
 * @method string|null getSource()
 * @method $this setAddress(string $address)
 * @method string|null getAddress()
 * @method $this setPostCode(string $postCode)
 * @method string|null getPostCode()
 */
class CreateDeclaration extends AbstractAction {

  public const RESULT_CREATED = 'created';
  public const RESULT_NOOP = 'noop';
  public const RESULT_ERROR = 'error';

  /**
   * Error code of the validation errors (returned, not logged).
   */
  private const INVALID_PARAMETER = 'invalid_parameter';

  /**
   * Contact ID (required).
   *
   * Not marked as required, so that a missing value is returned as an
   * "error" result rather than thrown.
   *
   * @var int
   */
  protected $contactId;

  /**
   * Eligible for Gift Aid (required): 1 = Yes, 3 = Yes and for donations
   * made in the past 4 years, 0 = No.
   *
   * @var int
   * @options 0,1,3
   */
  protected $eligibleForGiftAid;

  /**
   * Declaration start date. Defaults to today. Pass the donation date when
   * the declaration is made with a donation, so that it covers it.
   *
   * @var string
   */
  protected $startDate;

  /**
   * Date the declaration was given. Defaults to the start date.
   *
   * @var string
   */
  protected $givenDate;

  /**
   * Source of the declaration, e.g. "Impact Stack".
   *
   * @var string
   */
  protected $source;

  /**
   * Address for HMRC, e.g. "1 High Street, London". When neither address nor
   * postCode is given, both come from the contact's primary address.
   *
   * @var string
   */
  protected $address;

  /**
   * Postcode: a UK postcode (formatted as "SW1A 1AA") or "X" for an overseas
   * address. When neither address nor postCode is given, both come from the
   * contact's primary address.
   *
   * @var string
   */
  protected $postCode;

  /**
   * @param \Civi\Api4\Generic\Result $result
   */
  public function _run(Result $result) {
    try {
      $this->validate();
      $current = Declaration::getDeclaration($this->contactId, $this->startDate);
      if ($this->isValid($current)) {
        $result[] = $this->buildResult(self::RESULT_NOOP, '', $current['id']);
        return;
      }
      $result[] = $this->buildResult(self::RESULT_CREATED, '', $this->create());
    }
    catch (\Throwable $e) {
      if ($e instanceof \CRM_Core_Exception && $e->getErrorCode() === self::INVALID_PARAMETER) {
        $result[] = $this->buildResult(self::RESULT_ERROR, $e->getMessage(), NULL);
        return;
      }
      // Logged here, with the trace: the caller only gets the message.
      \Civi::log()->error('GiftAid.createDeclaration failed for contact {contact_id}: {message}', [
        'contact_id' => $this->contactId,
        'message' => $e->getMessage(),
        'exception' => $e,
      ]);
      $result[] = $this->buildResult(self::RESULT_ERROR, $e->getMessage(), NULL);
    }
  }

  /**
   * Validates the parameters, and normalises the dates.
   *
   * @throws \CRM_Core_Exception
   */
  private function validate() {
    if (empty($this->contactId) || !\CRM_Utils_Rule::positiveInteger($this->contactId)) {
      throw new \CRM_Core_Exception('Parameter "contactId" is required and must be a contact ID.', self::INVALID_PARAMETER);
    }
    $this->contactId = (int) $this->contactId;

    $eligibilities = [Declaration::DECLARATION_IS_NO, Declaration::DECLARATION_IS_YES, Declaration::DECLARATION_IS_PAST_4_YEARS];
    if (!isset($this->eligibleForGiftAid) || !\CRM_Utils_Rule::integer($this->eligibleForGiftAid)
      || !in_array((int) $this->eligibleForGiftAid, $eligibilities, TRUE)) {
      throw new \CRM_Core_Exception('Parameter "eligibleForGiftAid" is required and must be 0 (No), 1 (Yes) or 3 (Yes and for donations made in the past 4 years).', self::INVALID_PARAMETER);
    }
    $this->eligibleForGiftAid = (int) $this->eligibleForGiftAid;

    $this->startDate = $this->normaliseDate('startDate', $this->startDate) ?? date('Ymd') . '000000';
    $this->givenDate = $this->normaliseDate('givenDate', $this->givenDate);

    foreach (['source', 'address', 'postCode'] as $param) {
      if (isset($this->$param) && !is_string($this->$param)) {
        throw new \CRM_Core_Exception("Parameter \"$param\" must be a string.", self::INVALID_PARAMETER);
      }
    }

    $contact = Contact::get(FALSE)
      ->addSelect('contact_type')
      ->addWhere('id', '=', $this->contactId)
      ->addWhere('is_deleted', '=', FALSE)
      ->execute()
      ->first();
    if (empty($contact)) {
      throw new \CRM_Core_Exception("Contact {$this->contactId} does not exist.", self::INVALID_PARAMETER);
    }
    if ($contact['contact_type'] !== 'Individual') {
      throw new \CRM_Core_Exception("Contact {$this->contactId} is not an Individual: Gift Aid declarations are for individuals only.", self::INVALID_PARAMETER);
    }
  }

  /**
   * @param string $param
   * @param mixed $value
   *
   * @return string|null
   *   The date as YmdHis, or NULL when not given.
   *
   * @throws \CRM_Core_Exception
   */
  private function normaliseDate($param, $value) {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    $timestamp = is_string($value) ? strtotime($value) : FALSE;
    if ($timestamp === FALSE) {
      throw new \CRM_Core_Exception("Parameter \"$param\" must be a date.", self::INVALID_PARAMETER);
    }

    return date('YmdHis', $timestamp);
  }

  /**
   * Whether the declaration in force on the start date already says the
   * same as the request: "Yes" (either kind) for a "Yes", "No" for a "No".
   *
   * @param array $current
   *   The declaration in force on the start date, or an empty array.
   *
   * @return bool
   */
  private function isValid(array $current) {
    if (empty($current['id'])) {
      return FALSE;
    }

    return self::isYes($current['eligible_for_gift_aid']) === self::isYes($this->eligibleForGiftAid);
  }

  /**
   * @param int $eligibility
   *
   * @return bool
   */
  private static function isYes($eligibility) {
    return in_array((int) $eligibility, [Declaration::DECLARATION_IS_YES, Declaration::DECLARATION_IS_PAST_4_YEARS], TRUE);
  }

  /**
   * Creates the declaration (ending the opposite one in force, if any), all
   * or nothing.
   *
   * @return int
   *   The declaration id.
   *
   * @throws \CRM_Core_Exception
   */
  private function create() {
    $id = NULL;
    \CRM_Core_Transaction::create(TRUE)->run(function () use (&$id) {
      if ($this->address !== NULL || $this->postCode !== NULL) {
        $address = (string) $this->address;
        $postCode = (string) $this->postCode;
      }
      else {
        [$address, $postCode] = Declaration::getAddressAndPostalCode($this->contactId);
      }
      $params = [
        'entity_id' => $this->contactId,
        'eligible_for_gift_aid' => $this->eligibleForGiftAid,
        'start_date' => $this->startDate,
        'given_date' => $this->givenDate ?? $this->startDate,
        'address' => $address,
        'post_code' => self::formatPostCode($postCode),
      ];
      if ($this->source !== NULL && $this->source !== '') {
        $params['source'] = $this->source;
      }
      // An explicit declaration supersedes a partial one, which
      // setDeclaration() would otherwise merge over these values (its id and
      // eligibility win).
      Declaration::deletePartialDeclaration($this->contactId);
      Declaration::setDeclaration($params);

      $declaration = Declaration::getDeclaration($this->contactId, $this->startDate);
      if (empty($declaration['id'])) {
        throw new \CRM_Core_Exception("The Gift Aid declaration for contact {$this->contactId} was not created.");
      }
      $id = $declaration['id'];
    });

    return $id;
  }

  /**
   * Formats a UK postcode as HMRC expects it, e.g. "SW1A 1AA". "X" (overseas)
   * and empty postcodes are kept as they are.
   *
   * @param string $postCode
   *
   * @return string
   */
  public static function formatPostCode($postCode) {
    $postCode = strtoupper(trim((string) $postCode));
    if ($postCode === '' || $postCode === 'X') {
      return $postCode;
    }
    $postCode = preg_replace('/[^A-Z0-9]/', '', $postCode);
    if (strlen($postCode) <= 3) {
      return $postCode;
    }

    return substr($postCode, 0, -3) . ' ' . substr($postCode, -3);
  }

  /**
   * @param string $result
   * @param string $message
   * @param int|null $id
   *
   * @return array
   */
  private function buildResult($result, $message, $id) {
    return [
      'result' => $result,
      'message' => $message,
      'id' => $id,
    ];
  }

}
