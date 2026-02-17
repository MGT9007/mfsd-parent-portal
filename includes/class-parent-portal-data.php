<?php
/**
 * Parent Portal Data Layer
 * 
 * Handles all database queries for fetching linked students
 * and their activity progress across all weeks.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MFSD_Parent_Portal_Data {
    
    private $wpdb;
    
    // Activity definitions - easily extensible for new weeks/activities
    private $activities = [
        1 => [ // Week 1
            'word_association' => [
                'name' => 'Word Association',
                'icon' => '💭',
                'description' => 'Exploring thought patterns and associations'
            ],
            'junk_jobs' => [
                'name' => 'Junk Jobs',
                'icon' => '🗑️',
                'description' => 'Identifying careers to avoid'
            ],
            'personality_test_mbti' => [
                'name' => 'Personality Test (MBTI)',
                'icon' => '🧠',
                'description' => 'Myers-Briggs personality assessment'
            ],
            'weekly_rag' => [
                'name' => 'Weekly Check-in',
                'icon' => '🚦',
                'description' => 'Red/Amber/Green weekly reflection'
            ],
            'super_strengths' => [
                'name' => 'Super Strengths',
                'icon' => '💪',
                'description' => 'Discovering personal strengths',
                'coming_soon' => true
            ]
        ],
        2 => [ // Week 2 - placeholder
            'placeholder' => [
                'name' => 'Week 2 Activities',
                'icon' => '📚',
                'description' => 'Coming soon',
                'coming_soon' => true
            ]
        ],
        3 => [ // Week 3 - placeholder
            'placeholder' => [
                'name' => 'Week 3 Activities',
                'icon' => '📚',
                'description' => 'Coming soon',
                'coming_soon' => true
            ]
        ]
    ];
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get all students linked to a parent
     */
    public function get_linked_students($parent_user_id) {
        $table = $this->wpdb->prefix . 'mfsd_parent_student_links';
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                psl.*,
                u.display_name as student_name,
                u.user_email as student_email
             FROM {$table} psl
             JOIN {$this->wpdb->users} u ON psl.student_user_id = u.ID
             WHERE psl.parent_user_id = %d
             AND psl.link_status = 'active'
             ORDER BY psl.is_primary_contact DESC, u.display_name ASC",
            $parent_user_id
        ));
        
        // Enrich with user meta (year group, school, etc.)
        foreach ($results as &$student) {
            $student->year_group = get_user_meta($student->student_user_id, 'year_group', true);
            $student->school = get_user_meta($student->student_user_id, 'school', true);
            $student->avatar_url = get_avatar_url($student->student_user_id, ['size' => 80]);
        }
        
        return $results;
    }
    
    /**
     * Get activity definitions for a week
     */
    public function get_week_activities($week_num) {
        return $this->activities[$week_num] ?? [];
    }
    
    /**
     * Get all week numbers available
     */
    public function get_available_weeks() {
        return array_keys($this->activities);
    }
    
    /**
     * Get complete progress for a student across all weeks
     */
    public function get_student_progress($student_id) {
        $progress = [];
        
        foreach ($this->activities as $week_num => $week_activities) {
            $progress[$week_num] = [];
            
            foreach ($week_activities as $activity_key => $activity_info) {
                if (!empty($activity_info['coming_soon'])) {
                    $progress[$week_num][$activity_key] = [
                        'status' => 'coming_soon',
                        'info' => $activity_info
                    ];
                    continue;
                }
                
                $method = "get_{$activity_key}_status";
                if (method_exists($this, $method)) {
                    $status_data = $this->$method($student_id, $week_num);
                    $status_data['info'] = $activity_info;
                    $progress[$week_num][$activity_key] = $status_data;
                }
            }
        }
        
        return $progress;
    }
    
    /**
     * Word Association Status
     */
    private function get_word_association_status($student_id, $week_num = 1) {
        $table = $this->wpdb->prefix . 'mfsd_word_associations';
        
        // Check if table exists
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return ['status' => 'not_available', 'message' => 'Activity not yet configured'];
        }
        
        // Count responses
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $student_id
        ));
        
        if ($count == 0) {
            return [
                'status' => 'not_started',
                'progress' => 0,
                'progress_text' => 'Not started'
            ];
        }
        
        // Get responses with AI summaries
        $responses = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT wa.*, fc.word, fc.category 
             FROM {$table} wa
             LEFT JOIN {$this->wpdb->prefix}mfsd_flashcards_cards fc ON wa.card_id = fc.id
             WHERE wa.user_id = %d
             ORDER BY wa.created_at DESC",
            $student_id
        ));
        
        // Check if all have AI summaries (indicates completion)
        $with_summary = 0;
        foreach ($responses as $r) {
            if (!empty($r->ai_summary)) {
                $with_summary++;
            }
        }
        
        $has_summaries = $with_summary > 0;
        
        return [
            'status' => $has_summaries ? 'completed' : 'in_progress',
            'progress' => $count,
            'progress_text' => $count . ' words completed',
            'responses' => $responses,
            'has_ai_summary' => $has_summaries
        ];
    }
    
    /**
     * Junk Jobs Status
     */
    private function get_junk_jobs_status($student_id, $week_num = 1) {
        $table = $this->wpdb->prefix . 'mfsd_ai_junk_jobs_results';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return ['status' => 'not_available', 'message' => 'Activity not yet configured'];
        }
        
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $student_id
        ));
        
        if (!$result) {
            return [
                'status' => 'not_started',
                'progress' => 0,
                'progress_text' => 'Not started'
            ];
        }
        
        // Map database status to display
        $status_map = [
            'not_started' => ['status' => 'not_started', 'step' => 0, 'text' => 'Not started'],
            'in_progress' => ['status' => 'in_progress', 'step' => 1, 'text' => 'Selecting jobs'],
            'reasons'     => ['status' => 'in_progress', 'step' => 2, 'text' => 'Writing reasons'],
            'completed'   => ['status' => 'completed', 'step' => 4, 'text' => 'Completed']
        ];
        
        $status_info = $status_map[$result->status] ?? $status_map['not_started'];
        
        return [
            'status' => $status_info['status'],
            'db_status' => $result->status,
            'progress' => $status_info['step'],
            'progress_max' => 4,
            'progress_text' => $status_info['text'],
            'jobs' => !empty($result->jobs_json) ? json_decode($result->jobs_json, true) : [],
            'ranking' => !empty($result->ranking_json) ? json_decode($result->ranking_json, true) : [],
            'reasons' => !empty($result->reasons_json) ? json_decode($result->reasons_json, true) : [],
            'analysis' => $result->analysis,
            'mbti_type' => $result->mbti_type
        ];
    }
    
    /**
     * Personality Test (MBTI) Status
     */
    private function get_personality_test_mbti_status($student_id, $week_num = 1) {
        $answers_table = $this->wpdb->prefix . 'mfsd_ptest_answers';
        $results_table = $this->wpdb->prefix . 'mfsd_ptest_results';
        
        // Check for completed result first
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$results_table} 
             WHERE user_id = %d AND week_num = %d AND test_type IN ('MBTI', 'COMBINED')
             ORDER BY id DESC LIMIT 1",
            $student_id, $week_num
        ));
        
        if ($result) {
            return [
                'status' => 'completed',
                'progress' => 12,
                'progress_max' => 12,
                'progress_text' => 'Completed',
                'mbti_type' => $result->mbti_type,
                'ai_summary' => $result->ai_summary,
                'disc_scores' => [
                    'D' => $result->disc_d_score,
                    'I' => $result->disc_i_score,
                    'S' => $result->disc_s_score,
                    'C' => $result->disc_c_score
                ],
                'disc_primary' => $result->disc_primary
            ];
        }
        
        // Check for in-progress answers
        $answer_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$answers_table} 
             WHERE user_id = %d AND week_num = %d AND q_type = 'MBTI'",
            $student_id, $week_num
        ));
        
        if ($answer_count > 0) {
            return [
                'status' => 'in_progress',
                'progress' => (int)$answer_count,
                'progress_max' => 12,
                'progress_text' => "{$answer_count} of 12 questions"
            ];
        }
        
        return [
            'status' => 'not_started',
            'progress' => 0,
            'progress_max' => 12,
            'progress_text' => 'Not started'
        ];
    }
    
    /**
     * Weekly RAG Status
     */
    private function get_weekly_rag_status($student_id, $week_num = 1) {
        $table = $this->wpdb->prefix . 'mfsd_rag_answers';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return ['status' => 'not_available', 'message' => 'Activity not yet configured'];
        }
        
        $answers = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE user_id = %d AND week_num = %d
             ORDER BY question_id ASC",
            $student_id, $week_num
        ));
        
        $count = count($answers);
        $expected = 10; // Adjust based on your RAG questions per week
        
        if ($count == 0) {
            return [
                'status' => 'not_started',
                'progress' => 0,
                'progress_max' => $expected,
                'progress_text' => 'Not started'
            ];
        }
        
        // Calculate scores
        $total_score = 0;
        $score_breakdown = ['R' => 0, 'A' => 0, 'G' => 0];
        foreach ($answers as $a) {
            $total_score += (int)$a->score;
            if (isset($score_breakdown[$a->answer])) {
                $score_breakdown[$a->answer]++;
            }
        }
        
        $is_complete = $count >= $expected;
        
        return [
            'status' => $is_complete ? 'completed' : 'in_progress',
            'progress' => $count,
            'progress_max' => $expected,
            'progress_text' => $is_complete ? 'Completed' : "{$count} of {$expected} questions",
            'answers' => $answers,
            'total_score' => $total_score,
            'breakdown' => $score_breakdown
        ];
    }
    
    /**
     * Super Strengths Status (placeholder for future)
     */
    private function get_super_strengths_status($student_id, $week_num = 1) {
        // This will be implemented when the activity is built
        return [
            'status' => 'coming_soon',
            'progress_text' => 'Coming soon'
        ];
    }
}
