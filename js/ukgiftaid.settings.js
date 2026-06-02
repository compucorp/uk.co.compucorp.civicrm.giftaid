(function($, ts) {
  function updateFinancialTypesState() {
    var disabled = $('input#civigiftaid_globally_enabled_civigiftaid_globally_enabled').prop('checked');
    var $select = $('#civigiftaid_financial_types_enabled');
    $select.prop('disabled', disabled);
    // Sync the rendered crm-select2 widget so it greys out, not just the native control.
    var select2 = $.fn.select2;
    if (select2 && select2.amd) {
      // select2 v4.x reflects the disabled prop on change.
      $select.trigger('change.select2');
    }
    else if ($select.data('select2')) {
      // select2 v3.x.
      $select.select2('enable', !disabled);
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    updateFinancialTypesState();
  });

  $('input#civigiftaid_globally_enabled_civigiftaid_globally_enabled').on('change', function() {
    updateFinancialTypesState();
  });

}(CRM.$, CRM.ts('uk.co.compucorp.civicrm.giftaid')));
