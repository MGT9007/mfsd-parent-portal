# MFSD Parent Portal — Technical Specification

**Plugin:** `mfsd-parent-portal`  
**Current version:** 4.4.9  
**Shortcode:** `[mfsd_parent_portal]`  
**Page slug:** `about/parent-portal-home` (full path required — `portal-home` alone returns NULL)  
**Status:** Live  

---

## 1. Overview

The Parent Portal is a read-only progress dashboard for the High Performance Pathway. It serves two roles from a single WordPress page and shortcode:

- **Students** see their own courses and activity progress.
- **Parents** see the progress of all students linked to their account.

The plugin owns **no database tables**. All data is read from tables created by `mfsd-course-manager`, `mfsd-ordering`, and the individual activity plugins. There are no REST or AJAX endpoints — the entire page is server-side PHP rendered on load.

---

## 2. File Structure

```
mfsd-parent-portal/
├── mfsd-parent-portal.php                  Bootstrap, singleton, routing, shortcode
├── includes/
│   ├── class-parent-portal-data.php        Data layer — all DB queries and status methods
│   └── class-parent-portal-renderer.php    Renderer — all HTML output
├── assets/
│   ├── css/parent-portal.css               BEM-namespaced styles (.mfsd-pp__)
│   └── js/parent-portal.js                 jQuery: accordion, tabs, progress ring
└── techspecs/
    └── ParentPortal_TechSpec_v4.4.md       This document
```

---

## 3. Bootstrap and Lifecycle

**`mfsd-parent-portal.php`** registers a singleton class `MFSD_Parent_Portal` on the `plugins_loaded` hook. The `__construct()` loads both include files and wires three hooks:

| Hook | Method | Effect |
|---|---|---|
| `wp_enqueue_scripts` | `enqueue_assets()` | Enqueues CSS and JS only when the page contains `[mfsd_parent_portal]` |
| `add_shortcode` | `render_portal()` | Registers the `mfsd_parent_portal` shortcode |
| `body_class` | `add_body_classes()` | Adds `mfsd-no-page-title` when shortcode is present |

Assets are versioned against `MFSD_PARENT_PORTAL_VERSION`. JS dependency: `jquery`, loaded in footer.

There is no `register_activation_hook` that creates tables — no tables are owned by this plugin.

---

## 4. Routing

All routing is handled inside `render_portal()` by reading query params from the live request. The page URL never changes — course/student context is passed via `?course_id` and `?student_id`.

```
GET /about/parent-portal-home/
  → Landing view (no course_id)
  
GET /about/parent-portal-home/?course_id=1&student_id=42
  → Course detail view
```

**Role detection:**
```php
$is_student = in_array('student', $roles) && !in_array('administrator', $roles);
```
Administrators are treated as parents for portal display purposes.

**Unauthenticated users** receive a warning notice and no portal content.

### 4.1 Landing view (no `?course_id`)

| Viewer | Method called |
|---|---|
| Student | `$renderer->render_landing_student($current_user_id)` |
| Parent / Admin | `$renderer->render_landing_parent($linked_students)` |

If a parent has no linked students, an info notice is returned and no renderer is instantiated.

### 4.2 Course detail view (`?course_id` present)

| Viewer | `$view_student_id` source | Method called |
|---|---|---|
| Student | Always `$current_user_id` (ignores `?student_id`) | `$renderer->render_student_self($current_user_id, $course_id)` |
| Parent | `?student_id` param | `$renderer->render([$student], $course_id)` |

**Parent validation:** before rendering, `get_linked_students($current_user_id)` is called and the requested `$view_student_id` must appear in the result set. An unauthorised or unlinked `student_id` returns a warning notice.

---

## 5. Data Layer (`MFSD_Parent_Portal_Data`)

### 5.1 Instantiation

Constructed directly in `render_portal()` — no singleton. Stores `$wpdb` reference and a nullable `$course_id` cache.

### 5.2 Active Course

`get_active_course_id()` queries `wp_mfsd_courses WHERE active = 1 ORDER BY id ASC LIMIT 1` and caches the result on the instance. Used internally by `get_week_activities()` and `get_available_weeks()`.

### 5.3 Linked Students (`get_linked_students`)

