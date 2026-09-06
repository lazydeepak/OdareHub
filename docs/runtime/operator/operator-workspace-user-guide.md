# Operator Workspace Quick Reference

**For**: Manufacturing operators using the `/u` workspace  
**Updated**: May 2026

---

## Getting Started

### Login
1. Navigate to `http://localhost:8000/login`
2. Enter email and password
3. You'll land on your operator dashboard

### Your Workspace URL
```
http://localhost:8000/u/dashboard
http://localhost:8000/u/{your_name}/dashboard
```

Replace `{your_name}` with your operator name/username.

---

## Main Views

### 📊 Dashboard (`/u/dashboard`)
Your operator home page with:
- **KPI summary**: Today's critical metrics
- **Recent activity**: Latest updates
- **Critical items**: Issues requiring attention
- **Quick shortcuts**: Pinned views

### 📋 Critical Items (`/u/critical`)
Shows all urgent/critical items across manufacturing that need immediate attention.

### 🔍 Recent (`/u/recent`)
Your activity log — recent entries, updates, completed tasks.

### 📦 Parts (`/u/parts`)
Product/parts inventory lookup with search and filter.

---

## Manufacturing Execution

### 🏭 Production (`/u/production`)
Today's production plans and execution status:
- Planned quantity
- Current progress
- Issues/delays
- Next steps

### ⚙️ Processing (`/u/processing`)
Processing queue and status:
- Items ready for processing
- In-progress items
- Completed today
- Blocked items

### 📋 Preparation (`/u/preparation`)
Preparation readiness view:
- Items ready to dispatch
- Pending preparation
- Issues blocking dispatch

### 🚚 Dispatch (`/u/dispatch`)
Dispatch execution and tracking:
- Ready to ship
- In-transit tracking
- Destinations
- Driver/truck assignments

### 📍 Dispatch Detail (`/u/dispatch`)
Detailed shipment tracking with:
- Route details
- Estimated delivery
- Signature status

---

## Phase 4: Manufacturing Dashboards

### 🔬 Quality Control (`/u/qc`)
QC status and test results:
- **Urgent**: Items failing QC
- **Run Now**: Ready for testing
- **Pending**: Awaiting QC
- **Failed/Recheck**: Items that failed, ready for recheck
- **Ready to Dispatch**: Approved items ready to ship
- **Overdue**: Items past QC deadline

**Quick Actions**:
- Click "Full QC Log" to see complete history
- View failed items to debug root cause

### ⚙️ Machines (`/u/machines`)
Machine workboard and job tracking:
- **Running**: Currently active jobs
- **Next in Queue**: Queued jobs
- **Delayed**: Jobs past deadline
- **Waiting QC**: Jobs pending quality check
- **Planned Qty**: Today's target
- **Produced**: Completed today

**Quick Actions**:
- Click "Full Machines View" for detailed machine status
- Monitor delayed jobs to prevent backlog

### 🔩 Assembly (`/u/assembly`)
Assembly execution and progress:
- **Today's Total**: All assembly jobs for today
- **In Progress**: Currently being assembled
- **Completed**: Finished jobs today
- **Blocked**: Items that can't proceed
- **Planned Qty**: Target quantity
- **Completed Qty**: Actual completion

**Quick Actions**:
- View today's entries in detail table
- Identify bottlenecks in assembly line

### 📦 Materials (`/u/materials`)
Material inventory and supply status:
- **Total Materials**: All materials in system
- **Low Stock**: Below reorder point
- **Zero Stock**: Out of stock
- **Critical Shortage**: Negative inventory
- **Open Orders**: Outstanding purchase orders

**Quick Actions**:
- Click "Full Materials View" for detailed stock analysis
- Check critical shortage list to prevent production halt
- Monitor open orders for delivery status

### 📊 Coverage (`/u/coverage`)
Demand/supply coverage analysis:
- **Coverage %**: Average fill rate
- **Critical Orders**: Orders at risk
- **Low Coverage**: Orders with shortages
- **Fully Covered**: Orders with adequate stock

