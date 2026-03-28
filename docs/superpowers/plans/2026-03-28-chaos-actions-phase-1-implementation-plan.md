# Chaos Actions Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Magento-native chaos action framework and deliver two new actions (`cache_flush`, `graphql_pipeline_stress`) while preserving and integrating `reindex_all`.

**Architecture:** Introduce an action interface + DI action pool + roll outcome resolver so `chaosdonkey:kick` becomes a thin orchestrator. Implement each chaos behavior as an isolated action service that returns a structured result object. Keep status/state writing in existing services and preserve operator-friendly CLI output.

**Tech Stack:** PHP 8.5, Magento 2 module DI/config, Symfony Console commands, PHPUnit 12

---

## Scope and Boundaries
- In scope: action framework, roll resolution, kick command integration, cache flush action, internal GraphQL pipeline stress action, tests/docs updates.
- Out of scope: admin-driven action builders/toggles/probabilities, cron scheduling/automation.

## File Map

### Create
- `Api/ChaosActionInterface.php` (action contract)
- `Model/ChaosActionResult.php` (standardized action execution result)
- `Model/ActionPool.php` (action lookup by code)
- `Model/RollOutcomeResolver.php` (d20 -> outcome/action code mapping)
- `Action/CacheFlush.php` (new action)
- `Action/GraphQlInternalPipelineStress.php` (new action)
- `Test/Unit/Model/ActionPoolTest.php`
- `Test/Unit/Model/RollOutcomeResolverTest.php`
- `Test/Unit/Action/CacheFlushTest.php`
- `Test/Unit/Action/GraphQlInternalPipelineStressTest.php`
- minimal Magento/Symfony stub files if required by unit tests for new interfaces/services

### Modify
- `Action/ReindexAll.php` (implement interface + return `ChaosActionResult`)
- `Console/Command/ChaosDonkeyKick.php` (resolver + pool orchestration)
- `etc/di.xml` (register pool items and resolver dependencies)
- `README.md` (document new action mapping and behavior)
- `Test/Unit/Console/Command/ChaosDonkeyKickTest.php` (new outcomes and action execution assertions)

### Verify Existing
- `Test/Unit/Console/Command/ChaosDonkeyStatusTest.php`
- `Test/Unit/Model/ConfigTest.php`
- `Test/Unit/Model/StateWriterTest.php`
- `Test/Unit/Action/ReindexAllTest.php`

---

### Task 1: Introduce Action Contracts and Resolver Core

**Files:**
- Create: `Api/ChaosActionInterface.php`
- Create: `Model/ChaosActionResult.php`
- Create: `Model/RollOutcomeResolver.php`
- Test: `Test/Unit/Model/RollOutcomeResolverTest.php`

- [ ] **Step 1: Write failing resolver tests**

```php
self::assertSame('reindex_all', $resolver->resolve(2));
self::assertSame('cache_flush', $resolver->resolve(3));
self::assertSame('graphql_pipeline_stress', $resolver->resolve(4));
self::assertSame('critical_failure', $resolver->resolve(1));
self::assertSame('critical_success', $resolver->resolve(20));
self::assertSame('napping', $resolver->resolve(6));
```

- [ ] **Step 2: Run test to verify RED**

Run: `vendor/bin/phpunit Test/Unit/Model/RollOutcomeResolverTest.php`
Expected: FAIL (class not found / methods missing)

- [ ] **Step 3: Implement minimal contracts and resolver**

- Add interface with:
  - `getCode(): string`
  - `execute(OutputInterface $output): ChaosActionResult`
- Add result value object with constructor/getters.
- Implement resolver with fixed mapping from spec.

- [ ] **Step 4: Run resolver test to verify GREEN**

Run: `vendor/bin/phpunit Test/Unit/Model/RollOutcomeResolverTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Api/ChaosActionInterface.php Model/ChaosActionResult.php Model/RollOutcomeResolver.php Test/Unit/Model/RollOutcomeResolverTest.php
git commit -m "Add chaos action contract and roll outcome resolver"
```

---

### Task 2: Add ActionPool Service

**Files:**
- Create: `Model/ActionPool.php`
- Test: `Test/Unit/Model/ActionPoolTest.php`

- [ ] **Step 1: Write failing tests for known and unknown action codes**

```php
self::assertSame($reindexAction, $pool->get('reindex_all'));
self::assertNull($pool->get('not_registered'));
```

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit Test/Unit/Model/ActionPoolTest.php`
Expected: FAIL

- [ ] **Step 3: Implement minimal `ActionPool`**
- Constructor accepts array of `ChaosActionInterface` keyed by code.
- `get(string $code): ?ChaosActionInterface`

- [ ] **Step 4: Run GREEN**

Run: `vendor/bin/phpunit Test/Unit/Model/ActionPoolTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Model/ActionPool.php Test/Unit/Model/ActionPoolTest.php
git commit -m "Add action pool for chaos action resolution"
```

---

### Task 3: Adapt Reindex Action to Framework

**Files:**
- Modify: `Action/ReindexAll.php`
- Modify: `Test/Unit/Action/ReindexAllTest.php`

- [ ] **Step 1: Update tests to assert `ChaosActionResult` output contract**
- Keep existing per-indexer continue-on-error assertions.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit Test/Unit/Action/ReindexAllTest.php`
Expected: FAIL

