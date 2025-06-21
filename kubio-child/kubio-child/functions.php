<?php

add_action('woocommerce_before_add_to_cart_button', 'custom_add_product_fields');

// adds custom fields to the product to collect information to be used to create page later

function custom_add_product_fields() {
    echo '<div class="custom-fields-wrapper" style="margin-bottom: 20px;">';

    // Name of Person or Pet or Object
    woocommerce_form_field('customer_name', [
        'type'        => 'text',
        'required'    => true,
        'label'       => 'Name of Person/Pet/Object',
        'placeholder' => '',
    ]);
    
    echo '<p><label for="custom_image_upload">Upload an image:</label><br>';
    echo '<input type="file" name="custom_image_upload" accept="image/*"></p>';

    // Vital Information
    woocommerce_form_field('vital_information', [
        'type'        => 'textarea',
        'required'    => true,
        'label'       => 'Information Needed If Found by Stranger',
        'placeholder' => 'Example: Autistic, non-verbal, has dementia, requires medication, etc.',
    ]);
    
    // Phone Number
    woocommerce_form_field('phone_number', [
        'type'        => 'tel',
        'required'    => false,
        'label'       => 'Your phone number (will not appear on the scanned page)',
        'placeholder' => '',
    ]);

    echo '</div>';
}

add_filter('woocommerce_add_to_cart_validation', 'custom_validate_product_fields', 10, 3);

function custom_validate_product_fields($passed, $product_id, $quantity) {
    if (empty($_POST['customer_name'])) {
        wc_add_notice('Please enter a name.', 'error');
        return false;
    }
    
    if (empty($_POST['vital_information'])) {
        wc_add_notice("Please enter vital information", 'error');
        return false;
    }

    return $passed;
}

add_filter('woocommerce_add_cart_item_data', 'gds_save_fields_to_cart', 10, 2);

