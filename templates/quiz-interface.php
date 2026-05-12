<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> — Quiz</title>
    <?php wp_head(); ?>
    <style>
        html, html.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        body.nds-quiz-body,
        body.nds-quiz-body #page,
        body.nds-quiz-body #content,
        body.nds-quiz-body main,
        body.nds-quiz-body .site,
        body.nds-quiz-body .site-content,
        body.nds-quiz-body .ast-container,
        body.nds-quiz-body .ast-plain-container,
        body.nds-quiz-body .ast-builder-grid-row,
        body.nds-quiz-body .ast-site-content-wrap,
        body.nds-quiz-body .content-area,
        body.nds-quiz-body article,
        body.nds-quiz-body .hentry,
        body.nds-quiz-body .entry-content { margin-top: 0 !important; padding-top: 0 !important; }

        * { box-sizing: border-box; }

        body.nds-quiz-body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            margin: 0;
        }

        /* ─── Fixed bottom back bar ─── */
        .nds-back-bar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 100;
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(6px);
            border-top: 1px solid #e2e8f0;
            padding: 9px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
        }
        .nds-back-bar a {
            color: #667eea;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .nds-back-bar a:hover { color: #4f46e5; }
        .nds-back-bar-title { color: #94a3b8; }

        /* ─── Page ─── */
        .nds-quiz-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 16px 16px 64px;
        }

        /* ─── Header ─── */
        .nds-quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .nds-quiz-header h1 { margin: 0; font-size: 16px; font-weight: 700; }
        .nds-quiz-header-sub { margin: 2px 0 0; opacity:.85; font-size: 11px; }
        .nds-quiz-timer {
            background: rgba(255,255,255,.2);
            padding: 6px 13px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        .nds-quiz-timer.warning  { background: rgba(239,68,68,.35); }
        .nds-quiz-timer.critical { background: rgba(239,68,68,.55); animation: nds-pulse 1s infinite; }
        @keyframes nds-pulse { 0%,100%{opacity:1} 50%{opacity:.65} }

        /* ─── Info strip ─── */
        .nds-quiz-info-strip {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 12px;
            padding: 6px 12px;
            background: #fff;
            border-radius: 7px;
            border: 1px solid #e2e8f0;
        }
        .nds-quiz-info-strip strong { color: #1e293b; }

        /* ─── Two-col grid ─── */
        .nds-quiz-grid {
            display: grid;
            grid-template-columns: 1fr 190px;
            gap: 12px;
            align-items: start;
        }
        @media (max-width: 860px) {
            .nds-quiz-grid { grid-template-columns: 1fr; }
        }

        /* ─── Question card ─── */
        .nds-q-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
        }
        .nds-q-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #667eea;
            margin-bottom: 7px;
        }
        .nds-q-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 14px;
        }
        .nds-q-text {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.55;
            flex: 1;
        }
        .nds-flag-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            padding: 2px 4px;
            border-radius: 4px;
            transition: background .15s;
            flex-shrink: 0;
        }
        .nds-flag-btn:hover  { background: #fef3c7; }
        .nds-flag-btn.flagged { background: #fef3c7; }

        /* ─── Options ─── */
        .nds-options { display: flex; flex-direction: column; gap: 7px; margin-bottom: 16px; }
        .nds-option {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 11px;
            border: 1.5px solid #e2e8f0;
            border-radius: 7px;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .nds-option:hover { border-color: #667eea; background: #f8f9ff; }
        .nds-option input[type="radio"] { cursor: pointer; width: 14px; height: 14px; flex-shrink: 0; }
        .nds-opt-letter {
            width: 23px; height: 23px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: #475569;
            flex-shrink: 0;
        }
        .nds-opt-text { font-size: 13px; color: #334155; flex: 1; }

        /* ─── Nav row ─── */
        .nds-q-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
            gap: 8px;
        }
        .nds-btn {
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all .15s;
            white-space: nowrap;
        }
        .nds-btn:disabled { opacity: .45; cursor: not-allowed; }
        .nds-btn-ghost   { background: #f1f5f9; color: #475569; }
        .nds-btn-ghost:hover:not(:disabled)   { background: #e2e8f0; }
        .nds-btn-primary { background: #667eea; color: #fff; }
        .nds-btn-primary:hover:not(:disabled) { background: #5568d3; }
        .nds-btn-success { background: #10b981; color: #fff; }
        .nds-btn-success:hover:not(:disabled) { background: #059669; }
        .nds-btn-danger  { background: #ef4444; color: #fff; }
        .nds-btn-danger:hover:not(:disabled)  { background: #dc2626; }
        .nds-q-progress  { font-size: 12px; color: #94a3b8; white-space: nowrap; }

        /* ─── Navigator sidebar ─── */
        .nds-navigator {
            background: #fff;
            border-radius: 10px;
            padding: 13px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
            position: sticky;
            top: 14px;
        }
        .nds-nav-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .nds-nav-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
        }
        .nds-nav-btn {
            aspect-ratio: 1;
            border: 1.5px solid #e2e8f0;
            border-radius: 5px;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700;
            cursor: pointer;
            transition: all .15s;
            background: #fff; color: #64748b;
            user-select: none;
        }
        .nds-nav-btn:hover    { border-color: #667eea; color: #667eea; }
        .nds-nav-btn.current  { background: #667eea; color: #fff; border-color: #667eea; }
        .nds-nav-btn.answered { background: #d1fae5; color: #065f46; border-color: #10b981; }
        .nds-nav-btn.flagged  { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
        .nds-nav-legend {
            margin-top: 10px;
            display: flex; flex-direction: column; gap: 4px;
            font-size: 10px; color: #64748b;
        }
        .nds-nav-legend-item { display: flex; align-items: center; gap: 5px; }
        .nds-nav-legend-dot  {
            width: 11px; height: 11px;
            border-radius: 3px; border: 1.5px solid; flex-shrink: 0;
        }

        /* ─── Review screen ─── */
        #nds-review-screen { display: none; }
        .nds-review-header {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .nds-review-header h2 { margin: 0; font-size: 15px; }
        .nds-review-header-sub { font-size: 11px; opacity:.9; margin: 2px 0 0; }
        .nds-review-stats { font-size: 12px; background: rgba(255,255,255,.2); padding: 4px 10px; border-radius: 5px; white-space: nowrap; }
        .nds-unanswered-warn {
            font-size: 12px; color: #92400e;
            background: #fffbeb; border: 1px solid #fde68a;
            border-radius: 6px; padding: 7px 10px; margin-bottom: 10px;
        }
        .nds-review-list   { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
        .nds-review-item {
            background: #fff;
            border-radius: 8px;
            padding: 11px 13px;
            border-left: 3px solid #e2e8f0;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .nds-review-item.answered   { border-left-color: #10b981; }
        .nds-review-item.unanswered { border-left-color: #f59e0b; }
        .nds-review-num {
            width: 24px; height: 24px;
            background: #f1f5f9; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700; color: #475569;
            flex-shrink: 0;
        }
        .nds-review-body { flex: 1; min-width: 0; }
        .nds-review-q    { font-size: 12px; font-weight: 600; color: #1e293b; line-height: 1.4; margin-bottom: 3px; }
        .nds-review-ans  { font-size: 11px; color: #64748b; }
        .nds-review-ans .chosen { color: #10b981; font-weight: 700; }
        .nds-review-ans .none   { color: #f59e0b; font-weight: 700; }
        .nds-review-edit-btn {
            font-size: 11px; color: #667eea; background: none;
            border: none; cursor: pointer; padding: 0; font-weight: 700; flex-shrink: 0;
        }
        .nds-review-edit-btn:hover { text-decoration: underline; }
        .nds-review-actions {
            background: #fff; border-radius: 10px; padding: 14px 16px;
            display: flex; justify-content: space-between; align-items: center; gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
        }
        .nds-empty-state {
            text-align: center; padding: 60px 20px;
            background: white; border-radius: 12px;
        }
    </style>
</head>
<body <?php body_class('nds-quiz-body'); ?>>
<?php function_exists('wp_body_open') && wp_body_open(); ?>

<div id="nds-quiz-app">
<?php
global $wpdb;

$content_id = (int) get_query_var('nds_portal_quiz');
$student_id = (int) nds_portal_get_current_student_id();

if ($student_id <= 0 || $content_id <= 0) {
    echo '<div class="nds-empty-state"><h2>Invalid Request</h2><p>Unable to load quiz.</p></div>';
    echo '</div>'; wp_footer(); echo '</body></html>'; exit;
}

$quiz_content = $wpdb->get_row($wpdb->prepare(
    "SELECT lc.id, lc.title, lc.description, lc.module_id, lc.quiz_data, lc.time_limit_minutes,
            lc.attempts_allowed, lc.shuffle_questions, lc.pass_percentage,
            m.name AS module_name, m.id AS mid, c.name AS course_name
     FROM {$wpdb->prefix}nds_lecturer_content lc
     LEFT JOIN {$wpdb->prefix}nds_modules m ON m.id = lc.module_id
     LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = m.course_id
     WHERE lc.id = %d AND lc.content_type = 'quiz' AND lc.status = 'published'
     LIMIT 1",
    $content_id
), ARRAY_A);

if (!$quiz_content) {
    echo '<div class="nds-empty-state"><h2>Quiz Not Found</h2></div>';
    echo '</div>'; wp_footer(); echo '</body></html>'; exit;
}

$questions = json_decode($quiz_content['quiz_data'], true) ?: array();
if (empty($questions)) {
    echo '<div class="nds-empty-state"><h2>No Questions</h2><p>This quiz has no questions yet.</p></div>';
    echo '</div>'; wp_footer(); echo '</body></html>'; exit;
}

$shuffle            = !empty($quiz_content['shuffle_questions']);
if ($shuffle) { shuffle($questions); }
$time_limit_minutes = isset($quiz_content['time_limit_minutes']) ? (int) $quiz_content['time_limit_minutes'] : 0;
$module_id          = (int) ($quiz_content['mid'] ?? 0);
$module_detail_url  = $module_id > 0 ? home_url('/portal/module/' . $module_id . '/') : home_url('/portal/');
$total_q            = count($questions);
?>

<div class="nds-quiz-page">

    <!-- ═══ QUIZ SCREEN ═══ -->
    <div id="nds-quiz-screen">

        <div class="nds-quiz-header">
            <div>
                <h1><?php echo esc_html($quiz_content['title']); ?></h1>
                <p class="nds-quiz-header-sub">
                    <?php echo esc_html($quiz_content['course_name']); ?> &bull;
                    <?php echo esc_html($quiz_content['module_name']); ?>
                </p>
            </div>
            <?php if ($time_limit_minutes > 0) : ?>
                <div class="nds-quiz-timer" id="nds-quiz-timer">
                    ⏱️ <span id="nds-timer-display"><?php echo (int) $time_limit_minutes; ?>:00</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="nds-quiz-info-strip">
            <span><strong><?php echo $total_q; ?></strong> questions</span>
            <?php if ($time_limit_minutes > 0) : ?>
                <span>&bull; <strong><?php echo (int) $time_limit_minutes; ?></strong> min limit</span>
            <?php endif; ?>
            <?php if (!empty($quiz_content['attempts_allowed'])) : ?>
                <span>&bull; <strong><?php echo (int) $quiz_content['attempts_allowed']; ?></strong>
                    attempt<?php echo (int) $quiz_content['attempts_allowed'] !== 1 ? 's' : ''; ?></span>
            <?php endif; ?>
        </div>

        <div class="nds-quiz-grid">
            <!-- Questions column -->
            <div>
                <form id="nds-quiz-form" method="POST">
                    <?php wp_nonce_field('nds_portal_quiz_nonce', 'quiz_nonce'); ?>
                    <input type="hidden" name="action"            value="nds_portal_submit_quiz_attempt">
                    <input type="hidden" name="content_id"        value="<?php echo (int) $content_id; ?>">
                    <input type="hidden" name="flagged_questions" id="nds-flagged-questions" value="">

                    <?php foreach ($questions as $idx => $question) :
                        $q_type  = sanitize_key((string) ($question['type'] ?? 'multiple_choice'));
                        $q_text  = wp_kses_post((string) ($question['text'] ?? 'Question ' . ($idx + 1)));
                        $options = is_array($question['options'] ?? null) ? array_values($question['options']) : array();
                        $letters = array('A','B','C','D','E','F');
                    ?>
                        <div class="nds-q-card nds-question-item"
                             data-question-index="<?php echo (int) $idx; ?>"
                             id="nds-question-<?php echo (int) $idx; ?>">

                            <div class="nds-q-label">
                                Question <?php echo $idx + 1; ?> of <?php echo $total_q; ?>
                            </div>

                            <div class="nds-q-row">
                                <div class="nds-q-text"><?php echo $q_text; ?></div>
                                <button type="button" class="nds-flag-btn"
                                        data-question-index="<?php echo (int) $idx; ?>"
                                        title="Flag for review">🚩</button>
                            </div>

                            <?php if ($q_type === 'multiple_choice' && !empty($options)) : ?>
                                <div class="nds-options">
                                    <?php foreach ($options as $oi => $opt) :
                                        $letter = $letters[$oi] ?? chr(65 + $oi);
                                    ?>
                                        <label class="nds-option">
                                            <input type="radio"
                                                   name="answers[<?php echo (int) $idx; ?>]"
                                                   value="<?php echo esc_attr($letter); ?>">
                                            <span class="nds-opt-letter"><?php echo esc_html($letter); ?></span>
                                            <span class="nds-opt-text"><?php echo wp_kses_post($opt); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="nds-q-nav">
                                <button type="button" class="nds-btn nds-btn-ghost"
                                        onclick="ndsQuiz.navigate(-1)"
                                        <?php echo $idx === 0 ? 'disabled' : ''; ?>>
                                    ← Prev
                                </button>
                                <span class="nds-q-progress">
                                    <span class="nds-prog-cur"><?php echo $idx + 1; ?></span>
                                    / <?php echo $total_q; ?>
                                </span>
                                <?php if ($idx < $total_q - 1) : ?>
                                    <button type="button" class="nds-btn nds-btn-ghost"
                                            onclick="ndsQuiz.navigate(1)">
                                        Next →
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="nds-btn nds-btn-success"
                                            onclick="ndsQuiz.showReview()">
                                        ✓ Finish Quiz
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>

            <!-- Navigator sidebar -->
            <div class="nds-navigator">
                <div class="nds-nav-title">Questions</div>
                <div class="nds-nav-grid" id="nds-question-navigator">
                    <?php for ($n = 1; $n <= $total_q; $n++) : ?>
                        <div class="nds-nav-btn <?php echo $n === 1 ? 'current' : ''; ?>"
                             data-question-index="<?php echo $n - 1; ?>"
                             onclick="ndsQuiz.goTo(<?php echo $n - 1; ?>)">
                            <?php echo $n; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="nds-nav-legend">
                    <div class="nds-nav-legend-item">
                        <div class="nds-nav-legend-dot" style="background:#d1fae5;border-color:#10b981;"></div>
                        Answered
                    </div>
                    <div class="nds-nav-legend-item">
                        <div class="nds-nav-legend-dot" style="background:#fef3c7;border-color:#f59e0b;"></div>
                        Flagged
                    </div>
                    <div class="nds-nav-legend-item">
                        <div class="nds-nav-legend-dot" style="background:#667eea;border-color:#667eea;"></div>
                        Current
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /#nds-quiz-screen -->


    <!-- ═══ REVIEW SCREEN ═══ -->
    <div id="nds-review-screen">

        <div class="nds-review-header">
            <div>
                <h2>Review Your Answers</h2>
                <p class="nds-review-header-sub">
                    <?php echo esc_html($quiz_content['title']); ?> &bull; <?php echo $total_q; ?> questions
                </p>
            </div>
            <span class="nds-review-stats" id="nds-review-summary"></span>
        </div>

        <div id="nds-unanswered-warn" class="nds-unanswered-warn" style="display:none;"></div>

        <div class="nds-review-list" id="nds-review-list">
            <?php foreach ($questions as $idx => $question) :
                $q_text = wp_kses_post((string) ($question['text'] ?? 'Question ' . ($idx + 1)));
            ?>
                <div class="nds-review-item unanswered"
                     id="nds-review-item-<?php echo (int) $idx; ?>">
                    <div class="nds-review-num"><?php echo $idx + 1; ?></div>
                    <div class="nds-review-body">
                        <div class="nds-review-q"><?php echo $q_text; ?></div>
                        <div class="nds-review-ans" id="nds-review-ans-<?php echo (int) $idx; ?>">
                            <span class="none">Not answered</span>
                        </div>
                    </div>
                    <button type="button" class="nds-review-edit-btn"
                            onclick="ndsQuiz.backToQ(<?php echo (int) $idx; ?>)">
                        Edit
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="nds-review-actions">
            <button type="button" class="nds-btn nds-btn-ghost"
                    onclick="ndsQuiz.backToQuestions()">
                ← Back to Questions
            </button>
            <button type="button" class="nds-btn nds-btn-danger"
                    id="nds-final-submit-btn"
                    onclick="ndsQuiz.finalSubmit()">
                Submit Quiz →
            </button>
        </div>

        <!-- Hidden form for final submission -->
        <form id="nds-submit-form" method="POST" style="display:none;">
            <?php wp_nonce_field('nds_portal_quiz_nonce', 'nonce'); ?>
            <input type="hidden" name="action"            value="nds_portal_submit_quiz_attempt">
            <input type="hidden" name="content_id"        value="<?php echo (int) $content_id; ?>">
            <input type="hidden" name="flagged_questions" id="nds-submit-flagged" value="">
            <div id="nds-submit-answers"></div>
        </form>

    </div><!-- /#nds-review-screen -->

</div><!-- /.nds-quiz-page -->

<!-- Fixed bottom back bar -->
<div class="nds-back-bar">
    <a href="<?php echo esc_url($module_detail_url); ?>">← Back to Module</a>
    <span class="nds-back-bar-title">| <?php echo esc_html($quiz_content['title']); ?></span>
</div>

<script>
const ndsQuiz = (function () {
    const TOTAL       = <?php echo $total_q; ?>;
    const TIME_MINS   = <?php echo (int) $time_limit_minutes; ?>;
    const MODULE_URL  = <?php echo wp_json_encode(esc_url($module_detail_url)); ?>;

    let current  = 0;
    const flagged = new Set();
    let timerInt = null;

    /* ── Timer ── */
    function startTimer() {
        if (TIME_MINS <= 0) return;
        const end = Date.now() + TIME_MINS * 60000;
        function tick() {
            const rem = Math.max(0, end - Date.now());
            const m = Math.floor(rem / 60000);
            const s = Math.floor((rem % 60000) / 1000);
            const el = document.getElementById('nds-timer-display');
            if (el) el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            const box = document.getElementById('nds-quiz-timer');
            if (box) {
                if (rem < 300000) box.classList.add('warning');
                if (rem < 60000)  { box.classList.remove('warning'); box.classList.add('critical'); }
            }
            if (rem <= 0) { clearInterval(timerInt); finalSubmit(); }
        }
        tick();
        timerInt = setInterval(tick, 1000);
    }

    /* ── Show question ── */
    function showQ(idx) {
        document.querySelectorAll('.nds-question-item').forEach(el => el.style.display = 'none');
        const el = document.getElementById('nds-question-' + idx);
        if (el) el.style.display = 'block';
        document.querySelectorAll('.nds-prog-cur').forEach(e => e.textContent = idx + 1);
        document.querySelectorAll('.nds-nav-btn').forEach((btn, i) => {
            btn.classList.toggle('current', i === idx);
        });
        current = idx;
    }

    /* ── Update nav indicator ── */
    function updateIndicator(idx) {
        const btn = document.querySelector('.nds-nav-btn[data-question-index="' + idx + '"]');
        if (!btn) return;
        const checked = document.querySelector('input[name="answers[' + idx + ']"]:checked');
        if (flagged.has(idx)) {
            btn.classList.remove('answered'); btn.classList.add('flagged');
        } else if (checked) {
            btn.classList.remove('flagged'); btn.classList.add('answered');
        } else {
            btn.classList.remove('answered', 'flagged');
        }
    }

    /* ── HTML escape ── */
    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Show review ── */
    function showReview() {
        let answered = 0;
        for (let i = 0; i < TOTAL; i++) {
            const checked = document.querySelector('input[name="answers[' + i + ']"]:checked');
            const ansEl   = document.getElementById('nds-review-ans-' + i);
            const itemEl  = document.getElementById('nds-review-item-' + i);
            if (checked) {
                answered++;
                const optEl = checked.closest('.nds-option')?.querySelector('.nds-opt-text');
                const label = optEl ? checked.value + '. ' + esc(optEl.textContent.trim()) : checked.value;
                if (ansEl)  ansEl.innerHTML  = '<span class="chosen">' + label + '</span>';
                if (itemEl) { itemEl.classList.remove('unanswered'); itemEl.classList.add('answered'); }
            } else {
                if (ansEl)  ansEl.innerHTML  = '<span class="none">Not answered</span>';
                if (itemEl) { itemEl.classList.remove('answered'); itemEl.classList.add('unanswered'); }
            }
        }
        const summEl = document.getElementById('nds-review-summary');
        if (summEl) summEl.textContent = answered + ' / ' + TOTAL + ' answered';
        const warnEl = document.getElementById('nds-unanswered-warn');
        const miss   = TOTAL - answered;
        if (warnEl) {
            if (miss > 0) {
                warnEl.style.display = 'block';
                warnEl.textContent   = '⚠ ' + miss + ' question' + (miss > 1 ? 's' : '') + ' not yet answered — you can go back and answer them.';
            } else {
                warnEl.style.display = 'none';
            }
        }
        document.getElementById('nds-quiz-screen').style.display   = 'none';
        document.getElementById('nds-review-screen').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ── Final submit → redirect to module ── */
    function finalSubmit() {
        const btn = document.getElementById('nds-final-submit-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

        const container = document.getElementById('nds-submit-answers');
        container.innerHTML = '';
        for (let i = 0; i < TOTAL; i++) {
            const checked = document.querySelector('input[name="answers[' + i + ']"]:checked');
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'answers[' + i + ']';
            inp.value = checked ? checked.value : '';
            container.appendChild(inp);
        }
        const flagEl = document.getElementById('nds-submit-flagged');
        if (flagEl) flagEl.value = Array.from(flagged).join(',');

        const form   = document.getElementById('nds-submit-form');
        const params = new URLSearchParams(new FormData(form));

        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .finally(function () {
            window.location.href = MODULE_URL;
        });
    }

    /* ── Init ── */
    document.addEventListener('DOMContentLoaded', function () {
        showQ(0);
        startTimer();

        document.querySelectorAll('input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const idx = parseInt(this.closest('.nds-question-item').dataset.questionIndex);
                updateIndicator(idx);
            });
        });

        document.querySelectorAll('.nds-flag-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const idx = parseInt(this.dataset.questionIndex);
                flagged.has(idx) ? flagged.delete(idx) : flagged.add(idx);
                this.classList.toggle('flagged', flagged.has(idx));
                updateIndicator(idx);
                const fi = document.getElementById('nds-flagged-questions');
                if (fi) fi.value = Array.from(flagged).join(',');
            });
        });
    });

    return {
        navigate: function (dir) {
            const n = current + dir;
            if (n >= 0 && n < TOTAL) showQ(n);
        },
        goTo: function (idx) { showQ(idx); },
        showReview: showReview,
        backToQuestions: function () {
            document.getElementById('nds-review-screen').style.display = 'none';
            document.getElementById('nds-quiz-screen').style.display   = 'block';
            showQ(current);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        backToQ: function (idx) {
            document.getElementById('nds-review-screen').style.display = 'none';
            document.getElementById('nds-quiz-screen').style.display   = 'block';
            showQ(idx);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        finalSubmit: finalSubmit
    };
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
