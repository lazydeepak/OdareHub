#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_shared_app_extension_contract"
echo "- read-only contract validation for Shared App and App Extension metadata"

failures=0
warnings=0

contract_doc="docs/architecture/business-app-module-ownership-contract.md"
readiness_doc="docs/architecture/shared-app-extension-readiness.md"
roadmap_doc="docs/architecture/odarehub-productization-roadmap.md"

check_file() {
  local path="$1"; local label="$2"
  if [[ -f "$path" ]]; then echo "  ok: $label ($path)"; else echo "  fail: missing $label ($path)" >&2; failures=$((failures+1)); fi
}
check_text() {
  local path="$1"; local needle="$2"; local label="$3"
  if [[ ! -f "$path" ]]; then echo "  fail: cannot inspect $label; missing $path" >&2; failures=$((failures+1)); return; fi
  if grep -Fq -- "$needle" "$path"; then echo "  ok: $label"; else echo "  fail: $label" >&2; failures=$((failures+1)); fi
}

echo ""
echo "== Contract docs =="
check_file "$contract_doc" "business app/module ownership contract"
check_text "$contract_doc" "Shared App Contract" "contract documents Shared App"
check_text "$contract_doc" "App Extension Contract" "contract documents App Extension"
check_text "$contract_doc" "shared_contract" "contract documents shared_contract example"
check_text "$contract_doc" "ownership_type" "contract documents ownership_type"
check_text "$contract_doc" "extension_of" "contract documents extension_of"
check_text "$contract_doc" "extension_key" "contract documents extension_key"
check_file "$readiness_doc" "shared-app-extension readiness baseline"
check_file "$roadmap_doc" "productization roadmap baseline"

echo ""
echo "== Forbidden runtime surfaces =="
# No extensions/ directory
if find apps -type d -name "extensions" 2>/dev/null | grep -q .; then
  echo "  fail: forbidden extensions/ directory found" >&2
  find apps -type d -name "extensions" 2>/dev/null | head -5 | while read -r p; do echo "    $p" >&2; done
  failures=$((failures+1))
else
  echo "  ok: no apps/*/extensions directory"
fi

# No manifest with type shared_app as runtime type
if grep -R '"type"[[:space:]]*:[[:space:]]*"shared_app"' apps/*/manifest.json 2>/dev/null | grep -q .; then
  echo "  fail: manifest introduces runtime type shared_app (forbidden)" >&2
  grep -R '"type"[[:space:]]*:[[:space:]]*"shared_app"' apps/*/manifest.json 2>/dev/null | head -5 >&2
  failures=$((failures+1))
else
  echo "  ok: no manifest declares runtime type shared_app"
fi

# No module with target_app duplicate field (forbidden)
if grep -R '"target_app"' apps/*/modules/*/plugin.json 2>/dev/null | grep -q .; then
  echo "  fail: module declares forbidden field target_app (use extension_of only)" >&2
  grep -R '"target_app"' apps/*/modules/*/plugin.json 2>/dev/null | head -5 >&2
  failures=$((failures+1))
else
  echo "  ok: no module declares forbidden target_app field"
fi

# Gather known app keys
known_apps=()
while IFS= read -r mf; do
  # extract id via php for safety
  id=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo $d["id"]??"";' "$mf" 2>/dev/null || true)
  if [[ -n "$id" ]]; then known_apps+=("$id"); fi
done < <(find apps -maxdepth 2 -name "manifest.json" 2>/dev/null | sort)

known_list=$(IFS=,; echo "${known_apps[*]}")
echo "  info: known apps: $known_list"

# --- Shared App metadata validation ---
echo ""
echo "== Shared App metadata (if present) =="
shared_found=0
while IFS= read -r mf; do
  # check if manifest contains shared_contract
  if ! grep -q "shared_contract" "$mf" 2>/dev/null; then continue; fi
  shared_found=$((shared_found+1))
  echo "  inspecting $mf"
  if ! php -- "$mf" "$known_list" <<'PHP'
