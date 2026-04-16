<?php
/**
 * Learner Dashboard - Documents Tab
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$learner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$learner = nds_get_student($learner_id);
$learner_data = (array) $learner;

if (!function_exists('nds_learner_document_public_url')) {
    function nds_learner_document_public_url($relative_path) {
        $relative_path = ltrim(str_replace('\\', '/', (string) $relative_path), '/');
        if ($relative_path === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $relative_path)) {
            return $relative_path;
        }

        $plugin_main_file = dirname(__DIR__, 2) . '/nds-school.php';
        return plugins_url('public/' . $relative_path, $plugin_main_file);
    }
}

// Helper function to get file icon based on extension
function nds_get_file_icon($file_path) {
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':
            return '<i class="fas fa-file-pdf text-red-600"></i>';
        case 'doc':
        case 'docx':
            return '<i class="fas fa-file-word text-blue-600"></i>';
        case 'xls':
        case 'xlsx':
            return '<i class="fas fa-file-excel text-green-600"></i>';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return '<i class="fas fa-file-image text-purple-600"></i>';
        case 'zip':
        case 'rar':
            return '<i class="fas fa-file-archive text-yellow-600"></i>';
        default:
            return '<i class="fas fa-file text-gray-600"></i>';
    }
}

// Get documents from application forms if available
$documents = [];
$application_forms = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}nds_application_forms 
     WHERE email = %s 
     ORDER BY submitted_at DESC 
     LIMIT 1",
    $learner_data['email'] ?? ''
), ARRAY_A);

if (!empty($application_forms)) {
    $app = $application_forms[0];
    $doc_fields = [
        'id_passport_applicant' => 'ID/Passport (Applicant)',
        'id_passport_responsible' => 'ID/Passport (Responsible Person)',
        'saqa_certificate' => 'SAQA Certificate',
        'study_permit' => 'Study Permit',
        'parent_spouse_id' => 'Parent/Spouse ID',
        'latest_results' => 'Latest Results',
        'proof_residence' => 'Proof of Residence',
        'highest_grade_cert' => 'Highest Grade Certificate',
        'proof_medical_aid' => 'Proof of Medical Aid'
    ];
    
    foreach ($doc_fields as $field => $label) {
        if (!empty($app[$field])) {
            $documents[] = [
                'name' => $label,
                'path' => $app[$field],
                'type' => 'application_document',
                'icon' => nds_get_file_icon($app[$field])
            ];
        }
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900">Documents</h2>
        <button onclick="ndsOpenUploadModal()" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm transition-colors">
            <i class="fas fa-upload mr-2"></i>
            Upload Document
        </button>
    </div>

    <!-- Document Categories -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Application Documents -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-file-alt text-blue-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Application Documents</h3>
            </div>
            <?php if (!empty($documents)): ?>
                <ul class="space-y-2">
                    <?php foreach ($documents as $doc): ?>
                        <?php $doc_url = nds_learner_document_public_url($doc['path'] ?? ''); ?>
                        <li class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                            <div class="flex items-center space-x-2">
                                <?php echo $doc['icon'] ?? '<i class="fas fa-file text-gray-600"></i>'; ?>
                                <span class="text-sm text-gray-700"><?php echo esc_html($doc['name']); ?></span>
                            </div>
                            <a href="<?php echo esc_url($doc_url); ?>" 
                               target="_blank"
                               class="text-blue-600 hover:text-blue-700 text-sm">
                                <i class="fas fa-download"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-sm text-gray-500 mb-3">No application documents found.</p>
                <button onclick="ndsOpenUploadModal()" class="text-xs text-blue-600 hover:text-blue-700 underline">
                    Upload documents
                </button>
            <?php endif; ?>
        </div>

        <!-- Academic Documents -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-green-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Academic Documents</h3>
            </div>
            <p class="text-sm text-gray-500">Transcripts, certificates, and academic records will appear here.</p>
        </div>

        <!-- Financial Documents -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                    <i class="fas fa-receipt text-amber-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Financial Documents</h3>
            </div>
            <p class="text-sm text-gray-500">Payment receipts and financial statements will appear here.</p>
        </div>
    </div>

    <!-- All Documents Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">All Documents</h2>
        <?php if (!empty($documents)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Upload Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($documents as $doc): ?>
                            <?php $doc_url = nds_learner_document_public_url($doc['path'] ?? ''); ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-2">
                                        <?php echo $doc['icon'] ?? '<i class="fas fa-file text-gray-600"></i>'; ?>
                                        <span class="text-sm font-medium text-gray-900"><?php echo esc_html($doc['name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    Application Document
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    N/A
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?php echo esc_url($doc_url); ?>" 
                                       target="_blank"
                                       class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                    <a href="<?php echo esc_url($doc_url); ?>" 
                                       download
                                       class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-download mr-1"></i>Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No Documents</h3>
                <p class="text-gray-600 mb-4">Upload documents to manage learner files.</p>
                <button onclick="ndsOpenUploadModal()" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-sm transition-colors">
                    <i class="fas fa-upload mr-2"></i>
                    Upload Document
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Document Modal -->
    <div id="nds-upload-document-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-gray-900">Upload Document</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="ndsCloseUploadModal()">
                        <span class="dashicons dashicons-no-alt text-xl"></span>
                    </button>
                </div>
                <div class="p-6">
                    <form id="nds-upload-document-form" method="post" enctype="multipart/form-data" class="space-y-4">
                        <?php wp_nonce_field('nds_upload_learner_document', 'nds_upload_document_nonce'); ?>
                        <input type="hidden" name="action" value="nds_upload_learner_document">
                        <input type="hidden" name="learner_id" value="<?php echo esc_attr($learner_id); ?>">

                        <div>
                            <label for="document_name" class="block text-sm font-semibold text-gray-900 mb-2">
                                Document Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="document_name" name="document_name" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                   placeholder="e.g., Transcript, Certificate, etc.">
                        </div>

                        <div>
                            <label for="document_category" class="block text-sm font-semibold text-gray-900 mb-2">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select id="document_category" name="document_category" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <option value="">Select category</option>
                                <option value="application">Application Document</option>
                                <option value="academic">Academic Document</option>
                                <option value="financial">Financial Document</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label for="document_file" class="block text-sm font-semibold text-gray-900 mb-2">
                                File <span class="text-red-500">*</span>
                            </label>
                            <input type="file" id="document_file" name="document_file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            <p class="text-xs text-gray-500 mt-1">Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</p>
                        </div>

                        <div>
                            <label for="document_notes" class="block text-sm font-semibold text-gray-900 mb-2">
                                Notes (optional)
                            </label>
                            <textarea id="document_notes" name="document_notes" rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                      placeholder="Add any notes about this document..."></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                            <button type="button"
                                    class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium"
                                    onclick="ndsCloseUploadModal()">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
                                <i class="fas fa-upload mr-2"></i>
                                Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function ndsOpenUploadModal() {
        const modal = document.getElementById('nds-upload-document-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function ndsCloseUploadModal() {
        const modal = document.getElementById('nds-upload-document-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            document.body.style.overflow = '';
            // Reset form
            const form = document.getElementById('nds-upload-document-form');
            if (form) form.reset();
        }
    }

    // Handle form submission
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('nds-upload-document-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Document uploaded successfully!');
                        ndsCloseUploadModal();
                        location.reload();
                    } else {
                        alert('Error: ' + (data.data || 'Failed to upload document'));
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
        }
    });
    </script>
</div>
