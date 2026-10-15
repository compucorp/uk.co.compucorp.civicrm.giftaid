<?php
namespace Civi\Api4\Action\GiftAid;

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\CustomValue;
use Civi\Api4\GiftAid;
use Civi\Api4\StateProvince;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use CRM_Civigiftaid_Declaration as Declaration;

/**
 * Tests the GiftAid.createDeclaration API action.
 *
 * @group headless
 */
class CreateDeclarationTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * The UK country id.
   */
  private const UK = 1226;

  /**
   * @var int
   */
  private $contactId;

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    $this->contactId = $this->createIndividual();
  }

  public function tearDown(): void {
    \CRM_Core_Config::singleton()->userPermissionClass->permissions = NULL;
    parent::tearDown();
  }

  /**
   * Without a declaration, a "Yes" declaration is created from today, with
   * the contact's primary address and a formatted UK postcode.
   */
  public function testCreatesDeclaration() {
    $stateId = $this->getUkStateId();
    $this->addAddress([
      'name' => 'Rose Cottage',
      'street_address' => '1 High Street',
      'supplemental_address_1' => 'Upper Floor',
      'supplemental_address_2' => 'Old Town',
      'city' => 'London',
      'state_province_id' => $stateId,
      'postal_code' => 'sw1a1aa',
      'country_id' => self::UK,
    ]);

    $result = $this->createDeclaration(1);

    $declarations = $this->getDeclarations();
    $this->assertCount(1, $declarations);
    $declaration = $declarations[0];
    $this->assertSame(['result' => 'created', 'message' => '', 'id' => $declaration['id']], $result);
    $this->assertEquals($this->contactId, $declaration['entity_id']);
    $this->assertEquals(Declaration::DECLARATION_IS_YES, $declaration['eligible_for_gift_aid']);
    $this->assertSame(date('Y-m-d'), substr($declaration['start_date'], 0, 10));
    $this->assertSame(date('Y-m-d'), substr($declaration['given_date'], 0, 10));
    $this->assertEmpty($declaration['end_date']);
    $this->assertSame('Impact Stack', $declaration['source']);
    $this->assertSame(
      'Rose Cottage, 1 High Street, Upper Floor, Old Town, London, ' . \CRM_Core_PseudoConstant::stateProvince($stateId),
      $declaration['address']
    );
    $this->assertSame('SW1A 1AA', $declaration['post_code']);
  }

  /**
   * The given start and given dates are stored.
   */
  public function testCreatesDeclarationWithGivenDates() {
    $result = GiftAid::createDeclaration(FALSE)
      ->setContactId($this->contactId)
      ->setEligibleForGiftAid(3)
      ->setStartDate('2026-01-15')
      ->setGivenDate('2026-01-10')
      ->setSource('Impact Stack')
      ->execute()
      ->first();

    $this->assertSame('created', $result['result']);
    $declaration = $this->getDeclarations()[0];
    $this->assertEquals(Declaration::DECLARATION_IS_PAST_4_YEARS, $declaration['eligible_for_gift_aid']);
    $this->assertSame('2026-01-15', substr($declaration['start_date'], 0, 10));
    $this->assertSame('2026-01-10', substr($declaration['given_date'], 0, 10));
  }

  /**
   * An overseas contact gets the "X" postcode.
   */
  public function testUsesOverseasPostCode() {
    $this->addAddress([
      'street_address' => '1 Rue de Rivoli',
      'city' => 'Paris',
      'postal_code' => '75001',
      'country_id' => $this->getCountryId('FR'),
    ]);

    $this->createDeclaration(1);

    $declaration = $this->getDeclarations()[0];
    $this->assertSame('1 Rue de Rivoli, Paris', $declaration['address']);
    $this->assertSame('X', $declaration['post_code']);
  }

  /**
   * A current "Yes" declaration is valid when "Yes" (either kind) is
   * declared: nothing is created, and its id is returned.
   *
   * @dataProvider provideEligibilities
   *
   * @param int $eligibility
   */
  public function testDoesNothingWhenValidDeclarationExists($eligibility) {
    $existingId = $this->addDeclaration(Declaration::DECLARATION_IS_YES, '-1 year');

    $result = $this->createDeclaration($eligibility);

    $this->assertSame(['result' => 'noop', 'message' => '', 'id' => $existingId], $result);
    $this->assertCount(1, $this->getDeclarations());
  }

  /**
   * @return array
   */
  public function provideEligibilities() {
    return [
      'yes' => [1],
      'yes and past 4 years' => [3],
    ];
  }

  /**
   * An expired declaration is not valid: a new one is created.
   */
  public function testCreatesDeclarationWhenDeclarationHasExpired() {
    $expiredId = $this->addDeclaration(Declaration::DECLARATION_IS_YES, '-2 years', '-1 day');

    $result = $this->createDeclaration(1);

    $this->assertSame('created', $result['result']);
    $this->assertNotEquals($expiredId, $result['id']);
    $this->assertCount(2, $this->getDeclarations());
  }

  /**
   * A future declaration is not current: a new one is created.
   */
  public function testCreatesDeclarationWhenOnlyFutureDeclarationExists() {
    $this->addDeclaration(Declaration::DECLARATION_IS_YES, '+1 month');

    $result = $this->createDeclaration(1);

    $this->assertSame('created', $result['result']);
    $this->assertCount(2, $this->getDeclarations());
  }

  /**
   * A current "No" declaration is ended when "Yes" is declared, and a "Yes"
   * declaration created.
   */
  public function testReplacesCurrentNoDeclarationWithYes() {
    $noId = $this->addDeclaration(Declaration::DECLARATION_IS_NO, '-1 year');

    $result = $this->createDeclaration(1);

    $this->assertSame('created', $result['result']);
    $declarations = array_column($this->getDeclarations(), NULL, 'id');
    $this->assertCount(2, $declarations);
    $this->assertSame(date('Y-m-d'), substr($declarations[$noId]['end_date'], 0, 10));
    $this->assertEquals(Declaration::DECLARATION_IS_YES, $declarations[$result['id']]['eligible_for_gift_aid']);
    $this->assertEmpty($declarations[$result['id']]['end_date']);
    $this->assertSame(date('Y-m-d'), substr($declarations[$result['id']]['start_date'], 0, 10));
    $this->assertSame(date('Y-m-d'), substr($declarations[$result['id']]['given_date'], 0, 10));
  }

  /**
   * A current "No" declaration is valid when "No" is declared.
   */
  public function testDoesNothingWhenNoIsDeclaredAgain() {
    $noId = $this->addDeclaration(Declaration::DECLARATION_IS_NO, '-1 year');

    $result = $this->createDeclaration(0);

    $this->assertSame(['result' => 'noop', 'message' => '', 'id' => $noId], $result);
    $this->assertCount(1, $this->getDeclarations());
  }

  /**
   * A current "Yes" declaration is ended when "No" is declared (e.g. the
   * donor withdraws it), and a "No" declaration created.
   */
  public function testEndsYesDeclarationWhenNoIsDeclared() {
    $yesId = $this->addDeclaration(Declaration::DECLARATION_IS_YES, '-1 year');

    $result = $this->createDeclaration(0);

    $this->assertSame('created', $result['result']);
    $declarations = array_column($this->getDeclarations(), NULL, 'id');
    $this->assertCount(2, $declarations);
    $this->assertSame(date('Y-m-d'), substr($declarations[$yesId]['end_date'], 0, 10));
    $this->assertEquals(Declaration::DECLARATION_IS_NO, $declarations[$result['id']]['eligible_for_gift_aid']);
  }

  /**
   * Validity is checked on the start date: a "Yes" declaration starting
   * after it does not cover it, so a declaration is created from the start
   * date (e.g. the date of an earlier donation).
   */
  public function testCreatesDeclarationWhenValidDeclarationStartsAfterStartDate() {
    $this->addDeclaration(Declaration::DECLARATION_IS_YES, 'today');
    $startDate = date('Y-m-d', strtotime('-3 days'));

    $result = GiftAid::createDeclaration(FALSE)
      ->setContactId($this->contactId)
      ->setEligibleForGiftAid(1)
      ->setStartDate($startDate)
      ->execute()
      ->first();

    $this->assertSame('created', $result['result']);
    $declarations = array_column($this->getDeclarations(), NULL, 'id');
    $this->assertCount(2, $declarations);
    $this->assertSame($startDate, substr($declarations[$result['id']]['start_date'], 0, 10));
    $this->assertSame($startDate, substr($declarations[$result['id']]['given_date'], 0, 10));
  }

  /**
   * Validity is checked on the start date: an expired declaration that was
   * in force then covers it.
   */
  public function testDoesNothingWhenDeclarationWasValidOnStartDate() {
    $expiredId = $this->addDeclaration(Declaration::DECLARATION_IS_YES, '-1 year', '-1 day');

    $result = GiftAid::createDeclaration(FALSE)
      ->setContactId($this->contactId)
      ->setEligibleForGiftAid(1)
      ->setStartDate(date('Y-m-d', strtotime('-3 days')))
      ->execute()
      ->first();

    $this->assertSame(['result' => 'noop', 'message' => '', 'id' => $expiredId], $result);
    $this->assertCount(1, $this->getDeclarations());
  }

  /**
   * A partial declaration (no start date, no postcode) is superseded: the
   * declared eligibility is recorded, and the partial one deleted.
   */
  public function testSupersedesPartialDeclaration() {
    $partialId = (int) CustomValue::create('gift_aid_declaration', FALSE)
      ->addValue('entity_id', $this->contactId)
      ->addValue('eligible_for_gift_aid', Declaration::DECLARATION_IS_NO)
      ->execute()
      ->first()['id'];

    $result = $this->createDeclaration(1);

    $this->assertSame('created', $result['result']);
    $this->assertNotEquals($partialId, $result['id']);
    $declarations = $this->getDeclarations();
    $this->assertCount(1, $declarations);
    $this->assertEquals(Declaration::DECLARATION_IS_YES, $declarations[0]['eligible_for_gift_aid']);
  }

  /**
   * The given address and postcode are used instead of the primary address,
   * with the postcode formatted.
   */
  public function testUsesGivenAddress() {
    $this->addAddress([
      'street_address' => '1 Old Street',
      'city' => 'Leeds',
      'postal_code' => 'LS1 1AA',
      'country_id' => self::UK,
    ]);

    GiftAid::createDeclaration(FALSE)
      ->setContactId($this->contactId)
      ->setEligibleForGiftAid(1)
      ->setAddress('2 New Street, London')
      ->setPostCode('sw1a1aa')
      ->execute();

    $declaration = $this->getDeclarations()[0];
    $this->assertSame('2 New Street, London', $declaration['address']);
    $this->assertSame('SW1A 1AA', $declaration['post_code']);
  }

  /**
   * An overseas postcode ("X") is given as is.
   */
  public function testUsesGivenOverseasPostCode() {
    GiftAid::createDeclaration(FALSE)
      ->setContactId($this->contactId)
      ->setEligibleForGiftAid(1)
      ->setAddress('1 Rue de Rivoli, Paris')
      ->setPostCode('X')
      ->execute();

    $declaration = $this->getDeclarations()[0];
    $this->assertSame('1 Rue de Rivoli, Paris', $declaration['address']);
    $this->assertSame('X', $declaration['post_code']);
  }

  /**
   * Invalid parameters return an error, and nothing is written.
   *
   * @dataProvider provideInvalidParams
   *
   * @param array $params
   * @param string $message
   */
  public function testReturnsErrorForInvalidParams(array $params, $message) {
    $params += [
      'contactId' => $this->contactId,
      'eligibleForGiftAid' => 1,
    ];
    if ($params['contactId'] === 'organization') {
      $params['contactId'] = Contact::create(FALSE)
        ->addValue('contact_type', 'Organization')
        ->addValue('organization_name', 'Test Org')
        ->execute()
        ->first()['id'];
    }
    $params = array_filter($params, function ($value) {
      return $value !== 'missing';
    });

    $result = civicrm_api4('GiftAid', 'createDeclaration', ['checkPermissions' => FALSE] + $params)->first();

    $this->assertSame('error', $result['result']);
    $this->assertStringContainsString($message, $result['message']);
    $this->assertNull($result['id']);
    $this->assertCount(0, $this->getDeclarations());
  }

  /**
   * @return array
   */
  public function provideInvalidParams() {
    return [
      'missing contact' => [['contactId' => 'missing'], '"contactId" is required'],
      'unknown contact' => [['contactId' => 999999], 'does not exist'],
      'organization' => [['contactId' => 'organization'], 'not an Individual'],
      'missing eligibility' => [['eligibleForGiftAid' => 'missing'], '"eligibleForGiftAid" is required'],
      'unknown eligibility' => [['eligibleForGiftAid' => 2], '"eligibleForGiftAid" is required'],
      'invalid start date' => [['startDate' => 'not a date'], '"startDate" must be a date'],
      'invalid given date' => [['givenDate' => 'not a date'], '"givenDate" must be a date'],
    ];
  }

  /**
   * A deleted contact cannot get a declaration.
   */
  public function testReturnsErrorForDeletedContact() {
    Contact::delete(FALSE)->addWhere('id', '=', $this->contactId)->execute();

    $result = $this->createDeclaration(1);

    $this->assertSame('error', $result['result']);
    $this->assertStringContainsString('does not exist', $result['message']);
  }

  /**
   * Creating a declaration needs "edit all contacts".
   */
  public function testRequiresEditAllContactsPermission() {
    \CRM_Core_Config::singleton()->userPermissionClass->permissions = ['access CiviCRM'];

    $this->expectException(UnauthorizedException::class);

    GiftAid::createDeclaration()
      ->setContactId($this->contactId)
      ->setEligibleForGiftAid(1)
      ->execute();
  }

  /**
   * With "edit all contacts", the declaration is created.
   */
  public function testCreatesDeclarationWithEditAllContactsPermission() {
    \CRM_Core_Config::singleton()->userPermissionClass->permissions = ['access CiviCRM', 'edit all contacts'];

    $result = GiftAid::createDeclaration()
      ->setContactId($this->contactId)
      ->setEligibleForGiftAid(1)
      ->execute()
      ->first();

    $this->assertSame('created', $result['result']);
  }

  /**
   * @dataProvider providePostCodes
   *
   * @param string $postCode
   * @param string $expected
   */
  public function testFormatsPostCode($postCode, $expected) {
    $this->assertSame($expected, CreateDeclaration::formatPostCode($postCode));
  }

  /**
   * @return array
   */
  public function providePostCodes() {
    return [
      'already formatted' => ['SW1A 1AA', 'SW1A 1AA'],
      'lower case without space' => ['sw1a1aa', 'SW1A 1AA'],
      'extra spaces' => [' m1  1ae ', 'M1 1AE'],
      'punctuation' => ['B33-8TH', 'B33 8TH'],
      'overseas' => ['x', 'X'],
      'empty' => ['', ''],
    ];
  }

  /**
   * @param int $eligibility
   *
   * @return array
   *   The result record.
   */
  private function createDeclaration($eligibility) {
    return GiftAid::createDeclaration(FALSE)
      ->setContactId($this->contactId)
      ->setEligibleForGiftAid($eligibility)
      ->setSource('Impact Stack')
      ->execute()
      ->first();
  }

  /**
   * @return int
   */
  private function createIndividual() {
    return (int) Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Gift')
      ->addValue('last_name', 'Aider')
      ->execute()
      ->first()['id'];
  }

  /**
   * @param array $values
   */
  private function addAddress(array $values) {
    Address::create(FALSE)
      ->setValues($values + [
        'contact_id' => $this->contactId,
        'location_type_id:name' => 'Home',
        'is_primary' => TRUE,
      ])
      ->execute();
  }

  /**
   * @param int $eligibility
   * @param string $start
   *   Start date, relative to now.
   * @param string|null $end
   *   End date, relative to now.
   *
   * @return int
   *   The declaration id.
   */
  private function addDeclaration($eligibility, $start, $end = NULL) {
    $create = CustomValue::create('gift_aid_declaration', FALSE)
      ->addValue('entity_id', $this->contactId)
      ->addValue('eligible_for_gift_aid', $eligibility)
      ->addValue('start_date', date('Y-m-d', strtotime($start)))
      ->addValue('given_date', date('Y-m-d', strtotime($start)))
      ->addValue('address', 'Existing address')
      ->addValue('post_code', 'AB1 2CD');
    if ($end !== NULL) {
      $create->addValue('end_date', date('Y-m-d', strtotime($end)));
    }

    return (int) $create->execute()->first()['id'];
  }

  /**
   * @return array
   */
  private function getDeclarations() {
    return CustomValue::get('gift_aid_declaration', FALSE)
      ->addWhere('entity_id', '=', $this->contactId)
      ->addOrderBy('id')
      ->execute()
      ->getArrayCopy();
  }

  /**
   * @return int
   */
  private function getUkStateId() {
    return (int) StateProvince::get(FALSE)
      ->addSelect('id')
      ->addWhere('country_id', '=', self::UK)
      ->setLimit(1)
      ->execute()
      ->first()['id'];
  }

  /**
   * @param string $isoCode
   *
   * @return int
   */
  private function getCountryId($isoCode) {
    return (int) \Civi\Api4\Country::get(FALSE)
      ->addSelect('id')
      ->addWhere('iso_code', '=', $isoCode)
      ->execute()
      ->first()['id'];
  }

}
