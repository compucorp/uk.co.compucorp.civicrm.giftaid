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
 * Collection of upgrade steps.
 */
class CRM_Civigiftaid_Upgrader extends CRM_Civigiftaid_Upgrader_Base {

  const REPORT_CLASS = 'CRM_Civigiftaid_Report_Form_Contribute_GiftAid';
  const REPORT_URL = 'civicrm/contribute/report/uk-giftaid';

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /** @var int */
  public $declarationCustomGroupID;

  /** @var int */
  public $contributionGiftaidCustomGroupId;


  /** @var array */
  public $optionGroupNameToId = [];

  /** @var array */
  public $customFieldNamesToIds = [];

  /**
   */
  public function install() {
    $this->ensureDataStructures();

    // Nb. this is kept to preserve previous behaviour, it should not be needed.
    // Import existing batches.
    self::importBatches();

    $this->ensurePastYearSubmissionJob();

    // In case this extension had been installed and uninstalled before:
    $this->removeLegacyRegisteredReport();
  }

  public function postInstall() {
    $this->createReportInstance();
    return TRUE;
  }

  /**
   * Safely repeatable function to ensure all the data structures we need
   * exist.
   */
  public function ensureDataStructures() {
    // Note most of these depend on the others having been called during
    // runtime, so the order is important.
    $this->setOptionGroups();
    $this->enableOptionGroups(1);
    $this->ensureCustomGroups();
    $this->ensureCustomFields();
    $this->setDefaultSettings();
    $this->ensureProfiles();
  }
  /**
   * Ensure we have the custom groups defined.
   *
   *
   */
  protected function ensureCustomGroups() {
    $this->declarationCustomGroupID = $this->findOrCreate('CustomGroup',
      ['name' => 'Gift_Aid_Declaration'],
      [
        'is_active' => 1,
        'extends'   => 'Individual',
      ],
      [
        'title'                => 'Gift Aid Declaration',
        'table_name'           => 'civicrm_value_gift_aid_declaration',
        'is_multiple'          => 1,
        'style'                => 'Tab',
        'collapse_display'     => 0,
        'collapse_adv_display' => 0,
        'weight'               => 1,
      ]
    )['id'];

    $this->contributionGiftaidCustomGroupId = $this->findOrCreate('CustomGroup',
      ['name' => 'Gift_Aid'],
      [
        'is_active' => 1,
        'extends'   => 'Contribution',
      ],
      [
        'title'                => 'Gift Aid',
        'style'                => 'Inline',
        'collapse_display'     => 0,
        'help_pre'             => 'Stores the Gift Aid values for contributions',
        'table_name'           => 'civicrm_value_gift_aid_submission',
        'is_multiple'          => 0,
        'collapse_adv_display' => 0,
        'weight'               => 2,
      ]
    )['id'];
  }
  /**
   * Ensure we have the custom fields defined for declaration
   *
   * Note: requires ensureCustomGroups() to have run.
   */
  protected function ensureCustomFields() {
    foreach ($this->getCustomFields() as $name => $details) {

      $findParams =  array_intersect_key($details, array_flip([
        'name', 'custom_group_id']));

      // Which param are required, i.e. if they are not these params, the
      // existing data will be re-set to them.
      if (!empty($details['_requiredParams'])) {
        // This field has specified its own.
        $requiredParams = $details['_requiredParams'];
      }
      else {
        // Typical case, insist on these:
        $requiredParams = array_intersect_key($details, array_flip([
          'default_value', 'is_active', 'is_searchable', 'weight', 'help_pre',
          'help_post', 'is_search_range', 'text_length', 'data_type', 'html_type',
          'option_group_id',
        ] ));
      }
      unset($details['_requiredParams']);

      $additionalCreateParams = $details;

      // Ensure field exists.
      // Note: we store its ID so we can look it up easily later.
      // We cannot simply store the name though because sadly we have used
      // Eligible_for_Gift_Aid as a custom field name twice in two different
      // custom groups. Doh.
      $this->customFieldNamesToIds["$details[custom_group_id]--$details[name]"] = $this->findOrCreate('CustomField', $findParams, $requiredParams, $additionalCreateParams)['id'];
    }
  }

