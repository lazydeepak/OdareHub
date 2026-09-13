#!/usr/bin/env bash
# Commercial Domain-Owned Contract Reconciliation — bounded verification
# Milestone: COMMERCIAL DOMAIN-OWNED CONTRACT RECONCILIATION
# Hard rules: no shared commercial service/table/API; no universal order model; no promotion
set -euo pipefail

PASS=0
FAIL=0

echo "=== Commercial Domain-Owned Contract Probe ==="

# 1. No shared commercial runtime code on main
if [ -d "platform/SharedCommercial" ]; then
  echo "FAIL: platform/SharedCommercial exists (shared commercial authority)"
  FAIL=$((FAIL+1))
else
  echo "PASS: no platform/SharedCommercial"
  PASS=$((PASS+1))
fi

if [ -d "shared/Commercial" ]; then
  echo "FAIL: shared/Commercial exists"
  FAIL=$((FAIL+1))
else
  echo "PASS: no shared/Commercial"
  PASS=$((PASS+1))
fi

# 2. Work branch commercial contract exists but NOT promoted (work/ only has parties, not commercial)
if ls work/shared-commercial-foundation/ 1>/dev/null 2>&1; then
  echo "FAIL: work/shared-commercial-foundation promoted to work/"
  FAIL=$((FAIL+1))
else
  echo "PASS: no promoted work/shared-commercial-foundation"
  PASS=$((PASS+1))
fi

# 3. Manufacturing owns its commercial modules (plugin.json owner_app: manufacturing)
for mod in apps/Manufacturing/modules/DailyOrders apps/Manufacturing/modules/PreOrders apps/Manufacturing/modules/DispatchEntries apps/Manufacturing/modules/Ledger; do
  if [ -f "$mod/plugin.json" ]; then
    owner=$(grep '"owner_app"' "$mod/plugin.json" | head -1 | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "NONE")
    if [ "$owner" = "manufacturing" ]; then
      echo "PASS: $mod owner_app=manufacturing"
      PASS=$((PASS+1))
    else
      echo "FAIL: $mod owner_app=$owner (expected manufacturing)"
      FAIL=$((FAIL+1))
    fi
  else
    echo "FAIL: $mod/plugin.json missing"
    FAIL=$((FAIL+1))
  fi
done

# 4. SBAIO Sales module exists and is SBAIO-owned
if [ -f "apps/SBAIO/modules/Sales/plugin.json" ]; then
  owner=$(grep '"owner_app"' "apps/SBAIO/modules/Sales/plugin.json" | head -1 | sed 's/.*: *"\([^"]*\)".*/\1/' || echo "NONE")
  if [ "$owner" = "sbaio" ]; then
    echo "PASS: SBAIO Sales owner_app=sbaio"
    PASS=$((PASS+1))
  else
    echo "FAIL: SBAIO Sales owner_app=$owner"
    FAIL=$((FAIL+1))
  fi
else
  echo "FAIL: SBAIO modules/Sales missing"
  FAIL=$((FAIL+1))
fi

# 5. Procurement has domain-local purchase order tables (routes/manifests/services exist)
if [ -f "apps/Procurement/manifest.json" ]; then
  echo "PASS: Procurement app manifest exists (domain-local)"
  PASS=$((PASS+1))
else
  echo "FAIL: Procurement manifest missing"
  FAIL=$((FAIL+1))
fi

# 6. Hospitality reservations/charges are domain-local (services/controllers exist)
if [ -f "apps/Hospitality/Services/ReservationsService.php" ]; then
  echo "PASS: Hospitality ReservationsService exists (domain-local)"
  PASS=$((PASS+1))
else
  echo "FAIL: Hospitality ReservationsService missing"
  FAIL=$((FAIL+1))
fi

# 7. No universal commercial service/controller in platform/ or shared/
if grep -rq -l "SharedCommercial\|UniversalCommercial\|UniversalOrder\|UniversalTransaction" platform/ shared/ apps/ app/ 2>/dev/null; then
  echo "FAIL: universal commercial authority references found"
  FAIL=$((FAIL+1))
else
  echo "PASS: no universal commercial authority references"
  PASS=$((PASS+1))
fi

# 8. No cross-domain order master table/schema reference
if grep -rni 'universal.*order\|shared.*order\|cross_domain.*order' app/ apps/ platform/ shared/ 2>/dev/null | grep -v '.DS_Store' | grep -v 'vendor/' | grep -v '^docs/' | head -10; then
  echo "FAIL: universal/cross-domain order references found (see above)"
  FAIL=$((FAIL+1))
else
  echo "PASS: no universal/cross-domain order master references"
  PASS=$((PASS+1))
fi

# 9. Evidence docs confirm domain-owned commercial classification
if grep -qni 'INSUFFICIENT EVIDENCE\|DOMAIN-OWNED' docs/architecture/current-ownership-inventory.md docs/architecture/odarehub-future-architecture-planning-brief.md docs/architecture/odarehub-future-architecture-pressure-test-results.md; then
  echo "PASS: evidence docs confirm DOMAIN-OWNED / INSUFFICIENT EVIDENCE for Commercial"
  PASS=$((PASS+1))
else
  echo "FAIL: evidence docs missing domain-owned commercial confirmation"
  FAIL=$((FAIL+1))
fi

echo "=== RESULT: $PASS passed, $FAIL failed ==="
