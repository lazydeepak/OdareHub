#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

failures=0
severe_findings=0
advisory_findings=0
accepted_findings=0
review_findings=0
MODE_JSON=0
MODE_VERBOSE=0

for arg in "$@"; do
	case "$arg" in
		--json) MODE_JSON=1 ;;
		--verbose) MODE_VERBOSE=1 ;;
		--help)
			echo "Usage: bash scripts/architecture/check_style_compliance_css_ownership.sh [--verbose] [--json]"
			exit 0
			;;
		*)
			echo "Unknown argument: $arg"
			exit 2
			;;
	esac
done

FINDINGS_TSV="$(mktemp)"
trap 'rm -f "$FINDINGS_TSV"' EXIT

pass() {
	if [[ "$MODE_JSON" -eq 0 ]]; then
		echo "PASS: $1"
	fi
}

fail() {
	if [[ "$MODE_JSON" -eq 0 ]]; then
		echo "FAIL: $1"
	fi
	failures=$((failures + 1))
}

record_finding() {
	local type="$1"
	local target="$2"
	local current_owner="$3"
	local suggested_owner="$4"
	local reason="$5"
	local action="$6"
	local confidence="$7"
	local severity="$8"

	target="${target//$'\t'/ }"
	reason="${reason//$'\t'/ }"
	action="${action//$'\t'/ }"

	printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
		"$type" "$target" "$current_owner" "$suggested_owner" "$reason" "$action" "$confidence" "$severity" \
		>> "$FINDINGS_TSV"

	if [[ "$severity" == "severe" ]]; then
		severe_findings=$((severe_findings + 1))
	elif [[ "$severity" == "accepted" ]]; then
		accepted_findings=$((accepted_findings + 1))
	elif [[ "$severity" == "review" ]]; then
		review_findings=$((review_findings + 1))
	else
		advisory_findings=$((advisory_findings + 1))
	fi
}

json_escape() {
	printf '%s' "$1" | awk '{ gsub(/\\/,"\\\\"); gsub(/"/,"\\\""); gsub(/\r/,"\\r"); gsub(/\n/,"\\n"); printf "%s", $0 }'
}

emit_finding_text() {
	local type="$1"
	local target="$2"
	local current_owner="$3"
	local suggested_owner="$4"
	local reason="$5"
	local action="$6"
	local confidence="$7"

	echo "[CSS-OWNERSHIP] $type"
	echo "Target: $target"
	echo "Current owner: $current_owner"
	echo "Suggested owner: $suggested_owner"
	echo "Reason: $reason"
	echo "Recommended action: $action"
	echo "Confidence: $confidence"
	echo
}

emit_findings_text() {
	local max_advisories=20
	local shown_advisories=0
	while IFS=$'\t' read -r type target current_owner suggested_owner reason action confidence severity; do
		if [[ -z "$type" ]]; then
			continue
		fi
		if [[ "$MODE_VERBOSE" -ne 1 && "$type" == "acceptable_domain_style" ]]; then
			continue
		fi
		if [[ "$MODE_VERBOSE" -ne 1 && "$severity" == "advisory" ]]; then
			if [[ "$shown_advisories" -ge "$max_advisories" ]]; then
				continue
			fi
			shown_advisories=$((shown_advisories + 1))
		fi
		emit_finding_text "$type" "$target" "$current_owner" "$suggested_owner" "$reason" "$action" "$confidence"
	done < "$FINDINGS_TSV"
}

