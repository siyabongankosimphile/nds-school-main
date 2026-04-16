<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$recipes_table = $wpdb->prefix . "nds_recipes"; // Table name
$old_steps = '';

// ✅ Handle the repetitive logic for fetching recipe data
function recipes_handle_recipe_data($request_type = 'POST')
{
    $request_data = ($request_type === 'POST') ? $_POST : $_GET;


    return [
        'recipe_name'    => isset($request_data['recipe_name']) ? sanitize_text_field($request_data['recipe_name']) : '',
        'image'          => isset($request_data['recipe_image']) ? sanitize_text_field($request_data['recipe_image']) : null,  // Handle image file
        'gallery'        => isset($request_data['gallery_image']) ? sanitize_text_field($request_data['gallery_image']) : '',
        'the_recipe'     => isset($request_data['the_recipe']) ? sanitize_text_field($request_data['the_recipe']) : '',
        'cooking'          => isset($request_data['cooking']) ? sanitize_text_field($request_data['cooking']) : '',
        'prep'             => isset($request_data['prep']) ? sanitize_text_field($request_data['prep']) : '',
        'servings'         => isset($request_data['servings']) ? sanitize_text_field($request_data['servings']) : '',
        'mini_description' => isset($request_data['mini_description']) ? sanitize_text_field($request_data['mini_description']) : '',
        'steps' => isset($request_data['steps']) ? $request_data['steps'] : '',
        'ingredients' => isset($request_data['ingredients']) ? $request_data['ingredients'] : '',
        'created_at'     => current_time('mysql'),  // Automatically sets the current timestamp
    ];
}

// ✅ Function to add a new recipe
function recipes_add_recipe()
{

    global $wpdb;
    error_log("recipes_add_recipe function is executing...");

    // ✅ Verify Nonce for security
    if (!isset($_POST['nds_add_recipe_nonce']) || !wp_verify_nonce($_POST['nds_add_recipe_nonce'], 'nds_add_recipe_nonce_action')) {
        error_log("Nonce verification failed");
        wp_die('Security check failed.');
    }

    // ✅ Check User Permissions
    if (!current_user_can('manage_options')) {
        error_log("User does not have the required permissions");
        wp_die('Permission Denied');
    }

    // ✅ Get and sanitize input data
    $recipe_data = recipes_handle_recipe_data('POST');

    // ✅ Check if required fields are filled
    if (empty($recipe_data['recipe_name'])) {
        error_log("Recipe name is missing");
        wp_redirect(admin_url('admin.php?page=nds-add-recipe&error=missing_recipe_name'));
        exit;
    }

    // ✅ Build `the_recipe` inside this function
    $the_recipe = [
        'cooking'          => $recipe_data['cooking'],
        'prep'             => $recipe_data['prep'],
        'servings'         => $recipe_data['servings'],
        'mini_description' => $recipe_data['mini_description'],
        'gallery_image'    => $recipe_data['gallery'],
        'steps'            => $recipe_data['steps'],
        'ingredients'      => $recipe_data['ingredients'],
    ];
    $the_recipe = json_encode($the_recipe);
    $gallery = $recipe_data['gallery'];
    $gallery = json_encode($gallery);

    $inserted = $wpdb->insert(
        "{$wpdb->prefix}nds_recipes",
        [
            'recipe_name' => $recipe_data['recipe_name'],
            'image' => $recipe_data['image'], // Store image as ID
            'gallery' => $gallery,
            'the_recipe' => $the_recipe,
            'created_at' => current_time('mysql')
        ]
    );

    if ($inserted) {
        $last_inserted_id = $wpdb->insert_id;

        // Create a WordPress Post for this recipe
        $post_data = [
            'post_title'   => sanitize_text_field($recipe_data['recipe_name']),
            'post_content' => $recipe_data['the_recipe'], // Or customize this to your liking
            'post_status'  => 'publish',
            'post_type'    => 'post',  // You can change this to a custom post type if needed
            'meta_input'   => [
                '_wp_page_template' => 'single-recipe.php', // Set the template
            ]
        ];

        // Insert the post
        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            // Link the post to your recipe record in the database (optional)
            $wpdb->update(
                "{$wpdb->prefix}nds_recipes",
                ['post_id' => $post_id],
                ['id' => $last_inserted_id]
            );

            wp_redirect(admin_url("admin.php?page=nds-recipe-details&id=" . $last_inserted_id . "&success=created"));
            exit;
        }
    }

    // If there was an error inserting the recipe
    wp_redirect(admin_url('admin.php?page=nds-add-recipe&error=insert_failed'));
    exit;
}

