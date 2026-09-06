;(function (window, document) {
  'use strict';

  function syncWorkflowModePanels(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var strings = ctx.strings && typeof ctx.strings === 'object' ? ctx.strings : {};
    var inUpgrade = !!ctx.inUpgrade;

    var workflowPanels = document.querySelectorAll('[data-gs-workflow-status-panel]');
    var modePanels = document.querySelectorAll('[data-gs-mode-panel]');
    var modeBadgeEl = document.getElementById('gs-editor-mode-badge');

    if (!workflowPanels.length && !modePanels.length && !modeBadgeEl) {
      return;
    }

    var workflowValues = inUpgrade
      ? {
          analyze: String(strings.workflowReadyReadonly || 'Ready for read-only inspection'),
          changes: String(strings.workflowNoChangesStaged || 'No changes staged'),
          preview: String(strings.workflowNoPreview || 'No preview is active.'),
          approval: String(strings.workflowNoApproval || 'No approval is pending.'),
          apply: String(strings.workflowApplyInactive || 'Apply is inactive')
        }
      : {
          analyze: String(strings.workflowLoadBeforeAnalysis || 'Load a resource before analysis.'),
          changes: String(strings.workflowNoDiff || 'No diff is available.'),
          preview: String(strings.workflowNoPreview || 'No preview is active.'),
          approval: String(strings.workflowNoApproval || 'No approval is pending.'),
          apply: String(strings.workflowNoApply || 'No apply action is active.')
        };

    Array.prototype.forEach.call(workflowPanels, function (panel) {
      if (!panel) {
        return;
      }
      var analyzeNode = panel.querySelector('[data-gs-workflow-analyze]');
      var changesNode = panel.querySelector('[data-gs-workflow-changes]');
      var previewNode = panel.querySelector('[data-gs-workflow-preview]');
      var approvalNode = panel.querySelector('[data-gs-workflow-approval]');
      var applyNode = panel.querySelector('[data-gs-workflow-apply]');

      if (analyzeNode) {
        analyzeNode.textContent = workflowValues.analyze;
      }
      if (changesNode) {
        changesNode.textContent = workflowValues.changes;
      }
      if (previewNode) {
        previewNode.textContent = workflowValues.preview;
      }
      if (approvalNode) {
        approvalNode.textContent = workflowValues.approval;
      }
      if (applyNode) {
        applyNode.textContent = workflowValues.apply;
      }
    });

    var modeStates = {
      read_only: String(strings.modeStateActive || 'Active'),
      create: String(strings.modeStatePlanned || 'Planned'),
      edit: String(strings.modeStateNotActive || 'Not active'),
      upgrade: String(strings.modeStateRequiresGovernance || 'Requires governed workflow')
    };
    var activeMode = 'read_only';
    if (inUpgrade) {
      modeStates.upgrade = String(strings.modeStateContextAvailable || 'Context available');
    }

    Array.prototype.forEach.call(modePanels, function (panel) {
      if (!panel) {
        return;
      }
      Array.prototype.forEach.call(panel.querySelectorAll('[data-gs-mode-chip]'), function (chip) {
        var chipMode = String(chip.getAttribute('data-gs-mode-chip') || '').trim();
        var isActive = chipMode !== '' && chipMode === activeMode;
        chip.setAttribute('data-mode-active', isActive ? '1' : '0');
        var stateNode = chip.querySelector('[data-gs-mode-state]');
        if (stateNode) {
          stateNode.textContent = String(modeStates[chipMode] || String(strings.modeStateNotActive || 'Not active'));
        }
      });
    });

    if (modeBadgeEl) {
      modeBadgeEl.classList.remove('badge-editing', 'badge-creating');
      modeBadgeEl.classList.add(inUpgrade ? 'badge-editing' : 'badge-creating');
      modeBadgeEl.textContent = inUpgrade ? String(strings.badgeUpgrade || '') : String(strings.badgeCreate || '');
    }
  }

  window.gsSyncWorkflowModePanels = syncWorkflowModePanels;
}(window, document));
