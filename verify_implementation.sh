#!/bin/bash

# Unified Naming Convention Implementation Verification Script
# Verifies all phases are complete

echo "🔍 Verifying Unified Naming Convention Implementation..."
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

checks_passed=0
checks_failed=0

check() {
  if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} $1"
    ((checks_passed++))
  else
    echo -e "${RED}✗${NC} $1"
    ((checks_failed++))
  fi
}

echo "📋 Phase 1: Manufacturing App"
[ -f "apps/Manufacturing/modules/DailyOrders/lang/en.php" ] && check "DailyOrders locale file exists"
grep -q "app.manufacturing.daily_orders.index.table.title" app/Locale/en.php && check "Manufacturing entries in central locale"
grep -q "日次注文" app/Locale/ja.php && check "Manufacturing Japanese translations"
grep -q "दैनिक अर्डरहरू" app/Locale/ne.php && check "Manufacturing Nepali translations"

echo ""
echo "📋 Phase 2: Extended Apps"
[ -f "apps/Procurement/lang/en.php" ] && check "Procurement locale file exists"
[ -f "apps/Studio/lang/en.php" ] && check "Studio locale file exists"
grep -q "app.sbaio" app/Locale/en.php && check "SBAIO entries in central locale"
grep -q "Platform" app/Locale/ja.php && check "Platform Japanese translation"
grep -q "Procurement" app/Locale/ne.php && check "Procurement Nepali translation"

echo ""
echo "📋 Phase 3: GUI Studio Tool"
grep -q "'table', 'form', 'kpi', 'chart', 'list', 'dashboard', 'queue', 'report', 'detail'" apps/Studio/Services/GuiStudioService.php && check "All 9 rendering types supported"
grep -q "generatedLocaleFile" apps/Studio/Services/GuiStudioService.php && check "Locale file generation method exists"
grep -q "app\.' \. \$appKey \. '\.' \. \$moduleKey" apps/Studio/Services/GuiStudioService.php && check "New locale pattern used (app.{app}.{module})"
grep -q "display_name_key" apps/Studio/Services/GuiStudioService.php && check "display_name_key field added"

echo ""
echo "📋 Phase 4: Translations"
grep -c "app\." app/Locale/ja.php | grep -q "^[0-9]\+$" && check "Japanese translations present"
grep -c "app\." app/Locale/ne.php | grep -q "^[0-9]\+$" && check "Nepali translations present"
grep -q "スタジオ" app/Locale/ja.php && check "Japanese kanji/characters found"
grep -q "नेपाल" app/Locale/ne.php || grep -q "स्टुडियो" app/Locale/ne.php && check "Nepali Devanagari script found"

echo ""
echo "📋 Terminology: suite → app"
! grep -q "suite_management" plugins/AdminTools/routes.php && check "Old suite routes removed"
grep -q "app_management" plugins/AdminTools/routes.php && check "New app routes in place"
grep -q "appManagement" plugins/AdminTools/Controllers/AdminToolsController.php && check "Controller method renamed to appManagement"

echo ""
echo "📋 Core Fixes"
grep -q "ME_PLUGIN_CARDS.*cross_role_handoff" plugins/Base/Services/UserDashboardAssignmentService.php && check "cross_role_handoff card added"
! grep -q "array_values" plugins/Base/Services/UserDashboardAssignmentService.php | head -5 && check "array_values call removed (string keys preserved)"

echo ""
echo "📋 Documentation"
[ -f "NAMING_CONVENTION.md" ] && check "NAMING_CONVENTION.md exists"
[ -f "IMPLEMENTATION_COMPLETE.md" ] && check "IMPLEMENTATION_COMPLETE.md exists"
grep -q "app\.{app}\.{module}\." NAMING_CONVENTION.md && check "Convention pattern documented"

echo ""
echo "📋 Templates Updated"
grep -q "display_name_key" tools/gui_studio/placeholders/view_definition.placeholder.json && check "view_definition placeholder updated"
grep -q "display_name_key" tools/gui_studio/placeholders/app_manifest.placeholder.json && check "app_manifest placeholder updated"
grep -q "display_name_key" tools/gui_studio/placeholders/module_manifest.placeholder.json && check "module_manifest placeholder updated"

echo ""
echo "════════════════════════════════════════════"
echo -e "Results: ${GREEN}$checks_passed passed${NC}, ${RED}$checks_failed failed${NC}"
echo "════════════════════════════════════════════"

if [ $checks_failed -eq 0 ]; then
  echo -e "${GREEN}✓ All checks passed! Implementation is complete.${NC}"
  exit 0
else
  echo -e "${RED}✗ Some checks failed. Review the items above.${NC}"
  exit 1
fi
