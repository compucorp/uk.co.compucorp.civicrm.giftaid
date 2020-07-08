{*
 * https://civicrm.org/license
 *}

<div id="gift-aid-add" class="crm-block crm-form-block crm-export-form-block gift-aid">
  <h2>{ts}Add To Gift Aid{/ts}</h2>

  <div class="help">
    <p>{ts}Use this form to submit Gift Aid contributions. Note that this action is irreversible, i.e. you cannot take contributions out of a batch once they have been added.{/ts}</p>
    <p><strong>Possible reasons for contributions not valid for gift aid:</strong></p>
    <ol>
      <li>Contribution status is not 'Completed'</li>
      <li>Related Contact does not have a valid gift aid declaration</li>
      <li>Related Contact's gift aid declaration does not cover the contribution date</li>
    </ol>
  </div>

  <table class="form-layout">
    <tr>
      <td>
        <table class="form-layout">
          <tr>
            <td class="label">{$form.title.label}</td>
            <td>{$form.title.html}</td>
          <tr>
          <tr>
            <td class="label">{$form.description.label}</td>
            <td>{$form.description.html}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl"}</div>

  <div class="clear"></div>

  <h3>{ts}Summary{/ts}</h3>

  <p>Number of contributions that were selected: {$selectedContributions}</p>

  {if $totalAddedContributions}
    <div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-closed">
      <div class="crm-accordion-header">
        Number of contributions that will be added to this batch: {$totalAddedContributions}
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
            <th>{ts}Received{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
          </thead>
          {foreach from=$contributionsAddedRows item=row}
            <tr class="contribution" data-contribution-id="{$row.contribution_id}">
              <td>
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.display_name}</a>
              </td>
              <td>{$row.gift_aidable_amount|crmMoney:$row.currency}</td>
              <td>{$row.total_amount|crmMoney:$row.currency}</td>
              <td>{$row.line_items_count}</td>
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
    {include file="CRM/Civigiftaid/Form/Task/EmptyAccordion.tpl" content="Number of contributions that will be added to this batch: $totalAddedContributions"}
  {/if}
  {if $alreadyAddedContributions}
    <div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-closed">
      <div class="crm-accordion-header">
        Number of contributions already in a batch (will not be added to batch): {$alreadyAddedContributions}
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
            <th>{ts}Received{/ts}</th>
            <th>{ts}Batch{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
          </thead>
          {foreach from=$contributionsAlreadyAddedRows item=row}
            <tr class="contribution" data-contribution-id="{$row.contribution_id}">
              <td>
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.display_name}</a>
              </td>
              <td>{$row.gift_aidable_amount|crmMoney:$row.currency}</td>
              <td>{$row.total_amount|crmMoney:$row.currency}</td>
              <td>{$row.line_items_count}</td>
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
    {include file="CRM/Civigiftaid/Form/Task/EmptyAccordion.tpl" content="Number of contributions already in a batch: $alreadyAddedContributions"}
  {/if}
  {if $notValidContributions}
    <div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-closed">
      <div class="crm-accordion-header">
        Number of contributions not valid for gift aid (will not be added to batch): {$notValidContributions}
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
            <th>{ts}Received{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
          </thead>
          {foreach from=$contributionsNotValid item=row}
            <tr class="contribution" data-contribution-id="{$row.contribution_id}">
              <td>
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.display_name}</a>
              </td>
              <td>{$row.gift_aidable_amount|crmMoney:$row.currency}</td>
              <td>{$row.total_amount|crmMoney:$row.currency}</td>
              <td>{$row.line_items_count}</td>
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
    {include file="CRM/Civigiftaid/Form/Task/EmptyAccordion.tpl" content="Number of contributions not valid for gift aid: $notValidContributions"}
  {/if}
</div>
