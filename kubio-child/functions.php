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
        'placeholder' => 'Personally identifiable information or even crucial medical information that could be useful',
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
        'post_type'      => 'registered_item', // <-- Replace with your custom post type slug
        'posts_per_page' => -1,
        'author'         => $user_id
        // OR use 'meta_query' below if you're saving user ID in post meta
        /*
        'meta_query' => [
            [
                'key'     => 'registered_user_id',
                'value'   => $user_id,
                'compare' => '='
            ]
        ]
        */
    ];

    $query = new WP_Query($args);

    ob_start();

    if ($query->have_posts()) {
        echo '<ul>';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
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
