# SBAIO Workbook To Suite Migration Plan

Source workbook: `/Users/lazydeepak/Downloads/SBAIO.xlsm`

This document maps the legacy Excel/VBA system into the current SBAIO bundle architecture under:

- `apps/SBAIO/`
- `apps/SBAIO/modules/Staff`
- `apps/SBAIO/modules/Attendance`
- `apps/SBAIO/modules/Schedules`
- `apps/SBAIO/modules/Leave`
- `apps/SBAIO/modules/Timecards`
- `apps/SBAIO/modules/Payroll`
- `apps/SBAIO/modules/Sales`
- `apps/SBAIO/modules/Expenses`
- `apps/SBAIO/modules/Customers`
- `apps/SBAIO/modules/Tasks`
- `apps/SBAIO/modules/Notices`

The goal is phased parity with the workbook without inventing business rules that are not visible in workbook formulas or VBA.

## 1. Legacy Workbook Domains

The workbook is not a single payroll sheet. It is a small business operating system with four major domains.

### A. HR And Employment

- `Staffs`
- `Staffs2026`
- `Contract`

### B. Attendance And Timekeeping

- `Calendar`
- `Shift`
- `ShiftCalendar`
- `Leave`
- `Attendance`
- `TimeCardForm`
- `TimeCard`

### C. Payroll And Payslips

- `Salary`
- `FulltimeRates`
- `PaySlipFT`
- `PaySlipPT`

### D. Sales, Cashbook, And Reporting

- `SquareDaily`
- `SquareMonthly`
- `RestaurantSales`
- `RegiSales`
- `SalesSplit`
- `MSales2025`
- `Remittance`
- `DayBook`
- `BalanceSheet`
- `REPORTS`
- `Dashboard`

## 2. Workbook Facts Confirmed From Formulas

The following rules are explicitly visible in the workbook structure and formulas.

### Staff Master Facts

`Staffs` and `Staffs2026` include:

- `SN`
- `FullName`
- `SalaryType`
- `Nationality`
- `Gender`
- `DateOfBirth`
- `JoinDate`
- `ExitDate`
- `JobDetails`
- `NameRef`

`NameRef` is a derived display identifier and is used as the workbook join key across shift, timecard, salary, and contract sheets.

### Schedule Facts

`Shift` includes:

- `NameRef`
- `Day`
- `StartDate`
- `EndDate`
- `StartTime`
- `EndTime`
- `BreakStart`
- `BreakEnd`
- `Job`
- `Hours`

Confirmed formula:

- `Hours = EndTime - StartTime - (BreakEnd - BreakStart)`

### Calendar And Leave Facts

`Calendar` includes:

- `Date`
- `Day`
- `Holiday`
- `Closed`

`Leave` stores leave date ranges by employee.

### Timecard Facts

`TimeCard` contains derived daily rows with:

- `NameRef`
- `Date`
- `勤務開始時間` (work start)
- `勤務終了時間` (work end)
- `休憩開始`
- `休憩終了`
- `一日の勤務時間` (daily worked time)
- `出勤/休日` (workday/holiday marker)
- `SAL`
- `SalaryType`
- `SalaryRate`
- `Month`
- `Day`
- `helper`

Confirmed formula patterns:

- daily worked time is derived from start/end minus break
- `出勤/休日` is formula-driven based on whether start time exists
- `SalaryType` and `SalaryRate` are carried into the daily timecard row
- month and weekday are derived from date

### Payroll Facts

`Salary` is a monthly staff pay-rate matrix by `NameRef` and `SalaryType`.

Confirmed:

- `Fixed` salary type exists
- `Hourly` salary type exists
- monthly values are stored by employee across month columns

`PaySlipFT` and `PaySlipPT` confirm:

- working days are counted with `COUNTIFS` over `TimeCard[日付]`
- working time is summed from `TimeCard[一日の勤務時間]`
- overtime appears on the slip layout, but exact business thresholds still require full formula extraction
- full-time and part-time payslips are separate outputs

## 3. SBAIO Module Ownership Map

## Staff

Workbook sheets:

- `Staffs`
- `Staffs2026`
- parts of `Contract`
- `NameRef` usage across workbook

SBAIO ownership:

- `apps/SBAIO/modules/Staff`

Entities:

- `sbaio_staff`
- future `sbaio_staff_documents`
- future `sbaio_staff_contracts`

Required fields:

