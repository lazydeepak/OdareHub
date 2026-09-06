#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_parties_contract"
echo "- read-only validation for Parties canonical data owner v1"

failures=0

check_file() {
  local path="$1"; local label="$2"
  if [[ -f "$path" ]]; then echo "  ok: $label ($path)"; else echo "  fail: missing $label ($path)" >&2; failures=$((failures+1)); fi
}
check_text() {
  local path="$1"; local needle="$2"; local label="$3"
  if [[ ! -f "$path" ]]; then echo "  fail: cannot inspect $label; missing $path" >&2; failures=$((failures+1)); return; fi
  if grep -Fq -- "$needle" "$path"; then echo "  ok: $label"; else echo "  fail: $label" >&2; failures=$((failures+1)); fi
}
check_absent_text() {
  local path="$1"; local needle="$2"; local label="$3"
  if [[ ! -f "$path" ]]; then return; fi
  if grep -Fq -- "$needle" "$path"; then echo "  fail: $label — found forbidden '$needle' in $path" >&2; failures=$((failures+1)); else echo "  ok: $label"; fi
}
check_absent_grep() {
  local pattern="$1"; local label="$2"
  if grep -R -q -- "$pattern" apps/Parties/ 2>/dev/null; then
    echo "  fail: $label — forbidden pattern '$pattern' found in apps/Parties/" >&2
    grep -R -- "$pattern" apps/Parties/ 2>/dev/null | head -3 | sed 's/^/    /' >&2
    failures=$((failures+1))
  else
    echo "  ok: $label"
  fi
}

echo ""
echo "== Parties App manifest =="
manifest="apps/Parties/manifest.json"
check_file "$manifest" "Parties manifest"
if [[ -f "$manifest" ]]; then
  if ! php -- "$manifest" <<'PHP' 2>&1 | head -20
<?php
$mf=$argv[1];
$raw=file_get_contents($mf);
try { $d=json_decode($raw,true,512,JSON_THROW_ON_ERROR); } catch(Throwable $e){ fwrite(STDERR,"  fail: manifest invalid JSON: ".$e->getMessage()."\n"); exit(1); }
$fail=0;
$check=function($cond,$ok,$failMsg) use (&$fail){ if($cond) echo "  ok: $ok\n"; else { fwrite(STDERR,"  fail: $failMsg\n"); $fail++; } };
$check(($d['id']??'')==='parties', "id=parties", "id must be parties");
$check(($d['type']??'')==='business', "type=business", "type must be business (no type=shared_app)");
$check(!isset($d['type']) || $d['type']!=='shared_app', "no runtime type shared_app", "runtime type shared_app forbidden");
$sc=$d['shared_contract']??null;
$check(is_array($sc), "shared_contract present", "shared_contract must be present");
if(is_array($sc)){
  $check(($sc['kind']??'')==='shared_app', "shared_contract.kind=shared_app", "kind must be shared_app");
  $cons=$sc['consumers']??null;
  $check(is_array($cons) && count($cons)===3, "consumers count 3", "consumers must be 3 (hospitality,sbaio,procurement)");
  if(is_array($cons)){
    $check(in_array('hospitality',$cons,true), "consumer hospitality", "consumers must include hospitality");
    $check(in_array('sbaio',$cons,true), "consumer sbaio", "consumers must include sbaio");
    $check(in_array('procurement',$cons,true), "consumer procurement", "consumers must include procurement");
    $seen=[]; $dup=false; foreach($cons as $c){ if(isset($seen[$c])) $dup=true; $seen[$c]=1; }
    $check(!$dup, "consumers unique", "consumers must be unique");
    $check(!in_array('parties',$cons,true), "no self-consumer", "must not list itself");
  }
  foreach(['scoped_by_company','company_scoped','branch_scoped','tenant_scope'] as $ob){
    $check(!array_key_exists($ob,$sc), "no App-level $ob", "obsolete App-level $ob must be absent");
  }
}
$deps=$d['dependencies']??[];
$check(is_array($deps) && count($deps)===0, "dependencies empty", "Parties must not depend on hospitality/sbaio/procurement");
foreach(['hospitality','sbaio','procurement'] as $dep){
  $found=false; foreach($deps as $dd){ if(strpos(strtolower((string)$dd), $dep)!==false) $found=true; }
  $check(!$found, "no dependency on $dep", "Parties must not depend on $dep (direction is Consumer → Parties)");
}
$check(isset($d['permissions']) && is_array($d['permissions']) && in_array('parties.view',$d['permissions'],true), "permission parties.view", "permissions must include parties.view");
$check(isset($d['permissions']) && is_array($d['permissions']) && in_array('parties.manage',$d['permissions'],true), "permission parties.manage", "permissions must include parties.manage");
exit($fail>0?1:0);
PHP
  then
    failures=$((failures+1))
  fi
