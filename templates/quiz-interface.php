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
        html, html.admin-bar {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
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
        body.nds-quiz-body .entry-content {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        .nds-quiz-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .nds-quiz-container {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 24px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        @media (max-width: 1024px) {
            .nds-quiz-container {
                grid-template-columns: 1fr;
            }
            .nds-quiz-navigator {
                max-height: 300px;
                overflow-y: auto;
            }
        }
        .nds-quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nds-quiz-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .nds-quiz-timer {
            background: rgba(255, 255, 255, 0.2);
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nds-quiz-timer.warning {
            background: rgba(239, 68, 68, 0.3);
            color: #fecaca;
        }
        .nds-quiz-timer.critical {
            background: rgba(239, 68, 68, 0.5);
            color: #fca5a5;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .nds-question-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .nds-question-number {
            color: #667eea;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .nds-question-text {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .nds-question-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }
        .nds-option {
            display: flex;
            align-items: center;
            padding: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .nds-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .nds-option input[type="radio"] {
            margin-right: 12px;
            cursor: pointer;
            width: 20px;
            height: 20px;
        }
        .nds-option.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .nds-option-label {
            font-size: 16px;
            cursor: pointer;
            flex: 1;
            user-select: none;
        }
        .nds-option-letter {
            display: inline-block;
            width: 32px;
            height: 32px;
            background: #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 12px;
            color: #374151;
        }
        .nds-quiz-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        .nds-btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .nds-btn-primary {
            background: #667eea;
            color: white;
        }
        .nds-btn-primary:hover:not(:disabled) {
            background: #5568d3;
        }
        .nds-btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        .nds-btn-secondary:hover:not(:disabled) {
            background: #d1d5db;
        }
        .nds-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .nds-quiz-navigator {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .nds-navigator-title {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 16px;
            letter-spacing: 0.5px;
        }
        .nds-question-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        @media (max-width: 1024px) {
            .nds-question-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }
        .nds-question-indicator {
            width: 100%;
            aspect-ratio: 1;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            color: #6b7280;
        }
        .nds-question-indicator:hover {
            border-color: #667eea;
            color: #667eea;
        }
        .nds-question-indicator.current {
            background: #667eea;
            color: white;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .nds-question-indicator.answered {
            background: #d1fae5;
            color: #047857;
            border-color: #10b981;
        }
        .nds-question-indicator.flagged {
            background: #fef3c7;
            color: #b45309;
            border-color: #f59e0b;
        }
        .nds-flag-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            transition: all 0.2s ease;
        }
        .nds-flag-btn:hover {
            transform: scale(1.2);
        }
        .nds-flag-btn.flagged {
            color: #f59e0b;
        }
        .nds-quiz-info {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .nds-quiz-info-text {
            color: #374151;
            font-size: 14px;
            line-height: 1.6;
        }
        .nds-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
    </style>
</head>
<body <?php body_class('nds-quiz-body'); ?>>
<?php function_exists('wp_body_open') && wp_body_open(); ?>

<div class="nds-quiz-wrapper bg-gray-50 min-h-screen">
    <?php
    global $wpdb;
    
    $content_id = (int) get_query_var('nds_portal_quiz');
    $student_id = (int) nds_portal_get_current_student_id();
    
    if ($student_id <= 0 || $content_id <= 0) {
        echo '<div class="nds-empty-state"><h2>Invalid Request</h2><p>Unable to load quiz.</p></div>';
        return;
    }
    
    // Fetch quiz content
    $quiz_content = $wpdb->get_row($wpdb->prepare(
        "SELECT lc.id, lc.title, lc.description, lc.module_id, lc.quiz_data, lc.time_limit_minutes,
                lc.attempts_allowed, lc.shuffle_questions, lc.pass_percentage,
                m.name AS module_name, c.name AS course_name
         FROM {$wpdb->prefix}nds_lecturer_content lc
         LEFT JOIN {$wpdb->prefix}nds_modules m ON m.id = lc.module_id
         LEFT JOIN {$wpdb->prefix}nds_courses c ON c.id = m.course_id
         WHERE lc.id = %d AND lc.content_type = 'quiz' AND lc.status = 'published'
         LIMIT 1",
        $content_id
    ), ARRAY_A);
    
    if (!$quiz_content) {
        echo '<div class="nds-empty-state"><h2>Quiz Not Found</h2><p>The quiz you\'re looking for could not be found.</p></div>';
        return;
    }
    
    // Decode quiz data
    $questions = json_decode($quiz_content['quiz_data'], true) ?: array();
    
    if (empty($questions)) {
        echo '<div class="nds-empty-state"><h2>No Questions</h2><p>This quiz has no questions yet.</p></div>';
        return;
    }
    
    $time_limit_minutes = isset($quiz_content['time_limit_minutes']) ? (int) $quiz_content['time_limit_minutes'] : 0;
    $shuffle = isset($quiz_content['shuffle_questions']) ? (bool) $quiz_content['shuffle_questions'] : false;
    
    if ($shuffle) {
        shuffle($questions);
    }
    
    $nonce = wp_create_nonce('nds_portal_quiz_nonce');
    $module_id = (int) ($quiz_content['module_id'] ?? 0);
    ?>
    
    <div class="nds-quiz-container">
        <div>
            <div class="nds-quiz-header">
                <div>
                    <h1><?php echo esc_html($quiz_content['title']); ?></h1>
                    <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 14px;">
                        <?php echo esc_html($quiz_content['course_name']); ?> • 
                        <?php echo esc_html($quiz_content['module_name']); ?>
                    </p>
                </div>
                <?php if ($time_limit_minutes > 0) : ?>
                    <div class="nds-quiz-timer" id="nds-quiz-timer">
                        ⏱️ <span id="nds-timer-display"><?php echo (int) $time_limit_minutes; ?>:00</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="nds-quiz-info">
                <div class="nds-quiz-info-text">
                    <strong><?php echo count($questions); ?></strong> questions
                    <?php if ($time_limit_minutes > 0) : ?>
                        • <strong><?php echo (int) $time_limit_minutes; ?></strong> minute time limit
                    <?php endif; ?>
                    <?php if ($quiz_content['attempts_allowed'] > 0) : ?>
                        • <strong><?php echo (int) $quiz_content['attempts_allowed']; ?></strong> attempt<?php echo $quiz_content['attempts_allowed'] !== 1 ? 's' : ''; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <form id="nds-quiz-form" method="POST">
                <?php wp_nonce_field('nds_portal_quiz_nonce', 'nonce'); ?>
                <input type="hidden" name="action" value="nds_portal_submit_quiz_attempt">
                <input type="hidden" name="content_id" value="<?php echo (int) $content_id; ?>">
                <input type="hidden" name="flagged_questions" id="nds-flagged-questions" value="">
                
                <div id="nds-questions-container">
                    <?php foreach ($questions as $idx => $question) : 
                        $q_type = sanitize_key((string) ($question['type'] ?? 'multiple_choice'));
                        $question_text = (string) ($question['text'] ?? 'Question ' . ($idx + 1));
                        $options = is_array($question['options'] ?? null) ? array_values($question['options']) : array();
                        $question_id = 'nds-question-' . $idx;
                    ?>
                        <div class="nds-question-content nds-question-item" data-question-index="<?php echo (int) $idx; ?>" id="<?php echo esc_attr($question_id); ?>">
                            <div class="nds-question-number">
                                Question <?php echo (int) ($idx + 1); ?> of <?php echo count($questions); ?>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 24px;">
                                <div class="nds-question-text" style="flex: 1;">
                                    <?php echo wp_kses_post($question_text); ?>
                                </div>
                                <button type="button" class="nds-flag-btn" data-question-index="<?php echo (int) $idx; ?>" title="Flag for review">
                                    🚩
                                </button>
                            </div>
                            
                            <?php if ($q_type === 'multiple_choice' && !empty($options)) : ?>
                                <div class="nds-question-options">
                                    <?php 
                                    $letters = array('A', 'B', 'C', 'D', 'E', 'F');
                                    foreach ($options as $opt_idx => $option_text) : 
                                        $letter = isset($letters[$opt_idx]) ? $letters[$opt_idx] : chr(65 + $opt_idx);
                                        $option_id = $question_id . '-' . $letter;
                                    ?>
                                        <label class="nds-option">
                                            <input type="radio" name="answers[<?php echo (int) $idx; ?>]" 
                                                   value="<?php echo esc_attr($letter); ?>" id="<?php echo esc_attr($option_id); ?>">
                                            <span class="nds-option-letter"><?php echo esc_html($letter); ?></span>
                                            <span class="nds-option-label"><?php echo wp_kses_post($option_text); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="nds-quiz-navigation">
                                <button type="button" class="nds-btn nds-btn-secondary" id="prev-btn" 
                                        onclick="nds_quiz_navigate(-1)" <?php echo $idx === 0 ? 'disabled' : ''; ?>>
                                    ← Previous
                                </button>
                                <span style="color: #6b7280; font-size: 14px;">
                                    <span id="nds-progress-current"><?php echo (int) ($idx + 1); ?></span> / 
                                    <span id="nds-progress-total"><?php echo count($questions); ?></span>
                                </span>
                                <button type="button" class="nds-btn nds-btn-secondary" id="next-btn" 
                                        onclick="nds_quiz_navigate(1)" <?php echo $idx === count($questions) - 1 ? 'style="display:none;"' : ''; ?>>
                                    Next →
                                </button>
                            </div>
                            
                            <?php if ($idx === count($questions) - 1) : ?>
                                <div class="nds-quiz-navigation" style="justify-content: flex-end; border-top: none; margin-top: 16px;">
                                    <button type="submit" class="nds-btn nds-btn-primary" 
                                            style="font-size: 18px; padding: 14px 32px;">
                                        ✓ Submit Quiz
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
        
        <!-- Question Navigator Panel -->
        <div class="nds-quiz-navigator">
            <div class="nds-navigator-title">Navigation</div>
            <div class="nds-question-grid" id="nds-question-navigator">
                <?php foreach (range(1, count($questions)) as $num) : ?>
                    <div class="nds-question-indicator current" data-question-index="<?php echo (int) ($num - 1); ?>" 
                         onclick="nds_quiz_go_to_question(<?php echo (int) ($num - 1); ?>)">
                        <?php echo (int) $num; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        const NDSQuiz = {
            currentQuestionIndex: 0,
            totalQuestions: <?php echo count($questions); ?>,
            timeLimitMinutes: <?php echo (int) $time_limit_minutes; ?>,
            flaggedQuestions: new Set(),
            timerInterval: null,
            startTime: null,
            
            init() {
                this.showQuestion(0);
                if (this.timeLimitMinutes > 0) {
                    this.startTimer();
                }
                this.attachEventListeners();
            },
            
            startTimer() {
                this.startTime = Date.now();
                const endTime = this.startTime + (this.timeLimitMinutes * 60 * 1000);
                
                const updateTimer = () => {
                    const now = Date.now();
                    const remaining = Math.max(0, endTime - now);
                    const minutes = Math.floor(remaining / 60000);
                    const seconds = Math.floor((remaining % 60000) / 1000);
                    
                    const display = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                    const timerEl = document.getElementById('nds-timer-display');
                    if (timerEl) {
                        timerEl.textContent = display;
                    }
                    
                    const timerContainer = document.getElementById('nds-quiz-timer');
                    if (remaining < 300000) { // 5 minutes
                        timerContainer?.classList.add('warning');
                    }
                    if (remaining < 60000) { // 1 minute
                        timerContainer?.classList.remove('warning');
                        timerContainer?.classList.add('critical');
                    }
                    
                    if (remaining <= 0) {
                        clearInterval(this.timerInterval);
                        document.getElementById('nds-quiz-form')?.submit();
                    }
                };
                
                updateTimer();
                this.timerInterval = setInterval(updateTimer, 1000);
            },
            
            showQuestion(index) {
                document.querySelectorAll('.nds-question-item').forEach(el => {
                    el.style.display = 'none';
                });
                const questionEl = document.querySelector('[data-question-index="' + index + '"]');
                if (questionEl) {
                    questionEl.style.display = 'block';
                }
                
                document.getElementById('nds-progress-current').textContent = index + 1;
                this.updateNavigator(index);
                this.currentQuestionIndex = index;
            },
            
            updateNavigator(index) {
                document.querySelectorAll('.nds-question-indicator').forEach((el, i) => {
                    el.classList.remove('current');
                    if (i === index) {
                        el.classList.add('current');
                    }
                });
            },
            
            attachEventListeners() {
                document.querySelectorAll('.nds-flag-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const index = parseInt(btn.dataset.questionIndex);
                        this.toggleFlag(index);
                    });
                });
                
                document.querySelectorAll('input[type="radio"]').forEach(input => {
                    input.addEventListener('change', () => {
                        this.updateAnswerIndicator();
                    });
                });
            },
            
            toggleFlag(index) {
                const btn = document.querySelector('[data-question-index="' + index + '"].nds-flag-btn');
                const indicator = document.querySelector('[data-question-index="' + index + '"].nds-question-indicator');
                
                if (this.flaggedQuestions.has(index)) {
                    this.flaggedQuestions.delete(index);
                    btn.classList.remove('flagged');
                    indicator?.classList.remove('flagged');
                } else {
                    this.flaggedQuestions.add(index);
                    btn.classList.add('flagged');
                    indicator?.classList.add('flagged');
                }
                
                this.updateFlaggedField();
            },
            
            updateFlaggedField() {
                const flaggedArray = Array.from(this.flaggedQuestions);
                document.getElementById('nds-flagged-questions').value = flaggedArray.join(',');
            },
            
            updateAnswerIndicator() {
                const questionEl = document.querySelector('[data-question-index="' + this.currentQuestionIndex + '"]');
                const hasAnswer = questionEl?.querySelector('input[type="radio"]:checked');
                const indicator = document.querySelector('[data-question-index="' + this.currentQuestionIndex + '"].nds-question-indicator');
                
                if (hasAnswer && !this.flaggedQuestions.has(this.currentQuestionIndex)) {
                    indicator?.classList.add('answered');
                }
            }
        };
        
        function nds_quiz_navigate(direction) {
            const newIndex = NDSQuiz.currentQuestionIndex + direction;
            if (newIndex >= 0 && newIndex < NDSQuiz.totalQuestions) {
                NDSQuiz.showQuestion(newIndex);
                document.querySelector('[data-question-index="' + newIndex + '"]')?.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        function nds_quiz_go_to_question(index) {
            NDSQuiz.showQuestion(index);
            document.querySelector('[data-question-index="' + index + '"]')?.scrollIntoView({ behavior: 'smooth' });
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            NDSQuiz.init();
        });
    </script>
</div>

<?php wp_footer(); ?>
</body>
</html>
