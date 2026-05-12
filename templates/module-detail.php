<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> — Module Content</title>
    <?php wp_head(); ?>
    <style>
        html, html.admin-bar {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        body.nds-module-detail-body,
        body.nds-module-detail-body #page,
        body.nds-module-detail-body #content,
        body.nds-module-detail-body main,
        body.nds-module-detail-body .site,
        body.nds-module-detail-body .site-content,
        body.nds-module-detail-body .ast-container,
        body.nds-module-detail-body .ast-plain-container,
        body.nds-module-detail-body .ast-builder-grid-row,
        body.nds-module-detail-body .ast-site-content-wrap,
        body.nds-module-detail-body .content-area,
        body.nds-module-detail-body article,
        body.nds-module-detail-body .hentry,
        body.nds-module-detail-body .entry-content {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        .nds-module-detail-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .nds-content-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3b82f6;
            transition: all 0.3s ease;
        }
        .nds-content-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        .nds-content-card.quiz-card {
            border-left-color: #8b5cf6;
        }
        .nds-content-card.assignment-card {
            border-left-color: #ec4899;
        }
        .nds-content-card.reading-card {
            border-left-color: #10b981;
        }
        .nds-content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        .nds-content-card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        .nds-content-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .nds-content-type-badge.quiz {
            background: #ede9fe;
            color: #6d28d9;
        }
        .nds-content-type-badge.assignment {
            background: #fce7f3;
            color: #be185d;
        }
        .nds-content-type-badge.reading {
            background: #d1fae5;
            color: #047857;
        }
        .nds-content-type-badge.lesson {
            background: #dbeafe;
            color: #1e40af;
        }
        .nds-content-description {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
            margin: 12px 0;
        }
        .nds-content-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #6b7280;
            margin: 12px 0 16px 0;
        }
        .nds-content-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nds-content-footer {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        .nds-btn {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .nds-btn-primary {
            background: #3b82f6;
            color: white;
        }
        .nds-btn-primary:hover {
            background: #2563eb;
        }
        .nds-btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        .nds-btn-secondary:hover {
            background: #d1d5db;
        }
        .nds-btn-purple {
            background: #8b5cf6;
            color: white;
        }
        .nds-btn-purple:hover {
            background: #7c3aed;
        }
        .nds-btn-pink {
            background: #ec4899;
            color: white;
        }
        .nds-btn-pink:hover {
            background: #db2777;
        }
        .nds-btn-green {
            background: #10b981;
            color: white;
        }
        .nds-btn-green:hover {
            background: #059669;
        }
        .nds-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            margin-bottom: 40px;
            border-radius: 12px;
        }
        .nds-header h1 {
            margin: 0 0 8px 0;
            font-size: 32px;
        }
        .nds-header-subtitle {
            opacity: 0.9;
            margin: 0;
            font-size: 16px;
        }
        .nds-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }
        .nds-back-link:hover {
            color: #764ba2;
        }
        .nds-content-grid {
            display: grid;
            gap: 20px;
        }
        .nds-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        .nds-empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .nds-empty-state-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 8px 0;
        }
        .nds-empty-state-text {
            color: #6b7280;
            margin: 0;
        }
    </style>
</head>
<body <?php body_class('nds-module-detail-body'); ?>>
<?php function_exists('wp_body_open') && wp_body_open(); ?>