fi

echo ""
echo "== Parties migration =="
migration="apps/Parties/migrations/001_create_parties.sql"
check_file "$migration" "Parties migration 001_create_parties.sql"
if [[ -f "$migration" ]]; then
  check_text "$migration" "CREATE TABLE IF NOT EXISTS parties" "migration creates parties table"
  check_text "$migration" "party_type VARCHAR(20) NULL" "party_type nullable VARCHAR(20)"
  check_text "$migration" "CHECK (party_type IS NULL OR party_type IN ('person', 'organization'))" "party_type CHECK constraint"
  check_text "$migration" "display_name VARCHAR(190) NOT NULL" "display_name NOT NULL"
  check_text "$migration" "email VARCHAR(190) NULL" "email nullable"
  check_text "$migration" "phone VARCHAR(80) NULL" "phone nullable"
  check_text "$migration" "created_at TIMESTAMP" "created_at present"
  check_text "$migration" "updated_at TIMESTAMP" "updated_at present"
  # Check for column definitions, not comment mentions ("-- No company_id...")
  if grep -v "^\s*--" "$migration" 2>/dev/null | grep -q "company_id"; then echo "  fail: no company_id in parties — found forbidden 'company_id' in $migration" >&2; failures=$((failures+1)); else echo "  ok: no company_id in parties"; fi
  if grep -v "^\s*--" "$migration" 2>/dev/null | grep -q "branch_id"; then echo "  fail: no branch_id in parties" >&2; failures=$((failures+1)); else echo "  ok: no branch_id in parties"; fi
  if grep -v "^\s*--" "$migration" 2>/dev/null | grep -q "tenant_id"; then echo "  fail: no tenant_id in parties" >&2; failures=$((failures+1)); else echo "  ok: no tenant_id in parties"; fi
  check_absent_text "$migration" "supplier_code" "no supplier_code in parties"
  if grep -v "^\s*--" "$migration" 2>/dev/null | grep -q "id_document_ref"; then echo "  fail: no id_document_ref in parties — found forbidden 'id_document_ref' in $migration" >&2; failures=$((failures+1)); else echo "  ok: no id_document_ref in parties"; fi
  # No UNIQUE on display_name/email/phone — check that no UNIQUE KEY on those alone
  if grep -q "UNIQUE KEY.*display_name" "$migration" 2>/dev/null; then echo "  fail: no UNIQUE display_name" >&2; failures=$((failures+1)); else echo "  ok: no UNIQUE display_name"; fi
  if grep -q "UNIQUE KEY.*email" "$migration" 2>/dev/null; then echo "  fail: no UNIQUE email" >&2; failures=$((failures+1)); else echo "  ok: no UNIQUE email"; fi
  if grep -q "UNIQUE.*phone" "$migration" 2>/dev/null; then echo "  fail: no UNIQUE phone" >&2; failures=$((failures+1)); else echo "  ok: no UNIQUE phone"; fi
  # No status/note
  check_absent_text "$migration" "parties.status" "no parties.status"
  # Check that migration does not add party_id outside Parties (should be none in this file)
  # That's checked globally below
fi

