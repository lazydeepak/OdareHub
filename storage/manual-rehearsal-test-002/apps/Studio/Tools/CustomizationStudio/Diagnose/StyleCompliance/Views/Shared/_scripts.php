<script>
(function() {
    var resultsMount = document.getElementById('style-compliance-results');
    var scanForm = document.getElementById('scScopeForm');
    var scanBtn = document.querySelector('.sc-scan-btn');
    var csrfInput = document.getElementById('scCsrf');
    var workspaceInput = document.getElementById('scWorkspace');
    var scopeSelect = document.getElementById('scScopeSelect');
    var ownerWrap = document.getElementById('scOwnerWrap');
    var cockpit = document.querySelector('[data-sc-cockpit]');
    var readinessLabels = <?= json_encode([
        'checking' => $sc('repair_readiness_checking'),
        'ready_for_guarded_repair' => $sc('readiness_state_ready_for_guarded_repair'),
        'blocked' => $sc('readiness_state_blocked_status'),
        'stale' => $sc('readiness_state_stale'),
        'ambiguous' => $sc('readiness_state_ambiguous'),
        'unsupported' => $sc('readiness_state_unsupported'),
        'review_required' => $sc('readiness_state_review_required_status'),
        'failed' => $sc('repair_readiness_failed'),
        'checks' => $sc('repair_readiness_checks'),
    ], JSON_UNESCAPED_SLASHES) ?>;
    var executionCapability = <?= json_encode($executionCapability, JSON_UNESCAPED_SLASHES) ?>;
    var scanButtonDefaultLabel = scanBtn ? (scanBtn.querySelector('[data-sc-scan-label]') ? scanBtn.querySelector('[data-sc-scan-label]').textContent : scanBtn.textContent) : '';
    var activeScanController = null;
    var activeScanTimeout = null;
    var activeScanId = null;
    // Tracks whether a completed scan report is currently mounted in the DOM
    // so the lifecycle renderer can conditionally show/hide the prior-evidence marker.
    var hasPriorEvidence = cockpit ? cockpit.getAttribute('data-scan-status') === 'complete' : false;

    function syncOwnerControl(scopeValue) {
        var ownerSelect = scanForm ? scanForm.querySelector('[name="owner"]') : null;
        var show = scopeValue === 'owner';
        if (ownerWrap) {
            ownerWrap.classList.toggle('sc-owner-hidden', !show);
        }
        if (ownerSelect) {
            ownerSelect.disabled = !show;
            ownerSelect.required = show;
            if (show) {
                ownerSelect.removeAttribute('tabindex');
            } else {
                ownerSelect.setAttribute('tabindex', '-1');
            }
        }
    }

    if (scopeSelect) {
        syncOwnerControl(scopeSelect.value);
        scopeSelect.addEventListener('change', function() {
            syncOwnerControl(scopeSelect.value);
        });
    }

    var emptyMetric = '—';
    var cockpitLabels = <?= json_encode([
        'notScanned' => $sc('scan_status_not_scanned'),
        'scanning' => $sc('scan_status_scanning'),
        'complete' => $sc('scan_status_complete'),
        'failed' => $sc('scan_status_failed'),
        'priorScanning' => $sc('cockpit_prior_evidence_scanning'),
        'priorFailed' => $sc('cockpit_prior_evidence_failed'),
        'investigationStateNotScanned' => $sc('investigation_state_not_scanned'),
        'investigationStateScanning' => $sc('investigation_state_scanning'),
        'investigationStateFailed' => $sc('investigation_state_failed'),
        'investigationStateComplete' => $sc('investigation_state_complete'),
        'resultsUnavailableFailed' => $sc('results_unavailable_failed'),
        'preparing' => $sc('scan_status_preparing'),
        'failedTimeout' => $sc('scan_status_failed_timeout'),
        'failedServer' => $sc('scan_status_failed_server'),
        'failedSession' => $sc('scan_status_failed_session'),
        'failedReconnect' => $sc('scan_status_failed_reconnect'),
        'retryReady' => $sc('scan_status_retry_ready'),
        'buttonReady' => $sc('scan_button_ready'),
        'buttonPreparing' => $sc('scan_button_preparing'),
        'buttonScanning' => $sc('scan_button_scanning'),
        'buttonComplete' => $sc('scan_button_complete'),
        'buttonFailed' => $sc('scan_button_failed'),
        'scopeAllOwnersDesc' => $sc('scan_scope_desc_all_owners'),
        'scopeOwnerDesc' => $sc('scan_scope_desc_owner'),
        'scopeShellDesc' => $sc('scan_scope_desc_shell'),
        'scopeThemeDesc' => $sc('scan_scope_desc_theme'),
        'scanInitialSummary' => $sc('scan_console_initial_summary'),
        'scanPreparingSummary' => $sc('scan_console_preparing_summary'),
        'scanScanningSummary' => $sc('scan_console_scanning_summary'),
        'scanCompleteSummary' => $sc('scan_console_complete_summary'),
        'scanNextFixable' => $sc('scan_console_next_fixable'),
        'scanNextReview' => $sc('scan_console_next_review'),
        'scanNextRetry' => $sc('scan_console_next_retry'),
        'scanPriorAvailable' => $sc('scan_console_prior_available'),
        'missionNoScan' => $sc('scan_header_ready'),
        'missionReady' => $sc('scan_header_fixable'),
        'missionReview' => $sc('scan_header_review'),
        'actionReady' => 'Open Fixable Now',
        'actionInvestigate' => 'Open Detailed Investigation',
        'actionReadyLabel' => 'Fixable Now',
        'actionReviewLabel' => 'Review Only',
        'actionInvestigateLabel' => 'Investigation',
        'actionReadySub' => $sc($guardedExecutionEnabled ? 'verified_candidates_summary_body_guarded' : 'verified_candidates_summary_body_readonly'),
        'actionInvestigateSub' => $sc('action_center_no_repair_why'),
        'actionNoScanSub' => $sc('scan_console_initial_summary'),
    ], JSON_UNESCAPED_SLASHES) ?>;

    function cockpitEl(name) {
        return cockpit ? cockpit.querySelector('[data-sc-cockpit-' + name + ']') : null;
    }

    function setCockpitText(name, value) {
        if (!cockpit) return;
        var nodes = document.querySelectorAll('[data-sc-cockpit-' + name + ']');
        var text = value === null || typeof value === 'undefined' || value === '' ? emptyMetric : String(value);
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].textContent = text;
        }
    }

    function setCockpitHref(name, value) {
        if (!cockpit) return;
        var nodes = document.querySelectorAll('[data-sc-cockpit-' + name + ']');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].setAttribute('href', value);
        }
    }

    function setCockpitHidden(name, hidden) {
        if (!cockpit) return;
        var nodes = document.querySelectorAll('[data-sc-cockpit-' + name + ']');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].hidden = !!hidden;
        }
    }

    function setCockpitHeroReady(isReady) {
        var el = cockpitEl('action');
        if (!el) return;
        if (isReady) {
            el.classList.add('ready');
        } else {
            el.classList.remove('ready');
        }
    }

    function setScanConsoleText(name, value) {
        var nodes = document.querySelectorAll('[data-sc-scan-' + name + ']');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].textContent = value || '';
            if (name === 'next') {
                nodes[i].hidden = !value;
            }
        }
    }

    function scopeDescription(scope) {
        if (scope === 'owner') return cockpitLabels.scopeOwnerDesc;
        if (scope === 'shell') return cockpitLabels.scopeShellDesc;
        if (scope === 'theme') return cockpitLabels.scopeThemeDesc;
        return cockpitLabels.scopeAllOwnersDesc;
    }

    function classifyFailureLabel(errorMsg) {
        var msg = String(errorMsg || '');
        if (msg.indexOf('AbortError') !== -1 || msg.indexOf('timed out') !== -1 || msg.indexOf('timeout') !== -1) {
            return cockpitLabels.failedTimeout;
        }
        if (msg.indexOf('HTTP 401') !== -1 || msg.indexOf('HTTP 403') !== -1 || msg.indexOf('HTTP 419') !== -1 || msg.indexOf('CSRF') !== -1) {
            return cockpitLabels.failedSession;
        }
        if (msg.indexOf('Failed to fetch') !== -1 || msg.indexOf('network') !== -1 || msg.indexOf('Reconnect') !== -1) {
            return cockpitLabels.failedReconnect;
        }
        return cockpitLabels.failedServer;
    }

    function completeSummary(summary) {
        var s = summary || {};
        return cockpitLabels.scanCompleteSummary
            .replace('{files}', String(s.files_scanned ?? emptyMetric))
            .replace('{findings}', String(s.findings_total ?? emptyMetric))
            .replace('{fixable_now}', String(s.fixable_now ?? s.repair_ready ?? 0));
    }

    function updateScanConsole(status, options) {
        var opts = options || {};
        var label = opts.label || (status === 'complete' ? cockpitLabels.complete : (status === 'scanning' ? cockpitLabels.scanning : (status === 'failed' ? cockpitLabels.failed : cockpitLabels.notScanned)));
        setScanConsoleText('status', label);
        setCockpitText('scan-mode', label);
        if (status === 'complete') {
            setScanConsoleText('summary', completeSummary(opts.summary || {}));
            setScanConsoleText('next', (opts.summary && ((opts.summary.fixable_now || opts.summary.repair_ready || 0) > 0)) ? cockpitLabels.scanNextFixable : cockpitLabels.scanNextReview);
        } else if (status === 'failed') {
            setScanConsoleText('summary', opts.message || cockpitLabels.scanNextRetry);
            setScanConsoleText('next', opts.prior ? cockpitLabels.scanPriorAvailable : cockpitLabels.scanNextRetry);
        } else if (status === 'scanning') {
            setScanConsoleText('summary', opts.message || cockpitLabels.scanScanningSummary);
            setScanConsoleText('next', opts.prior ? cockpitLabels.scanPriorAvailable : '');
        } else if (status === 'preparing') {
            setScanConsoleText('summary', cockpitLabels.scanPreparingSummary);
            setScanConsoleText('next', opts.prior ? cockpitLabels.scanPriorAvailable : '');
        } else {
            setScanConsoleText('summary', cockpitLabels.scanInitialSummary);
            setScanConsoleText('next', '');
        }
    }

    function setAllCockpitMetricsToEmpty() {
        var metricAttrs = ['owners', 'files', 'evidence', 'total', 'ready', 'review', 'score', 'blocked', 'decisions'];
        for (var i = 0; i < metricAttrs.length; i++) {
            setCockpitText(metricAttrs[i], emptyMetric);
        }
    }

    function setPriorEvidenceMarker(mode) {
        if (!cockpit) return;
        var marker = document.querySelector('[data-sc-prior-evidence]');
        if (!marker) return;
        if (mode === 'scanning') {
            marker.textContent = cockpitLabels.priorScanning;
            marker.hidden = false;
        } else if (mode === 'failed') {
            marker.textContent = cockpitLabels.priorFailed;
            marker.hidden = false;
        } else {
            marker.textContent = '';
            marker.hidden = true;
        }
    }

    function setCockpitProgress(mode) {
        setCockpitText('orbit-label', mode === 'complete' ? cockpitLabels.buttonComplete : (mode === 'scanning' ? cockpitLabels.buttonScanning : (mode === 'failed' ? cockpitLabels.buttonFailed : cockpitLabels.buttonReady)));
    }

    function setScanButtonVisual(mode) {
        if (!scanBtn) return;
        var label = scanBtn.querySelector('[data-sc-scan-label]');
        if (mode === 'scanning') {
            scanBtn.classList.add('is-scanning');
            scanBtn.classList.remove('is-stopping');
            if (label) label.textContent = cockpitLabels.buttonScanning;
            if (!label) scanBtn.textContent = cockpitLabels.buttonScanning;
        } else if (mode === 'failed') {
            scanBtn.classList.remove('is-scanning');
            scanBtn.classList.remove('is-stopping');
            if (label) label.textContent = cockpitLabels.buttonFailed;
            if (!label) scanBtn.textContent = cockpitLabels.buttonFailed;
        } else {
            scanBtn.classList.remove('is-scanning');
            scanBtn.classList.remove('is-stopping');
            var text = mode === 'complete' ? cockpitLabels.buttonComplete : cockpitLabels.buttonReady;
            if (label) label.textContent = text;
            if (!label) scanBtn.textContent = text;
        }
    }

    function markScanStopping() {
        if (!scanBtn) return;
        var label = scanBtn.querySelector('[data-sc-scan-label]');
        scanBtn.classList.add('is-stopping');
        if (label) label.textContent = cockpitLabels.buttonScanning;
    }

    function setScanButtonStage(stageText) {
        if (!scanBtn) return;
        var label = scanBtn.querySelector('[data-sc-scan-label]');
        if (label) label.textContent = stageText;
    }

    function applyLifecycleState(status, prior) {
        // Single lifecycle renderer: applies a mutually-exclusive lifecycle state
        // to cockpit status, investigation text, prior-evidence marker,
        // metrics clearing, progress/orbit labels, scan button visual,
        // and accessibility attributes.
        //   status: 'not_scanned' | 'scanning' | 'failed' | 'complete'
        //   prior:  boolean — whether a completed report is currently mounted
        if (!cockpit) return;
        var sl = cockpitLabels;
        var label = status === 'not_scanned' ? sl.notScanned : (status === 'scanning' ? sl.scanning : (status === 'failed' ? sl.failed : sl.complete));
        cockpit.setAttribute('data-scan-status', status);

        // 1. Cockpit status labels
        setCockpitText('status', label);
        setCockpitText('scan-mode', label);
        setCockpitText('status-metric', label);
        updateScanConsole(status, { label: label, prior: prior });

        // 2. Investigation state text (locale-driven, never hardcoded)
        var invText = status === 'not_scanned' ? sl.investigationStateNotScanned : (status === 'scanning' ? sl.investigationStateScanning : (status === 'failed' ? sl.investigationStateFailed : sl.investigationStateComplete));
        setCockpitText('investigation-state', invText);

        // 3. Prior-evidence marker: visible only when prior completed results exist
        var marker = document.querySelector('[data-sc-prior-evidence]');
        if (marker) {
            if (prior && (status === 'scanning' || status === 'failed')) {
                marker.textContent = status === 'scanning' ? sl.priorScanning : sl.priorFailed;
                marker.hidden = false;
            } else {
                marker.textContent = '';
                marker.hidden = true;
            }
        }

        // 4. Clear metrics on lifecycle transitions that invalidate prior data
        if (status === 'scanning' || status === 'failed') {
            setAllCockpitMetricsToEmpty();
        }

        // 5. Progress / orbit labels
        setCockpitProgress(status);

        // 6. Scan button visual
        setScanButtonVisual(status);

        // 7. Accessibility: aria-busy only during active scanning
        disableScan(status === 'scanning');
    }

    function resetAllOwnersCockpit(mode, prior) {
        applyLifecycleState(mode || 'not_scanned', prior || false);
        setCockpitText('mission', cockpitLabels.missionNoScan);
        setAllCockpitMetricsToEmpty();
        setCockpitText('action-label', cockpitLabels.actionInvestigateLabel);
        setCockpitText('action-word', mode === 'scanning' ? cockpitLabels.buttonScanning : (mode === 'failed' ? cockpitLabels.retryReady : cockpitLabels.notScanned));
        setCockpitText('action-sub', mode === 'scanning' ? cockpitLabels.scanScanningSummary : cockpitLabels.actionNoScanSub);
        setCockpitText('footer-last', mode === 'scanning' ? cockpitLabels.scanning : (mode === 'failed' ? cockpitLabels.failed : emptyMetric));
        setCockpitText('primary-cta', cockpitLabels.actionInvestigate);
        setCockpitHref('primary-cta', '#scInvestigationDetails');
        setCockpitHidden('secondary-cta', true);
        setCockpitHeroReady(false);
    }

    function syncScopeMonitorFromForm(formData) {
        if (!formData) return;
        var scopeValue = formData.get('scope') || '';
        var ownerValue = formData.get('owner') || '';
        var label = scopeValue === 'all_owners' ? 'All Owners' : (scopeValue === 'owner' ? (ownerValue || 'Owner') : scopeValue);
        setCockpitText('scope', label);
        setCockpitText('scope-monitor', label);
        setCockpitText('owner', scopeValue === 'owner' ? (ownerValue || emptyMetric) : (scopeValue === 'all_owners' ? 'All' : emptyMetric));
        setScanConsoleText('scope-title', label);
        setScanConsoleText('scope-desc', scopeDescription(scopeValue));
    }

    function syncCockpitFromState(state) {
        if (!cockpit || !state || !state.summary) return;
        var s = state.summary;
        var scope = state.scope || 'all_owners';
        var na = state.next_action || {};

        applyLifecycleState('complete', false);
        syncScopeMonitorFromForm(new Map([
            ['scope', scope],
            ['owner', scope === 'owner' ? (state.owner_key || '') : '']
        ]));

        // Always populate metrics from canonical state summary
        setCockpitText('owners', String(s.owners_scanned ?? emptyMetric));
        setCockpitText('files', String(s.files_scanned ?? emptyMetric));
        setCockpitText('evidence', String(s.findings_total ?? emptyMetric));
        setCockpitText('total', String(s.findings_total ?? emptyMetric));
        setCockpitText('ready', String(s.fixable_now ?? s.repair_ready ?? emptyMetric));
        setCockpitText('review', String(s.review_only ?? s.review_total ?? emptyMetric));
        setCockpitText('score', s.compliance_score !== null && s.compliance_score !== undefined ? String(s.compliance_score) : emptyMetric);
        setCockpitText('blocked', String(s.blocked_or_not_safe ?? emptyMetric));
        setCockpitText('decisions', String(s.decision_backlog ?? s.decision_total ?? emptyMetric));
        updateScanConsole('complete', { label: cockpitLabels.complete, summary: s });
        setCockpitText('footer-last', 'Just now');

        // Sync owner visibility from state
        syncOwnerControl(scope);

        // Use next_action from state to drive mission/CTA
        var readyNumber = na.kind === 'fixable_now' ? (s.fixable_now || s.repair_ready || 0) : 0;
        var reviewTotal = s.review_only || s.review_total || 0;

        if (readyNumber > 0) {
            setCockpitText('mission', cockpitLabels.missionReady.replace('%d', String(readyNumber)).replace('{count}', String(readyNumber)));
            setCockpitText('action-label', cockpitLabels.actionReadyLabel);
            setCockpitText('action-word', 'Yes');
            setCockpitText('action-sub', cockpitLabels.actionReadySub);
            setCockpitText('primary-cta', cockpitLabels.actionReady);
            setCockpitHref('primary-cta', '#scVerifiedCandidates');
            setCockpitHidden('secondary-cta', false);
            setCockpitHeroReady(true);
        } else {
            setCockpitText('mission', reviewTotal > 0 ? cockpitLabels.missionReview : cockpitLabels.missionNoScan);
            setCockpitText('action-label', reviewTotal > 0 ? cockpitLabels.actionReviewLabel : cockpitLabels.actionInvestigateLabel);
            setCockpitText('action-word', reviewTotal > 0 ? 'Review' : 'Pending');
            setCockpitText('action-sub', cockpitLabels.actionInvestigateSub);
            setCockpitText('primary-cta', cockpitLabels.actionInvestigate);
            setCockpitHref('primary-cta', '#scInvestigationDetails');
            setCockpitHidden('secondary-cta', true);
            setCockpitHeroReady(false);
        }
    }

    if (cockpit) {
        cockpit.addEventListener('click', function(e) {
            var link = e.target.closest('[data-sc-cockpit-primary-cta]');
            if (!link) return;
            var details = document.getElementById('scInvestigationDetails');
            if (details) details.open = true;
        });
    }

    if (cockpit && scopeSelect && scopeSelect.value === 'all_owners' && (!workspaceInput || workspaceInput.value === 'overview')) {
        var initialResultsText = resultsMount ? resultsMount.textContent : '';
        if (initialResultsText.indexOf('Select a scope and run Scan') !== -1) {
            resetAllOwnersCockpit('not_scanned');
        }
    }

    /* Evidence toggle delegation — survives innerHTML replacement */
    if (resultsMount) {
        resultsMount.addEventListener('click', function(e) {
            var toggle = e.target.closest('.sc-evidence-toggle');
            if (!toggle) return;
            var targetId = toggle.getAttribute('data-target');
            if (!targetId) return;
            var row = document.getElementById(targetId);
            if (!row) return;
            row.hidden = !row.hidden;
        });

        /* Remaining cards toggle */
        resultsMount.addEventListener('click', function(e) {
            var btn = e.target.closest('.sc-remaining-toggle');
            if (!btn) return;
            var showLabel = btn.getAttribute('data-show') || '';
            var hideLabel = btn.getAttribute('data-hide') || '';
            var extras = btn.closest('.sc-section').querySelectorAll('.sc-queue-card-extra');
            var anyHidden = false;
            for (var i = 0; i < extras.length; i++) {
                if (extras[i].hidden) { anyHidden = true; break; }
            }
            for (var j = 0; j < extras.length; j++) {
                extras[j].hidden = anyHidden ? false : true;
            }
            btn.textContent = anyHidden ? hideLabel : showLabel;
        });

        resultsMount.addEventListener('click', function(e) {
            var btn = e.target.closest('.sc-readiness-check');
            if (!btn || !csrfInput) return;
            var proposalId = btn.getAttribute('data-proposal-id') || '';
            var cardMain = btn.closest('.sc-queue-card-main');
            var inline = cardMain ? cardMain.querySelector('[data-readiness-status]') : null;
            var resultEl = cardMain ? cardMain.querySelector('[data-readiness-result]') : null;
            if (!proposalId || !resultEl) return;
            btn.disabled = true;
            if (inline) inline.textContent = readinessLabels.checking;
            resultEl.hidden = true;
            resultEl.textContent = '';

            var formData = new FormData();
            formData.set('proposal_id', proposalId);
            formData.set('csrf', csrfInput.value);

            fetch('/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            })
            .then(function(resp) {
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                return resp.json();
            })
            .then(function(data) {
                var state = data && data.state ? String(data.state) : 'blocked';
                var label = readinessLabels[state] || state;
                var reason = data && data.reason ? String(data.reason) : '';
                if (inline) inline.textContent = label;
                var checks = Array.isArray(data && data.checks) ? data.checks : [];
                var detail = '';
                if (checks.length > 0) {
                    detail = '<details><summary>' + escapeHtml(readinessLabels.checks) + '</summary><dl class="sc-evidence-dl">' + checks.map(function(check) {
                        return '<dt>' + escapeHtml(String(check.key || '')) + '</dt><dd>' + escapeHtml(String(check.state || '')) + ' — ' + escapeHtml(String(check.detail || '')) + '</dd>';
                    }).join('') + '</dl></details>';
                }
                resultEl.innerHTML = '<strong>' + escapeHtml(label) + '</strong>' + escapeHtml(reason) + detail;
                resultEl.hidden = false;
            })
            .catch(function(err) {
                if (inline) inline.textContent = readinessLabels.failed;
                resultEl.textContent = readinessLabels.failed + ': ' + (err && err.message ? err.message : 'Unknown error');
                resultEl.hidden = false;
            })
            .finally(function() {
                btn.disabled = false;
            });
        });

        resultsMount.addEventListener('click', function(e) {
            var btn = e.target.closest('.sc-fix-one');
            if (!btn || !csrfInput) return;
            var proposalId = btn.getAttribute('data-proposal-id') || '';
            var cardMain = btn.closest('.sc-queue-card-main');
            var inline = cardMain ? cardMain.querySelector('[data-fix-one-status]') : null;
            var resultEl = cardMain ? cardMain.querySelector('[data-fix-one-result]') : null;
            if (!proposalId || !resultEl) return;

            btn.disabled = true;
            if (inline) inline.textContent = 'Fixing';
            resultEl.hidden = true;
            resultEl.textContent = '';

            var formData = new FormData();
            formData.set('proposal_id', proposalId);
            formData.set('csrf', csrfInput.value);

            fetch('/apps/studio/tools/customization-studio/diagnose/style-compliance/fix-one', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            })
            .then(function(resp) {
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                return resp.json();
            })
            .then(function(data) {
                var state = data && data.state ? String(data.state) : 'failed';
                var evidence = data && data.evidence && typeof data.evidence === 'object' ? data.evidence : {};
                var reason = data && data.reason ? String(data.reason) : '';
                if (inline) inline.textContent = state;
                var changed = evidence.changed_declaration ? '<dt>Changed</dt><dd><code>' + escapeHtml(String(evidence.changed_declaration)) + '</code></dd>' : '';
                var replacement = evidence.replacement_declaration ? '<dt>Replacement</dt><dd><code>' + escapeHtml(String(evidence.replacement_declaration)) + '</code></dd>' : '';
                var disappeared = typeof evidence.original_finding_disappeared !== 'undefined'
                    ? '<dt>Original finding disappeared</dt><dd>' + escapeHtml(String(evidence.original_finding_disappeared)) + '</dd>'
                    : '';
                resultEl.innerHTML = '<strong>' + escapeHtml(state) + '</strong>: ' + escapeHtml(reason)
                    + '<dl class="sc-evidence-dl">' + changed + replacement + disappeared + '</dl>';
                resultEl.hidden = false;
                if (state === 'fixed') {
                    btn.hidden = true;
                    if (data && data.scan_state && data.scan_state.summary) {
                        syncCockpitFromState(data.scan_state);
                        var countEl = document.querySelector('[data-fixable-now-count]');
                        if (countEl && typeof data.scan_state.summary.fixable_now !== 'undefined') {
                            countEl.textContent = String(data.scan_state.summary.fixable_now);
                        }
                    }
                } else {
                    btn.disabled = false;
                }
            })
            .catch(function(err) {
                if (inline) inline.textContent = 'failed';
                resultEl.textContent = 'failed: ' + (err && err.message ? err.message : 'Unknown error');
                resultEl.hidden = false;
                btn.disabled = false;
            });
        });

    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function(ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
        });
    }

    function setProgress(stateKey, errorMsg) {
        if (stateKey === 'preparing') {
            setScanButtonStage(cockpitLabels.buttonPreparing);
            updateScanConsole('preparing', { label: cockpitLabels.preparing, prior: hasPriorEvidence });
        }
        if (stateKey === 'scanning' || stateKey === 'building' || stateKey === 'rendering') {
            setScanButtonStage(cockpitLabels.buttonScanning);
            updateScanConsole('scanning', { label: cockpitLabels.scanning, prior: hasPriorEvidence });
        }
        if (stateKey === 'complete') {
            setScanButtonStage(cockpitLabels.buttonComplete);
        }
        if (stateKey === 'failed') {
            setScanButtonStage(cockpitLabels.buttonFailed);
        }
    }

    function disableScan(disabled) {
        if (scanBtn) scanBtn.setAttribute('aria-busy', disabled ? 'true' : 'false');
    }

    function generateScanId() {
        var arr = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(arr);
        } else {
            for (var i = 0; i < 16; i++) arr[i] = Math.floor(Math.random() * 256);
        }
        arr[6] = (arr[6] & 0x0f) | 0x40;
        arr[8] = (arr[8] & 0x3f) | 0x80;
        var hex = [];
        for (var j = 0; j < 16; j++) {
            hex.push((arr[j] >>> 4).toString(16));
            hex.push((arr[j] & 0x0f).toString(16));
        }
        return hex.join('');
    }

    if (scanForm && scanBtn && resultsMount && csrfInput) {
        scanBtn.addEventListener('click', function(e) {
            if (!activeScanController) return;
            e.preventDefault();
            markScanStopping();
            activeScanController.abort();
        });

        scanForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var scope = scanForm.querySelector('[name="scope"]');
            var owner = scanForm.querySelector('[name="owner"]');
            if (!scope) return;
            if (activeScanController) return;

            activeScanId = generateScanId();

            // Capture whether a completed report is currently mounted — becomes
            // prior evidence if the new scan succeeds or fails with prior results visible.
            hasPriorEvidence = cockpit ? cockpit.getAttribute('data-scan-status') === 'complete' : false;

            disableScan(true);
            if (scope.value === 'all_owners' && (!workspaceInput || workspaceInput.value === 'overview')) {
                resetAllOwnersCockpit('scanning', hasPriorEvidence);
            } else {
                applyLifecycleState('scanning', hasPriorEvidence);
            }
            setProgress('preparing');

            var formData = new FormData();
            formData.set('scope', scope.value);
            formData.set('owner', scope.value === 'owner' && owner ? owner.value : '');
            formData.set('workspace', workspaceInput ? workspaceInput.value : 'overview');
            formData.set('csrf', csrfInput.value);
            formData.set('scan_id', activeScanId);
            syncScopeMonitorFromForm(formData);

            var controller = new AbortController();
            activeScanController = controller;
            var timeoutId = setTimeout(function() { controller.abort(); }, 60000);
            activeScanTimeout = timeoutId;

            setProgress('scanning');

            fetch('/apps/studio/tools/customization-studio/diagnose/style-compliance/scan', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
                signal: controller.signal,
            })
            .then(function(resp) {
                clearTimeout(timeoutId);
                activeScanTimeout = null;
                setProgress('building');
                if (!resp.ok) {
                    return resp.json().then(function(data) {
                        var err = new Error(data && data.error ? data.error : 'HTTP ' + resp.status);
                        err.state = data && data.state || null;
                        throw err;
                    }).catch(function(catchErr) {
                        if (catchErr.state) throw catchErr;
                        var err2 = new Error('HTTP ' + resp.status);
                        err2.state = null;
                        throw err2;
                    });
                }
                return resp.json();
            })
            .then(function(data) {
                // Supersession guard: only the newest request may update the page
                if (data.state && data.state.scan_id && data.state.scan_id !== activeScanId) {
                    activeScanController = null;
                    activeScanId = null;
                    disableScan(false);
                    return;
                }
                setProgress('rendering');
                if (data.html) {
                    resultsMount.innerHTML = data.html;
                    syncCockpitFromState(data.state);
                    hasPriorEvidence = true;
                    if (data.canonical_url) {
                        history.replaceState(data.state || null, '', data.canonical_url);
                    }
                    var hash = window.location.hash;
                    if (hash) {
                        var details = document.getElementById('scInvestigationDetails');
                        if (details) details.open = true;
                        var target = document.querySelector(hash);
                        if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                    }
                }
                if (window.scReinitEvidence) window.scReinitEvidence();
                setProgress('complete');
                activeScanController = null;
                activeScanId = null;
                disableScan(false);
            })
            .catch(function(err) {
                clearTimeout(timeoutId);
                activeScanTimeout = null;
                activeScanController = null;
                // Supersession guard: if a newer scan has started, don't update UI
                if (err.state && err.state.scan_id && err.state.scan_id !== activeScanId) {
                    disableScan(false);
                    return;
                }
                var errorMsg = err.message || 'Scan failed. Please try again.';
                if (err.name === 'AbortError') {
                    errorMsg = 'Scan timed out. Please try again.';
                } else if (errorMsg === 'Failed to fetch') {
                    errorMsg = 'Scan request failed (network or server error). Verify the server is running and try again.';
                }
                var failureLabel = classifyFailureLabel(errorMsg);
                var failedScope = formData.get('scope') || 'unknown';
                var failedOwner = formData.get('owner') || '';
                var failedWs = formData.get('workspace') || 'overview';
                var scopeLabel = failedScope === 'owner' && failedOwner ? failedScope + '/' + failedOwner : failedScope;
                if (!hasPriorEvidence) {
                    resultsMount.innerHTML = '<div class="sc-empty" style="color:var(--color-danger-text)">' + escapeHtml(cockpitLabels.resultsUnavailableFailed || errorMsg) + '</div>';
                }
                if (failedScope === 'all_owners' && failedWs === 'overview') {
                    resetAllOwnersCockpit('failed', hasPriorEvidence);
                } else {
                    applyLifecycleState('failed', hasPriorEvidence);
                    setCockpitText('mission', cockpitLabels.missionNoScan);
                    setCockpitText('action-label', cockpitLabels.actionInvestigateLabel);
                    setCockpitText('action-word', 'Failed');
                    setCockpitText('action-sub', cockpitLabels.actionNoScanSub);
                    setCockpitText('primary-cta', cockpitLabels.actionInvestigate);
                    setCockpitHref('primary-cta', '#scInvestigationDetails');
                    setCockpitHidden('secondary-cta', true);
                    setCockpitHeroReady(false);
                }
                setCockpitText('status', failureLabel);
                setCockpitText('scan-mode', failureLabel);
                setCockpitText('status-metric', failureLabel);
                updateScanConsole('failed', {
                    label: failureLabel,
                    message: errorMsg,
                    prior: hasPriorEvidence
                });
                setCockpitText('footer-last', 'Failed');
                setProgress('failed', errorMsg);
                disableScan(false);
                activeScanId = null;
            });
        });
    }
})();
</script>
