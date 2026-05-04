<?php
/**
 * Rooms Management - CRUD Interface
 * Manage halls, classrooms, kitchens, and other venues
 */
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions via admin_init (fires before any output, ensuring wp_redirect works)
add_action('admin_init', 'nds_handle_room_form');
add_action('init', 'nds_handle_room_form');
function nds_handle_room_form() {
    if (!isset($_POST['nds_room_action'])) {
        return;
    }
    if (!current_user_can('manage_options') && !function_exists('nds_can_manage_timetables') || (!current_user_can('manage_options') && !nds_can_manage_timetables())) {
        wp_die('Unauthorized');
    }

    check_admin_referer('nds_room_action');

    global $wpdb;
    $rooms_table = $wpdb->prefix . 'nds_rooms';

    $action = sanitize_text_field($_POST['nds_room_action']);

    if ($action === 'add' || $action === 'edit') {
        $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
        $code     = sanitize_text_field($_POST['code']);
        $name     = sanitize_text_field($_POST['name']);
        $type     = sanitize_text_field($_POST['type']);
        $capacity = isset($_POST['capacity']) ? intval($_POST['capacity']) : 0;
        $location  = sanitize_text_field($_POST['location'] ?? '');
        $equipment = sanitize_textarea_field($_POST['equipment'] ?? '');
        $amenities = sanitize_textarea_field($_POST['amenities'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $data = [
            'code'       => $code,
            'name'       => $name,
            'type'       => $type,
            'capacity'   => $capacity,
            'location'   => $location,
            'equipment'  => $equipment,
            'amenities'  => $amenities,
            'is_active'  => $is_active,
            'updated_at' => current_time('mysql'),
        ];
        $format = ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s'];

        if ($action === 'add') {
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';
            $result = $wpdb->insert($rooms_table, $data, $format);
            if ($result) {
                wp_redirect(nds_get_rooms_page_url(array('success' => 'added')));
            } else {
                wp_redirect(nds_get_rooms_page_url(array('error' => rawurlencode($wpdb->last_error))));
            }
        } else {
            $result = $wpdb->update($rooms_table, $data, ['id' => $room_id], $format, ['%d']);
            if ($result !== false) {
                wp_redirect(nds_get_rooms_page_url(array('success' => 'updated')));
            } else {
                wp_redirect(nds_get_rooms_page_url(array('error' => rawurlencode($wpdb->last_error))));
            }
        }
        exit;

    } elseif ($action === 'delete') {
        $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
        if ($room_id) {
            $result = $wpdb->delete($rooms_table, ['id' => $room_id], ['%d']);
            if ($result) {
                wp_redirect(nds_get_rooms_page_url(array('success' => 'deleted')));
            } else {
                wp_redirect(nds_get_rooms_page_url(array('error' => rawurlencode($wpdb->last_error))));
            }
            exit;
        }
    }
}

// Main Rooms Management Page
function nds_rooms_page() {
    if (!current_user_can('manage_options') && !function_exists('nds_can_manage_timetables') || (!current_user_can('manage_options') && !nds_can_manage_timetables())) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'nds_rooms';
    
    // Handle edit mode
    $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $edit_room = null;
    if ($edit_id) {
        $edit_room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$rooms_table} WHERE id = %d", $edit_id), ARRAY_A);
    }
    
    // Handle search and filters
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    
    $where_conditions = [];
    $where_values = [];
    
    if ($search) {
        $where_conditions[] = "(name LIKE %s OR code LIKE %s OR location LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    if ($type_filter) {
        $where_conditions[] = "type = %s";
        $where_values[] = $type_filter;
    }
    
    if ($status_filter === 'active') {
        $where_conditions[] = "is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "is_active = 0";
    }
    
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    if (!empty($where_values)) {
        $query = "SELECT * FROM {$rooms_table} {$where_sql} ORDER BY type, name";
        $rooms = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
    } else {
        $rooms = $wpdb->get_results("SELECT * FROM {$rooms_table} ORDER BY type, name", ARRAY_A);
    }
    
    // Get statistics data
    $all_rooms = $wpdb->get_results("SELECT * FROM {$rooms_table} ORDER BY name", ARRAY_A);
    $total_rooms_count = count($all_rooms);
    
    $active_rooms_list = array_values(array_filter($all_rooms, function($r) { return (int)$r['is_active'] === 1; }));
    $training_rooms_list = array_values(array_filter($all_rooms, function($r) { 
        return in_array(strtolower($r['type']), ['classroom', 'lab', 'workshop']); 
    }));
    $large_venues_list = array_values(array_filter($all_rooms, function($r) { return (int)$r['capacity'] >= 50; }));
    
    // Show success/error messages
    if (isset($_GET['success'])) {
        $messages = [
            'added' => 'Room added successfully!',
            'updated' => 'Room updated successfully!',
            'deleted' => 'Room deleted successfully!'
        ];
        $message = $messages[$_GET['success']] ?? 'Operation completed successfully!';
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
    
    if (isset($_GET['error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html($_GET['error']) . '</p></div>';
    }
    ?>
    
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-600 to-blue-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-building text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Rooms & Venues</h1>
                            <p class="text-gray-600">Manage halls, classrooms, kitchens, and other campus facilities</p>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo esc_url(nds_get_rooms_page_url(array('add' => 1))); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-lg flex items-center gap-2 transition-colors duration-200 shadow-sm hover:shadow-md">
                            <i class="fas fa-plus text-sm"></i>
                            Add New Room
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <?php if (isset($_GET['add']) || $edit_id): ?>
            <!-- Add / Edit Room Form -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-<?php echo $edit_id ? 'edit' : 'plus'; ?> text-blue-600 text-sm"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        <?php echo $edit_id ? 'Edit Room / Venue' : 'Add New Room / Venue'; ?>
                    </h2>
                </div>
                <form method="post" action="<?php echo esc_url(nds_get_rooms_page_url()); ?>" class="px-6 py-6">
                    <?php wp_nonce_field('nds_room_action'); ?>
                    <input type="hidden" name="nds_room_action" value="<?php echo $edit_id ? 'edit' : 'add'; ?>">
                    <?php if ($edit_id): ?>
                        <input type="hidden" name="room_id" value="<?php echo intval($edit_id); ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Code -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Room Code <span class="text-red-500">*</span></label>
                            <input type="text" name="code" value="<?php echo esc_attr($edit_room['code'] ?? ''); ?>" required
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm"
                                   placeholder="e.g. RM101">
                        </div>
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Room Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="<?php echo esc_attr($edit_room['name'] ?? ''); ?>" required
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm"
                                   placeholder="e.g. Main Lecture Hall">
                        </div>
                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                            <select name="type" required class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm bg-white">
                                <option value="">— Select Type —</option>
                                <option value="hall" <?php selected(($edit_room['type'] ?? ''), 'hall'); ?>>Hall</option>
                                <option value="classroom" <?php selected(($edit_room['type'] ?? ''), 'classroom'); ?>>Classroom</option>
                                <option value="kitchen" <?php selected(($edit_room['type'] ?? ''), 'kitchen'); ?>>Kitchen</option>
                                <option value="lab" <?php selected(($edit_room['type'] ?? ''), 'lab'); ?>>Laboratory</option>
                                <option value="workshop" <?php selected(($edit_room['type'] ?? ''), 'workshop'); ?>>Workshop</option>
                                <option value="other" <?php selected(($edit_room['type'] ?? ''), 'other'); ?>>Other</option>
                            </select>
                        </div>
                        <!-- Capacity -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Capacity</label>
                            <input type="number" name="capacity" value="<?php echo intval($edit_room['capacity'] ?? 0); ?>" min="0"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm"
                                   placeholder="0">
                        </div>
                        <!-- Location -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Location / Building</label>
                            <input type="text" name="location" value="<?php echo esc_attr($edit_room['location'] ?? ''); ?>"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm"
                                   placeholder="e.g. Block A, Ground Floor">
                        </div>
                        <!-- Equipment -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Equipment</label>
                            <textarea name="equipment" rows="3"
                                      class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm"
                                      placeholder="e.g. Projector, Whiteboard, PA System"><?php echo esc_textarea($edit_room['equipment'] ?? ''); ?></textarea>
                        </div>
                        <!-- Amenities -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Amenities</label>
                            <textarea name="amenities" rows="3"
                                      class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm"
                                      placeholder="e.g. Air conditioning, WiFi, Accessible"><?php echo esc_textarea($edit_room['amenities'] ?? ''); ?></textarea>
                        </div>
                        <!-- Active Status -->
                        <div class="md:col-span-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" <?php checked(intval($edit_room['is_active'] ?? 1), 1); ?>
                                       class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-700">Active (available for scheduling)</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-6 mt-6 border-t border-gray-100">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm transition-colors shadow-sm">
                            <?php echo $edit_id ? 'Update Room' : 'Add Room'; ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=nds-rooms')); ?>"
                           class="bg-white hover:bg-gray-50 text-gray-700 font-semibold py-2.5 px-6 rounded-lg text-sm border border-gray-300 transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div><!-- /max-w container -->
    </div><!-- /nds-tailwind-wrapper -->
    <?php return; endif; ?>

            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Venues -->
                <div onclick="openStatModal('total_rooms')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Total Venues</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_rooms_count); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fas fa-building text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Total buildings and facilities
                    </p>
                </div>

                <!-- Active Rooms -->
                <div onclick="openStatModal('active_rooms')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Active Rooms</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n(count($active_rooms_list)); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Available for bookings
                    </p>
                </div>

                <!-- Training Rooms -->
                <div onclick="openStatModal('training_rooms')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Training Rooms</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n(count($training_rooms_list)); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Classrooms, Labs & Workshops
                    </p>
                </div>

                <!-- Large Venues -->
                <div onclick="openStatModal('large_venues')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Large Venues</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n(count($large_venues_list)); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center">
                            <i class="fas fa-users text-orange-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Capacity of 50+ people
                    </p>
                </div>
            </div>
        
            <!-- Search & Filter Card -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-5 py-6 border-b border-gray-100 bg-gray-50/50">
                    <form method="get" action="" class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                        <input type="hidden" name="page" value="nds-rooms">
                        <!-- Search Box -->
                        <div class="md:col-span-4 lg:col-span-5">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Search Venue</label>
                            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search by name, code, or location..." 
                                   class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm shadow-sm transition-all duration-200">
                        </div>
                        <!-- Type Filter -->
                        <div class="md:col-span-2 lg:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Type</label>
                            <select name="type" class="block w-full py-2.5 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm bg-white">
                                <option value="">All Types</option>
                                <option value="hall" <?php selected($type_filter, 'hall'); ?>>Hall</option>
                                <option value="classroom" <?php selected($type_filter, 'classroom'); ?>>Classroom</option>
                                <option value="kitchen" <?php selected($type_filter, 'kitchen'); ?>>Kitchen</option>
                                <option value="lab" <?php selected($type_filter, 'lab'); ?>>Laboratory</option>
                                <option value="workshop" <?php selected($type_filter, 'workshop'); ?>>Workshop</option>
                                <option value="other" <?php selected($type_filter, 'other'); ?>>Other</option>
                            </select>
                        </div>
                        <!-- Status Filter -->
                        <div class="md:col-span-2 lg:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Status</label>
                            <select name="status" class="block w-full py-2.5 px-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm bg-white">
                                <option value="">All Status</option>
                                <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Inactive</option>
                            </select>
                        </div>
                        <!-- Buttons -->
                        <div class="md:col-span-4 lg:col-span-3 flex gap-2">
                            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-all duration-200 shadow-sm hover:shadow-md flex items-center justify-center gap-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <?php if ($search || $type_filter || $status_filter): ?>
                                <a href="<?php echo admin_url('admin.php?page=nds-rooms'); ?>" class="flex-1 bg-white hover:bg-gray-50 text-gray-700 font-bold py-2.5 px-4 rounded-lg text-sm border border-gray-300 transition-all duration-200 text-center flex items-center justify-center gap-2">
                                    <i class="fas fa-undo text-xs"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Rooms Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name & Equipment</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Capacity</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php if (empty($rooms)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 italic">
                                        No rooms found. Get started by <a href="<?php echo admin_url('admin.php?page=nds-rooms&add=1'); ?>" class="text-blue-600 hover:underline">adding your first room</a>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rooms as $room): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-1 bg-gray-100 text-gray-800 text-xs font-bold rounded-md">
                                                <?php echo esc_html($room['code']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-gray-900"><?php echo esc_html($room['name']); ?></div>
                                            <?php if ($room['equipment']): ?>
                                                <div class="text-xs text-gray-500 mt-0.5 max-w-xs truncate" title="<?php echo esc_attr($room['equipment']); ?>">
                                                    <i class="fas fa-tools mr-1 opacity-70"></i><?php echo esc_html(wp_trim_words($room['equipment'], 6)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $room['type']))); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium text-gray-700">
                                            <?php echo $room['capacity'] > 0 ? number_format($room['capacity']) : '<span class="text-gray-400">—</span>'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <?php echo esc_html($room['location'] ?: '—'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <?php if ($room['is_active']): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span>Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end gap-3">
                                                <a href="<?php echo admin_url('admin.php?page=nds-rooms&edit=' . intval($room['id'])); ?>" 
                                                   class="text-blue-600 hover:text-blue-800 transition-colors">Edit</a>
                                                <form method="post" action="" class="inline" onsubmit="return confirm('Are you sure you want to delete this room?');">
                                                    <?php wp_nonce_field('nds_room_action'); ?>
                                                    <input type="hidden" name="nds_room_action" value="delete">
                                                    <input type="hidden" name="room_id" value="<?php echo intval($room['id']); ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 transition-colors">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Card Modal (Matching Student Management Style) -->
    <div id="statModal" class="hidden" style="position:fixed; inset:0; z-index:999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="position:fixed; inset:0; background:rgba(0,0,0,0.5);" onclick="closeStatModal()"></div>
        <div style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:1rem;">
            <div style="background:#fff; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); width:100%; max-width:42rem; max-height:80vh; display:flex; flex-direction:column; position:relative;">
                <!-- Modal Header -->
                <div id="statModalHeader" style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid #e5e7eb; border-radius:1rem 1rem 0 0;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div id="modalIconBg" style="width:2.5rem; height:2.5rem; border-radius:0.5rem; display:flex; align-items:center; justify-content:center;">
                            <i id="modalIcon" style="font-size:1.25rem;"></i>
                        </div>
                        <div>
                            <h3 id="statModalTitle" style="font-size:1.125rem; font-weight:700; color:#111827; margin:0;"></h3>
                            <p id="statModalCount" style="font-size:0.875rem; color:#6b7280; margin:0;"></p>
                        </div>
                    </div>
                    <button onclick="closeStatModal()" style="color:#9ca3af; padding:0.5rem; border-radius:0.5rem; border:none; background:none; cursor:pointer;" onmouseover="this.style.color='#4b5563'; this.style.background='#f3f4f6'" onmouseout="this.style.color='#9ca3af'; this.style.background='none'">
                        <i class="fas fa-times" style="font-size:1.25rem;"></i>
                    </button>
                </div>
                <!-- Modal Body (Scrollable) -->
                <div style="overflow-y:auto; flex:1; padding:0.5rem;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="background:#f9fafb; position:sticky; top:0; z-index:10;">
                            <tr>
                                <th id="col1Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;"></th>
                                <th id="col2Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;"></th>
                            </tr>
                        </thead>
                        <tbody id="statModalBody">
                            <!-- Dynamic Content -->
                        </tbody>
                    </table>
                </div>
                <!-- Modal Footer -->
                <div style="padding:0.75rem 1.5rem; border-top:1px solid #e5e7eb; background:#f9fafb; border-radius:0 0 1rem 1rem; text-align:right;">
                    <button onclick="closeStatModal()" style="padding:0.5rem 1rem; font-size:0.875rem; font-weight:500; color:#374151; background:#fff; border:1px solid #d1d5db; border-radius:0.5rem; cursor:pointer;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statsData = {
            total_rooms: <?php echo json_encode($all_rooms); ?>,
            active_rooms: <?php echo json_encode($active_rooms_list); ?>,
            training_rooms: <?php echo json_encode($training_rooms_list); ?>,
            large_venues: <?php echo json_encode($large_venues_list); ?>
        };

        const modalConfig = {
            total_rooms: {
                title: 'All Campus Venues',
                col1: 'Room Code & Name',
                col2: 'Type / Location',
                icon: 'fas fa-building',
                iconColor: '#2563eb',
                iconBg: '#eff6ff'
            },
            active_rooms: {
                title: 'Active Rooms',
                col1: 'Room',
                col2: 'Capacity / Location',
                icon: 'fas fa-check-circle',
                iconColor: '#059669',
                iconBg: '#ecfdf5'
            },
            training_rooms: {
                title: 'Training Rooms',
                col1: 'Facility Name',
                col2: 'Type / Capacity',
                icon: 'fas fa-graduation-cap',
                iconColor: '#7c3aed',
                iconBg: '#f5f3ff'
            },
            large_venues: {
                title: 'Large Capacity Venues',
                col1: 'Venue Name',
                col2: 'Capacity / Type',
                icon: 'fas fa-users',
                iconColor: '#ea580c',
                iconBg: '#fff7ed'
            }
        };

        window.openStatModal = function(type) {
            const modal = document.getElementById('statModal');
            const config = modalConfig[type];
            const data = statsData[type];
            
            if (!modal || !config || !data) return;

            document.getElementById('statModalTitle').textContent = config.title;
            document.getElementById('statModalCount').textContent = data.length + ' item' + (data.length !== 1 ? 's' : '');
            document.getElementById('col1Header').textContent = config.col1;
            document.getElementById('col2Header').textContent = config.col2;
            
            const modalIcon = document.getElementById('modalIcon');
            const modalIconBg = document.getElementById('modalIconBg');
            
            modalIcon.className = config.icon;
            modalIcon.style.color = config.iconColor;
            modalIconBg.style.backgroundColor = config.iconBg;

            const tbody = document.getElementById('statModalBody');
            tbody.innerHTML = '';
            
            if (data && data.length > 0) {
                data.forEach(item => {
                    const row = document.createElement('tr');
                    row.style.cssText = 'border-bottom:1px solid #f3f4f6; transition: background 0.15s;';
                    row.onmouseover = function() { this.style.background = '#f9fafb'; };
                    row.onmouseout = function() { this.style.background = ''; };
                    
                    let col1 = '';
                    let col2 = '';
                    let typeLabel = (item.type || 'Venue').charAt(0).toUpperCase() + (item.type || 'Venue').slice(1);

                    col1 = `[${item.code}] ${item.name}`;
                    if (type === 'large_venues' || type === 'active_rooms') {
                        col2 = `Cap: ${item.capacity || 0} • ${item.location || 'N/A'}`;
                    } else {
                        col2 = `${typeLabel} • ${item.location || 'N/A'}`;
                    }

                    row.innerHTML = `
                        <td style="padding:0.75rem 1rem;">
                            <div style="display:flex; align-items:center;">
                                <div style="width:2rem; height:2rem; background:#f0fdf4; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:0.75rem;">
                                    <i class="fas fa-door-open" style="color:#10b981; font-size:0.75rem;"></i>
                                </div>
                                <div style="font-size:0.875rem; font-weight:600; color:#111827;">${col1}</div>
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#6b7280; font-weight:400;">${col2}</td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="2" style="padding:2rem; text-align:center; color:#9ca3af;">No items found</td></tr>`;
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };

        window.closeStatModal = function() {
            const modal = document.getElementById('statModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        };
    });
    </script>
    <?php
}