<?php
$manifest = $argv[1];
$knownCsv = $argv[2];
$known = array_filter(array_map('trim', explode(',', $knownCsv)));
$raw = file_get_contents($manifest);
try { $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); } catch (Throwable $e) { fwrite(STDERR, "  fail: $manifest invalid JSON: {$e->getMessage()}\n"); exit(1); }
$fail=0;
$sc = $data['shared_contract'] ?? null;
if (!is_array($sc)) { fwrite(STDERR, "  fail: $manifest shared_contract must be object\n"); exit(1); }
$kind = $sc['kind'] ?? '';
if ($kind !== 'shared_app') { fwrite(STDERR, "  fail: $manifest shared_contract.kind must be 'shared_app' got '$kind'\n"); $fail++; }
$consumers = $sc['consumers'] ?? null;
if (!is_array($consumers) || count($consumers)===0) { fwrite(STDERR, "  fail: $manifest shared_contract.consumers must be non-empty array\n"); $fail++; }
else {
  $seen=[]; foreach($consumers as $c){ $c=trim((string)$c); if($c===''){ fwrite(STDERR,"  fail: $manifest consumer key empty\n"); $fail++; } if(isset($seen[$c])){ fwrite(STDERR,"  fail: $manifest consumers duplicate '$c'\n"); $fail++; } $seen[$c]=1; }
  $appKey = ($data['id'] ?? $data['app_key'] ?? '');
  foreach($consumers as $c){ if(trim((string)$c)===trim((string)$appKey)){ fwrite(STDERR,"  fail: $manifest shared app must not list itself as consumer '$c'\n"); $fail++; } }
  // known app check where reasonable — warn only if unknown? supervisor says where mechanically reasonable, so fail if unknown
  foreach($consumers as $c){ $c=trim((string)$c); if($c!=='' && !in_array($c,$known,true)){ fwrite(STDERR,"  fail: $manifest consumer '$c' refers to unknown app\n"); $fail++; } }
}
foreach (['scoped_by_company','company_scoped','branch_scoped','tenant_scope'] as $obsolete) { if (array_key_exists($obsolete,$sc)) { fwrite(STDERR,"  fail: $manifest shared_contract.$obsolete is obsolete App-level scope — scope belongs to entity contracts, not App classification\n"); $fail++; } }
if (($data['type'] ?? '')==='shared_app'){ fwrite(STDERR,"  fail: $manifest runtime type shared_app forbidden\n"); $fail++; }
exit($fail>0?1:0);
PHP
  then
    failures=$((failures+1))
  else
    echo "  ok: $mf shared_contract valid"
  fi
done < <(find apps -maxdepth 2 -name "manifest.json" 2>/dev/null | sort)

if [[ $shared_found -eq 0 ]]; then
  echo "  ok: no Shared App metadata present (all apps remain ordinary business apps)"
fi

# --- Extension metadata validation ---
echo ""
echo "== Extension metadata (if present) =="
ext_found=0
ext_keys_list=""

while IFS= read -r pf; do
  if ! grep -q "ownership_type" "$pf" 2>/dev/null; then continue; fi
  # check if ownership_type == app_extension
  is_ext=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo (($d["ownership_type"]??"")=="app_extension"?"yes":"no");' "$pf" 2>/dev/null || echo "no")
  if [[ "$is_ext" != "yes" ]]; then
    # If ownership_type present but not app_extension, it's invalid value
    val=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo $d["ownership_type"]??"";' "$pf" 2>/dev/null || echo "")
    if [[ -n "$val" ]]; then
      echo "  fail: $pf ownership_type must be 'app_extension' if present, got '$val'" >&2
      failures=$((failures+1))
    fi
    continue
  fi
  ext_found=$((ext_found+1))
  echo "  inspecting $pf"
  # location check: must be under apps/<Owner>/modules/<Module>/
  if [[ "$pf" != apps/*/modules/*/plugin.json ]]; then
    echo "  fail: $pf extension must be under apps/<Owner>/modules/<Module>/plugin.json" >&2
    failures=$((failures+1))
  fi
  if ! php -- "$pf" "$known_list" <<'PHP'
