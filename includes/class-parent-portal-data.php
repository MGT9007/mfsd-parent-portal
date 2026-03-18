<?php
/**
 * Parent Portal Data Layer
 *
 * Status resolution order:
 *   1. wp_mfsd_task_progress  → authoritative for locked / available
 *   2. Plugin-specific tables → used for in_progress detail and completed results
 */

if (!defined('ABSPATH')) exit;

class MFSD_Parent_Portal_Data {

    private $wpdb;

    // Display metadata for each activity.
    // 'task_slug' maps to the value stored in wp_mfsd_task_progress.task_slug
    // (only needed when it differs from the array key).
    private $activities = [
        1 => [
            'word_association' => [
                'name'        => 'Word Association',
                'icon'        => '💭',
                'description' => 'Exploring thought patterns and associations',
                'url'         => 'https://mfsd.me/my-future-self-foundation-course/week-1/word-association/',
            ],
            'junk_jobs' => [
                'name'        => 'Junk Jobs',
                'icon'        => '🗑️',
                'description' => 'Identifying careers to avoid',
                'url'         => 'https://mfsd.me/my-future-self-foundation-course/week-1/junk-jobs/',
            ],
            'personality_test_mbti' => [
                'name'        => 'Personality Test (MBTI)',
                'icon'        => '🧠',
                'description' => 'Myers-Briggs personality assessment',
                'task_slug'   => 'personality_test',
                'url'         => 'https://mfsd.me/my-future-self-foundation-course/week-1/week-1-personality-test/',
            ],
            'weekly_rag' => [
                'name'        => 'Weekly Check-in',
                'icon'        => '🚦',
                'description' => 'Red/Amber/Green weekly reflection',
                'url'         => 'https://mfsd.me/my-future-self-foundation-course/week-1/week-1-rag/',
            ],
            'super_strengths' => [
                'name'        => 'Super Strengths',
                'icon'        => '💪',
                'description' => 'Discovering personal strengths',
                'url'         => 'https://mfsd.me/my-future-self-foundation-course/week-1/super-strengths/',
            ],
        ],
        2 => [
            'placeholder' => [
                'name'        => 'Week 2 Activities',
                'icon'        => '📚',
                'description' => 'Coming soon',
                'coming_soon' => true,
            ],
        ],
        3 => [
            'placeholder' => [
                'name'        => 'Week 3 Activities',
                'icon'        => '📚',
                'description' => 'Coming soon',
                'coming_soon' => true,
            ],
        ],
    ];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    // ── Course-level % (all weeks, from task management tables) ──────────────
    // This matches the landing page calculation exactly.
    public function get_course_percent($student_id) {
        $order_table    = $this->wpdb->prefix . 'mfsd_task_order';
        $progress_table = $this->wpdb->prefix . 'mfsd_task_progress';

        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$order_table}'") !== $order_table) {
            return 0;
        }

        // Get the course this student is enrolled on
        $enrol_table = $this->wpdb->prefix . 'mfsd_enrolments';
        $course_id   = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT course_id FROM {$enrol_table} WHERE student_id = %d LIMIT 1",
            $student_id
        ));

        if (!$course_id) return 0;

        $total = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$order_table} WHERE course_id = %d AND active = 1",
            $course_id
        ));

        if (!$total) return 0;

        $completed = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$progress_table}
             WHERE student_id = %d AND course_id = %d AND status = 'completed'",
            $student_id, $course_id
        ));

        return round(($completed / $total) * 100);
    }

    // ── Courses ───────────────────────────────────────────────────────────────

    /**
     * Get all courses a student is enrolled on, with overall % complete.
     * Returns array of objects with: id, course_name, image_url, percent_complete, total_tasks, completed_tasks
     */
    public function get_student_courses($student_id) {
        $courses_table  = $this->wpdb->prefix . 'mfsd_courses';
        $enrol_table    = $this->wpdb->prefix . 'mfsd_enrolments';
        $progress_table = $this->wpdb->prefix . 'mfsd_task_progress';
        $order_table    = $this->wpdb->prefix . 'mfsd_task_order';

        // Guard: tables may not exist
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$courses_table}'") !== $courses_table) {
            return [];
        }

        // Check whether image_url column exists yet (added during course setup phase)
        $has_image_col = $this->wpdb->get_results(
            "SHOW COLUMNS FROM {$courses_table} LIKE 'image_url'"
        );
        $image_select = $has_image_col ? 'c.image_url' : "'' AS image_url";

        // Enrolled active courses for this student
        $courses = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT c.id, c.course_name, {$image_select}
             FROM {$courses_table} c
             JOIN {$enrol_table} e ON e.course_id = c.id
             WHERE e.student_id = %d AND c.active = 1
             ORDER BY e.enrolled_date ASC",
            $student_id
        ));

        foreach ($courses as &$course) {
            // Use the same plugin-table data source as the detail page
            // so landing page % always matches the detail page %
            $progress = $this->get_student_progress($student_id);

            $total = $completed = 0;
            foreach ($progress as $week_progress) {
                foreach ($week_progress as $activity) {
                    if (!in_array($activity['status'], ['coming_soon', 'not_available', 'locked'])) {
                        $total++;
                        if ($activity['status'] === 'completed') $completed++;
                    }
                }
            }

            $course->total_tasks      = $total;
            $course->completed_tasks  = $completed;
            $course->percent_complete = $total > 0 ? round(($completed / $total) * 100) : 0;
        }

        return $courses;
    }

    // ── Linking table ─────────────────────────────────────────────────────────
    public function get_linked_students($parent_user_id) {

        $table   = $this->wpdb->prefix . 'mfsd_parent_student_links';
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT psl.*, u.display_name AS student_name, u.user_email AS student_email
             FROM {$table} psl
             JOIN {$this->wpdb->users} u ON psl.student_user_id = u.ID
             WHERE psl.parent_user_id = %d AND psl.link_status = 'active'
             ORDER BY psl.is_primary_contact DESC, u.display_name ASC",
            $parent_user_id
        ));
        foreach ($results as &$student) {
            $student->year_group = get_user_meta($student->student_user_id, 'year_group', true);
            $student->school     = get_user_meta($student->student_user_id, 'school', true);
            $student->avatar_url = get_avatar_url($student->student_user_id, ['size' => 80]);
        }
        return $results;
    }

    public function get_week_activities($week_num) {
        return $this->activities[$week_num] ?? [];
    }

    public function get_available_weeks() {
        return array_keys($this->activities);
    }

    // ── Master progress builder ───────────────────────────────────────────────
    public function get_student_progress($student_id) {
        $progress = [];

        foreach ($this->activities as $week_num => $week_activities) {
            $progress[$week_num] = [];

            foreach ($week_activities as $activity_key => $activity_info) {

                // Hard coming-soon (not yet built)
                if (!empty($activity_info['coming_soon'])) {
                    $progress[$week_num][$activity_key] = [
                        'status' => 'coming_soon',
                        'info'   => $activity_info,
                    ];
                    continue;
                }

                // Resolve the task_slug for the course management table
                $task_slug = $activity_info['task_slug'] ?? $activity_key;

                // ── Step 1: check course management tables ─────────────────
                $cp_status = $this->get_task_progress_status($student_id, $task_slug);

                if ($cp_status === 'locked') {
                    $progress[$week_num][$activity_key] = [
                        'status'        => 'locked',
                        'progress_text' => 'Complete the previous activity to unlock this one.',
                        'info'          => $activity_info,
                    ];
                    continue;
                }

                if ($cp_status === 'available') {
                    $progress[$week_num][$activity_key] = [
                        'status'        => 'not_started',
                        'progress_text' => 'Not started',
                        'info'          => $activity_info,
                    ];
                    continue;
                }

                // ── Step 2: in_progress / completed (or no task_progress row)
                // Call the plugin-specific method for rich data.
                $method = "get_{$activity_key}_status";
                if (method_exists($this, $method)) {
                    $status_data         = $this->$method($student_id, $week_num);
                    $status_data['info'] = $activity_info;

                    // If task_progress has a definitive completed flag, honour it
                    // even if the plugin table disagrees (e.g. edge cases).
                    if ($cp_status === 'completed' && $status_data['status'] !== 'completed') {
                        $status_data['status'] = 'completed';
                    }

                    $progress[$week_num][$activity_key] = $status_data;
                }
            }
        }

        return $progress;
    }

    // ── Course management: read status from wp_mfsd_task_progress ────────────
    /**
     * Returns 'locked' | 'available' | 'in_progress' | 'completed' | null
     * null means the table doesn't exist or no row found (fall through to plugin tables).
     */
    private function get_task_progress_status($student_id, $task_slug) {
        $table = $this->wpdb->prefix . 'mfsd_task_progress';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return null;
        }
        $status = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT status FROM {$table}
             WHERE student_id = %d AND task_slug = %s
             ORDER BY id DESC LIMIT 1",
            $student_id, $task_slug
        ));
        return $status ?: null;
    }

    // ── Plugin-specific status methods ────────────────────────────────────────

    private function get_word_association_status($student_id, $week_num = 1) {
        $table = $this->wpdb->prefix . 'mfsd_word_associations';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return ['status' => 'not_available', 'message' => 'Activity not yet configured'];
        }
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $student_id
        ));
        if ($count == 0) {
            return ['status' => 'not_started', 'progress' => 0, 'progress_text' => 'Not started'];
        }
        $responses = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT wa.*, fc.word, fc.category
             FROM {$table} wa
             LEFT JOIN {$this->wpdb->prefix}mfsd_flashcards_cards fc ON wa.card_id = fc.id
             WHERE wa.user_id = %d ORDER BY wa.created_at DESC",
            $student_id
        ));
        $with_summary = count(array_filter((array) $responses, fn($r) => !empty($r->ai_summary)));
        return [
            'status'         => $with_summary > 0 ? 'completed' : 'in_progress',
            'progress'       => $count,
            'progress_text'  => $count . ' words completed',
            'responses'      => $responses,
            'has_ai_summary' => $with_summary > 0,
        ];
    }

    private function get_junk_jobs_status($student_id, $week_num = 1) {
        $table = $this->wpdb->prefix . 'mfsd_ai_junk_jobs_results';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return ['status' => 'not_available', 'message' => 'Activity not yet configured'];
        }
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d", $student_id
        ));
        if (!$result) {
            return ['status' => 'not_started', 'progress' => 0, 'progress_text' => 'Not started'];
        }
        $status_map = [
            'not_started' => ['status' => 'not_started', 'step' => 0, 'text' => 'Not started'],
            'in_progress' => ['status' => 'in_progress', 'step' => 1, 'text' => 'Selecting jobs'],
            'reasons'     => ['status' => 'in_progress', 'step' => 2, 'text' => 'Writing reasons'],
            'completed'   => ['status' => 'completed',   'step' => 4, 'text' => 'Completed'],
        ];
        $info = $status_map[$result->status] ?? $status_map['not_started'];
        return [
            'status'        => $info['status'],
            'db_status'     => $result->status,
            'progress'      => $info['step'],
            'progress_max'  => 4,
            'progress_text' => $info['text'],
            'jobs'          => !empty($result->jobs_json)    ? json_decode($result->jobs_json, true)    : [],
            'ranking'       => !empty($result->ranking_json) ? json_decode($result->ranking_json, true) : [],
            'reasons'       => !empty($result->reasons_json) ? json_decode($result->reasons_json, true) : [],
            'analysis'      => $result->analysis,
            'mbti_type'     => $result->mbti_type,
        ];
    }

    private function get_personality_test_mbti_status($student_id, $week_num = 1) {
        $answers_table = $this->wpdb->prefix . 'mfsd_ptest_answers';
        $results_table = $this->wpdb->prefix . 'mfsd_ptest_results';
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$results_table}
             WHERE user_id = %d AND week_num = %d AND test_type IN ('MBTI','COMBINED')
             ORDER BY id DESC LIMIT 1",
            $student_id, $week_num
        ));
        if ($result) {
            return [
                'status'        => 'completed',
                'progress'      => 12,
                'progress_max'  => 12,
                'progress_text' => 'Completed',
                'mbti_type'     => $result->mbti_type,
                'ai_summary'    => $result->ai_summary,
                'disc_scores'   => [
                    'D' => $result->disc_d_score,
                    'I' => $result->disc_i_score,
                    'S' => $result->disc_s_score,
                    'C' => $result->disc_c_score,
                ],
                'disc_primary' => $result->disc_primary,
            ];
        }
        $answer_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$answers_table}
             WHERE user_id = %d AND week_num = %d AND q_type = 'MBTI'",
            $student_id, $week_num
        ));
        if ($answer_count > 0) {
            return [
                'status'        => 'in_progress',
                'progress'      => (int) $answer_count,
                'progress_max'  => 12,
                'progress_text' => "{$answer_count} of 12 questions",
            ];
        }
        return ['status' => 'not_started', 'progress' => 0, 'progress_max' => 12, 'progress_text' => 'Not started'];
    }

    private function get_weekly_rag_status($student_id, $week_num = 1) {
        $table = $this->wpdb->prefix . 'mfsd_rag_answers';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return ['status' => 'not_available', 'message' => 'Activity not yet configured'];
        }
        $answers  = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND week_num = %d ORDER BY question_id ASC",
            $student_id, $week_num
        ));
        $count    = count($answers);
        $expected = 10;
        if ($count == 0) {
            return ['status' => 'not_started', 'progress' => 0, 'progress_max' => $expected, 'progress_text' => 'Not started'];
        }
        $total_score = 0;
        $breakdown   = ['R' => 0, 'A' => 0, 'G' => 0];
        foreach ($answers as $a) {
            $total_score += (int) $a->score;
            if (isset($breakdown[$a->answer])) $breakdown[$a->answer]++;
        }
        $is_complete = $count >= $expected;
        return [
            'status'        => $is_complete ? 'completed' : 'in_progress',
            'progress'      => $count,
            'progress_max'  => $expected,
            'progress_text' => $is_complete ? 'Completed' : "{$count} of {$expected} questions",
            'answers'       => $answers,
            'total_score'   => $total_score,
            'breakdown'     => $breakdown,
        ];
    }

    private function get_super_strengths_status($student_id, $week_num = 1) {
        $players_table = $this->wpdb->prefix . 'mfsd_ss_players';
        $games_table   = $this->wpdb->prefix . 'mfsd_ss_games';
        $cards_table   = $this->wpdb->prefix . 'mfsd_ss_cards';

        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$players_table}'") !== $players_table) {
            return ['status' => 'not_available', 'message' => 'Super Strengths plugin not active'];
        }

        $player = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT p.*, g.status AS game_status, g.id AS game_id
             FROM {$players_table} p
             JOIN {$games_table} g ON g.id = p.game_id
             WHERE p.user_id = %d
             ORDER BY g.created_at DESC LIMIT 1",
            $student_id
        ), ARRAY_A);

        if (!$player) {
            return ['status' => 'not_started', 'progress_text' => 'Not started'];
        }

        $game_id     = (int) $player['game_id'];
        $player_id   = (int) $player['id'];
        $all_players = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT display_name, role, submission_status
             FROM {$players_table} WHERE game_id = %d
             ORDER BY COALESCE(turn_order, id) ASC",
            $game_id
        ), ARRAY_A);

        $submitted = count(array_filter($all_players, fn($p) => $p['submission_status'] === 'submitted'));
        $total     = count($all_players);

        if (in_array($player['game_status'], ['submission', 'dealing', 'playing'])) {
            $labels = [
                'submission' => 'Writing cards — ' . $submitted . ' of ' . $total . ' submitted',
                'dealing'    => 'Dealing cards',
                'playing'    => 'Game in progress',
            ];
            return [
                'status'        => 'in_progress',
                'game_status'   => $player['game_status'],
                'progress'      => $submitted,
                'progress_max'  => $total,
                'progress_text' => $labels[$player['game_status']] ?? 'In progress',
                'all_players'   => $all_players,
            ];
        }

        if ($player['game_status'] === 'complete') {
            $received = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT c.strength_text, p.display_name AS author_name
                 FROM {$cards_table} c
                 JOIN {$players_table} p ON p.id = c.author_player_id
                 WHERE c.game_id = %d AND c.target_player_id = %d AND c.flagged = 0
                 ORDER BY c.strength_text ASC",
                $game_id, $player_id
            ), ARRAY_A);

            $cache_key  = 'mfsd_ss_ai_' . $game_id . '_' . $student_id;
            $ai_summary = get_user_meta($student_id, $cache_key, true);
            if (empty($ai_summary) && !empty($received) && class_exists('MFSD_SS_Game')) {
                $user_obj   = get_userdata($student_id);
                $name       = $user_obj ? $user_obj->display_name : 'your child';
                $ai_summary = MFSD_SS_Game::generate_strengths_summary($received, $name);
                if ($ai_summary) update_user_meta($student_id, $cache_key, $ai_summary);
            }

            return [
                'status'         => 'completed',
                'progress_text'  => 'Completed',
                'received_cards' => $received,
                'ai_summary'     => $ai_summary ?: null,
                'all_players'    => $all_players,
            ];
        }

        return ['status' => 'not_started', 'progress_text' => 'Not started'];
    }
}