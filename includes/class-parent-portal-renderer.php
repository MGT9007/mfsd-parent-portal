<?php
/**
 * Parent Portal Renderer v4.4.7
 * Supports parent view (linked students) and student self-view.
 * $viewer_role: 'parent' | 'student'
 */

if (!defined('ABSPATH')) exit;

class MFSD_Parent_Portal_Renderer {

    private $data;
    private $viewer_role;
    private $current_student_id = 0;

    public function __construct(MFSD_Parent_Portal_Data $data, $viewer_role = 'parent') {
        $this->data        = $data;
        $this->viewer_role = $viewer_role;
    }

    // =========================================================================
    // LANDING — STUDENT
    // =========================================================================
    public function render_landing_student($student_id) {
        $courses  = $this->data->get_student_courses($student_id);
        $page_url = get_permalink();
        ob_start();
        ?>
        <div class="mfsd-pp mfsd-pp--landing mfsd-pp--student">
            <div class="mfsd-pp__header">
                <h1 class="mfsd-pp__title">My Courses</h1>
                <p class="mfsd-pp__subtitle">Your High Performance Pathway journey</p>
            </div>
            <?php if (empty($courses)): ?>
                <div class="mfsd-pp-notice mfsd-pp-notice--info">
                    <p>You don't have any courses yet. Check back soon!</p>
                </div>
            <?php else: ?>
                <div class="mfsd-pp__course-grid">
                    <?php foreach ($courses as $course): ?>
                        <?php $this->render_course_card($course, $page_url, $student_id); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // LANDING — PARENT
    // =========================================================================
    public function render_landing_parent($linked_students) {
        $page_url = get_permalink();
        ob_start();
        ?>
        <div class="mfsd-pp mfsd-pp--landing mfsd-pp--parent">
            <div class="mfsd-pp__header">
                <h1 class="mfsd-pp__title">Parent Portal</h1>
                <p class="mfsd-pp__subtitle">Track your child's progress</p>
            </div>

            <?php echo $this->render_course_tabs(); ?>

            <div class="mfsd-pp__tab-panel" data-panel="my-courses">
                <?php foreach ($linked_students as $student): ?>
                    <?php $courses = $this->data->get_student_courses($student->student_user_id); ?>
                    <div class="mfsd-pp__landing-student">
                        <div class="mfsd-pp__landing-student-header">
                            <img src="<?php echo esc_url($student->avatar_url); ?>"
                                 class="mfsd-pp__landing-avatar" alt="">
                            <h2 class="mfsd-pp__landing-student-name">
                                <?php echo esc_html($student->student_name); ?>'s Courses
                            </h2>
                        </div>
                        <?php if (empty($courses)): ?>
                            <p class="mfsd-pp__not-started-text"><?php echo esc_html($student->student_name); ?> isn't enrolled on any courses yet.</p>
                        <?php else: ?>
                            <div class="mfsd-pp__course-grid">
                                <?php foreach ($courses as $course): ?>
                                    <?php $this->render_course_card($course, $page_url, $student->student_user_id); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php echo $this->render_coming_soon_panel('new-courses', '🚀', 'New courses will appear here when available.'); ?>
            <?php echo $this->render_coming_soon_panel('all-courses', '📚', 'Browse all available courses here soon.'); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // TAB BAR — shared by both landing views
    // =========================================================================
    private function render_course_tabs(): string {
        ob_start();
        ?>
        <div class="mfsd-pp__tabs" role="tablist"
             style="display:flex!important;gap:0!important;border-bottom:2px solid #E5E7EB!important;margin-bottom:24px!important;">
            <button class="mfsd-pp__tab mfsd-pp__tab--active" role="tab"
                    aria-selected="true" data-tab="my-courses"
                    style="padding:10px 20px!important;background:none!important;border:none!important;border-bottom:3px solid #4F46E5!important;margin-bottom:-2px!important;font-size:14px!important;font-weight:600!important;color:#4F46E5!important;cursor:pointer!important;">
                My Courses
            </button>
            <button class="mfsd-pp__tab" role="tab"
                    aria-selected="false" data-tab="new-courses"
                    style="padding:10px 20px!important;background:none!important;border:none!important;border-bottom:3px solid transparent!important;margin-bottom:-2px!important;font-size:14px!important;font-weight:600!important;color:#6B7280!important;cursor:pointer!important;">
                New Courses
            </button>
            <button class="mfsd-pp__tab" role="tab"
                    aria-selected="false" data-tab="all-courses"
                    style="padding:10px 20px!important;background:none!important;border:none!important;border-bottom:3px solid transparent!important;margin-bottom:-2px!important;font-size:14px!important;font-weight:600!important;color:#6B7280!important;cursor:pointer!important;">
                All Courses
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // COMING SOON PANEL — shared placeholder for new tabs
    // =========================================================================
    private function render_coming_soon_panel(string $panel, string $icon, string $text): string {
        ob_start();
        ?>
        <div class="mfsd-pp__tab-panel" data-panel="<?php echo esc_attr($panel); ?>"
             style="display:none!important;">
            <div style="text-align:center!important;padding:56px 24px!important;">
                <p style="font-size:40px!important;margin:0 0 16px 0!important;line-height:1!important;"><?php echo $icon; ?></p>
                <h3 style="font-size:18px!important;font-weight:700!important;color:#1F2937!important;margin:0 0 8px 0!important;">Coming Soon</h3>
                <p style="font-size:14px!important;color:#6B7280!important;margin:0!important;"><?php echo esc_html($text); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // COURSE CARD
    // =========================================================================
    private function render_course_card($course, $page_url, $student_id) {
        $pct     = (int) $course->percent_complete;
        $detail  = add_query_arg(['course_id' => $course->id, 'student_id' => $student_id], $page_url);
        $has_img = !empty($course->image_url);
        $colours = ['#4F46E5','#7C3AED','#0EA5E9','#10B981','#F59E0B','#EF4444'];
        $bg      = $colours[$course->id % count($colours)];
        ?>
        <a href="<?php echo esc_url($detail); ?>" class="mfsd-pp__course-card"
           style="display:flex!important;flex-direction:column!important;background:#fff!important;border:1px solid #E5E7EB!important;border-radius:12px!important;overflow:hidden!important;text-decoration:none!important;color:inherit!important;"
           onmouseover="this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,.1)'!important;this.style.transform='translateY(-2px)'!important;"
           onmouseout="this.style.boxShadow='none'!important;this.style.transform='none'!important;">

            <div class="mfsd-pp__course-thumb">
                <?php if ($has_img): ?>
                    <img src="<?php echo esc_url($course->image_url); ?>"
                         alt="<?php echo esc_attr($course->course_name); ?>">
                <?php else: ?>
                    <div class="mfsd-pp__course-thumb-placeholder"
                         style="background:<?php echo $bg; ?>">
                        <span class="mfsd-pp__course-thumb-initials">
                            <?php
                            $words    = explode(' ', $course->course_name);
                            $initials = implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), array_slice($words, 0, 2)));
                            echo esc_html($initials);
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div style="padding:14px 16px 16px!important;display:flex!important;flex-direction:column!important;gap:10px!important;flex:1!important;">
                <h3 style="font-size:15px!important;font-weight:600!important;color:#1F2937!important;margin:0!important;line-height:1.3!important;">
                    <?php echo esc_html($course->course_name); ?>
                </h3>
                <div style="display:flex!important;flex-direction:column!important;gap:6px!important;">
                    <div style="display:flex!important;align-items:center!important;gap:10px!important;">
                        <div style="flex:1!important;height:4px!important;background:#E5E7EB!important;border-radius:2px!important;overflow:hidden!important;">
                            <div style="height:100%!important;width:<?php echo $pct; ?>%!important;background:#6d28d9!important;border-radius:2px!important;"></div>
                        </div>
                        <span style="font-size:12px!important;color:#4B5563!important;font-weight:600!important;white-space:nowrap!important;flex-shrink:0!important;"><?php echo $pct; ?>% complete</span>
                    </div>
                </div>
            </div>
        </a>
        <?php
    }

    // =========================================================================
    // PARENT VIEW — course detail
    // =========================================================================
    public function render($linked_students, $course_id = 0) {
        ob_start();
        ?>
        <div class="mfsd-pp mfsd-pp--parent">
            <div class="mfsd-pp__header">
                <?php echo $this->render_back_link(); ?>
                <h1 class="mfsd-pp__title">Parent Portal</h1>
                <p class="mfsd-pp__subtitle">Track your child's progress</p>
            </div>
            <?php foreach ($linked_students as $student): ?>
                <?php $this->render_student_section($student); ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // STUDENT SELF-VIEW — course detail
    // =========================================================================
    public function render_student_self($student_id, $course_id = 0) {
        $user = get_userdata($student_id);

        $student                     = new stdClass();
        $student->student_user_id    = $student_id;
        $student->student_name       = $user ? $user->display_name : 'Student';
        $student->year_group         = get_user_meta($student_id, 'year_group', true);
        $student->school             = get_user_meta($student_id, 'school', true);
        $student->avatar_url         = $this->get_profile_picture_url($student_id);
        $student->is_primary_contact = false;

        ob_start();
        ?>
        <div class="mfsd-pp mfsd-pp--student">
            <div class="mfsd-pp__header">
                <?php echo $this->render_back_link(); ?>
                <h1 class="mfsd-pp__title">My Progress</h1>
                <p class="mfsd-pp__subtitle">Your High Performance Pathway journey</p>
            </div>
            <?php $this->render_student_section($student); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // BACK LINK
    // =========================================================================
    private function render_back_link() {
        $back = get_permalink();
        return '<a href="' . esc_url($back) . '" class="mfsd-pp__back-link">← Back to courses</a>';
    }

    // =========================================================================
    // STUDENT SECTION (shared by parent and student detail views)
    // =========================================================================
    private function render_student_section($student) {
        $this->current_student_id = (int) $student->student_user_id;
        $progress       = $this->data->get_student_progress($student->student_user_id);
        $course_percent = $this->data->get_course_percent($student->student_user_id);
        ?>
        <div class="mfsd-pp__student" data-student-id="<?php echo esc_attr($student->student_user_id); ?>">

            <div class="mfsd-pp__student-header" style="display:flex!important;align-items:center!important;gap:20px!important;background:linear-gradient(135deg,#4F46E5 0%,#6366F1 100%)!important;padding:24px!important;border-radius:12px!important;color:#fff!important;box-shadow:0 10px 15px -3px rgba(0,0,0,.1)!important;margin-bottom:24px!important;flex-wrap:wrap!important;">
                <div style="flex-shrink:0!important;">
                    <img src="<?php echo esc_url($student->avatar_url); ?>" alt=""
                         style="width:80px!important;height:80px!important;border-radius:50%!important;border:3px solid rgba(255,255,255,.3)!important;object-fit:cover!important;display:block!important;">
                </div>
                <div style="flex:1!important;min-width:0!important;">
                    <h2 style="font-size:24px!important;font-weight:700!important;margin:0 0 8px 0!important;color:#fff!important;">
                        <?php echo esc_html($student->student_name); ?>
                    </h2>
                    <div style="display:flex!important;flex-wrap:wrap!important;gap:12px!important;">
                        <?php if (!empty($student->year_group)): ?>
                            <span style="font-size:14px!important;color:rgba(255,255,255,.9)!important;">📚 Year <?php echo esc_html($student->year_group); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($student->school)): ?>
                            <span style="font-size:14px!important;color:rgba(255,255,255,.9)!important;">🏫 <?php echo esc_html($student->school); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($student->is_primary_contact)): ?>
                            <span style="background:rgba(255,255,255,.2)!important;padding:4px 10px!important;border-radius:20px!important;font-size:14px!important;color:#fff!important;">⭐ Primary Contact</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="flex-shrink:0!important;text-align:center!important;">
                    <?php echo $this->render_overall_progress($course_percent); ?>
                </div>
            </div>

            <div style="display:flex!important;flex-direction:column!important;gap:16px!important;">
                <?php foreach ($progress as $week_num => $week_progress): ?>
                    <?php $this->render_week_section($week_num, $week_progress); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // OVERALL PROGRESS RING — course level %
    // =========================================================================
    private function render_overall_progress($percentage) {
        $percentage = (int) $percentage;
        ob_start();
        ?>
        <div style="display:flex!important;flex-direction:column!important;align-items:center!important;gap:8px!important;">
            <div style="width:80px!important;height:80px!important;position:relative!important;flex-shrink:0!important;">
                <svg viewBox="0 0 36 36" width="80" height="80"
                     style="width:80px!important;height:80px!important;display:block!important;transform:rotate(-90deg)!important;">
                    <path fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="3"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                    <path fill="none" stroke="white" stroke-width="3" stroke-linecap="round"
                        stroke-dasharray="<?php echo $percentage; ?>, 100"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                </svg>
                <span style="position:absolute!important;top:50%!important;left:50%!important;transform:translate(-50%,-50%)!important;font-size:18px!important;font-weight:700!important;color:#fff!important;">
                    <?php echo $percentage; ?>%
                </span>
            </div>
            <span style="font-size:12px!important;color:rgba(255,255,255,.9)!important;">Course Progress</span>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // WEEK SECTION
    // =========================================================================
    private function render_week_section($week_num, $week_progress) {
        $week_names         = [1 => 'Week 1: Foundation', 2 => 'Week 2: Exploration', 3 => 'Week 3: Discovery'];
        $week_name          = $week_names[$week_num] ?? "Week {$week_num}";
        $is_all_coming_soon = $this->is_week_coming_soon($week_progress);
        $expanded           = $week_num === 1;

        $w_completed = $w_total = 0;
        foreach ($week_progress as $activity) {
            // Exclude only truly unavailable/placeholder activities from the count
            if (in_array($activity['status'], ['coming_soon', 'not_available'])) continue;
            $w_total++;
            if ($activity['status'] === 'completed') $w_completed++;
        }
        $w_pct = $w_total > 0 ? round(($w_completed / $w_total) * 100) : 0;
        ?>
        <div class="mfsd-pp__week" data-week="<?php echo $week_num; ?>"
             style="background:#fff!important;border-radius:12px!important;box-shadow:0 1px 3px rgba(0,0,0,.1)!important;overflow:hidden!important;border:1px solid #E5E7EB!important;<?php echo $is_all_coming_soon ? 'opacity:.7!important;' : ''; ?>">

            <button class="mfsd-pp__week-header" aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
                    style="display:flex!important;align-items:center!important;width:100%!important;padding:16px 20px!important;background:#F9FAFB!important;border:none!important;cursor:pointer!important;text-align:left!important;gap:12px!important;">
                <h3 style="display:flex!important;align-items:center!important;gap:10px!important;margin:0!important;font-size:18px!important;font-weight:600!important;color:#1F2937!important;flex:1!important;">
                    <span><?php echo $this->get_week_icon($week_progress); ?></span>
                    <?php echo esc_html($week_name); ?>
                </h3>
                <div style="flex-shrink:0!important;text-align:right!important;">
                    <?php if ($w_total > 0): ?>
                        <div style="font-size:13px!important;font-weight:600!important;color:#4F46E5!important;"><?php echo $w_pct; ?>% complete</div>
                        <div style="font-size:12px!important;color:#6B7280!important;margin-top:2px!important;"><?php echo $w_completed; ?> of <?php echo $w_total; ?> activities</div>
                    <?php else: ?>
                        <span style="font-size:13px!important;color:#9CA3AF!important;font-style:italic!important;">Coming Soon</span>
                    <?php endif; ?>
                </div>
                <span class="mfsd-pp__week-toggle" style="flex-shrink:0!important;width:24px!important;height:24px!important;color:#9CA3AF!important;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </button>

            <div class="mfsd-pp__week-content" <?php echo !$expanded ? 'hidden' : ''; ?>
                 style="padding:20px!important;border-top:1px solid #E5E7EB!important;">
                <div class="mfsd-pp__activities"
                     style="display:grid!important;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))!important;gap:16px!important;">
                    <?php foreach ($week_progress as $activity_key => $activity): ?>
                        <?php $this->render_activity_card($activity_key, $activity); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
    private function is_week_coming_soon($week_progress) {
        foreach ($week_progress as $a) {
            if ($a['status'] !== 'coming_soon') return false;
        }
        return true;
    }

    private function get_week_icon($week_progress) {
        $completed = $total = 0;
        foreach ($week_progress as $a) {
            if (in_array($a['status'], ['coming_soon', 'not_available'])) continue;
            $total++;
            if ($a['status'] === 'completed') $completed++;
        }
        if ($total === 0)          return '🔒';
        if ($completed === $total) return '✅';
        if ($completed > 0)        return '🔄';
        return '⭕';
    }

    private function get_week_summary($week_progress) {
        $completed = $total = 0;
        foreach ($week_progress as $a) {
            if (!in_array($a['status'], ['coming_soon', 'not_available'])) {
                $total++;
                if ($a['status'] === 'completed') $completed++;
            }
        }
        if ($total === 0) return '<span class="mfsd-pp__week-status mfsd-pp__week-status--coming">Coming Soon</span>';
        return "<span class='mfsd-pp__week-status'>{$completed} of {$total} activities completed</span>";
    }

    // =========================================================================
    // ACTIVITY CARD
    // =========================================================================
    private function render_activity_card($activity_key, $activity) {
        $status = $activity['status'];
        $info   = $activity['info'] ?? [];
        $s      = $this->viewer_role === 'student';
        $url    = $info['url'] ?? '';

        // When a parent follows a link, append the student_id so the target plugin
        // can look up the student's session rather than the parent's.
        if ( !$s && $url && $this->current_student_id > 0 ) {
            $url = add_query_arg( 'student_id', $this->current_student_id, $url );
        }

        // Parents only follow links on completed tasks; students can access any active task.
        $clickable = $url && (
            $s
                ? in_array($status, ['not_started', 'in_progress', 'completed'])
                : $status === 'completed'
        );

        $border_colors = [
            'completed'   => '#10B981',
            'in_progress' => '#F59E0B',
            'coming_soon' => '#E5E7EB',
            'locked'      => '#E5E7EB',
            'not_started' => '#E5E7EB',
        ];
        $border = $border_colors[$status] ?? '#E5E7EB';

        $card_style = "background:#fff!important;border-radius:8px!important;border:1px solid {$border}!important;overflow:hidden!important;" .
            (in_array($status, ['coming_soon','locked']) ? 'opacity:.6!important;' : '') .
            ($clickable ? 'cursor:pointer!important;text-decoration:none!important;color:inherit!important;display:block!important;' : '');

        $tag   = $clickable ? 'a' : 'div';
        $href  = $clickable ? ' href="' . esc_url($url) . '"' : '';
        $hover = $clickable
            ? ' onmouseover="this.style.boxShadow=\'0 4px 12px rgba(0,0,0,.12)\'"
                onmouseout="this.style.boxShadow=\'none\'"'
            : '';
        ?>
        <<?php echo $tag; ?> class="mfsd-pp__activity"
            data-activity="<?php echo esc_attr($activity_key); ?>"
            <?php echo $href . $hover; ?>
            style="<?php echo $card_style; ?>">

            <div style="display:flex!important;align-items:flex-start!important;gap:12px!important;padding:16px!important;background:#F9FAFB!important;border-bottom:1px solid #F3F4F6!important;">
                <span style="font-size:28px!important;line-height:1!important;flex-shrink:0!important;"><?php echo $info['icon'] ?? '📝'; ?></span>
                <div style="flex:1!important;min-width:0!important;">
                    <h4 style="font-size:16px!important;font-weight:600!important;margin:0 0 4px 0!important;color:#1F2937!important;">
                        <?php echo esc_html($info['name'] ?? $activity_key); ?>
                    </h4>
                    <p style="font-size:13px!important;color:#6B7280!important;margin:0!important;">
                        <?php echo esc_html($info['description'] ?? ''); ?>
                    </p>
                </div>
                <?php echo $this->render_status_badge($status); ?>
            </div>

            <div style="padding:16px!important;">
                <?php if ($status === 'locked'): ?>
                    <p style="color:#6B7280!important;margin:0!important;font-size:14px!important;">
                        <?php echo $s ? '🔒 Complete the previous activity to unlock this one.' : '🔒 Unlocks after the previous activity is completed.'; ?>
                    </p>
                <?php elseif ($status === 'coming_soon'): ?>
                    <p style="color:#9CA3AF!important;font-style:italic!important;margin:0!important;">
                        <?php echo $s ? 'This activity is coming soon.' : 'This activity will be available soon.'; ?>
                    </p>
                <?php elseif ($status === 'not_started'): ?>
                    <p style="color:#6B7280!important;margin:0!important;font-size:14px!important;">
                        <?php echo $s ? "You haven't started this activity yet." : "Your child hasn't started this activity yet."; ?>
                    </p>
                    <?php if ($clickable): ?>
                        <p style="margin:8px 0 0!important;font-size:13px!important;font-weight:600!important;color:#4F46E5!important;">Start →</p>
                    <?php endif; ?>
                <?php elseif ($status === 'in_progress'): ?>
                    <?php echo $this->render_progress_bar($activity); ?>
                    <p style="font-size:13px!important;color:#6B7280!important;margin:4px 0 0 0!important;">
                        <?php echo esc_html($activity['progress_text'] ?? ''); ?>
                    </p>
                    <?php if ($activity_key === 'super_strengths' && !empty($activity['all_players'])): ?>
                        <?php $this->render_ss_player_status($activity['all_players']); ?>
                    <?php endif; ?>
                    <?php if ($clickable): ?>
                        <p style="margin:8px 0 0!important;font-size:13px!important;font-weight:600!important;color:#4F46E5!important;">Continue →</p>
                    <?php endif; ?>
                <?php elseif ($status === 'completed'): ?>
                    <?php echo $this->render_activity_results($activity_key, $activity); ?>
                    <?php if ($clickable): ?>
                        <p style="margin:8px 0 0!important;font-size:13px!important;font-weight:600!important;color:#10B981!important;">View →</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </<?php echo $tag; ?>>
        <?php
    }

    // =========================================================================
    // STATUS BADGE
    // =========================================================================
    private function render_status_badge($status) {
        $badges = [
            'not_started'   => ['⭕', 'Not Started',  'mfsd-pp__badge--not-started'],
            'in_progress'   => ['🔄', 'In Progress',  'mfsd-pp__badge--in-progress'],
            'completed'     => ['✅', 'Completed',     'mfsd-pp__badge--completed'],
            'coming_soon'   => ['🔒', 'Coming Soon',   'mfsd-pp__badge--coming'],
            'locked'        => ['🔒', 'Locked',        'mfsd-pp__badge--coming'],
            'not_available' => ['❓', 'Not Available', 'mfsd-pp__badge--unavailable'],
        ];
        $badge = $badges[$status] ?? $badges['not_available'];
        return sprintf(
            '<span class="mfsd-pp__badge %s"><span class="mfsd-pp__badge-icon">%s</span> %s</span>',
            $badge[2], $badge[0], $badge[1]
        );
    }

    // =========================================================================
    // PROGRESS BAR
    // =========================================================================
    private function render_progress_bar($activity) {
        $progress   = $activity['progress']     ?? 0;
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

    // =========================================================================
    // ACTIVITY RESULTS DISPATCH
    // =========================================================================
    private function render_activity_results($activity_key, $activity) {
        ob_start();
        switch ($activity_key) {
            case 'word_association':       $this->render_word_association_results($activity); break;
            case 'junk_jobs':              $this->render_junk_jobs_results($activity);        break;
            case 'personality_test_week_1': $this->render_personality_results($activity);     break;
            case 'rag_week_1':
            case 'rag_week_2':
            case 'rag_week_3':             $this->render_rag_results($activity);              break;
            case 'super_strengths':        $this->render_super_strengths_results($activity);  break;
            default: echo '<p>' . ($this->viewer_role === 'student' ? 'You\'ve completed this activity.' : 'Activity completed.') . '</p>';
        }
        return ob_get_clean();
    }

    // =========================================================================
    // PROFILE PICTURE — ProfilePress meta first, then WP avatar, then fallback
    // =========================================================================
    private function get_profile_picture_url($student_id, $size = 80) {
        // Check ProfilePress and other avatar plugin meta keys
        $meta_keys = array(
            'pp_profile_pic',
            'pp_profile_photo',
            'pp_profile_image',
            'profilepress_profile_picture',
            'profilepress_profile_image',
            'wp_user_avatar',
            'pp_custom_avatar',
            'user_avatar',
        );
        foreach ($meta_keys as $key) {
            $val = get_user_meta($student_id, $key, true);
            if (!empty($val)) {
                if (is_numeric($val)) {
                    $url = wp_get_attachment_url((int) $val);
                    if ($url) return esc_url($url);
                } elseif (filter_var($val, FILTER_VALIDATE_URL)) {
                    return esc_url($val);
                }
            }
        }

        // Parse get_avatar() HTML — ProfilePress hooks into this
        $avatar_html = get_avatar($student_id, $size);
        if ($avatar_html && preg_match('/src=["\']([^"\']+)["\']/', $avatar_html, $m)) {
            $src = html_entity_decode($m[1]);
            if (!empty($src) && strpos($src, 'gravatar.com') === false) {
                return esc_url($src);
            }
        }

        // Fall back to WP avatar with 404 default (prevents Gravatar placeholder)
        $url = get_avatar_url($student_id, ['size' => $size, 'default' => '404']);
        if ($url && strpos($url, '404') === false) {
            return esc_url($url);
        }

        return '';
    }

    private function render_word_association_results($activity) {
        $responses = $activity['responses'] ?? [];
        $s         = $this->viewer_role === 'student';
        ?>
        <p style="margin:0!important;font-size:14px!important;color:#374151!important;">
            <strong><?php echo count($responses); ?></strong><?php echo $s ? ' words you\'ve completed' : ' words completed'; ?>
        </p>
        <?php
    }

    private function render_junk_jobs_results($activity) {
        $jobs = $activity['jobs'] ?? [];
        $s    = $this->viewer_role === 'student';
        ?>
        <p style="margin:0!important;font-size:14px!important;color:#374151!important;">
            <strong><?php echo count($jobs); ?></strong><?php echo $s ? ' jobs you\'ve identified' : ' jobs identified'; ?>
        </p>
        <?php
    }

    private function render_personality_results($activity) {
        ?>
        <p style="margin:0!important;font-size:14px!important;color:#374151!important;">
            <?php if (!empty($activity['mbti_type'])): ?>
                MBTI type: <strong><?php echo esc_html($activity['mbti_type']); ?></strong>
                <?php if (!empty($activity['disc_primary'])): ?>
                    &nbsp;·&nbsp; DISC: <strong><?php echo esc_html($activity['disc_primary']); ?></strong>
                <?php endif; ?>
            <?php else: ?>
                Personality assessment complete
            <?php endif; ?>
        </p>
        <?php
    }

    private function render_rag_results($activity) {
        $breakdown = $activity['breakdown'] ?? [];
        ?>
        <p style="margin:0!important;font-size:14px!important;color:#374151!important;">
            Score: <strong><?php echo intval($activity['total_score'] ?? 0); ?></strong>
            <?php if (!empty($breakdown)): ?>
                &nbsp;·&nbsp;
                <?php if (($breakdown['G'] ?? 0) > 0): ?><?php echo $breakdown['G']; ?>🟢 <?php endif; ?>
                <?php if (($breakdown['A'] ?? 0) > 0): ?><?php echo $breakdown['A']; ?>🟡 <?php endif; ?>
                <?php if (($breakdown['R'] ?? 0) > 0): ?><?php echo $breakdown['R']; ?>🔴<?php endif; ?>
            <?php endif; ?>
        </p>
        <?php
    }

    private function render_super_strengths_results($activity) {
        $received = $activity['received_cards'] ?? [];
        $s        = $this->viewer_role === 'student';
        ?>
        <p style="margin:0!important;font-size:14px!important;color:#374151!important;">
            <strong><?php echo count($received); ?></strong><?php echo $s ? ' strength cards you received' : ' strength cards received'; ?>
        </p>
        <?php
    }

    // =========================================================================
    // SUPER STRENGTHS PLAYER STATUS PILLS
    // =========================================================================
    private function render_ss_player_status($all_players) {
        ?>
        <div style="margin-top:10px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pp-gray-500);margin-bottom:6px;">
                Player status
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:7px;">
                <?php foreach ($all_players as $p): ?>
                    <?php $done = $p['submission_status'] === 'submitted'; ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:12px;
                        background:<?php echo $done ? 'var(--pp-success-light)' : 'var(--pp-gray-100)'; ?>;
                        color:<?php echo $done ? '#065F46' : 'var(--pp-gray-600)'; ?>;
                        border:1px solid <?php echo $done ? 'var(--pp-success)' : 'var(--pp-gray-300)'; ?>;">
                        <?php echo $done ? '✅' : '⏳'; ?>
                        <?php echo esc_html($p['display_name']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}