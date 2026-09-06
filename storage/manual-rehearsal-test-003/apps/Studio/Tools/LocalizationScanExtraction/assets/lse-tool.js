(function () {
  'use strict';

  var root = document.getElementById('lse-tool-root');
  if (!root) return;

  var findingsData = [];
  var detailCopy = {};
  var asyncCopy = {};
  var selectedIndex = -1;
  var activeCategory = 'all';
  var lastFullScan = null;
  var lastOwnerScan = null;
  var activeFetch = null;
  var selectedCampaignOwners = {};
  var campaignName = '';
  var campaignDescription = '';

  try {
    var findingsScript = document.getElementById('lse-findings-data');
    if (findingsScript) findingsData = JSON.parse(findingsScript.textContent || '[]');
  } catch (_) {}
  try {
    var detailCopyScript = document.getElementById('lse-detail-copy');
    if (detailCopyScript) detailCopy = JSON.parse(detailCopyScript.textContent || '{}');
  } catch (_) {}
  try {
    var asyncCopyScript = document.getElementById('lse-async-copy');
    if (asyncCopyScript) asyncCopy = JSON.parse(asyncCopyScript.textContent || '{}');
  } catch (_) {}

  var runBtn = document.getElementById('lse-run-btn');
  var ownerSelect = document.getElementById('lse-owner');
  var scanForm = document.getElementById('lse-scan-form');
  var progressWrap = document.getElementById('lse-scan-progress');
  var progressFill = document.getElementById('lse-progress-fill');
  var progressPercent = document.getElementById('lse-progress-percent');
  var progressLabel = document.getElementById('lse-progress-label');
  var progressTrack = progressWrap ? progressWrap.querySelector('.lse-progress-track') : null;
  var resultsPanel = document.getElementById('lse-results-panel');
  var asyncResults = document.getElementById('lse-async-results');

  function text(key, fallback) {
    return asyncCopy[key] || detailCopy[key] || fallback || key;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[ch];
    });
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(String(value == null ? '' : value));
    }
    return String(value == null ? '' : value).replace(/["\\]/g, '\\$&');
  }

  function numberValue(value) {
    var parsed = parseInt(value || 0, 10);
    return isNaN(parsed) ? 0 : parsed;
  }

  function setProgress(value, label) {
    var pct = Math.max(0, Math.min(100, value));
    if (progressFill) progressFill.style.width = pct + '%';
    if (progressPercent) progressPercent.textContent = pct + '%';
    if (progressLabel && label) progressLabel.textContent = label;
    if (progressTrack) progressTrack.setAttribute('aria-valuenow', String(pct));
  }

  function summaryCard(label, value, cls) {
    return '<div class="lse-summary-card has-data ' + (cls || '') + '">' +
      '<div class="lse-summary-value">' + numberValue(value) + '</div>' +
      '<div class="lse-summary-label">' + escapeHtml(label) + '</div>' +
      '</div>';
  }

  function keyValueRows(ownerScan) {
    var plan = ownerScan && ownerScan.missing_key_review_plan ? ownerScan.missing_key_review_plan : {};
    var rows = Array.isArray(plan.rows) ? plan.rows : [];
    return rows.map(function (row) {
      var confidence = row.suggestion_confidence || 'none';
      var value = row.suggested_english_value || '';
      var ready = row.suggestion_ready === true || row.suggestion_ready === 1 || row.suggestion_ready === '1';
      var reason = row.suggestion_rejection_reason || '';
      return {
        key: row.key || '',
        value: value,
        confidence: confidence,
        tier: row.suggestion_tier || confidence,
        reason: reason,
        evidenceType: row.suggestion_evidence_type || '',
        evidenceSource: row.suggestion_evidence_source || '',
        suggestionReason: row.suggestion_reason || '',
        file: row.first_file || '',
        line: row.first_line || 0,
        safe: value !== '' && ready
      };
    });
  }

  function confidenceLabel(confidence) {
    return text('confidence_' + confidence, confidence);
  }

  function notReadyReasonLabel(reason, confidence) {
    if (reason) {
      return text('not_ready_' + reason, reason.replace(/_/g, ' '));
    }
    if (confidence === 'low') return text('not_ready_low_confidence', 'low confidence');
    if (confidence === 'none') return text('not_ready_no_suggestion', 'no safe suggestion');
    return text('not_ready_needs_review', 'needs review');
  }

  function computeV2Breakdown(findingsArr) {
    var inlineFindings = 0, techTerms = 0, falsePositives = 0;
    var relDist = { critical: 0, high: 0, medium: 0, low: 0 };
    var semBreakdown = {};
    var arr = Array.isArray(findingsArr) ? findingsArr : [];
    arr.forEach(function (f) {
      if (f.type !== 'inline_text') return;
      var cat = f.category || '';
      var rel = f.relevance || 'low';
      var sem = f.semantic_category || 'general';
      if (cat === 'human_facing_candidate') {
        inlineFindings++;
        if (!semBreakdown[sem]) semBreakdown[sem] = 0;
        semBreakdown[sem]++;
      } else if (cat === 'internal_string') {
        techTerms++;
      }
      if (relDist.hasOwnProperty(rel)) relDist[rel]++;
    });
    return { inlineFindings: inlineFindings, semBreakdown: semBreakdown, techTerms: techTerms, falsePositives: falsePositives, relDist: relDist };
  }

  function renderSemBreakdown(semBreakdown) {
    var keys = Object.keys(semBreakdown);
    keys.sort(function (a, b) { return (semBreakdown[b] || 0) - (semBreakdown[a] || 0); });
    var html = '<div class="lse-breakdown-list">';
    keys.forEach(function (k) {
      var label = k.charAt(0).toUpperCase() + k.slice(1).replace(/_/g, ' ');
      html += '<span class="lse-breakdown-item"><span class="lse-breakdown-label">' + escapeHtml(label) + '</span><span class="lse-breakdown-count">' + semBreakdown[k] + '</span></span>';
    });
    html += '</div>';
    return html;
  }

  function confidenceBreakdown(rows) {
    var counts = { auto_safe: 0, high: 0, medium: 0 };
    rows.forEach(function (row) {
      if (row.confidence === 'auto_safe') counts.auto_safe++;
      if (row.confidence === 'high') counts.high++;
      if (row.confidence === 'medium') counts.medium++;
    });
    return counts;
  }

  function migrationCounts(plan) {
    var counts = plan && plan.counts ? plan.counts : {};
    return {
      ready: numberValue(counts.ready_to_migrate),
      review: numberValue(counts.needs_review),
      rejected: numberValue(counts.rejected),
      total: numberValue(plan && plan.total)
    };
  }

  function migrationCandidates(plan, state) {
    var candidates = plan && Array.isArray(plan.candidates) ? plan.candidates : [];
    return candidates.filter(function (candidate) {
      return (candidate.state || '') === state;
    });
  }

  function migrationQualityCounts(plan) {
    var counts = plan && plan.key_quality_counts ? plan.key_quality_counts : {};
    return {
      excellent: numberValue(counts.excellent),
      good: numberValue(counts.good),
      acceptable: numberValue(counts.acceptable),
      needs_review: numberValue(counts.needs_review)
    };
  }

  function reviewReasonLabel(reason) {
    return text('review_reason_' + reason, String(reason || 'other').replace(/_/g, ' '));
  }

  function reviewPriorityLabel(priority) {
    return text('review_priority_' + priority, String(priority || 'manual_review').replace(/_/g, ' '));
  }

  function orderedReviewReasons(counts) {
    var source = counts || {};
    var order = ['domain_vocabulary', 'ambiguous_context', 'long_description', 'duplicate_candidate', 'technical_term', 'naming_uncertain', 'reusable_ui_term', 'mixed_usage', 'other'];
    return order.map(function (key) {
      return { key: key, count: numberValue(source[key]) };
    }).filter(function (item) {
      return item.count > 0;
    });
  }

  function reviewPriorityCounts(planOrCounts) {
    var counts = planOrCounts && planOrCounts.review_priority_counts ? planOrCounts.review_priority_counts : (planOrCounts || {});
    return {
      likely_migratable: numberValue(counts.likely_migratable),
      manual_review: numberValue(counts.manual_review),
      blocked: numberValue(counts.blocked)
    };
  }

  function renderReviewBreakdown(counts) {
    var items = orderedReviewReasons(counts);
    if (items.length === 0) return '';
    var html = '<div class="lse-review-breakdown">';
    items.forEach(function (item) {
      html += '<span class="lse-review-breakdown-item"><span>' + escapeHtml(reviewReasonLabel(item.key)) + '</span><strong>' + item.count + '</strong></span>';
    });
    html += '</div>';
    return html;
  }

  function ownerReadyTotal(row) {
    return numberValue(row.ready_missing_key_corrections || row.ready_auto_fix_keys) + numberValue(row.inline_ready_to_migrate);
  }

  function ownerRejectedTotal(row) {
    return numberValue(row.rejected_total != null ? row.rejected_total : row.rejected_unsafe_keys);
  }

  function suggestedCampaignOwners(owners, ownerKeys) {
    return ownerKeys.slice().sort(function (a, b) {
      var rowA = owners[a] || {};
      var rowB = owners[b] || {};
      var readyDiff = ownerReadyTotal(rowB) - ownerReadyTotal(rowA);
      if (readyDiff !== 0) return readyDiff;
      var rejectedDiff = ownerRejectedTotal(rowA) - ownerRejectedTotal(rowB);
      if (rejectedDiff !== 0) return rejectedDiff;
      return a.localeCompare(b);
    }).filter(function (ownerKey) {
      return ownerReadyTotal(owners[ownerKey] || {}) > 0;
    }).slice(0, 3);
  }

  function campaignEligibleOwners(owners, ownerKeys) {
    return ownerKeys.filter(function (ownerKey) {
      return !!owners[ownerKey];
    });
  }

  function ensureCampaignDefaults(owners, ownerKeys) {
    if (!campaignName) campaignName = text('campaign_default_name', 'Localization Wave 1');
    if (!campaignDescription) campaignDescription = text('campaign_default_description', 'Read-only campaign plan for the highest-value localization reductions.');
    if (campaignSelectedKeys(owners).length > 0) return;
    campaignEligibleOwners(owners, ownerKeys).forEach(function (ownerKey) {
      selectedCampaignOwners[ownerKey] = true;
    });
  }

  function campaignSelectedKeys(owners) {
    return Object.keys(selectedCampaignOwners).filter(function (ownerKey) {
      return selectedCampaignOwners[ownerKey] && owners[ownerKey];
    });
  }

  function campaignTotals(owners, selectedKeys) {
    return selectedKeys.reduce(function (acc, ownerKey) {
      var row = owners[ownerKey] || {};
      var readyMissing = numberValue(row.ready_missing_key_corrections || row.ready_auto_fix_keys);
      var readyInline = numberValue(row.inline_ready_to_migrate);
      acc.readyMissing += readyMissing;
      acc.readyInline += readyInline;
      acc.needsReview += numberValue(row.needs_review_total != null ? row.needs_review_total : row.needs_review_keys);
      acc.rejected += ownerRejectedTotal(row);
      acc.estimatedMissing += numberValue(row.estimated_missing_key_reductions != null ? row.estimated_missing_key_reductions : readyMissing);
      acc.estimatedInline += numberValue(row.estimated_inline_reductions != null ? row.estimated_inline_reductions : readyInline);
      acc.estimatedKeys += numberValue(row.estimated_keys_created != null ? row.estimated_keys_created : readyMissing + readyInline);
      acc.estimatedFiles += numberValue(row.estimated_files_modified);
      return acc;
    }, {
      readyMissing: 0,
      readyInline: 0,
      needsReview: 0,
      rejected: 0,
      estimatedMissing: 0,
      estimatedInline: 0,
      estimatedKeys: 0,
      estimatedFiles: 0
    });
  }

  function campaignQueue(owners, selectedKeys) {
    return selectedKeys.slice().sort(function (a, b) {
      var rowA = owners[a] || {};
      var rowB = owners[b] || {};
      var readyDiff = ownerReadyTotal(rowB) - ownerReadyTotal(rowA);
      if (readyDiff !== 0) return readyDiff;
      var rejectedDiff = ownerRejectedTotal(rowA) - ownerRejectedTotal(rowB);
      if (rejectedDiff !== 0) return rejectedDiff;
      return a.localeCompare(b);
    });
  }

  function renderCampaignSummaryHtml(owners) {
    var selectedKeys = campaignSelectedKeys(owners);
    var totals = campaignTotals(owners, selectedKeys);
    var queue = campaignQueue(owners, selectedKeys);
    var html = '';
    html += '<div class="lse-campaign-summary-grid lse-summary-grid">';
    html += summaryCard(text('campaign_ready_missing', 'Ready Missing Keys'), totals.readyMissing, 'lse-cat-already_localized');
    html += summaryCard(text('campaign_ready_inline', 'Ready Inline Migrations'), totals.readyInline, 'lse-cat-human_facing');
    html += summaryCard(text('needs_review', 'Needs Review'), totals.needsReview, 'lse-cat-ambiguous_string');
    html += summaryCard(text('migration_rejected_label', 'Rejected'), totals.rejected, 'lse-cat-missing_owner_key');
    html += '</div>';
    html += '<div class="lse-campaign-impact">';
    html += '<h5>' + escapeHtml(text('campaign_estimated_impact_title', 'Estimated Impact')) + '</h5>';
    html += '<div class="lse-campaign-impact-grid">';
    html += '<span>' + escapeHtml(text('campaign_estimated_missing_reductions', 'Estimated Missing-Key Reductions')) + ': <strong>' + totals.estimatedMissing + '</strong></span>';
    html += '<span>' + escapeHtml(text('campaign_estimated_inline_reductions', 'Estimated Inline Reductions')) + ': <strong>' + totals.estimatedInline + '</strong></span>';
    html += '<span>' + escapeHtml(text('campaign_estimated_keys_created', 'Estimated Keys Created')) + ': <strong>' + totals.estimatedKeys + '</strong></span>';
    html += '<span>' + escapeHtml(text('campaign_estimated_files_modified', 'Estimated Files Modified')) + ': <strong>' + totals.estimatedFiles + '</strong></span>';
    html += '</div></div>';
    html += '<div class="lse-campaign-progress">';
    html += '<h5>' + escapeHtml(text('campaign_progress_title', 'Progress Tracking')) + '</h5>';
    html += '<div class="lse-campaign-impact-grid">';
    html += '<span>' + escapeHtml(text('campaign_status_label', 'Status')) + ': <strong>' + escapeHtml(text('campaign_status_planned', 'Planned')) + '</strong></span>';
    html += '<span>' + escapeHtml(text('campaign_owners_completed', 'Owners Completed')) + ': <strong>0/' + selectedKeys.length + '</strong></span>';
    html += '<span>' + escapeHtml(text('campaign_inline_debt_reduced', 'Inline Debt Reduced')) + ': <strong>0</strong></span>';
    html += '<span>' + escapeHtml(text('campaign_missing_keys_corrected', 'Missing Keys Corrected')) + ': <strong>0</strong></span>';
    html += '</div></div>';
    html += '<div class="lse-campaign-queue">';
    html += '<h5>' + escapeHtml(text('campaign_execution_queue_title', 'Execution Queue')) + '</h5>';
    if (queue.length === 0) {
      html += '<div class="lse-summary-empty-state">' + escapeHtml(text('campaign_no_owners_selected', 'Select owners to build a campaign.')) + '</div>';
    } else {
      html += '<ol>';
      queue.forEach(function (ownerKey) {
        var row = owners[ownerKey] || {};
        html += '<li><button class="lse-btn is-link lse-owner-result-link" type="button" data-owner="' + escapeHtml(ownerKey) + '">' + escapeHtml(ownerKey) + '</button>';
        html += '<span class="lse-readonly-note">' + escapeHtml(text('campaign_queue_ready', 'Ready')) + ': ' + ownerReadyTotal(row) + ' · ' + escapeHtml(text('migration_rejected_label', 'Rejected')) + ': ' + ownerRejectedTotal(row) + '</span></li>';
      });
      html += '</ol>';
    }
    html += '</div>';
    return html;
  }

  function renderCampaignBuilder(owners, ownerKeys) {
    ensureCampaignDefaults(owners, ownerKeys);
    var selectedKeys = campaignSelectedKeys(owners);
    var html = '';
    html += '<details class="lse-campaign-builder lse-section-fold" data-campaign-builder="1">';
    html += '<summary class="lse-section-summary">';
    html += '<span class="lse-section-title">' + escapeHtml(text('campaign_builder_title', 'Campaign Builder')) + '</span>';
    html += '<span class="lse-badge is-info">' + escapeHtml(text('campaign_readonly_badge', 'Read-only Plan')) + '</span>';
    html += '<span class="lse-badge is-safe" data-campaign-selected-count>' + selectedKeys.length + ' ' + escapeHtml(text('campaign_owners_included', 'Owners Included')) + '</span>';
    html += '</summary>';
    html += '<div class="lse-section-body">';
    html += '<p class="lse-panel-desc">' + escapeHtml(text('campaign_builder_desc', 'Plan a localization reduction wave across owners. Campaigns do not apply changes; use existing owner workflows for execution.')) + '</p>';
    html += '<div class="lse-campaign-fields">';
    html += '<label><span>' + escapeHtml(text('campaign_name_label', 'Name')) + '</span><input type="text" class="lse-input" data-campaign-name value="' + escapeHtml(campaignName) + '"></label>';
    html += '<label><span>' + escapeHtml(text('campaign_description_label', 'Description')) + '</span><textarea class="lse-input" rows="2" data-campaign-description>' + escapeHtml(campaignDescription) + '</textarea></label>';
    html += '</div>';
    html += '<div class="lse-campaign-owner-picker">';
    html += '<div class="lse-intelligence-relevance-title">' + escapeHtml(text('campaign_owners_included', 'Owners Included')) + ' (' + selectedKeys.length + ')</div>';
    html += '<div class="lse-campaign-owner-actions">';
    html += '<button type="button" class="lse-filter-btn" data-campaign-select-all>' + escapeHtml(text('campaign_select_all', 'Select all')) + '</button>';
    html += '<button type="button" class="lse-filter-btn" data-campaign-select-none>' + escapeHtml(text('campaign_select_none', 'None')) + '</button>';
    html += '</div>';
    html += '<div class="lse-campaign-owner-grid">';
    ownerKeys.forEach(function (ownerKey) {
      var row = owners[ownerKey] || {};
      var ready = ownerReadyTotal(row);
      if (campaignEligibleOwners(owners, [ownerKey]).length === 0) return;
      html += '<label class="lse-campaign-owner-option">';
      html += '<input type="checkbox" data-campaign-owner="' + escapeHtml(ownerKey) + '"' + (selectedCampaignOwners[ownerKey] ? ' checked' : '') + '>';
      html += '<span><strong>' + escapeHtml(ownerKey) + '</strong><small>' + escapeHtml(text('campaign_queue_ready', 'Ready')) + ': ' + ready + ' · ' + escapeHtml(text('needs_review', 'Needs Review')) + ': ' + numberValue(row.needs_review_total != null ? row.needs_review_total : row.needs_review_keys) + ' · ' + escapeHtml(text('migration_rejected_label', 'Rejected')) + ': ' + ownerRejectedTotal(row) + '</small></span>';
      html += '</label>';
    });
    html += '</div></div>';
    html += '<div data-campaign-summary>' + renderCampaignSummaryHtml(owners) + '</div>';
    html += '</div>';
    html += '</details>';
    return html;
  }

  function renderMigrationPlan(ownerScan) {
    var plan = ownerScan && ownerScan.migration_plan ? ownerScan.migration_plan : null;
    if (!plan || numberValue(plan.total) === 0) return '';

    var counts = migrationCounts(plan);
    var qualityCounts = migrationQualityCounts(plan);
    var priorityCounts = reviewPriorityCounts(plan);
    var readyRows = migrationCandidates(plan, 'ready_to_migrate');
    var reviewRows = migrationCandidates(plan, 'needs_review');
    var ownerKey = ownerScan && ownerScan.owner_key ? ownerScan.owner_key : '';
    var scopeInput = scanForm ? (new FormData(scanForm).get('scope') || 'full') : 'full';
    var csrfInput = scanForm ? (new FormData(scanForm).get('csrf') || '') : '';
    var html = '';

    html += '<div class="lse-migration-plan lse-section-migration">';
    html += '<div class="lse-async-heading">';
    html += '<h4>' + escapeHtml(text('migration_plan_title', 'Inline Migration Planner')) + '</h4>';
    html += '<span class="lse-badge is-info">' + counts.total + ' ' + escapeHtml(text('migration_total_label', 'Total Candidates')) + '</span>';
    html += '</div>';
    html += '<p class="lse-panel-desc">' + escapeHtml(text('migration_plan_desc', 'Inline text candidates classified by migration readiness.')) + '</p>';
    html += '<div class="lse-migration-summary lse-summary-grid">';
    html += summaryCard(text('migration_ready_label', 'Ready to Migrate'), counts.ready, 'lse-mig-ready');
    html += summaryCard(text('migration_review_label', 'Needs Review'), counts.review, 'lse-mig-review');
    html += summaryCard(text('migration_rejected_label', 'Rejected'), counts.rejected, 'lse-mig-rejected');
    html += summaryCard(text('migration_total_label', 'Total Candidates'), counts.total);
    html += '</div>';
    html += '<details class="lse-key-quality-summary lse-section-fold">';
    html += '<summary class="lse-section-summary">';
    html += '<span class="lse-section-title">' + escapeHtml(text('migration_key_quality_summary', 'Key Quality Summary')) + '</span>';
    html += '<span class="lse-badge is-safe">' + qualityCounts.excellent + ' ' + escapeHtml(text('migration_quality_excellent', 'Excellent')) + '</span>';
    html += '<span class="lse-badge is-info">' + qualityCounts.good + ' ' + escapeHtml(text('migration_quality_good', 'Good')) + '</span>';
    html += '<span class="lse-badge is-warn">' + qualityCounts.needs_review + ' ' + escapeHtml(text('migration_quality_needs_review', 'Needs Review')) + '</span>';
    html += '</summary>';
    html += '<div class="lse-relevance-bar">';
    html += '<span class="lse-relevance-item lse-quality-excellent">' + escapeHtml(text('migration_quality_excellent', 'Excellent')) + ': ' + qualityCounts.excellent + '</span>';
    html += '<span class="lse-relevance-item lse-quality-good">' + escapeHtml(text('migration_quality_good', 'Good')) + ': ' + qualityCounts.good + '</span>';
    html += '<span class="lse-relevance-item lse-quality-acceptable">' + escapeHtml(text('migration_quality_acceptable', 'Acceptable')) + ': ' + qualityCounts.acceptable + '</span>';
    html += '<span class="lse-relevance-item lse-quality-needs_review">' + escapeHtml(text('migration_quality_needs_review', 'Needs Review')) + ': ' + qualityCounts.needs_review + '</span>';
    html += '</div></details>';

    if (counts.review > 0) {
      html += '<details class="lse-key-quality-summary lse-review-intelligence lse-section-fold">';
      html += '<summary class="lse-section-summary"><span class="lse-section-title">' + escapeHtml(text('review_intelligence_title', 'Review Intelligence')) + '</span>';
      html += '<span class="lse-review-summary-chip">' + escapeHtml(text('review_priority_likely_migratable', 'Likely Migratable')) + ': ' + priorityCounts.likely_migratable + '</span>';
      html += '<span class="lse-review-summary-chip">' + escapeHtml(text('review_priority_manual_review', 'Manual Review')) + ': ' + priorityCounts.manual_review + '</span>';
      html += '<span class="lse-review-summary-chip">' + escapeHtml(text('review_priority_blocked', 'Blocked')) + ': ' + priorityCounts.blocked + '</span></summary>';
      html += '<div class="lse-relevance-bar">';
      html += '<span class="lse-relevance-item lse-priority-likely_migratable">' + escapeHtml(text('review_priority_likely_migratable', 'Likely Migratable')) + ': ' + priorityCounts.likely_migratable + '</span>';
      html += '<span class="lse-relevance-item lse-priority-manual_review">' + escapeHtml(text('review_priority_manual_review', 'Manual Review')) + ': ' + priorityCounts.manual_review + '</span>';
      html += '<span class="lse-relevance-item lse-priority-blocked">' + escapeHtml(text('review_priority_blocked', 'Blocked')) + ': ' + priorityCounts.blocked + '</span>';
      html += '</div>';
      html += renderReviewBreakdown(plan.review_reason_counts || {});
      html += '</details>';
    }

    html += '<details class="lse-change-preview lse-inline-migration-preview lse-section-fold" id="lse-inline-migration-preview">';
    html += '<summary class="lse-section-summary">';
    html += '<span class="lse-section-title">' + escapeHtml(text('migration_preview_title', 'Inline Migration Preview')) + '</span>';
    html += '<span class="lse-badge ' + (counts.ready > 0 ? 'is-safe' : 'is-warn') + '">' + counts.ready + ' ' + escapeHtml(text('migration_ready_label', 'Ready to Migrate')) + '</span>';
    html += '</summary>';

    if (readyRows.length > 0) {
      html += '<p class="lse-panel-desc">' + escapeHtml(text('migration_preview_desc', 'Ready-to-migrate inline text candidates that can be automatically extracted as translation keys.')) + '</p>';
      html += '<div class="lse-table-wrap lse-change-preview-scroll">';
      html += '<table class="lse-report-table lse-migration-table"><thead><tr>';
      html += '<th>' + escapeHtml(text('migration_col_text', 'Text')) + '</th>';
      html += '<th>' + escapeHtml(text('migration_col_suggested_key', 'Suggested Key')) + '</th>';
      html += '<th>' + escapeHtml(text('migration_col_suggested_english', 'Suggested English')) + '</th>';
      html += '<th>' + escapeHtml(text('migration_col_key_quality', 'Key Quality')) + '</th>';
      html += '<th>' + escapeHtml(text('col_file', 'File')) + '</th>';
      html += '<th>' + escapeHtml(text('col_line', 'Line')) + '</th>';
      html += '<th>' + escapeHtml(text('col_element_type', 'Element')) + '</th>';
      html += '<th>' + escapeHtml(text('col_relevance', 'Relevance')) + '</th>';
      html += '<th>' + escapeHtml(text('col_occurrences', 'Occurrences')) + '</th>';
      html += '</tr></thead><tbody>';
      readyRows.slice(0, 150).forEach(function (row) {
        var relevance = row.relevance || 'low';
      html += '<tr class="lse-mig-row lse-mig-ready_to_migrate">';
        html += '<td class="lse-cell-detected"><details class="lse-text-reveal"><summary><code>' + escapeHtml(String(row.text || '').slice(0, 80)) + '</code></summary>';
        html += '<div class="lse-migration-full-text">';
        html += '<dl>';
        html += '<div><dt>' + escapeHtml(text('migration_full_source_text', 'Full Source Text')) + '</dt><dd>' + escapeHtml(row.text || '') + '</dd></div>';
        html += '<div><dt>' + escapeHtml(text('migration_col_suggested_key', 'Suggested Key')) + '</dt><dd><code>' + escapeHtml(row.suggested_key || '') + '</code></dd></div>';
        html += '<div><dt>' + escapeHtml(text('migration_col_suggested_english', 'Suggested English')) + '</dt><dd>' + escapeHtml(row.suggested_english || '') + '</dd></div>';
        html += '<div><dt>' + escapeHtml(text('col_file', 'File')) + '</dt><dd class="lse-cell-file">' + escapeHtml(row.file || '') + '</dd></div>';
        html += '<div><dt>' + escapeHtml(text('col_occurrences', 'Occurrences')) + '</dt><dd>' + numberValue(row.occurrence_count || 1) + '</dd></div>';
        html += '</dl></div></details></td>';
        html += '<td><code>' + escapeHtml(row.suggested_key || '') + '</code></td>';
        html += '<td>' + escapeHtml(String(row.suggested_english || '').slice(0, 80)) + '</td>';
        html += '<td><span class="lse-badge lse-badge-sm lse-quality-' + escapeHtml(row.key_quality || 'needs_review') + '" title="' + escapeHtml(row.key_quality_reason || '') + '">' + escapeHtml(text('migration_quality_' + (row.key_quality || 'needs_review'), row.key_quality || 'Needs Review')) + '</span></td>';
        html += '<td class="lse-cell-file">' + escapeHtml(row.file || '') + '</td>';
        html += '<td>' + (numberValue(row.line) > 0 ? numberValue(row.line) : '-') + '</td>';
        html += '<td>' + escapeHtml(row.element_type || '-') + '</td>';
        html += '<td><span class="lse-rel-badge is-' + escapeHtml(relevance) + '">' + escapeHtml(text('relevance_' + relevance, relevance)) + '</span></td>';
        html += '<td>' + numberValue(row.occurrence_count || 1) + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table></div>';
      if (readyRows.length > 150) {
        html += '<p class="lse-readonly-note">' + escapeHtml(text('owner_findings_limited', 'Showing first 150 findings.')) + '</p>';
      }
      html += '<form class="lse-action-form" method="post" action="/apps/studio/tools/localization-scan-extraction/apply-inline-migration" style="margin-top:12px">';
      html += '<input type="hidden" name="csrf" value="' + escapeHtml(csrfInput) + '">';
      html += '<input type="hidden" name="owner" value="' + escapeHtml(ownerKey) + '">';
      html += '<input type="hidden" name="scope" value="' + escapeHtml(scopeInput) + '">';
      html += '<input type="hidden" name="locale" value="en">';
      html += '<button class="lse-btn" type="submit" onclick="return confirm(\'' + escapeHtml(text('migration_apply_confirm', 'Apply inline migrations now? Source files and the locale file will be modified. Snapshots will be taken for rollback.')) + '\')">' + escapeHtml(text('migration_apply_button', 'Apply Ready Inline Migrations')) + ' (' + counts.ready + ')</button>';
      html += '<span class="lse-readonly-note" style="margin-left:8px">' + escapeHtml(text('migration_apply_note', 'Snapshots will be taken before changes.')) + '</span>';
      html += '</form>';
    } else {
      html += '<div class="lse-summary-empty-state">';
      if (counts.review > 0 && counts.rejected > 0) {
        html += escapeHtml(text('migration_none_ready_review_rejected', 'No inline migrations are ready. Candidates need review or were rejected by safety checks.'));
      } else if (counts.review > 0) {
        html += escapeHtml(text('migration_none_ready_review', 'No inline migrations are ready. All candidates need review.'));
      } else if (counts.rejected > 0) {
        html += escapeHtml(text('migration_none_ready_rejected', 'No inline migrations are ready. All candidates were rejected by safety checks.'));
      } else {
        html += escapeHtml(text('migration_none_ready', 'No inline migrations are ready.'));
      }
      html += '</div>';
    }
    html += '</details>';

    if (reviewRows.length > 0) {
      html += '<details class="lse-change-preview lse-inline-review-preview lse-review-candidate-fold lse-section-fold">';
      html += '<summary class="lse-review-candidate-summary lse-section-summary">';
      html += '<span class="lse-review-candidate-title lse-section-title">' + escapeHtml(text('review_candidate_table_title', 'Review Candidate Table')) + '</span>';
      html += '<span class="lse-badge is-warn">' + reviewRows.length + ' ' + escapeHtml(text('migration_review_label', 'Needs Review')) + '</span>';
      html += '</summary>';
      html += '<div class="lse-table-wrap lse-change-preview-scroll">';
      html += '<table class="lse-report-table lse-migration-table lse-review-table"><thead><tr>';
      html += '<th>' + escapeHtml(text('migration_col_text', 'Text')) + '</th>';
      html += '<th>' + escapeHtml(text('review_col_reason', 'Review Reason')) + '</th>';
      html += '<th>' + escapeHtml(text('review_col_priority', 'Priority')) + '</th>';
      html += '<th>' + escapeHtml(text('col_semantic_category', 'Semantic Category')) + '</th>';
      html += '<th>' + escapeHtml(text('col_relevance', 'Relevance')) + '</th>';
      html += '<th>' + escapeHtml(text('col_occurrences', 'Occurrences')) + '</th>';
      html += '<th>' + escapeHtml(text('review_col_direction', 'Suggested Direction')) + '</th>';
      html += '</tr></thead><tbody>';
      reviewRows.slice(0, 150).forEach(function (row) {
        var relevance = row.relevance || 'low';
        var reason = row.review_reason || 'other';
        var priority = row.review_priority || 'manual_review';
        html += '<tr class="lse-mig-row lse-mig-needs_review">';
        html += '<td class="lse-cell-detected"><code>' + escapeHtml(String(row.text || '').slice(0, 90)) + '</code></td>';
        html += '<td><span class="lse-badge lse-badge-sm is-info">' + escapeHtml(reviewReasonLabel(reason)) + '</span></td>';
        html += '<td><span class="lse-badge lse-badge-sm lse-priority-' + escapeHtml(priority) + '">' + escapeHtml(reviewPriorityLabel(priority)) + '</span></td>';
        html += '<td>' + escapeHtml(row.semantic_category || '-') + '</td>';
        html += '<td><span class="lse-rel-badge is-' + escapeHtml(relevance) + '">' + escapeHtml(text('relevance_' + relevance, relevance)) + '</span></td>';
        html += '<td>' + numberValue(row.occurrence_count || 1) + '</td>';
        html += '<td>' + escapeHtml(row.suggested_direction || '') + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table></div>';
      if (reviewRows.length > 150) {
        html += '<p class="lse-readonly-note">' + escapeHtml(text('owner_findings_limited', 'Showing first 150 findings.')) + '</p>';
      }
      html += '</details>';
    }
    html += '</div>';

    return html;
  }

  function correctionStatus(row) {
    var readyMissing = numberValue(row.ready_missing_key_corrections || row.ready_auto_fix_keys);
    var readyInline = numberValue(row.inline_ready_to_migrate);
    var review = numberValue(row.needs_review_total != null ? row.needs_review_total : row.needs_review_keys);
    var rejected = numberValue(row.rejected_total != null ? row.rejected_total : row.rejected_unsafe_keys);
    var ready = readyMissing + readyInline;
    if (!row.scan_ok) {
      return { label: text('status_failed', 'Failed'), cls: 'is-error' };
    }
    if (ready === 0 && review === 0 && rejected === 0) {
      return { label: text('correction_status_clean', 'Clean'), cls: 'is-safe' };
    }
    if (ready > 0 && (review > 0 || rejected > 0)) {
      return { label: text('ops_status_mixed', 'Mixed'), cls: 'is-warn' };
    }
    if (ready > 0) {
      return { label: text('ops_status_ready_to_fix', 'Ready To Fix'), cls: 'is-safe' };
    }
    return { label: text('correction_status_review_needed', 'Review Needed'), cls: 'is-info' };
  }

  function operationAction(row) {
    if (numberValue(row.ready_missing_key_corrections || row.ready_auto_fix_keys) > 0) {
      return text('ops_action_correct', 'Correct');
    }
    if (numberValue(row.inline_ready_to_migrate) > 0) {
      return text('ops_action_migrate', 'Migrate');
    }
    return text('ops_action_review', 'Review');
  }

  function affectedFileCount(rows) {
    var files = {};
    rows.forEach(function (row) {
      if (row.file) files[row.file] = true;
    });
    return Object.keys(files).length;
  }

  function focusCorrectionPlan() {
    var plan = root.querySelector('.lse-owner-detail-result');
    if (!plan) return;

    try {
      plan.focus({ preventScroll: true });
    } catch (error) {
      plan.focus();
    }
    plan.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function renderFullScan(fullScan, ownerScan, focusOwnerPlan) {
    if (!asyncResults || !resultsPanel) return;
    lastFullScan = fullScan;
    var totals = fullScan && fullScan.totals ? fullScan.totals : {};
    var owners = fullScan && fullScan.owner_results ? fullScan.owner_results : {};
    var ownerKeys = Object.keys(owners);
    var html = '';

    html += '<div class="lse-async-section">';
    html += '<div class="lse-async-heading">';
    html += '<h4>' + escapeHtml(text('full_scan_results_title', 'Full Scan Results')) + '</h4>';
    html += '<span class="lse-badge is-safe">' + escapeHtml(text('scan_complete', 'Scan complete')) + '</span>';
    html += '</div>';
    html += '<div class="lse-summary-grid">';
    html += summaryCard(text('full_owners_label', 'Owners scanned'), totals.owners);
    html += summaryCard(text('ops_ready_missing_total', 'Ready Missing-Key Corrections'), totals.ready_missing_key_corrections || totals.ready_auto_fix_keys, 'lse-cat-already_localized');
    html += summaryCard(text('ops_ready_inline_total', 'Ready Inline Migrations'), totals.ready_inline_migrations, 'lse-cat-human_facing');
    html += summaryCard(text('needs_review', 'Needs Review'), totals.needs_review_total != null ? totals.needs_review_total : totals.needs_review_keys, 'lse-cat-ambiguous_string');
    html += summaryCard(text('migration_rejected_label', 'Rejected'), totals.rejected_total != null ? totals.rejected_total : totals.rejected_unsafe_keys, 'lse-cat-missing_owner_key');
    html += '</div>';

    if (numberValue(totals.needs_review_total != null ? totals.needs_review_total : totals.needs_review_keys) > 0) {
      var systemPriorityCounts = reviewPriorityCounts(totals.review_priority_counts || {});
      html += '<details class="lse-key-quality-summary lse-review-intelligence lse-system-review-intelligence lse-section-fold">';
      html += '<summary class="lse-section-summary"><span class="lse-section-title">' + escapeHtml(text('review_intelligence_title', 'Review Intelligence')) + '</span>';
      html += '<span class="lse-review-summary-chip">' + escapeHtml(text('review_priority_likely_migratable', 'Likely Migratable')) + ': ' + systemPriorityCounts.likely_migratable + '</span>';
      html += '<span class="lse-review-summary-chip">' + escapeHtml(text('review_priority_manual_review', 'Manual Review')) + ': ' + systemPriorityCounts.manual_review + '</span>';
      html += '<span class="lse-review-summary-chip">' + escapeHtml(text('review_priority_blocked', 'Blocked')) + ': ' + systemPriorityCounts.blocked + '</span></summary>';
      html += '<div class="lse-relevance-bar">';
      html += '<span class="lse-relevance-item lse-priority-likely_migratable">' + escapeHtml(text('review_priority_likely_migratable', 'Likely Migratable')) + ': ' + systemPriorityCounts.likely_migratable + '</span>';
      html += '<span class="lse-relevance-item lse-priority-manual_review">' + escapeHtml(text('review_priority_manual_review', 'Manual Review')) + ': ' + systemPriorityCounts.manual_review + '</span>';
      html += '<span class="lse-relevance-item lse-priority-blocked">' + escapeHtml(text('review_priority_blocked', 'Blocked')) + ': ' + systemPriorityCounts.blocked + '</span>';
      html += '</div>';
      html += renderReviewBreakdown(totals.review_reason_counts || {});
      html += '</details>';
    }

    if (ownerKeys.length > 0) {
      html += renderCampaignBuilder(owners, ownerKeys);

      html += '<details class="lse-ops-dashboard-fold lse-section-fold" data-owner-filter="all">';
      html += '<summary class="lse-async-heading lse-ops-dashboard-heading lse-section-summary">';
      html += '<span class="lse-section-title">' + escapeHtml(text('ops_dashboard_title', 'Operations Dashboard')) + '</span>';
      html += '<span class="lse-badge is-info">' + numberValue(totals.owners) + ' ' + escapeHtml(text('full_owners_label', 'Owners scanned')) + '</span>';
      html += '<span class="lse-badge is-safe">' + numberValue(totals.ready_missing_key_corrections || totals.ready_auto_fix_keys) + ' ' + escapeHtml(text('ops_ready_missing_total', 'Ready Missing-Key Corrections')) + '</span>';
      html += '<span class="lse-badge is-safe">' + numberValue(totals.ready_inline_migrations) + ' ' + escapeHtml(text('ops_ready_inline_total', 'Ready Inline Migrations')) + '</span>';
      html += '<span class="lse-badge is-warn">' + numberValue(totals.needs_review_total != null ? totals.needs_review_total : totals.needs_review_keys) + ' ' + escapeHtml(text('needs_review', 'Needs Review')) + '</span>';
      html += '</summary>';
      html += '<div class="lse-owner-filter-bar">';
      html += '<div class="lse-filter-bar">';
      [['all', 'campaign_filter_all', 'All'], ['ready_fixes', 'campaign_filter_ready_fixes', 'Ready Fixes'], ['ready_migrations', 'campaign_filter_ready_migrations', 'Ready Migrations'], ['needs_review', 'campaign_filter_needs_review', 'Needs Review'], ['rejected', 'campaign_filter_rejected', 'Rejected']].forEach(function (item) {
        html += '<button class="lse-filter-btn' + (item[0] === 'all' ? ' is-active' : '') + '" type="button" data-owner-filter-btn="' + item[0] + '">' + escapeHtml(text(item[1], item[2])) + '</button>';
      });
      html += '</div>';
      html += '<label class="lse-owner-search"><span>' + escapeHtml(text('campaign_owner_search_label', 'Owner Search')) + '</span><input type="search" class="lse-input" data-owner-search placeholder="' + escapeHtml(text('campaign_owner_search_placeholder', 'Search owners')) + '"></label>';
      html += '</div>';
      html += '<div class="lse-table-wrap lse-owner-results-wrap lse-correction-results-wrap">';
      html += '<table class="lse-report-table lse-owner-results-table lse-correction-results-table">';
      html += '<thead><tr>';
      html += '<th>' + escapeHtml(text('owner_label', 'Owner')) + '</th>';
      html += '<th>' + escapeHtml(text('ops_missing_ready_col', 'Missing-Key Corrections Ready')) + '</th>';
      html += '<th>' + escapeHtml(text('ops_inline_ready_col', 'Inline Migrations Ready')) + '</th>';
      html += '<th>' + escapeHtml(text('needs_review', 'Needs Review')) + '</th>';
      html += '<th>' + escapeHtml(text('migration_rejected_label', 'Rejected')) + '</th>';
      html += '<th>' + escapeHtml(text('status_label', 'Status')) + '</th>';
      html += '<th>' + escapeHtml(text('col_action', 'Action')) + '</th>';
      html += '</tr></thead><tbody>';
      ownerKeys.forEach(function (ownerKey) {
        var row = owners[ownerKey] || {};
        var status = correctionStatus(row);
        var action = operationAction(row);
        var filterAttrs = ' data-owner-row="1" data-owner-search-text="' + escapeHtml(ownerKey.toLowerCase()) + '"' +
          ' data-ready-fixes="' + numberValue(row.ready_missing_key_corrections || row.ready_auto_fix_keys) + '"' +
          ' data-ready-migrations="' + numberValue(row.inline_ready_to_migrate) + '"' +
          ' data-needs-review="' + numberValue(row.needs_review_total != null ? row.needs_review_total : row.needs_review_keys) + '"' +
          ' data-rejected="' + numberValue(row.rejected_total != null ? row.rejected_total : row.rejected_unsafe_keys) + '"';
        html += '<tr' + filterAttrs + '>';
        html += '<td><button class="lse-btn is-link lse-owner-result-link" type="button" data-owner="' + escapeHtml(ownerKey) + '">' + escapeHtml(ownerKey) + '</button></td>';
        html += '<td>' + numberValue(row.ready_missing_key_corrections || row.ready_auto_fix_keys) + '</td>';
        html += '<td>' + numberValue(row.inline_ready_to_migrate) + '</td>';
        html += '<td>' + numberValue(row.needs_review_total != null ? row.needs_review_total : row.needs_review_keys) + renderReviewBreakdown(row.review_reason_counts || {}) + '</td>';
        html += '<td>' + numberValue(row.rejected_total != null ? row.rejected_total : row.rejected_unsafe_keys) + '</td>';
        html += '<td><span class="lse-badge lse-badge-sm ' + status.cls + '">' + escapeHtml(status.label) + '</span></td>';
        html += '<td><button class="lse-btn is-secondary lse-btn-sm lse-owner-result-link" type="button" data-owner="' + escapeHtml(ownerKey) + '">' + escapeHtml(action) + '</button></td>';
        html += '</tr>';
      });
      html += '</tbody></table></div></details>';

      html += '<details class="lse-collapsible lse-governance-diagnostics lse-section-fold">';
      html += '<summary class="lse-section-summary"><span class="lse-section-title">' + escapeHtml(text('governance_title', 'Governance & Safety')) + ' · ' + escapeHtml(text('advanced_diagnostics_title', 'Advanced Diagnostics')) + '</span><span class="lse-badge is-info">' + ownerKeys.length + ' ' + escapeHtml(text('full_owners_label', 'Owners scanned')) + '</span></summary>';
      html += '<div class="lse-table-wrap lse-owner-results-wrap">';
      html += '<table class="lse-report-table lse-owner-diagnostics-table">';
      html += '<thead><tr>';
      html += '<th>' + escapeHtml(text('owner_label', 'Owner')) + '</th>';
      html += '<th>' + escapeHtml(text('status_label', 'Status')) + '</th>';
      html += '<th>' + escapeHtml(text('human_facing', 'Human-facing')) + '</th>';
      html += '<th>' + escapeHtml(text('already_localized', 'Already localized')) + '</th>';
      html += '<th>' + escapeHtml(text('missing_keys', 'Missing owner keys')) + '</th>';
      html += '<th>' + escapeHtml(text('shared_keys', 'Shared')) + '</th>';
      html += '<th>' + escapeHtml(text('unused_keys', 'Possibly unused')) + '</th>';
      html += '<th>' + escapeHtml(text('ambiguous_strings', 'Ambiguous')) + '</th>';
      html += '<th>' + escapeHtml(text('internal_strings', 'Internal')) + '</th>';
      html += '<th>' + escapeHtml(text('pending_keys_label', 'Pending')) + '</th>';
      html += '</tr></thead><tbody>';
      ownerKeys.forEach(function (ownerKey) {
        var row = owners[ownerKey] || {};
        html += '<tr>';
        html += '<td><button class="lse-btn is-link lse-owner-result-link" type="button" data-owner="' + escapeHtml(ownerKey) + '">' + escapeHtml(ownerKey) + '</button></td>';
        html += '<td><span class="lse-badge lse-badge-sm ' + (row.scan_ok ? 'is-safe' : 'is-error') + '">' + escapeHtml(row.scan_ok ? text('status_success', 'Success') : text('status_failed', 'Failed')) + '</span></td>';
        html += '<td>' + numberValue(row.human_facing_candidates || row.human_facing) + '</td>';
        html += '<td>' + numberValue(row.already_localized_usages) + '</td>';
        html += '<td>' + numberValue(row.missing_owner_keys) + '</td>';
        html += '<td>' + numberValue(row.external_shared_key_usages) + '</td>';
        html += '<td>' + numberValue(row.possibly_unused_keys) + '</td>';
        html += '<td>' + numberValue(row.ambiguous_strings) + '</td>';
        html += '<td>' + numberValue(row.internal_strings) + '</td>';
        html += '<td>' + numberValue(row.pending_missing_keys) + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table></div></details>';
    }
    html += '</div>';

    if (ownerScan) {
      html += renderOwnerScan(ownerScan);
    }

    asyncResults.innerHTML = html;
    resultsPanel.hidden = false;
    if (focusOwnerPlan && ownerScan) {
      focusCorrectionPlan();
    } else {
      resultsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    bindOwnerLinks();
    bindApplyButtons();
    bindCampaignBuilder();
    bindOwnerFilters();
  }

  function renderOwnerScan(ownerScan) {
    var summary = ownerScan && ownerScan.summary ? ownerScan.summary : {};
    var ownerKey = ownerScan && ownerScan.owner_key ? ownerScan.owner_key : '';
    var rows = keyValueRows(ownerScan);
    var safeRows = rows.filter(function (row) { return row.safe; });
    var reviewRows = rows.filter(function (row) { return !row.safe; });
    var rejectedRows = reviewRows.filter(function (row) { return row.reason !== ''; });
    var v2b = computeV2Breakdown(ownerScan ? ownerScan.findings : []);
    var pendingCount = rows.length;
    var migrationPlan = ownerScan && ownerScan.migration_plan ? ownerScan.migration_plan : null;
    var migrationPlanCounts = migrationCounts(migrationPlan);
    var filesAffected = {};
    rows.forEach(function (row) {
      if (row.file) filesAffected[row.file] = true;
    });
    migrationCandidates(migrationPlan, 'ready_to_migrate').forEach(function (row) {
      if (row.file) filesAffected[row.file] = true;
      (row.occurrence_files || []).forEach(function (file) {
        if (file) filesAffected[file] = true;
      });
    });
    var html = '';
    lastOwnerScan = ownerScan;

    html += '<div class="lse-async-section lse-owner-detail-result" tabindex="-1">';
    html += '<section class="lse-owner-workbench" id="lse-owner-workbench">';
    html += '<div class="lse-async-heading">';
    html += '<h4>' + escapeHtml(text('owner_workbench_title', 'Owner Workbench')) + ': <code>' + escapeHtml(ownerKey) + '</code></h4>';
    html += '<span class="lse-badge is-info">' + escapeHtml(text('owner_result_title', 'Owner result')) + '</span>';
    html += '</div>';
    html += '<p class="lse-panel-desc">' + escapeHtml(text('owner_workbench_desc', 'Owner-level summary, actions, and safety links for correction work.')) + '</p>';
    html += '<div class="lse-summary-grid">';
    html += summaryCard(text('owner_workbench_owner_summary', 'Owner Summary'), v2b.inlineFindings + rows.length, 'lse-cat-human_facing');
    html += summaryCard(text('owner_workbench_ready_missing', 'Ready Missing-Key Corrections'), safeRows.length, 'lse-cat-already_localized');
    html += summaryCard(text('owner_workbench_ready_inline', 'Ready Inline Migrations'), migrationPlanCounts.ready, 'lse-cat-human_facing');
    html += summaryCard(text('needs_review', 'Needs Review'), reviewRows.length + migrationPlanCounts.review, 'lse-cat-ambiguous_string');
    html += summaryCard(text('migration_rejected_label', 'Rejected'), rejectedRows.length + migrationPlanCounts.rejected, 'lse-cat-missing_owner_key');
    html += summaryCard(text('owner_workbench_files_affected', 'Files Affected'), Object.keys(filesAffected).length);
    html += '</div>';
    html += '<div class="lse-owner-workbench-actions">';
    if (safeRows.length > 0) {
      html += '<button class="lse-btn lse-apply-safe-corrections" type="button" data-owner="' + escapeHtml(ownerKey) + '">' + escapeHtml(text('apply_safe_corrections', 'Apply Safe Corrections')) + '</button>';
    }
    if (migrationPlanCounts.ready > 0) {
      html += '<a class="lse-btn is-secondary" href="#lse-inline-migration-preview">' + escapeHtml(text('migration_apply_button', 'Apply Ready Inline Migrations')) + '</a>';
    }
    html += '<a class="lse-btn is-secondary" href="#lse-governance">' + escapeHtml(text('owner_workbench_rollback_link', 'Rollback')) + '</a>';
    html += '<a class="lse-btn is-secondary" href="#lse-history">' + escapeHtml(text('owner_workbench_history_link', 'History')) + '</a>';
    html += '</div>';
    html += '</section>';

    html += '<div class="lse-intelligence">';
    html += '<div class="lse-intelligence-heading"><h4>' + escapeHtml(text('inline_breakdown_title', 'Inline Finding Breakdown')) + '</h4></div>';
    html += '<p class="lse-intelligence-desc">' + escapeHtml(text('inline_breakdown_desc', 'Classification of inline findings by semantic category. Sum equals Inline Findings total.')) + '</p>';
    html += renderSemBreakdown(v2b.semBreakdown);
    html += '<div class="lse-intelligence-relevance">';
    html += '<div class="lse-intelligence-relevance-title">' + escapeHtml(text('relevance_distribution', 'Relevance Distribution')) + '</div>';
    html += '<div class="lse-relevance-bar">';
    html += '<span class="lse-relevance-item lse-rel-critical">' + escapeHtml(text('relevance_critical', 'Critical')) + ': ' + numberValue(v2b.relDist.critical) + '</span>';
    html += '<span class="lse-relevance-item lse-rel-high">' + escapeHtml(text('relevance_high', 'High')) + ': ' + numberValue(v2b.relDist.high) + '</span>';
    html += '<span class="lse-relevance-item lse-rel-medium">' + escapeHtml(text('relevance_medium', 'Medium')) + ': ' + numberValue(v2b.relDist.medium) + '</span>';
    html += '<span class="lse-relevance-item lse-rel-low">' + escapeHtml(text('relevance_low', 'Low')) + ': ' + numberValue(v2b.relDist.low) + '</span>';
    html += '</div></div></div>';

    html += renderMigrationPlan(ownerScan);

    html += '<div class="lse-correction-plan-actions">';
    if (safeRows.length === 0 && rows.length === 0) {
      if (v2b.inlineFindings > 0) {
        html += '<span class="lse-badge is-info">' + escapeHtml(text('no_safe_corrections', 'No missing-key corrections available')) + ' ' + v2b.inlineFindings + ' ' + escapeHtml(text('inline_findings_available', 'inline findings are available for review.')) + '</span>';
      } else {
        html += '<span class="lse-badge is-warn">' + escapeHtml(text('corrections_empty_none', 'No corrections available')) + '</span>';
      }
    } else if (safeRows.length === 0) {
      html += '<span class="lse-badge is-warn">' + escapeHtml(text('no_safe_corrections', 'No safe corrections available')) + '</span>';
    }
    html += '<span class="lse-readonly-note">' + escapeHtml(text('safe_corrections_note', 'Ready rows include auto-safe, high-confidence, and medium-confidence suggestions that pass rejection checks. Low-confidence and unsafe suggestions stay in review.')) + '</span>';
    html += '</div>';
    if (safeRows.length > 0) {
      var readyBreakdown = confidenceBreakdown(safeRows);
      html += '<div class="lse-confidence-breakdown">';
      html += '<span class="lse-confidence-breakdown-label">' + escapeHtml(text('ready_confidence_breakdown', 'Ready confidence')) + '</span>';
      html += '<span class="lse-badge lse-badge-sm is-safe">' + escapeHtml(text('confidence_auto_safe', 'Auto-safe')) + ': ' + readyBreakdown.auto_safe + '</span>';
      html += '<span class="lse-badge lse-badge-sm is-safe">' + escapeHtml(text('confidence_high', 'High')) + ': ' + readyBreakdown.high + '</span>';
      html += '<span class="lse-badge lse-badge-sm is-warn">' + escapeHtml(text('confidence_medium', 'Medium')) + ': ' + readyBreakdown.medium + '</span>';
      html += '</div>';
    }
    html += '<div id="lse-correction-report-slot"></div>';

    if (safeRows.length > 0) {
      html += '<details class="lse-collapsible" id="lse-change-preview">';
      html += '<summary class="lse-collapsible-summary">';
      html += '<h4>' + escapeHtml(text('change_preview_title', 'Change Preview')) + '</h4>';
      html += '<span class="lse-badge is-safe">' + safeRows.length + ' ' + escapeHtml(text('safe_auto_fixes', 'Ready to Auto-Fix')) + '</span>';
      html += '</summary>';
      html += '<div class="lse-table-wrap lse-change-preview-scroll">';
      html += '<table class="lse-report-table lse-change-preview-table"><thead><tr>';
      html += '<th>' + escapeHtml(text('review_plan_key', 'Key')) + '</th>';
      html += '<th>' + escapeHtml(text('value_label', 'Value')) + '</th>';
      html += '<th>' + escapeHtml(text('evidence_type_label', 'Evidence Type')) + '</th>';
      html += '<th>' + escapeHtml(text('evidence_source_label', 'Evidence Source')) + '</th>';
      html += '<th>' + escapeHtml(text('reason_label', 'Reason')) + '</th>';
      html += '<th>' + escapeHtml(text('review_plan_file', 'First file')) + '</th>';
      html += '</tr></thead><tbody>';
      safeRows.slice(0, 150).forEach(function (row) {
        html += '<tr>';
        html += '<td><code>' + escapeHtml(row.key) + '</code></td>';
        html += '<td>' + escapeHtml(row.value) + '</td>';
        html += '<td><span class="lse-confidence lse-conf-' + escapeHtml(row.confidence) + '">' + escapeHtml(row.evidenceType || confidenceLabel(row.confidence)) + '</span></td>';
        html += '<td>' + escapeHtml(row.evidenceSource || '-') + '</td>';
        html += '<td>' + escapeHtml(row.suggestionReason || '-') + '</td>';
        html += '<td class="lse-cell-file">' + escapeHtml(row.file) + (numberValue(row.line) > 0 ? ':' + numberValue(row.line) : '') + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
      if (safeRows.length > 150) {
        html += '<p class="lse-readonly-note">' + escapeHtml(text('owner_findings_limited', 'Showing first 150 findings.')) + '</p>';
      }
      html += '</div>';
      html += '</details>';
    }

    if (reviewRows.length > 0) {
      html += '<details class="lse-collapsible lse-needs-review-block">';
      html += '<summary><h4>' + escapeHtml(text('needs_review', 'Needs Review')) + ' (' + reviewRows.length + ')</h4></summary>';
      html += '<div class="lse-table-wrap lse-needs-review-scroll"><table class="lse-report-table lse-needs-review-table"><thead><tr>';
      html += '<th>' + escapeHtml(text('review_plan_key', 'Key')) + '</th>';
      html += '<th>' + escapeHtml(text('value_label', 'Value')) + '</th>';
      html += '<th>' + escapeHtml(text('evidence_type_label', 'Evidence Type')) + '</th>';
      html += '<th>' + escapeHtml(text('evidence_source_label', 'Evidence Source')) + '</th>';
      html += '<th>' + escapeHtml(text('not_ready_reason', 'Why not ready')) + '</th>';
      html += '<th>' + escapeHtml(text('reason_label', 'Reason')) + '</th>';
      html += '<th>' + escapeHtml(text('review_plan_file', 'First file')) + '</th>';
      html += '</tr></thead><tbody>';
      reviewRows.slice(0, 80).forEach(function (row) {
        html += '<tr>';
        html += '<td><code>' + escapeHtml(row.key) + '</code></td>';
        html += '<td>' + escapeHtml(row.value || text('review_plan_no_suggestion', 'No safe suggestion')) + '</td>';
        html += '<td><span class="lse-confidence lse-conf-' + escapeHtml(row.confidence) + '">' + escapeHtml(row.evidenceType || confidenceLabel(row.confidence)) + '</span></td>';
        html += '<td>' + escapeHtml(row.evidenceSource || '-') + '</td>';
        html += '<td>' + escapeHtml(notReadyReasonLabel(row.reason, row.confidence)) + '</td>';
        html += '<td>' + escapeHtml(row.suggestionReason || '-') + '</td>';
        html += '<td class="lse-cell-file">' + escapeHtml(row.file) + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table></div></details>';
    }
    html += '</div>';
    return html;
  }

  function bindOwnerLinks() {
    var ownerLinks = root.querySelectorAll('.lse-owner-result-link');
    ownerLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        var owner = link.getAttribute('data-owner') || '';
        if (!owner || !scanForm) return;
        var fd = new FormData(scanForm);
        fd.delete('owners[]');
        fd.delete('owners');
        fd.append('owners[]', owner);
        runAsyncScan(fd, true);
      });
    });
  }

  function bindCampaignBuilder() {
    var builder = root.querySelector('[data-campaign-builder]');
    if (!builder || !lastFullScan || !lastFullScan.owner_results) return;
    var owners = lastFullScan.owner_results;
    var summarySlot = builder.querySelector('[data-campaign-summary]');
    var refresh = function () {
      if (summarySlot) {
        summarySlot.innerHTML = renderCampaignSummaryHtml(owners);
        bindOwnerLinks();
      }
      var included = builder.querySelector('.lse-intelligence-relevance-title');
      if (included) {
        included.textContent = text('campaign_owners_included', 'Owners Included') + ' (' + campaignSelectedKeys(owners).length + ')';
      }
      var summaryCount = builder.querySelector('[data-campaign-selected-count]');
      if (summaryCount) {
        summaryCount.textContent = campaignSelectedKeys(owners).length + ' ' + text('campaign_owners_included', 'Owners Included');
      }
    };

    var nameInput = builder.querySelector('[data-campaign-name]');
    if (nameInput) {
      nameInput.addEventListener('input', function () {
        campaignName = nameInput.value;
      });
    }

    var descInput = builder.querySelector('[data-campaign-description]');
    if (descInput) {
      descInput.addEventListener('input', function () {
        campaignDescription = descInput.value;
      });
    }

    var selectAll = builder.querySelector('[data-campaign-select-all]');
    if (selectAll) {
      selectAll.addEventListener('click', function () {
        campaignEligibleOwners(owners, Object.keys(owners)).forEach(function (ownerKey) {
          selectedCampaignOwners[ownerKey] = true;
        });
        builder.querySelectorAll('[data-campaign-owner]').forEach(function (input) {
          input.checked = true;
        });
        refresh();
      });
    }

    var selectNone = builder.querySelector('[data-campaign-select-none]');
    if (selectNone) {
      selectNone.addEventListener('click', function () {
        selectedCampaignOwners = {};
        builder.querySelectorAll('[data-campaign-owner]').forEach(function (input) {
          input.checked = false;
        });
        refresh();
      });
    }

    builder.querySelectorAll('[data-campaign-owner]').forEach(function (input) {
      input.addEventListener('change', function () {
        var ownerKey = input.getAttribute('data-campaign-owner') || '';
        if (!ownerKey) return;
        selectedCampaignOwners[ownerKey] = input.checked;
        if (!input.checked) {
          delete selectedCampaignOwners[ownerKey];
        }
        refresh();
      });
    });
  }

  function bindOwnerFilters() {
    var dashboard = root.querySelector('.lse-ops-dashboard-fold');
    if (!dashboard) return;
    var buttons = dashboard.querySelectorAll('[data-owner-filter-btn]');
    var search = dashboard.querySelector('[data-owner-search]');
    var applyFilter = function () {
      var filter = dashboard.getAttribute('data-owner-filter') || 'all';
      var query = search ? search.value.trim().toLowerCase() : '';
      dashboard.querySelectorAll('[data-owner-row]').forEach(function (row) {
        var visible = true;
        if (filter === 'ready_fixes') visible = numberValue(row.getAttribute('data-ready-fixes')) > 0;
        if (filter === 'ready_migrations') visible = numberValue(row.getAttribute('data-ready-migrations')) > 0;
        if (filter === 'needs_review') visible = numberValue(row.getAttribute('data-needs-review')) > 0;
        if (filter === 'rejected') visible = numberValue(row.getAttribute('data-rejected')) > 0;
        if (query && !(row.getAttribute('data-owner-search-text') || '').includes(query)) {
          visible = false;
        }
        row.hidden = !visible;
      });
    };

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        buttons.forEach(function (btn) { btn.classList.remove('is-active'); });
        button.classList.add('is-active');
        dashboard.setAttribute('data-owner-filter', button.getAttribute('data-owner-filter-btn') || 'all');
        applyFilter();
      });
    });

    if (search) {
      search.addEventListener('input', applyFilter);
    }
  }

  function renderCorrectionReport(report, beforePending, afterScan) {
    var afterPlan = afterScan && afterScan.missing_key_review_plan ? afterScan.missing_key_review_plan : {};
    var afterRows = Array.isArray(afterPlan.rows) ? afterPlan.rows : [];
    var diags = report && report.re_scan_diagnostics ? report.re_scan_diagnostics : {};
    var reportBefore = diags.before_pending_missing_keys;
    var reportAfter = diags.after_pending_missing_keys != null ? diags.after_pending_missing_keys : diags.pending_missing_keys;
    var beforeCount = reportBefore != null ? numberValue(reportBefore) : numberValue(beforePending);
    var afterPending = reportAfter != null ? numberValue(reportAfter) : afterRows.length;
    var ok = !!(report && report.success);
    var reductionConfirmed = !(report && report.re_scan_confirmed === false);
    var validationOk = ok && reductionConfirmed;
    var statusClass = validationOk ? 'is-safe' : 'is-error';
    var html = '<div class="lse-correction-report ' + statusClass + '">';
    html += '<div class="lse-async-heading"><h4>' + escapeHtml(text('correction_report_title', 'Correction Report')) + '</h4>';
    html += '<span class="lse-badge ' + statusClass + '">' + escapeHtml(validationOk ? text('validation_passed', 'Validation passed') : (ok ? text('validation_needs_review', 'Validation needs review') : text('status_failed', 'Failed'))) + '</span></div>';
    html += '<div class="lse-summary-grid">';
    html += summaryCard(text('keys_added_label', 'Keys added'), report ? report.added_count : 0, 'lse-cat-already_localized');
    html += summaryCard(text('failures_label', 'Failures'), ok ? 0 : 1, ok ? 'lse-cat-already_localized' : 'lse-cat-missing_owner_key');
    html += summaryCard(text('pending_keys_label', 'Pending missing keys'), afterPending, 'lse-cat-missing_owner_key');
    html += '</div>';
    html += '<p class="lse-readonly-note">' + escapeHtml(text('pending_change_label', 'Pending Missing Keys')) + ': ' + beforeCount + ' → ' + afterPending + '</p>';
    if (report && report.rollback_status) {
      html += '<p class="lse-readonly-note">' + escapeHtml(text('rollback_status_label', 'Rollback')) + ': ' + escapeHtml(report.rollback_status) + '</p>';
    }
    if (report && report.error) {
      html += '<p class="lse-report-error">' + escapeHtml(report.error) + '</p>';
    }
    html += '</div>';
    return html;
  }

  function bindApplyButtons() {
    var buttons = root.querySelectorAll('.lse-apply-safe-corrections');
    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        if (!lastOwnerScan || !scanForm) return;
        var rows = keyValueRows(lastOwnerScan).filter(function (row) { return row.safe; });
        if (rows.length === 0) return;
        if (!window.confirm(text('apply_safe_confirm', 'Apply safe corrections now?'))) return;

        var fd = new FormData(scanForm);
        fd.delete('owners[]');
        fd.delete('owners');
        var ownerKey = lastOwnerScan.owner_key || button.getAttribute('data-owner') || '';
        fd.set('owner', ownerKey);
        rows.forEach(function (row) {
          fd.append('selected_keys[]', row.key);
        });

        var ownerButtons = root.querySelectorAll('.lse-apply-safe-corrections[data-owner="' + cssEscape(ownerKey) + '"]');
        ownerButtons.forEach(function (btn) {
          btn.disabled = true;
          btn.textContent = text('applying_safe_corrections', 'Applying...');
        });
        fetch('/apps/studio/tools/localization-scan-extraction/apply-safe-corrections', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        }).then(function (response) {
          return response.json().then(function (json) {
            if (!response.ok || !json.ok) {
              throw new Error(json.error || 'Correction failed');
            }
            return json;
          });
        }).then(function (json) {
          var beforePending = rows.length;
          var reportHtml = renderCorrectionReport(json.report || {}, beforePending, json.owner_scan || null);
          if (json.owner_scan) {
            lastOwnerScan = json.owner_scan;
            var ownerDetail = root.querySelector('.lse-owner-detail-result');
            if (ownerDetail) {
              ownerDetail.outerHTML = renderOwnerScan(json.owner_scan);
              bindApplyButtons();
            }
          }
          var reportSlot = document.getElementById('lse-correction-report-slot');
          if (reportSlot) reportSlot.innerHTML = reportHtml;
          root.querySelectorAll('.lse-apply-safe-corrections[data-owner="' + cssEscape(ownerKey) + '"]').forEach(function (btn) {
            btn.disabled = true;
            btn.textContent = text('applied_safe_corrections', 'Applied');
          });
        }).catch(function (err) {
          var reportSlot = document.getElementById('lse-correction-report-slot');
          if (reportSlot) {
            reportSlot.innerHTML = '<div class="lse-notice is-error"><span class="lse-notice-icon">&#9888;</span><span>' + escapeHtml(err.message || 'Correction failed') + '</span></div>';
          }
          ownerButtons.forEach(function (btn) {
            btn.disabled = false;
            btn.textContent = text('apply_safe_corrections', 'Apply Safe Corrections');
          });
        });
      });
    });
  }

  function runAsyncScan(formData, keepFullResults) {
    if (!scanForm || !asyncResults || !resultsPanel) return;
    if (activeFetch && activeFetch.abort) activeFetch.abort();
    activeFetch = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var action = scanForm.getAttribute('data-async-url') || scanForm.getAttribute('action');
    var progressValue = 3;
    var timer = null;

    if (runBtn) runBtn.disabled = true;
    if (progressWrap) progressWrap.hidden = false;
    setProgress(progressValue, text('scan_progress_starting', 'Starting scan'));
    if (!keepFullResults) {
      asyncResults.innerHTML = '';
      resultsPanel.hidden = true;
    }

    timer = window.setInterval(function () {
      progressValue = Math.min(90, progressValue + (progressValue < 45 ? 9 : 4));
      setProgress(progressValue, text('scan_progress_running', 'Scanning owners'));
    }, 240);

    fetch(action, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      signal: activeFetch ? activeFetch.signal : undefined
    }).then(function (response) {
      return response.json().then(function (json) {
        if (!response.ok || !json.ok) {
          throw new Error(json.error || 'Scan failed');
        }
        return json;
      });
    }).then(function (json) {
      window.clearInterval(timer);
      setProgress(100, text('scan_progress_complete', 'Scan complete'));
      window.setTimeout(function () {
        if (progressWrap) progressWrap.hidden = true;
        if (keepFullResults && lastFullScan) {
          renderFullScan(lastFullScan, json.owner_scan || null, true);
        } else {
          renderFullScan(json.full_scan || {}, json.owner_scan || null, false);
        }
      }, 250);
    }).catch(function (err) {
      window.clearInterval(timer);
      setProgress(100, text('scan_progress_failed', 'Scan failed'));
      if (progressWrap) progressWrap.hidden = true;
      resultsPanel.hidden = false;
      asyncResults.innerHTML = '<div class="lse-notice is-error"><span class="lse-notice-icon">&#9888;</span><span>' + escapeHtml(err.message || 'Scan failed') + '</span></div>';
    }).finally(function () {
      if (runBtn) runBtn.disabled = false;
      activeFetch = null;
    });
  }

  if (scanForm) {
    scanForm.addEventListener('submit', function (event) {
      event.preventDefault();
      runAsyncScan(new FormData(scanForm), false);
    });
  }

  if (runBtn && ownerSelect) {
    function updateRunBtn() {
      runBtn.disabled = ownerSelect.value === '' || ownerSelect.disabled;
    }
    ownerSelect.addEventListener('change', updateRunBtn);
  }

  var filterBtns = root.querySelectorAll('.lse-filter-btn');
  var findingRows = root.querySelectorAll('.lse-finding-row');
  var detailPlaceholder = document.getElementById('lse-detail-placeholder');
  var detailContent = document.getElementById('lse-detail-content');
  var detailContext = document.getElementById('lse-detail-context');
  var detailCatBadge = document.getElementById('lse-detail-cat-badge');
  var detailCatReason = document.getElementById('lse-detail-cat-reason');
  var detailHandoff = document.getElementById('lse-detail-handoff');
  var detailHandoffLink = document.getElementById('lse-detail-handoff-link');
  var detailNextStepText = document.getElementById('lse-detail-next-step-text');
  var detailReviewMeta = document.getElementById('lse-detail-review-meta');
  var detailReviewGroup = document.getElementById('lse-detail-review-group');
  var detailUsageCount = document.getElementById('lse-detail-usage-count');
  var detailSuggestedEnglish = document.getElementById('lse-detail-suggested-english');
  var copyReviewPlanBtn = document.getElementById('lse-copy-review-plan');
  var reviewPlanData = document.getElementById('lse-review-plan-data');
  var detailExtractableBadge = document.getElementById('lse-detail-extractable-badge');
  var detailExtractAction = document.getElementById('lse-detail-extract-action');
  var extractFindingIndex = document.getElementById('lse-extract-finding-index');

  function applyCategoryFilter(category) {
    activeCategory = category;
    filterBtns.forEach(function (b) { b.classList.remove('is-active'); });
    var activeBtn = root.querySelector('.lse-filter-btn[data-category="' + category + '"]');
    if (activeBtn) activeBtn.classList.add('is-active');

    findingRows.forEach(function (row) {
      if (category === 'all') {
        row.style.display = '';
      } else {
        var rowCat = row.getAttribute('data-category') || '';
        row.style.display = rowCat === category ? '' : 'none';
      }
    });
  }

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      applyCategoryFilter(btn.getAttribute('data-category'));
    });
  });

  function showFindingDetail(index) {
    if (!detailContent || !detailPlaceholder || !detailContext) return;

    findingRows.forEach(function (r) { r.classList.remove('is-selected'); });

    var row = root.querySelector('.lse-finding-row[data-finding-index="' + index + '"]');
    if (row) row.classList.add('is-selected');

    detailPlaceholder.hidden = true;
    detailPlaceholder.style.display = 'none';
    detailContent.hidden = false;
    detailContent.style.display = '';

    if (findingsData[index]) {
      var f = findingsData[index];
      var cat = f.category || 'unknown';
      var parts = [];
      parts.push('// ' + (cat || f.type) + ' | confidence: ' + f.confidence + ' | status: ' + f.status);
      if (f.file && f.file !== '(cross-reference)' && f.file !== '(locale file)') {
        parts.push('// File: ' + f.file + (f.line > 0 ? ':' + f.line : ''));
      }
      if (f.detected) parts.push(f.detected);
      if (f.context && f.context !== f.detected) parts.push('\n// Context:\n' + f.context);
      detailContext.textContent = parts.join('\n');

      if (detailCatBadge) {
        detailCatBadge.className = 'lse-finding-type lse-cat-' + cat;
        detailCatBadge.textContent = cat.replace(/_/g, ' ');
      }
      if (detailCatReason) {
        detailCatReason.textContent = f.reason || '';
      }
      if (detailNextStepText) {
        detailNextStepText.textContent = detailCopy[cat] || detailCopy.default || '';
      }
      if (detailReviewMeta) {
        if (f.category === 'missing_owner_key') {
          detailReviewMeta.hidden = false;
          detailReviewMeta.style.display = '';
          if (detailReviewGroup) detailReviewGroup.textContent = f.review_group || '';
          if (detailUsageCount) detailUsageCount.textContent = String(f.usage_count || 1);
          if (detailSuggestedEnglish) detailSuggestedEnglish.textContent = f.suggested_english_value || 'No safe suggestion';
        } else {
          detailReviewMeta.hidden = true;
          detailReviewMeta.style.display = 'none';
          if (detailReviewGroup) detailReviewGroup.textContent = '';
          if (detailUsageCount) detailUsageCount.textContent = '';
          if (detailSuggestedEnglish) detailSuggestedEnglish.textContent = '';
        }
      }
      if (detailHandoff && detailHandoffLink) {
        if (f.category === 'missing_owner_key' && f.handoff_url) {
          detailHandoff.hidden = false;
          detailHandoff.style.display = '';
          detailHandoffLink.setAttribute('href', f.handoff_url);
        } else {
          detailHandoff.hidden = true;
          detailHandoff.style.display = 'none';
          detailHandoffLink.setAttribute('href', '#');
        }
      }
      if (detailExtractableBadge && detailExtractAction && extractFindingIndex) {
        if (f.extractable && f.type === 'inline_text') {
          detailExtractableBadge.innerHTML = '<span class="lse-badge is-safe">' + (detailCopy.badge_actionable || 'Available') + '</span>';
          detailExtractAction.hidden = false;
          detailExtractAction.style.display = '';
          extractFindingIndex.value = String(index);
        } else {
          detailExtractableBadge.innerHTML = '<span class="lse-badge is-warn">' + (detailCopy.badge_not_actionable || 'No (classification only)') + '</span>';
          detailExtractAction.hidden = true;
          detailExtractAction.style.display = 'none';
          extractFindingIndex.value = '';
        }
      }
      var detailOccurrence = document.getElementById('lse-detail-occurrence');
      if (detailOccurrence) {
        if (f.type === 'inline_text' && f.relevance) {
          detailOccurrence.hidden = false;
          detailOccurrence.style.display = '';
          var relEl = document.getElementById('lse-detail-occurrence-relevance');
          if (relEl) relEl.textContent = (detailCopy['relevance_' + f.relevance] || f.relevance);
          var cntEl = document.getElementById('lse-detail-occurrence-count');
          if (cntEl) cntEl.textContent = String(f.occurrence_count || 1);
          var fileList = document.getElementById('lse-detail-occurrence-file-list');
          if (fileList) {
            fileList.innerHTML = '';
            var files = f.occurrence_files;
            if (Array.isArray(files) && files.length > 0) {
              files.forEach(function (fp) {
                var li = document.createElement('li');
                li.textContent = fp;
                fileList.appendChild(li);
              });
            } else {
              var li = document.createElement('li');
              li.textContent = f.file || '(current file)';
              fileList.appendChild(li);
            }
          }
        } else {
          detailOccurrence.hidden = true;
          detailOccurrence.style.display = 'none';
        }
      }
    } else {
      detailContext.textContent = '';
      if (detailNextStepText) detailNextStepText.textContent = '';
      if (detailReviewMeta) {
        detailReviewMeta.hidden = true;
        detailReviewMeta.style.display = 'none';
      }
      if (detailHandoff) {
        detailHandoff.hidden = true;
        detailHandoff.style.display = 'none';
      }
    }

    selectedIndex = index;
  }

  for (var i = 0; i < findingRows.length; i++) {
    (function (row) {
      row.addEventListener('click', function () {
        var idx = parseInt(row.getAttribute('data-finding-index'), 10);
        if (!isNaN(idx)) showFindingDetail(idx);
      });
    })(findingRows[i]);
  }

  var selectBtns = root.querySelectorAll('.lse-select-finding');
  for (var j = 0; j < selectBtns.length; j++) {
    (function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var idx = parseInt(btn.getAttribute('data-finding-index'), 10);
        if (!isNaN(idx)) showFindingDetail(idx);
      });
    })(selectBtns[j]);
  }

  if (copyReviewPlanBtn && reviewPlanData) {
    copyReviewPlanBtn.addEventListener('click', function () {
      var copyLabel = copyReviewPlanBtn.getAttribute('data-copy-label') || copyReviewPlanBtn.textContent;
      var copiedLabel = copyReviewPlanBtn.getAttribute('data-copied-label') || 'Copied';
      var failedLabel = copyReviewPlanBtn.getAttribute('data-failed-label') || 'Copy failed';
      var text = reviewPlanData.textContent || '';

      function updateLabel(label) {
        copyReviewPlanBtn.textContent = label;
        window.setTimeout(function () {
          copyReviewPlanBtn.textContent = copyLabel;
        }, 1600);
      }

      function copyViaTextarea() {
        var temp = document.createElement('textarea');
        temp.value = text;
        temp.setAttribute('readonly', 'readonly');
        temp.style.position = 'fixed';
        temp.style.left = '-9999px';
        document.body.appendChild(temp);
        temp.select();
        var copied = false;
        try {
          copied = document.execCommand('copy');
        } catch (_) {
          copied = false;
        }
        document.body.removeChild(temp);
        updateLabel(copied ? copiedLabel : failedLabel);
      }

      if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          updateLabel(copiedLabel);
        }).catch(copyViaTextarea);
      } else {
        copyViaTextarea();
      }
    });
  }

})();
