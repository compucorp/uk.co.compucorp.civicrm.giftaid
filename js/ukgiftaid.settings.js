(function($, ts) {
  var CHECKBOX = 'input#civigiftaid_globally_enabled_civigiftaid_globally_enabled';
  var SELECT = '#civigiftaid_financial_types_enabled';

  function updateFinancialTypesState() {
    var disabled = $(CHECKBOX).prop('checked');
    var $select = $(SELECT);
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

  // Use jQuery ready (idempotent) rather than DOMContentLoaded: CiviCRM injects
  // this script via addScriptFile, so DOMContentLoaded has usually already fired.
  $(function() {
    updateFinancialTypesState();

    $(CHECKBOX).on('change', updateFinancialTypesState);

    // A disabled control is not submitted, which would wipe the saved financial
    // types when the form is saved with the global checkbox ticked. Re-enable it
    // just before submit so its value is posted.
    $(SELECT).closest('form').on('submit', function() {
      $(SELECT).prop('disabled', false);
    });
  });

}(CRM.$, CRM.ts('uk.co.compucorp.civicrm.giftaid')));
