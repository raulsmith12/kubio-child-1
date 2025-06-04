<?php
/* Template Name: QR Code Scan 
 * Custom format removing regular header and footer and displaying just the content
 * Works in conjunction with the custom plugin to create a new page based on information collected at checkout
 */
 
echo '<!DOCTYPE html>';
echo '<html ' . get_language_attributes() . '>';
echo '<head>';
echo '<meta charset="' . get_bloginfo('charset') . '">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
wp_head();
echo '<script src="' . get_stylesheet_directory_uri() . '/js/serial-page.js"></script>';
echo '</head>';
echo '<body>';

echo '<header id="branding-header" style="padding: 10px; text-align: center;">';
echo '<a href="' . esc_url(home_url('/')) . '">';
echo '<img src="https://lockstonellc.com/wp-content/uploads/2025/04/Icon-Image-1.png" alt="RSQ Tag" style="max-height:50px;">';
echo '</a>';
echo '</header>';

$post_id = get_the_ID();
$registered_user_id = get_post_meta($post_id, '_registered_user_id', true);
$current_user_id = get_current_user_id();
$is_owner = $registered_user_id == $current_user_id;

// Handle form submission
if ($is_owner && isset($_POST['gds_update_serial_page'])) {
    $new_display_name = sanitize_text_field($_POST['display_name']);
    $new_vital_info = sanitize_textarea_field($_POST['vital_information']);

    // Optionally update meta
    update_post_meta($post_id, '_display_name', $new_display_name);
    update_post_meta($post_id, '_vital_information', $new_vital_info);

    // Rebuild content
    $image_url = wp_get_attachment_url(get_post_meta($post_id, '_customer_image', true));
    $image_html = $image_url
        ? '<img src="' . esc_url($image_url) . '" alt="Customer Image" style="float:right; margin:0 0 20px 20px; max-width:300px; height:auto;">'
        : '<p>No image uploaded.</p>';

    $tel_href = get_post_meta($post_id, '_tel_href', true);

    $buttons_html = '
        <div style="margin-top: 30px; display: flex; flex-wrap: wrap; gap: 15px;">
            <a href="' . esc_attr($tel_href) . '" 
               style="padding: 24px; background-color: #dc2626; color: white; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold;">
                📞 Contact
            </a>
            <a href="#" id="open-maps-btn" 
               style="padding: 24px; background-color: #16a34a; color: white; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold;">
                🚔 Find the Nearest Authorities
            </a>
        </div>';

    $new_content = "
        <h1>$new_display_name</h1>
        $image_html
        <p>$new_vital_info</p>
        <div style='clear: both;'>&nbsp;</div>
        $buttons_html
    ";

    wp_update_post([
        'ID' => $post_id,
        'post_content' => $new_content,
    ]);

    echo '<p style="color:green;"><strong>Page updated.</strong></p>';
}
?>

<div class="qr-page-wrapper" style="max-width: 800px; margin: auto;">
<?php if ($is_owner): ?>

    <?php
    // Extract existing values
    $display_name = get_post_meta($post_id, '_display_name', true);
    $vital_information = get_post_meta($post_id, '_vital_information', true);
    ?>

    <form method="post">
        <p>
            <label>Display Name<br>
                <input type="text" name="display_name" value="<?php echo esc_attr($display_name); ?>" style="width:100%;">
            </label>
        </p>
        <p>
            <label>Vital Information<br>
                <textarea name="vital_information" rows="5" style="width:100%;"><?php echo esc_textarea($vital_information); ?></textarea>
            </label>
        </p>
        <p>
            <input type="submit" name="gds_update_serial_page" value="Update Page">
        </p>
    </form>

<?php else: ?>
    <?php
    // Default output for public
    while (have_posts()) : the_post();
        the_content();
    endwhile;
    ?>
<?php endif; ?>
</div>