// 🔥 Hook into WordPress Admin-Post System
add_action('admin_post_recipes_add_recipe', 'recipes_add_recipe');

function merge_recipe($recipe_data)
{
    $the_recipe = array(
        'cooking' => $recipe_data['cooking'],
        'prep' => $recipe_data['prep'],
        'servings' => $recipe_data['servings'],
        'mini_description' => $recipe_data['mini_description'],
        'steps' => $recipe_data['steps'],
        'ingredients' => $recipe_data['ingredients']
    );

    return $the_recipe;
}

// ✅ Function to update an existing recipe
add_action('admin_post_recipes_update_recipe', 'recipes_update_recipe');

function recipes_update_recipe($recipe_id)
{

    if (!isset($_POST['nds_update_recipe_nonce']) || !wp_verify_nonce($_POST['nds_update_recipe_nonce'], 'nds_update_recipe_nonce_action')) {
        die('Permission denied');
    }

    global $wpdb;
    $table = $wpdb->prefix . "nds_recipes";

    // Prepare data for updating
    $prepared_data = recipes_handle_recipe_data('POST');

    $the_recipe = merge_recipe($prepared_data);
    $prepared_data['the_recipe'] = $the_recipe;

    $inserting = array(
        'recipe_name' => $prepared_data['recipe_name'],
        'image' => $prepared_data['image'],
        'gallery' => $prepared_data['gallery'],
        'the_recipe' => json_encode($prepared_data['the_recipe'], true),
    );

    if (empty($recipe_id)) {
        $recipe_id = $_POST['recipe_id'];
    }
    // Update the recipe with the given ID
    $wpdb->update($table, $inserting, ['id' => $recipe_id]);
    //exit(var_dump($wpdb->last_query));
    $rayray = array('id' => $recipe_id);
    wp_redirect(admin_url("admin.php?page=nds-recipe-details&id=" . $recipe_id . "&success=updated"));
}

// ✅ Function to delete a recipe
function recipes_delete_recipe($recipe_id)
{
    global $wpdb;
    $table = $wpdb->prefix . "nds_recipes";

    $recipe_id = intval($recipe_id);
    if ($recipe_id <= 0) {
        return 0;
    }

    $wpdb->delete($table, ['id' => $recipe_id], ['%d']);
    return $wpdb->rows_affected;
}

// ✅ Function to fetch all recipes
function recipes_get_all_recipes()
{
    global $wpdb;
    $table = $wpdb->prefix . "nds_recipes";

    return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
}

// Admin-post action to handle deletion securely
add_action('admin_post_recipes_delete_recipe', function() {
    $redirect_url = admin_url('admin.php?page=nds-recipes');

    if (!isset($_POST['nds_delete_recipe_nonce']) || !wp_verify_nonce($_POST['nds_delete_recipe_nonce'], 'nds_delete_recipe_nonce_action')) {
        wp_redirect(add_query_arg('error', 'security_check_failed', $redirect_url));
        exit;
    }
    if (!current_user_can('manage_options')) {
        wp_redirect(add_query_arg('error', 'unauthorized', $redirect_url));
        exit;
    }

    $recipe_id = isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0;
    if ($recipe_id > 0) {
        $deleted_rows = recipes_delete_recipe($recipe_id);
        if ($deleted_rows > 0) {
            wp_redirect(add_query_arg('success', 'deleted', $redirect_url));
            exit;
        }

        wp_redirect(add_query_arg('error', 'delete_failed', $redirect_url));
        exit;
    }

    wp_redirect(add_query_arg('error', 'invalid_id', $redirect_url));
    exit;
});

// ✅ Function to fetch a single recipe by ID
function recipes_get_recipe_by_id($recipe_id)
{
    global $wpdb;
    $table = $wpdb->prefix . "nds_recipes";

    // Fetch the recipe by ID
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $recipe_id), ARRAY_A);
}



// ✅ Function to fetch form

