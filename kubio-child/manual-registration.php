<?php get_header();

if (!empty($_SESSION['gds_registration_notice'])) {
    $notice = $_SESSION['gds_registration_notice'];
    echo '<div class="' . esc_attr($notice['type']) . '" style="padding:10px; border:1px solid #ccc; margin-bottom:20px;">';
    echo esc_html($notice['message']);
    echo '</div>';

    // Clear notice after displaying
    unset($_SESSION['gds_registration_notice']);
}

if (isset($_GET['gds_registered']) && $_GET['gds_registered'] == '1') {
    $page_url = get_transient('gds_registration_success_' . get_current_user_id());
    if ($page_url) {
        echo '<div class="woocommerce-message" role="alert" style="margin: 20px 0;">';
        echo '✅ Your serial number has been successfully registered. ';
        echo '<a href="' . esc_url($page_url) . '" target="_blank" style="font-weight: bold;">Click here to view your page.</a>';
        echo '</div>';
        delete_transient('gds_registration_success_' . get_current_user_id());
    }
}

?>

<form method="post" enctype="multipart/form-data">
    <?php
    // Simulate dummy data for order/item if this is a manual form
    $dummy_order_id = 999999; // replace with logic if needed
    $dummy_item_id  = 69; // noice
    $nonce = wp_create_nonce('gds_register_serial_' . $dummy_item_id);
    ?>

    <input type="hidden" name="gds_submit_registration" value="1">
    <input type="hidden" name="gds_order_id" value="<?php echo esc_attr($dummy_order_id); ?>">
    <input type="hidden" name="gds_item_id" value="<?php echo esc_attr($dummy_item_id); ?>">
    <input type="hidden" name="gds_nonce" value="<?php echo esc_attr($nonce); ?>">

    <p>
        <label>Full Name<br>
            <input type="text" name="gds_name" required style="width:100%;">
        </label>
    </p>
    <p>
        <label>Phone Number<br>
            <input type="text" name="gds_phone" required style="width:100%;">
        </label>
    </p>
    <p>
        <label>Vital Information<br>
            <textarea name="gds_vital_info" rows="5" placeholder="Example: Autistic, non-verbal, has dementia, requires medication, etc." required style="width:100%;"></textarea>
        </label>
    </p>
    <p>
        <label>Serial Number<br>
            <input type="text" name="gds_serial" required style="width:100%;">
        </label>
    </p>
    <p>
        <label>Upload Image (optional)<br>
            <input type="file" name="gds_image">
        </label>
    </p>
    <p>
        <input type="submit" value="Register Product">
    </p>
</form>

<?php get_footer(); ?>