Queries `wp_mfsd_parent_student_links` joined with `wp_users`, filtering `link_status = 'active'`, ordered by `is_primary_contact DESC, display_name ASC`. Each result is enriched with:
- `year_group` — user meta `year_group`
- `school` — user meta `school`
- `avatar_url` — resolved by `get_profile_picture_url()` (see §5.8)

### 5.4 Student Courses (`get_student_courses`)

Queries `wp_mfsd_courses` joined with `wp_mfsd_enrolments` for courses where `active = 1` and `student_id = $student_id`. Dynamically checks for `image_url` column existence (graceful degradation). Each course row is augmented with `percent_complete` from `get_course_percent()`.

### 5.5 Activity Definitions

`get_week_activities($week_num)` queries `wp_mfsd_task_order` for `course_id`, `week`, and `active = 1`, ordered by `sequence_order`. Each `task_slug` row is matched against the hardcoded `$metadata` array:

| Slug | Name | Icon | Status method |
|---|---|---|---|
| `solution_lens` | The Solution Lens | 🔍 | `get_solution_lens_status` (auto-resolved) |
| `word_association` | Word Association | 💭 | `get_word_association_status` (auto-resolved) |
| `junk_jobs` | Junk Jobs | 🗑️ | `get_junk_jobs_status` (auto-resolved) |
| `personality_test_week_1` | Who Am I Part 1 | 🧠 | `get_personality_test_mbti_status` (explicit override) |
| `super_strengths` | Super Strengths | 💪 | `get_super_strengths_status` (auto-resolved) |
| `rag_week_1` | Weekly Check-in | 🚦 | `get_weekly_rag_status` (explicit override) |

**Auto-resolution rule:** if `status_method` is not set in `get_metadata()`, the method name is derived as `get_{task_slug}_status` (hyphens replaced with underscores).

Unrecognised slugs fall back to `display_name` from the DB row, with a generic 📋 icon and empty URL.

**`get_metadata()` vs `$metadata`:** the activity metadata was originally a class property `$metadata`. As of v4.4.9 it is a method `get_metadata(): array` — URLs are built with `home_url()` so the portal works correctly on any domain or staging environment instead of hardcoding `https://mfsd.me`.

### 5.6 Progress Builder (`get_student_progress`)

Returns a nested array `[$week_num => [$slug => $activity_data]]`.

**Always shows at least weeks 1–3**, merging DB weeks with `[1, 2, 3]`. Weeks with no tasks in the DB are shown as a single `coming_soon` placeholder entry.

**Per-activity resolution (first pass):**

1. Call `get_task_progress_status($student_id, $slug)` — queries `wp_mfsd_task_progress` for the latest row.
2. If status is `locked` → emit `locked` entry, skip further resolution.
3. If status is `available` → emit `not_started`, skip further resolution.
4. Otherwise call the plugin-specific status method.
5. If `wp_mfsd_task_progress` says `completed`, override the status method result to `completed` — task_progress is the source of truth for completion.
6. If no status method exists, derive from task_progress value directly.

**Second pass (locking):** iterates all activities in week order. Once an incomplete (non-`completed`) activity is found, all subsequent `not_started` activities are changed to `locked`.

### 5.7 Plugin-Specific Status Methods

#### `get_solution_lens_status`
Queries `wp_mfsd_lens_sessions WHERE student_id = %d ORDER BY started_at DESC LIMIT 1`. Returns `not_started`, `in_progress`, or `completed` (includes `summary_type` when complete). Gracefully handles absent table.

#### `get_word_association_status`
Queries `wp_mfsd_word_associations`. Returns `not_started` (count=0), `in_progress` (responses exist but no `ai_summary`), or `completed` (at least one row has `ai_summary`). Includes all response rows and `has_ai_summary` flag.

#### `get_junk_jobs_status`
Queries `wp_mfsd_ai_junk_jobs_results WHERE user_id = %d` (single row per user). Maps `result->status` field through: `not_started` → `not_started`, `in_progress` → step 1, `reasons` → step 2, `completed` → step 4 (of 4). Includes decoded `jobs_json`, `ranking_json`, `reasons_json`, `analysis`, `mbti_type`.

