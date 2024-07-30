<?php
namespace Civi\Api4\Action\Contribution;

use CRM_Civigiftaid_ExtensionUtil as E;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\LineItem;
use Civi\Api4\CustomValue;
use Civi\Api4\PriceFieldValue;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 */
class UpdateGiftAidTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {

  /** @var array */
  protected $contacts = [];
  /** @var array */
  protected $contributions = [];
  /** @var array */
  protected $declarations = [];

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function testUpdatesFromNoToYes() {
    $this->setupFixture();
    $contactID = $this->contacts[0]['id'];

    // Create a simple donation contribution that is eligible.
    $donationSimple = Contribution::create(FALSE)
    ->addValue('contact_id', $contactID)
    ->addValue('financial_type_id', 1)
    ->addValue('total_amount', 10)
    ->execute()->first();
    // Check it was automatically given a donation line item as we want to test the line-items codepath.
    $this->assertEquals(1, LineItem::get(FALSE)->addWhere('contribution_id', '=', $donationSimple['id'])
      ->addWhere('financial_type_id', '=', 1)->execute()->count());

    // Check that nothing changes - it's should be all correct.
    $result = Contribution::UpdateGiftAid(FALSE)
    ->addWhere('id', '=', $donationSimple['id'])
    ->execute()->getArrayCopy();
    $this->assertEmpty($result);

    // Manually set this contribution as Not Eligible for GA.
    // (nb. this triggers a recalc to zero)
    $r = Contribution::update(FALSE)
      ->addWhere('id', '=', $donationSimple['id'])
      ->addValue('gift_aid.eligible_for_gift_aid', FALSE)
      ->execute()->getArrayCopy();

    // Check that the contribution is set back eligible (and amounts are recalc'ed) by UpdateGiftAid
    $result = Contribution::UpdateGiftAid(FALSE)->addWhere('id', '=', $donationSimple['id'])->execute()->getArrayCopy();
    $this->assertCount(1, $result);
    $this->assertEquals($donationSimple['id'], $result[0]['id']);
    $this->assertEquals(1, $result[0]['gift_aid.eligible_for_gift_aid']);

    $now = $this->fetchCn($donationSimple['id']);
    $this->assertEquals($donationSimple['id'], $now['id']);
    $this->assertEquals(1, $now['gift_aid.eligible_for_gift_aid']);
    $this->assertEquals(10, $now['gift_aid.amount']);
    $this->assertEquals(2.5, $now['gift_aid.gift_aid_amount']);
  }

  public function testUpdateFromYesToNo() {
    $this->setupFixture();
    $contactID = $this->contacts[0]['id'];

    // Create a simple donation contribution that is NOT eligible.
    $donationSimple = Contribution::create(FALSE)
    ->addValue('contact_id', $contactID)
    ->addValue('financial_type_id', 4)
    ->addValue('total_amount', 10)
    ->execute()->first();

    // Check that nothing changes - it's should be all correct.
    $result = Contribution::UpdateGiftAid(FALSE)
    ->addWhere('id', '=', $donationSimple['id'])
    ->execute()->getArrayCopy();
    $this->assertEmpty($result);

    // Manually set it eligible_for_gift_aid.
    $r = Contribution::update(FALSE)
    ->addWhere('id', '=', $donationSimple['id'])
    ->addValue('gift_aid.eligible_for_gift_aid', 1)
    ->addValue('gift_aid.gift_aid_amount', 1)
    ->addValue('gift_aid.amount', 0)
    ->execute();

    $now = $this->fetchCn($donationSimple['id']);
    // When we update the contribution it will automatically recalculate the amounts and set eligibility to 0 if
    //   the amounts are 0.
    $this->assertEquals(0, $now['gift_aid.eligible_for_gift_aid']);
    $this->assertEquals(0, $now['gift_aid.amount']);
    $this->assertEquals(0, $now['gift_aid.gift_aid_amount']);
  }

  public function testUpdateAmountOnly() {
    $this->setupFixture();
    $contactID = $this->contacts[0]['id'];

    // Create a simple donation contribution that is eligible.
    $donationSimple = Contribution::create(FALSE)
    ->addValue('contact_id', $contactID)
    ->addValue('financial_type_id', 1)
    ->addValue('total_amount', 10)
    ->execute()->first();

    // Check that nothing changes - it's should be all correct.
    $result = Contribution::UpdateGiftAid(FALSE)
    ->setUpdateLevel('amountsOnly')
    ->addWhere('id', '=', $donationSimple['id'])
    ->execute()->getArrayCopy();
    $this->assertEmpty($result);

    // Change the cached tax rate
    \Civi::$statics['CRM_Civigiftaid_Utils_Contribution']['basictaxrate'] = 50;

    $result = Contribution::UpdateGiftAid(FALSE)
    ->setUpdateLevel('amountsOnly')
    ->addWhere('id', '=', $donationSimple['id'])
    ->execute()->getArrayCopy();
    $this->assertIsArray($result);
    $this->assertEquals(10, $result[0]['gift_aid.gift_aid_amount']);

    $now = $this->fetchCn($donationSimple['id']);
    $this->assertEquals($donationSimple['id'], $now['id']);
    $this->assertEquals(1, $now['gift_aid.eligible_for_gift_aid']);
    $this->assertEquals(10, $now['gift_aid.amount']);
    $this->assertEquals(10, $now['gift_aid.gift_aid_amount']);

    // Set basic rate back to 20
    \Civi::$statics['CRM_Civigiftaid_Utils_Contribution']['basictaxrate'] = 20;
    // $r = \Civi\Api4\OptionValue::save(FALSE)->setRecords([
    //   ['name' => 'basic_rate_tax', 'value' => 20, 'option_group_id.name' => 'giftaid_basic_rate_tax'],
    // ])->setMatch(['name', 'option_group_id'])->execute();
  }