  /**
   */
  protected function ensureProfiles() {
    $profileId = $this->findOrCreate('UFGroup',
      ['name' => 'Gift_Aid'],
      [
        'is_active' => 1,
        'group_type' => 'Individual,Contact',
      ],
      [
        'title' => 'Gift Aid',
        'add_captcha' => 0,
        'is_map' => 0,
        'is_edit_link' => 0,
        'is_uf_link' => 0,
        'is_update_dupe' => 2,
        'is_proximity_search' => 0,
      ]
    )['id'];

    $this->findOrCreate('UFField',
      [
        'uf_group_id' => $profileId,
        'field_name' => 'custom_' . $this->customFieldNamesToIds["{$this->declarationCustomGroupID}--Eligible_for_Gift_Aid"],
      ],
      [
        'is_active' => 1,
        'is_view' => 0,
        'is_required' => 1,
        'help_post' => '<p>By selecting \'Yes\' above you are confirming that
<ol>
<li>You are a UK taxpayer</li>
<li>The amount of income and/or capital gains tax you pay is at least as much as we will reclaim on your donations in this tax year</li>
</ol>
<p><b>About Gift Aid</b><p>
<p>Gift Aid increases the value of membership contributions and donations to charities by allowing them to reclaim basic rate tax on your gift. It allows us to claim 25% on top of your donation, for example, on a £50 membership or donation, we can claim back an additional £12.50.</p>
<p>We would like to reclaim gift aid on your behalf. We can only reclaim Gift Aid if you are a UK taxpayer. Please confirm that you are eligible for Gift Aid. <a href="http://www.hmrc.gov.uk/individuals/giving/gift-aid.htm">More about Gift Aid</a>.</p>',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => 1,
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'Can we reclaim gift aid on your donation?',
        'field_type' => 'Individual',
      ]);

    $this->findOrCreate('UFField',
      [
        'field_name' => 'first_name',
        'uf_group_id' => $profileId,
      ],
      [
        'is_active' => '1',
        'is_view' => '0',
        'is_required' => '1',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => '2',
        'help_post' => '',
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'First Name',
        'field_type' => 'Individual',
      ]);

    $this->findOrCreate('UFField',
      [
        'field_name' => 'last_name',
        'uf_group_id' => $profileId,
      ],
      [
        'is_active' => '1',
        'is_view' => '0',
        'is_required' => '1',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => '3',
        'help_post' => '',
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'Last Name',
        'field_type' => 'Individual',
      ]
    );

    $this->findOrCreate('UFField',
      [
        'field_name' => 'street_address',
        'uf_group_id' => $profileId,
      ],
      [
        'is_active' => '1',
        'is_view' => '0',
        'location_type_id' => '1',
        'is_required' => '1',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => '4',
        'help_post' => '',
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'Street Address',
        'field_type' => 'Contact',
      ]);


    $this->findOrCreate('UFField',
      [
        'field_name' => 'supplemental_address_1',
        'uf_group_id' => $profileId,
      ],
      [
        'is_active' => '1',
        'is_view' => '0',
        'location_type_id' => '1',
        'is_required' => '0',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => '5',
        'help_post' => '',
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'Supplemental Address 1',
        'field_type' => 'Contact',
      ]);

    $this->findOrCreate('UFField',
      [
        'uf_group_id' => $profileId,
        'field_name' => 'supplemental_address_2',
      ],
      [
        'is_active' => '1',
        'is_view' => '0',
        'location_type_id' => '1',
        'is_required' => '0',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => '6',
        'help_post' => '',
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'Supplemental Address 2',
        'field_type' => 'Contact',
      ]);

    $this->findOrCreate('UFField',
      [
        'uf_group_id' => $profileId,
        'field_name' => 'city',
      ],
      [
        'is_active' => '1',
        'is_view' => '0',
        'location_type_id' => '1',
        'is_required' => '0',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => '7',
        'help_post' => '',
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'City',
        'field_type' => 'Contact',
      ]);

    $this->findOrCreate('UFField',
      [
        'uf_group_id' => $profileId,
        'field_name' => 'state_province',
      ],
      [
        'is_active' => '1',
        'is_view' => '0',
        'location_type_id' => '1',
        'is_required' => '0',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => '8',
        'help_post' => '',
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'County',
        'field_type' => 'Contact',
      ]);

    $this->findOrCreate('UFField',
      [
        'uf_group_id' => $profileId,
        'field_name' => 'postal_code',
      ],
      [
        'is_active' => '1',
        'is_view' => '0',
        'location_type_id' => '1',
        'is_required' => '1',
        'visibility' => 'User and User Admin Only',
      ],
      [
        'weight' => '9',
        'help_post' => '',
        'in_selector' => '0',
        'is_searchable' => '0',
        'label' => 'Post code',
        'field_type' => 'Contact',
      ]);

  }
  /**
   * Set up Past Year Submissions Job
   */
  public function ensurePastYearSubmissionJob() {
    $existing = civicrm_api3('Job', 'get', [
      'api_entity' => "gift_aid",
      'api_action' => "makepastyearsubmissions",
    ]);

    if (empty($existing['count'])) {
      $jobParams = [
        'domain_id' => CRM_Core_Config::domainID(),
        'run_frequency' => 'Daily',
        'name' => 'Make Past Year Submissions',
        'description' => 'Make Past Year Submissions',
        'api_entity' => 'gift_aid',
        'api_action' => 'makepastyearsubmissions',
        'is_active' => 0,
      ];
      civicrm_api3('Job', 'create', $jobParams);
    }
  }

