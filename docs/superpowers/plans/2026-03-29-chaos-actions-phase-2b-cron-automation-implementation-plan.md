# Chaos Actions Phase 2B Cron Automation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Magento-native cron automation for ChaosDonkey kicks with admin-configurable scheduling gates, while keeping kick behavior identical to CLI execution.

**Architecture:** Introduce cron configuration keys and a cron entrypoint that delegates to a shared kick executor service. Refactor `chaosdonkey:kick` to call the same executor so CLI and cron use one orchestration path (including action-toggle reroll and state persistence).

**Tech Stack:** PHP 8.5, Magento 2 config/cron XML, Symfony Console, PSR-3 logging, PHPUnit 12

---

## Scope and Boundaries
- In scope: admin cron config fields/defaults, cron registration, shared executor extraction, cron gate checks (enabled + allowed hours), unit tests, README updates.
- Out of scope: schedule preset builders, cron jitter, DB run history table, per-action schedules.

## File Map

### Create
- `Cron/ChaosDonkeyKickCron.php`
- `Model/KickExecutor.php`
- `Test/Unit/Cron/ChaosDonkeyKickCronTest.php`
- `Test/Unit/Model/KickExecutorTest.php`

### Modify
- `etc/adminhtml/system.xml`
- `etc/config.xml`
- `etc/crontab.xml` (new if absent in repo)
- `etc/di.xml` (only if additional wiring is required)
- `Model/Config.php`
- `Console/Command/ChaosDonkeyKick.php`
- `Test/Unit/Model/ConfigTest.php`
- `Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
- `README.md`

### Verify Existing
- `Test/Unit/Action/*.php`
- `Test/Unit/Console/Command/ChaosDonkeyStatusTest.php`

---

### Task 1: Add Cron Admin Config Fields and Defaults

**Files:**
- Modify: `etc/adminhtml/system.xml`
- Modify: `etc/config.xml`

- [ ] **Step 1: Add failing expectations in config tests (if applicable)**
- Add assertions in config tests for new cron keys/default handling where practical.

- [ ] **Step 2: Implement admin fields and defaults**
- Add fields under `admin/chaos_donkey`:
  - `cron_enabled` (yes/no)
  - `cron_expression` (text)
  - `cron_allowed_hours` (text)
- Set defaults in `etc/config.xml`:
  - `cron_enabled=0`
  - `cron_expression=*/30 * * * *`
  - `cron_allowed_hours=` (empty)
- Use default scope only for these new fields.

- [ ] **Step 3: Verify no regressions**
Run: `vendor/bin/phpunit --filter ConfigTest`
Expected: PASS.

- [ ] **Step 4: Commit**
```bash
git add etc/adminhtml/system.xml etc/config.xml Test/Unit/Model/ConfigTest.php
git commit -m "Add cron admin config fields and defaults"
```

---

### Task 2: Extend Config Model for Cron Accessors and Hour Parsing

**Files:**
- Modify: `Model/Config.php`
- Modify: `Test/Unit/Model/ConfigTest.php`

- [ ] **Step 1: Write failing tests**
Add tests for:
- `isCronEnabled()`
- `getCronExpression()` trimming/normalization
- `getCronAllowedHoursRaw()`
- `getCronAllowedHours()` returning sorted unique `int[]`
- invalid tokens handling strategy (ignore invalid entries, keep valid values)

- [ ] **Step 2: Run RED**
Run: `vendor/bin/phpunit Test/Unit/Model/ConfigTest.php`
Expected: FAIL on missing methods/constants.

- [ ] **Step 3: Implement minimal config logic**
- Add config path constants.
- Add cron getter methods.
- Implement allowed-hours parser:
  - split by comma
  - trim tokens
  - accept only numeric 0-23
  - discard invalid tokens
  - unique + sort ascending

- [ ] **Step 4: Run GREEN**
Run: `vendor/bin/phpunit Test/Unit/Model/ConfigTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add Model/Config.php Test/Unit/Model/ConfigTest.php
git commit -m "Add cron config accessors and allowed-hour parsing"
```

---

### Task 3: Extract Shared Kick Executor Service

**Files:**
- Create: `Model/KickExecutor.php`
- Create: `Test/Unit/Model/KickExecutorTest.php`
- Modify: `Console/Command/ChaosDonkeyKick.php`
- Modify: `Test/Unit/Console/Command/ChaosDonkeyKickTest.php`

- [ ] **Step 1: Write failing executor tests**
Cover existing kick behavior currently in command:
- module disabled short-circuit behavior handled by caller contract
- disabled-action reroll behavior
- all-actions-disabled warning text hook
- max-attempt fallback to `napping`
- final state persistence

- [ ] **Step 2: Run RED**
Run: `vendor/bin/phpunit Test/Unit/Model/KickExecutorTest.php`
Expected: FAIL (class missing).

- [ ] **Step 3: Implement `KickExecutor`**
- Move orchestration logic from command to executor.
- Keep current result behavior unchanged.
- Return a small result DTO/object for caller-specific output needs.

- [ ] **Step 4: Refactor command to delegate**
- Command keeps CLI formatting.
- Command calls executor and prints returned messages.

- [ ] **Step 5: Run focused GREEN**
Run:
- `vendor/bin/phpunit Test/Unit/Model/KickExecutorTest.php`
- `vendor/bin/phpunit Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**
```bash
git add Model/KickExecutor.php Console/Command/ChaosDonkeyKick.php Test/Unit/Model/KickExecutorTest.php Test/Unit/Console/Command/ChaosDonkeyKickTest.php
git commit -m "Extract shared kick executor for CLI and cron"
```