  public function testNoUpdatesToBatchedItems() {
    $this->setupFixture();
    $contactID = $this->contacts[0]['id'];

    // Create a simple donation contribution that is eligible.
    $donationSimple = Contribution::create(FALSE)
    ->addValue('contact_id', $contactID)
    ->addValue('financial_type_id', 1)
    ->addValue('total_amount', 10)
    ->execute()->first();

    // Create a batch
    $batchID = \Civi\Api4\OptionValue::create(FALSE)
    ->addValue('label', 'test_' . time())
    ->addValue('option_group_id:name', 'giftaid_batch_name')
    ->execute()->first()['value'];

    // Manually set this contribution as in that batch.
    $r = Contribution::update(FALSE)
      ->addWhere('id', '=', $donationSimple['id'])
      ->addValue('gift_aid.batch_name', $batchID)
      ->execute()->getArrayCopy();

    // Check that the contribution is set back eligible (and amounts are recalc'ed) by UpdateGiftAid

    // Change the tax rate
    \Civi::$statics['CRM_Civigiftaid_Utils_Contribution']['basictaxrate'] = 50;
    $result = Contribution::UpdateGiftAid(FALSE)->addWhere('id', '=', $donationSimple['id'])->execute()->getArrayCopy();
    $this->assertCount(0, $result, "Expected that a batched contribution was untouched by UpdateGiftAid");
    $result = Contribution::UpdateGiftAid(FALSE)->addWhere('id', '=', $donationSimple['id'])->setUpdateLevel('amountsOnly')->execute()->getArrayCopy();
    $this->assertCount(0, $result, "Expected that a batched contribution was untouched by UpdateGiftAid");

    $now = $this->fetchCn($donationSimple['id']);
    $this->assertEquals($donationSimple['id'], $now['id']);
    $this->assertEquals(1, $now['gift_aid.eligible_for_gift_aid']);
    $this->assertEquals(10, $now['gift_aid.amount']);
    $this->assertEquals(2.5, $now['gift_aid.gift_aid_amount']);
    $this->assertEquals($batchID, $now['gift_aid.batch_name']);

    // Check we can force changes
    $result = Contribution::UpdateGiftAid(FALSE)
    ->addWhere('id', '=', $donationSimple['id'])
    ->setAllowUpdatingExistingBatches(TRUE)
    ->execute()->getArrayCopy();
    $this->assertCount(1, $result);
    $now = $this->fetchCn($donationSimple['id']);
    $this->assertEquals($donationSimple['id'], $now['id']);
    $this->assertEquals(1, $now['gift_aid.eligible_for_gift_aid']);
    $this->assertEquals(10, $now['gift_aid.amount']);
    $this->assertEquals(10, $now['gift_aid.gift_aid_amount']);
    $this->assertEquals($batchID, $now['gift_aid.batch_name']);

    \Civi::$statics['CRM_Civigiftaid_Utils_Contribution']['basictaxrate'] = 20;
  }

  /**
   * Create a contact and check some assumptions.
   */
  protected function setupFixture() {
    $r = civicrm_api3('FinancialType', 'get', []);
    $this->assertEquals('Donation', $r['values'][1]['name'], "Test assumes fin type 1 is donation but it is not.");
    $this->assertEquals('Event Fee', $r['values'][4]['name'], "Test assumes fin type 4 is event fee but it is not.");

    PriceFieldValue::create(FALSE)
      ->addValue('price_field_id.name', 'contribution_amount')
      ->addValue('label', 'Event Fee')
      ->addValue('amount', 1)
      ->execute();

    // Mark Donation as an eligible type, (and event fee as not).
    \Civi::settings()->set('civigiftaid_globally_enabled', 0);
    // Just donations.
    \Civi::settings()->set('civigiftaid_financial_types_enabled', [1]);

    // Create a contact.
    $result = Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('display_name', 'Test 123')
      ->execute()[0];
    $this->contacts[] = $result;

  }

  protected function fetchCn($id) {
    return Contribution::get(FALSE)->addWhere('id', '=', $id)
      ->addSelect('id', 'financial_type_id',
        'gift_aid.eligible_for_gift_aid', 'gift_aid.batch_name',
        'gift_aid.amount', 'gift_aid.gift_aid_amount')
      ->execute()->first();
  }

}

