<?php
// Registration panel partial for learner portal Registration tab
if (!defined('ABSPATH')) exit;
?>
<?php if ($can_show_registration_panel) : ?>
<div class="rounded-lg border border-emerald-200 bg-white p-4" id="nds-registration-panel"
     data-course-id="<?php echo esc_attr((int) $registration_application['course_id']); ?>"
     data-nonce="<?php echo esc_attr(wp_create_nonce('nds_portal_nonce')); ?>">
    <div class="text-xs font-semibold tracking-wide text-emerald-700 uppercase mb-2">Registration</div>
    <div class="flex flex-col sm:flex-row gap-2 mb-3">
        <select id="nds-registration-action" class="border border-gray-300 rounded-lg px-3 py-2 text-sm flex-1"
            onchange="(function(el){var wrap=document.getElementById('nds-registration-module-wrap');if(!wrap){return;}var needs=['submit_registration','add_module','cancel_module'].indexOf(el.value)!==-1;wrap.classList.toggle('hidden',!needs);})(this)">
            <option value="">Registration actions</option>
            <option value="submit_registration">Submit registration</option>
            <option value="download_proof">Download proof of registration</option>
            <option value="add_module">Add module</option>
            <option value="cancel_module">Cancel module</option>
        </select>
        <button id="nds-registration-run" type="button" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">Apply</button>
    </div>
    <div id="nds-registration-module-wrap" class="hidden">
        <div class="text-xs text-gray-600 mb-2">Modules for your accepted course:</div>
        <?php if (!empty($registration_modules)) : ?>
            <label class="inline-flex items-center text-xs text-gray-700 mb-2">
                <input type="checkbox" id="nds-modules-select-all" class="mr-2">Select all modules
            </label>
            <div class="max-h-40 overflow-y-auto space-y-2 pr-1" id="nds-registration-modules">
                <?php foreach ($registration_modules as $module_row) : ?>
                    <?php
                    $module_id = (int) ($module_row['id'] ?? 0);
                    $checked = in_array($module_id, $registration_selected_module_ids, true);
                    ?>
                    <label class="flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" class="nds-module-pick" value="<?php echo esc_attr($module_id); ?>" <?php checked($checked); ?>>
                        <span><?php echo esc_html($module_row['name'] ?? 'Module'); ?><?php if (!empty($module_row['module_code'])) : ?> (<?php echo esc_html($module_row['module_code']); ?>)<?php endif; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-sm text-gray-600">No modules are configured for this accepted course yet.</p>
        <?php endif; ?>
    </div>
    <div id="nds-registration-feedback" class="mt-3 text-sm" style="display:none;"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('nds-registration-panel');
    var actionSelect = document.getElementById('nds-registration-action');
    var applyBtn = document.getElementById('nds-registration-run');
    var moduleWrap = document.getElementById('nds-registration-module-wrap');
    var feedback = document.getElementById('nds-registration-feedback');
    var selectAll = document.getElementById('nds-modules-select-all');
    if (!panel || !actionSelect || !applyBtn || !moduleWrap || panel.getAttribute('data-reg-standalone-bound') === '1') {
        return;
    }
    panel.setAttribute('data-reg-standalone-bound', '1');
    var needsModules = function (action) {
        return action === 'submit_registration' || action === 'add_module' || action === 'cancel_module';
    };
    var syncWrap = function () {
        moduleWrap.classList.toggle('hidden', !needsModules(actionSelect.value));
    };
    var syncSelectAll = function () {
        if (!selectAll) { return; }
        var checks = Array.prototype.slice.call(document.querySelectorAll('.nds-module-pick'));
        if (checks.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }
        var checkedCount = checks.filter(function (el) { return el.checked; }).length;
        selectAll.checked = checkedCount === checks.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < checks.length;
    };
    var showFeedback = function (message, isError) {
        if (!feedback) { return; }
        feedback.style.display = 'block';
        feedback.textContent = message;
        feedback.className = isError
            ? 'mt-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2'
            : 'mt-3 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-3 py-2';
    };
    actionSelect.addEventListener('change', syncWrap);
    actionSelect.addEventListener('input', syncWrap);
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var checked = !!selectAll.checked;
            document.querySelectorAll('.nds-module-pick').forEach(function (el) {
                el.checked = checked;
            });
            syncSelectAll();
        });
    }
    document.querySelectorAll('.nds-module-pick').forEach(function (el) {
        el.addEventListener('change', syncSelectAll);
    });
    applyBtn.addEventListener('click', function () {
        var selectedAction = actionSelect.value;
        var courseId = panel.getAttribute('data-course-id') || '';
        var nonce = panel.getAttribute('data-nonce') || '';
        if (!selectedAction) {
            showFeedback('Please choose a registration action first.', true);
            return;
        }
        syncWrap();
        var selectedModuleIds = Array.prototype.slice.call(document.querySelectorAll('.nds-module-pick:checked')).map(function (el) {
            return el.value;
        });
        if (needsModules(selectedAction) && selectedModuleIds.length === 0) {
            showFeedback('Please select at least one module for this action.', true);
            return;
        }
        applyBtn.disabled = true;
        applyBtn.textContent = 'Working...';
        var payload = new URLSearchParams();
        payload.append('action', 'nds_portal_registration_action');
        payload.append('nonce', nonce);
        payload.append('registration_action', selectedAction);
        payload.append('course_id', String(courseId));
        selectedModuleIds.forEach(function (id) {
            payload.append('module_ids[]', id);
        });
        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
            if (!json || !json.success) {
                showFeedback((json && json.data) ? json.data : 'Registration action failed.', true);
                return;
            }
            var data = json.data || {};
            showFeedback(data.message || 'Action completed successfully.', false);
            if (selectedAction === 'download_proof' && data.proof_content) {
                var blob = new Blob([data.proof_content], { type: 'text/plain;charset=utf-8' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = data.proof_filename || 'proof-of-registration.txt';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            }
            if (Array.isArray(data.enrolled_module_ids)) {
                var enrolled = data.enrolled_module_ids.map(function (v) { return parseInt(v, 10); });
                document.querySelectorAll('.nds-module-pick').forEach(function (el) {
                    el.checked = enrolled.indexOf(parseInt(el.value, 10)) !== -1;
                });
                syncSelectAll();
            }
        })
        .catch(function () {
            showFeedback('Something went wrong while processing the action. Please try again.', true);
        })
        .finally(function () {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        });
    });
    syncWrap();
    syncSelectAll();
});
</script>
<?php endif; ?>