---

### Task 4: Add Magento Cron Wiring and Cron Job Class

**Files:**
- Create or Modify: `etc/crontab.xml`
- Create: `Cron/ChaosDonkeyKickCron.php`
- Create: `Test/Unit/Cron/ChaosDonkeyKickCronTest.php`
- Modify: `etc/di.xml` (only if explicit wiring needed)

- [ ] **Step 1: Write failing cron tests**
Test cases:
- skip when module disabled
- skip when cron disabled
- skip outside allowed hours
- execute inside allowed hours
- invalid allowed-hours input does not crash job

- [ ] **Step 2: Run RED**
Run: `vendor/bin/phpunit Test/Unit/Cron/ChaosDonkeyKickCronTest.php`
Expected: FAIL (files missing).

- [ ] **Step 3: Implement cron registration and class**
- Add cron job ID and schedule config path usage.
- Cron class dependencies: `Config`, `KickExecutor`, logger, clock/time provider.
- Add explicit skip/execution logs.

- [ ] **Step 4: Run GREEN**
Run: `vendor/bin/phpunit Test/Unit/Cron/ChaosDonkeyKickCronTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add etc/crontab.xml Cron/ChaosDonkeyKickCron.php Test/Unit/Cron/ChaosDonkeyKickCronTest.php etc/di.xml
git commit -m "Add Magento cron job for automated ChaosDonkey kicks"
```

---

### Task 5: Documentation and Full Verification

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update README**
Document:
- new cron admin config keys
- sample cron expression usage
- allowed-hours format
- skip behavior when cron/module disabled

- [ ] **Step 2: Run full suite**
Run: `vendor/bin/phpunit`
Expected: full PASS with no new failures.

- [ ] **Step 3: Commit docs**
```bash
git add README.md
git commit -m "Document Phase 2B cron automation settings"
```

---

### Task 6: Final Verification Gate

**Files:**
- No code changes expected unless regression found

- [ ] **Step 1: Final command set**
```bash
vendor/bin/phpunit
git status --short
```
Expected:
- tests green
- clean tree (except intentional untracked files)

- [ ] **Step 2: Resolve any failures and rerun**

- [ ] **Step 3: Hand off to `finishing-a-development-branch` workflow**

---

## Skills During Execution
- `superpowers:test-driven-development`
- `superpowers:verification-before-completion`
- `superpowers:receiving-code-review` (when feedback arrives)
- `superpowers:finishing-a-development-branch` (after all tasks pass)

## Commit Strategy
- One commit per task for clean review.
- Keep behavior-changing commits separate from docs-only commits.
- Preserve green tests at each checkpoint.
