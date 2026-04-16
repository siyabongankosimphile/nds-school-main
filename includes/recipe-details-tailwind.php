<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    // WordPress not loaded, define function but it will fail gracefully
    function nds_recipe_details_page_tailwind() {
        echo '<div class="wrap"><h1>Recipe Details</h1><p>WordPress not loaded properly.</p></div>';
    }
    return;
}

// Modern Recipe Details Page with Tailwind CSS, Breadcrumbs, and Quick Actions
function nds_recipe_details_page_tailwind() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
    global $wpdb;

    // Get recipe ID from URL
    $recipe_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$recipe_id) {
        wp_die('Invalid recipe ID');
    }

    // Get recipe data
    $recipe = recipes_get_recipe_by_id($recipe_id);
    if (!$recipe) {
        wp_die('Recipe not found');
    }

    // Parse recipe data
    $recipe_name = $recipe['recipe_name'];
    $image_id = $recipe['image'];
    
    // Safely decode gallery IDs
    $gallery_data = $recipe['gallery'] ?? '';
    if (is_string($gallery_data) && !empty($gallery_data)) {
        $gallery_ids = json_decode($gallery_data, true);
        if (!is_array($gallery_ids)) {
            $gallery_ids = [];
        }
    } else {
        $gallery_ids = [];
    }
    
    // Safely decode recipe data
    $recipe_data = $recipe['the_recipe'] ?? '';
    if (is_string($recipe_data) && !empty($recipe_data)) {
        $the_recipe = json_decode($recipe_data, true);
        if (!is_array($the_recipe)) {
            $the_recipe = [];
        }
    } else {
        $the_recipe = [];
    }

    // Get all recipes for quick browser
    $all_recipes = recipes_get_all_recipes();
    $current_index = -1;
    foreach ($all_recipes as $index => $rec) {
        if ($rec['id'] == $recipe_id) {
            $current_index = $index;
            break;
        }
    }

    // Navigation helpers
    $prev_recipe = $current_index > 0 ? $all_recipes[$current_index - 1] : null;
    $next_recipe = $current_index < count($all_recipes) - 1 ? $all_recipes[$current_index + 1] : null;

    // Get recipe statistics
    $total_recipes = count($all_recipes);

    // Check for success/error messages
    $success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
    $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

    ?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12 p-8" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">

        <!-- Success/Error Messages -->
        <?php if ($success === 'updated'): ?>
            <div class="max-w-7xl mx-auto mb-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-3"></i>
                        <div>
                            <h3 class="text-sm font-medium text-green-800">Recipe Updated Successfully</h3>
                            <p class="text-sm text-green-600 mt-1">Your recipe has been saved and updated.</p>
                        </div>
                        <button type="button" class="ml-auto text-green-600 hover:text-green-800" onclick="this.parentElement.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb Navigation -->
        <div class="max-w-7xl mx-auto mb-6">
            <nav class="flex items-center space-x-2 text-sm text-gray-600">
                <a href="<?php echo admin_url('admin.php?page=nds-academy'); ?>" class="hover:text-blue-600 transition-colors">
                    <i class="fas fa-home mr-1"></i>NDS Academy
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <a href="<?php echo admin_url('admin.php?page=nds-content-management'); ?>" class="hover:text-blue-600 transition-colors">
                    Recipe Management
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <a href="<?php echo admin_url('admin.php?page=nds-recipes'); ?>" class="hover:text-blue-600 transition-colors">
                    Recipes
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <span class="text-gray-900 font-medium"><?php echo esc_html($recipe_name); ?></span>
            </nav>
        </div>

        <!-- Quick Actions Bar -->
        <div class="max-w-7xl mx-auto mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <!-- Navigation Arrows -->
                        <div class="flex items-center space-x-2">
                            <?php if ($prev_recipe): ?>
                                <a href="<?php echo admin_url('admin.php?page=nds-recipe-details&id=' . $prev_recipe['id']); ?>"
                                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-chevron-left mr-2"></i>Previous
                                </a>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-400 cursor-not-allowed">
                                    <i class="fas fa-chevron-left mr-2"></i>Previous
                                </span>
                            <?php endif; ?>

                            <?php if ($next_recipe): ?>
                                <a href="<?php echo admin_url('admin.php?page=nds-recipe-details&id=' . $next_recipe['id']); ?>"
                                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                    Next<i class="fas fa-chevron-right ml-2"></i>
                                </a>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-400 cursor-not-allowed">
                                    Next<i class="fas fa-chevron-right ml-2"></i>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Recipe Counter -->
                        <div class="text-sm text-gray-500">
                            Recipe <?php echo ($current_index + 1); ?> of <?php echo $total_recipes; ?>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <!-- Edit Button -->
                        <button type="button" onclick="toggleEditMode()"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-edit mr-2"></i>Edit Recipe
                        </button>

                        <!-- Delete Button -->
                        <button type="button" onclick="confirmDelete()"
                                class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>

                        <!-- Back to Recipes -->
                        <a href="<?php echo admin_url('admin.php?page=nds-recipes'); ?>"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Recipes
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Main Recipe Content -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Recipe Header -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4">
                        <h1 class="text-2xl font-bold text-white"><?php echo esc_html($recipe_name); ?></h1>
                        <div class="flex items-center text-purple-100 text-sm mt-2">
                            <i class="fas fa-calendar mr-2"></i>
                            Created <?php echo date('F j, Y', strtotime($recipe['created_at'])); ?>
                        </div>
                    </div>

                    <!-- Recipe Image -->
                    <?php if ($image_id): ?>
                        <div class="p-6">
                            <div class="aspect-video rounded-lg overflow-hidden">
                                <img src="<?php echo esc_url(wp_get_attachment_url($image_id)); ?>"
                                     alt="<?php echo esc_attr($recipe_name); ?>"
                                     class="w-full h-full object-cover">
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recipe Meta Info -->
                    <div class="px-6 pb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <?php if (!empty($the_recipe['prep'])): ?>
                                <div class="bg-blue-50 rounded-lg p-4 text-center">
                                    <div class="text-2xl font-bold text-blue-600"><?php echo esc_html($the_recipe['prep']); ?>m</div>
                                    <div class="text-sm text-blue-600">Prep Time</div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($the_recipe['cooking'])): ?>
                                <div class="bg-red-50 rounded-lg p-4 text-center">
                                    <div class="text-2xl font-bold text-red-600"><?php echo esc_html($the_recipe['cooking']); ?>m</div>
                                    <div class="text-sm text-red-600">Cook Time</div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($the_recipe['servings'])): ?>
                                <div class="bg-green-50 rounded-lg p-4 text-center">
                                    <div class="text-2xl font-bold text-green-600"><?php echo esc_html($the_recipe['servings']); ?></div>
                                    <div class="text-sm text-green-600">Servings</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <?php if (!empty($the_recipe['mini_description'])): ?>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Description</h3>
                                <p class="text-gray-700"><?php echo esc_html($the_recipe['mini_description']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ingredients -->
                <?php if (!empty($the_recipe['ingredients'])): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i class="fas fa-list-check mr-2"></i>Ingredients
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <?php if (isset($the_recipe['ingredients']) && is_array($the_recipe['ingredients'])): ?>
                                    <?php foreach ($the_recipe['ingredients'] as $ingredient): ?>
                                    <?php if (!empty(trim($ingredient))): ?>
                                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                            <i class="fas fa-check-circle text-green-600"></i>
                                            <span class="text-gray-700"><?php echo esc_html($ingredient); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-span-full text-center py-8">
                                        <i class="fas fa-list-check text-4xl text-gray-300 mb-4"></i>
                                        <p class="text-gray-500">No ingredients listed</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Instructions -->
                <?php if (isset($the_recipe['steps']) && is_array($the_recipe['steps']) && !empty($the_recipe['steps'])): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i class="fas fa-list-ol mr-2"></i>Cooking Instructions
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($the_recipe['steps'] as $index => $step): ?>
                                    <?php if (!empty(trim($step))): ?>
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0 w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-semibold text-sm">
                                                <?php echo ($index + 1); ?>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-gray-700"><?php echo esc_html($step); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i class="fas fa-list-ol mr-2"></i>Cooking Instructions
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="text-center py-8">
                                <i class="fas fa-list-ol text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No cooking instructions available</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Gallery -->
                <?php if (is_array($gallery_ids) && !empty($gallery_ids)): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-orange-600 to-orange-700 px-6 py-4">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i class="fas fa-images mr-2"></i>Recipe Gallery
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php if (is_array($gallery_ids) && !empty($gallery_ids)): ?>
                                    <?php foreach ($gallery_ids as $gallery_id): ?>
                                    <?php $gallery_url = wp_get_attachment_url($gallery_id); ?>
                                    <?php if ($gallery_url): ?>
                                        <div class="aspect-square rounded-lg overflow-hidden">
                                            <img src="<?php echo esc_url($gallery_url); ?>"
                                                 alt="Recipe image"
                                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-200 cursor-pointer">
                                        </div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-span-full text-center py-8">
                                        <i class="fas fa-images text-4xl text-gray-300 mb-4"></i>
                                        <p class="text-gray-500">No gallery images available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Sidebar -->
            <div class="space-y-6">

                <!-- Quick Recipe Browser -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="fas fa-book-open mr-2"></i>Recipe Browser
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            <?php foreach ($all_recipes as $rec): ?>
                                <a href="<?php echo admin_url('admin.php?page=nds-recipe-details&id=' . $rec['id']); ?>"
                                   class="block p-3 rounded-lg hover:bg-gray-50 transition-colors <?php echo ($rec['id'] == $recipe_id) ? 'bg-blue-50 border-l-4 border-blue-600' : ''; ?>">
                                    <div class="flex items-center space-x-3">
                                        <?php if ($rec['image']): ?>
                                            <img src="<?php echo esc_url(wp_get_attachment_url($rec['image'])); ?>"
                                                 alt="<?php echo esc_attr($rec['recipe_name']); ?>"
                                                 class="w-10 h-10 rounded-lg object-cover">
                                        <?php else: ?>
                                            <div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-utensils text-gray-400 text-sm"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-gray-900"><?php echo esc_html($rec['recipe_name']); ?></h4>
                                            <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($rec['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="fas fa-bolt mr-2"></i>Quick Actions
                        </h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <a href="<?php echo admin_url('admin.php?page=nds-add-recipe'); ?>"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add New Recipe
                        </a>

                        <button type="button" onclick="duplicateRecipe()"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-copy mr-2"></i>Duplicate Recipe
                        </button>

                        <button type="button" onclick="exportRecipe()"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export Recipe
                        </button>

                        <button type="button" onclick="shareRecipe()"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-share mr-2"></i>Share Recipe
                        </button>
                    </div>
                </div>

                <!-- Recipe Statistics -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-teal-600 to-teal-700 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i>Recipe Stats
                        </h3>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Total Recipes</span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo $total_recipes; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Ingredients</span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo count($the_recipe['ingredients'] ?? []); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Steps</span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo count($the_recipe['steps'] ?? []); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Gallery Images</span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo count($gallery_ids); ?></span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- Edit Modal (Hidden by default) -->
        <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">Edit Recipe</h2>
                            <button type="button" onclick="toggleEditMode()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        <?php nds_recipes_form('update', $recipe_id); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden Delete Form -->
        <form id="deleteRecipeForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>" style="display: none;">
            <input type="hidden" name="action" value="recipes_delete_recipe">
            <input type="hidden" name="recipe_id" value="<?php echo intval($recipe_id); ?>">
            <?php wp_nonce_field('nds_delete_recipe_nonce_action', 'nds_delete_recipe_nonce'); ?>
        </form>

    </div>

    <script>
    function toggleEditMode() {
        const modal = document.getElementById('editModal');
        modal.classList.toggle('hidden');
        if (!modal.classList.contains('hidden')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    function confirmDelete() {
        if (confirm('Are you sure you want to delete this recipe? This action cannot be undone.')) {
            document.getElementById('deleteRecipeForm').submit();
        }
    }

    function duplicateRecipe() {
        if (confirm('Create a duplicate of this recipe?')) {
            // Add duplicate functionality here
            alert('Duplicate functionality will be implemented soon.');
        }
    }

    function exportRecipe() {
        // Add export functionality here
        alert('Export functionality will be implemented soon.');
    }

    function shareRecipe() {
        // Add share functionality here
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            alert('Recipe link copied to clipboard!');
        });
    }

    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            toggleEditMode();
        }
    });

    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('editModal');
            if (!modal.classList.contains('hidden')) {
                toggleEditMode();
            }
        }
    });
    </script>
    <?php
}
?>