function gds_save_fields_to_cart($cart_item_data, $product_id) {
    $cart_item_data['customer_name'] = sanitize_text_field($_POST['customer_name']);
    $cart_item_data['phone_number'] = sanitize_text_field($_POST['phone_number']);
    $cart_item_data['vital_information'] = sanitize_textarea_field($_POST['vital_information']);
    
    if (!empty($_FILES['custom_image_upload']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = media_handle_upload('custom_image_upload', 0);

        if (is_wp_error($uploaded)) {
            wc_add_notice(__('There was an error uploading the image.'), 'error');
        } else {
            $cart_item_data['custom_image_id'] = $uploaded;
        }
    }
    // We'll process the image on checkout
    return $cart_item_data;
}

add_filter('woocommerce_get_item_data', 'gds_display_fields_in_cart', 10, 2);

function gds_display_fields_in_cart($item_data, $cart_item) {
    foreach (['customer_name', 'phone_number', 'vital_information'] as $key) {
        if (!empty($cart_item[$key])) {
            $item_data[] = [
                'key'   => ucwords(str_replace('_', ' ', $key)),
                'value' => esc_html($cart_item[$key])
            ];
        }
    }

    if (!empty($cart_item['custom_image_id'])) {
        $image_url = wp_get_attachment_url($cart_item['custom_image_id']);
        if ($image_url) {
            $item_data[] = [
                'key'   => 'Uploaded Image',
                'value' => '<img src="' . esc_url($image_url) . '" style="max-width:100px; height:auto;" />',
                'display' => '',
            ];
        }
    }

    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'gds_save_fields_to_order_items', 10, 4);

function gds_save_fields_to_order_items($item, $cart_item_key, $values, $order) {
    foreach (['customer_name', 'phone_number', 'vital_information'] as $key) {
        if (!empty($values[$key])) {
            $item->add_meta_data(ucwords(str_replace('_', ' ', $key)), $values[$key]);
        }
    }

    if (!empty($values['custom_image_id'])) {
        $image_url = wp_get_attachment_url($values['custom_image_id']);
        if ($image_url) {
            $item->add_meta_data('Customer Image', $values['custom_image_id']);
        }
    }
}

add_action('woocommerce_before_order_itemmeta', function($item_id, $item, $product) {
    $image_url = $item->get_meta('Customer Image');
    if ($image_url) {
        echo '<p><strong>Customer Image:</strong><br><img src="' . esc_url($image_url) . '" style="max-width:100px;" /></p>';
    }
}, 10, 3);

// CRITICAL - this is required, otherwise the information saved gets wiped out due to Kubio page builder
add_filter('the_content', function ($content) {
    // Only apply on front-end single page view
    if (is_page() && !is_admin()) {
        global $post;

        // Check if it's a programmatically created page (e.g., by title or meta)
        if (strpos($post->post_title, 'Information -') === 0) {
            // Return the raw post_content without Kubio modification
            return $post->post_content;
        }
    }

    return $content;
}, 5); // Run this before Kubio's filters

// This is required to add a button used by Google Maps
function enqueue_serial_page_script() {
    if (is_page()) {
        // Optional: Add a check here if you only want the script to load for certain pages
        wp_enqueue_script(
            'serial-page-js',
            get_stylesheet_directory_uri() . '/js/serial-page.js',
            array(), // No dependencies
            filemtime(get_stylesheet_directory() . '/js/serial-page.js'), // Cache busting
            true // Load in footer
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_serial_page_script');

add_action('woocommerce_order_item_meta_start', function($item_id, $item, $order, $plain_text) {
    $image_url = $item->get_meta('Customer Image');
    if ($image_url) {
        echo '<p><strong>Uploaded Image:</strong><br><img src="' . esc_url($image_url) . '" style="max-width:100px;" /></p>';
    }
}, 10, 4);

add_action('woocommerce_order_status_processing', 'send_order_processing_email');

function send_order_processing_email($order_id) {
    $order = wc_get_order($order_id);
    $to = $order->get_billing_email();
    $subject = 'Your order is now being processed!';
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    $message = '<p><img src="https://lockstonellc.com/wp-content/uploads/2025/04/Icon-Image-1.png" alt="RSQ Tag" width="25%" /></p>';
    $message .= '<p>Hi ' . $order->get_billing_first_name() . ',</p>';
    $message .= '<p>Thanks for your order! We\'ve started processing it and will notify you when it\'s ready.</p>';
    $message .= '<p>Order Number: ' . $order->get_order_number() . '</p>';
    
    wp_mail($to, $subject, $message, $headers);
}

add_action('woocommerce_order_status_completed', 'send_order_completed_email');

function send_order_completed_email($order_id) {
    $order = wc_get_order($order_id);
    $to = $order->get_billing_email();
    $subject = 'Your order is complete!';
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Get the saved generated page link
    $generated_url = get_post_meta($order_id, '_generated_page_url', true);

    $message = '<p><img src="https://lockstonellc.com/wp-content/uploads/2025/04/Icon-Image-1.png" alt="RSQ Tag" width="25%" /></p>';
    $message .= '<p>Hi ' . $order->get_billing_first_name() . ',</p>';
    $message .= '<p>Your order has been completed. Thank you for choosing us!</p>';
    $message .= '<p>You can view your custom page here: <a href="' . esc_url($generated_url) . '">' . esc_html($generated_url) . '</a></p>';
    $message .= '<p>Order Number: ' . $order->get_order_number() . '</p>';

    wp_mail($to, $subject, $message, $headers);
}

function gds_user_registered_items_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your registrations.</p>';
    }

    $user_id = get_current_user_id();

    $args = [
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => '_is_registered_item',
                'value'   => true,
                'compare' => '='
            ],
            [
                'key'     => '_registered_user_id',
                'value'   => $user_id,
                'compare' => '='
            ]
        ]
    ];

    $query = new WP_Query($args);

    ob_start();

    if ($query->have_posts()) {
        echo '<ul>';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<li><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No registered items found.</p>';
    }

    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('user_registered_items', 'gds_user_registered_items_shortcode');

add_filter( 'woocommerce_account_menu_items', 'remove_my_account_downloads_tab' );

function remove_my_account_downloads_tab( $items ) {
    unset( $items['downloads'] );
    return $items;
}

add_filter( 'woocommerce_account_menu_items', 'custom_rename_orders_tab', 999 );
function custom_rename_orders_tab( $menu_items ) {
    if ( isset( $menu_items['orders'] ) ) {
        $menu_items['orders'] = 'Registered Items';
    }
    return $menu_items;
}

add_filter( 'woocommerce_account_menu_items', 'combine_account_details_and_addresses', 100 );
function combine_account_details_and_addresses( $menu_items ) {
    unset( $menu_items['edit-account'] ); // Account Details
    unset( $menu_items['edit-address'] ); // Addresses

    // Add custom combined tab
    $menu_items['combined-details'] = __( 'Profile Info', 'woocommerce' );

    return $menu_items;
}

// Register the new endpoint
add_action( 'init', 'register_combined_details_endpoint' );
function register_combined_details_endpoint() {
    add_rewrite_endpoint( 'combined-details', EP_ROOT | EP_PAGES );
}

// Add content to the new endpoint
add_action( 'woocommerce_account_combined-details_endpoint', 'combined_details_content' );
function combined_details_content() {
    echo '<h2>Account Details</h2>';
    wc_get_template( 'myaccount/form-edit-account.php' );

    echo '<hr style="margin:40px 0;">';

    echo '<h2>Addresses</h2>';
    wc_get_template( 'myaccount/my-address.php' );
}

add_action( 'woocommerce_account_orders_endpoint', 'append_to_registered_items_tab' );
function append_to_registered_items_tab() {
    echo do_shortcode('[user_registered_items]');
}

add_filter( 'woocommerce_account_menu_items', 'reorder_account_tabs', 110 );
function reorder_account_tabs( $items ) {
    $new_order = array(
        'dashboard'         => $items['dashboard'],
        'orders'            => $items['orders'], // or 'Registered Items'
        'combined-details'  => $items['combined-details'],
        'logout'            => $items['customer-logout'],
    );

    return $new_order;
}

add_action( 'init', 'override_woocommerce_dashboard', 15 );
function override_woocommerce_dashboard() {
    remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard', 10 );
    add_action( 'woocommerce_account_dashboard', 'custom_dashboard_content' );
}

// Remove WooCommerce's default dashboard content
remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard', 10 );

// Add your custom dashboard content
add_action( 'woocommerce_account_dashboard', 'custom_dashboard_content' );
function custom_dashboard_content() {
    $user = wp_get_current_user();

    echo '<h2>Welcome back, ' . esc_html( $user->display_name ) . '!</h2>';
    echo '<p>This is your custom account dashboard. From here, you can:</p>';

    echo '<ul>';
    echo '<li><a href="' . esc_url( wc_get_account_endpoint_url( 'orders' ) ) . '">View Registered Items</a></li>';
    echo '<li><a href="' . esc_url( wc_get_account_endpoint_url( 'account-info' ) ) . '">Edit Profile Info</a></li>';
    echo '<li><a href="' . esc_url( wc_get_account_endpoint_url( 'customer-logout' ) ) . '">Logout</a></li>';
    echo '</ul>';
}

function manual_serial_registration_form() {
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
<?php
}

add_action( 'woocommerce_account_orders_endpoint', 'show_my_form_on_registered_items_tab' );
function show_my_form_on_registered_items_tab() {
    echo '<h2>Register a New Item</h2>';
    manual_serial_registration_form();
}
