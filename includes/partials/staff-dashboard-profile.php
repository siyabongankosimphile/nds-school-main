<?php
if (!defined('ABSPATH')) {
    exit;
}

$profile_notice = isset($_GET['profile_notice']) ? sanitize_text_field(wp_unslash($_GET['profile_notice'])) : '';
$profile_error = isset($_GET['profile_error']) ? sanitize_text_field(wp_unslash($_GET['profile_error'])) : '';

$staff_profile = is_array($staff_data ?? null) ? $staff_data : array();
$portal_user = wp_get_current_user();
$staff_email = $staff_profile['email'] ?? ($portal_user->user_email ?? '');
$profile_form_action = isset($profile_form_action) && is_string($profile_form_action) && $profile_form_action !== ''
    ? $profile_form_action
    : 'nds_portal_update_staff_profile';
?>

<div class="max-w-4xl space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">My profile</h2>
        <p class="mt-1 text-sm text-gray-500">Update your staff details and change your password.</p>
    </div>

    <?php if ($profile_notice !== '') : ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <?php echo esc_html($profile_notice); ?>
        </div>
    <?php endif; ?>

    <?php if ($profile_error !== '') : ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <?php echo esc_html($profile_error); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-6">
        <input type="hidden" name="action" value="<?php echo esc_attr($profile_form_action); ?>">
        <?php wp_nonce_field('nds_staff_profile_action', 'nds_staff_profile_nonce'); ?>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-5">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Professional details</h3>
                <p class="mt-1 text-sm text-gray-500">Keep your staff portal contact details up to date.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">First name</span>
                    <input type="text" name="first_name" value="<?php echo esc_attr($staff_profile['first_name'] ?? ''); ?>" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm" required>
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Last name</span>
                    <input type="text" name="last_name" value="<?php echo esc_attr($staff_profile['last_name'] ?? ''); ?>" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm" required>
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Email</span>
                    <input type="email" name="email" value="<?php echo esc_attr($staff_email); ?>" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm" required>
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Phone</span>
                    <input type="text" name="phone" value="<?php echo esc_attr($staff_profile['phone'] ?? ''); ?>" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Date of birth</span>
                    <input type="date" name="dob" value="<?php echo esc_attr($staff_profile['dob'] ?? ''); ?>" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Gender</span>
                    <select name="gender" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">
                        <option value="">Select gender</option>
                        <?php foreach (array('Male', 'Female', 'Other') as $gender_option) : ?>
                            <option value="<?php echo esc_attr($gender_option); ?>" <?php selected(($staff_profile['gender'] ?? ''), $gender_option); ?>><?php echo esc_html($gender_option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label class="block">
                <span class="block text-sm font-medium text-gray-700 mb-1">Address</span>
                <textarea name="address" rows="3" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm"><?php echo esc_textarea($staff_profile['address'] ?? ''); ?></textarea>
            </label>

            <label class="block">
                <span class="block text-sm font-medium text-gray-700 mb-1">Profile picture URL</span>
                <input type="url" name="profile_picture" value="<?php echo esc_attr($staff_profile['profile_picture'] ?? ''); ?>" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm" placeholder="https://example.com/profile.jpg">
            </label>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-5">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Change password</h3>
                <p class="mt-1 text-sm text-gray-500"><?php echo esc_html(nds_get_password_policy_message()); ?></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Current password</span>
                    <input type="password" name="current_password" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm" autocomplete="current-password">
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">New password</span>
                    <input type="password" name="new_password" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm" autocomplete="new-password">
                </label>
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Confirm new password</span>
                    <input type="password" name="confirm_password" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm" autocomplete="new-password">
                </label>
            </div>

            <p class="text-xs text-gray-500">Leave the password fields blank if you only want to update your profile details.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex items-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                Save changes
            </button>
            <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="text-sm font-medium text-blue-700 hover:text-blue-900">Forgot password?</a>
        </div>
    </form>
</div>