  /**
   * Check we have the thing we need.
   *
   * When creating an entity $findParams + $requiredParams + $additionalCreateParams is used
   *
   * @param string $entity
   * @param array $findParams
   *   API params used to find if the thing we want exists. e.g. ['name' => 'my_name']
   * @param array $requiredParams
   *   If these values are incorrect in a found entity, they will be corrected.
   * @param array $additionalCreateParams
   *   These values will only be used if creating an entity.
   *
   * @return array
   *   The entity, as returned by a 'get' action (which can sometimes differ from the result of a create action.)
   */
  protected function findOrCreate($entity, $findParams, $requiredParams = [], $additionalCreateParams = []) {
    try {
      $result = civicrm_api3($entity, 'get', $findParams);
      $found = $result['count'];
      if ($found == 0) {
        // Not found, create now.
        $result = civicrm_api3($entity, 'create', $findParams + $requiredParams + $additionalCreateParams);
        return civicrm_api3($entity, 'getsingle', ['id' => $result['id']]);
      }
      elseif ($found == 1) {
        // Take the first (only) item, but check for requiredParams.
        $thing = current($result['values']);
        $corrections = [];
        foreach ($requiredParams as $k => $v) {
          if (($thing[$k] ?? NULL) != $v) {
            $corrections[$k] = $v;
          }
        }
        if ($corrections) {
          // Need to correct it/update it.
          $corrections['id'] = $thing['id'];
          civicrm_api3($entity, 'create', $corrections);
          // Reload
          return civicrm_api3($entity, 'getsingle', ['id' => $thing['id']]);
        }
        else {
          // Wanted one, found it, no corrections.
          return $thing;
        }
      }
      else {
        // Huh, we found more than one?
        throw new \Exception("Cannot install '$entity', expected 0 or 1 matching "
        . json_encode($findParams) . " but found $found");
      }
    }
    catch (\Exception $e) {
      Civi::log()->error("FAILED ON: " .json_encode([$entity, $findParams, $requiredParams, $additionalCreateParams]));
      throw $e;
    }
  }