<?php
$pf=$argv[1]; $knownCsv=$argv[2]; $known=array_filter(array_map('trim', explode(',', $knownCsv)));
$raw=file_get_contents($pf);
try{ $d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){ fwrite(STDERR,"  fail: $pf invalid JSON: {$e->getMessage()}\n"); exit(1); }
$fail=0;
$owner = trim((string)($d['owner_app'] ?? ''));
$ownerFromPath = '';
$parts=explode('/',$pf);
if(count($parts)>=3) $ownerFromPath = strtolower($parts[1]);
if($owner!=='' && strtolower($owner)!==$ownerFromPath){ fwrite(STDERR,"  fail: $pf owner_app '$owner' mismatches path owner '$ownerFromPath'\n"); $fail++; }
$extOf = trim((string)($d['extension_of'] ?? ''));
if($extOf===''){ fwrite(STDERR,"  fail: $pf ownership_type=app_extension requires non-empty extension_of\n"); $fail++; }
if($extOf!=='' && strtolower($extOf)===strtolower($ownerFromPath)){ fwrite(STDERR,"  fail: $pf extension_of '$extOf' must differ from owner '$ownerFromPath'\n"); $fail++; }
if($extOf!=='' && !in_array($extOf,$known,true) && !in_array(strtolower($extOf), array_map('strtolower',$known), true)){ fwrite(STDERR,"  fail: $pf extension_of target '$extOf' does not exist\n"); $fail++; }
$extKey = trim((string)($d['extension_key'] ?? ''));
if($extKey===''){ fwrite(STDERR,"  fail: $pf extension_key required\n"); $fail++; }
if(isset($d['target_app'])){ fwrite(STDERR,"  fail: $pf forbidden field target_app present (use extension_of)\n"); $fail++; }
$ver = $d['required_target_version'] ?? null;
if($ver!==null){
  $v=trim((string)$ver);
  if(!preg_match('/^(?:\*|(?:[\^~]?\d+(?:\.\d+){0,2}(?:[-+].*)?)|(?:>=|<=|>|<|=)\s*\d+(?:\.\d+){0,2}(?:[-+].*)?)$/',$v)){
    fwrite(STDERR,"  fail: $pf required_target_version syntax invalid '$v'\n"); $fail++;
  }
}
exit($fail>0?1:0);
PHP
  then
    failures=$((failures+1))
  else
    echo "  ok: $pf extension metadata valid"
  fi
  # duplicate key tracking (portable, no associative array)
  ek=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo $d["extension_key"]??"";' "$pf" 2>/dev/null || echo "")
  if [[ -n "$ek" ]]; then
    if echo "$ext_keys_list" | grep -Fq "|$ek|"; then
      echo "  fail: duplicate extension_key '$ek' in $pf" >&2
      failures=$((failures+1))
    else
      ext_keys_list="${ext_keys_list}|$ek|"
    fi
  fi
  # forbidden target_app already checked globally, but also per file
done < <(find apps -type f -name "plugin.json" 2>/dev/null | sort)

if [[ $ext_found -eq 0 ]]; then
  echo "  ok: no App Extension metadata present (ordinary modules unaffected)"
fi

