<?php
/**
 * Parent Portal Renderer
 */

if (!defined('ABSPATH')) exit;

class MFSD_Parent_Portal_Renderer {

    private $data;

    public function __construct(MFSD_Parent_Portal_Data $data) {
        $this->data = $data;
    }

    public function render($linked_students) {
        ob_start();
        ?>
        <div class="mfsd-pp">
            <div class="mfsd-pp__header">
                <h1 class="mfsd-pp__title">Parent Portal</h1>
                <p class="mfsd-pp__subtitle">Track your child's progress in the High Performance Pathway</p>
            </div>
            <?php foreach ($linked_students as $student): ?>
                <?php $this->render_student_section($student); ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_student_section($student) {
        $progress = $this->data->get_student_progress($student->student_user_id);
        ?>
        <div class="mfsd-pp__student" data-student-id="<?php echo esc_attr($student->student_user_id); ?>">
            <div class="mfsd-pp__student-header">
                <div class="mfsd-pp__student-avatar">
                    <img src="<?php echo esc_url($student->avatar_url); ?>" alt="">
                </div>
                <div class="mfsd-pp__student-info">
                    <h2 class="mfsd-pp__student-name"><?php echo esc_html($student->student_name); ?></h2>
                    <div class="mfsd-pp__student-meta">
                        <?php if (!empty($student->year_group)): ?>
                            <span class="mfsd-pp__meta-item">
                                <span class="mfsd-pp__meta-icon">📚</span>
                                Year <?php echo esc_html($student->year_group); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($student->school)): ?>
                            <span class="mfsd-pp__meta-item">
                                <span class="mfsd-pp__meta-icon">🏫</span>
                                <?php echo esc_html($student->school); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($student->is_primary_contact): ?>
                            <span class="mfsd-pp__meta-item mfsd-pp__meta-item--primary">
                                <span class="mfsd-pp__meta-icon">⭐</span>
                                Primary Contact
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mfsd-pp__student-overall">
                    <?php echo $this->render_overall_progress($progress); ?>
                </div>
            </div>
            <div class="mfsd-pp__weeks">
                <?php foreach ($progress as $week_num => $week_progress): ?>
                    <?php $this->render_week_section($week_num, $week_progress); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function render_overall_progress($progress) {
        $total = $completed = 0;
        foreach ($progress as $week_progress) {
            foreach ($week_progress as $activity) {
                if (!in_array($activity['status'], ['coming_soon', 'not_available'])) {
                    $total++;
                    if ($activity['status'] === 'completed') $completed++;
                }
            }
        }
        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
        ob_start();
        ?>
        <div class="mfsd-pp__overall-progress">
            <div class="mfsd-pp__progress-ring" style="--progress: <?php echo $percentage; ?>">
                <svg viewBox="0 0 36 36">
                    <path class="mfsd-pp__progress-bg"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                    <path class="mfsd-pp__progress-fill"
                        stroke-dasharray="<?php echo $percentage; ?>, 100"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                </svg>
                <span class="mfsd-pp__progress-text"><?php echo $percentage; ?>%</span>
            </div>
            <span class="mfsd-pp__progress-label"><?php echo $completed; ?>/<?php echo $total; ?> Complete</span>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_week_section($week_num, $week_progress) {
        $week_names = [
            1 => 'Week 1: Foundation',
            2 => 'Week 2: Exploration',
            3 => 'Week 3: Discovery'
        ];
        $week_name         = $week_names[$week_num] ?? "Week {$week_num}";
        $is_all_coming_soon = $this->is_week_coming_soon($week_progress);
        ?>
        <div class="mfsd-pp__week <?php echo $is_all_coming_soon ? 'mfsd-pp__week--coming-soon' : ''; ?>"
             data-week="<?php echo $week_num; ?>">
            <button class="mfsd-pp__week-header"
                    aria-expanded="<?php echo $week_num === 1 ? 'true' : 'false'; ?>">
                <h3 class="mfsd-pp__week-title">
                    <span class="mfsd-pp__week-icon"><?php echo $this->get_week_icon($week_num, $week_progress); ?></span>
                    <?php echo esc_html($week_name); ?>
                </h3>
                <div class="mfsd-pp__week-summary">
                    <?php echo $this->get_week_summary($week_progress); ?>
                </div>
                <span class="mfsd-pp__week-toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </button>
            <div class="mfsd-pp__week-content" <?php echo $week_num !== 1 ? 'hidden' : ''; ?>>
                <div class="mfsd-pp__activities">
                    <?php foreach ($week_progress as $activity_key => $activity): ?>
                        <?php $this->render_activity_card($activity_key, $activity); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function is_week_coming_soon($week_progress) {
        foreach ($week_progress as $activity) {
            if ($activity['status'] !== 'coming_soon') return false;
        }
        return true;
    }

    private function get_week_icon($week_num, $week_progress) {
        $completed = $total = 0;
        foreach ($week_progress as $activity) {
            if (!in_array($activity['status'], ['coming_soon', 'not_available'])) {
                $total++;
                if ($activity['status'] === 'completed') $completed++;
            }
        }
        if ($total === 0)             return '🔒';
        if ($completed === $total)    return '✅';
        if ($completed > 0)           return '🔄';
        return '⭕';
    }

    private function get_week_summary($week_progress) {
        $completed = $total = 0;
        foreach ($week_progress as $activity) {
            if (!in_array($activity['status'], ['coming_soon', 'not_available'])) {
                $total++;
                if ($activity['status'] === 'completed') $completed++;
            }
        }
        if ($total === 0) {
            return '<span class="mfsd-pp__week-status mfsd-pp__week-status--coming">Coming Soon</span>';
        }
        return "<span class='mfsd-pp__week-status'>{$completed} of {$total} activities completed</span>";
    }

    private function render_activity_card($activity_key, $activity) {
        $status = $activity['status'];
        $info   = $activity['info'] ?? [];
        ?>
        <div class="mfsd-pp__activity mfsd-pp__activity--<?php echo esc_attr($status); ?>"
             data-activity="<?php echo esc_attr($activity_key); ?>">

            <div class="mfsd-pp__activity-header">
                <span class="mfsd-pp__activity-icon"><?php echo $info['icon'] ?? '📝'; ?></span>
                <div class="mfsd-pp__activity-title-wrap">
                    <h4 class="mfsd-pp__activity-title"><?php echo esc_html($info['name'] ?? $activity_key); ?></h4>
                    <p class="mfsd-pp__activity-desc"><?php echo esc_html($info['description'] ?? ''); ?></p>
                </div>
                <?php echo $this->render_status_badge($status); ?>
            </div>

            <?php if ($status === 'coming_soon'): ?>
                <div class="mfsd-pp__activity-body mfsd-pp__activity-body--coming">
                    <p>This activity will be available soon.</p>
                </div>

            <?php elseif ($status === 'not_started'): ?>
                <div class="mfsd-pp__activity-body">
                    <p class="mfsd-pp__not-started-text">Your child hasn't started this activity yet.</p>
                </div>

            <?php elseif ($status === 'in_progress'): ?>
                <div class="mfsd-pp__activity-body">
                    <?php echo $this->render_progress_bar($activity); ?>
                    <p class="mfsd-pp__progress-note"><?php echo esc_html($activity['progress_text'] ?? ''); ?></p>
                    <?php if ($activity_key === 'super_strengths' && !empty($activity['all_players'])): ?>
                        <?php $this->render_ss_player_status($activity['all_players']); ?>
                    <?php endif; ?>
                </div>

            <?php elseif ($status === 'completed'): ?>
                <div class="mfsd-pp__activity-body">
                    <?php echo $this->render_activity_results($activity_key, $activity); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_status_badge($status) {
        $badges = [
            'not_started'   => ['⭕', 'Not Started',   'mfsd-pp__badge--not-started'],
            'in_progress'   => ['🔄', 'In Progress',   'mfsd-pp__badge--in-progress'],
            'completed'     => ['✅', 'Completed',      'mfsd-pp__badge--completed'],
            'coming_soon'   => ['🔒', 'Coming Soon',    'mfsd-pp__badge--coming'],
            'not_available' => ['❓', 'Not Available',  'mfsd-pp__badge--unavailable']
        ];
        $badge = $badges[$status] ?? $badges['not_available'];
        return sprintf(
            '<span class="mfsd-pp__badge %s"><span class="mfsd-pp__badge-icon">%s</span> %s</span>',
            $badge[2], $badge[0], $badge[1]
        );
    }

    private function render_progress_bar($activity) {
        $progress   = $activity['progress'] ?? 0;
        $max        = $activity['progress_max'] ?? 100;
        $percentage = $max > 0 ? round(($progress / $max) * 100) : 0;
        ob_start();
        ?>
        <div class="mfsd-pp__progress-bar-wrap">
            <div class="mfsd-pp__progress-bar">
                <div class="mfsd-pp__progress-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
            </div>
            <span class="mfsd-pp__progress-percent"><?php echo $percentage; ?>%</span>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_activity_results($activity_key, $activity) {
        ob_start();
        switch ($activity_key) {
            case 'word_association':
                $this->render_word_association_results($activity);
                break;
            case 'junk_jobs':
                $this->render_junk_jobs_results($activity);
                break;
            case 'personality_test_mbti':
                $this->render_personality_results($activity);
                break;
            case 'weekly_rag':
                $this->render_rag_results($activity);
                break;
            case 'super_strengths':
                $this->render_super_strengths_results($activity);
                break;
            default:
                echo '<p>Results available.</p>';
        }
        return ob_get_clean();
    }

    // ── Word Association ──────────────────────────────────────────────────────
    private function render_word_association_results($activity) {
        $responses = $activity['responses'] ?? [];
        ?>
        <div class="mfsd-pp__results mfsd-pp__results--word-assoc">
            <p class="mfsd-pp__results-summary">
                <strong><?php echo count($responses); ?></strong> words completed
            </p>
            <?php if (!empty($responses) && !empty($responses[0]->ai_summary)): ?>
                <div class="mfsd-pp__ai-summary">
                    <h5 class="mfsd-pp__ai-summary-title">
                        <span class="mfsd-pp__ai-icon">🤖</span> AI Insights
                    </h5>
                    <div class="mfsd-pp__ai-summary-content">
                        <?php echo wp_kses_post($responses[0]->ai_summary); ?>
                    </div>
                </div>
            <?php endif; ?>
            <details class="mfsd-pp__details">
                <summary>View word responses</summary>
                <div class="mfsd-pp__word-list">
                    <?php foreach ($responses as $r): ?>
                        <div class="mfsd-pp__word-item">
                            <span class="mfsd-pp__word-prompt"><?php echo esc_html($r->word); ?></span>
                            <span class="mfsd-pp__word-responses">
                                <?php echo esc_html(implode(', ', array_filter([$r->association_1, $r->association_2, $r->association_3]))); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <?php
    }

    // ── Junk Jobs ─────────────────────────────────────────────────────────────
    private function render_junk_jobs_results($activity) {
        $jobs    = $activity['jobs']    ?? [];
        $reasons = $activity['reasons'] ?? [];
        ?>
        <div class="mfsd-pp__results mfsd-pp__results--junk-jobs">
            <?php if (!empty($activity['mbti_type'])): ?>
                <p class="mfsd-pp__results-context">
                    Analysis based on <strong><?php echo esc_html($activity['mbti_type']); ?></strong> personality type
                </p>
            <?php endif; ?>
            <?php if (!empty($activity['analysis'])): ?>
                <div class="mfsd-pp__ai-summary">
                    <h5 class="mfsd-pp__ai-summary-title">
                        <span class="mfsd-pp__ai-icon">🤖</span> Career Insights Analysis
                    </h5>
                    <div class="mfsd-pp__ai-summary-content">
                        <?php echo wp_kses_post($activity['analysis']); ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($jobs)): ?>
                <details class="mfsd-pp__details">
                    <summary>View selected jobs &amp; reasons</summary>
                    <div class="mfsd-pp__jobs-list">
                        <?php foreach ($jobs as $job): ?>
                            <div class="mfsd-pp__job-item">
                                <span class="mfsd-pp__job-name">🗑️ <?php echo esc_html($job); ?></span>
                                <?php if (!empty($reasons[$job])): ?>
                                    <span class="mfsd-pp__job-reason"><?php echo esc_html($reasons[$job]); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Personality Test ──────────────────────────────────────────────────────
    private function render_personality_results($activity) {
        ?>
        <div class="mfsd-pp__results mfsd-pp__results--personality">
            <div class="mfsd-pp__personality-type">
                <span class="mfsd-pp__mbti-badge"><?php echo esc_html($activity['mbti_type'] ?? 'N/A'); ?></span>
                <?php if (!empty($activity['disc_primary'])): ?>
                    <span class="mfsd-pp__disc-badge">DISC: <?php echo esc_html($activity['disc_primary']); ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($activity['disc_scores'])): ?>
                <div class="mfsd-pp__disc-scores">
                    <?php foreach ($activity['disc_scores'] as $letter => $score): ?>
                        <?php if ($score !== null): ?>
                            <div class="mfsd-pp__disc-score">
                                <span class="mfsd-pp__disc-letter"><?php echo $letter; ?></span>
                                <div class="mfsd-pp__disc-bar">
                                    <div class="mfsd-pp__disc-bar-fill mfsd-pp__disc-bar-fill--<?php echo strtolower($letter); ?>"
                                         style="width: <?php echo intval($score); ?>%"></div>
                                </div>
                                <span class="mfsd-pp__disc-value"><?php echo intval($score); ?>%</span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($activity['ai_summary'])): ?>
                <div class="mfsd-pp__ai-summary">
                    <h5 class="mfsd-pp__ai-summary-title">
                        <span class="mfsd-pp__ai-icon">🎯</span> Steve Says
                    </h5>
                    <div class="mfsd-pp__ai-summary-content">
                        <?php echo wp_kses_post($activity['ai_summary']); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Weekly RAG ────────────────────────────────────────────────────────────
    private function render_rag_results($activity) {
        $breakdown = $activity['breakdown'] ?? [];
        $total     = array_sum($breakdown);
        ?>
        <div class="mfsd-pp__results mfsd-pp__results--rag">
            <div class="mfsd-pp__rag-overview">
                <div class="mfsd-pp__rag-score">
                    <span class="mfsd-pp__rag-score-value"><?php echo intval($activity['total_score'] ?? 0); ?></span>
                    <span class="mfsd-pp__rag-score-label">Total Score</span>
                </div>
                <?php if ($total > 0): ?>
                    <div class="mfsd-pp__rag-breakdown">
                        <?php if (($breakdown['G'] ?? 0) > 0): ?>
                            <span class="mfsd-pp__rag-item mfsd-pp__rag-item--green">🟢 <?php echo $breakdown['G']; ?> Green</span>
                        <?php endif; ?>
                        <?php if (($breakdown['A'] ?? 0) > 0): ?>
                            <span class="mfsd-pp__rag-item mfsd-pp__rag-item--amber">🟡 <?php echo $breakdown['A']; ?> Amber</span>
                        <?php endif; ?>
                        <?php if (($breakdown['R'] ?? 0) > 0): ?>
                            <span class="mfsd-pp__rag-item mfsd-pp__rag-item--red">🔴 <?php echo $breakdown['R']; ?> Red</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ── Super Strengths ───────────────────────────────────────────────────────

    /**
     * Completed state: strength cards received + Steve Says AI summary.
     */
    private function render_super_strengths_results($activity) {
        $received = $activity['received_cards'] ?? [];
        ?>
        <div class="mfsd-pp__results mfsd-pp__results--super-strengths">

            <?php if (!empty($activity['ai_summary'])): ?>
                <div class="mfsd-pp__ai-summary">
                    <h5 class="mfsd-pp__ai-summary-title">
                        <span class="mfsd-pp__ai-icon">💬</span> Steve Says
                    </h5>
                    <div class="mfsd-pp__ai-summary-content">
                        <?php echo wp_kses_post(nl2br(esc_html($activity['ai_summary']))); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($received)): ?>
                <details class="mfsd-pp__details">
                    <summary>View all <?php echo count($received); ?> Super Strength cards received</summary>
                    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-top:10px;">
                        <?php foreach ($received as $card): ?>
                            <span style="
                                display:inline-block;
                                background:var(--pp-primary-light);
                                border:1px solid var(--pp-primary);
                                border-radius:20px;
                                padding:5px 13px;
                                font-size:13px;
                                color:var(--pp-gray-700);
                            ">
                                <?php echo esc_html($card['strength_text']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * In-progress state: show each player's submission status as coloured pills.
     */
    private function render_ss_player_status($all_players) {
        ?>
        <div style="margin-top:10px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pp-gray-500);margin-bottom:6px;">
                Player status
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:7px;">
                <?php foreach ($all_players as $p): ?>
                    <?php $done = $p['submission_status'] === 'submitted'; ?>
                    <span style="
                        display:inline-flex;
                        align-items:center;
                        gap:5px;
                        padding:4px 11px;
                        border-radius:20px;
                        font-size:12px;
                        background:<?php echo $done ? 'var(--pp-success-light)' : 'var(--pp-gray-100)'; ?>;
                        color:<?php echo $done ? '#065F46' : 'var(--pp-gray-600)'; ?>;
                        border:1px solid <?php echo $done ? 'var(--pp-success)' : 'var(--pp-gray-300)'; ?>;
                    ">
                        <?php echo $done ? '✅' : '⏳'; ?>
                        <?php echo esc_html($p['display_name']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}