<?php
/**
 * Learner Dashboard - Documents Tab
 * Redesigned: required-document list with upload/download/delete per row.
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ── Required documents ────────────────────────────────────────────────────────
$required_docs = [
    'id_passport_applicant'   => 'ID/Passport (Applicant)',
    'id_passport_responsible' => 'ID/Passport (Responsible Person)',
    'saqa_certificate'        => 'SAQA Certificate',
    'study_permit'            => 'Study Permit',
    'parent_spouse_id'        => 'Parent/Spouse ID',
    'latest_results'          => 'Latest Academic Results',
    'proof_residence'         => 'Proof of Residence',
    'highest_grade_cert'      => 'Highest Grade Certificate',
    'proof_medical_aid'       => 'Proof of Medical Aid',
];

// ── Load uploaded documents for this student ─────────────────────────────────
$uploaded = [];
if ($learner_id > 0) {
    $doc_table    = $wpdb->prefix . 'nds_student_documents';
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $doc_table));
    if ($table_exists) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$doc_table} WHERE student_id = %d ORDER BY uploaded_at DESC",
            $learner_id
        ), ARRAY_A);
        foreach ($rows as $row) {
            $uploaded[$row['document_type']] = $row;
        }
    }
}

$upload_nonce = wp_create_nonce('nds_upload_learner_document');
$delete_nonce = wp_create_nonce('nds_delete_learner_document');
$ajax_url     = admin_url('admin-ajax.php');
$plugin_url   = plugin_dir_url(dirname(dirname(__FILE__)));

$uploaded_count = count($uploaded);
$total_count    = count($required_docs);
$pct            = $total_count > 0 ? round(($uploaded_count / $total_count) * 100) : 0;
?>

<div class="space-y-6">

    <!-- Header row -->
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900">Required Documents</h2>
        <p class="text-sm text-gray-500 nds-docs-summary"><?php echo $uploaded_count; ?> of <?php echo $total_count; ?> uploaded</p>
    </div>

    <!-- Progress bar -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700">Upload progress</span>
            <span class="text-sm font-semibold nds-docs-pct-label <?php echo $pct === 100 ? 'text-green-600' : 'text-blue-600'; ?>"><?php echo $pct; ?>%</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2">
            <div class="h-2 rounded-full nds-docs-progress-bar <?php echo $pct === 100 ? 'bg-green-500' : 'bg-blue-500'; ?> transition-all duration-500"
                 style="width: <?php echo $pct; ?>%"></div>
        </div>
    </div>

    <!-- Documents table -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width:3rem">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width:7rem">Uploaded</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width:12rem">Date Uploaded</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width:11rem">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100" id="nds-docs-tbody">
                    <?php $i = 1; foreach ($required_docs as $doc_type => $doc_label): ?>
                        <?php $doc = $uploaded[$doc_type] ?? null; ?>
                        <tr id="nds-doc-row-<?php echo esc_attr($doc_type); ?>" class="hover:bg-gray-50 transition-colors">

                            <!-- Index -->
                            <td class="px-4 py-4 text-sm text-gray-400"><?php echo $i++; ?></td>

                            <!-- Document name -->
                            <td class="px-4 py-4">
                                <span class="text-sm font-medium text-gray-900"><?php echo esc_html($doc_label); ?></span>
                            </td>

                            <!-- Status badge -->
                            <td class="px-4 py-4" id="nds-status-<?php echo esc_attr($doc_type); ?>">
                                <?php if ($doc): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        <i class="fas fa-check"></i> Yes
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                        <i class="fas fa-times"></i> No
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Date uploaded -->
                            <td class="px-4 py-4 text-sm text-gray-500" id="nds-date-<?php echo esc_attr($doc_type); ?>">
                                <?php if ($doc): ?>
                                    <?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($doc['uploaded_at']))); ?>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-4" id="nds-actions-<?php echo esc_attr($doc_type); ?>">
                                <div class="flex items-center gap-2">
                                    <?php if ($doc): ?>
                                        <a href="<?php echo esc_url($plugin_url . 'public/' . $doc['file_path']); ?>"
                                           download
                                           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <button type="button"
                                                data-doc-id="<?php echo intval($doc['id']); ?>"
                                                onclick="ndsDeleteDoc(<?php echo intval($doc['id']); ?>, '<?php echo esc_js($doc_type); ?>')"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    <?php else: ?>
                                        <button type="button"
                                                onclick="ndsUploadDoc('<?php echo esc_js($doc_type); ?>', '<?php echo esc_js($doc_label); ?>')"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                            <i class="fas fa-upload"></i> Upload
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.space-y-6 -->

<!-- ── Upload Modal ─────────────────────────────────────────────────────────── -->
<div id="nds-upload-document-modal"
     class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center"
     style="display:none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900" id="nds-upload-modal-title">Upload Document</h3>
            <button type="button" onclick="ndsCloseUploadModal()"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
            <form id="nds-upload-document-form" method="post" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action"                  value="nds_upload_learner_document">
                <input type="hidden" name="nds_upload_document_nonce" value="<?php echo esc_attr($upload_nonce); ?>">
                <input type="hidden" name="learner_id"              value="<?php echo esc_attr($learner_id); ?>">
                <input type="hidden" name="document_type"           id="nds-upload-doc-type"  value="">
                <input type="hidden" name="document_label"          id="nds-upload-doc-label" value="">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Document</label>
                    <p class="text-sm font-semibold text-gray-900" id="nds-upload-doc-name-display"></p>
                </div>

                <div>
                    <label for="nds-document-file" class="block text-sm font-medium text-gray-700 mb-1">
                        Select file <span class="text-red-500">*</span>
                    </label>
                    <input type="file" id="nds-document-file" name="document_file" required
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                           class="w-full text-sm text-gray-700 border border-gray-300 rounded-lg px-3 py-2
                                  focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="mt-1 text-xs text-gray-400">Accepted: PDF, DOC, DOCX, JPG, PNG — max 10 MB</p>
                </div>

                <div>
                    <label for="nds-document-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                    <textarea id="nds-document-notes" name="document_notes" rows="2"
                              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2
                                     focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Any additional notes…"></textarea>
                </div>

                <div id="nds-upload-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2"></div>

                <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                    <button type="button" onclick="ndsCloseUploadModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="nds-upload-submit-btn"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium
                                   text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var ajaxUrl     = <?php echo json_encode($ajax_url); ?>;
    var deleteNonce = <?php echo json_encode($delete_nonce); ?>;
    var pluginUrl   = <?php echo json_encode($plugin_url); ?>;
    var requiredDocs = <?php echo json_encode($required_docs); ?>;

    // ── Open Upload Modal ────────────────────────────────────────────────────
    window.ndsUploadDoc = function (docType, docLabel) {
        document.getElementById('nds-upload-doc-type').value            = docType;
        document.getElementById('nds-upload-doc-label').value           = docLabel;
        document.getElementById('nds-upload-doc-name-display').textContent = docLabel;
        document.getElementById('nds-upload-modal-title').textContent   = 'Upload: ' + docLabel;
        document.getElementById('nds-document-file').value              = '';
        document.getElementById('nds-document-notes').value             = '';
        var err = document.getElementById('nds-upload-error');
        err.classList.add('hidden');
        err.textContent = '';
        var modal = document.getElementById('nds-upload-document-modal');
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };

    // ── Close Upload Modal ───────────────────────────────────────────────────
    window.ndsCloseUploadModal = function () {
        var modal = document.getElementById('nds-upload-document-modal');
        modal.style.display = 'none';
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    };

    // Close on backdrop click
    document.getElementById('nds-upload-document-modal').addEventListener('click', function (e) {
        if (e.target === this) { ndsCloseUploadModal(); }
    });

    // ── Upload form submit ───────────────────────────────────────────────────
    document.getElementById('nds-upload-document-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var form    = this;
        var btn     = document.getElementById('nds-upload-submit-btn');
        var errBox  = document.getElementById('nds-upload-error');
        var origTxt = btn.innerHTML;

        btn.disabled  = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading…';
        errBox.classList.add('hidden');

        fetch(ajaxUrl, { method: 'POST', body: new FormData(form) })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    var docType  = document.getElementById('nds-upload-doc-type').value;
                    var docLabel = document.getElementById('nds-upload-doc-label').value;
                    ndsCloseUploadModal();
                    ndsRefreshRowAfterUpload(docType, docLabel, data.data);
                    ndsUpdateProgress();
                } else {
                    errBox.textContent = data.data || 'Upload failed. Please try again.';
                    errBox.classList.remove('hidden');
                    btn.disabled  = false;
                    btn.innerHTML = origTxt;
                }
            })
            .catch(function () {
                errBox.textContent = 'Network error. Please try again.';
                errBox.classList.remove('hidden');
                btn.disabled  = false;
                btn.innerHTML = origTxt;
            });
    });

    // ── Update row cells after a successful upload ───────────────────────────
    function ndsRefreshRowAfterUpload(docType, docLabel, data) {
        // Status
        document.getElementById('nds-status-' + docType).innerHTML =
            '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">' +
            '<i class="fas fa-check"></i> Yes</span>';

        // Date
        var ts = data.uploaded_at ? new Date(data.uploaded_at.replace(' ', 'T')) : new Date();
        document.getElementById('nds-date-' + docType).textContent =
            ts.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });

        // Actions
        var docId    = data.doc_id || 0;
        var fileHref = pluginUrl + 'public/' + (data.path || '');
        document.getElementById('nds-actions-' + docType).innerHTML =
            '<div class="flex items-center gap-2">' +
              '<a href="' + fileHref + '" download ' +
                 'class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">' +
                 '<i class="fas fa-download"></i> Download</a>' +
              '<button type="button" data-doc-id="' + docId + '" ' +
                      'onclick="ndsDeleteDoc(' + docId + ', \'' + ndsEscJs(docType) + '\')" ' +
                      'class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">' +
                 '<i class="fas fa-trash"></i> Delete</button>' +
            '</div>';
    }

    // ── Delete document ──────────────────────────────────────────────────────
    window.ndsDeleteDoc = function (docId, docType) {
        if (!confirm('Delete this document? This cannot be undone.')) { return; }

        var btn = document.querySelector('[data-doc-id="' + docId + '"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

        var fd = new FormData();
        fd.append('action',  'nds_delete_learner_document');
        fd.append('nonce',   deleteNonce);
        fd.append('doc_id',  docId);

        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    // Status → No
                    document.getElementById('nds-status-' + docType).innerHTML =
                        '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">' +
                        '<i class="fas fa-times"></i> No</span>';
                    // Date → dash
                    document.getElementById('nds-date-' + docType).innerHTML =
                        '<span class="text-gray-300">—</span>';
                    // Actions → Upload button
                    var label = requiredDocs[docType] || docType;
                    document.getElementById('nds-actions-' + docType).innerHTML =
                        '<div class="flex items-center gap-2">' +
                          '<button type="button" ' +
                                  'onclick="ndsUploadDoc(\'' + ndsEscJs(docType) + '\', \'' + ndsEscJs(label) + '\')" ' +
                                  'class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">' +
                            '<i class="fas fa-upload"></i> Upload</button>' +
                        '</div>';
                    ndsUpdateProgress();
                } else {
                    alert(data.data || 'Failed to delete document.');
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> Delete'; }
                }
            })
            .catch(function () {
                alert('Network error. Please try again.');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> Delete'; }
            });
    };

    // ── Recalculate + update progress bar ───────────────────────────────────
    function ndsUpdateProgress() {
        var rows   = document.querySelectorAll('#nds-docs-tbody tr');
        var total  = rows.length;
        var done   = 0;
        rows.forEach(function (row) {
            if (row.querySelector('.bg-green-100')) { done++; }
        });
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        var bar     = document.querySelector('.nds-docs-progress-bar');
        var pctLbl  = document.querySelector('.nds-docs-pct-label');
        var summary = document.querySelector('.nds-docs-summary');
        if (bar)     { bar.style.width = pct + '%'; }
        if (pctLbl)  { pctLbl.textContent = pct + '%'; }
        if (summary) { summary.textContent = done + ' of ' + total + ' uploaded'; }
    }

    // ── Escape string for inline JS ──────────────────────────────────────────
    function ndsEscJs(str) {
        return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }
}());
</script>