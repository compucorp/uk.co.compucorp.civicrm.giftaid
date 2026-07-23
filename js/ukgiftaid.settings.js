(function($, ts) {
  const checkbox = 'input#civigiftaid_globally_enabled';
  const select = '#civigiftaid_financial_types_enabled';

  function updateFinancialTypesVisibility() {
    let $select = $(select);
    let $row = $select.closest('tr');

    if ($(checkbox).prop('checked')) {
      if (($select.val() ?? []).length) {
        CRM.alert("Warning: by enabling all financial types for Gift Aid, the previous selection of financial types was lost; re-enabling the option will not restore the original list. If you did not mean to do this, do not press Save.", "Financial type selection cleared", "warning");
      }
      $select.val(null).trigger('change');
      $row.hide();
    }
    else {
      $row.show();
    }
  }

  $(function() {
    updateFinancialTypesVisibility();
    $(checkbox).on('change', updateFinancialTypesVisibility);
  });

}(CRM.$, CRM.ts('uk.co.compucorp.civicrm.giftaid')));