- [ ] **Step 3: Implement interface + result return in `ReindexAll`**
- Keep current progress output.
- Return structured summary and success flag.

- [ ] **Step 4: Run GREEN**

Run: `vendor/bin/phpunit Test/Unit/Action/ReindexAllTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Action/ReindexAll.php Test/Unit/Action/ReindexAllTest.php
git commit -m "Adapt reindex action to chaos action interface"
```

---

### Task 4: Implement CacheFlush Action

**Files:**
- Create: `Action/CacheFlush.php`
- Create: `Test/Unit/Action/CacheFlushTest.php`
- Update stubs only if required

- [ ] **Step 1: Write failing tests for full success and partial failure**
- Assert all cache types attempted.
- Assert failures are captured but execution continues.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit Test/Unit/Action/CacheFlushTest.php`
Expected: FAIL

- [ ] **Step 3: Implement minimal action**
- Use Magento cache management APIs.
- Return `ChaosActionResult` with details.

- [ ] **Step 4: Run GREEN**

Run: `vendor/bin/phpunit Test/Unit/Action/CacheFlushTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Action/CacheFlush.php Test/Unit/Action/CacheFlushTest.php
git commit -m "Add cache flush chaos action"
```

---

### Task 5: Implement Internal GraphQL Pipeline Stress Action

**Files:**
- Create: `Action/GraphQlInternalPipelineStress.php`
- Create: `Test/Unit/Action/GraphQlInternalPipelineStressTest.php`
- Update/add stubs for internal request/dispatch dependencies as needed

- [ ] **Step 1: Write failing tests for internal dispatch orchestration**
- Assert synthetic request targets `/graphql`.
- Assert multiple payloads are dispatched.
- Assert failures aggregate and do not abort remaining payloads.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit Test/Unit/Action/GraphQlInternalPipelineStressTest.php`
Expected: FAIL

- [ ] **Step 3: Implement minimal action**
- Hardcode initial payload list (Phase 1 default).
- Dispatch through internal Magento HTTP pipeline path (no external HTTP client).
- Aggregate metadata/errors into result.

- [ ] **Step 4: Run GREEN**

Run: `vendor/bin/phpunit Test/Unit/Action/GraphQlInternalPipelineStressTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Action/GraphQlInternalPipelineStress.php Test/Unit/Action/GraphQlInternalPipelineStressTest.php
git commit -m "Add internal GraphQL pipeline stress chaos action"
```

---

### Task 6: Wire DI Pool and Refactor Kick Command Orchestration

**Files:**
- Modify: `etc/di.xml`
- Modify: `Console/Command/ChaosDonkeyKick.php`
- Modify: `Test/Unit/Console/Command/ChaosDonkeyKickTest.php`

- [ ] **Step 1: Write failing command tests for roll `3` and `4` action execution**
- Assert resolver output controls action selection.
- Assert state persistence still occurs.

- [ ] **Step 2: Run RED**

Run: `vendor/bin/phpunit Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
Expected: FAIL

- [ ] **Step 3: Implement orchestration updates**
- Inject `RollOutcomeResolver` and `ActionPool`.
- Resolve roll code and execute action if mapped.
- Keep existing disabled-gate and state writer behavior.

- [ ] **Step 4: Wire actions in `etc/di.xml`**
- Register actions in pool keyed by code.

- [ ] **Step 5: Run GREEN**

Run: `vendor/bin/phpunit Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add Console/Command/ChaosDonkeyKick.php etc/di.xml Test/Unit/Console/Command/ChaosDonkeyKickTest.php
git commit -m "Refactor kick command to use resolver and action pool"
```

---

### Task 7: Documentation and Full Verification

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update README with new roll mapping and action behavior**
- Document `cache_flush` and `graphql_pipeline_stress`.
- Document Phase 1 default payload behavior for GraphQL action.

- [ ] **Step 2: Run complete unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS (no errors, no notices)

- [ ] **Step 3: Optional Magento runtime smoke validation**

Run in Magento env:
- `bin/magento chaosdonkey:status`
- `bin/magento config:set admin/chaos_donkey/enabled 1`
- `bin/magento chaosdonkey:kick`

Expected: mapped action output and status updates.

- [ ] **Step 4: Commit docs updates**

```bash
git add README.md
git commit -m "Document phase 1 chaos action framework and mappings"
```

---

### Task 8: Final Verification Gate (Required)

**Files:**
- No additional code changes expected unless regressions found

- [ ] **Step 1: Run final verification commands**

```bash
vendor/bin/phpunit
git status --short
```

Expected:
- test suite fully green
- working tree clean except intentionally untracked local files

- [ ] **Step 2: If regressions found, fix and re-run verification**

- [ ] **Step 3: Hand off to `finishing-a-development-branch` workflow**

---

## Skills to Apply During Execution
- `superpowers:test-driven-development`
- `superpowers:verification-before-completion`
- `superpowers:receiving-code-review` (if review comments arrive)
- `superpowers:finishing-a-development-branch` (after all tasks)

## Commit Strategy
- One commit per task.
- Avoid batching unrelated file changes.
- Keep commit messages imperative and scoped.
