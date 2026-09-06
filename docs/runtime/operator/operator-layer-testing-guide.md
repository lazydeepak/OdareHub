# Operator Layer Testing Guide

**Version**: 1.0  
**Purpose**: Comprehensive testing procedures for operator layer (`/u/*`) routes and adapters  
**Audience**: QA engineers, developers, operators

---

## Test Environment Setup

### Prerequisites

```bash
# 1. Start PHP dev server
cd /Users/lazydeepak/sbaio
/bin/bash scripts/runtime/start_dev_router.sh > /tmp/sbaio-php-server.log 2>&1 &

# 1b. Start operator realtime daemon when validating live updates
/bin/bash scripts/runtime/start_operator_realtime.sh > /tmp/sbaio-operator-realtime.log 2>&1 &

# 2. MySQL connection ready
mysql -h 127.0.0.1 -u root erp_local

# 3. Test user credentials
email: lazydeepak@outlook.com
password: Password123!
```

Use the router-backed launcher only. Starting the PHP server with plain `-t public` or `public/index.php` bypasses the app-scoped CSS passthrough and causes `/assets/apps/*` styles to return 404. For browser-level realtime validation, the websocket daemon must also be running on `127.0.0.1:8001`.

### Test Data Requirements

- ✅ Active user with operator role assigned
- ✅ Manufacturing app assigned to user
- ✅ At least 1 QC entry for today
- ✅ At least 1 machine job for today
- ✅ At least 1 assembly entry for today
- ✅ At least 1 material in inventory

---

## Smoke Tests (All Routes)

### Objective
Verify all operator routes return HTTP 200 with valid session.

### Procedure

```bash
#!/bin/bash

# 1. Setup session
jar=$(mktemp /tmp/operator-smoke-XXXX.cookie)
php_hash=$(/opt/homebrew/bin/php -r "echo password_hash('Password123!', PASSWORD_DEFAULT);")
mysql -h 127.0.0.1 -u root erp_local -e "UPDATE users SET password_hash='$php_hash', twofa_enabled=0 WHERE email='lazydeepak@outlook.com' LIMIT 1;"

# 2. Login
login_page=$(curl -sS -c "$jar" http://localhost:8000/login)
csrf=$(echo "$login_page" | grep -o 'name="csrf" value="[^"]*"' | head -n1 | sed 's/name="csrf" value="//;s/"$//')
curl -sS -L -c "$jar" -b "$jar" --data-urlencode "csrf=$csrf" --data-urlencode "email=lazydeepak@outlook.com" --data-urlencode "password=Password123!" http://localhost:8000/login > /dev/null

# 3. Test all routes
echo "=== Operator Layer Smoke Tests ==="
routes=(
  "dashboard"
  "work-entry"
  "critical"
  "recent"
  "parts"
  "production"
  "processing"
  "preparation"
  "dispatch"
  "coverage"
  "qc"
  "machines"
  "assembly"
  "materials"
  "account"
)

for route in "${routes[@]}"; do
  code=$(curl -sS -o /dev/null -w "%{http_code}" -L -b "$jar" "http://localhost:8000/u/$route")
  if [ "$code" = "200" ]; then
    echo "✅ /u/$route: $code"
  else
    echo "❌ /u/$route: $code"
  fi
done

rm -f "$jar"
```

### Expected Results
- ✅ All routes return **HTTP 200**
- ✅ Page renders without errors
- ✅ Sidebar visible with Manufacturing section

### Realtime Smoke Test

Verify the Phase 8 websocket path independently of the browser shell.

```bash
cd /Users/lazydeepak/sbaio
/bin/zsh scripts/runtime/operator_realtime_smoke.sh
```

### Expected Realtime Results
- ✅ The script prints a `connected` message
- ✅ The script prints a `subscribed` message
- ✅ The script prints a `kpi_update` message
- ✅ The script exits with `operator realtime smoke test passed`

---

## Adapter Data Tests

### Test 1: QcAdapter

**File**: `apps/Shell/Services/OperatorLayerAdapters/QcAdapter.php`