#### `get_personality_test_mbti_status`
Queries `wp_mfsd_ptest_results WHERE user_id = %d AND week_num = %d AND test_type IN ('MBTI','COMBINED')`. If found: `completed` with `mbti_type`, `disc_primary`, DISC scores. If not found, falls back to `wp_mfsd_ptest_answers` answer count to determine `in_progress` (count > 0) or `not_started`. Progress out of 12 questions.

#### `get_weekly_rag_status`
Queries `wp_mfsd_rag_answers WHERE user_id = %d AND week_num = %d`. Returns `not_started`, `in_progress`, or `completed` (≥10 answers). Includes `total_score`, `breakdown` (R/A/G counts), and all answer rows.

#### `get_super_strengths_status`
Queries `wp_mfsd_ss_players` joined with `wp_mfsd_ss_games` for the student's most recent game. States:
- No player record → `not_started`
- Game status `submission`, `dealing`, `playing` → `in_progress` with player list and submission counts
- Game status `complete` → `completed` with `received_cards` (from `wp_mfsd_ss_cards` where `flagged = 0`), `all_players`, and `ai_summary`

**AI summary caching (super strengths):** when game is complete and no cached summary exists, calls `MFSD_SS_Game::generate_strengths_summary()` and stores result in user meta key `mfsd_ss_ai_{game_id}_{student_id}`.

### 5.8 Profile Picture Resolution

`get_profile_picture_url($student_id, $size = 80)` attempts sources in order:

1. **ProfilePress/avatar meta keys** (8 checked): `pp_profile_pic`, `pp_profile_photo`, `pp_profile_image`, `profilepress_profile_picture`, `profilepress_profile_image`, `wp_user_avatar`, `pp_custom_avatar`, `user_avatar`. Numeric values are treated as attachment IDs.
2. **`get_avatar()` HTML parsing** — regex-extracts `src`, skips gravatar.com URLs.
3. **`get_avatar_url()` with `default=404`** — skips result if it contains `404`.
4. **Final fallback:** `get_avatar_url()` with default WP fallback.

### 5.9 Course Percent (`get_course_percent`)

Calls `get_student_progress()` and counts completed / total activities, excluding `coming_soon` and `not_available`. Returns integer 0–100. Used for both the course card progress bar and the SVG ring in the student header — always in sync because both use the same underlying method.

---

## 6. Renderer (`MFSD_Parent_Portal_Renderer`)

Constructed with a `MFSD_Parent_Portal_Data` instance and a `$viewer_role` string (`'parent'` or `'student'`). Uses `ob_start()`/`ob_get_clean()` throughout.

### 6.1 View Dispatch

| Method | View |
|---|---|
| `render_landing_student($student_id)` | Student course list |
| `render_landing_parent($linked_students)` | Parent course list with student tabs |
| `render_student_self($student_id, $course_id)` | Student course detail (self-view) |
| `render($linked_students, $course_id)` | Parent course detail (child view) |

`render_student_self()` wraps the student in a synthetic `stdClass` (matching the shape returned by `get_linked_students`) and delegates to `render_student_section()` — same code path as the parent detail view.

### 6.2 Landing Tabs (parent only)

Rendered by `render_course_tabs()`. Three tabs: **My Courses** (active by default), **New Courses**, **All Courses**. The latter two render `render_coming_soon_panel()` placeholders. Tab state (active border, colour) is managed by `parent-portal.js` via `data-tab` / `data-panel` attributes.

Tabs are only added to the parent landing — not the student landing.

### 6.3 Course Cards

`render_course_card($course, $page_url, $student_id)` renders a card linking to:
```
{page_url}?course_id={course.id}&student_id={student_id}
```

Shows course thumbnail (or colour-coded initials placeholder) and a progress bar from `percent_complete`. Hover state managed via inline `onmouseover`/`onmouseout`.

### 6.4 Student Header (detail views)

Gradient card (`#4F46E5 → #6366F1`) showing:
- Avatar (80×80, circular)
- Student name, year group, school, primary-contact badge (if set)
- SVG progress ring (course-level %) rendered by `render_overall_progress()`

The progress ring is an inline SVG using `stroke-dasharray="{percent}, 100"` on a circular path. Rotated −90° so the arc starts at the top.

### 6.5 Week Sections

`render_week_section($week_num, $week_progress)` renders a collapsible accordion card.

