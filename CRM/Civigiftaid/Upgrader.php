<?php

/**
 * Collection of upgrade steps
 */
class CRM_Civigiftaid_Upgrader extends CRM_Extension_Upgrader_Base {

  const REPORT_CLASS = 'CRM_Civigiftaid_Report_Form_Contribute_GiftAid';
  const REPORT_URL = 'civicrm/contribute/report/uk-giftaid';

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed
   *
   */
  public function install() {
    //only for vesion 1.0 to 2.1
    //To remove the future
    self::removeLegacyRegisteredReport();
    self::migrateOneToTwo($this);
    //end step for upgrading version 1.0 t0 2.1

    $ogId = self::getReportTemplateGroupId();
    if($ogId){
      $className = self::REPORT_CLASS;
      $reportUrl = new CRM_Core_DAO_OptionValue();
      $reportUrl->option_group_id = $ogId;
      $reportUrl->value = self::REPORT_URL;
      $dupeURL = $dupeClass = FALSE;
      if ($reportUrl->find(TRUE)) {
        //if url exist
        $dupeURL = TRUE;
        if ($reportUrl->name == $className) {
          $dupeClass = TRUE;
        }
      }
      if (!$dupeClass) {
        $reportClass = new CRM_Core_DAO_OptionValue();
        $reportClass->option_group_id = $ogId;
        $reportClass->name = $className;
        if ($reportClass->find(TRUE)) {
          $dupeClass = TRUE;
        }
      }

      if (!$dupeClass && !$dupeURL) {
        $params = array(
          'version' => 3,
          'option_group_id' => $ogId,
          'label' => 'Gift Aid Report',
          'name' => self::REPORT_CLASS,
          'value' => 'civicrm/contribute/uk-giftaid',
          'description' => 'For submitting Gift Aid reports to HMRC treasury.',
          'component_id' => CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Component',
            'CRM_Contribute',
            'id',
            'namespace'
          ),
          'is_active' => 1
        );
        $result = civicrm_api('OptionValue', 'create', $params);
      }
    }

    $this->upgrade_3000();
    $this->upgrade_3101();
    $this->upgrade_3103();
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled
   *
   */
  public function uninstall() {

    $reportUrl = new CRM_Core_DAO_OptionValue();
    $reportUrl->option_group_id = self::getReportTemplateGroupId();;
    $reportUrl->value = self::REPORT_URL;
    if ($reportUrl->find(TRUE)) {
      if ($reportUrl->name == $className) {
        $reportUrl->delete();
      }
    }

    $reportClass = new CRM_Core_DAO_OptionValue();
    $reportClass->option_group_id = self::getReportTemplateGroupId();;
    $reportClass->name = self::REPORT_CLASS;
    if ($reportClass->find(TRUE)) {
      $reportClass->delete();
    }

    $this->unsetSettings();
  }

  /**
   * Example: Run a simple query when a module is enabled
   *
  */
  public function enable() {

    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 1  WHERE name = 'giftaid_batch_name'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 1  WHERE name = 'giftaid_basic_rate_tax'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 1  WHERE name = 'reason_ended'");

    civicrm_api('CustomGroup', 'update', array(
      'version' => 3,
      'is_active' => 1,
      'id' => CRM_Utils_Array::value('id',civicrm_api('CustomGroup', 'getsingle', array(
        'version' => 3,
        'name' => 'Gift_Aid')
      )),
    ));

    civicrm_api('CustomGroup', 'update', array(
      'version' => 3,
      'is_active' => 1,
      'id' =>  CRM_Utils_Array::value('id',civicrm_api('CustomGroup', 'getsingle', array(
        'version' => 3,
        'name' => 'Gift_Aid_Declaration')
      )),
    ));

    civicrm_api('UFGroup', 'update', array(
      'version' => 3,
      'is_active' => 1,
      'id' =>  CRM_Utils_Array::value('id',civicrm_api('UFGroup', 'getsingle', array(
        'version' => 3,
        'name' => 'Gift_Aid_Declaration')
      )),
    ));
    $gid = self::getReportTemplateGroupId();
    $className = self::REPORT_CLASS;
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_value SET is_active = 1 WHERE option_group_id = $gid AND name = '$className'");
  }

