<?php
/**
 * Plugin Name: WPML WXR Exporter
 * Description: Exports WPML posts per language for MultilingualPress migration.
 * Version: 0.4
 * Author: Femi
 * License: GPL3
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1️⃣ Create Export Page in Admin
function wpml_add_export_page() {
    add_management_page(
        'WPML Export', 
        'WPML Export', 
        'manage_options', 
        'wpml-export', 
        'wpml_render_export_page'
    );
}
add_action('admin_menu', 'wpml_add_export_page');

// 2️⃣ Get Current WPML Language for This Subsite
function wpml_get_current_admin_language() {
    if (function_exists('icl_get_languages')) {
        return apply_filters('wpml_current_language', NULL); // Official WPML method
    }
    return ''; // Return empty if WPML is not active
}

// 3️⃣ Render Export Page
function wpml_render_export_page() {
    $current_language = wpml_get_current_admin_language();
    $woocommerce_active = class_exists('WooCommerce');

    ?>
    <div class="wrap">
        <h1>WPML WXR Export</h1>
        <p><strong>Current Language:</strong> <?php echo strtoupper($current_language); ?></p>
        <form method="POST">
            <?php wp_nonce_field('wpml_export_nonce', 'wpml_export_nonce'); ?>
            <input type="hidden" name="export_language" value="<?php echo esc_attr($current_language); ?>">
            
            <label><strong>Select Post Types:</strong></label><br>
            <input type="checkbox" name="export_types[]" value="post" checked> Posts <br>
            <input type="checkbox" name="export_types[]" value="page" checked> Pages <br>
            <?php if ($woocommerce_active): ?>
                <input type="checkbox" name="export_types[]" value="product"> Products <br>
            <?php endif; ?>

            <br>
            <input type="submit" name="wpml_export_submit" class="button button-primary" value="Download WXR Export">
        </form>
    </div>
    <?php
}

// 4️⃣ Handle Export as WXR
function wpml_handle_export() {
    if (isset($_POST['wpml_export_submit'])) {
        if (!isset($_POST['wpml_export_nonce']) || !wp_verify_nonce($_POST['wpml_export_nonce'], 'wpml_export_nonce')) {
            die("Security check failed");
        }

        $selected_language = wpml_get_current_admin_language();
        $selected_types = isset($_POST['export_types']) ? array_map('sanitize_text_field', $_POST['export_types']) : [];

        if (empty($selected_language) || empty($selected_types)) {
            wp_die("Error: No language or post types selected.");
        }

        global $wpdb;

        // Get only the posts in the correct WPML language
        $post_ids = $wpdb->get_col($wpdb->prepare("
            SELECT element_id FROM {$wpdb->prefix}icl_translations
            WHERE language_code = %s
        ", $selected_language));

        if (empty($post_ids)) {
            wp_die("No posts found for the selected language ($selected_language).");
        }

        $posts = get_posts([
            'post__in'   => $post_ids,
            'post_type'  => $selected_types,
            'post_status'=> 'publish',
            'numberposts'=> -1
        ]);

        if (empty($posts)) {
            wp_die("No posts found for the selected language ($selected_language) and post types.");
        }

        // Generate WXR Output
        header('Content-Type: application/rss+xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="wpml-export-' . $selected_language . '.wxr"');

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/">';
        echo '<channel>';
        echo '<wp:wxr_version>1.2</wp:wxr_version>';

        foreach ($posts as $post) {
            echo '<item>';
            echo '<title>' . esc_xml($post->post_title) . '</title>';
            echo '<wp:post_id>' . esc_xml($post->ID) . '</wp:post_id>';
            echo '<wp:post_date>' . esc_xml($post->post_date) . '</wp:post_date>';
            echo '<wp:post_type>' . esc_xml($post->post_type) . '</wp:post_type>';
            echo '<content:encoded><![CDATA[' . $post->post_content . ']]></content:encoded>';
            echo '<category domain="language"><![CDATA[' . esc_xml($selected_language) . ']]></category>';
            echo '</item>';
        }

        echo '</channel>';
        echo '</rss>';
        exit;
    }
}
add_action('admin_init', 'wpml_handle_export');
