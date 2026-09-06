#!/usr/bin/env bash
# Generic gate: declared operator focus_tokens must equal provider view_map keys.
set -u
cd "$(dirname "$0")/../.." || exit 1
php scripts/architecture/probe_operator_focus_declaration_parity.php
exit $?