function nds_recipes_form($action, $recipe_id = null)
{
    global $wpdb;
    $the_recipe = [];
    $image = '';
    $gallery_ids = [];
    $gallery_urls = [];
    $gallery_urls_json = '';
    $image_url = '';
    $recipe_name = '';


    // Fetch the recipe data if updating
    if ($action == 'update' && $recipe_id) {
        $the_recipe = recipes_get_recipe_by_id($recipe_id);
        if ($the_recipe) {
            $image = $the_recipe['image'];
            $gallery_ids = explode(',', $the_recipe['gallery']);
            $gallery_urls_json = json_encode($gallery_ids);
            $gallery_urls = array_map(function ($id) {
                return wp_get_attachment_url($id);
            }, $gallery_ids);
            $recipe_name = $the_recipe['recipe_name'];
            $the_recipe = json_decode($the_recipe['the_recipe'], true);
            $steps = $the_recipe['steps'] ?? [];
            $ingredients = $the_recipe['ingredients'] ?? [];
            $image_url = wp_get_attachment_url($image);
        }
    }

?>

    <!-- Form UI -->
    <div class="mx-auto p-6 bg-white rounded-lg shadow-lg m-6 relative pb-24">
        <h2 class="text-2xl font-semibold text-center mb-6">
            <?php echo $action == 'update' ? 'Update Recipe' : 'Add New Recipe'; ?></h2>
        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
            <input type="hidden" name="recipe_id" value="<?php echo $recipe_id; ?>">
            <div class="grid grid-cols-7 gap-4">
                <!-- Recipe Name -->
                <div class="col-span-3 space-y-2">
                    <div>
                        <label for="recipe_name" class="block text-sm font-medium text-gray-700">Recipe Name</label>
                        <input type="text" id="recipe_name" name="recipe_name"
                            value="<?php echo (isset($recipe_name)) ? esc_attr($recipe_name) : ''; ?>"
                            class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg" required>

                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <!-- Cooking Time -->
                        <div class="">
                            <label for="cooking" class="block text-sm font-medium text-gray-700 items-center">
                                <span class="text-xs">Cooking <i class="fa fa-clock"></i></span>
                            </label>
                            <input type="number" id="cooking" name="cooking"
                                value="<?php echo esc_attr($the_recipe['cooking'] ?? '20'); ?>"
                                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <!-- Prep Time -->
                        <div class="">
                            <label for="prep_time" class="block text-sm font-medium text-gray-700 items-center">
                                <span class="text-xs">Prep <i class="fa fa-clock"></i></span>
                            </label>
                            <input type="number" id="prep" name="prep"
                                value="<?php echo esc_attr($the_recipe['prep'] ?? '12'); ?>"
                                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <!-- Servings ssssss -->
                        <div class="">
                            <label for="servings" class="block text-sm font-medium text-gray-700 items-center">
                                <span class="text-xs">Servings <i class="fas fa-users mr-2"></i></span>
                            </label>
                            <input type="number" id="servings" name="servings"
                                value="<?php echo esc_attr($the_recipe['servings'] ?? '4'); ?>"
                                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                </div>
                <!-- Description -->
                <div class="col-span-4">
                    <label for="mini_description" class="block text-sm font-medium text-gray-700">Recipe Description (Max: 40 words)</label>
                    <textarea id="mini_description" name="mini_description" rows="4" maxlength="200"
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo esc_textarea($the_recipe['mini_description'] ?? ''); ?></textarea>
                    <div class="text-right text-sm text-gray-500 mt-1">
                        <span id="char-count">0</span>/200 characters
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-6">
                <!-- Recipe Image (ID from WordPress Media Uploader) -->
                <div class="recipePic border-2 rounded p-6">
                    <div class="gallery-item inline-block relative w-full h-40 m-1">
                        <img id="recipe_preview" class="w-full h-full object-cover rounded mx-auto"
                            src="<?php echo (isset($image_url) && !empty($image_url)) ? esc_url($image_url) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik04MCAxMDBDODAgODkuNTQ0NyA4OC41NDQ3IDgxIDEwMCA4MUMxMTEuNDU1IDgxIDEyMCA4OS41NDQ3IDEyMCAxMDBDMTIwIDExMC40NTUgMTExLjQ1NSA5OSAxMDAgMTE5Qzg4LjU0NDcgMTE5IDgwIDExMC40NTUgODAgMTAwWiIgZmlsbD0iIzlDQTBBNiIvPgo8cGF0aCBkPSJNMTMwIDEyMEMxMzAgMTEwLjQ1NSAxMjEuNDU1IDEwMiAxMTEgMTAyQzEwMC41NDUgMTAyIDkyIDExMC40NTUgOTIgMTIwQzkyIDEyOS41NDUgMTAwLjU0NSAxMzggMTExIDEzOEMxMjEuNDU1IDEzOCAxMzAgMTI5LjU0NSAxMzAgMTIwWiIgZmlsbD0iIzlDQTBBNiIvPgo8L3N2Zz4K'; ?>"
                            data-image-url="<?php echo (isset($image_url) && !empty($image_url)) ? esc_url($image_url) : ''; ?>"
                            style="display: <?php echo (isset($image_url) && !empty($image_url)) ? 'block' : 'block'; ?>;">
                    </div>
                    <input type="hidden" name="recipe_image" class="sad" id="recipe_image"
                        value="<?php echo (isset($image)) ? esc_attr($image) : ''; ?>">
                    <button type="button" class="button upload_picture" id="upload_recipe_picture">Upload</button>
                    <button type="button" class="button button-secondary" id="remove_recipe_picture"
                        style="display: <?php echo (isset($image) && !empty($image)) ? 'inline-block' : 'none'; ?>;">Remove</button>
                </div>

                <!-- Gallery (Multiple Images, serialized) -->
                <div class="col-span-2 border-2 border-dashed border-gray-300 rounded p-6 bg-slate-50 hover:border-blue-400 transition-colors duration-200" id="gallery-dropzone">
                    <label for="gallery" class="block text-sm font-medium text-gray-700 mb-2">Gallery (Select multiple images)</label>
                    <p class="text-xs text-gray-500 mb-4">Drag & drop images here or click "Select Images"</p>
                    <input type="hidden" id="gallery_prev_images" name="gallery_prev_images"
                        value="<?php echo (isset($gallery_ids)) ? esc_attr(implode(',', $gallery_ids)) : ''; ?>"
                        data-image-urls='<?php echo (isset($gallery_urls_json)) ? esc_attr($gallery_urls) : ''; ?>'>
                    <input type="hidden" id="gallery_image" name="gallery_image"
                        value="<?php echo (isset($gallery_ids)) ? esc_attr(implode(',', $gallery_ids)) : ''; ?>"
                        data-image-urls='<?php echo (isset($gallery_urls_json)) ? esc_attr($gallery_urls) : ''; ?>'>
                    <div class="grid grid-cols-4 gap-4" id="gallery_preview">
                        <?php
                        if (isset($gallery_urls) && !empty($gallery_urls)):
                            foreach ($gallery_urls as $url): ?>
                                <div class="gallery-item inline-block relative w-full h-32 m-1" data-id="${id}">
                                    <img src="<?php echo esc_url($url); ?>" class="w-full h-full object-cover rounded">
                                    <button class="button remove-image absolute top-1 right-1 bg-red-500 text-white text-xs px-1 rounded">✕</button>
                                </div>
                        <?php endforeach;
                        else: ?>
                            <div class="gallery-item relative w-full h-32 m-1 border-2 border-dashed border-gray-300 rounded flex items-center justify-center bg-white">
                                <div class="text-center text-gray-400">
                                    <svg class="w-8 h-8 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-xs">No images</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="select_gallery_images"
                        class="mt-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Select Images</button>
                </div>
            </div>


            <div class="grid grid-cols-2 gap-4 mt-6">
                <!-- Recipe Ingredients -->
                <div class="border-2 rounded p-6 bg-slate-50">
                    <label for="ingredients" class="block text-sm font-medium text-gray-700">Ingredients Ingredients</label>
                    <div id="ingredients_container">
                        <?php
                        // Check if the array is not empty
                        if (!empty($ingredients)) {

                            // Loop through each ingredient and create an input for it
                            foreach ($ingredients as $index => $ingredient) {
                                echo '<div class="ingredient_input flex items-center space-x-2">';
                                echo '<input type="text" name="ingredients[]" id="ingredient' . ($index + 1) . '" value="' . htmlspecialchars($ingredient) . '" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg" />';
                                echo '<button type="button" class="remove-ingredient px-2 py-1 bg-red-500 text-white rounded-lg" data-ingredient="' . ($index + 1) . '">✕</button>';
                                echo '</div>';
                            }
                        } else {
                            echo 'No ingredients found.';
                        }

                        ?>
                    </div>
                    <button type="button" id="add_ingredient" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Add New ingredient</button>
                    <script>
                        jQuery(document).ready(function() {
                            var ingredientsContainer = jQuery('#ingredients_container');
                            var ingredientsInput = jQuery('#ingredients');
                            var addIngredientButton = jQuery('#add_ingredient');

                            // If no ingredients exist, create the first one on load
                            /* if (ingredientsContainer.children().length === 0) {
                                addIngredientField('', 1);
                            } */

                            // Add new ingredient button click
                            addIngredientButton.click(function() {
                                addIngredientField('', ingredientsContainer.children().length + 1);
                            });

                            // Function to add input field
                            function addIngredientField(value, ingredientNumber) {
                                var inputField =
                                    `
                        <div class="flex items-center space-x-2">
                        <input type="text" name="ingredients[]" id="ingredient${ingredientNumber}" value="${value}" class="block w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <button type="button" class="remove-ingredient px-2 py-1 bg-red-500 text-white rounded-lg" data-ingredient="${ingredientNumber}">✕</button></div>`;

                                // Ensure container exists and add input field
                                if (ingredientsContainer.length === 0) {
                                    jQuery('<div id="ingredients_container" class="space-y-2"></div>').insertBefore(
                                        addIngredientButton);
                                    ingredientsContainer = jQuery('#ingredients_container'); // Re-select after insertion
                                }

                                var newInput = jQuery(inputField).appendTo(ingredientsContainer).find('input');
                                newInput.focus(); // Set cursor to new input
                                updateSerializedIngredients();
                            }

                            // Remove ingredient field
                            ingredientsContainer.on('click', '.remove-ingredient', function() {
                                jQuery(this).parent().remove();
                                updateSerializedIngredients();
                            });

                            // Update hidden textarea when ingredients change
                            function updateSerializedIngredients() {
                                var ingredientsArray = [];
                                jQuery('input[name="ingredients[]"]').each(function() {
                                    ingredientsArray.push(jQuery(this).val()); // Collect all ingredient values
                                });

                                //var serializedIngredients = JSON.stringify(ingredientsArray); // Serialize the array
                                //jQuery('#ingredients').val(serializedIngredients); // Store in hidden input or textarea
                            }

                            // Track changes in input fields
                            ingredientsContainer.on('input', 'input[name="ingredients[]"]', updateSerializedIngredients);
                        });
                    </script>
                </div>
                <!-- Recipe Steps -->
                <div class="border-2 rounded p-6 bg-slate-50">
                    <label for="steps" class="block text-sm font-medium text-gray-700">Recipe Steps</label>
                    <div id="steps_container">
                        <?php
                        // Check if the array is not empty
                        if (!empty($steps)) {

                            // Loop through each step and create an input for it
                            foreach ($steps as $index => $step) {
                                echo '<div class="step_input flex items-center space-x-2">';
                                echo '<input type="text" name="steps[]" id="step' . ($index + 1) . '" value="' . htmlspecialchars($step) . '" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg" />';
                                echo '<button type="button" class="remove-step px-2 py-1 bg-red-500 text-white rounded-lg" data-step="' . ($index + 1) . '">✕</button>';
                                echo '</div>';
                            }
                        } else {
                            echo 'No steps found.';
                        }

                        ?>
                    </div>
                    <button type="button" id="add_step" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Add New Step</button>
                    <script>
                        jQuery(document).ready(function() {
                            var stepsContainer = jQuery('#steps_container');
                            var stepsInput = jQuery('#steps');
                            var addStepButton = jQuery('#add_step');

                            // If no steps exist, create the first one on load
                            /* if (stepsContainer.children().length === 0) {
                                addStepField('', 1);
                            } */

                            // Add new step button click
                            addStepButton.click(function() {
                                addStepField('', stepsContainer.children().length + 1);
                            });

                            // Function to add input field
                            function addStepField(value, stepNumber) {
                                var inputField =
                                    `
                        <div class="flex items-center space-x-2">
                        <input type="text" name="steps[]" id="step${stepNumber}" value="${value}" class="block w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <button type="button" class="remove-step px-2 py-1 bg-red-500 text-white rounded-lg" data-step="${stepNumber}">✕</button></div>`;

                                // Ensure container exists and add input field
                                if (stepsContainer.length === 0) {
                                    jQuery('<div id="steps_container" class="space-y-2"></div>').insertBefore(
                                        addStepButton);
                                    stepsContainer = jQuery('#steps_container'); // Re-select after insertion
                                }

                                var newInput = jQuery(inputField).appendTo(stepsContainer).find('input');
                                newInput.focus(); // Set cursor to new input
                                updateSerializedSteps();
                            }

                            // Remove step field
                            stepsContainer.on('click', '.remove-step', function() {
                                jQuery(this).parent().remove();
                                updateSerializedSteps();
                            });

                            // Update hidden textarea when steps change
                            function updateSerializedSteps() {
                                var stepsArray = [];
                                jQuery('input[name="steps[]"]').each(function() {
                                    stepsArray.push(jQuery(this).val()); // Collect all step values
                                });

                                //var serializedSteps = JSON.stringify(stepsArray); // Serialize the array
                                //jQuery('#steps').val(serializedSteps); // Store in hidden input or textarea
                            }

                            // Track changes in input fields
                            stepsContainer.on('input', 'input[name="steps[]"]', updateSerializedSteps);
                        });
                    </script>
                </div>
            </div>


            <!-- Sticky Action Bar -->
            <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-50 px-6 py-4">
                <div class="max-w-7xl mx-auto flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        <?php echo $action == 'update' ? 'Editing Recipe' : 'Creating New Recipe'; ?>
                    </div>
                    <div class="flex gap-4">
                        <input type="hidden" name="action" value="recipes_<?php echo $action; ?>_recipe" />
                        <?php wp_nonce_field('nds_' . $action . '_recipe_nonce_action', 'nds_' . $action . '_recipe_nonce'); ?>
                        <button type="submit" class="px-8 py-3 text-white bg-blue-500 rounded-lg hover:bg-blue-600 transition duration-200 font-medium shadow-md">
                            <i class="fas fa-save mr-2"></i><?php echo $action == 'update' ? 'Update Recipe' : 'Add Recipe'; ?>
                        </button>
                        <?php if ($action == 'update' && $recipe_id): ?>
                            <button type="button" onclick="confirmDelete()" class="px-8 py-3 text-white bg-red-600 rounded-lg hover:bg-red-700 transition duration-200 font-medium shadow-md">
                                <i class="fas fa-trash mr-2"></i>Delete Recipe
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Hidden Delete Form -->
        <?php if ($action == 'update' && $recipe_id): ?>
            <form id="deleteRecipeForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>" style="display: none;">
                <input type="hidden" name="action" value="recipes_delete_recipe">
                <input type="hidden" name="recipe_id" value="<?php echo intval($recipe_id); ?>">
                <?php wp_nonce_field('nds_delete_recipe_nonce_action', 'nds_delete_recipe_nonce'); ?>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Character counter for description
        jQuery(document).ready(function() {
            const textarea = jQuery('#mini_description');
            const charCount = jQuery('#char-count');
            
            function updateCharCount() {
                const length = textarea.val().length;
                charCount.text(length);
                if (length > 180) {
                    charCount.addClass('text-red-500');
                } else {
                    charCount.removeClass('text-red-500');
                }
            }
            
            textarea.on('input', updateCharCount);
            updateCharCount(); // Initial count
            
            // Drag and drop for gallery
            const dropzone = document.getElementById('gallery-dropzone');
            if (dropzone) {
                dropzone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('border-blue-500', 'bg-blue-50');
                });
                
                dropzone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-blue-500', 'bg-blue-50');
                });
                
                dropzone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-blue-500', 'bg-blue-50');
                    // Trigger the gallery selection
                    jQuery('#select_gallery_images').click();
                });
            }
        });
        
        // Delete confirmation function
        function confirmDelete() {
            if (confirm('Are you sure you want to delete this recipe? This action cannot be undone.')) {
                document.getElementById('deleteRecipeForm').submit();
            }
        }
        
        var mediaUploader;
        var mediaUploader2;
        // Function to update the preview for a single image
        function updateImagePreview(targetDiv, url) {
            jQuery('#' + targetDiv).attr('src', url).show();
        }
        // Recipe Image Upload
        jQuery('#upload_recipe_picture').click(function(e) {
            e.preventDefault();

            // Initialize the WordPress media uploader
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();

                // Store attachment ID in hidden input (if needed)
                jQuery('#recipe_image').val(attachment.id);

                // Update image preview using the direct image URL
                jQuery('#recipe_preview').attr('src', attachment.url);
            });

            mediaUploader.open();
        });

        // Show existing image preview if the image URL is already set
        var existingRecipeImageUrl = jQuery('#recipe_image').data('image-url');
        if (existingRecipeImageUrl) {
            jQuery('#recipe_preview').attr('src', existingRecipeImageUrl);
        }
        // Gallery Image Upload
        jQuery('#select_gallery_images').click(function(e) {
            e.preventDefault();

            var existingGalleryIds = jQuery('#gallery_image').val().split(',').map(id => parseInt(id)).filter(
                Boolean);
            var prevGalleryIds = jQuery('#gallery_prev_images').val().split(',').map(id => parseInt(id)).filter(
                Boolean);

            if (mediaUploader2) {
                mediaUploader2.open();
                return;
            }

            mediaUploader2 = wp.media({
                title: 'Select Gallery Images',
                button: {
                    text: 'Use these images'
                },
                multiple: true
            });

            mediaUploader2.on('open', function() {
                var selection = mediaUploader2.state().get('selection');

                existingGalleryIds.forEach(function(id) {
                    var attachment = wp.media.attachment(id);
                    attachment.fetch();
                    selection.add(attachment);
                });
            });

            mediaUploader2.on('select', function() {
                var selectedImages = mediaUploader2.state().get('selection').map(attachment => attachment
                    .toJSON());

                var newImageIds = selectedImages.map(img => img.id);
                var newImageUrls = selectedImages.map(img => img.url);

                // Merge new selections and remove invalid IDs (not in WP)
                var validImageIds = Array.from(new Set([...newImageIds]));
                var validImageUrls = Array.from(new Set([...newImageUrls]));

                // Compare with prev DB gallery to detect removed images
                var removedIds = prevGalleryIds.filter(id => !validImageIds.includes(id));

                // Store updated IDs and URLs
                jQuery('#gallery_image').val(validImageIds.join(',')).attr('data-image-urls', JSON
                    .stringify(validImageUrls));

                // Remove missing images from preview
                updateGalleryPreview(validImageIds, validImageUrls);

                console.log("Updated gallery_image:", validImageIds);
                console.log("Removed Images:", removedIds);
            });

            mediaUploader2.open();
        });

        function updateGalleryPreview(imageIds, imageUrls) {
            var galleryContainer = jQuery('#gallery_preview');
            galleryContainer.empty();

            if (imageIds.length === 0 || imageUrls.length === 0) {
                // Show placeholder when no images
                var placeholderHtml = `
                <div class="gallery-item inline-block relative w-full h-32 m-1 border-2 border-dashed border-gray-300 rounded flex items-center justify-center bg-white">
                    <div class="text-center text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-xs">No images</span>
                    </div>
                </div>
            `;
                galleryContainer.append(placeholderHtml);
                return;
            }

            imageIds.forEach(function(id, index) {
                var url = imageUrls[index];
                if (!url || url === 'false') return;

                var imageHtml = `
                <div class="gallery-item inline-block relative w-full h-32 m-1" data-id="${id}">
                    <img src="${url}" class="w-full h-full object-cover rounded">
                    <button class="button remove-image absolute top-1 right-1 bg-red-500 text-white text-xs px-1 rounded">✕</button>
                </div>
            `;
                galleryContainer.append(imageHtml);
            });
        }

        jQuery('#gallery_preview').on('click', '.remove-image', function() {
            var parent = jQuery(this).closest('.gallery-item');
            var imageId = parent.data('id');

            parent.remove();

            var updatedIds = jQuery('#gallery_image').val().split(',').map(id => parseInt(id)).filter(id => id !==
                imageId);
            jQuery('#gallery_image').val(updatedIds.join(','));

            console.log("Updated gallery_image:", updatedIds);
        });

        var existingGalleryIds = jQuery('#gallery_image').val().split(',').map(id => parseInt(id)).filter(Boolean);
        var existingGalleryUrls = JSON.parse(jQuery('#gallery_image').attr('data-image-urls') || "[]");
        updateGalleryPreview(existingGalleryIds, existingGalleryUrls);
    </script>

<?php
}
