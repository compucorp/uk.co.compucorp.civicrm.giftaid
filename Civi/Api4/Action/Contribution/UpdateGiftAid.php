<?php
namespace Civi\Api4\Action\Contribution;

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\Generic\AbstractBatchAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\Utils\CoreUtil;

/**
 * Take a query for a set of Contributions and update the calculated Gift Aid fields.
 *
 * Contributions that are part of a GiftAid batch will not be touched, unless allowUpdatingExistingBatches is set TRUE.
 *
 */
class UpdateGiftAid extends AbstractBatchAction {

  /**
   * Danger! Normally you would NOT want to change anything about a contribution
   * that has been included in a Gift Aid batch. Use this override with caution!
   *
   * @default FALSE
   * @var bool
   */
  protected $allowUpdatingExistingBatches = FALSE;

  /**
   * Whether to update the eligibility based on checking financial type (of line items)
   * against the configured eligible types.
   *
   * @default all
   * @options all,amountsOnly
   * @var string
   */
  protected $updateLevel = 'all';

  /**
   * You can speed up bulk actions by disabling logging.
   *
   * @default TRUE
   * @var bool
   */
  protected $logging = TRUE;

  /**
   * Just a cache
   *
   * @var bool|array If TRUE, means all financial types.
   */
  private $eligibleFinancialTypes;

  public function _run(Result $result) {

    if (!$this->where) {
      throw new \CRM_Core_Exception('Parameter "where" is required.');
    }

    $this->eligibleFinancialTypes = \Civi::settings()->get('civigiftaid_globally_enabled')
    ? TRUE
    : \Civi::settings()->get('civigiftaid_financial_types_enabled');

    // By default we don't touch anything that's in a batch, since it would
    // normally have been exported, e.g. to HMRC.
    if (!$this->allowUpdatingExistingBatches) {
      $this->addWhere('gift_aid.batch_name', 'IS EMPTY');
    }

    $cns = $this->getBatchRecords();
    if (empty($cns)) {
      return;
    }

    // First, check we have permission to edit ALL of these records.
    $loggedInContactID = \CRM_Core_Session::getLoggedInContactID() ?: 0;
    foreach ($cns as $cn) {
      if ($this->checkPermissions && !CoreUtil::checkAccessRecord($this, $cn, $loggedInContactID)) {
        throw new UnauthorizedException("ACL check failed");
      }
    }

    // Calculate updates required.
    $records = [];
    foreach ($cns as $cn) {
      $updates = $this->gatherUpdates($cn);
      if ($updates) {
        $records[] = ['id' => $cn['id']] + $updates;
      }
    }

    // Ok, now do the updates, if we have any.
    if ($records) {
      try {
        $loggingSchema = new \CRM_Logging_Schema();
        $loggingEnabled = $loggingSchema->isEnabled();
        if ($loggingEnabled && $this->logging === FALSE) {
          $loggingSchema->disableLogging();
          \Civi::settings()->set('logging', '0');
        }
        \Civi\Api4\Contribution::save()->setRecords($records)->execute();
        $result->exchangeArray($records);
      }
      finally {
        // Re-enable logging
        if ($this->logging === FALSE && $loggingEnabled) {
          $loggingSchema->enableLogging();
          \Civi::settings()->set('logging', '1');
        }
      }
    }
  }

  /**
   * Get an API action object which resolves the list of records for this batch.
   *
   * This is similar to `getBatchRecords()`, but you may further refine the
   * API call (e.g. selecting different fields or data-pages) before executing.
   *
   * @return \Civi\Api4\Generic\AbstractGetAction
   */
  protected function getBatchAction() {
    $params = [
      'checkPermissions' => $this->checkPermissions,
      'where' => $this->where,
      'orderBy' => $this->orderBy,
      'limit' => $this->limit,
      'offset' => $this->offset,
    ];
    /** @var \Civi\Api4\Generic\DAOGetAction */
    $api = \Civi\API\Request::create($this->getEntityName(), 'get', ['version' => 4] + $params);

    $allFinancialTypesEnabled = (bool) \Civi::settings()->get('civigiftaid_globally_enabled');
    if (!$allFinancialTypesEnabled) {

      $eligibleFinancialTypes = \Civi::settings()->get('civigiftaid_financial_types_enabled');
      $api->addJoin('LineItem AS eligible_lines', 'LEFT', ['eligible_lines.contribution_id', '=', 'id'],
        ['eligible_lines.financial_type_id', 'IN', $eligibleFinancialTypes]
      );

      $api->addSelect('id', 'financial_type_id', 'gift_aid.eligible_for_gift_aid', 'gift_aid.batch_name',
        'gift_aid.amount', 'gift_aid.gift_aid_amount',
        "SUM(eligible_lines.line_total) AS eligible_line_total",
      )
      ->addGroupBy('id');
    }

    return $api;
  }

  /**
   * Generate an array of data that needs changing for this Contribution.
   */
  protected function gatherUpdates(array $cn): array {
    $updates = [];
    $eligible = $cn['gift_aid.eligible_for_gift_aid'];
    if ($this->updateLevel === 'all') {
      $eligible = (int) ($cn['eligible_line_total'] > 0);
      if ((int) $cn['gift_aid.eligible_for_gift_aid'] !== $eligible) {
        $updates['gift_aid.eligible_for_gift_aid'] = $eligible;
      }
    }

    // Note: the following code works to generate updates, but these updates
    // are immediately overwritten by the code that works on the postCommit event
    // which is a shame.
    if ($eligible) {
      $eligibleAmount = (float) (($this->eligibleFinancialTypes === TRUE) ? $cn['total_amount'] : $cn['eligible_line_total']);
      $giftAidAmount = \CRM_Civigiftaid_Utils_Contribution::calculateGiftAidAmt(
        $eligibleAmount,
        \CRM_Civigiftaid_Utils_Contribution::getBasicRateTax()
      );
    }
    else {
      $eligibleAmount = 0;
      $giftAidAmount = 0;
    }
    if ($cn['gift_aid.amount'] != $eligibleAmount) {
      $updates['gift_aid.amount'] = $eligibleAmount;
    }
    if ($cn['gift_aid.gift_aid_amount'] != $giftAidAmount) {
      $updates['gift_aid.gift_aid_amount'] = $giftAidAmount;
    }

    return $updates;
  }
}