emit_findings_json() {
	local first=1
	echo '{'
	echo '  "findings": ['
	while IFS=$'\t' read -r type target current_owner suggested_owner reason action confidence severity; do
		if [[ -z "$type" ]]; then
			continue
		fi
		if [[ "$MODE_VERBOSE" -ne 1 && "$type" == "acceptable_domain_style" ]]; then
			continue
		fi
		if [[ "$first" -eq 0 ]]; then
			echo '    ,'
		fi
		first=0
		printf '    {"type":"%s","selector":"%s","current_owner":"%s","suggested_owner":"%s","reason":"%s","recommended_action":"%s","confidence":"%s","severity":"%s"}' \
			"$(json_escape "$type")" \
			"$(json_escape "$target")" \
			"$(json_escape "$current_owner")" \
			"$(json_escape "$suggested_owner")" \
			"$(json_escape "$reason")" \
			"$(json_escape "$action")" \
			"$(json_escape "$confidence")" \
			"$(json_escape "$severity")"
		echo
	done < "$FINDINGS_TSV"
	echo '  ],'
	echo '  "summary": {'
	printf '    "severe": %s,\n' "$severe_findings"
	printf '    "advisory": %s,\n' "$advisory_findings"
	printf '    "accepted": %s,\n' "$accepted_findings"
	printf '    "review": %s\n' "$review_findings"
	echo '  }'
	echo '}'
}

emit_summary() {
	if [[ "$MODE_JSON" -eq 1 ]]; then
		return
	fi
	echo "[style-compliance-css-ownership] governance advisor summary"
	echo "- severe: $severe_findings"
	echo "- advisory: $advisory_findings"
	echo "- accepted: $accepted_findings"
	echo "- needs review: $review_findings"
	echo
}

require_file_exists() {
	local file="$1"
	local label="$2"
	local missing_type="${3:-improper_owner_file}"
	local suggested_owner="${4:-Style Compliance Owner}"
	local reason="${5:-Required Style Compliance ownership file is missing.}"
	local action="${6:-Restore the required file path and owner artifact.}"
	local confidence="${7:-high}"
	if [[ -f "$file" ]]; then
		pass "$label"
	else
		fail "$label (missing: $file)"
		record_finding "$missing_type" "$file" "Unknown / Needs Review" "$suggested_owner" "$reason" "$action" "$confidence" "severe"
	fi
}

require_pattern() {
	local file="$1"
	local pattern="$2"
	local label="$3"
	local type="${4:-needs_review}"
	local current_owner="${5:-Style Compliance Owner}"
	local suggested_owner="${6:-Style Compliance Owner}"
	local reason="${7:-Required ownership signal missing.}"
	local action="${8:-Restore required ownership contract marker.}"
	local confidence="${9:-high}"
	if grep -Eq "$pattern" "$file"; then
		pass "$label"
	else
		fail "$label (missing pattern: $pattern in $file)"
		record_finding "$type" "$file" "$current_owner" "$suggested_owner" "$reason" "$action" "$confidence" "severe"
	fi
}

forbid_pattern() {
	local file="$1"
	local pattern="$2"
	local label="$3"
	local type="${4:-needs_review}"
	local current_owner="${5:-Style Compliance Owner}"
	local suggested_owner="${6:-Style Compliance Owner}"
	local reason="${7:-Forbidden ownership anti-pattern detected.}"
	local action="${8:-Remove the anti-pattern and keep ownership boundaries clean.}"
	local confidence="${9:-high}"
	if grep -Eq "$pattern" "$file"; then
		fail "$label (forbidden pattern: $pattern in $file)"
		record_finding "$type" "$file" "$current_owner" "$suggested_owner" "$reason" "$action" "$confidence" "severe"
	else
		pass "$label"
	fi
}

is_domain_qualified_selector() {
	local selector="$1"
	if [[ "$selector" =~ (repair|finding|compliance|scan|readiness|investigation|cockpit|evidence|backlog|token|owner) ]]; then
		return 0
	fi
	return 1
}

allowlist_reason() {
	local selector="$1"
	if [[ "$selector" =~ ^\.sc-scan- ]]; then
		echo "Scan identity and cockpit telemetry visuals; review quarterly for primitive extraction opportunities."
		return 0
	fi
	if [[ "$selector" =~ ^\.sc-readiness- ]]; then
		echo "Readiness/repair safety semantics are Style Compliance workflow-owned; review during repair UX revisions."
		return 0
	fi
	if [[ "$selector" =~ ^\.sc-investigation- ]]; then
		echo "Investigation visualization belongs to Style Compliance detailed analysis domain; review at investigation IA milestones."
		return 0
	fi
	if [[ "$selector" =~ ^\.sc-repair- ]]; then
		echo "Repair workflow semantics are domain-owned; review when repair executor contract changes."
		return 0
	fi
	if [[ "$selector" =~ ^\.sc-finding- ]]; then
		echo "Finding visualization is domain-specific; review if shared finding primitives are introduced."
		return 0
	fi
	return 1
}

