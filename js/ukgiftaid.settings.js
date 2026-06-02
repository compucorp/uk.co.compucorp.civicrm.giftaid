(function($, ts) {
  var CHECKBOX = 'input#civigiftaid_globally_enabled_civigiftaid_globally_enabled';
  var SELECT = '#civigiftaid_financial_types_enabled';

  function updateFinancialTypesVisibility() {
    var $select = $(SELECT);
    // Resolve the containing row from the field itself rather than a hard-coded
    // row class — the previous selector (tr.crm--form-block-...) never matched
    // the rendered markup, so the original handler was a silent no-op.
    var $row = $select.closest('tr');

    if ($(CHECKBOX).prop('checked')) {
      // Gift aid applies to line items of ANY financial type, so the per-type
      // "Enabled Financial Types" list is redundant: clear it and hide the row.
      // The <select> stays in the DOM (just hidden), so the cleared value is
      // submitted and the redundant selection is not persisted.
      $select.val(null).trigger('change');
      $row.hide();
    }
    else {
      $row.show();
    }
  }

  // Use jQuery ready (runs immediately if the DOM is already parsed) rather than
  // a DOMContentLoaded listener: CiviCRM injects this script via addScriptFile,
  // by which point DOMContentLoaded has usually already fired.
  $(function() {
    updateFinancialTypesVisibility();
    $(CHECKBOX).on('change', updateFinancialTypesVisibility);
  });

}(CRM.$, CRM.ts('uk.co.compucorp.civicrm.giftaid')));
