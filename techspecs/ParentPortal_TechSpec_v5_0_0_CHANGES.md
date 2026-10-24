# MFSD Parent Portal: Change Specification v4.5 (Week 2 order, Wider Interests, commitments)

**Plugin:** `mfsd-parent-portal` 4.4.10 → **5.0.0** (new Commitments panel and gated task cards are a significant new capability: a major bump under the CLAUDE.md versioning rules. The file name keeps "v4.5" so existing references still work.)
**Applies on top of:** `ParentPortal_TechSpec_v4.4.md`
**Suggested repo path:** `mfsd-parent-portal/techspecs/ParentPortal_TechSpec_v5_0_0_CHANGES.md`
**Status:** Ready to build (revision 2, 24 Oct 2026). §4 removed: course versioning replaces it.

> **Decisions applied in this copy (Mark, 23 Oct 2026):**
> 1. **Every commitment step is required.** There's no "Not now", "Skip" or "No Spark this time". A task only completes, and its badges and coins are only awarded, once the commitment is saved.
>    - If `mfsd-commitments` is inactive, the child still writes the commitment. The task saves it on its own row and completes, so a student is never stranded.
> 2. **No card limits.** `limit_reached`, the "Choose what to keep" screen, `mfsd_render_keep_screen()` and `exempt_from_limit` are removed.
> 3. **The parent sees the commitment.** Each task's parent summary names it in the child's words, with a short explanation for the parent: what it is, why it matters, how they can help, and how to confirm it on their home screen.
> 4. **Parent Portal progress** gets a "Choosing their commitment" step (named per task) before Completed.

**Linear:** new ticket (Parent Portal)

> Checked against the public repo (29 Sep 2026). Activity display details live in `MFSD_Parent_Portal_Data::get_metadata()`, keyed by `task_slug`. A slug with no entry falls back to the Task Order `display_name`, a 📋 icon and **an empty URL**, so the parent gets no link. The portal is server-rendered with no AJAX.

---

## 1. Metadata entries (`get_metadata()`)

Add:

| task_slug | name | icon | description | url | status_method |
|---|---|---|---|---|---|
| `who_inspired` (confirm) | Who Inspired You Most | 🌟 | The people who inspire them, a family guessing game | `/my-future-self-foundation-course/week-2/who-inspired-you-most/` | (later, with the plugin) |
| `wider_interests` | Wider Interests | ❤️ | What they love, what they want to try, and their Spark | `/my-future-self-foundation-course/week-2/wider-interests/` | `get_wider_interests_status` |
| `dream_life` | Dream Life | 🌈 | Their picture of their dream life, and first steps towards it | `/my-future-self-foundation-course/week-2/dream-life/` | `get_dream_life_status` |
| `rag_week_2` | Weekly Check-in | 🚦 | Red/Amber/Green weekly reflection | `/my-future-self-foundation-course/week-2/week-2-rag/` | `get_weekly_rag_status` |
| `rag_week_3` | Weekly Check-in | 🚦 | Red/Amber/Green weekly reflection | `/my-future-self-foundation-course/week-3/week-3-rag/` | `get_weekly_rag_status` |

- Confirm every URL against the live pages before deploying.
- `get_weekly_rag_status()` already takes `$week_num`, and `get_student_progress()` already passes it.
- **No `life_wheel` entry is needed**, because inactive tasks aren't listed. A student who completed Life Wheel still has the badge in Quest Log.
- The Week 3 tasks need entries too when they go live; that's outside this change.

## 2. Status methods

### 2.1 `get_wider_interests_status( $student_id, $week_num )`

Reads `wp_mfsd_wi_results` and, for the current parent, `wp_mfsd_wi_predictions`.

| State | progress_text |
|---|---|
| No row | Not started |
| Started, not completed | Exploring interests |
| Completed, this parent hasn't predicted | **Completed · make your prediction to see their results** |
| Completed, predicted | Completed · you guessed {score} of 3 |

**It must not show any loves, tries, the Spark or swipes** (Wider Interests spec §3.2). The link goes to the task page with `?student_id=`, where the prediction game is played on the parent tab.

### 2.2 `get_dream_life_status( $student_id, $week_num )`

