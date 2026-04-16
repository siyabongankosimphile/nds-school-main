<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// Modern Recipe Management with Tailwind CSS
function nds_content_management_page() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }

    global $wpdb;
    $recipes_table = $wpdb->prefix . 'nds_recipes';

    // Get statistics
    $all_recipes = $wpdb->get_results("SELECT * FROM {$recipes_table} ORDER BY created_at DESC", ARRAY_A);
    $total_recipes = count($all_recipes);
    $with_images_list = array_values(array_filter($all_recipes, function($r) { return !empty($r['image']); }));
    $recent_recipes = array_slice($all_recipes, 0, 6);

    // Handle search
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $search_condition = $search ? $wpdb->prepare("WHERE recipe_name LIKE %s", '%' . $search . '%') : '';

    // Get filtered recipes
    $recipes = $wpdb->get_results("SELECT * FROM {$recipes_table} {$search_condition} ORDER BY created_at DESC", ARRAY_A);

    ?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-utensils text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Recipe Management</h1>
                            <p class="text-sm text-gray-600 mt-1">Manage recipes and culinary content</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="<?php echo admin_url('admin.php?page=nds-add-recipe'); ?>" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 px-6 rounded-lg flex items-center gap-2 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            <i class="fas fa-plus"></i>
                            Add Recipe
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breadcrumb Navigation -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-4">
            <nav class="flex items-center space-x-2 text-sm text-gray-600">
                <a href="<?php echo admin_url('admin.php?page=nds-academy'); ?>" class="hover:text-purple-600 transition-colors flex items-center">
                    <i class="fas fa-home mr-1"></i>NDS Academy
                </a>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <span class="text-gray-900 font-medium">Recipe Management</span>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Total Recipes -->
                <div onclick="openStatModal('total')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Total Recipes</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_recipes); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <i class="fas fa-utensils text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        All recipes in the system.
                    </p>
                </div>

                <!-- Published Recipes -->
                <div onclick="openStatModal('total')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group text-left">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">Published</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n($total_recipes); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Published recipes available.
                    </p>
                </div>

                <!-- Recipes with Images -->
                <div onclick="openStatModal('images')" class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 flex flex-col justify-between hover:bg-gray-50 transition-all duration-200 cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 group-hover:text-gray-700">With Images</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">
                                <?php echo number_format_i18n(count($with_images_list)); ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                            <i class="fas fa-images text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Recipes with images attached.
                    </p>
                </div>
            </div>
            <!-- Search and Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <input type="hidden" name="page" value="nds-recipes">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Recipes</label>
                        <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Recipe name..."
                               class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-200 flex items-center gap-2 shadow-md hover:shadow-lg">
                            <i class="fas fa-search"></i>Search
                        </button>
                        <?php if ($search): ?>
                            <a href="<?php echo admin_url('admin.php?page=nds-recipes'); ?>" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-200 flex items-center gap-2 shadow-md hover:shadow-lg">
                                <i class="fas fa-times"></i>Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Content Sections -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Recipes Section -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex justify-between items-center">
                                <h3 class="text-xl font-semibold text-gray-900">
                                    <i class="fas fa-utensils text-purple-600 mr-3"></i>Recipes <span class="text-purple-600">(<?php echo count($recipes); ?>)</span>
                                </h3>
                                <a href="<?php echo admin_url('admin.php?page=nds-add-recipe'); ?>" class="text-purple-600 hover:text-purple-800 font-medium text-sm transition-colors">
                                    Add New <i class="fas fa-plus ml-1"></i>
                                </a>
                            </div>
                        </div>

                        <div class="p-6">
                            <?php if ($recipes): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <?php foreach ($recipes as $recipe): ?>
                                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow duration-200">
                                            <div class="flex items-start justify-between mb-4">
                                                <div class="flex items-center space-x-3 flex-1 min-w-0">
                                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center border border-purple-200 flex-shrink-0">
                                                        <i class="fas fa-utensils text-purple-600 text-sm"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <h4 class="font-semibold text-gray-900 text-sm mb-1 truncate"><?php echo esc_html($recipe['recipe_name']); ?></h4>
                                                        <p class="text-xs text-gray-500">
                                                            <?php echo date('M j, Y', strtotime($recipe['created_at'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex gap-2 flex-shrink-0 ml-2">
                                                    <a href="<?php echo admin_url('admin.php?page=nds-recipe-details&recipe_id=' . $recipe['id']); ?>" class="text-blue-600 hover:text-blue-800 text-xs transition-colors p-1.5 rounded hover:bg-blue-50" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo admin_url('admin.php?page=nds-add-recipe&edit_recipe=' . $recipe['id']); ?>" class="text-indigo-600 hover:text-indigo-800 text-xs transition-colors p-1.5 rounded hover:bg-indigo-50" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="#" class="text-red-600 hover:text-red-800 text-xs transition-colors p-1.5 rounded hover:bg-red-50" title="Delete" onclick="return confirm('Are you sure you want to delete this recipe?')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </div>
                                            </div>

                                            <?php if ($recipe['image']): ?>
                                                <div class="mb-4 overflow-hidden rounded-lg">
                                                    <img src="<?php echo esc_url(wp_get_attachment_url($recipe['image'])); ?>" alt="<?php echo esc_attr($recipe['recipe_name']); ?>" class="w-full h-40 object-cover rounded-lg">
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($recipe['the_recipe']): ?>
                                                <div class="text-sm text-gray-600 line-clamp-3 leading-relaxed">
                                                    <?php echo wp_trim_words(strip_tags($recipe['the_recipe']), 20); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <i class="fas fa-utensils text-6xl text-gray-300 mb-4"></i>
                                    <h3 class="text-xl font-medium text-gray-900 mb-2">
                                        <?php echo $search ? 'No recipes found' : 'No recipes yet'; ?>
                                    </h3>
                                    <p class="text-gray-600 mb-6">
                                        <?php echo $search ? 'Try adjusting your search terms.' : 'Get started by adding your first recipe.'; ?>
                                    </p>
                                    <?php if (!$search): ?>
                                        <a href="<?php echo admin_url('admin.php?page=nds-add-recipe'); ?>" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-lg flex items-center gap-2 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                            <i class="fas fa-plus"></i>
                                            Add First Recipe
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Recent Recipes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-clock text-gray-600 mr-2"></i>Recent Recipes
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if ($recent_recipes): ?>
                                <div class="space-y-4">
                                    <?php foreach ($recent_recipes as $recipe): ?>
                                        <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center border border-purple-200">
                                                <i class="fas fa-utensils text-purple-600 text-xs"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-900"><?php echo esc_html($recipe['recipe_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($recipe['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-utensils text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-600 text-sm">No recipes yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-chart-bar text-green-600 mr-2"></i>Content Stats
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Total Recipes</span>
                                    <span class="font-semibold text-gray-900"><?php echo $total_recipes; ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Articles</span>
                                    <span class="font-semibold text-gray-900">0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Media Files</span>
                                    <span class="font-semibold text-gray-900">0</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Storage Used</span>
                                    <span class="font-semibold text-gray-900">0 MB</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-bolt text-orange-600 mr-2"></i>Quick Actions
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <a href="<?php echo admin_url('admin.php?page=nds-add-recipe'); ?>" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-2.5 px-4 rounded-lg text-sm transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg">
                                    <i class="fas fa-plus mr-2"></i>Add Recipe
                                </a>
                                <a href="<?php echo admin_url('admin-post.php?action=nds_export_recipes'); ?>" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-lg text-sm transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg">
                                    <i class="fas fa-download mr-2"></i>Export Recipes
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=nds-content-settings'); ?>" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2.5 px-4 rounded-lg text-sm transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    @media print {
        .bg-gray-50 { background: white !important; }
        .bg-white { background: white !important; }
        .shadow-sm, .shadow-md { box-shadow: none !important; }
        .border { border: 1px solid #e5e7eb !important; }
        .bg-purple-600 { display: none !important; }
        .hover\:bg-purple-700 { display: none !important; }
    }

    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    </style>

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
                                <th id="col1Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Recipe Name</th>
                                <th id="col2Header" style="padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:500; color:#6b7280; text-transform:uppercase;">Created Date</th>
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
            total: <?php echo json_encode($all_recipes); ?>,
            images: <?php echo json_encode($with_images_list); ?>
        };

        const modalConfig = {
            total: {
                title: 'All Recipes',
                icon: 'fas fa-utensils',
                iconColor: '#9333ea',
                iconBg: '#f5f3ff'
            },
            images: {
                title: 'Recipes with Images',
                icon: 'fas fa-images',
                iconColor: '#10b981',
                iconBg: '#f0fdf4'
            }
        };

        window.openStatModal = function(type) {
            const modal = document.getElementById('statModal');
            const config = modalConfig[type];
            const data = statsData[type];
            
            if (!modal || !config || !data) return;

            document.getElementById('statModalTitle').textContent = config.title;
            document.getElementById('statModalCount').textContent = data.length + ' item' + (data.length !== 1 ? 's' : '');
            
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
                    row.style.cssText = 'border-bottom:1px solid #f3f4f6; transition: background 0.15s; cursor: pointer;';
                    row.onclick = function() {
                        window.location.href = '<?php echo admin_url('admin.php?page=nds-recipe-details&recipe_id='); ?>' + item.id;
                    };
                    row.onmouseover = function() { this.style.background = '#f9fafb'; };
                    row.onmouseout = function() { this.style.background = ''; };
                    
                    const date = new Date(item.created_at).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });

                    row.innerHTML = `
                        <td style="padding:0.75rem 1rem;">
                            <div style="display:flex; align-items:center;">
                                <div style="width:2rem; height:2rem; background:#f5f3ff; border-radius:0.5rem; display:flex; align-items:center; justify-content:center; margin-right:0.75rem;">
                                    <i class="fas fa-utensils" style="color:#9333ea; font-size:0.75rem;"></i>
                                </div>
                                <div style="font-size:0.875rem; font-weight:600; color:#111827;">${item.recipe_name}</div>
                            </div>
                        </td>
                        <td style="padding:0.75rem 1rem; font-size:0.875rem; color:#6b7280;">${date}</td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="2" style="padding:2rem; text-align:center; color:#9ca3af;">No recipes found</td></tr>`;
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