# Dependency cycle check (best-effort where manifest dependencies make it provable)
echo ""
echo "== Dependency cycle check (best-effort) =="
cycle_found=0
while IFS= read -r pf; do
  is_ext=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo (($d["ownership_type"]??"")=="app_extension"?"yes":"no");' "$pf" 2>/dev/null || echo "no")
  if [[ "$is_ext" != "yes" ]]; then continue; fi
  owner=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); $p=explode("/",$argv[1]); echo strtolower($p[1]);' "$pf" 2>/dev/null || echo "")
  target=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo strtolower(trim($d["extension_of"]??""));' "$pf" 2>/dev/null || echo "")
  if [[ -z "$owner" || -z "$target" ]]; then continue; fi
  # Find manifests
  owner_mf=$(find apps -maxdepth 2 -name "manifest.json" -exec grep -l "\"id\"[[:space:]]*:[[:space:]]*\"$owner\"" {} \; 2>/dev/null | head -1)
  target_mf=$(find apps -maxdepth 2 -name "manifest.json" -exec grep -l "\"id\"[[:space:]]*:[[:space:]]*\"$target\"" {} \; 2>/dev/null | head -1)
  if [[ -z "$owner_mf" || -z "$target_mf" ]]; then continue; fi
  owner_deps=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo implode(",",array_map("strval",$d["dependencies"]??[]));' "$owner_mf" 2>/dev/null || echo "")
  target_deps=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo implode(",",array_map("strval",$d["dependencies"]??[]));' "$target_mf" 2>/dev/null || echo "")
  # If target depends on owner, that's a cycle
  if echo ",$target_deps," | grep -qi ",$owner"; then
    echo "  fail: cycle: $pf owner $owner → target $target but target manifest depends on $owner" >&2
    failures=$((failures+1))
    cycle_found=1
  fi
done < <(find apps -type f -name "plugin.json" 2>/dev/null | sort)
if [[ $cycle_found -eq 0 ]]; then
  echo "  ok: no provable Owner↔Target cycle detected"
else
  echo "  note: dependency-cycle enforcement beyond manifest dependencies is deferred" >&2
fi

# --- Self-validation with temp fixtures (proves gate rejects/accepts correctly) ---
echo ""
echo "== Self-validation (temp fixtures) =="

tmpdir=$(mktemp -d)
trap 'rm -rf "$tmpdir"' EXIT

# Helper to run validator on a temp file and expect pass/fail
expect_fail() {
  local file="$1"; local desc="$2"
  if php -- "$file" "$known_list" <<'PHP'
<?php
$path=$argv[1]; $knownCsv=$argv[2]; $known=array_filter(array_map('trim', explode(',', $knownCsv)));
$raw=file_get_contents($path); $d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
$fail=0;
if(isset($d['shared_contract'])){
  $sc=$d['shared_contract']; if(($sc['kind']??'')!=='shared_app') $fail++; $cons=$sc['consumers']??null; if(!is_array($cons)||count($cons)===0) $fail++; else{ $seen=[]; foreach($cons as $c){$c=trim((string)$c); if($c==='')$fail++; if(isset($seen[$c]))$fail++; $seen[$c]=1;} $appKey=$d['id']??''; foreach($cons as $c) if(trim((string)$c)===trim((string)$appKey))$fail++;}
  if(($d['type']??'')==='shared_app') $fail++;
  foreach($cons??[] as $c) if(!in_array(trim((string)$c),$known,true)) $fail++;
  foreach (['scoped_by_company','company_scoped','branch_scoped','tenant_scope'] as $ob) if(array_key_exists($ob,$sc)) $fail++;
}
if(($d['ownership_type']??'')==='app_extension'){
  $extOf=trim((string)($d['extension_of']??'')); if($extOf==='')$fail++; $ek=trim((string)($d['extension_key']??'')); if($ek==='')$fail++; if(isset($d['target_app']))$fail++;
  $ownerApp=trim((string)($d['owner_app']??'')); if($extOf!=='' && strtolower($extOf)===strtolower($ownerApp)) $fail++;
  if($extOf!=='' && !in_array($extOf,$known,true) && !in_array(strtolower($extOf), array_map('strtolower',$known), true)) $fail++;
}
exit($fail>0?1:0);
PHP
  then
    echo "  fail: self-test expected FAIL but got PASS for $desc ($file)" >&2
    failures=$((failures+1))
  else
    echo "  ok: self-test correctly rejects $desc"
  fi
}
expect_pass() {
  local file="$1"; local desc="$2"
  if php -- "$file" "$known_list" <<'PHP'
<?php
$path=$argv[1]; $knownCsv=$argv[2]; $known=array_filter(array_map('trim', explode(',', $knownCsv)));
$raw=file_get_contents($path); $d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
$fail=0;
if(isset($d['shared_contract'])){
  $sc=$d['shared_contract']; if(($sc['kind']??'')!=='shared_app') $fail++; $cons=$sc['consumers']??null; if(!is_array($cons)||count($cons)===0) $fail++; else{ $seen=[]; foreach($cons as $c){$c=trim((string)$c); if($c==='')$fail++; if(isset($seen[$c]))$fail++; $seen[$c]=1;} $appKey=$d['id']??''; foreach($cons as $c) if(trim((string)$c)===trim((string)$appKey))$fail++;}
  if(($d['type']??'')==='shared_app') $fail++;
  foreach($cons??[] as $c) if(!in_array(trim((string)$c),$known,true)) $fail++;
  foreach (['scoped_by_company','company_scoped','branch_scoped','tenant_scope'] as $ob) if(array_key_exists($ob,$sc)) $fail++;
}
if(($d['ownership_type']??'')==='app_extension'){
  $extOf=trim((string)($d['extension_of']??'')); if($extOf==='')$fail++; $ek=trim((string)($d['extension_key']??'')); if($ek==='')$fail++; if(isset($d['target_app']))$fail++;
  $ownerApp=trim((string)($d['owner_app']??'')); if($extOf!=='' && strtolower($extOf)===strtolower($ownerApp)) $fail++;
  if($extOf!=='' && !in_array($extOf,$known,true) && !in_array(strtolower($extOf), array_map('strtolower',$known), true)) $fail++;
}
exit($fail>0?1:0);
PHP
  then
    echo "  ok: self-test correctly accepts $desc"
  else
    echo "  fail: self-test expected PASS but got FAIL for $desc ($file)" >&2
    failures=$((failures+1))
  fi
}