  /**
   * Example: Run a simple query when a module is disabled
   *
   */
  public function disable() {

    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 0 WHERE name = 'giftaid_batch_name'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 0 WHERE name = 'giftaid_basic_rate_tax'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 0 WHERE name = 'reason_ended'");

     civicrm_api('CustomGroup', 'update', array(
      'version' => 3,
      'is_active' => 0,
      'id' => CRM_Utils_Array::value('id',civicrm_api('CustomGroup', 'getsingle', array(
        'version' => 3,
        'name' => 'Gift_Aid')
      )),
    ));

    civicrm_api('CustomGroup', 'update', array(
      'version' => 3,
      'is_active' => 0,
      'id' =>  CRM_Utils_Array::value('id',civicrm_api('CustomGroup', 'getsingle', array(
        'version' => 3,
        'name' => 'Gift_Aid_Declaration')
      )),
    ));

    civicrm_api('UFGroup', 'update', array(
      'version' => 3,
      'is_active' => 0,
      'id' =>  CRM_Utils_Array::value('id',civicrm_api('UFGroup', 'getsingle', array(
        'version' => 3,
        'name' => 'Gift_Aid_Declaration')
      )),
    ));
    $gid = self::getReportTemplateGroupId();
    $className = self::REPORT_CLASS;
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_value SET is_active = 0 WHERE option_group_id = $gid AND name = '$className'");

    /*
    civicrm_api('OptionValue', 'update', array(
      'version' => 3,
      'is_active' => 0,
      'name' => self::REPORT_CLASS,
      'option_group_id' => self::getReportTemplateGroupId()
    ));*/

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
    static::importBatches();

    return TRUE;
  }

  /*
   * Set up Past Year Submissions Job
   */
  public function upgrade_3101()
  {
    $this->log('Applying update 3101');

    // create scheduled job
    $dao = new CRM_Core_DAO_Job();
    $dao->api_entity = 'gift_aid';
    $dao->api_action = 'makepastyearsubmissions';
    $dao->find(TRUE);
    if (!$dao->id)
    {
      $dao = new CRM_Core_DAO_Job();
      $dao->domain_id = CRM_Core_Config::domainID();
      $dao->run_frequency = 'Daily';
      $dao->parameters = null;
      $dao->name = 'Make Past Year Submissions';
      $dao->description = 'Make Past Year Submissions';
      $dao->api_entity = 'gift_aid';
      $dao->api_action = 'makepastyearsubmissions';
      $dao->is_active = 0;
      $dao->save();
    }

    return TRUE;
  }

  public function upgrade_3102() {
    $this->log('Applying update 3102');

    // Alter existing eligible_for_gift_aid columns
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_gift_aid_declaration MODIFY COLUMN eligible_for_gift_aid int");
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_gift_aid_submission MODIFY COLUMN eligible_for_gift_aid int");

    // Update custom field type from String to Int
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET data_type = 'Int' WHERE name = 'Eligible_for_Gift_Aid'");


    // Add new option groups and options
    $og1Id = CRM_Core_BAO_OptionGroup::ensureOptionGroupExists(
      [
        'name' => 'eligibility_declaration_options',
        'title' => 'Eligibility Declaration Options',
        'label' => 'Eligibility Declaration Options',
        'is_active' => 1,
        'is_reserved' => 1,
      ]
    );
    $og2Id = CRM_Core_BAO_OptionGroup::ensureOptionGroupExists(
      [
        'name' => 'uk_taxpayer_options',
        'title' => 'UK Taxpayer Options',
        'label' => 'UK Taxpayer Options',
        'is_active' => 1,
        'is_reserved' => 1
      ]
    );

    $optionValues = array (
      array (
        'sequential' => 1,
        'option_group_id' => $og1Id,
        'label' => 'Yes',
        'value' => 1,
        'name' => 'eligible_for_giftaid',
      ),
      array (
        'sequential' => 1,
        'option_group_id' => $og1Id,
        'label' => 'No',
        'value' => 0,
        'name' => 'not_eligible_for_giftaid',
      ),
      array (
        'sequential' => 1,
        'option_group_id' => $og1Id,
        'label' => 'Yes, in the Past 4 Years',
        'value' => 3,
        'name' => 'past_four_years',
      ),
      array (
        'sequential' => 1,
        'option_group_id' => $og2Id,
        'label' => 'Yes',
        'value' => 1,
        'name' => 'yes_uk_taxpayer',
      ),
      array (
        'sequential' => 1,
        'option_group_id' => $og2Id,
        'label' => 'No',
        'value' => 0,
        'name' => 'not_uk_taxpayer',
      ),
      array (
        'sequential' => 1,
        'option_group_id' => $og2Id,
        'label' => 'Yes, in the Past 4 Years',
        'value' => 3,
        'name' => 'uk_taxpayer_past_four_years',
      ),
    );

    foreach($optionValues as $params) {
      CRM_Core_BAO_OptionValue::ensureOptionValueExists($params);
    }

    $declarationCustomGroupID = CRM_Utils_Array::value('id',civicrm_api('CustomGroup', 'getsingle', array(
      'version' => 3,
      'name' => 'Gift_Aid_Declaration')
    ));

    $submissionCustomGroupId = CRM_Utils_Array::value('id',civicrm_api('CustomGroup', 'getsingle', array(
      'version' => 3,
      'name' => 'Gift_Aid')
    ));

    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET option_group_id = {$og1Id} WHERE name = 'Eligible_for_Gift_Aid' AND custom_group_id = {$declarationCustomGroupID}");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET option_group_id = {$og2Id} WHERE name = 'Eligible_for_Gift_Aid' AND custom_group_id = {$submissionCustomGroupId}");