  private function createReportInstance() {
    // Now see if we have a report and create one if we don't
    try {
      $reportID = CRM_Core_DAO::singleValueQuery(
        "SELECT id FROM `civicrm_report_instance` WHERE report_id='" . CRM_Civigiftaid_Upgrader::REPORT_URL . "'"
      );
    }
    catch (Exception $e) {
      $reportID = NULL;
    }

    if (!$reportID) {
      try {
        $reportTemplate = civicrm_api3('ReportTemplate', 'getsingle', [
          'value' => CRM_Civigiftaid_Upgrader::REPORT_URL,
        ]);
        civicrm_api3('ReportInstance', 'create', [
          'title' => $reportTemplate['label'],
          'report_id' => $reportTemplate['value'],
          'description' => $reportTemplate['description'],
          'permission' => 'access CiviReport',
        ]);
      }
      catch (Exception $e) {
        \Civi::log()->error('Failed to create GiftAid report instance. ' . $e->getMessage());
      }
    }
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled
   */
  public function uninstall() {
    $this->unsetSettings();
  }

  /**
   * Example: Run a simple query when a module is enabled
   */
  public function enable() {
    $this->setOptionGroups();
    $this->enableOptionGroups(1);
    $this->ensureCustomGroups();
    $this->ensureCustomFields();
  }

  /**
   * Example: Run a simple query when a module is disabled
   *
   */
  public function disable() {
    $this->enableOptionGroups(0);
  }

  /**
   * Perform upgrade to version 2.1
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_2100() {
    $this->log('Applying update 2100');
    self::removeLegacyRegisteredReport();
    return TRUE;
  }

  /**
   * Perform upgrade to version 3.0
   *
   * @return bool
   */
  public function upgrade_3000() {
    $this->log('Applying update 3000');

    // Set default settings.
    $this->setDefaultSettings();

    // Create database schema.
    $this->executeSqlFile('sql/upgrade_3000.sql');

    // Import existing batches.
    self::importBatches();

    return TRUE;
  }

  /**
   * Set up Past Year Submissions Job
   */
  public function upgrade_3101() {
    $this->log('Applying update 3101 - Add past year submissions job');
    $this->ensurePastYearSubmissionJob();
    return TRUE;
  }

  public function upgrade_3102() {
    $this->log('Applying update 3102');

    // Alter existing eligible_for_gift_aid columns
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_gift_aid_declaration MODIFY COLUMN eligible_for_gift_aid int");
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_gift_aid_submission MODIFY COLUMN eligible_for_gift_aid int");

    // Update custom field type from String to Int
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET data_type = 'Int' WHERE name = 'Eligible_for_Gift_Aid'");

    return TRUE;
  }

  public function upgrade_3103() {
    $this->log('Applying update 3103 - delete old report templates');
    $this->removeLegacyRegisteredReport();
    return TRUE;
  }

  public function upgrade_3104() {
    $this->log('Applying update 3104 - change profile(s) to use Individual declaration eligibility field instead of contribution eligibility field');
    $contributionGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('Eligible_For_Gift_Aid', 'Gift_Aid');
    $contactGiftAidField = CRM_Civigiftaid_Utils::getCustomByName('Eligible_For_Gift_Aid', 'Gift_Aid_Declaration');
    $helpPost = '<p>By selecting &#039;Yes&#039; above you are confirming that you are a UK taxpayer and the amount of income and/or capital gains tax you pay is at least as much as we will reclaim on your donations in this tax year.</p>
<p><b>About Gift Aid</b></p>
<p>Gift Aid increases the value of donations to charities by allowing them to reclaim basic rate tax on your gift.  We would like to reclaim gift aid on your behalf.  We can only reclaim Gift Aid if you are a UK taxpayer.  Please confirm that you are a eligible for gift aid above.  <a href="http://www.hmrc.gov.uk/individuals/giving/gift-aid.htm">More about Gift Aid</a>.</p>';

    $query = "UPDATE civicrm_uf_field SET field_name='{$contactGiftAidField}', field_type='Individual', help_post='{$helpPost}' WHERE field_name='{$contributionGiftAidField}'";
    CRM_Core_DAO::executeQuery($query);

    $this->log('Applying update 3104 - checking optiongroups');
    $this->setOptionGroups();

    return TRUE;
  }

  public function upgrade_3107() {
    $this->log('Updating custom fields');
    $this->ensureCustomGroups();
    $this->ensureCustomFields();
    return TRUE;
  }

  /**
   * There's the chance of ambiguity on the default value of batch_name,
   * which should be NULL. This was causing tests to fail on new installs
   * and historically a lot has changed around this so better to make sure
   * the default is applied here too.
   *
   * @see https://github.com/mattwire/uk.co.compucorp.civicrm.giftaid/pull/18
   * @see comment in install()
   */
  public function upgrade_3108() {
    $this->log('Updating default batch_name');
    $this->executeSqlFile('sql/reset_batch_name_default.sql');
    return TRUE;
  }

  public function upgrade_3109() {
    $this->log('Disable "Yes, in the past 4 years" for contributions if it exists');
    try {
      $optionValueParams = [
        'option_group_id' => 'uk_taxpayer_options',
        'name' => 'uk_taxpayer_past_four_years',
      ];
      $optionValue = civicrm_api3('OptionValue', 'getsingle', $optionValueParams);
      $optionValueParams['id'] = $optionValue['id'];
      if (empty($optionValue['is_active'])) {
        $optionValueParams['is_active'] = 0;
        civicrm_api3('OptionValue', 'create', $optionValueParams);
      }
    }
    catch(Exception $e) {
      // Ok, it doesn't exist. Good.
    }
    return TRUE;
  }

  /**
   * Add given date.
   *
   */
  public function upgrade_3110() {
    $this->log('Add given date field.');
    $this->ensureCustomGroups();
    $this->ensureCustomFields();
    // Update any records missing a given date.
    $sql = "UPDATE civicrm_value_gift_aid_declaration
      SET given_date = start_date
      WHERE given_date IS NULL";

    CRM_Core_DAO::executeQuery($sql);

    return TRUE;
  }

  public function upgrade_3112() {
    $this->log('Check we have batch_type = "Gift Aid" and fix profile setup');
    $this->ensureDataStructures();
     \Civi\Api4\System::flush()
       ->setCheckPermissions(FALSE)
       ->setSession(TRUE)
       ->setTriggers(TRUE);
    return TRUE;
  }

  public function upgrade_3113() {
    $this->log('Extend source field length to match contribution page');
    $this->ensureDataStructures();
    return TRUE;
  }

  public function upgrade_3114() {
    $this->log('Check and update data_type for custom fields');
    $this->ensureDataStructures();
    return TRUE;
  }

  public function upgrade_3115() {
    $this->log('Check and create GiftAid report instance');
    $this->createReportInstance();
    $this->log('Fix searching by GiftAid batch');
    $this->ensureDataStructures();
    return TRUE;
  }

  /**
   * @return array
   */
  private function getCustomFields() {
    if (empty($this->declarationCustomGroupID)) {
      throw new \RuntimeException("CRM_Civigiftaid_Upgrader::getCustomFields called before declarationCustomGroupID defined.");
    }
    if (empty($this->contributionGiftaidCustomGroupId)) {
      throw new \RuntimeException("CRM_Civigiftaid_Upgrader::getCustomFields called before contributionGiftaidCustomGroupId defined.");
    }

    $customFields = [
      // For contacts' declarations...
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'Eligible_for_Gift_Aid',
        'label' => 'UK Tax Payer?',
        'data_type' => 'Int',
        'html_type' => 'Radio',
        'is_required' => '1',
        'is_searchable' => '1',
        'is_search_range' => '0',
        'weight' => '1',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'eligible_for_gift_aid',
        'option_group_id' => $this->optionGroupNameToId['eligibility_declaration_options'],
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'Address',
        'label' => 'Address',
        'data_type' => 'Memo',
        'html_type' => 'TextArea',
        'is_required' => '0',
        'is_searchable' => '1',
        'is_search_range' => '0',
        'weight' => '2',
        'help_pre' => 'The address and post code are automatically copied from the contact\'s "Home" address and formatted for submission to HMRC. You don\'t normally need to make any changes here.',
        'attributes' => 'rows=4, cols=60',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'address',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'Post_Code',
        'label' => 'Post Code',
        'data_type' => 'String',
        'html_type' => 'Text',
        'is_required' => '0',
        'is_searchable' => '1',
        'is_search_range' => '0',
        'weight' => '3',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '16',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'post_code',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'given_date',
        'label' => 'Date declaration given',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_required' => '0',
        'is_searchable' => '0',
        'is_search_range' => '0',
        'weight' => '4',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'date_format' => 'dd-mm-yy',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'given_date',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'Start_Date',
        'label' => 'Start Date',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_required' => '1',
        'is_searchable' => '0',
        'is_search_range' => '0',
        'weight' => '4',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'date_format' => 'dd-mm-yy',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'start_date',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'End_Date',
        'label' => 'End Date',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_required' => '0',
        'is_searchable' => '0',
        'is_search_range' => '0',
        'weight' => '5',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'date_format' => 'dd-mm-yy',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'end_date',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'Reason_Ended',
        'label' => 'Reason Ended',
        'data_type' => 'String',
        'html_type' => 'Radio',
        'is_required' => '0',
        'is_searchable' => '0',
        'is_search_range' => '0',
        'weight' => '6',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '32',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'reason_ended',
        'option_group_id' => $this->optionGroupNameToId['reason_ended'],
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'Source',
        'label' => 'Source',
        'data_type' => 'String',
        'html_type' => 'Text',
        'is_required' => '0',
        'is_searchable' => '0',
        'is_search_range' => '0',
        'weight' => '7',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'source',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'Notes',
        'label' => 'Notes',
        'data_type' => 'Memo',
        'html_type' => 'TextArea',
        'is_required' => '0',
        'is_searchable' => '0',
        'is_search_range' => '0',
        'weight' => '8',
        'attributes' => 'rows=4, cols=60',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'notes',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->declarationCustomGroupID,
        'name' => 'Scanned_Declaration',
        'label' => 'Scanned Declaration',
        'data_type' => 'File',
        'html_type' => 'File',
        'is_required' => '0',
        'is_searchable' => '0',
        'is_search_range' => '0',
        'weight' => '9',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'scanned_declaration',
        'in_selector' => '0',
      ],

      // For Contributions' status
      [
        'custom_group_id' => $this->contributionGiftaidCustomGroupId,
        'name' => 'Eligible_for_Gift_Aid',
        'label' => 'Eligible for Gift Aid?',
        'data_type' => 'Int',
        'html_type' => 'Radio',
        'default_value' => '1',
        'is_required' => '1',
        'is_searchable' => '1',
        'is_search_range' => '0',
        'weight' => '1',
        'help_pre' => '\'Eligible for Gift Aid\' will be set automatically based on the financial type of the contribution if you do not select Yes or No',
        'is_active' => '1',
        'is_view' => '0',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'eligible_for_gift_aid',
        'option_group_id' => $this->optionGroupNameToId['uk_taxpayer_options'],
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->contributionGiftaidCustomGroupId,
        'name' => 'Amount',
        'label' => 'Eligible Amount',
        'data_type' => 'Money',
        'html_type' => 'Text',
        'is_required' => '0',
        'is_searchable' => '1',
        'is_search_range' => '1',
        'weight' => '2',
        'is_active' => '1',
        'is_view' => '1',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'amount',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->contributionGiftaidCustomGroupId,
        'name' => 'Gift_Aid_Amount',
        'label' => 'Gift Aid Amount',
        'data_type' => 'Money',
        'html_type' => 'Text',
        'is_required' => '0',
        'is_searchable' => '1',
        'is_search_range' => '1',
        'weight' => '3',
        'is_active' => '1',
        'is_view' => '1',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'gift_aid_amount',
        'in_selector' => '0',
      ],
      [
        'custom_group_id' => $this->contributionGiftaidCustomGroupId,
        'name' => 'Batch_Name',
        'label' => 'Batch Name',
        'html_type' => 'Select',
        'default_value' => '',
        'is_required' => '0',
        'is_searchable' => '1',
        'is_search_range' => '0',
        'weight' => '4',
        'is_active' => '1',
        'is_view' => '1',
        'text_length' => '255',
        'note_columns' => '60',
        'note_rows' => '4',
        'column_name' => 'batch_name',
        'option_group_id' => $this->optionGroupNameToId['giftaid_batch_name'],
        'in_selector' => '0',
      ],
    ];

    return $customFields;
  }

