<?php

use CRM_Civigiftaid_ExtensionUtil as E;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\CustomValue;
use Civi\Api4\PriceFieldValue;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests for CRM/Civigiftaid/Utils/Contribution.php class
 *
 * NOTE: these are NOT implementing TransactionalInterface - so we must do all our own cleanup.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Civigiftaid_Utils_ContributionTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {

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

  public function setUp(): void {
    // We use session to store some information on contribution page submission.
    // eg. "uktaxpayer" controls whether to create a new declaration.
    CRM_Core_Session::singleton()->reset();
  }

  /**
   */
  public function testCreateContribWithApi3SetsCustomData() {
    $this->setupFixture1();

    $contributionGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('eligible_for_gift_aid', 'gift_aid');

    // Create contribution using API3
    $contributionID = civicrm_api3('Contribution', 'create', [
      'contact_id' => $this->contacts[0]['id'],
      'financial_type_id' => 1,
      'total_amount' => 100,
      $contributionGiftAidField => 1,
    ])['id'];
    $this->assertGreaterThan(0, $contributionID);

    // Re-fetch the contribution details.
    $contributions = Contribution::get(FALSE)
      ->addSelect('gift_aid.eligible_for_gift_aid', 'gift_aid.amount', 'gift_aid.gift_aid_amount', 'gift_aid.batch_name')
      ->addWhere('id', '=', $contributionID)
      ->execute()
      ->first();

    $this->assertEquals(1, $contributions['gift_aid.eligible_for_gift_aid'] ?? NULL, "Expect contribution to be eligible.");
    $this->assertEquals('', $contributions['gift_aid.batch_name'] ?? NULL, "Expected empty batch name");
    $this->assertEquals(100, $contributions['gift_aid.amount'] ?? NULL, "Expected amount eligible to be calculated");
    $this->assertEquals(25, $contributions['gift_aid.gift_aid_amount'] ?? NULL, "Expected amount claimable to be calculated");
  }

  /**
   * When creating a contribution with API4 for some reason it
   * does not seem to save the custom fields.
   * Not sure if this is to do with this extension or api4
   */
  public function testCreateContribWithApi4SetsCustomData() {
    $this->setupFixture1();

    // Create contribution
    $contributionID = Contribution::create()
        ->setCheckPermissions(FALSE)
        ->addValue('contact_id', $this->contacts[0]['id'])
        ->addValue('financial_type_id', 1)
        ->addValue('total_amount', 100)
        ->addValue('gift_aid.eligible_for_gift_aid', 1)
        ->execute()[0]['id'] ?? 0;
    $this->assertGreaterThan(0, $contributionID);

    // Re-fetch the contribution details.
    $contributions = Contribution::get()
      ->setCheckPermissions(FALSE)
      ->addSelect('gift_aid.eligible_for_gift_aid', 'gift_aid.amount', 'gift_aid.gift_aid_amount', 'gift_aid.batch_name')
      ->addWhere('id', '=', $contributionID)
      ->execute()[0];

    $this->assertEquals(1, $contributions['gift_aid.eligible_for_gift_aid'] ?? NULL);
    $this->assertEquals(100, $contributions['gift_aid.amount'] ?? NULL);
    $this->assertEquals(25, $contributions['gift_aid.gift_aid_amount'] ?? NULL);
    $this->assertEquals('', $contributions['gift_aid.batch_name'] ?? NULL);
  }

  /**
   * Test contribution eligibility in various situations
   *
   * @dataProvider contributionEligibilityCalcsCases
   */
  public function testContributionEligibilityCalcs($label, $settings, $orderCreateParams, $expectations) {

    $this->setupFixture1();

    // Apply settings.
    CRM_Civigiftaid_Settings::save($settings);

    // Create contribution with order api
    // Merge in common fields:
    $orderCreateParams += [
      'contact_id'             => $this->contacts[0]['id'],
      'total_amount'           => 100,
      'contribution_status_id' => 'Pending',
    ];
    $contributionID = civicrm_api3('Order', 'create', $orderCreateParams)['id'] ?? NULL;
    /*
    // Can't use API4 at the mo as Order is not implemented.
    $contributionID = \Civi\Api4\Contribution::create()
      ->setCheckPermissions(FALSE)
      ->addValue('contact_id', $this->contacts[0]['id'])
      ->addValue('financial_type_id', $params['financial_type_id'])
      ->addValue('total_amount', 100)
      // ->addValue('gift_aid.eligible_for_gift_aid', 1)
      ->execute()[0]['id'] ?? 0;
     */
    $this->assertGreaterThan(0, $contributionID);

    // Re-fetch the contribution details.
    $contribution = Contribution::get()
      ->setCheckPermissions(FALSE)
      ->addSelect('gift_aid.eligible_for_gift_aid', 'gift_aid.amount', 'gift_aid.gift_aid_amount', 'gift_aid.batch_name')
      ->addWhere('id', '=', $contributionID)
      ->execute()->first();

    $this->assertEquals($expectations['eligibility'], $contribution['gift_aid.eligible_for_gift_aid'] ?? NULL);
    $this->assertEquals($expectations['eligible_amount'], $contribution['gift_aid.amount'] ?? NULL);
    $this->assertEquals($expectations['ga_worth'], $contribution['gift_aid.gift_aid_amount'] ?? NULL);
    $this->assertEquals('', $contribution['gift_aid.batch_name'] ?? NULL);
  }

  /**
   * Provides datasets for testContributionEligibilityCalcs
   *
   * @return array
   */
  public function contributionEligibilityCalcsCases() {
    return [
      [
        'test globally-set eligibility',
        [
          'globally_enabled' => 1,
          'financial_types_enabled' => []
        ],
        ['financial_type_id' => 1],
        ['eligibility' => 1,
          'eligible_amount' => 100,
          'ga_worth' => 25
        ]
      ],

      [
        'test donation (eligible)',
        [
          'globally_enabled' => 0,
          'financial_types_enabled' => [1]
        ],
        ['financial_type_id' => 1],
        [
          'eligibility' => 1,
          'eligible_amount' => 100,
          'ga_worth' => 25
        ]
      ],

      [
        'test Event Fee (not eligible)',
        [
          'globally_enabled' => 0,
          'financial_types_enabled' => [1]
        ],
        ['financial_type_id' => 4],
        [
          'eligibility' => 0,
          'eligible_amount' => NULL,
          'ga_worth' => NULL
        ]
      ],

      [
        'test mixed Line Items Event Fee when main fin type is eligible',
        [
          'globally_enabled' => 0,
          'financial_types_enabled' => [1]
        ],
        [
          // Seting financial_type_id here makes no sense but we have to do it,
          // see https://lab.civicrm.org/dev/core/-/issues/1761
          'financial_type_id' => 1,
          'line_items' => [
            [
              'params' => [],
              'line_item' => [
                // The donation
                [
                  'line_total' => 20,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'price_field_value_id' => 1,
                  'qty' => 1
                ],
                // The event fee
                [
                  'line_total' => 80,
                  'financial_type_id' => 4,
                  'price_field_id' => 1,
                  'price_field_value_id' => 2,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ],
        [
          'eligibility' => 1,
          'eligible_amount' => 20,
          'ga_worth' => 5
        ]
      ],

      [
        'test mixed Line Items Event Fee when main fin type is NOT eligible',
        [
          'globally_enabled' => 0,
          'financial_types_enabled' => [1]
        ],
        [
          'financial_type_id' => 4, //xxx why do we need to set this here?
          'line_items' => [
            [
              'params' => [],
              'line_item' => [
                // The donation
                [
                  'line_total' => 20,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'price_field_value_id' => 1,
                  'qty' => 1
                ],
                // The event fee
                [
                  'line_total' => 80,
                  'financial_type_id' => 4,
                  'price_field_id' => 1,
                  'price_field_value_id' => 2,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ],
        [
          'eligibility' => 1,
          'eligible_amount' => 20,
          'ga_worth' => 5
        ]
      ],
    ];
  }
  /**
   * Test contribution eligibility is calculated for multiple changes.
   *
   * @see Issue 26
   *
   */
  public function testContributionEligibilityCalcsForMultipleCalls() {

    $this->setupFixture1();

    // Create contribution with order api
    // Merge in common fields:
    $orderCreateDefaults = [
      'contact_id'             => $this->contacts[0]['id'],
      'contribution_status_id' => 'Pending',
      'financial_type_id'      => 1,
    ];

    // Call twice
    $contributionIDs = [];
    foreach ([100, 200] as $amount) {
      $assertionContext = "Pass for amount $amount:";
      $orderCreateParams = [ 'total_amount' => $amount ] + $orderCreateDefaults;
      $contributionID = civicrm_api3('Order', 'create', $orderCreateParams)['id'] ?? NULL;
      $contributionIDs[] = $contributionID;
      $this->assertGreaterThan(0, $contributionID, $assertionContext);

      // Re-fetch the contribution details.
      $contribution = Contribution::get()
        ->setCheckPermissions(FALSE)
        ->addSelect('gift_aid.eligible_for_gift_aid', 'gift_aid.amount', 'gift_aid.gift_aid_amount', 'gift_aid.batch_name')
        ->addWhere('id', '=', $contributionID)
        ->execute()->first();

      $this->assertEquals(1, $contribution['gift_aid.eligible_for_gift_aid'] ?? NULL, $assertionContext);
      $this->assertEquals($amount, $contribution['gift_aid.amount'] ?? NULL, $assertionContext);
      $this->assertEquals($amount/4, $contribution['gift_aid.gift_aid_amount'] ?? NULL, $assertionContext);
      $this->assertEquals('', $contribution['gift_aid.batch_name'] ?? NULL, $assertionContext);
    }

    // Delete contributions
    Contribution::delete()
      ->addWhere('id', 'IN', $contributionIDs)
      ->setCheckPermissions(FALSE)
      ->execute();
  }

  /**
   * Test isContributionEligible.
   *
   * This is the main logic.
   *
   * @dataProvider isContributionEligibleCases
   *
   */
  public function testIsContributionEligible($description, $declarations, $contribution) {
    $this->setupFixture2();

    // Clear static caches.
    unset(Civi::$statics[E::LONG_NAME]); //['updatedDeclarationAmount']);
    unset(Civi::$statics['CRM_Civigiftaid_Declaration']);

    if ($declarations) {
      CustomValue::save('gift_aid_declaration', FALSE)
        ->addDefault('entity_id', $this->contacts[0]['id'])
        ->addDefault('address', 'Somewhere')
        ->addDefault('post_code', 'SW1A 0AA')
        ->addDefault('source', 'test')
        ->setRecords($declarations)
        ->execute();
    }

    // Create contribution with order api
    // Merge in common fields:
    $orderCreateParams = array_merge(
      [
        'contact_id'             => $this->contacts[0]['id'],
        'total_amount'           => 100,
        'financial_type_id'      => 1,
        'contribution_status_id' => 'Pending',
      ],
      $contribution,
    );
    $contributionID = civicrm_api3('Order', 'create', $orderCreateParams)['id'] ?? NULL;

    // test contributions.
    // Get all contributions from found IDs that are not already in a batch
    $contributionCreated = Contribution::get(FALSE)
      ->addWhere('id', '=', $contributionID)
      ->addSelect('*', 'custom.*')
      ->execute()
      ->first();

    $isEligible = CRM_Civigiftaid_Utils_Contribution::isContributionEligible($contributionCreated);
    $this->assertEquals($contribution['expectedEligibility'], $isEligible,
      "$description"
    );
    $declaration = CRM_Civigiftaid_Utils_Contribution::getDeclarationForContribution($contributionCreated);
    $address = CRM_Civigiftaid_Declaration::getDonorAddress($contributionCreated['id']);
    if ($isEligible) {
      // Check it.
      $this->assertNotEquals([], $address, 'Error address empty when it should be full.');
    }
    else {
      // If not eligible we may have an address or not. Either:
      //   - the contribution is explicitly "not eligible"
      //   - there is a "no" declaration covering this period.
      if ($declaration) {
        $this->assertEquals(0, $declaration['eligible_for_gift_aid'], 'Declaration should be "no"');
      }
      else {
        $this->assertEquals([], $address, 'Error address found when contribution is not eligible.');
      }
    }

    // delete contributions.
    Contribution::delete()
      ->addWhere('id', '=', $contributionID)
      ->setCheckPermissions(FALSE)
      ->execute();
  }

  /**
   * Data provider for testIsContributionEligible
   *
   * Each case contains:
   * - A description
   *
   * - An array of declarations (may be empty) to create. Note that these are
   * created with the API which means they are done without the setDeclaration
   * logic.
   *
   * - An array of contributions to create (with Order.create). Some defaults
   * are added to this, and it also has a key called expectedEligibility which
   * is a bool.
   *
   * The test creates the declarations, creates the orders, reloads the orders
   * and calls isContributionEligible on each contribution, testing against
   * expectedEligibility.
   *
   * @return Array
   */
  public function isContributionEligibleCases() {
    // Classloader has not run when loaded dataProvider.
    require_once(__DIR__ . '/../../../../../CRM/Civigiftaid/Declaration.php');
    $no = CRM_Civigiftaid_Declaration::DECLARATION_IS_NO;
    $yes = CRM_Civigiftaid_Declaration::DECLARATION_IS_YES;
    $yesPast4 = CRM_Civigiftaid_Declaration::DECLARATION_IS_PAST_4_YEARS;

    return [
      // Case #0
      [
        'Contribution on a person without declaration',
        [],
        [
          'expectedEligibility' => FALSE,
          'receive_date' => '2020-02-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #1
      [
        'Contribution on a person with a "no" declaration',
        [
          [
            'start_date' => '20200201',
            'eligible_for_gift_aid' => $no
          ]
        ],
        [
          'expectedEligibility' => FALSE,
          'receive_date' => '2020-02-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #2
      [
        'Contribution on a person with yes declaration',
        [
          [
            'start_date' => '20200201',
            'eligible_for_gift_aid' => $yes
          ]
        ],
        [
          'expectedEligibility' => TRUE,
          'receive_date' => '2020-02-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #3
      [
        'Contribution on a person with yes past 4 years declaration',
        [
          [
            'start_date' => '20200201',
            'eligible_for_gift_aid' => $yesPast4,
            'address' => '1 The Street, West Aberdeen',
            'post_code' => 'AB1 2CD'
          ]
        ],
        [
          'expectedEligibility' => TRUE,
          'receive_date' => '2020-02-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #4
      [
        'Contribution on a person with yes past 4 years declaration, contrib is before date decl. given.',
        [
          [
            'start_date' => '20200201',
            'eligible_for_gift_aid' => $yesPast4
          ]
        ],
        [
          'expectedEligibility' => TRUE,
          'receive_date' => '2018-02-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #5
      [
        'Contribution on a person with yes past 4 years declaration, contrib is too old',
        [
          [
            'start_date' => '20200201',
            'eligible_for_gift_aid' => $yesPast4
          ]
        ],
        [
          'expectedEligibility' => FALSE,
          'receive_date' => '2015-02-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #6
      [
        'Contribution before yes declaration',
        [
          [
            'start_date' => '20200201',
            'eligible_for_gift_aid' => $yes
          ]
        ],
        [
          'expectedEligibility' => FALSE,
          'receive_date' => '2020-01-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #7
      [
        'Contribution after end date of a yes declaration',
        [
          [
            'start_date' => '20190101',
            'end_date' => '20191201',
            'eligible_for_gift_aid' => $yes
          ]
        ],
        [
          'expectedEligibility' => FALSE,
          'receive_date' => '2020-01-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #8
      [
        'Contribution during No period after end date of a yes declaration',
        [
          [
            'start_date' => '20190101',
            'end_date' => '20191201',
            'eligible_for_gift_aid' => $yes
          ],
          [
            'start_date' => '20191201',
            'end_date' => '',
            'eligible_for_gift_aid' => $no
          ]
        ],
        [
          'expectedEligibility' => FALSE,
          'receive_date' => '2020-01-01 00:00:00',
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #9
      [
        'Contribution is not of eligible type, but is during Yes period',
        [
          [
            'start_date' => '20190101',
            'end_date' => '',
            'eligible_for_gift_aid' => $yes
          ],
        ],
        [
          'expectedEligibility' => FALSE,
          'receive_date' => '2020-01-01 00:00:00',
          'financial_type_id' => 1, // Donation, but line item contradicts.
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 100,
                  'financial_type_id' => 4,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #10
      [
        'Contribution with zero value',
        [],
        [
          'expectedEligibility' => FALSE,
          'receive_date' => '2020-02-01 00:00:00',
          'total_amount' => 0,
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 0,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #11
      [
        'Check that a No declaration can be overruled by a later yes past 4 - contribution on no date',
        [
          // This 'no' decl has been completely overwritten by a later Yes + 4 one.
          // Note: 'reason_ended' is very much focused on recording why a Yes dec. ended; it does not make sense for
          // why a No declaration ended given the current options (xxx 'declined')
          [
            'start_date' => '20200101',
            'end_date' => '20200101',
            'eligible_for_gift_aid' => $no
          ],
          [
            'start_date' => '20200222',
            'eligible_for_gift_aid' => $yesPast4
          ],
        ],
        [
          'expectedEligibility' => TRUE,
          'receive_date' => '2020-01-01 00:00:00',
          'total_amount' => 1,
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 1,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],

      // Case #12
      [
        'Check that a No declaration can be overruled by a later yes past 4 - contribution before No',
        [
          // This 'no' decl has been completely overwritten by a later Yes + 4 one.
          // Note: 'reason_ended' is very much focused on recording why a Yes dec. ended; it does not make sense for
          // why a No declaration ended given the current options (xxx 'declined')
          [
            'start_date' => '20200101',
            'end_date' => '20200101',
            'eligible_for_gift_aid' => $no
          ],
          [
            'start_date' => '20200222',
            'eligible_for_gift_aid' => $yesPast4
          ],
        ],
        [
          'expectedEligibility' => TRUE,
          'receive_date' => '2019-01-01 00:00:00',
          'total_amount' => 1,
          'line_items' => [
            [
              'line_item' => [
                [
                  'line_total' => 1,
                  'financial_type_id' => 1,
                  'price_field_id' => 1,
                  'qty' => 1
                ],
              ]
            ]
          ]
        ]
      ],
    ];
  }

  /**
   */
  protected function setupFixture1() {
    $this->setupFixture2();
    $contactID = $this->contacts[0]['id'];

    // Create a declaration for this contact.
    CustomValue::create('gift_aid_declaration', FALSE)
      ->addValue('entity_id', $contactID)
      ->addValue('eligible_for_gift_aid', 1)
      ->addValue('address', 'somewhere')
      ->addValue('post_code', 'SW1A 0AA')
      ->addValue('start_date', '20200101')
      ->addValue('source', 'test 1')
      ->execute()
      ->first()['id'];
  }

  /**
   * Create a contact and check some assumptions.
   */
  protected function setupFixture2() {
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
}