<div class="nds-module-detail-wrapper bg-gray-50 min-h-screen" style="padding: 20px;">
    <div style="max-width: 900px; margin: 0 auto;">
        <?php
        global $wpdb;
        
        $module_id = (int) get_query_var('nds_portal_module');
        $student_id = (int) nds_portal_get_current_student_id();
        
        if ($student_id <= 0 || $module_id <= 0) {
            echo '<div class="nds-empty-state"><div class="nds-empty-state-icon">⚠️</div><h2 class="nds-empty-state-title">Invalid Request</h2><p class="nds-empty-state-text">Unable to load module details. Please return to your portal.</p></div>';
            return;
        }
        
        // Fetch module info
        $module = $wpdb->get_row($wpdb->prepare(
            "SELECT m.id, m.name, m.module_code, m.course_id, c.name AS course_name
             FROM {$wpdb->prefix}nds_modules m
             LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = m.course_id
             WHERE m.id = %d
             LIMIT 1",
            $module_id
        ), ARRAY_A);
        
        if (!$module) {
            echo '<div class="nds-empty-state"><div class="nds-empty-state-icon">📚</div><h2 class="nds-empty-state-title">Module Not Found</h2><p class="nds-empty-state-text">The module you\'re looking for could not be found.</p></div>';
            return;
        }
        
        // Check if student is enrolled
        $enrolled = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}nds_student_modules
             WHERE student_id = %d AND module_id = %d AND status IN ('enrolled', 'completed')",
            $student_id,
            $module_id
        ));
        
        if (!$enrolled) {
            echo '<div class="nds-empty-state"><div class="nds-empty-state-icon">🔒</div><h2 class="nds-empty-state-title">Access Denied</h2><p class="nds-empty-state-text">You are not enrolled in this module.</p></div>';
            return;
        }
        
        // Get learner cohorts
        $learner_cohort_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT cohort_id FROM {$wpdb->prefix}nds_student_cohorts
             WHERE student_id = %d AND (status = 'active' OR status IS NULL)",
            $student_id
        ));
        $learner_cohort_ids = array_values(array_unique(array_filter(array_map('intval', $learner_cohort_ids ?: array()))));
        
        // Fetch content
        $content = $wpdb->get_results($wpdb->prepare(
            "SELECT lc.id, lc.content_type, lc.title, lc.description, lc.resource_url, lc.attachment_url,
                    lc.due_date, lc.time_limit_minutes, lc.attempts_allowed, lc.quiz_data, lc.created_at,
                    lc.access_grouping, lc.allowed_cohort_ids, cs.access_grouping AS section_access_grouping,
                    cs.allowed_cohort_ids AS section_allowed_cohort_ids
             FROM {$wpdb->prefix}nds_lecturer_content lc
             LEFT JOIN {$wpdb->prefix}nds_course_sections cs ON cs.id = lc.section_id
             WHERE lc.module_id = %d AND lc.is_visible = 1 AND lc.status = 'published'
               AND (lc.access_start IS NULL OR lc.access_start <= NOW())
               AND (lc.access_end IS NULL OR lc.access_end >= NOW())
               AND (cs.id IS NULL OR cs.is_visible = 1)
               AND (cs.id IS NULL OR cs.access_start IS NULL OR cs.access_start <= NOW())
               AND (cs.id IS NULL OR cs.access_end IS NULL OR cs.access_end >= NOW())
             ORDER BY lc.created_at DESC",
            $module_id
        ), ARRAY_A);
        
        // Filter by access grouping
        $content = array_values(array_filter($content, static function ($item) use ($learner_cohort_ids) {
            $content_grouping = sanitize_key((string) ($item['access_grouping'] ?? 'all'));
            if ($content_grouping === 'cohorts') {
                $content_allowed = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) ($item['allowed_cohort_ids'] ?? ''))), static function ($id) {
                    return $id > 0;
                }));
                if (empty($content_allowed) || empty(array_intersect($content_allowed, $learner_cohort_ids))) {
                    return false;
                }
            }
            
            $section_grouping = sanitize_key((string) ($item['section_access_grouping'] ?? 'all'));
            if ($section_grouping === 'cohorts') {
                $section_allowed = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) ($item['section_allowed_cohort_ids'] ?? ''))), static function ($id) {
                    return $id > 0;
                }));
                if (empty($section_allowed) || empty(array_intersect($section_allowed, $learner_cohort_ids))) {
                    return false;
                }
            }
            
            return true;
        }));

        $quiz_attempts_count_map = array();
        $quiz_latest_attempt_map = array();
        if (!empty($content) && function_exists('nds_portal_ensure_quiz_attempts_table')) {
            $quiz_content_ids = array_values(array_filter(array_map(static function ($row) {
                if (sanitize_key((string) ($row['content_type'] ?? '')) !== 'quiz') {
                    return 0;
                }
                return (int) ($row['id'] ?? 0);
            }, $content), static function ($id) {
                return $id > 0;
            }));

            if (!empty($quiz_content_ids)) {
                $quiz_attempts_table = nds_portal_ensure_quiz_attempts_table();
                $quiz_placeholders = implode(',', array_fill(0, count($quiz_content_ids), '%d'));

                $count_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT content_id, COUNT(*) AS attempt_count
                     FROM {$quiz_attempts_table}
                     WHERE student_id = %d
                       AND content_id IN ({$quiz_placeholders})
                     GROUP BY content_id",
                    array_merge(array($student_id), $quiz_content_ids)
                ), ARRAY_A);

                foreach ($count_rows as $count_row) {
                    $quiz_attempts_count_map[(int) ($count_row['content_id'] ?? 0)] = (int) ($count_row['attempt_count'] ?? 0);
                }

                $latest_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT qa.content_id, qa.score_percent, qa.submitted_at, lc.pass_percentage, lc.min_grade_required
                     FROM {$quiz_attempts_table} qa
                     INNER JOIN (
                        SELECT content_id, MAX(attempt_no) AS last_attempt_no
                        FROM {$quiz_attempts_table}
                        WHERE student_id = %d
                          AND content_id IN ({$quiz_placeholders})
                        GROUP BY content_id
                     ) latest ON latest.content_id = qa.content_id AND latest.last_attempt_no = qa.attempt_no
                     INNER JOIN {$wpdb->prefix}nds_lecturer_content lc ON lc.id = qa.content_id
                     WHERE qa.student_id = %d",
                    array_merge(array($student_id), $quiz_content_ids, array($student_id))
                ), ARRAY_A);

                foreach ($latest_rows as $latest_row) {
                    $quiz_latest_attempt_map[(int) ($latest_row['content_id'] ?? 0)] = $latest_row;
                }
            }
        }
        
        ?>
        
        <a href="<?php echo esc_url(home_url('/portal/')); ?>" class="nds-back-link">← Back to Portal</a>
        
        <div class="nds-header">
            <h1><?php echo esc_html($module['name']); ?></h1>
            <p class="nds-header-subtitle"><?php echo esc_html($module['course_name']); ?></p>
        </div>
        
        <?php if (empty($content)) : ?>
            <div class="nds-empty-state">
                <div class="nds-empty-state-icon">📖</div>
                <h2 class="nds-empty-state-title">No Content Available</h2>
                <p class="nds-empty-state-text">Your instructor hasn't added any content to this module yet.</p>
            </div>
        <?php else : ?>
            <div class="nds-content-grid">
                <?php foreach ($content as $item) : 
                    $content_id = (int) $item['id'];
                    $content_type = sanitize_key($item['content_type']);
                    $title = esc_html($item['title'] ?? 'Untitled');
                    $description = isset($item['description']) ? wp_kses_post($item['description']) : '';
                    $due_date = $item['due_date'] ? date('F j, Y', strtotime($item['due_date'])) : '';
                    $time_limit = isset($item['time_limit_minutes']) && $item['time_limit_minutes'] > 0 ? (int) $item['time_limit_minutes'] : 0;
                    $attempts = isset($item['attempts_allowed']) ? (int) $item['attempts_allowed'] : 1;
                    $attempts_used = isset($quiz_attempts_count_map[$content_id]) ? (int) $quiz_attempts_count_map[$content_id] : 0;
                    $attempts_remaining = $attempts > 0 ? max(0, $attempts - $attempts_used) : -1;
                    $quiz_locked = $content_type === 'quiz' && $attempts > 0 && $attempts_remaining <= 0;
                    $latest_attempt = isset($quiz_latest_attempt_map[$content_id]) ? $quiz_latest_attempt_map[$content_id] : null;
                    
                    $card_class = 'nds-content-card ' . ($content_type === 'quiz' ? 'quiz-card' : ($content_type === 'assignment' ? 'assignment-card' : 'reading-card'));
                    $badge_class = 'nds-content-type-badge ' . $content_type;
                ?>
                    <div class="<?php echo esc_attr($card_class); ?>">
                        <div class="nds-content-card-header">
                            <h3 class="nds-content-card-title"><?php echo $title; ?></h3>
                            <span class="<?php echo esc_attr($badge_class); ?>">
                                <?php 
                                if ($content_type === 'quiz') echo '📝 Quiz';
                                elseif ($content_type === 'assignment') echo '📋 Assignment';
                                else echo '📖 Reading';
                                ?>
                            </span>
                        </div>
                        
                        <?php if ($description) : ?>
                            <div class="nds-content-description"><?php echo $description; ?></div>
                        <?php endif; ?>
                        
                        <div class="nds-content-meta">
                            <?php if ($due_date) : ?>
                                <div class="nds-content-meta-item">
                                    📅 Due: <strong><?php echo esc_html($due_date); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($content_type === 'quiz' && $time_limit > 0) : ?>
                                <div class="nds-content-meta-item">
                                    ⏱️ Time Limit: <strong><?php echo (int) $time_limit; ?> min</strong>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($content_type === 'quiz' && $attempts > 0) : ?>
                                <div class="nds-content-meta-item">
                                    🔄 Attempts: <strong><?php echo (int) $attempts_used; ?> / <?php echo (int) $attempts; ?></strong>
                                </div>
                            <?php endif; ?>

                            <?php if ($content_type === 'quiz' && is_array($latest_attempt)) : ?>
                                <?php
                                $latest_score = isset($latest_attempt['score_percent']) && $latest_attempt['score_percent'] !== null ? (float) $latest_attempt['score_percent'] : null;
                                $pass_threshold = isset($latest_attempt['pass_percentage']) && $latest_attempt['pass_percentage'] !== null
                                    ? (float) $latest_attempt['pass_percentage']
                                    : (isset($latest_attempt['min_grade_required']) && $latest_attempt['min_grade_required'] !== null ? (float) $latest_attempt['min_grade_required'] : 50.0);
                                ?>
                                <div class="nds-content-meta-item">
                                    📊 Last result:
                                    <?php if ($latest_score !== null) : ?>
                                        <strong><?php echo esc_html(number_format($latest_score, 2)); ?>% (<?php echo $latest_score >= $pass_threshold ? 'Pass' : 'Fail'; ?>)</strong>
                                    <?php else : ?>
                                        <strong>Pending review</strong>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="nds-content-footer">
                            <?php if ($content_type === 'quiz') : ?>
                                <?php if ($quiz_locked) : ?>
                                    <span class="nds-btn nds-btn-secondary" style="cursor:not-allowed;opacity:.75;">🔒 Attempts Completed</span>
                                <?php else : ?>
                                    <a href="<?php echo esc_url(home_url('/portal/quiz/' . (int)$content_id . '/')); ?>" class="nds-btn nds-btn-purple">
                                        ✍️ Start Quiz
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($item['resource_url']) : ?>
                                <a href="<?php echo esc_url($item['resource_url']); ?>" target="_blank" class="nds-btn nds-btn-green">
                                    🔗 Open Resource
                                </a>
                            <?php elseif ($item['attachment_url']) : ?>
                                <a href="<?php echo esc_url($item['attachment_url']); ?>" class="nds-btn nds-btn-green">
                                    📥 Download File
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