- `employee_code` from `SN`
- `full_name`
- `name_ref`
- `salary_type`
- `salary_rate`
- `nationality`
- `gender`
- `date_of_birth`
- `join_date`
- `exit_date`
- `job_details`
- `employment_status`

## Schedules

Workbook sheets:

- `Shift`
- parts of `ShiftCalendar`

SBAIO ownership:

- `apps/SBAIO/modules/Schedules`

Entities:

- `sbaio_schedule_templates`
- `sbaio_schedule_assignments`
- future `sbaio_shift_rules`

Mapping:

- one shift row in Excel becomes a schedule rule or assignment
- workbook day-of-week schedule rows become reusable templates plus date-ranged assignments

## Leave

Workbook sheets:

- `Leave`
- `Calendar`

SBAIO ownership:

- `apps/SBAIO/modules/Leave`

Entities:

- `sbaio_leave_requests`
- `sbaio_holiday_calendar`
- future `sbaio_closed_days`

Mapping:

- employee leave ranges stay in Leave
- public holiday and closed-day interpretation comes from Calendar

## Attendance

Workbook sheets:

- `Attendance`
- `TimeCardForm`

SBAIO ownership:

- `apps/SBAIO/modules/Attendance`

Entities:

- `sbaio_attendance_daily`
- future `sbaio_attendance_punches`
- future `sbaio_attendance_import_batches`

Important source-of-truth rule:

- raw attendance is not the final pay record
- it is the input layer that feeds Timecards

Current workbook note:

- the legacy `Attendance` sheet is schedule-display oriented, not just raw punches
- `TimeCardForm` shows a manual daily entry form with `Date`, `In`, `Out`, `In2`, `Out2`, `Signature`

SBAIO conversion rule:

- keep raw attendance rows in `Attendance`
- move shift-display logic to `Schedules`
- move derived pay/time logic to `Timecards`

## Timecards

Workbook sheets:

- `TimeCard`
- `NameDate`

SBAIO ownership:

- `apps/SBAIO/modules/Timecards`

Entities:

- `sbaio_timecards_daily`
- `sbaio_timecard_periods`

Responsibilities:

- generate per-employee per-date derived rows
- carry schedule, leave, holiday, and attendance interpretation into a clean daily row
- aggregate by month/period

Confirmed daily fields to preserve:

- work start
- work end
- break start
- break end
- daily worked time
- workday/holiday status
- salary type
- salary rate
- helper flag

## Payroll

Workbook sheets:

- `Salary`
- `FulltimeRates`
- `PaySlipFT`
- `PaySlipPT`

SBAIO ownership:

- `apps/SBAIO/modules/Payroll`

Entities:

- `sbaio_payroll_runs`
- `sbaio_payroll_records`
- future `sbaio_payroll_rate_cards`
- future `sbaio_payslips`

Responsibilities:

- consume approved timecard periods
- compute employee payroll output from confirmed workbook rules
- generate full-time and part-time pay output views

Important separation:

- `Salary` workbook sheet is a configuration source and may also hold reference monthly rates
- `Payroll` module should own final payroll runs and locked outputs

## Sales

Workbook sheets:

- `SquareDaily`
- `SquareMonthly`
- `RestaurantSales`
- `RegiSales`
- `SalesSplit`
- `MSales2025`
- `Remittance`

SBAIO ownership:

- `apps/SBAIO/modules/Sales`

Recommended future subareas:

- POS imports
- sales reconciliation
- channel split reporting
- takeout vs dine-in analysis

## Expenses

Workbook sheets:

- `DayBook`
- parts of `BalanceSheet`

SBAIO ownership:

- `apps/SBAIO/modules/Expenses`

Recommended future subareas:

- cashbook/daybook
- purchase entries
- expense entries
- month-end expense summaries

## Future Finance Reporting

Workbook sheets:

- `BalanceSheet`
- `REPORTS`
- `Dashboard`

Recommended future module:

- `FinanceReports` or `Reports`

This is not required in the current first HR/payroll conversion pass.

## 4. Exact Conversion Phases

## Phase 1. HR Master Parity

Target modules:

- `Staff`
- `Leave`
- `Schedules`

Tasks:

- import `Staffs` and `Staffs2026` structure into SBAIO Staff
- preserve `NameRef` as a compatibility field
- add contract metadata storage scaffold from `Contract`
- support shift templates and date-ranged assignments from `Shift`
- support holiday and leave range entry from `Calendar` and `Leave`

## Phase 2. Daily Attendance And Timecard Parity

Target modules:

- `Attendance`
- `Timecards`

