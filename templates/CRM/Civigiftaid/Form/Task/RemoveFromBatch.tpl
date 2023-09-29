{*
 * https://civicrm.org/license
 *}

<div id="gift-aid-remove" class="crm-block crm-form-block crm-export-form-block gift-aid">
  <h2>{ts}Remove contribution from Gift Aid batch{/ts}</h2>

  <div class="help">
    <p>{ts}Use this form to remove Gift Aid contributions from the batch. You can remove multiple contributions from different batches. All gift aid field values including the amount of gift aid will be removed from the contribution record. If you are using the online gift aid submission module and the batch has been submitted, the form below will state the contibution has been submitted and you cannot remove this from the batch.{/ts}</p>
  </div>

  <h3>{ts}Summary{/ts}</h3>

  <p>Number of selected contributions: {$selectedContributions}</p>

  {if $totalToRemoveContributions}
    <div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-closed">
      <div class="crm-accordion-header">
        Number of contributions that will be removed from batch: {$totalToRemoveContributions}
      </div>
      <!-- /.crm-accordion-header -->
      <div class="crm-accordion-body">
        <table class="selector">
          <thead>
          <tr>
            <th>{ts}Name{/ts}</th>
            <th>{ts}Gift Aidable Amount{/ts}</th>
            <th>{ts}Total Amount{/ts}</th>
            <th>{ts}No of items{/ts}</th>
            <th>{ts}Type{/ts}</th>
            <th>{ts}Source{/ts}</th>
            <th>{ts}Recieved{/ts}</th>
            <th>{ts}Batch name{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
          </thead>
          {foreach from=$contributionsToRemoveRows item=row}
            <tr class="contribution" data-contribution-id="{$row.contribution_id}">
              <td>
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id"}">{$row.display_name}</a>
              </td>
              <td>{$row.gift_aidable_amount}</td>
              <td>{$row.total_amount}</td>
              <td>{$row.line_items|@count}</td>
              <td>{$row.financial_account}</td>
              <td>{$row.source}</td>
              <td>{$row.receive_date}</td>
              <td>{$row.batch}</td>
              <td>{$row.actions}</td>
            </tr>
          {/foreach}
        </table>
      </div>
      <!-- /.crm-accordion-body -->
    </div>
    <!-- /.crm-accordion-wrapper -->
  {else}
    {include file="CRM/Civigiftaid/Form/Task/EmptyAccordion.tpl" content="Number of contributions that will be removed from batch: $totalToRemoveContributions"}
  {/if}
  {if $alreadySubmitedContributions}
    <div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-closed">
      <div class="crm-accordion-header">
        Number of contributions already submitted to HMRC: {$alreadySubmitedContributions}
      </div>
      <!-- /.crm-accordion-header -->
      <div class="crm-accordion-body">
        <table class="selector">
          <thead>
          <tr>
            <th>{ts}Name{/ts}</th>
            <th>{ts}Gift Aidable Amount{/ts}</th>
            <th>{ts}Total Amount{/ts}</th>
            <th>{ts}No of items{/ts}</th>
            <th>{ts}Type{/ts}</th>
            <th>{ts}Source{/ts}</th>
            <th>{ts}Recieved{/ts}</th>
            <th>{ts}Batch name{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
          </thead>
          {foreach from=$contributionsAlreadySubmitedRows item=row}
            <tr class="contribution" data-contribution-id="{$row.contribution_id}">
              <td>
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=$row.contact_id"}">{$row.display_name}</a>
              </td>
              <td>{$row.gift_aidable_amount}</td>
              <td>{$row.total_amount}</td>
              <td>{$row.line_items|@count}</td>
              <td>{$row.financial_account}</td>
              <td>{$row.source}</td>
              <td>{$row.receive_date}</td>
              <td>{$row.batch}</td>
              <td>{$row.actions}</td>
            </tr>
          {/foreach}
        </table>
      </div>
      <!-- /.crm-accordion-body -->
    </div>
    <!-- /.crm-accordion-wrapper -->
  {elseif $onlineSubmissionExtensionInstalled}
    {include file="CRM/Civigiftaid/Form/Task/EmptyAccordion.tpl" content="Number of contributions already submited to HMRC: $alreadySubmitedContributions"}
  {/if}

  {if $notInBatchContributions}
    <div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-closed">
      <div class="crm-accordion-header">
        Number of contributions that are not in any batch: {$notInBatchContributions}
      </div>
      <!-- /.crm-accordion-header -->
      <div class="crm-accordion-body">
        <table class="selector">
          <thead>
          <tr>
            <th>{ts}Name{/ts}</th>
            <th>{ts}Gift Aidable Amount{/ts}</th>
            <th>{ts}Total Amount{/ts}</th>
            <th>{ts}No of items{/ts}</th>
            <th>{ts}Type{/ts}</th>
            <th>{ts}Source{/ts}</th>
            <th>{ts}Recieved{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
          </thead>
          {foreach from=$contributionsNotInBatchRows item=row}
            <tr class="contribution" data-contribution-id="{$row.contribution_id}">
              <td>
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=$row.contact_id"}">{$row.display_name}</a>
              </td>
              <td>{$row.gift_aidable_amount}</td>
              <td>{$row.total_amount}</td>
              <td>{$row.line_items|@count}</td>
              <td>{$row.financial_account}</td>
              <td>{$row.source}</td>
              <td>{$row.receive_date}</td>
              <td>{$row.actions}</td>
            </tr>
          {/foreach}
        </table>
      </div>
      <!-- /.crm-accordion-body -->
    </div>
    <!-- /.crm-accordion-wrapper -->
  {else}
    {include file="CRM/Civigiftaid/Form/Task/EmptyAccordion.tpl" content="Number of contributions that not in any batch: $notInBatchContributions"}
  {/if}

  {$form.buttons.html}
</div>