**Hardcoded week names:**
- Week 1: "Week 1: Self Awareness & the Solution Lens"
- Week 2: "Week 2: Interest, barriers and turning dreams into plans"
- Week 3: "Week 3: High performance success beliefs & future direction"

Week 1 is expanded by default (`aria-expanded="true"`, no `hidden` attribute). All other weeks start collapsed.

Week header shows completion count (X of N activities) or "Coming Soon" if no activities are defined.

**Week icon:** `✅` all complete, `🔄` in progress, `⭕` not started, `🔒` all coming soon.

### 6.6 Activity Cards

`render_activity_card($activity_key, $activity)` renders a card as either `<a>` (clickable) or `<div>` (non-clickable).

**Clickability rules:**
- **Student:** `not_started`, `in_progress`, `completed` statuses are all clickable.
- **Parent:** only `completed` activities are clickable.

**Parent link URL:** when `$viewer_role = 'parent'`, `?student_id={current_student_id}` is appended to the activity URL so the target plugin can show the student's data rather than the parent's.

**Border colours:** `#10B981` (completed), `#F59E0B` (in_progress), `#E5E7EB` (all others). `coming_soon` and `locked` cards also have `opacity: 0.6`.

Activity card body renders different content per status:
- `locked` → lock message
- `coming_soon` → coming soon text (role-aware wording)
- `not_started` → "not started" text + "Start →" CTA if clickable
- `in_progress` → progress bar + `progress_text` + "Continue →" CTA if clickable; Super Strengths also shows player status pills
- `completed` → results summary (dispatched per `$activity_key`) + "View →" CTA if clickable

### 6.7 Activity Results Rendering

Dispatched from `render_activity_results($activity_key, $activity)`:

| Key | Method | Output |
|---|---|---|
| `word_association` | `render_word_association_results` | Count of words completed |
| `junk_jobs` | `render_junk_jobs_results` | Count of jobs identified |
| `personality_test_week_1` | `render_personality_results` | MBTI type + DISC primary |
| `rag_week_1/2/3` | `render_rag_results` | Total score + R/A/G breakdown |
| `super_strengths` | `render_super_strengths_results` | Count of strength cards received |
| All others | *(inline fallback)* | "You've completed this activity." / "Activity completed." |

