# Parent Portal: Change Specification v5.1.0 (Weekly RAG plan stage, Wider Interests steps)

**Plugin:** `mfsd-parent-portal` 5.0.0 → **5.1.0** (bug fix, so a minor bump under CLAUDE.md)
**Applies on top of:** `ParentPortal_TechSpec_v5_0_0_CHANGES.md`
**Build list:** item 11b (follow-up from Phase 3)
**Status:** Built 25 Oct 2026, awaiting Claude Code review and commit

## 1. Weekly RAG: "Choosing next week's plan"

**The problem.** Weekly RAG 8.0.0 completes a week only once the child saves their plan for next week. Parent Portal 5.0.0 still marked the week Completed as soon as 10 questions were answered.

**The fix.** `get_weekly_rag_status()` now uses `mfsd_rag_week_stage( $student_id, $week )` when Weekly RAG 8.0.0 is active:

| RAG stage | Portal status | Text |
|---|---|---|
| `not_started` | (the existing answer count decides) | Not started |
| `in_progress` | in_progress | "n of 10 questions", or "In progress" when all are answered |
| `plan` | in_progress, progress bar full | **Choosing next week's plan** |
| `completed` | completed | Completed (also covers weeks completed under RAG 7.4.0) |

If Weekly RAG is older, or the function is missing, the 5.0.0 answer-count logic is used unchanged. The answers, score and R/A/G breakdown are returned as before.

## 2. Wider Interests steps

**The problem.** 5.0.0 guessed the step from `loves_ranked`. That showed "Exploring interests" in two wrong cases:
- a child who chose no loves (only tries)
- a child whose Spark was saved while Steve's summary was still to come (status `summary`)

**The fix.** It now reads the plugin's own `status` column:

| Wider Interests status | Text | Progress |
|---|---|---|
| `swiping`, `custom`, `shortlist` | Exploring interests | 1 / 3 |
| `deepdive`, `spark` | Choosing their Spark | 2 / 3 |
| `summary` | Spark chosen, nearly done | 2 / 3 |
| completed (`completed_at` set) | Completed (then the prediction-game lines, unchanged) | 3 / 3 |

Still nothing about loves, tries, the Spark or swipes is shown.

## 3. Testing (at the end-to-end test)

1. **RAG:** answer every Week 2 question but don't save the plan. The portal shows "Choosing next week's plan". Save the plan, and it shows Completed.
2. **Wider Interests:** a child with no loves who reaches the Spark screen shows "Choosing their Spark". With Steve unavailable after the Spark is saved, the portal shows "Spark chosen, nearly done".
