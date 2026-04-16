<?php
// Prevent direct access - this file should only be included by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    return;
}

// Modern Add Recipe Page with Tailwind CSS and Breadcrumbs
function nds_add_recipe_page_tailwind() {
    if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
    global $wpdb;
    $recipes_table = $wpdb->prefix . 'nds_recipes';

    // Handle form submission
    if (isset($_POST['action']) && $_POST['action'] === 'recipes_add_recipe') {
        // The form will be processed by the existing recipes_add_recipe function
        // via the admin_post hook, so we don't need to handle it here
    }

    ?>
    <div class="nds-tailwind-wrapper bg-gray-50 pb-12" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin-left: -20px; padding-left: 20px; margin-top: -20px;">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-600 to-pink-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-plus-circle text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Add New Recipe</h1>
                            <p class="text-sm text-gray-600 mt-1">Create a delicious recipe with ingredients, steps, and beautiful images</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="<?php echo admin_url('admin.php?page=nds-recipes'); ?>"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 shadow-sm hover:shadow-md">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Recipes
                        </a>
                        <div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-lg px-4 py-2 shadow-sm">
                            <div class="text-sm text-blue-800 font-medium">Step 1 of 4</div>
                            <div class="text-xs text-blue-600">Basic Information</div>
                        </div>
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
                <a href="<?php echo admin_url('admin.php?page=nds-content-management'); ?>" class="hover:text-purple-600 transition-colors">
                    Recipe Management
                </a>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <span class="text-gray-900 font-medium">Add New Recipe</span>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">

            <!-- Progress Indicator -->
            <div class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recipe Creation Progress</h3>
                        <span class="text-sm text-gray-500 font-medium"><span id="progress-percentage">25</span>% Complete</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 shadow-inner">
                        <div id="progress-bar" class="bg-gradient-to-r from-purple-600 to-pink-600 h-3 rounded-full transition-all duration-300 shadow-md" style="width: 25%"></div>
                    </div>
                    <div class="flex justify-between mt-3 text-xs text-gray-500">
                        <span id="step-1-indicator" class="text-purple-600 font-medium">Basic Info</span>
                        <span id="step-2-indicator">Ingredients</span>
                        <span id="step-3-indicator">Instructions</span>
                        <span id="step-4-indicator">Gallery</span>
                    </div>
                </div>
            </div>
            <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="space-y-8">

                <!-- Basic Information Section -->
                <div id="step-1" class="step bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4 shadow-md">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>Basic Information
                        </h3>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <!-- Recipe Name -->
                            <div class="lg:col-span-2">
                                <label for="recipe_name" class="block text-sm font-semibold text-gray-900 mb-3">
                                    Recipe Name <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" id="recipe_name" name="recipe_name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                           placeholder="Enter recipe name (e.g., Classic Chocolate Chip Cookies)"
                                           required>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <i class="fas fa-utensils text-gray-400"></i>
                                    </div>
                                </div>
                                <p class="mt-2 text-sm text-gray-500">Choose a descriptive name that clearly identifies your recipe</p>
                            </div>

                            <!-- Recipe Image -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-3">
                                    Recipe Image <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div id="recipe_image_preview" class="w-full h-48 border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center cursor-pointer hover:border-blue-400 transition-colors">
                                        <div class="text-center">
                                            <i class="fas fa-camera text-3xl text-gray-400 mb-2"></i>
                                            <p class="text-sm text-gray-500">Click to upload image</p>
                                            <p class="text-xs text-gray-400 mt-1">JPG, PNG up to 5MB</p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="recipe_image" id="recipe_image" required>
                                    <button type="button" id="upload_recipe_image" class="mt-3 w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-upload mr-2"></i>Choose Image
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mt-8">
                            <label for="mini_description" class="block text-sm font-semibold text-gray-900 mb-3">
                                Recipe Description <span class="text-red-500">*</span>
                            </label>
                            <textarea id="mini_description" name="mini_description" rows="4"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 resize-none"
                                      placeholder="Describe your recipe in 1-2 sentences. What makes it special?"
                                      maxlength="300" required></textarea>
                            <div class="flex justify-between items-center mt-2">
                                <p class="text-sm text-gray-500">Brief description that will appear in recipe listings</p>
                                <span class="text-sm text-gray-500"><span id="description_count">0</span>/300 characters</span>
                            </div>
                        </div>

                        <!-- Cooking Details -->
                        <div class="mt-8">
                            <h4 class="text-sm font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-clock text-gray-600 mr-2"></i>Cooking Details
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="prep" class="block text-sm font-medium text-gray-700 mb-2">
                                        Prep Time (minutes)
                                    </label>
                                    <input type="number" id="prep" name="prep"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                           placeholder="15" min="1">
                                </div>
                                <div>
                                    <label for="cooking" class="block text-sm font-medium text-gray-700 mb-2">
                                        Cook Time (minutes)
                                    </label>
                                    <input type="number" id="cooking" name="cooking"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                           placeholder="25" min="1">
                                </div>
                                <div>
                                    <label for="servings" class="block text-sm font-medium text-gray-700 mb-2">
                                        Servings
                                    </label>
                                    <input type="number" id="servings" name="servings"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                           placeholder="4" min="1">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 1 Navigation -->
                    <div class="px-6 pb-6">
                        <div class="flex justify-end">
                            <button type="button" id="next-to-step-2" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                Next: Ingredients <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Ingredients Section -->
                <div id="step-2" class="step bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hidden">
                    <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 shadow-md">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-list-check mr-2"></i>Ingredients
                        </h3>
                    </div>

                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Recipe Ingredients</h4>
                                <p class="text-sm text-gray-500">List all ingredients needed for this recipe</p>
                            </div>
                            <button type="button" id="add_ingredient" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:from-green-700 hover:to-emerald-700 transition-all duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>Add Ingredient
                            </button>
                        </div>

                        <div id="ingredients_container" class="space-y-3">
                            <!-- Ingredients will be added here -->
                            <div class="ingredient-item flex items-center space-x-3 p-4 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <input type="text" name="ingredients[]"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                           placeholder="e.g., 2 cups all-purpose flour">
                                </div>
                                <button type="button" class="remove-ingredient inline-flex items-center justify-center w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-lightbulb text-blue-600 mt-1 mr-3"></i>
                                <div>
                                    <h5 class="text-sm font-medium text-blue-900">Tips for ingredients:</h5>
                                    <ul class="text-sm text-blue-800 mt-1 space-y-1">
                                        <li>• Include quantities and measurements</li>
                                        <li>• Specify ingredient quality (e.g., "fresh basil", "organic tomatoes")</li>
                                        <li>• Group ingredients by category if needed</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2 Navigation -->
                    <div class="px-6 pb-6">
                        <div class="flex justify-between">
                            <button type="button" id="back-to-step-1" class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>Back: Basic Info
                            </button>
                            <button type="button" id="next-to-step-3" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:from-green-700 hover:to-emerald-700 transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                Next: Instructions <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Instructions Section -->
                <div id="step-3" class="step bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4 shadow-md">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-list-ol mr-2"></i>Cooking Instructions
                        </h3>
                    </div>

                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Step-by-Step Instructions</h4>
                                <p class="text-sm text-gray-500">Provide clear, detailed cooking instructions</p>
                            </div>
                            <button type="button" id="add_step" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>Add Step
                            </button>
                        </div>

                        <div id="steps_container" class="space-y-4">
                            <!-- Steps will be added here -->
                            <div class="step-item">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0 w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center font-semibold text-sm">
                                        1
                                    </div>
                                    <div class="flex-1">
                                        <textarea name="steps[]" rows="3"
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none"
                                                  placeholder="Describe this cooking step in detail..."></textarea>
                                    </div>
                                    <button type="button" class="remove-step inline-flex items-center justify-center w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors mt-1">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-lightbulb text-purple-600 mt-1 mr-3"></i>
                                <div>
                                    <h5 class="text-sm font-medium text-purple-900">Tips for instructions:</h5>
                                    <ul class="text-sm text-purple-800 mt-1 space-y-1">
                                        <li>• Be specific about temperatures and timing</li>
                                        <li>• Include safety precautions when needed</li>
                                        <li>• Mention any special techniques or tools required</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3 Navigation -->
                    <div class="px-6 pb-6">
                        <div class="flex justify-between">
                            <button type="button" id="back-to-step-2" class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>Back: Ingredients
                            </button>
                            <button type="button" id="next-to-step-4" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                Next: Gallery <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Gallery Section -->
                <div id="step-4" class="step bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hidden">
                    <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-6 py-4 shadow-md">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-images mr-2"></i>Recipe Gallery
                        </h3>
                    </div>

                    <div class="p-6">
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-gray-900">Additional Images</h4>
                            <p class="text-sm text-gray-500">Add multiple images to showcase your recipe process</p>
                        </div>

                        <div id="gallery_container" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div class="gallery-upload-placeholder border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-orange-400 transition-colors cursor-pointer" id="gallery_upload_trigger">
                                <div class="text-center">
                                    <i class="fas fa-plus text-2xl text-gray-400 mb-2"></i>
                                    <p class="text-sm text-gray-500">Add Images</p>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="gallery_image" id="gallery_image">
                        <button type="button" id="select_gallery_images" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-orange-600 to-amber-600 text-white rounded-lg hover:from-orange-700 hover:to-amber-700 transition-all duration-200 shadow-md hover:shadow-lg">
                            <i class="fas fa-images mr-2"></i>Select Gallery Images
                        </button>
                    </div>
                    
                    <!-- Step 4 Navigation -->
                    <div class="px-6 pb-6">
                        <div class="flex justify-between">
                            <button type="button" id="back-to-step-3" class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>Back: Instructions
                            </button>
                            <button type="button" id="finish-recipe" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-orange-600 to-amber-600 text-white rounded-lg hover:from-orange-700 hover:to-amber-700 transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                Finish Recipe <i class="fas fa-check ml-2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div id="final-actions" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                            <span class="text-sm text-gray-600">Ready to save your recipe?</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <a href="<?php echo admin_url('admin.php?page=nds-recipes'); ?>"
                               class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium shadow-sm">
                                <i class="fas fa-save mr-2"></i>Create Recipe
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Hidden Fields -->
                <input type="hidden" name="action" value="recipes_add_recipe">
                <?php wp_nonce_field('nds_add_recipe_nonce_action', 'nds_add_recipe_nonce'); ?>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        let ingredientCount = 1;
        let stepCount = 1;
        let currentStep = 1;
        const totalSteps = 4;

        // Character counter for description
        $('#mini_description').on('input', function() {
            const count = $(this).val().length;
            $('#description_count').text(count);
            if (count > 270) {
                $('#description_count').addClass('text-red-500').removeClass('text-gray-500');
            } else {
                $('#description_count').removeClass('text-red-500').addClass('text-gray-500');
            }
        });

        // Add ingredient
        $('#add_ingredient').on('click', function() {
            ingredientCount++;
            const ingredientHtml = `
                <div class="ingredient-item flex items-center space-x-3 p-4 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                        <input type="text" name="ingredients[]"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               placeholder="e.g., 2 cups all-purpose flour">
                    </div>
                    <button type="button" class="remove-ingredient inline-flex items-center justify-center w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors">
                        <i class="fas fa-trash text-sm"></i>
                    </button>
                </div>
            `;
            $('#ingredients_container').append(ingredientHtml);
        });

        // Remove ingredient
        $(document).on('click', '.remove-ingredient', function() {
            $(this).closest('.ingredient-item').remove();
        });

        // Add step
        $('#add_step').on('click', function() {
            stepCount++;
            const stepHtml = `
                <div class="step-item">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center font-semibold text-sm">
                            ${stepCount}
                        </div>
                        <div class="flex-1">
                            <textarea name="steps[]" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none"
                                      placeholder="Describe this cooking step in detail..."></textarea>
                        </div>
                        <button type="button" class="remove-step inline-flex items-center justify-center w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors mt-1">
                            <i class="fas fa-trash text-sm"></i>
                        </button>
                    </div>
                </div>
            `;
            $('#steps_container').append(stepHtml);
        });

        // Remove step
        $(document).on('click', '.remove-step', function() {
            $(this).closest('.step-item').remove();
            // Renumber steps
            $('.step-item').each(function(index) {
                $(this).find('.rounded-full').text(index + 1);
            });
            stepCount = $('.step-item').length;
        });

        // Recipe Image Upload
        var mediaUploader;
        $('#upload_recipe_image, #recipe_image_preview').on('click', function(e) {
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: 'Select Recipe Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#recipe_image').val(attachment.id);
                $('#recipe_image_preview').html(`
                    <img src="${attachment.url}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                        <button type="button" class="text-white opacity-0 hover:opacity-100 transition-opacity">
                            <i class="fas fa-camera text-xl"></i>
                        </button>
                    </div>
                `);
            });

            mediaUploader.open();
        });

        // Gallery Upload
        var galleryUploader;
        $('#select_gallery_images, #gallery_upload_trigger').on('click', function(e) {
            e.preventDefault();

            if (galleryUploader) {
                galleryUploader.open();
                return;
            }

            galleryUploader = wp.media({
                title: 'Select Gallery Images',
                button: {
                    text: 'Use these images'
                },
                multiple: true
            });

            galleryUploader.on('select', function() {
                var attachments = galleryUploader.state().get('selection').map(function(attachment) {
                    return attachment.toJSON();
                });

                var imageIds = attachments.map(img => img.id);
                $('#gallery_image').val(imageIds.join(','));

                // Update gallery preview
                $('#gallery_container').empty();
                $('#gallery_container').append(`
                    <div class="gallery-upload-placeholder border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-orange-400 transition-colors cursor-pointer" id="gallery_upload_trigger">
                        <div class="text-center">
                            <i class="fas fa-plus text-2xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-500">Add Images</p>
                        </div>
                    </div>
                `);

                attachments.forEach(function(attachment) {
                    $('#gallery_container').prepend(`
                        <div class="relative group">
                            <img src="${attachment.url}" class="w-full h-32 object-cover rounded-lg">
                            <button type="button" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    `);
                });
            });

            galleryUploader.open();
        });

        // Remove gallery image
        $(document).on('click', '.gallery-item .remove-image', function() {
            $(this).closest('.gallery-item').remove();
            updateGalleryIds();
        });

        function updateGalleryIds() {
            var ids = [];
            $('.gallery-item').each(function() {
                var id = $(this).data('id');
                if (id) ids.push(id);
            });
            $('#gallery_image').val(ids.join(','));
        }

        // Multi-step navigation functions
        function updateProgress() {
            const percentage = Math.round((currentStep / totalSteps) * 100);
            $('#progress-percentage').text(percentage);
            $('#progress-bar').css('width', percentage + '%');
            
            // Update step indicators
            $('[id^="step-"][id$="-indicator"]').removeClass('text-blue-600 font-medium').addClass('text-gray-500');
            $('#step-' + currentStep + '-indicator').removeClass('text-gray-500').addClass('text-blue-600 font-medium');
        }

        function showStep(stepNumber) {
            $('.step').addClass('hidden');
            $('#step-' + stepNumber).removeClass('hidden');
            currentStep = stepNumber;
            updateProgress();
            
            // Show/hide final actions
            if (stepNumber === totalSteps) {
                $('#final-actions').removeClass('hidden');
            } else {
                $('#final-actions').addClass('hidden');
            }
        }

        function validateStep(stepNumber) {
            let isValid = true;
            
            if (stepNumber === 1) {
                // Validate basic information
                if (!$('#recipe_name').val().trim()) {
                    alert('Please enter a recipe name.');
                    $('#recipe_name').focus();
                    isValid = false;
                } else if (!$('#recipe_image').val()) {
                    alert('Please select a recipe image.');
                    isValid = false;
                } else if (!$('#mini_description').val().trim()) {
                    alert('Please enter a recipe description.');
                    $('#mini_description').focus();
                    isValid = false;
                }
            } else if (stepNumber === 2) {
                // Validate ingredients
                const ingredients = $('input[name="ingredients[]"]').filter(function() {
                    return $(this).val().trim() !== '';
                });
                if (ingredients.length === 0) {
                    alert('Please add at least one ingredient.');
                    isValid = false;
                }
            } else if (stepNumber === 3) {
                // Validate instructions
                const steps = $('textarea[name="steps[]"]').filter(function() {
                    return $(this).val().trim() !== '';
                });
                if (steps.length === 0) {
                    alert('Please add at least one cooking instruction.');
                    isValid = false;
                }
            }
            
            return isValid;
        }

        // Navigation button event handlers
        $('#next-to-step-2').on('click', function() {
            if (validateStep(1)) {
                showStep(2);
            }
        });

        $('#back-to-step-1').on('click', function() {
            showStep(1);
        });

        $('#next-to-step-3').on('click', function() {
            if (validateStep(2)) {
                showStep(3);
            }
        });

        $('#back-to-step-2').on('click', function() {
            showStep(2);
        });

        $('#next-to-step-4').on('click', function() {
            if (validateStep(3)) {
                showStep(4);
            }
        });

        $('#back-to-step-3').on('click', function() {
            showStep(3);
        });

        $('#finish-recipe').on('click', function() {
            if (validateStep(4)) {
                $('#final-actions').removeClass('hidden');
                $('html, body').animate({
                    scrollTop: $('#final-actions').offset().top - 100
                }, 500);
            }
        });

        // Initialize the form
        updateProgress();
    });
    </script>
    <?php
}
?>
