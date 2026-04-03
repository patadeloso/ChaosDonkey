# ChaosDonkey Plans.md

作成日: 2026-04-02

---

## Phase 5: Execution history and operator visibility

Purpose: Persist recent executed kicks from the shared execution path and surface a compact operator-facing history view.

| Task | 内容 | DoD | Depends | Status |
|------|------|-----|---------|--------|
| 5.1 | Add persistent execution history storage for recent runs | A module-owned persistence shape exists for execution history and automated tests prove the expected schema/config contract | - | cc:完了 |
| 5.2 | Record executed CLI and cron runs through the shared execution path | Automated tests prove both CLI and cron executions append a history record with source, kick, outcome, and profile context | 5.1 | cc:完了 |
| 5.3 | Extend `chaosdonkey:status` with a compact recent-history section | Command output includes a bounded recent-history summary without regressing existing status lines, proven by command tests | 5.2 | cc:完了 |
| 5.4 | Document history behavior and operator expectations | `README.md` documents what is recorded, what is intentionally excluded, and how operators read the new status output | 5.3 | cc:完了 |
| 5.5 | Run full verification and close the phase | `vendor/bin/phpunit` passes and the working tree is ready for branch-finishing workflow | 5.4 | cc:完了 |

## Phase 6: Execution history hardening

Purpose: Make execution history best-effort operator visibility instead of a hard runtime dependency for kick, cron, and status paths.

| Task | 内容 | DoD | Depends | Status |
|------|------|-----|---------|--------|
| 6.1 | Degrade gracefully when execution-history writes fail | CLI and cron executions still complete and keep `last_*` state updates when history insertion fails or the table is unavailable, proven by targeted automated tests | Phase 5 | cc:完了 |
| 6.2 | Degrade gracefully when status history reads fail | `chaosdonkey:status` still renders the core operator snapshot and a safe history placeholder when history queries fail, proven by command tests | 6.1 | cc:完了 |
| 6.3 | Re-document degraded-history behavior and re-verify the branch | `README.md` documents degraded history behavior and `composer validate --no-check-publish` plus `vendor/bin/phpunit` pass after the hardening changes | 6.2 | cc:完了 |

## Phase 7: Source-aware execution health

Purpose: Turn execution history into a clearer operator snapshot by showing the latest CLI and cron executions and surfacing a soft cron-visibility notice in `chaosdonkey:status`.

| Task | 内容 | DoD | Depends | Status |
|------|------|-----|---------|--------|
| 7.1 | Add source-aware execution-history reads for the latest CLI and cron runs | `ExecutionHistoryStorage` exposes tested reads for the most recent `cli` and `cron` rows without regressing the existing bounded recent-history query | Phase 6 | cc:TODO |
| 7.2 | Extend `chaosdonkey:status` with last CLI/cron execution lines and a soft cron notice | Command output shows last CLI execution, last cron execution, and a soft notice when cron is enabled but no cron history exists, proven by command tests and preserving degraded-history behavior | 7.1 | cc:TODO |
| 7.3 | Document source-aware status health and re-verify the branch | `README.md` explains the new status-health lines and `composer validate --no-check-publish` plus `vendor/bin/phpunit` pass after the changes | 7.2 | cc:TODO |