**Quick Actions**:
- See risk bands (green/yellow/red)
- View demand window (today, 3-day, 7-day, all)
- Review critical orders to prioritize sourcing

---

## Navigation

### Sidebar (Left)
- **My Work**: Personal dashboard and tasks
- **Manufacturing**: All operator manufacturing views
- **Recent**: Recent activities
- **Notifications**: Alerts and updates (future)

### Top Navigation
- **Workspace selector**: Switch between assigned operator workspaces (if applicable)
- **Notifications**: Alerts and messages
- **Profile**: Account settings, preferences, logout

### Quick Search (Header)
Search for:
- Part numbers
- Order IDs
- Machine names
- Material codes

---

## Tips & Tricks

### 🎯 Prioritize Your Day
1. Check **Dashboard** first for critical items
2. Review **QC** for urgent failures
3. Monitor **Machines** for delays
4. Check **Materials** for supply issues
5. Plan **Production** for tomorrow

### ⏰ Real-Time Updates
- Pages show **today's data** by default
- Refresh the page to see latest KPIs (no auto-refresh yet)
- Check KPI cards for color indicators:
  - **Green**: Normal status
  - **Orange/Yellow**: Warning
  - **Red**: Critical alert

### 🔗 Deep Dives
- Click "Full Log" / "Full View" links to access detailed admin pages
- These open in new tabs — your operator dashboard stays available
- Use admin pages for detailed analysis, filtering, reporting

### 📱 Mobile Access
- All views are **mobile-responsive**
- Sidebar becomes a drawer on phones
- Tables collapse to compact card view
- Touch-friendly buttons for easy navigation

### 🌍 Language
- Click **Settings** → **Language** to switch:
  - English
  - 日本語 (Japanese)
  - नेपाली (Nepali)

---

## Common Tasks

### Find a Specific Part
1. Go to **Parts** (`/u/parts`)
2. Search by part number or name
3. Click part for detail view

### Check Today's QC Status
1. Go to **QC** (`/u/qc`)
2. Review KPI strip at top
3. Check "Run Now" table for items to test
4. Check "Failed/Recheck" table for issues

### Monitor Production Progress
1. Go to **Production** (`/u/production`)
2. See planned vs. actual
3. Click "Full Production View" for details
4. Identify bottlenecks

### Check Material Stock
1. Go to **Materials** (`/u/materials`)
2. Review KPI for critical shortages
3. Click "Critical Shortage" table
4. Check "Open Orders" for incoming stock

### Schedule Tomorrow
1. Go to **Dashboard** or **Production**
2. Review current completion rates
3. Check **Materials** for supply constraints
4. Plan accordingly in production system

---

## Keyboard Shortcuts (Future)

*Not yet implemented — coming in Phase 6*

- `Ctrl+/` — Toggle command palette
- `Ctrl+K` — Quick search
- `Ctrl+L` — Focus location bar
- `?` — Help / shortcuts

---

## Troubleshooting

### Page Not Loading
- ✅ Check your internet connection
- ✅ Refresh the page (Ctrl+R or Cmd+R)
- ✅ Clear browser cache (Ctrl+Shift+Del)
- ✅ Try a different browser

### Can't Find a View
- ✅ Click **Manufacturing** in sidebar to see all manufacturing views
- ✅ Use top search bar to find items directly
- ✅ Check you're logged in (not redirected to login)

### Data Looks Old
- ✅ Refresh page to see latest data (no auto-refresh)
- ✅ Check your internet connection
- ✅ Contact IT if data is consistently stale

### Mobile View Looks Wrong
- ✅ Try rotating device to landscape (better for tables)
- ✅ Pinch to zoom if text is too small
- ✅ Use "Full View" link to see desktop version if needed

### Permissions Error
- ✅ You may not be assigned to this app
- ✅ Contact your supervisor or IT for access
- ✅ Check **Settings** → **Assignments** to see your current access

---

## Support

- **Questions?** Contact your supervisor or the IT helpdesk
- **Bug?** Note the exact time and what you were doing, email IT
- **Feature Request?** Suggest it to your team lead for the next planning cycle

---

**Version 1.0**  
*Operator Workspace — Manufacturing Execution Platform*