Note: `solution_lens` has no dedicated results renderer — uses the fallback. The parent can follow the link to the Solution Lens page (which shows the student's session via `?student_id`).

### 6.8 Status Badges

`render_status_badge($status)` returns a `<span class="mfsd-pp__badge {modifier}">` with icon and label:

| Status | Icon | Label |
|---|---|---|
| `not_started` | ⭕ | Not Started |
| `in_progress` | 🔄 | In Progress |
| `completed` | ✅ | Completed |
| `coming_soon` | 🔒 | Coming Soon |
| `locked` | 🔒 | Locked |
| `not_available` | ❓ | Not Available |

---

## 7. Frontend Assets

### 7.1 CSS (`parent-portal.css`)

BEM namespace: `.mfsd-pp__`. Uses CSS custom properties (`var(--pp-*)`) for colours. Responsive grid for course cards (`repeat(auto-fit, minmax(280px, 1fr))`).

Note: Much of the renderer outputs inline styles with `!important` declarations to override theme CSS — the CSS file covers structural layout, not all visual styling.

### 7.2 JavaScript (`parent-portal.js`)

jQuery IIFE, two functions initialised at `document.ready`:

**`initCourseTabs()`** — delegated click on `.mfsd-pp__tab`. Reads `data-tab` attribute, updates `aria-selected`, toggles `data-panel` matching visibility. Active tab: `#4F46E5` colour + border; inactive: `#6B7280`.

**`initWeekAccordions()`** — direct click on `.mfsd-pp__week-header`. Reads `aria-expanded`, toggles `hidden` attribute on `.mfsd-pp__week-content`. Independent accordions — multiple weeks can be expanded simultaneously (the `closeOtherWeeks()` function is defined but commented out).

**`initProgressRingAnimation()`** — IntersectionObserver-based animation on `.mfsd-pp__progress-ring` elements. Defined but **not called** from `document.ready` — effectively dead code in the current version.

---

## 8. Database Dependencies

This plugin owns no tables. It reads from tables owned by other plugins:

| Table | Owner plugin | Used for |
|---|---|---|
| `wp_mfsd_parent_student_links` | mfsd-parent-portal\* | Parent↔student relationships |
| `wp_mfsd_courses` | mfsd-course-manager | Course list |
| `wp_mfsd_enrolments` | mfsd-course-manager | Student-course enrolments |
| `wp_mfsd_task_order` | mfsd-ordering | Task slug/week/sequence definitions |
| `wp_mfsd_task_progress` | mfsd-ordering | Per-student task completion status |
| `wp_mfsd_lens_sessions` | mfsd-solution-lens | Solution Lens session status |
| `wp_mfsd_word_associations` | mfsd-word-association | Word association responses |
| `wp_mfsd_ai_junk_jobs_results` | ai-junk-jobs | Junk Jobs completion and selections |
| `wp_mfsd_ptest_answers` | mfsd-personality-test | MBTI answer progress |
| `wp_mfsd_ptest_results` | mfsd-personality-test | MBTI/COMBINED results |
| `wp_mfsd_rag_answers` | mfsd-weekly-rag | RAG check-in answers |
| `wp_mfsd_ss_players` | mfsd-super-strengths-v2 | Super Strengths player records |
| `wp_mfsd_ss_games` | mfsd-super-strengths-v2 | Super Strengths game state |
| `wp_mfsd_ss_cards` | mfsd-super-strengths-v2 | Super Strengths strength cards |

\* `wp_mfsd_parent_student_links` is conceptually owned by this portal, but the table may be created by an external management tool.

**Defensive table checks:** `get_week_activities()`, `get_task_progress_status()`, `get_word_association_status()`, `get_junk_jobs_status()`, `get_super_strengths_status()`, and `get_weekly_rag_status()` all check `SHOW TABLES LIKE '{$table}'` before querying and return a safe empty/`not_available` result if the table is absent.

---

## 9. Security Model

- **Authentication gate:** `render_portal()` returns a notice and exits immediately if `!is_user_logged_in()`.
- **Parent-student authorisation:** parent course detail view validates that the requested `student_id` appears in the parent's linked students list before rendering. Mismatch returns a warning notice.
- **Output escaping:** all dynamic HTML output uses `esc_html()`, `esc_url()`, `esc_attr()`.
- **Database queries:** all queries with user input use `$wpdb->prepare()`.
- **No nonce/CSRF:** not required — this is a read-only page with no state-changing operations.

---

## 10. Known Issues / Notes

- **`initProgressRingAnimation()` unused:** defined in `parent-portal.js` but never called from `document.ready`. If the SVG ring animation is desired on scroll, a `initProgressRingAnimation()` call needs to be added.
- **`solution_lens` has no results renderer:** completed Solution Lens cards show only "Activity completed." in the portal. A dedicated `render_solution_lens_results()` could be added to show summary type or session details.

---

## 11. Version History

| Version | Ticket | Change |
|---|---|---|
| 4.4.9 | — | Fix version constant mismatch; replace hardcoded `https://mfsd.me` task URLs with `home_url()` |
| 4.4.8 | MYF-189 | Correct hardcoded week names on course detail screen |
| 4.4.7 | MYF-182 | Append `?student_id` to task URLs when parent views portal |
| 4.4.6 | MYF-182 | Fix Solution Lens URL slug (`solution-lens` → `the-solution-lens`) |
| 4.4.5 | MYF-182 | Parents only get clickable links on `completed` activities |
| 4.4.4 | MYF-182 | Text fixes — back link label and personality assessment name |
| 4.4.3 | MYF-181 | Suppress WordPress page title via `mfsd-no-page-title` body class |
| 4.4.2 | MYF-181 | Subtitle text simplification |
| 4.4.1 | MYF-181 | Tabs only on parent landing, not student landing |
| 4.4.0 | MYF-181 | Add New Courses and All Courses tabs to landing page |
| ~4.3.x | MYF-126 | Derive course % from `get_student_progress()` to keep ring in sync |
| ~4.2.x | MYF-117 | Avatar sourced from ProfilePress meta keys |
| ~4.1.x | MYF-115 | Replace hardcoded activity array with live `wp_mfsd_task_order` query |
