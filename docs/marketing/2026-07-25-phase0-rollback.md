# Phase 0 rollback record

Captured immediately before the Phase 0 teardown on 2026-07-25.
Project: `jwrpjuqaynownxaoeayi` (`twins-dash-prod`, eu-west-1).

Plan: `docs/superpowers/plans/2026-07-25-twins-marketing-os-phase-0.md`
Spec: `docs/superpowers/specs/2026-07-25-twins-marketing-os-design.md`

## Cron state before teardown

```sql
select jobid, jobname, schedule, active
from cron.job
where jobid in (33, 36, 37, 63, 86, 103, 104, 105, 106)
order by jobid;
```

| jobid | jobname | schedule | active | Phase 0 action |
| --- | --- | --- | --- | --- |
| 33 | ghl-contacts-sync-nightly | `15 4 * * *` | true | leave running |
| 36 | meta-ads-sync-nightly | `5 4 * * *` | true | leave running |
| 37 | meta-leads-sync-nightly | `20 4 * * *` | true | leave running |
| 63 | call-intake-process-5min | `*/5 * * * *` | true | **do not touch** — not marketing |
| 86 | offline-conversions-weekly | `7 10 * * 5` | true | leave running |
| 103 | publish-content-5min | `*/5 * * * *` | true | **disable** |
| 104 | spend-recommendations-monday | `0 11 * * 1` | true | leave running (owner decision) |
| 105 | sync-post-performance-nightly | `50 7 * * *` | true | leave running |
| 106 | poll-video-jobs-2min | `*/2 * * * *` | true | **disable** |

## Table row counts before teardown

```sql
select
  (select count(*) from content_items)          as content_items,
  (select count(*) from content_performance)    as content_performance,
  (select count(*) from video_jobs)             as video_jobs,
  (select count(*) from spend_recommendations)  as spend_recommendations;
```

| table | rows |
| --- | --- |
| `content_items` | **0** |
| `content_performance` | **0** |
| `video_jobs` | **0** |
| `spend_recommendations` | 6 |

**Note:** the three content tables are empty. The scheduler shipped in July 2026
but never held a single content item, which independently corroborates that it
never published anything — it has been blocked on Meta App Review and Google
Business Profile API access since it was built. Nothing is lost by retiring it.

The 6 `spend_recommendations` rows are proposals awaiting a decision. Cron 104
stays active, so this table keeps growing; it is untouched by Phase 0.

## launchd state before teardown

```bash
launchctl list | grep -i twins
```

Result: **no twins agents loaded.**

The three plists exist in `twins-content-engine/deploy/` but are not installed in
`~/Library/LaunchAgents`. The last real Instagram publish was 2026-07-15
(`twins-content-engine/logs/ig_publish.log`). Phase 0 deletes the plists so they
cannot be re-installed by accident.

## Restore procedure

To restore automated publishing:

1. `select cron.alter_job(103, active := true);`
2. `select cron.alter_job(106, active := true);`
3. Set project secret `MARKETING_PUBLISHING_ENABLED=true`
   (the edge-function gate fails closed, so an unset secret means disabled)
4. Set `GHL_SOCIAL_WRITES_ENABLED=true` in `twins-content-engine/.env` to
   re-enable GHL social writes
5. `git revert` the Phase 0 commits in both repos:
   - `twins-dash` branch `feat/phase0-retire-marketing-publishing`
   - `twins-dashboard` branch `docs/twins-marketing-os-spec`
6. Re-installing the launchd agents from git history is a deliberate
   **re-enable**, not a restore — they were already unloaded at teardown time.

Nothing in Phase 0 drops data, so no restore step touches table contents.