Tasks:

- reproduce workbook daily row derivation for:
  - work start
  - work end
  - break start
  - break end
  - daily worked time
  - workday/holiday marker
  - helper flag
- add `NameRef + Date` compatibility joins during migration
- build `TimeCard` parity tests on a few real workbook staff/date rows

## Phase 3. Salary Matrix And Payroll Parity

Target modules:

- `Payroll`

Tasks:

- model workbook `Salary` sheet as pay-rate source data
- split fixed and hourly logic only where workbook formulas explicitly do so
- extract `PaySlipFT` and `PaySlipPT` logic into service code
- generate payroll records and payslip-ready outputs

## Phase 4. Sales And Daybook Parity

Target modules:

- `Sales`
- `Expenses`

Tasks:

- model daily sales ingestion and reconciliation
- model daybook expense and purchase entries
- recreate core sales-summary views from workbook reports

## Phase 5. Reporting Layer

Target modules:

- future `Reports`

Tasks:

- rebuild dashboard/report tabs as UI screens over normalized data
- avoid copying Excel layout literally unless needed for operational familiarity

## 5. Confirmed Rule Translation Targets

These should be ported into service code because the workbook clearly shows them.

## Timecards

Implement from workbook:

- derive daily worked time from:
  - `end_time - start_time - (break_end - break_start)`
- derive workday status from whether actual work-start data exists
- derive month and weekday from date
- carry `salary_type` and `salary_rate` into the daily row
- preserve helper classification field if workbook formulas confirm it affects inclusion or display

Target service:

- `apps/SBAIO/modules/Timecards/Services/TimecardGenerationService.php`

## Payroll

Implement from workbook:

- fixed vs hourly branching only where formula-confirmed
- month-bounded working day counts from `COUNTIFS`
- month-bounded worked-time sums from `SUMIFS`
- pay-slip data grouping for full-time and part-time outputs

Target service:

- `apps/SBAIO/modules/Payroll/Services/PayrollScaffoldService.php`

## 6. Rules Still Deferred Until Full VBA Or Formula Extraction

Do not invent these yet.

- overtime thresholds
- overtime pay multipliers
- rounding policy
- holiday premiums
- helper classification pay effects
- insurance/deduction calculations beyond what is explicitly visible in workbook formulas
- gross-to-net deduction pipeline if the exact formulas are not yet extracted

## 7. Immediate Implementation Backlog

This is the next practical work order for converting the workbook into SBAIO.

1. Add workbook-compatible import fields to `Staff`:
   - `name_ref`
   - `legacy_sn`
   - `legacy_job_details`
   - `join_date`
   - `exit_date`
   - `salary_type`
   - `salary_rate`

2. Extend `Schedules` to support workbook-compatible fields:
   - `day_of_week`
   - `start_date`
   - `end_date`
   - `start_time`
   - `end_time`
   - `break_start`
   - `break_end`
   - `job_label`
   - derived `scheduled_minutes`

3. Extend `Timecards` to include workbook parity fields:
   - `name_ref`
   - `work_start_at`
   - `work_end_at`
   - `break_start_at`
   - `break_end_at`
   - `worked_minutes`
   - `attendance_marker`
   - `salary_type`
   - `salary_rate`
   - `helper_flag`
   - `month_label`
   - `weekday_label`

4. Add `Payroll` compatibility sources:
   - monthly salary matrix
   - fixed/full-time pay source table
   - payslip-ready output fields

5. Create a workbook parity fixture set:
   - 3 hourly employees
   - 2 fixed employees
   - 1 month of schedule rows
   - 1 month of attendance rows
   - compare generated timecards and pay outputs to workbook values

## 8. Architecture Notes

- Raw truth stays in `Attendance`, `Schedules`, `Leave`, and `Calendar`.
- Derived truth stays in `Timecards`.
- Financial output stays in `Payroll`.
- `NameRef` should be preserved during migration as a legacy compatibility key, but long-term SBAIO should use stable staff IDs internally.
- The workbook's sales/accounting area should become SBAIO business modules later, not be mixed into HR/payroll services.

## 9. Recommended Next Codex Pass

Implement workbook compatibility in this order:

1. extend SBAIO schemas for workbook field parity
2. map `Staffs`, `Shift`, `Calendar`, `Leave` into import-ready entities
3. port `TimeCard` daily-row formulas into `TimecardGenerationService`
4. port `Salary` and payslip formulas into `PayrollScaffoldService`
5. only then tackle sales/daybook/report sheets
