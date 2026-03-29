# Chaos Actions Phase 2A Admin Toggles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin-configurable per-action enable/disable toggles and make `chaosdonkey:kick` reroll away from disabled action outcomes.

**Architecture:** Keep existing action framework unchanged. Add config fields/getters for action toggles and update kick orchestration to reroll when disabled action outcomes are selected, with explicit operator warnings and a max-attempt guard fallback.

**Tech Stack:** PHP 8.5, Magento 2 system config XML, Symfony Console, PHPUnit 12

---

## Scope and Boundaries
- In scope: action toggle config fields, config model accessors, reroll logic in kick command, unit tests, docs update.
- Out of scope: probability controls, per-store overrides, GraphQL payload builder, cron automation.

## File Map

### Modify
- `etc/adminhtml/system.xml`
- `etc/config.xml`
- `Model/Config.php`
- `Console/Command/ChaosDonkeyKick.php`
- `Test/Unit/Model/ConfigTest.php`
- `Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
- `README.md`

### Verify Existing
- `Test/Unit/Action/*.php`
- `Test/Unit/Console/Command/ChaosDonkeyStatusTest.php`

---

### Task 1: Add Admin Toggle Fields and Default Values

**Files:**
- Modify: `etc/adminhtml/system.xml`
- Modify: `etc/config.xml`

- [ ] **Step 1: Add failing XML expectations via lightweight structure assertions**
- Add or update XML tests if present; if not, proceed with direct XML edit and rely on integration/manual verification.

- [ ] **Step 2: Implement XML fields**
- Add yes/no fields under `admin/chaos_donkey`:
  - `enable_reindex_all`
  - `enable_cache_flush`
  - `enable_graphql_pipeline_stress`
- Add defaults in `etc/config.xml` as `1` for each toggle.

- [ ] **Step 3: Run focused verification**
Run: `vendor/bin/phpunit`
Expected: suite remains green.

- [ ] **Step 4: Commit**
```bash
git add etc/adminhtml/system.xml etc/config.xml
git commit -m "Add admin action toggle config fields"
```

---

### Task 2: Extend Config Model for Action Toggle Access

**Files:**
- Modify: `Model/Config.php`
- Modify: `Test/Unit/Model/ConfigTest.php`

- [ ] **Step 1: Write failing tests**
- Add tests for new getters:
  - `isReindexAllEnabled()`
  - `isCacheFlushEnabled()`
  - `isGraphQlPipelineStressEnabled()`
- Add tests for `isActionEnabled(string $actionCode): bool` mapping and unknown-code behavior.

- [ ] **Step 2: Run RED**
Run: `vendor/bin/phpunit Test/Unit/Model/ConfigTest.php`
Expected: FAIL for missing methods/constants.

- [ ] **Step 3: Implement minimal Config changes**
- Add constants for three config paths.
- Add boolean getters using `isSetFlag`.
- Add `isActionEnabled()` mapping:
  - `reindex_all` => reindex toggle
  - `cache_flush` => cache toggle
  - `graphql_pipeline_stress` => graphql toggle
  - non-action outcomes / unknown => `true`

- [ ] **Step 4: Run GREEN**
Run: `vendor/bin/phpunit Test/Unit/Model/ConfigTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add Model/Config.php Test/Unit/Model/ConfigTest.php
git commit -m "Add action toggle getters and isActionEnabled config mapping"
```

---

### Task 3: Add Disabled-Action Reroll Logic in Kick Command

**Files:**
- Modify: `Console/Command/ChaosDonkeyKick.php`
- Modify: `Test/Unit/Console/Command/ChaosDonkeyKickTest.php`

- [ ] **Step 1: Write failing tests for new behavior**
Add tests for:
- disabled action result triggers reroll
- all-actions-disabled warning logs and only non-action outcomes execute
- max-attempt guard falls back to `napping`
- persisted `last_kick` and `last_outcome` reflect final executed outcome

- [ ] **Step 2: Run RED**
Run: `vendor/bin/phpunit Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
Expected: FAIL for missing reroll logic/warnings.

- [ ] **Step 3: Implement minimal reroll flow**
- Determine enabled action flags from `Config`.
- Roll and resolve in a bounded loop.
- If selected outcome is disabled action, reroll.
- Log all-actions-disabled warning once when applicable.
- On max-attempt exceed: set outcome to `napping` and log warning.
- Keep existing persistence and output semantics.

- [ ] **Step 4: Run GREEN**
Run: `vendor/bin/phpunit Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add Console/Command/ChaosDonkeyKick.php Test/Unit/Console/Command/ChaosDonkeyKickTest.php
git commit -m "Skip disabled actions in kick command via bounded reroll"
```

---

### Task 4: Documentation and Full Verification

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update README**
- Document new admin action toggles and reroll behavior.
- Document all-actions-disabled warning behavior.

- [ ] **Step 2: Run full verification**
Run: `vendor/bin/phpunit`
Expected: all tests pass, no notices/deprecations newly introduced.

- [ ] **Step 3: Commit docs**
```bash
git add README.md
git commit -m "Document Phase 2A action toggle and reroll behavior"
```

---

### Task 5: Final Verification Gate

**Files:**
- No code changes expected unless regressions discovered

- [ ] **Step 1: Final command set**
```bash
vendor/bin/phpunit
git status --short
```
Expected:
- test suite green
- clean working tree (excluding intentional untracked files)

- [ ] **Step 2: If failing, fix and rerun**

- [ ] **Step 3: Hand off to `finishing-a-development-branch` workflow**

---

## Skills During Execution
- `superpowers:test-driven-development`
- `superpowers:verification-before-completion`
- `superpowers:receiving-code-review` (if review feedback arrives)
- `superpowers:finishing-a-development-branch` (after tasks complete)

## Commit Strategy
- One commit per task (or smaller, if needed for clarity).
- Keep commits scoped and imperative.