  private function getOptionGroups() {
    // eligibility_declaration_options is for the Gift Aid Declaration, i.e. on the contact (3 options)
    // uk_taxpayer_options is for the Gift Aid Contribution (2 options)
    $optionGroups = [
      'eligibility_declaration_options' => [
        'name' => 'eligibility_declaration_options',
        'title' => 'GiftAid eligibility declaration options',
        'is_active' => 1,
        'is_reserved' => 1,
      ],
      'uk_taxpayer_options' => [
        'name' => 'uk_taxpayer_options',
        'title' => 'GiftAid contribution eligibility',
        'is_active' => 1,
        'is_reserved' => 1
      ],
      'giftaid_basic_rate_tax' => [
        'name' => 'giftaid_basic_rate_tax',
        'title' => 'GiftAid basic rate of tax',
        'is_active' => 1,
        'is_reserved' => 1
      ],
      'giftaid_batch_name' => [
        'name' => 'giftaid_batch_name',
        'title' => 'GiftAid batch name',
        'is_active' => 1,
        'is_reserved' => 1
      ],
      'reason_ended' => [
        'name' => 'reason_ended',
        'title' => 'GiftAid reason ended',
        'is_active' => 1,
        'is_reserved' => 1
      ],
    ];
    $optionGroups['batch_type'] = civicrm_api3('OptionGroup', 'getsingle', [
      'name' => 'batch_type',
    ]);
    return $optionGroups;
  }