```php
<?php
// Unit test for QcAdapter
$data = \Apps\Shell\Services\OperatorLayerAdapters\QcAdapter::getData();

assert(is_array($data), "QcAdapter::getData() must return array");
assert(isset($data['kpi']), "Must have 'kpi' key");
assert(isset($data['run_now']), "Must have 'run_now' key");
assert(isset($data['empty']), "Must have 'empty' key");
assert(isset($data['error']), "Must have 'error' key");
assert(is_bool($data['empty']), "'empty' must be boolean");
assert(is_string($data['error']), "'error' must be string");

if (!$data['empty'] && $data['error'] === '') {
    assert(is_array($data['kpi']), "'kpi' must be array");
    assert(is_array($data['run_now']), "'run_now' must be array");
    assert(isset($data['kpi']['urgent']), "KPI must have 'urgent'");
    echo "✅ QcAdapter data structure valid";
} else {
    echo "⚠️  QcAdapter empty or error state";
}
?>
```

**Manual Test**:
1. Go to `/u/qc`
2. Check page loads (no 500 error)
3. Verify KPI strip displays numbers
4. Verify "Run Now" table shows data or "No entries" message
5. Check page source for no hardcoded strings (all via `$this->tr()`)

### Test 2: DispatchAdapter

```php
$data = \Apps\Shell\Services\OperatorLayerAdapters\DispatchAdapter::getData();
assert(isset($data['kpi']['ready_now']), "KPI must have 'ready_now'");
assert(isset($data['ready_now']), "Must have 'ready_now' table");
assert(isset($data['blocked_hold']), "Must have 'blocked_hold' table");
```

**Manual Test**:
1. Go to `/u/machines`
2. Check KPI shows Running, Queue, Delayed counts
3. Verify machine names display correctly
4. Check tables show or "No entries" message

### Test 3: MachinesAdapter

**Manual Test**:
1. Go to `/u/machines`
2. Check all 6 KPI cards present (Running, Queue, Delayed, QC-waiting, Planned, Produced)
3. Verify numbers are numeric (not NaN or error text)
4. Check Currently Running table

### Test 4: AssemblyAdapter

**Manual Test**:
1. Go to `/u/assembly`
2. Check 6 KPI cards (Total, In Progress, Completed, Blocked, Planned, Completed Qty)
3. Verify today's date displays in header
4. Check Today's Entries table

### Test 5: MaterialsAdapter

**Manual Test**:
1. Go to `/u/materials`
2. Check 5 KPI cards (Total, Low Stock, Zero, Critical, Open Orders)
3. Verify Critical Shortage table (should be empty or show low-stock materials)
4. Verify Low Stock table

### Test 6: CoverageAdapter

**Manual Test**:
1. Go to `/u/coverage`
2. Verify KPI cards (Open, Critical, Low Coverage, Avg %, Due Today)
3. Check risk-band doughnut chart renders (or no JS error)
4. Check demand-window bar chart
5. Check Critical Orders table

---

## Localization Tests

### English (en)

```bash
# Test with English locale
curl -sS -b session.cookie http://localhost:8000/u/qc | grep -i "quality control\|run now\|pending"
```

**Expected**: English labels visible

### Japanese (ja)

```bash
# Change user locale to ja
mysql -h 127.0.0.1 -u root erp_local -e "UPDATE users SET locale='ja' WHERE email='lazydeepak@outlook.com';"

# Test Japanese labels
curl -sS -b session.cookie http://localhost:8000/u/qc | grep "品質管理\|今すぐ実行"
```

**Expected**: Japanese labels visible (品質管理, etc.)

### Nepali (ne)

```bash
# Change user locale to ne
mysql -h 127.0.0.1 -u root erp_local -e "UPDATE users SET locale='ne' WHERE email='lazydeepak@outlook.com';"

# Test Nepali labels
curl -sS -b session.cookie http://localhost:8000/u/qc | grep "गुणस्तर"
```

**Expected**: Nepali labels visible

### Localization Checklist

