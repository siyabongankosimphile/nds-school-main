<?php
if (!defined('ABSPATH')) {
    exit;
}

$quiz = isset($quiz) && is_array($quiz) ? $quiz : [];
$questions = isset($questions) && is_array($questions) ? $questions : [];
$attempt = isset($attempt) && is_array($attempt) ? $attempt : null;
$selected_quiz_content = isset($selected_quiz_content) && is_array($selected_quiz_content) ? $selected_quiz_content : [];

$question_count = count($questions);
$questions_per_page = isset($quiz['questions_per_page']) ? (int) $quiz['questions_per_page'] : 0;
if ($questions_per_page <= 0) {
    $questions_per_page = $question_count > 0 ? $question_count : 1;
}
$total_pages = $question_count > 0 ? (int) ceil($question_count / $questions_per_page) : 1;
$time_limit_minutes = isset($quiz['time_limit']) ? (int) $quiz['time_limit'] : 0;
?>
<div class="moodle-quiz-taker max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-800"><?php echo esc_html((string) ($quiz['name'] ?? 'Quiz')); ?></h1>
                <p class="text-gray-600 mt-2"><?php echo wp_kses_post((string) ($quiz['description'] ?? '')); ?></p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">Time remaining</div>
                <div id="quiz-timer" class="text-2xl font-mono font-bold text-red-600">
                    <?php echo $time_limit_minutes > 0 ? esc_html(gmdate('H:i:s', $time_limit_minutes * 60)) : '--:--:--'; ?>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <div class="flex justify-between text-sm text-gray-600 mb-1">
                <span>Progress</span>
                <span id="progress-text">0 / <?php echo (int) $question_count; ?></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <form method="post" id="quiz-submit-form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <input type="hidden" name="action" value="nds_submit_quiz_attempt">
        <input type="hidden" name="quiz_id" value="<?php echo (int) ($quiz['id'] ?? 0); ?>">
        <input type="hidden" name="content_id" value="<?php echo (int) ($selected_quiz_content['id'] ?? 0); ?>">
        <input type="hidden" name="attempt_id" value="<?php echo (int) ($attempt['id'] ?? 0); ?>">
        <input type="hidden" name="attempt_start" value="<?php echo esc_attr(current_time('mysql')); ?>">
        <?php wp_nonce_field('nds_quiz_take_' . (int) ($quiz['id'] ?? 0), 'nds_quiz_take_nonce'); ?>

        <div id="questions-container">
            <?php foreach ($questions as $index => $question): ?>
                <?php $page = (int) floor($index / $questions_per_page) + 1; ?>
                <div class="question-page <?php echo $page > 1 ? 'hidden' : ''; ?>" data-page="<?php echo (int) $page; ?>">
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-4">
                        <div class="flex justify-between mb-4">
                            <span class="text-sm text-gray-500">Question <?php echo (int) ($index + 1); ?> of <?php echo (int) $question_count; ?></span>
                            <span class="text-sm font-medium">Marks: <?php echo esc_html(number_format((float) ($question['mark'] ?? 1), 2)); ?></span>
                        </div>

                        <div class="question-text prose max-w-none mb-6">
                            <?php echo wp_kses_post((string) ($question['question_text'] ?? '')); ?>
                        </div>

                        <?php $q_type = (string) ($question['question_type'] ?? 'multiple_choice'); ?>
                        <?php if ($q_type === 'multiple_choice'): ?>
                            <?php
                            $answers = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}nds_question_answers WHERE question_id = %d ORDER BY answer_order ASC, id ASC",
                                (int) $question['id']
                            ), ARRAY_A);
                            ?>
                            <div class="space-y-3">
                                <?php foreach ($answers as $ans): ?>
                                    <label class="flex items-start p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="q_<?php echo (int) $question['id']; ?>" value="<?php echo (int) $ans['id']; ?>" class="mt-1 mr-3">
                                        <span><?php echo esc_html((string) $ans['answer_text']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($q_type === 'true_false'): ?>
                            <div class="flex space-x-4">
                                <label class="flex items-center p-3 border rounded-lg flex-1 cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="q_<?php echo (int) $question['id']; ?>" value="true" class="mr-2"> True
                                </label>
                                <label class="flex items-center p-3 border rounded-lg flex-1 cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="q_<?php echo (int) $question['id']; ?>" value="false" class="mr-2"> False
                                </label>
                            </div>
                        <?php elseif ($q_type === 'short_answer'): ?>
                            <input type="text" name="q_<?php echo (int) $question['id']; ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Type your answer here...">
                        <?php elseif ($q_type === 'essay'): ?>
                            <textarea name="q_<?php echo (int) $question['id']; ?>" rows="8" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Write your answer in detail..."></textarea>
                        <?php else: ?>
                            <input type="text" name="q_<?php echo (int) $question['id']; ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Type your answer here...">
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-between mt-6">
            <button type="button" id="prev-btn" class="px-6 py-2 border rounded-lg hover:bg-gray-50 hidden">
                <i class="fas fa-chevron-left mr-2"></i> Previous
            </button>
            <div class="flex space-x-3 ml-auto">
                <button type="button" id="save-progress-btn" class="px-6 py-2 border rounded-lg hover:bg-gray-50">
                    <i class="fas fa-save mr-2"></i> Save Progress
                </button>
                <button type="button" id="next-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Next <i class="fas fa-chevron-right ml-2"></i>
                </button>
                <button type="submit" id="submit-btn" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg hidden">
                    <i class="fas fa-check-circle mr-2"></i> Submit Quiz
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function() {
    const form = document.getElementById('quiz-submit-form');
    if (!form) return;

    let timeLeft = <?php echo (int) ($time_limit_minutes * 60); ?>;
    let timerInterval;
    let currentPage = 1;
    const totalPages = <?php echo (int) $total_pages; ?>;
    const totalQuestions = <?php echo (int) $question_count; ?>;

    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    const saveBtn = document.getElementById('save-progress-btn');

    function startTimer() {
        if (timeLeft <= 0) return;
        timerInterval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                alert('Time is up! Your quiz will be submitted now.');
                form.requestSubmit();
                return;
            }
            timeLeft--;
            const hours = Math.floor(timeLeft / 3600);
            const minutes = Math.floor((timeLeft % 3600) / 60);
            const seconds = timeLeft % 60;
            const timerEl = document.getElementById('quiz-timer');
            if (timerEl) {
                timerEl.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        }, 1000);
    }

    function answeredCount() {
        let count = 0;
        document.querySelectorAll('[name^="q_"]').forEach((input) => {
            if (input.type === 'radio' && input.checked) {
                count++;
            }
            if ((input.tagName === 'INPUT' && input.type === 'text' || input.tagName === 'TEXTAREA') && input.value.trim() !== '') {
                count++;
            }
        });
        return count;
    }

    function showPage(page) {
        document.querySelectorAll('.question-page').forEach((el) => el.classList.add('hidden'));
        const active = document.querySelector(`.question-page[data-page="${page}"]`);
        if (active) active.classList.remove('hidden');

        if (prevBtn) prevBtn.classList.toggle('hidden', page === 1);
        if (submitBtn) submitBtn.classList.toggle('hidden', page !== totalPages);
        if (nextBtn) nextBtn.classList.toggle('hidden', page === totalPages);

        const answered = Math.min(answeredCount(), totalQuestions);
        const progressText = document.getElementById('progress-text');
        const progressBar = document.getElementById('progress-bar');
        if (progressText) progressText.textContent = `${answered} / ${totalQuestions}`;
        if (progressBar) progressBar.style.width = totalQuestions > 0 ? `${(answered / totalQuestions) * 100}%` : '0%';
    }

    function saveProgress() {
        const formData = new FormData(form);
        formData.set('action', 'nds_save_quiz_progress');
        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            method: 'POST',
            body: formData
        }).catch(() => {});
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                showPage(currentPage);
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                showPage(currentPage);
            }
        });
    }
    if (saveBtn) {
        saveBtn.addEventListener('click', saveProgress);
    }

    setInterval(saveProgress, 30000);

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then((res) => res.json())
        .then((data) => {
            if (data && data.success) {
                alert('Quiz submitted successfully.');
                window.location.reload();
            } else {
                alert((data && data.data) ? data.data : 'Unable to submit quiz.');
            }
        })
        .catch(() => {
            alert('Unable to submit quiz right now. Please try again.');
        });
    });

    showPage(currentPage);
    startTimer();
})();
</script>