  private function getOptionValues($optionGroups) {
    $optionValues = [
      // eligibility_declaration_options: these apply to contacts and record details
      // about their declarations.
      [
        'option_group_id' => $this->optionGroupNameToId['eligibility_declaration_options'],
        'label' => 'Yes, today and in the future',
        'value' => 1,
        'name' => 'eligible_for_giftaid',
        'is_default' => 0,
        'weight' => 2,
        'is_reserved' => 1,
      ],
      [
        'option_group_id' => $this->optionGroupNameToId['eligibility_declaration_options'],
        'label' => 'No',
        'value' => 0,
        'name' => 'not_eligible_for_giftaid',
        'is_default' => 0,
        'weight' => 3,
        'is_reserved' => 1,
      ],
      [
        'option_group_id' => $this->optionGroupNameToId['eligibility_declaration_options'],
        'label' => 'Yes, and for donations made in the past 4 years',
        'value' => 3,
        'name' => 'past_four_years',
        'is_default' => 0,
        'weight' => 1,
        'is_reserved' => 1,
      ],
      // uk_taxpayer_options: these apply to profiles
      [
        'option_group_id' => $this->optionGroupNameToId['uk_taxpayer_options'],
        'label' => 'Yes',
        'value' => 1,
        'name' => 'yes_uk_taxpayer',
        'is_default' => 0,
        'is_reserved' => 1,
      ],
      [
        'option_group_id' => $this->optionGroupNameToId['uk_taxpayer_options'],
        'label' => 'No',
        'value' => 0,
        'name' => 'not_uk_taxpayer',
        'is_default' => 0,
        'is_reserved' => 1,
      ],

      // These apply to why a declaration has an end date.
      [
        'option_group_id' => $this->optionGroupNameToId['reason_ended'],
        'label' => 'Contact Declined',
        'value' => 'Contact Declined',
        'name' => 'Contact_Declined',
        'is_default' => 0,
        'weight' => 2,
      ],
      [
        'option_group_id' => $this->optionGroupNameToId['reason_ended'],
        'label' => 'HMRC Declined',
        'value' => 'HMRC Declined',
        'name' => 'HMRC_Declined',
        'is_default' => 0,
        'weight' => 1,
      ],

      // Tax rates
      [
        'option_group_id' => $this->optionGroupNameToId['giftaid_basic_rate_tax'],
        'label' => 'The basic rate tax',
        'value' => 20,
        'name' => 'basic_rate_tax',
        'is_default' => 0,
        'weight' => 1,
        'is_reserved' => 1,
        'description' => 'The GiftAid basic tax rate (%)'
      ],
      [
        'option_group_id' => $this->optionGroupNameToId['batch_type'],
        'name' => "Gift Aid",
        'is_active' => 1,
        'is_reserved' => 1,
        'is_default' => 0,
      ]
    ];

    return $optionValues;
  }