- ✅ No hardcoded English in operator views (search for "Quality", "Running", "Machines", etc.)
- ✅ All labels use `$this->tr()` function
- ✅ Locale keys exist in all 3 language files (en, ja, ne)
- ✅ Fallback text provided in `$this->tr()` calls
- ✅ Page renders correctly in each language

---

## Mobile Responsiveness Tests

### Desktop (>1024px)

```bash
# Simulate desktop (1920x1080)
# 1. Open Chrome DevTools
# 2. Ctrl+Shift+M to toggle device toolbar
# 3. Select "Desktop"
```

**Checklist**:
- ✅ Sidebar visible (not collapsed)
- ✅ Main content area wide
- ✅ KPI cards in row (not stacked)
- ✅ Tables horizontal scroll (not required)

### Tablet (768-1024px)

**Checklist**:
- ✅ Sidebar collapsible (hamburger menu)
- ✅ KPI cards in 2-3 column grid
- ✅ Tables remain readable (font size OK)
- ✅ Touch-friendly tap targets (44px minimum)

### Mobile (<768px)

**Checklist**:
- ✅ Sidebar becomes drawer (hamburger menu)
- ✅ KPI cards stack vertically (1 column)
- ✅ Tables show card view or collapse
- ✅ No horizontal overflow
- ✅ Buttons are 44px minimum
- ✅ Font size readable (16px minimum)

### Real Device Tests

Test on actual phones/tablets:
- ✅ iPhone 12 (iOS Safari)
- ✅ Android device (Chrome)
- ✅ Tablet (iPad)

---

## Security Tests

### Session Validation

```bash
# Test 1: No session = redirect to login
curl -sS -o /dev/null -w "%{http_code}" http://localhost:8000/u/qc
# Expected: 302 (redirect to login)

# Test 2: Valid session = HTTP 200
jar=$(mktemp)
# ... login ...
curl -sS -o /dev/null -w "%{http_code}" -b "$jar" http://localhost:8000/u/qc
# Expected: 200
```

### XSS Prevention

```bash
# Test: No raw HTML in output (all through htmlspecialchars)
curl -sS -b session.cookie http://localhost:8000/u/qc | grep -E '<script[^>]*>|javascript:|onerror=' | wc -l
# Expected: 0 lines (no inline scripts)
```

### SQL Injection

```bash
# Test: Adapter queries use parameterized statements
grep -r "SELECT.*\$\|DB::query(" apps/Shell/Services/OperatorLayerAdapters/ | grep -v "DB::fetch\|:bind\|?"
# Expected: 0 results (all queries are parameterized)
```

---

## Performance Tests

### Page Load Time

```bash
# Measure full page load time
time curl -sS -b session.cookie -o /dev/null http://localhost:8000/u/qc
# Expected: < 1 second (including DB queries)
```

### Query Count

```bash
# Enable query logging in app config
// app/Services/DB.php - add query count tracking

// After page load, check query count
echo $queryCount;
# Expected: < 10 queries per page load
```

### Memory Usage

```bash
# Check PHP memory usage
echo "Memory used: " . (memory_get_usage(true) / 1024 / 1024) . " MB";
# Expected: < 20 MB per request
```

---

## Error Handling Tests

### Database Down

```bash
# 1. Stop MySQL
sudo systemctl stop mysql

# 2. Access operator route
curl -sS -b session.cookie http://localhost:8000/u/qc

# Expected: Graceful error message (not 500)
# Or empty state with "Error loading data" message

# 3. Restart MySQL
sudo systemctl start mysql
```

### Null Query Results

```bash
# 1. Create new user with no assignments
mysql -h 127.0.0.1 -u root erp_local -e "INSERT INTO users (email, name, password_hash) VALUES ('test@example.com', 'Test', 'hash');"

# 2. Access operator routes
# Expected: Empty state message ("No QC entries found for today.")
```

### Malformed Date Filter

```bash
# Test invalid date parameter
curl -sS -b session.cookie "http://localhost:8000/u/qc?qc_date=invalid"
# Expected: Uses today's date (not error)

# Test past date
curl -sS -b session.cookie "http://localhost:8000/u/qc?qc_date=2020-01-01"
# Expected: Shows empty state or past data
```

