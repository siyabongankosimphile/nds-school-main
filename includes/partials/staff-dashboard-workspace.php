<?php
if (!defined('ABSPATH')) {
    exit;
}

$selected_course_name = $selected_course['name'] ?? 'No course selected';
$selected_course_code = $selected_course['code'] ?? '';
$has_course_context = !empty($selected_course_id);

$quick_links = [
    ['label' => 'Add Course Content', 'desc' => 'Upload resources, links, and activities.', 'tab' => 'content', 'icon' => 'fa-plus-circle', 'color' => 'blue'],
    ['label' => 'Create Assessment', 'desc' => 'Build quizzes and assignments.', 'tab' => 'assessments', 'icon' => 'fa-clipboard-list', 'color' => 'orange'],
    ['label' => 'Grade Submissions', 'desc' => 'Review and score learner work.', 'tab' => 'marks', 'icon' => 'fa-check-circle', 'color' => 'green'],
    ['label' => 'Message Learners', 'desc' => 'Send announcements and direct messages.', 'tab' => 'communication', 'icon' => 'fa-comments', 'color' => 'indigo'],
];
?>

<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-5 sm:p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Workspace</h2>
                <p class="text-sm text-gray-600 mt-1">Start your most common lecturer tasks from one place.</p>
            </div>
            <div class="text-sm text-gray-600">
                <span class="font-medium">Current course:</span>
                <span><?php echo esc_html($selected_course_name); ?></span>
                <?php if (!empty($selected_course_code)) : ?>
                    <span class="text-gray-400">(<?php echo esc_html($selected_course_code); ?>)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$has_course_context) : ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 text-sm">
            Select a course from the top selector to unlock all workspace tools.
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <?php foreach ($quick_links as $link) :
            $url = nds_staff_tab_url($link['tab'], $selected_course_id ?: null);
            $is_disabled = !$has_course_context && $link['tab'] !== 'communication';
        ?>
            <a href="<?php echo esc_url($is_disabled ? '#' : $url); ?>"
               class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 hover:shadow-md transition <?php echo $is_disabled ? 'opacity-60 pointer-events-none' : ''; ?>">
                <div class="w-10 h-10 rounded-lg bg-<?php echo esc_attr($link['color']); ?>-100 flex items-center justify-center mb-3">
                    <i class="fas <?php echo esc_attr($link['icon']); ?> text-<?php echo esc_attr($link['color']); ?>-600"></i>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 mb-1"><?php echo esc_html($link['label']); ?></h3>
                <p class="text-xs text-gray-600 leading-relaxed"><?php echo esc_html($link['desc']); ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">What to do now</h3>
            <ul class="space-y-2 text-sm text-gray-700">
                <li class="flex items-start"><i class="fas fa-dot-circle text-blue-500 mt-1 mr-2"></i>Update this week's learning materials.</li>
                <li class="flex items-start"><i class="fas fa-dot-circle text-blue-500 mt-1 mr-2"></i>Check ungraded submissions before publishing marks.</li>
                <li class="flex items-start"><i class="fas fa-dot-circle text-blue-500 mt-1 mr-2"></i>Post one course announcement for learner clarity.</li>
            </ul>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Navigation tips</h3>
            <ul class="space-y-2 text-sm text-gray-700">
                <li class="flex items-start"><i class="fas fa-arrow-right text-gray-400 mt-1 mr-2"></i>Use <strong class="mx-1">Teaching</strong> for content and class planning.</li>
                <li class="flex items-start"><i class="fas fa-arrow-right text-gray-400 mt-1 mr-2"></i>Use <strong class="mx-1">Assessment</strong> for grading and reporting.</li>
                <li class="flex items-start"><i class="fas fa-arrow-right text-gray-400 mt-1 mr-2"></i>Use <strong class="mx-1">People</strong> for messaging and enrollment actions.</li>
            </ul>
        </div>
    </div>
</div>
