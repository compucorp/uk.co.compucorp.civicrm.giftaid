(function($, ts) {
  function updateVisibleElements() {
    if ($('input#civigiftaid_globally_enabled_civigiftaid_globally_enabled').prop('checked')) {
      $('tr.crm--form-block-civigiftaid_financial_types_enabled').hide();
    }
    else {
      $('tr.crm--form-block-civigiftaid_financial_types_enabled').show();
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    updateVisibleElements();
  });

  $('input#civigiftaid_globally_enabled_civigiftaid_globally_enabled').on('change', function() {
    updateVisibleElements();
  });

}(CRM.$, CRM.ts('uk.co.compucorp.civicrm.giftaid')));