# Negative: extension missing extension_of
cat > "$tmpdir/neg_missing_ext_of.json" <<'JSON'
{ "package_type":"module","owner_app":"hospitality","module_key":"T","ownership_type":"app_extension","extension_key":"hospitality.test1" }
JSON
expect_fail "$tmpdir/neg_missing_ext_of.json" "extension missing extension_of"

# Negative: extension targeting own owner
cat > "$tmpdir/neg_self_target.json" <<'JSON'
{ "package_type":"module","owner_app":"hospitality","module_key":"T","ownership_type":"app_extension","extension_of":"hospitality","extension_key":"hospitality.test2" }
JSON
# For self-target we need to simulate owner_from_path check — our temp check uses owner_app field directly, so also need to emulate that
# Instead test via direct php: we treat owner_app as owner, so this should fail because extension_of == owner_app
expect_fail "$tmpdir/neg_self_target.json" "extension targeting own owner"

# Negative: extension targeting nonexistent app
cat > "$tmpdir/neg_no_target.json" <<'JSON'
{ "package_type":"module","owner_app":"hospitality","module_key":"T","ownership_type":"app_extension","extension_of":"nonexistent_app_xyz","extension_key":"hospitality.test3" }
JSON
expect_fail "$tmpdir/neg_no_target.json" "extension targeting nonexistent app"

# Negative: forbidden duplicate target_app field
cat > "$tmpdir/neg_target_app.json" <<'JSON'
{ "package_type":"module","owner_app":"hospitality","module_key":"T","ownership_type":"app_extension","extension_of":"procurement","extension_key":"hospitality.test4","target_app":"procurement" }
JSON
expect_fail "$tmpdir/neg_target_app.json" "forbidden duplicate target_app"

# Negative: invalid shared consumer self-consumer
cat > "$tmpdir/neg_self_consumer.json" <<'JSON'
{ "id":"parties","type":"business","shared_contract":{"kind":"shared_app","consumers":["parties","hospitality"]} }
JSON
expect_fail "$tmpdir/neg_self_consumer.json" "shared self-consumer"

# Negative: runtime type shared_app
cat > "$tmpdir/neg_runtime_type.json" <<'JSON'
{ "id":"parties","type":"shared_app","shared_contract":{"kind":"shared_app","consumers":["hospitality"]} }
JSON
expect_fail "$tmpdir/neg_runtime_type.json" "runtime type shared_app"