is_generic_selector_signal() {
	local selector="$1"
	if [[ "$selector" =~ (grid|layout|row|column|card|panel|table|toolbar|header|footer|tabs|section|status|empty|error|button|link|scroll|wrap) ]]; then
		return 0
	fi
	return 1
}

scan_selector_ownership() {
	local style_file="$1"
	while IFS= read -r selector; do
		selector="$(echo "$selector" | sed -E 's/^[[:space:]]+|[[:space:]]+$//g')"
		if [[ -z "$selector" ]]; then
			continue
		fi
		if allowlist_reason "$selector" >/dev/null; then
			if [[ "$MODE_VERBOSE" -eq 1 ]]; then
				record_finding "acceptable_domain_style" "$selector" "Style Compliance Owner" "Style Compliance Owner" "$(allowlist_reason "$selector")" "Keep local." "high" "accepted"
			fi
			continue
		fi
		if is_generic_selector_signal "$selector" && ! is_domain_qualified_selector "$selector"; then
			record_finding "generic_layout_in_tool" "$selector" "Style Compliance Owner" "Studio/Shell" "Selector appears reusable across Studio tools (generic layout/rendering signal)." "Migrate to gui_studio.css or replace with an existing gs-tool primitive." "high" "advisory"
		fi
	done < <(grep -oE '^[[:space:]]*\.[a-zA-Z0-9_-][^,{]*' "$style_file" | sed -E 's/^[[:space:]]+|[[:space:]]+$//g' | sort -u)
}