---

## Regression Tests

### Verify Core Shell Intact

After operator changes, verify:

```bash
# 1. Sidebar renders correctly
curl -sS -b session.cookie http://localhost:8000/u/dashboard | grep -c "sidebar\|Manufacturing"

# 2. Header renders correctly
curl -sS -b session.cookie http://localhost:8000/u/dashboard | grep -c "header\|company"

# 3. Footer renders correctly
curl -sS -b session.cookie http://localhost:8000/u/dashboard | grep -c "footer"

# 4. Theme applies (check for CSS variables)
curl -sS http://localhost:8000/assets/app.css | grep -c "var(--"
```

### Verify Admin Layer Untouched

```bash
# Admin layer should still work
curl -sS -b session.cookie -o /dev/null -w "%{http_code}" http://localhost:8000/apps/manufacturing/coverage
# Expected: 200

curl -sS -b session.cookie -o /dev/null -w "%{http_code}" http://localhost:8000/apps/manufacturing/qc-entries
# Expected: 200
```

---

## Integration Tests

### End-to-End User Journey

```
1. Login as lazydeepak@outlook.com
   → /u/dashboard (home)
   → Check critical items card
   
2. Navigate to /u/qc
   → Verify KPI strip loads
   → Check "Run Now" table has data
   → Verify "Full QC Log" link works (opens new tab)
   
3. Navigate to /u/materials
   → Verify 5 KPI cards
   → Check critical shortage alert (if any)
   → Verify links stay within /u/* namespace
   
4. Search for part in header search
   → Results load
   → Click result → goes to /u/parts/detail
   → Back button → returns to previous page
   
5. Logout
   → Redirects to login page
```

---

## Acceptance Criteria

| Criteria | Status |
|----------|--------|
| All 15 routes return 200 | ✅ Pass |
| All adapters return valid data | ✅ Pass |
| All locale keys present (en/ja/ne) | ✅ Pass |
| Mobile responsive (<768px) | ✅ Pass |
| No hardcoded strings in views | ✅ Pass |
| No XSS vulnerabilities | ✅ Pass |
| No SQL injection vulnerabilities | ✅ Pass |
| Graceful error handling | ✅ Pass |
| Page load < 1 second | ✅ Pass |
| No CSS/JS console errors | ✅ Pass |
| Admin layer regression-free | ✅ Pass |

---

## Test Results Template

```markdown
## Operator Layer Test Run — [Date]

**Tester**: [Name]  
**Test Environment**: [Dev/Staging/Prod]  
**Browser**: [Chrome/Firefox/Safari] v[Version]  
**Device**: [Desktop/Tablet/Mobile]  

### Smoke Tests
- [ ] All routes return 200
- [ ] Login → Operator workspace flow works
- [ ] Sidebar displays correctly

### Adapter Tests
- [ ] QC: KPI strip loads, tables display
- [ ] Dispatch: Ready/Blocked counts correct
- [ ] Machines: All 6 KPI cards present
- [ ] Assembly: Today's entries display
- [ ] Materials: Stock counts accurate
- [ ] Coverage: Charts render (if JS available)

### Localization
- [ ] English labels display correctly
- [ ] Japanese labels display correctly
- [ ] Nepali labels display correctly
- [ ] No fallback text visible (all translations present)

### Mobile
- [ ] Desktop (>1024px): Full layout
- [ ] Tablet (768px): Sidebar collapse works
- [ ] Mobile (<768px): Vertical stack, no overflow

### Security
- [ ] No session = Redirect to login
- [ ] Valid session = Access granted
- [ ] No XSS (no console errors)
- [ ] No SQL injection (parameterized queries)

### Performance
- [ ] Page load < 1 second
- [ ] No console errors
- [ ] Memory usage < 20MB

### Results
**PASS** ✅ / **FAIL** ❌

**Issues Found**:
1. [Issue 1]
2. [Issue 2]

**Sign-off**: [Tester] on [Date]
```

---

**End of Testing Guide**
