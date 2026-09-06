(function (window, document) {
  'use strict';

  function escapeGateErrorText(value) {
    return String(value || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function initApplyFormGate(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var strings = ctx.strings && typeof ctx.strings === 'object' ? ctx.strings : {};
    var switchTab = typeof ctx.switchTab === 'function' ? ctx.switchTab : null;
    var applyForm = document.getElementById('gs-apply-form');

    if (!applyForm) {
      return;
    }

    applyForm.addEventListener('submit', function (event) {
      const submitter = event.submitter;
      const isApplySubmit = !!(submitter && submitter.id === 'btn-apply');
      if (!isApplySubmit) {
        return;
      }

      const gateErrors = [];
      const analyzeOk = applyForm.getAttribute('data-analyze-ok') === '1';
      const reasonInput = document.getElementById('gs-apply-reason');
      const confirmationInput = document.getElementById('gs-apply-confirmation');
      const gateErrorsBox = document.getElementById('gs-apply-gate-errors');

      if (!analyzeOk) {
        gateErrors.push(applyForm.getAttribute('data-err-analyze') || '');
      }
      if (!confirmationInput || !confirmationInput.checked) {
        gateErrors.push(applyForm.getAttribute('data-err-confirm') || '');
      }
      if (!reasonInput || reasonInput.value.trim() === '') {
        gateErrors.push(applyForm.getAttribute('data-err-reason') || '');
      }

      // Block apply when breaking changes exist and not yet acknowledged
      var hasBreakingChangesNow = parseInt((document.getElementById('gs-change-high') || {}).textContent || '0', 10) > 0;
      if (hasBreakingChangesNow) {
        var riskAckInput = applyForm.querySelector('input[name="risk_acknowledged"]');
        if (!riskAckInput || !riskAckInput.checked) {
          gateErrors.push(String(strings.breakingChangesBlocked || ''));
        }
      }

      if (gateErrors.length > 0) {
        event.preventDefault();
        if (gateErrorsBox) {
          const title = applyForm.getAttribute('data-err-title') || '';
          gateErrorsBox.style.display = 'block';
          gateErrorsBox.innerHTML = '<strong>' +
            escapeGateErrorText(title) +
            '</strong><ul class="u-style-b6c4a08dd1">' +
            gateErrors.map(function (err) { return '<li>' + escapeGateErrorText(err) + '</li>'; }).join('') +
            '</ul>';
        }
        if (switchTab) {
          switchTab('apply');
        }
      }
    });
  }

  window.gsInitApplyFormGate = initApplyFormGate;
}(window, document));