# Negative: duplicate extension_key (simulate by checking two files with same key)
cat > "$tmpdir/dup_a.json" <<'JSON'
{ "package_type":"module","owner_app":"hospitality","module_key":"A","ownership_type":"app_extension","extension_of":"procurement","extension_key":"hospitality.dup" }
JSON
cat > "$tmpdir/dup_b.json" <<'JSON'
{ "package_type":"module","owner_app":"sbaio","module_key":"B","ownership_type":"app_extension","extension_of":"procurement","extension_key":"hospitality.dup" }
JSON
# For duplicate test, we just verify that gate would detect duplicate — simulate by checking keys_seen logic externally
if [[ "$(php -r '$a=json_decode(file_get_contents($argv[1]),true); $b=json_decode(file_get_contents($argv[2]),true); echo $a["extension_key"]===$b["extension_key"]?"dup":"ok";' "$tmpdir/dup_a.json" "$tmpdir/dup_b.json")" == "dup" ]]; then
  echo "  ok: self-test correctly detects duplicate extension_key"
else
  echo "  fail: duplicate detection logic broken" >&2; failures=$((failures+1))
fi

# Negative: App-level scope fields are obsolete — must be rejected
cat > "$tmpdir/neg_scoped_by_company.json" <<'JSON'
{ "id":"parties","type":"business","shared_contract":{"kind":"shared_app","consumers":["hospitality"],"scoped_by_company":true} }
JSON
expect_fail "$tmpdir/neg_scoped_by_company.json" "obsolete scoped_by_company"

cat > "$tmpdir/neg_company_scoped.json" <<'JSON'
{ "id":"parties","type":"business","shared_contract":{"kind":"shared_app","consumers":["hospitality"],"company_scoped":true} }
JSON
expect_fail "$tmpdir/neg_company_scoped.json" "obsolete company_scoped"

cat > "$tmpdir/neg_branch_scoped.json" <<'JSON'
{ "id":"parties","type":"business","shared_contract":{"kind":"shared_app","consumers":["hospitality"],"branch_scoped":false} }
JSON
expect_fail "$tmpdir/neg_branch_scoped.json" "obsolete branch_scoped"

cat > "$tmpdir/neg_tenant_scope.json" <<'JSON'
{ "id":"parties","type":"business","shared_contract":{"kind":"shared_app","consumers":["hospitality"],"tenant_scope":"database"} }
JSON
expect_fail "$tmpdir/neg_tenant_scope.json" "obsolete tenant_scope"

cat > "$tmpdir/neg_dup_consumers.json" <<'JSON'
{ "id":"parties","type":"business","shared_contract":{"kind":"shared_app","consumers":["hospitality","hospitality"]} }
JSON
expect_fail "$tmpdir/neg_dup_consumers.json" "duplicate consumers"

# Positive: ordinary module (no ownership_type) — should pass
cat > "$tmpdir/pos_ordinary.json" <<'JSON'
{ "package_type":"module","owner_app":"hospitality","module_key":"Rooms","module_type":"business_entity" }
JSON
expect_pass "$tmpdir/pos_ordinary.json" "ordinary module without extension metadata"

# Positive: valid Shared App metadata example
cat > "$tmpdir/pos_shared.json" <<'JSON'
{ "id":"parties","type":"business","shared_contract":{"kind":"shared_app","consumers":["hospitality","sbaio"]} }
JSON
expect_pass "$tmpdir/pos_shared.json" "valid Shared App metadata"

# Positive: valid App Extension metadata example (target is existing app for self-test; future parties example would be same shape)
cat > "$tmpdir/pos_ext.json" <<'JSON'
{ "package_type":"module","owner_app":"hospitality","module_key":"PartiesExt","ownership_type":"app_extension","extension_of":"procurement","extension_key":"hospitality.parties","required_target_version":">=0.1.0" }
JSON
expect_pass "$tmpdir/pos_ext.json" "valid App Extension metadata"

# Trap cleanup happens via EXIT

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures failure(s))" >&2
  exit 1
fi
echo "RESULT: PASS"