    return TRUE;
  }

  /**
   * Create Giftaid batch type.
   */
  public function upgrade_3103() {
    $this->log('Applying update 3103');

    CRM_Core_BAO_OptionValue::ensureOptionValueExists(
      [
        'option_group_id' => "batch_type",
        'label' => 'Giftaid Batch',
        'name' => 'giftaid_batch',
        'description' => 'Giftaid Batch Type',
      ]
    );

    return TRUE;
  }

  /**  Remove all the report that registerd on GiftAid 1.0beta and 2.0beta
  **/
  static function removeLegacyRegisteredReport(){
    $reportClass = new CRM_Core_DAO_OptionValue();
    $reportClass->option_group_id = self::getReportTemplateGroupId();
    $reportClass->name = 'GiftAid_Report_Form_Contribute_GiftAid';
    if ($reportClass->find(TRUE)) {
      $reportClass->delete();
    }
  }

  static function getReportTemplateGroupId(){
    $params = array(
      'version' => 3,
      'name' => 'report_template',
    );
    $og = civicrm_api('OptionGroup', 'getsingle', $params);
    $ogId = CRM_Utils_Array::value('id', $og);
    return $ogId;
  }

  static function migrateOneToTwo($ctx){
    $ctx->executeSqlFile('sql/upgrade_20.sql');
    $query = "SELECT DISTINCT batch_name
              FROM civicrm_value_gift_aid_submission
             ";
    $batchNames = array();
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      array_push($batchNames, $dao->batch_name);
    }
    $gId = CRM_Utils_Array::value('id',civicrm_api('OptionGroup', 'getsingle', array(
        'version' => 3,
        'name' => 'giftaid_batch_name')
    ));
    if($gId){
      foreach ($batchNames as $name) {
        $params = array(
          'version' => 3,
          'option_group_id' => $gId,
          'label' => $name,
          'name' => $name,
          'value' => $name,
          'is_active' => 1
        );
        $result = civicrm_api('OptionValue', 'create', $params);
      }
    }
  }

  public static function migrateToThree($ctx) {
    $ctx->executeSqlFile('sql/upgrade_3100');
  }

  /**
   * Set the default admin settings for the extension.
   */
  private function setDefaultSettings() {
    $settings = new stdClass();

    // Set 'Globally Enabled' by default
    $settings->globally_enabled = 1;
    $settings->financial_types_enabled = [];

    \Civi::settings()->set($this->getExtensionKey() . ':settings', $settings);
  }

  /**
   * Remove the admin settings for the extension.
   *
   * @throws \CRM_Extension_Exception
   */
  private function unsetSettings() {
    $settingName = $this->getExtensionKey() . ':settings';

    CRM_Core_BAO_Setting::executeQuery("DELETE FROM civicrm_setting WHERE name = '{$settingName}'");
  }

  /**
   * Create default settings for existing batches, for which settings don't already exist.
   */
  private static function importBatches() {
    $sql = /** @lang MySQL */
      "
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
        CRM_Civigiftaid_BAO_BatchSettings::create(array(
          'batch_id' => (int) $dao->id,
          'financial_types_enabled' => array(),
          'globally_enabled' => TRUE,
          'basic_rate_tax' => $basicRateTax
        ));
      }
    }
  }

  /**
   * @return string
   * @throws \CRM_Extension_Exception
   * @throws \CRM_Extension_Exception_ParseException
   */
  private function getExtensionKey() {
    $info = CRM_Extension_Info::loadFromFile(__DIR__ . '/../../info.xml');

    if (empty($info->key)) {
      throw new CRM_Extension_Exception('Extension key not found for Gift Aid extension');
    }

    return $info->key;
  }

  private function log($message) {
    if (is_object($this->ctx) && method_exists($this->ctx, 'info')) {
      $this->ctx->log->info($message);
    }
  }
}