Reads the Dream Life results row (check the table name in `ai-dream-life`). Completed text: "Completed · {n} first steps on their home screen". A completed Dream Life always has 1–2 steps. The table is `wp_mfsd_dream_life_results` (plugin `mfsd-dream-life`).

### 2.3 Dream & Junk Jobs

`get_junk_jobs_status()` reads `dream_ranking_json`. Add the new `commit` status as step 7, **"Choosing their action"** (in_progress), and set `progress_max` to 7. The progress bar stays as it is (Mark, 23 Oct 2026).

**Guessing game gate** (Dream & Junk Jobs `_checked` §17.6): when the task is complete but **this** parent hasn't finished the guessing game, the completed card shows "Completed · play the guessing game to see their results", with **no** job counts or "Top dream". Once they've played, it shows today's summary line plus "You guessed {n} of {total}". Read `wp_mfsd_djj_guesses` for the viewing parent.

### 2.4 "Choosing their commitment" step for every producer

| Task | Status before Completed | progress_text |
|---|---|---|
| Dream & Junk Jobs | `commit` | Choosing their action |
| Wider Interests | Shortlist done, Spark not saved | Choosing their Spark |
| Dream Life | `first_steps` | Choosing their first steps |
| Weekly RAG | Summary saved, plan not saved | Choosing next week's plan |
| Super Strengths (Week 1) | Game done, card not saved | Choosing their strength |


## 3. Commitments panel (new)

Shown at the top of a child's progress view, above the weeks, when `mfsd-commitments` is active (`function_exists( 'mfsd_get_open_commitments' )`).

**Parent visibility (added 24 Oct 2026):** the panel shows exactly what the Commitments parent routes show, never more:
- a card with an unreleased **moderation** flag (`mfsd_safeguarding_is_withheld( $child_id, 'commitments', $card_id )`) is left out entirely, with no count and no placeholder
- a card that `apply_filters( 'mfsd_commitment_visible_to_parent', true, $card, $parent_id )` hides (e.g. Dream & Junk Jobs before this parent plays the guessing game) is left out of the list and the counts. The task's own card in the progress view already points the parent to the game.
- "Waiting for you" lists `checked_in` cards **without** a "Let's talk" request. Cards the parent asked to talk about show under a separate "You asked to talk about" line.

- **Waiting for you:** the child's `checked_in` cards, most recent first. Each shows the card title, its source task, the date and the child's "what happened?" text, with **"Confirm on your home screen →"** linking to the home page. The portal stays read-only: validation happens in the home widget.
- **Counts:** "{n} cards open · {n} done", with done linking to the home widget's Done tab.
- If there are no cards, the panel is hidden.
- Server-rendered, with the same linked-parent check the portal already uses.

## 4. Locking (removed, 24 Oct 2026)

The earlier §4 ("respect bypassed tasks") is **dropped**. Course versioning (`COURSE_VERSIONING_TECH_SPEC_v1_0_0.md`, build list #14) replaces task bypassing: existing students stay on the version of the course they started, so tasks are never inserted into an in-progress student's order. `get_student_progress()`'s locking pass is unchanged in 5.0.0. Course versioning will make the portal read the student's own course version (Course Versioning spec §8).

## 5. Build notes (24 Oct 2026)

- The Wider Interests tables (`wp_mfsd_wi_results`, `wp_mfsd_wi_predictions`) and the Dream & Junk Jobs guesses table (`wp_mfsd_djj_guesses`) don't exist until those tasks ship (build list #7, #8). Every status method checks that its table exists, and falls back to the current behaviour when it doesn't.
- Status values a task doesn't produce yet (`commit`, `first_steps`, the Spark step) are mapped now, so the portal is ready when the tasks ship.

## 6. Testing

1. A Week 2 parent view shows Dream & Junk Jobs, Wider Interests, Dream Life and the Week 2 RAG with names, icons and working links; Who Inspired doesn't appear while it's inactive.
2. Child completed Wider Interests, parent hasn't predicted → "make your prediction…" and no results anywhere on the page or in its source.
3. After predicting → the score shows.
4. Two checked-in cards → "Waiting for you" lists both, linking to the home page.
5. A card held for moderation, or hidden until the parent plays the guessing game, doesn't appear in the panel or its counts.
6. `mfsd-commitments` deactivated → no panel, no errors.
7. 360px: the panel stacks cleanly above the weeks.
