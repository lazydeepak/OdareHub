(function (window, document) {
  'use strict';

  function renderImpactAnalysisRows(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var body = ctx.body || null;
    var rows = Array.isArray(ctx.rows) ? ctx.rows : [];
    var impactLabels = ctx.impactLabels && typeof ctx.impactLabels === 'object' ? ctx.impactLabels : {};
    var impactCategoryLabels = ctx.impactCategoryLabels && typeof ctx.impactCategoryLabels === 'object' ? ctx.impactCategoryLabels : {};
    var emptyImpactLabel = String(ctx.emptyImpactLabel || '');

    if (!body) {
      return;
    }

    if (rows.length === 0) {
      body.innerHTML = '<tr><td colspan="4" class="muted"></td></tr>';
      var emptyImpactCell = body.querySelector('td');
      if (emptyImpactCell) emptyImpactCell.textContent = emptyImpactLabel;
      return;
    }

    var frag = document.createDocumentFragment();
    rows.forEach(function (row) {
      var tr = document.createElement('tr');
      var severity = String(row.severity || 'low').toLowerCase();
      var severityLabel = impactLabels[severity] || severity.toUpperCase();
      var chipClass = severity === 'high' ? 'danger' : (severity === 'medium' ? 'warning' : 'success');

      var tdChange = document.createElement('td');
      tdChange.textContent = impactCategoryLabels[String(row.change || '')] || String(row.change || '');
      var tdField = document.createElement('td');
      tdField.textContent = String(row.field || row.path || '');
      var tdAffects = document.createElement('td');
      tdAffects.textContent = Array.isArray(row.affects) ? row.affects.join(', ') : '';
      var tdSeverity = document.createElement('td');
      var chip = document.createElement('span');
      chip.className = 'status-chip ' + chipClass;
      chip.textContent = severityLabel;
      tdSeverity.appendChild(chip);

      tr.appendChild(tdChange);
      tr.appendChild(tdField);
      tr.appendChild(tdAffects);
      tr.appendChild(tdSeverity);
      frag.appendChild(tr);
    });
    body.innerHTML = '';
    body.appendChild(frag);
  }

  function renderMigrationPlanRows(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var body = ctx.body || null;
    var plan = Array.isArray(ctx.plan) ? ctx.plan : [];
    var migrationStrategyLabels = ctx.migrationStrategyLabels && typeof ctx.migrationStrategyLabels === 'object' ? ctx.migrationStrategyLabels : {};
    var impactLabels = ctx.impactLabels && typeof ctx.impactLabels === 'object' ? ctx.impactLabels : {};
    var emptyMigrationLabel = String(ctx.emptyMigrationLabel || '');

    if (!body) {
      return;
    }

    if (plan.length === 0) {
      body.innerHTML = '<tr><td colspan="4" class="muted"></td></tr>';
      var emptyCell = body.querySelector('td');
      if (emptyCell) emptyCell.textContent = emptyMigrationLabel;
      return;
    }

    var frag = document.createDocumentFragment();
    plan.forEach(function (step) {
      var tr = document.createElement('tr');
      var risk = String(step.risk || 'low').toLowerCase();
      var chipClass = risk === 'high' ? 'danger' : (risk === 'medium' ? 'warning' : 'success');
      var strategy = String(step.strategy || 'safe');

      var tdAction = document.createElement('td');
      tdAction.textContent = String(step.action || '');
      var tdField = document.createElement('td');
      tdField.textContent = String(step.field || '');
      var tdStrategy = document.createElement('td');
      tdStrategy.textContent = migrationStrategyLabels[strategy] || strategy;
      var tdRisk = document.createElement('td');
      var chip = document.createElement('span');
      chip.className = 'status-chip ' + chipClass;
      chip.textContent = impactLabels[risk] || risk.toUpperCase();
      tdRisk.appendChild(chip);

      tr.appendChild(tdAction);
      tr.appendChild(tdField);
      tr.appendChild(tdStrategy);
      tr.appendChild(tdRisk);
      frag.appendChild(tr);
    });
    body.innerHTML = '';
    body.appendChild(frag);
  }

  function renderSimulationPreviewRows(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var body = ctx.body || null;
    var viewsAfter = Array.isArray(ctx.viewsAfter) ? ctx.viewsAfter : [];
    var brokenViews = Array.isArray(ctx.brokenViews) ? ctx.brokenViews : [];
    var removedFields = Array.isArray(ctx.removedFields) ? ctx.removedFields : [];
    var newFields = Array.isArray(ctx.newFields) ? ctx.newFields : [];
    var simulationReasonLabels = ctx.simulationReasonLabels && typeof ctx.simulationReasonLabels === 'object' ? ctx.simulationReasonLabels : {};
    var emptySimulationLabel = String(ctx.emptySimulationLabel || '');

    if (!body) {
      return;
    }

    if (viewsAfter.length === 0 && brokenViews.length === 0 && removedFields.length === 0 && newFields.length === 0) {
      body.innerHTML = '<tr><td colspan="4" class="muted"></td></tr>';
      var emptyCell = body.querySelector('td');
      if (emptyCell) emptyCell.textContent = emptySimulationLabel;
      return;
    }

    var viewNames = viewsAfter.map(function (viewItem) {
      return String((viewItem && viewItem.view) || '');
    }).filter(Boolean);
    var brokenReasons = brokenViews.map(function (broken) {
      var reason = String((broken && broken.reason) || '');
      return simulationReasonLabels[reason] || reason;
    }).filter(Boolean);

    var row = document.createElement('tr');

    var tdViews = document.createElement('td');
    tdViews.textContent = viewNames.join(', ');
    var tdBroken = document.createElement('td');
    tdBroken.textContent = brokenReasons.join(', ');
    var tdRemoved = document.createElement('td');
    tdRemoved.textContent = removedFields.join(', ');
    var tdNew = document.createElement('td');
    tdNew.textContent = newFields.join(', ');

    row.appendChild(tdViews);
    row.appendChild(tdBroken);
    row.appendChild(tdRemoved);
    row.appendChild(tdNew);

    body.innerHTML = '';
    body.appendChild(row);
  }

  function renderChangeSummaryRows(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var body = ctx.body || null;
    var changes = Array.isArray(ctx.changes) ? ctx.changes : [];
    var impactLabels = ctx.impactLabels && typeof ctx.impactLabels === 'object' ? ctx.impactLabels : {};
    var severityLabels = ctx.severityLabels && typeof ctx.severityLabels === 'object' ? ctx.severityLabels : {};
    var categoryFieldsLabel = String(ctx.categoryFieldsLabel || '');
    var categoryViewLabel = String(ctx.categoryViewLabel || '');
    var categoryNavLabel = String(ctx.categoryNavLabel || '');
    var emptyChangesLabel = String(ctx.emptyChangesLabel || '');

    function stringifyValue(value) {
      if (value === null || typeof value === 'undefined') return '-';
      if (typeof value === 'object') {
        try {
          return JSON.stringify(value);
        } catch (error) {
          return '[object]';
        }
      }
      return String(value);
    }

    if (!body) {
      return;
    }

    if (changes.length === 0) {
      body.innerHTML = '<tr><td colspan="4" class="muted"></td></tr>';
      var emptyCell = body.querySelector('td');
      if (emptyCell) emptyCell.textContent = emptyChangesLabel;
      return;
    }

    var frag = document.createDocumentFragment();
    changes.forEach(function (change) {
      var category = categoryFieldsLabel;
      if (change.type === 'view_changed') category = categoryViewLabel;
      if (change.type === 'nav_changed') category = categoryNavLabel;

      var tr = document.createElement('tr');
      var impact = String(change.impact || 'low').toLowerCase();
      var impactLabel = impactLabels[impact] || impact.toUpperCase();
      var severityLabel = severityLabels[impact] || String(change.impact || 'low').toUpperCase();
      var detail = String(change.path || '') + ': ' + stringifyValue(change.before) + ' -> ' + stringifyValue(change.after);

      var tdCategory = document.createElement('td');
      tdCategory.textContent = category;
      var tdType = document.createElement('td');
      tdType.textContent = String(change.type || '');
      var tdDetail = document.createElement('td');
      tdDetail.textContent = detail;
      var tdImpact = document.createElement('td');
      var chip = document.createElement('span');
      chip.className = 'status-chip ' + (impact === 'high' ? 'danger' : (impact === 'medium' ? 'warning' : 'success'));
      chip.textContent = impactLabel;
      tdImpact.appendChild(chip);
      var tdSeverity = document.createElement('td');
      var sevChip = document.createElement('span');
      sevChip.className = 'status-chip ' + (impact === 'high' ? 'danger' : (impact === 'medium' ? 'warning' : 'success'));
      sevChip.textContent = severityLabel;
      tdSeverity.appendChild(sevChip);

      tr.appendChild(tdCategory);
      tr.appendChild(tdType);
      tr.appendChild(tdDetail);
      tr.appendChild(tdImpact);
      tr.appendChild(tdSeverity);
      frag.appendChild(tr);
    });

    body.innerHTML = '';
    body.appendChild(frag);
  }

  window.gsRenderImpactAnalysisRows = renderImpactAnalysisRows;
  window.gsRenderMigrationPlanRows = renderMigrationPlanRows;
  window.gsRenderSimulationPreviewRows = renderSimulationPreviewRows;
  window.gsRenderChangeSummaryRows = renderChangeSummaryRows;
}(window, document));
