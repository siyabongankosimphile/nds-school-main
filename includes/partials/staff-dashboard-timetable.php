<?php
if (!defined('ABSPATH')) {
    exit;
}
// This file expects: $staff, $staff_id, $courses_taught
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">My Timetable</h2>
            <p class="text-sm text-gray-500 mt-1">View your teaching schedule</p>
        </div>
    </div>

    <?php if (empty($courses_taught)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-900 mb-2">No Courses Assigned</h3>
            <p class="text-gray-600 mb-6">You are not currently assigned to teach any courses.</p>
        </div>
    <?php else: ?>
        <!-- FullCalendar Timetable View -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div id="nds-staff-calendar" style="min-height: 600px;"></div>
        </div>
        
        <!-- Event Details Modal (Staff) -->
        <div id="nds-event-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" onclick="if(event.target === this) closeEventModal();">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-screen overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 id="event-modal-title" class="text-2xl font-bold text-gray-900">Event Details</h2>
                            <button type="button" onclick="closeEventModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div id="event-modal-content">
                            <!-- Content will be populated by JavaScript -->
                        </div>
                        
                        <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200">
                            <button type="button" onclick="closeEventModal()"
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 font-medium">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Close event modal
function closeEventModal() {
    var modal = document.getElementById('nds-event-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}
</script>
