(function($, ts) {
  const checkbox = 'input#civigiftaid_globally_enabled_civigiftaid_globally_enabled';
  const select = '#civigiftaid_financial_types_enabled';

  function updateFinancialTypesVisibility() {
    let $select = $(select);
    let $row = $select.closest('tr');

    if ($(checkbox).prop('checked')) {
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
