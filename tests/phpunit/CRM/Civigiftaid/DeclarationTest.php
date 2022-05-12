<?php

use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\CustomValue;
use CRM_Civigiftaid_ExtensionUtil as E;
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
class CRM_Civigiftaid_DeclarationTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {

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
    parent::setUp();

    // This is common to all tests.
    $this->setupFixture1();
  }

  /**
   * CRM_Civigiftaid_Declaration::update is called by the hook_post callback
   * for contributions, and then it calls setDeclaration which makes several
   * other calls to update.
   *
   * The purpose is to update the declaration records for a contact, after
   * having saved a Contribution record, but especially - or primarily - after
   * a contribution page has been submitted.
   *
   * This currently relies on two session variables, which are set as part of
   * the Form code.
   *
   * @todo this should be moved out of the form layer.
   *
   */
  public function testDeclarationUpdateFirstDeclarationEver() {

    // First, where there is no declaration.
    $session = CRM_Core_Session::singleton();
    $session->set('postProcessTitle', 'testDeclarationUpdate', E::LONG_NAME);

    foreach ([
               CRM_Civigiftaid_Declaration::DECLARATION_IS_YES => 'Yes',
               CRM_Civigiftaid_Declaration::DECLARATION_IS_NO => 'No',
               CRM_Civigiftaid_Declaration::DECLARATION_IS_PAST_4_YEARS => 'Yes and past 4',
             ] as $type => $assertionContext) {

      $session->set('uktaxpayer', $type, E::LONG_NAME);
      $assertionContext = "During '$assertionContext' declaration test round";
      // Clear static caches.
      unset(Civi::$statics[E::LONG_NAME]); //['updatedDeclarationAmount']);
      unset(Civi::$statics['CRM_Civigiftaid_Declaration']);

      // Create (eligible) contribution
      $contributionID = Contribution::create(FALSE)
        ->addValue('contact_id', $this->contacts[0]['id'])
        ->addValue('financial_type_id', 1)
        ->addValue('total_amount', 100)
        ->execute()
        ->first()['id'] ?? 0;
      $this->assertGreaterThan(0, $contributionID, $assertionContext);

      // Check for declarations.
      $declarations = CRM_Civigiftaid_Declaration::getAllDeclarations($this->contacts[0]['id']);
      $this->assertInternalType('array', $declarations, $assertionContext);

      // Special case: a No declaration does not get created.
      if ($type === CRM_Civigiftaid_Declaration::DECLARATION_IS_NO) {
        $this->assertCount(0, $declarations, $assertionContext);
        continue;
      }

      $this->assertCount(1, $declarations, $assertionContext);
      $decl = $declarations[0];
      $this->assertEquals($this->contacts[0]['id'], $decl['entity_id'], $assertionContext);
      $this->assertEquals($type, $decl['eligible_for_gift_aid'], $assertionContext);

      // Check that it was created in the last 2 seconds.
      $timeDiff = time() - strtotime($decl['start_date']);
      $this->assertGreaterThanOrEqual(0, $timeDiff, $assertionContext);
      $this->assertLessThan(2, $timeDiff, $assertionContext);
      $this->assertEmpty($decl['end_date'], $assertionContext);
      $this->assertEmpty($decl['notes'], $assertionContext);
      $this->assertEquals('testDeclarationUpdate', $decl['source'], $assertionContext);

      // Cleanup for next round of foreach
      \Civi\Api4\CustomValue::delete('gift_aid_declaration', FALSE)
        ->addWhere('id', '=', $declarations[0]['id'])
        ->execute();
    }

    /*
   * @param array  $newParams    - fields to store in declaration:
   *               - entity_id:  the Individual for whom we will create/update declaration
   *               - eligible_for_gift_aid: 3=Yes+past 4 years,1=Yes,0=No
   *               - start_date: start date of declaration (in ISO date format)
   *               - end_date:   end date of declaration (in ISO date format)
   *               */

  }
  /**
   * Test logic.
   *
   *
   * @dataProvider logicTestProvider
   *
   */
  public function testSetDeclarationLogic($description, $sequence, $expectations) {

    $session = CRM_Core_Session::singleton();
    $session->set('postProcessTitle', 'testDeclarationUpdate', E::LONG_NAME);
    $entityIdParam = ['entity_id' => $this->contacts[0]['id']];
    $declarationsToDelete = [];
    $testDescription = "Test set: $description";

    // Simulate the sequence.
    foreach ($sequence as $declaration) {
      // Clear static caches.
      unset(Civi::$statics[E::LONG_NAME]); //['updatedDeclarationAmount']);
      unset(Civi::$statics['CRM_Civigiftaid_Declaration']);
      // Convert date formats from ISO (Y-m-d H:i:s) to MySQL style (YmdHis)
      foreach (['start_date', 'end_date', 'given_date'] as $_) {
        if (!empty($declaration[$_])) {
          $declaration[$_] = preg_replace('/[^0-9]/', '', $declaration[$_]);
        }
      }
      CRM_Civigiftaid_Declaration::setDeclaration($declaration + $entityIdParam);
    }

    // Test expectations.

    // Check for declarations.
    $declarations = CRM_Civigiftaid_Declaration::getAllDeclarations($this->contacts[0]['id']);
    $this->assertInternalType('array', $declarations, $testDescription);
    $this->assertCount(count($expectations), $declarations, $testDescription);

    if (!$expectations) {
      // All is fine.
      return;
    }

    $i = 0;
    do {
      $expectation = array_shift($expectations);
      $declaration = array_shift($declarations);
      $declarationsToDelete[] = $declaration['id'];
      $i++;

      foreach ($expectation as $key => $value) {
        if (($key === 'start_date' || $key === 'end_date')
          && !preg_match('/ 00:00:00$/', $value)) {
          // We're comparing a full date stamp with seconds
          // We need to allow some leeway here since the test data provider
          // is sourced at the beginning, so allow 5 minutes.

          $this->assertGreaterThanOrEqual($value, $declaration[$key] ?? '(MISSING)', "$testDescription Expect declr $i to have $key >= $value");
          $this->assertLessThan(date('Y-m-d H:i:s', strtotime($value) + 60*5), $declaration[$key] ?? '(MISSING)', "$testDescription Expect declr $i to have $key no later than 5 mins after $value");
        }
        else {
          // Simple comparison.
          if ($value === '') {
            // Allow '' === undefined.
            $this->assertEquals($value, $declaration[$key] ?? '', "$testDescription: Expect declr $i to have $key = ''");
          }
          else {
            $this->assertEquals($value, $declaration[$key] ?? '(MISSING)', "$testDescription: Expect declr $i to have $key = $value");
          }
        }
      }
    } while ($expectations);

    // End of test, clean up:

    // Delete the declaration.
    CustomValue::delete('gift_aid_declaration')
      ->setCheckPermissions(FALSE)
      ->addWhere('id', 'IN', $declarationsToDelete)
      ->execute();

  }
  public function logicTestProvider() {
    $no = CRM_Civigiftaid_Declaration::DECLARATION_IS_NO;
    $yes = CRM_Civigiftaid_Declaration::DECLARATION_IS_YES;
    $yesPast4 = CRM_Civigiftaid_Declaration::DECLARATION_IS_PAST_4_YEARS;
    return [
      [
        'existing no, adding a yes (also tests omitted given_date and provided given_date)',
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $no],
          ['start_date' => '2020-05-01 00:00:00', 'given_date' => '2020-04-01 00:00:00', 'eligible_for_gift_aid' => $yes],
        ],
        [
          ['start_date' => '2020-01-01 00:00:00', 'given_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $no, 'end_date' => '2020-05-01 00:00:00'],
          ['start_date' => '2020-05-01 00:00:00', 'given_date' => '2020-04-01 00:00:00', 'eligible_for_gift_aid' => $yes],
        ]
      ],

      // This behaviour changed with resolving gitlab issue 1
      [
        'existing no, adding a yes past 4',
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $no],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $yesPast4],
        ],
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $no, 'end_date' => '2020-05-01 00:00:00'],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $yesPast4],
        ]
      ],
      [
        'existing no, adding a no',
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $no],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $no],
        ],
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $no, 'end_date' => ''],
        ]
      ],

      [
        'existing yes, adding a no',
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $yes],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $no],
        ],
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $yes, 'end_date' => '2020-05-01 00:00:00', 'reason_ended' => 'Contact Declined'],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $no, 'end_date' => ''],
        ]
      ],

      [
        'existing yes past 4, adding a no',
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $yesPast4],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $no],
        ],
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $yesPast4, 'end_date' => '2020-05-01 00:00:00', 'reason_ended' => 'Contact Declined'],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $no, 'end_date' => ''],
        ]
      ],

      [
        'existing yes, adding a yes',
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $yes],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $yes],
        ],
        [
          ['start_date' => '2020-01-01 00:00:00', 'eligible_for_gift_aid' => $yes, 'end_date' => '', 'reason_ended' => ''],
        ]
      ],

      [
        'existing yes, adding a yes past 4, but existing declaration is older than 4 years ago',
        [
          // This start date is definitely over 4 years ago!
          ['start_date' => '2012-01-01', 'eligible_for_gift_aid' => $yes],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $yesPast4],
        ],
        [
          // No change
          ['start_date' => '2012-01-01 00:00:00', 'eligible_for_gift_aid' => $yes, 'end_date' => '', 'reason_ended' => ''],
        ]
      ],


      [
        'existing yes, adding a yes past 4, but existing declaration is not older than 4 years ago',
        [
          // This start date needs to be relevant to actually NOW, since that's the way
          // the code is written (@todo open issue on this).
          ['start_date' => date('Y-m-d', strtotime('today - 1 year')), 'eligible_for_gift_aid' => $yes],
          ['start_date' => '2020-05-01 00:00:00', 'eligible_for_gift_aid' => $yesPast4],
        ],
        [
          ['start_date' => date('Y-m-d H:i:s'), 'eligible_for_gift_aid' => $yesPast4, 'end_date' => '', 'reason_ended' => ''],
        ]
      ],
    ];
  }

  protected function dump() {
    $customFieldID = CRM_Core_BAO_CustomField::getCustomFieldID('eligible_for_gift_aid', 'gift_aid');
    $customContribTableName = CRM_Core_BAO_CustomField::getTableColumnGroup($customFieldID)[0];

    $dao = CRM_Core_DAO::executeQuery("SELECT id, contact_type, display_name FROM civicrm_contact ORDER BY id");
    echo "\nContacts:\n";
    while ($dao->fetch()) {
      print "  $dao->id: $dao->contact_type $dao->display_name\n";
    }
    print "\n";
    $sql = "
      SELECT c.id, c.contact_id, c.receive_date, c.total_amount,
          ga.id ga_id, ga.eligible_for_gift_aid, ga.amount ga_amount, ga.gift_aid_amount, ga.batch_name
      FROM civicrm_contribution c
      LEFT JOIN $customContribTableName ga ON ga.entity_id = c.id
      ORDER BY c.id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    echo "\nContributions:\n";
    while ($dao->fetch()) {
      $_ = $dao->toArray();
      foreach ($_ as $k => $v) {
        print "  $k: " . json_encode($v) ."\n";
      }
      print "\n";
    }
    print "\n";


    $sql = "
      SELECT *
      FROM $customContribTableName
      ORDER BY id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    echo "\n$customContribTableName :\n";
    while ($dao->fetch()) {
      $_ = $dao->toArray();
      foreach ($_ as $k => $v) {
        print "  $k: " . json_encode($v) ."\n";
      }
      print "\n";
    }
    print "\n";

  }
  /**
   */
  protected function setupFixture1() {
    $r = civicrm_api3('FinancialType', 'get', []);
    $this->assertEquals('Donation', $r['values'][1]['name'], "Test assumes fin type 1 is donation but it is not.");
    $this->assertEquals('Event Fee', $r['values'][4]['name'], "Test assumes fin type 4 is event fee but it is not.");

    // Mark Donation as an eligible type, (and event fee as not), and globally eligible for now.
    //$financialTypesAvailable = (Array) CRM_Civigiftaid_Settings::get('financial_types_enabled');
    CRM_Civigiftaid_Settings::save([
      'globally_enabled' => 1,
      'financial_types_enabled' => [1], // Just donations.
    ]);

    // Create a contact.
    $this->contacts[] = Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('display_name', 'Test 123')
      ->execute()
      ->first();
  }

}
