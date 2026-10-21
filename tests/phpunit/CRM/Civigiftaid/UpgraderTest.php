<?php

use Civi\Api4\CustomField;
use Civi\Test\HeadlessInterface;
use CRM_Civigiftaid_ExtensionUtil as E;

/**
 * Tests for the contribution eligible_for_gift_aid field fixes in
 * CRM_Civigiftaid_Upgrader.
 *
 * @group headless
 */
class CRM_Civigiftaid_UpgraderTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface {

  /**
   * Original values of the eligible_for_gift_aid fields, keyed by field ID.
   *
   * @var array
   */
  private $originalFields = [];

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    foreach ([$this->getContributionField(), $this->getDeclarationField()] as $field) {
      $this->originalFields[$field['id']] = $field;
    }
  }

  public function tearDown(): void {
    foreach ($this->originalFields as $id => $field) {
      CustomField::update(FALSE)
        ->addWhere('id', '=', $id)
        ->addValue('is_required', $field['is_required'])
        ->addValue('default_value', $field['default_value'])
        ->execute();
    }
    parent::tearDown();
  }

  public function testContributionFieldIsOptionalAfterInstall(): void {
    $field = $this->getContributionField();

    $this->assertFalse($field['is_required']);
    $this->assertEmpty($field['default_value']);
  }

  public function testUpgrade3121MakesRequiredContributionFieldOptional(): void {
    $this->makeContributionFieldRequired();

    $this->assertTrue($this->getUpgrader()->upgrade_3121());

    $field = $this->getContributionField();
    $this->assertFalse($field['is_required']);
    $this->assertEmpty($field['default_value']);
  }

  public function testUpgrade3122MakesRequiredContributionFieldOptional(): void {
    $this->makeContributionFieldRequired();

    $this->assertTrue($this->getUpgrader()->upgrade_3122());

    $field = $this->getContributionField();
    $this->assertFalse($field['is_required']);
    $this->assertEmpty($field['default_value']);
  }

  public function testMakeContributionEligibilityFieldOptionalUpdatesOnlyContributionField(): void {
    $this->makeContributionFieldRequired();
    CustomField::update(FALSE)
      ->addWhere('id', '=', $this->getDeclarationField()['id'])
      ->addValue('is_required', TRUE)
      ->execute();

    $count = $this->getUpgrader()->makeContributionEligibilityFieldOptional();

    $this->assertEquals(1, $count);
    $this->assertFalse($this->getContributionField()['is_required']);
    $this->assertTrue($this->getDeclarationField()['is_required']);
  }

  private function makeContributionFieldRequired(): void {
    CustomField::update(FALSE)
      ->addWhere('id', '=', $this->getContributionField()['id'])
      ->addValue('is_required', TRUE)
      ->addValue('default_value', '1')
      ->execute();
    $field = $this->getContributionField();
    $this->assertTrue($field['is_required'], 'Fixture: contribution field should be required');
    $this->assertEquals('1', $field['default_value'], 'Fixture: contribution field should have a default');
  }

  private function getContributionField(): array {
    return $this->getEligibilityField('gift_aid');
  }

  private function getDeclarationField(): array {
    return $this->getEligibilityField('gift_aid_declaration');
  }

  private function getEligibilityField(string $customGroupName): array {
    return CustomField::get(FALSE)
      ->addSelect('id', 'is_required', 'default_value')
      ->addWhere('name', '=', 'eligible_for_gift_aid')
      ->addWhere('custom_group_id:name', '=', $customGroupName)
      ->execute()
      ->single();
  }

  private function getUpgrader(): CRM_Civigiftaid_Upgrader {
    $upgrader = new CRM_Civigiftaid_Upgrader();
    $upgrader->init(['key' => E::LONG_NAME]);
    return $upgrader;
  }

}
