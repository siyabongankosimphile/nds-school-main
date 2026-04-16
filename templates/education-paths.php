<?php
global $wpdb;
$table_name = $wpdb->prefix . 'nds_faculties';
$paths = $wpdb->get_results("SELECT * FROM $table_name");

if (isset($_GET['success'])) {
    echo '<div class="updated"><p>Faculty added successfully!</p></div>';
}
?>

<table class="wp-list-table widefat nds-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($paths as $path) : ?>
            <tr>
                <td><?php echo esc_html($path->id); ?></td>
                <td><?php echo esc_html($path->name); ?></td>
                <td><?php echo esc_html($path->description); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>a