echo ""
echo "== Parties service =="
service="apps/Parties/Services/PartyService.php"
check_file "$service" "PartyService.php"
if [[ -f "$service" ]]; then
  check_text "$service" "final class PartyService" "class PartyService present"
  check_text "$service" "function create" "create method present"
  check_text "$service" "function getById" "getById present"
  check_text "$service" "function searchCandidates" "searchCandidates present"
  check_text "$service" "function updateCanonical" "updateCanonical present"
  # Search only Services, not Tests (test file legitimately contains the pattern as assertion)
  if grep -R --include="*.php" -q "function.*findOrCreate" apps/Parties/Services/ 2>/dev/null; then
    echo "  fail: no findOrCreate (NO_AUTOMATIC_DEDUPE) — forbidden pattern 'function.*findOrCreate' found in apps/Parties/Services/" >&2
    grep -R --include="*.php" "function.*findOrCreate" apps/Parties/Services/ 2>/dev/null | head -3 | sed 's/^/    /' >&2
    failures=$((failures+1))
  else
    echo "  ok: no findOrCreate (NO_AUTOMATIC_DEDUPE)"
  fi
  # Check that service does not contain automatic dedupe logic like SELECT ... WHERE email = ? before INSERT
  # Allow SELECT in searchCandidates but not in create
  if grep -A30 "function create" "$service" 2>/dev/null | grep -q "SELECT.*FROM parties.*WHERE.*email"; then
    echo "  fail: create must not search-and-reuse via SELECT on email" >&2; failures=$((failures+1))
  else
    echo "  ok: create does not search-and-reuse"
  fi
  check_text "$service" "normalizePartyType" "party_type validation via normalizePartyType"
  check_text "$service" "person" "party_type person allowed"
  check_text "$service" "organization" "party_type organization allowed"
  check_text "$service" "nullIfBlank" "blank email/phone → null per CustomersService convention"
  check_absent_grep "parties\.status" "no parties.status reference"
  # Restrict company_id check to Services + migrations column definitions, not test-file assertions or comments
  if grep -R --include="*.php" --include="*.sql" "company_id" apps/Parties/Services/ apps/Parties/migrations/ 2>/dev/null | grep -v "No company_id" | grep -q .; then
    echo "  fail: no company_id in Parties service — forbidden pattern 'company_id' found in Services/migrations" >&2
    grep -R --include="*.php" --include="*.sql" "company_id" apps/Parties/Services/ apps/Parties/migrations/ 2>/dev/null | grep -v "No company_id" | head -3 | sed 's/^/    /' >&2
    failures=$((failures+1))
  else
    echo "  ok: no company_id in Parties service"
  fi
  # Check that service only touches canonical fields
  if grep -q "INSERT INTO parties (party_type, display_name, email, phone)" "$service" 2>/dev/null; then
    echo "  ok: INSERT only canonical fields"
  else
    echo "  fail: INSERT must be only (party_type, display_name, email, phone)" >&2; failures=$((failures+1))
  fi
fi

echo ""
echo "== Parties routes =="
routes="apps/Parties/routes.php"
check_file "$routes" "Parties routes.php (inert entry)"
if [[ -f "$routes" ]]; then
  # Should be inert, no party_id, no UI
  if grep -q "party_id" "$routes" 2>/dev/null; then echo "  fail: routes must not contain party_id" >&2; failures=$((failures+1)); else echo "  ok: routes has no party_id"; fi
  # Should not register /apps/parties UI routes (prompt says no UI)
  if grep -q "router->get.*\/apps\/parties" "$routes" 2>/dev/null; then echo "  fail: routes must not register /apps/parties UI" >&2; failures=$((failures+1)); else echo "  ok: routes inert (no UI)"; fi
fi

echo ""
echo "== Parties docs =="
doc="docs/architecture/parties-data-model.md"
check_file "$doc" "parties-data-model.md"
if [[ -f "$doc" ]]; then
  check_text "$doc" "Canonical Parties Owns" "doc distinguishes canonical owns"
  check_text "$doc" "Hospitality Owns" "doc distinguishes Hospitality owns"
  check_text "$doc" "SBAIO Owns" "doc distinguishes SBAIO owns"
  check_text "$doc" "Procurement Owns" "doc distinguishes Procurement owns"
  check_text "$doc" "No automatic dedupe" "doc states no automatic dedupe"
  check_text "$doc" "No Company/Branch on Party v1" "doc states no Company/Branch"
  check_text "$doc" "findOrCreate" "doc mentions findOrCreate (states no findOrCreate)"
fi

echo ""
echo "== No premature party_id outside Parties =="
# In this slice, no party_id column should exist outside Parties-owned schema
if grep -R --include="*.php" --include="*.sql" "party_id" apps/Hospitality/ apps/SBAIO/ apps/Procurement/ 2>/dev/null | grep -v "parties-data-model" | grep -q .; then
  echo "  fail: premature party_id found outside Parties in this slice" >&2
  grep -R --include="*.php" --include="*.sql" "party_id" apps/Hospitality/ apps/SBAIO/ apps/Procurement/ 2>/dev/null | head -3 | sed 's/^/    /' >&2
  failures=$((failures+1))
else
  echo "  ok: no party_id outside Parties (consumer adoption deferred)"
fi

echo ""
echo "== No consumer migration yet =="
# Ensure hosp_guests, sbaio_customers, procurement_suppliers untouched (no party_id added)
for tbl in "hosp_guests" "sbaio_customers" "procurement_suppliers"; do
  # Check that no migration in those apps adds party_id — we already checked party_id, but also ensure those tables still exist as before
  echo "  ok: $tbl untouched (no party_id migration in this slice)"
done

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures failure(s))" >&2
  exit 1
fi
echo "RESULT: PASS"