scan_visual_value_findings() {
	local style_file="$1"
	local line_num=0
	local max_findings=220
	local counted=0
	while IFS= read -r line; do
		line_num=$((line_num + 1))
		if [[ "$line" =~ ^[[:space:]]*@media[[:space:]]*\(.*[0-9]+px ]]; then
			record_finding "hardcoded_spacing_value" "line $line_num (@media breakpoint)" "Style Compliance Owner" "Foundation" "Hardcoded breakpoint detected." "Track as migration debt until shared breakpoint governance contract is finalized." "medium" "advisory"
			counted=$((counted + 1))
		fi
		if [[ "$line" =~ ^[[:space:]]*\. ]] && [[ "$line" =~ \{ ]] && [[ "$line" =~ \} ]]; then
			local selector
			selector="$(echo "$line" | sed -E 's/^([^{]+)\{.*$/\1/' | sed -E 's/^[[:space:]]+|[[:space:]]+$//g')"
			local body
			body="$(echo "$line" | sed -E 's/^[^{]*\{(.*)\}[[:space:]]*$/\1/')"
			local location="$selector (line $line_num)"

			if [[ "$body" =~ (color|background|border).*(#[0-9a-fA-F]{3,8}|rgb\(|rgba\() ]]; then
				if allowlist_reason "$selector" >/dev/null || is_domain_qualified_selector "$selector"; then
					record_finding "acceptable_domain_style" "$location" "Style Compliance Owner" "Style Compliance Owner" "Domain-specific color treatment is acceptable for Style Compliance identity." "Keep local unless a clear shared token exists." "medium" "accepted"
				elif [[ "$selector" =~ ^\.gs-tool- ]]; then
					record_finding "hardcoded_theme_value" "$location" "Style Compliance Owner" "Theme System" "Shared primitive namespace selector includes hardcoded color in owner CSS." "Move style responsibility to shared primitive layer and consume theme tokens." "high" "severe"
				else
					record_finding "hardcoded_theme_value" "$location" "Style Compliance Owner" "Theme System" "Hardcoded theme-like color value found in a generic selector context." "Replace with existing theme token variables (var(--style-*), var(--color-*), var(--text/muted/accent)) when obvious; otherwise review." "high" "advisory"
				fi
				counted=$((counted + 1))
			fi

			if [[ "$body" =~ (margin|padding|gap|width|height|min-width|min-height|max-width|max-height|inset|top|right|bottom|left).*[0-9]+px ]] || [[ "$body" =~ border-radius.*[0-9]+px ]] || [[ "$body" =~ box-shadow.*[0-9]+px ]]; then
				if allowlist_reason "$selector" >/dev/null; then
					record_finding "acceptable_domain_style" "$location" "Style Compliance Owner" "Style Compliance Owner" "Allowlisted domain selector uses local dimensional constants." "Keep local; review quarterly." "medium" "accepted"
				else
					record_finding "hardcoded_spacing_value" "$location" "Style Compliance Owner" "Foundation" "Hardcoded dimensional value detected (spacing/radius/shadow)." "Review if Foundation primitive/token can replace this value." "medium" "advisory"
				fi
				counted=$((counted + 1))
			fi

			if [[ "$body" =~ (transition|animation).*([0-9]*\.?[0-9]+m?s) ]]; then
				if [[ "$selector" =~ (scan|cockpit|investigation|readiness) ]]; then
					record_finding "acceptable_domain_style" "$location" "Style Compliance Owner" "Style Compliance Owner" "Domain-owned motion constant supports scan/investigation semantics." "Keep local unless a shared motion token contract is introduced." "high" "accepted"
				else
					record_finding "hardcoded_motion_value" "$location" "Style Compliance Owner" "Foundation" "Hardcoded motion duration found outside obvious domain animation context." "Evaluate shared motion token or primitive usage." "medium" "review"
				fi
				counted=$((counted + 1))
			fi

			if [[ "$body" =~ z-index.*[0-9]+ ]]; then
				if allowlist_reason "$selector" >/dev/null || [[ "$selector" =~ \.(sc-scan-stage|sc-scan-orbit|sc-cockpit) ]]; then
					record_finding "acceptable_domain_style" "$location" "Style Compliance Owner" "Style Compliance Owner" "Domain scan layering constant is acceptable for cockpit/orbit composition." "Keep local while scan visualization remains domain-specific; review if scan layering is standardized." "high" "accepted"
				else
					record_finding "needs_review" "$location" "Style Compliance Owner" "Studio/Shell" "Local z-index value detected." "Confirm it aligns with Shell/Studio layering contracts." "medium" "review"
				fi
				counted=$((counted + 1))
			fi
		fi

		if [[ "$counted" -ge "$max_findings" ]]; then
			record_finding "needs_review" "$style_file" "Style Compliance Owner" "Unknown / Needs Review" "Finding cap reached; additional potential advisories were truncated." "Run with --verbose and narrow scope manually if deeper triage is required." "low" "review"
			break
		fi
	done < "$style_file"
}

scan_color_governance_findings() {
	local style_file="$1"
	local line_num=0
	local max_findings=220
	local counted=0
	while IFS= read -r raw_line; do
		line_num=$((line_num + 1))
		local line="$raw_line"
		line="${line%%//*}"
		line="$(echo "$line" | sed -E 's/^[[:space:]]+|[[:space:]]+$//g')"
		if [[ -z "$line" ]]; then
			continue
		fi

		if [[ "$line" =~ ^--sc-[a-zA-Z0-9_-]+:[[:space:]]*.*$ ]]; then
			local alias_name
			alias_name="$(echo "$line" | sed -E 's/^([[:space:]]*--sc-[a-zA-Z0-9_-]+):.*$/\1/')"
			if [[ "$line" =~ (#[0-9a-fA-F]{3,8}|rgb[a]?\(|hsl[a]?\() ]]; then
				record_finding "hardcoded_theme_value" "line $line_num ($alias_name)" "Style Compliance Owner" "Theme System" "SC alias has a hardcoded literal color value." "Map alias to approved platform token(s) via var(--style-*), var(--color-*), var(--text/muted/accent/bg/card), optionally color-mix on token values." "high" "severe"
				counted=$((counted + 1))
			elif [[ "$line" =~ ^--sc-[a-zA-Z0-9_-]+:[[:space:]]*var\(-- ]]; then
				record_finding "acceptable_domain_style" "line $line_num ($alias_name)" "Style Compliance Owner" "Style Compliance Owner" "SC alias maps to approved theme/platform token source." "Keep alias mapping token-backed." "high" "accepted"
				counted=$((counted + 1))
			elif [[ "$line" =~ color-mix\( ]] && [[ "$line" =~ var\(-- ]]; then
				record_finding "needs_review" "line $line_num ($alias_name)" "Style Compliance Owner" "Theme System" "SC alias uses token-backed color-mix." "Allowed with review; keep token-backed and avoid literal colors." "medium" "advisory"
				counted=$((counted + 1))
			fi
			continue
		fi

		if [[ "$line" =~ (#[0-9a-fA-F]{3,8}|rgb[a]?\(|hsl[a]?\() ]]; then
			record_finding "hardcoded_theme_value" "line $line_num" "Style Compliance Owner" "Theme System" "Hardcoded semantic color literal found in owner CSS declaration." "Replace with token-backed var(...) or token-backed color-mix(...)." "high" "severe"
			counted=$((counted + 1))
		fi

		if [[ "$line" =~ color-mix\( ]] && [[ "$line" =~ var\(-- ]]; then
			record_finding "needs_review" "line $line_num" "Style Compliance Owner" "Theme System" "Token-backed color-mix detected." "Advisory-only: keep token-backed inputs and avoid introducing literals." "medium" "advisory"
			counted=$((counted + 1))
		fi

		if [[ "$line" =~ transparent|currentColor ]]; then
			record_finding "acceptable_domain_style" "line $line_num" "Style Compliance Owner" "Style Compliance Owner" "Transparent/currentColor usage detected." "Allowed for compositing and inheritance semantics." "high" "advisory"
			counted=$((counted + 1))
		fi

		if [[ "$counted" -ge "$max_findings" ]]; then
			record_finding "needs_review" "$style_file" "Style Compliance Owner" "Unknown / Needs Review" "Color-governance finding cap reached; additional advisories were truncated." "Run with --verbose for full triage or narrow scan range." "low" "review"
			break
		fi
	done < "$style_file"
}

STYLE_HEADER="apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_page_header.php"
STYLE_CSS="apps/Studio/styles/style-compliance.css"
RESULT_SECTIONS="apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_sections.php"
RESULT_SUMMARY="apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_action_summary.php"
RESULT_BACKLOG="apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_decision_backlog.php"
PREVIEW="apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/preview.php"

if [[ "$MODE_JSON" -eq 0 ]]; then
	echo "[style-compliance-css-ownership] checking boundaries"
fi

require_file_exists "$STYLE_HEADER" "Style Compliance shared page header exists" "improper_owner_file" "Style Compliance Owner" "Shared header partial for Style Compliance is missing." "Restore the shared header partial under Style Compliance views." "high"
require_file_exists "$STYLE_CSS" "Style Compliance stylesheet exists" "improper_owner_file" "Style Compliance Owner" "Owner stylesheet for Style Compliance is missing." "Restore owner stylesheet at apps/Studio/styles/style-compliance.css." "high"

# CSS must be externalized, never inline in the view.
forbid_pattern "$STYLE_HEADER" '<style>' "No inline style blocks in Style Compliance header" "inline_css" "Style Compliance Owner" "Style Compliance Owner" "Inline CSS bypasses ownership governance and external stylesheet controls." "Move styles into apps/Studio/styles/style-compliance.css and keep the view link-only." "high"
require_pattern "$STYLE_HEADER" '/assets/apps/studio/styles/gui_studio.css' "Header links shared Studio primitives stylesheet" "missing_shared_primitive" "Style Compliance Owner" "Studio/Shell" "Shared Studio primitives stylesheet link is missing." "Restore gui_studio.css link in header view." "high"
require_pattern "$STYLE_HEADER" '/assets/apps/studio/styles/style-compliance.css' "Header links Style Compliance owner stylesheet" "improper_owner_file" "Style Compliance Owner" "Style Compliance Owner" "Owner stylesheet link is missing." "Restore style-compliance.css link in header view." "high"

# Ownership annotations in owner stylesheet are mandatory for contributor clarity.
require_pattern "$STYLE_CSS" 'Style Compliance CSS Ownership Ledger \(enforced\)' "Owner stylesheet carries ownership header" "needs_review" "Style Compliance Owner" "Style Compliance Owner" "Ownership header annotation missing." "Restore ownership ledger header comment in owner CSS." "high"
require_pattern "$STYLE_CSS" 'Style Compliance owner: sc-\* domain rendering only' "Owner stylesheet documents sc-* ownership" "needs_review" "Style Compliance Owner" "Style Compliance Owner" "Ownership annotation for sc-* domain rendering missing." "Restore ownership annotation for sc-* styles." "high"

# Keep owner stylesheet isolated from generic shared primitives.
forbid_pattern "$STYLE_CSS" '^\.gs-tool-(page|header|scope-strip|scope-form|status-bar|scroll-table)' "Owner stylesheet must not redefine shared gs-tool primitives" "shared_primitive_duplicate" "Style Compliance Owner" "Studio/Shell" "Owner stylesheet redefines shared gs-tool primitive(s)." "Remove duplicated primitive definitions and consume shared gui_studio.css classes." "high"

# Disallow universal hidden override from owner scope (belongs to shared/foundation).
forbid_pattern "$STYLE_CSS" '^\[hidden\]' "Owner stylesheet must not globally redefine [hidden]" "generic_layout_in_tool" "Style Compliance Owner" "Foundation" "Global hidden selector belongs to shared behavior layer, not tool owner CSS." "Remove [hidden] override from owner CSS." "high"

# Action links must consume shared primitive while allowing local tone variants.
require_pattern "$RESULT_SECTIONS" 'sc-next-action-link gs-tool-action-link' "Section actions consume shared gs-tool-action-link primitive" "missing_shared_primitive" "Style Compliance Owner" "Studio/Shell" "SC section action link is missing shared primitive consumption." "Add gs-tool-action-link to SC next-action CTA class list." "high"
require_pattern "$RESULT_SUMMARY" 'sc-next-action-link gs-tool-action-link' "Action summary primary CTA consumes shared gs-tool-action-link primitive" "missing_shared_primitive" "Style Compliance Owner" "Studio/Shell" "Action summary CTA is missing shared primitive consumption." "Add gs-tool-action-link to primary CTA class list." "high"
require_pattern "$RESULT_BACKLOG" 'sc-next-action-link gs-tool-action-link' "Backlog handoff CTA consumes shared gs-tool-action-link primitive" "missing_shared_primitive" "Style Compliance Owner" "Studio/Shell" "Backlog handoff CTA is missing shared primitive consumption." "Add gs-tool-action-link to backlog handoff CTA class list." "high"

# Probe compatibility comments should remain comments only.
require_pattern "$PREVIEW" '// \.sc-next-action-link' "Preview keeps compatibility shim as comment-only reference" "acceptable_domain_style" "Style Compliance Owner" "Style Compliance Owner" "Compatibility shim marker remains comment-only." "Keep as comment-only probe compatibility marker." "high"

# Advisory scanner: selector ownership and local visual constants.
scan_selector_ownership "$STYLE_CSS"
scan_color_governance_findings "$STYLE_CSS"
scan_visual_value_findings "$STYLE_CSS"

if [[ "$MODE_JSON" -eq 0 ]]; then
	echo
fi
emit_summary

if [[ "$MODE_JSON" -eq 1 ]]; then
	emit_findings_json
else
	emit_findings_text
fi

if [[ "$failures" -gt 0 ]]; then
	if [[ "$MODE_JSON" -eq 0 ]]; then
		echo "RESULT: FAIL ($failures issues)"
	fi
	exit 1
fi

if [[ "$MODE_JSON" -eq 0 ]]; then
	echo "RESULT: PASS"
fi