  private function setOptionGroups() {
    foreach ($this->getOptionGroups() as $groupName => $groupParams) {

      // Create the option groups.
      $optionGroups[$groupName] = civicrm_api3('OptionGroup', 'get', [
        'name' => $groupName,
      ]);
      if ($optionGroups[$groupName]['id'] ?? NULL) {
        $groupParams['id'] = $optionGroups[$groupName]['id'];
      }
      // Add new option groups and options
      $optionGroups[$groupName] = civicrm_api3('OptionGroup', 'create', $groupParams);
      // Save the look up of this group's ID.
      $this->optionGroupNameToId[$groupName] = $optionGroups[$groupName]['id'];
    }

    // Create the values within the groups.
    $optionValues = $this->getOptionValues($optionGroups);
    foreach($optionValues as $params) {
      $optionValue = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => $params['option_group_id'],
        'value' => $params['value'],
      ]);
      if (CRM_Utils_Array::value('id', $optionValue)) {
        $params['id'] = $optionValue['id'];
      }
      civicrm_api3('OptionValue', 'create', $params);
    }
  }

  /**
   * Enable/Disable option groups
   * @param int $enable
   */
  private function enableOptionGroups($enable = 1) {
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = {$enable} WHERE name = 'giftaid_batch_name'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = {$enable} WHERE name = 'giftaid_basic_rate_tax'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = {$enable} WHERE name = 'reason_ended'");

    try {
      civicrm_api3('CustomGroup', 'update', [
        'is_active' => $enable,
        'id' => CRM_Utils_Array::value('id', civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid'])),
      ]);
    }
    catch (Exception $e) {
      // Couldn't find CustomGroup, maybe it was manually deleted
    }

    try {
      civicrm_api3('CustomGroup', 'update', [
        'is_active' => $enable,
        'id' => CRM_Utils_Array::value('id', civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid_Declaration'])),
      ]);
    }
    catch (Exception $e) {
      // Couldn't find CustomGroup, maybe it was manually deleted
    }

    try {
      civicrm_api3('UFGroup', 'update', [
        'is_active' => $enable,
        'id' =>  CRM_Utils_Array::value('id',civicrm_api3('UFGroup', 'getsingle', ['name' => 'Gift_Aid'])),
      ]);
    }
    catch (Exception $e) {
      // Couldn't find CustomGroup, maybe it was manually deleted
    }
  }

  /**
   * Remove report templates created by older versions
   */
  private static function removeLegacyRegisteredReport() {
    $report1 = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => "report_template",
      'name' => 'GiftAid_Report_Form_Contribute_GiftAid',
    ]);
    $report2 = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => "report_template",
      'value' => 'civicrm/contribute/uk-giftaid',
    ]);

    $reports = [];
    if (!empty($report1['count'])) {
      $reports[] = CRM_Utils_Array::first($report1['values']);
    }
    if (!empty($report2['count'])) {
      $reports[] = CRM_Utils_Array::first($report2['values']);
    }
    foreach ($reports as $report) {
      civicrm_api3('OptionValue', 'delete', ['id' => $report['id']]);
    }
  }

  private static function migrateOneToTwo($ctx){
    $ctx->executeSqlFile('sql/upgrade_20.sql');
    $query = "SELECT DISTINCT batch_name
              FROM civicrm_value_gift_aid_submission
             ";
    $batchNames = [];
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      array_push($batchNames, $dao->batch_name);
    }
    $gId = CRM_Utils_Array::value('id',civicrm_api3('OptionGroup', 'getsingle', ['name' => 'giftaid_batch_name']));
    if ($gId) {
      foreach ($batchNames as $name) {
        $params = [
          'option_group_id' => $gId,
          'name' => $name,
        ];
        $existing = civicrm_api3('OptionValue', 'get', $params);
        if (empty($existing['count'])) {
          $params['label'] = $name;
          $params['value'] = $name;
          $params['is_active'] = 1;
          civicrm_api3('OptionValue', 'create', $params);
        }
      }
    }
  }

  /**
   * Set the default admin settings for the extension.
   */
  private function setDefaultSettings() {
    Civi::settings()->set(E::SHORT_NAME . 'globally_enabled', 1);
    Civi::settings()->set(E::SHORT_NAME . 'financial_types_enabled', []);
  }

  /**
   * Remove the admin settings for the extension.
   */
  private function unsetSettings() {
    Civi::settings()->revert(E::SHORT_NAME . 'globally_enabled');
    Civi::settings()->revert(E::SHORT_NAME . 'financial_types_enabled');
  }

  /**
   * Create default settings for existing batches, for which settings don't already exist.
   */
  private static function importBatches() {
    $sql = "
      SELECT id
      FROM civicrm_batch
      WHERE name LIKE 'GiftAid%'
    ";

    $dao = CRM_Core_DAO::executeQuery($sql);

    $basicRateTax = CRM_Civigiftaid_Utils_Contribution::getBasicRateTax();

    while ($dao->fetch()) {
      // Only add settings for batches for which settings don't exist already
      if (CRM_Civigiftaid_BAO_BatchSettings::findByBatchId($dao->id) === FALSE) {
        // Set globally enabled to TRUE by default, for existing batches
        CRM_Civigiftaid_BAO_BatchSettings::create([
          'batch_id' => (int) $dao->id,
          'financial_types_enabled' => [],
          'globally_enabled' => TRUE,
          'basic_rate_tax' => $basicRateTax
        ]);
      }
    }
  }

  private function log($message) {
    Civi::log()->info($message);
  }
}
