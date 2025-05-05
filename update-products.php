<?php
/*
Plugin Name: Update Products Button
Description: Adds a button in the admin dashboard to update products and send a message to Claude 3.5 API.
Version: 1.0
Author: Payal Sharma
*/

// Include the Claude API Client (make sure to load this class appropriately)
require_once 'vendor/autoload.php'; // Adjust the path as needed

use Claude\Claude3Api\Client;
use Claude\Claude3Api\Config;

// Hook to add button on admin page
add_action('admin_menu', 'upb_add_admin_menu');

function upb_add_admin_menu() {
    add_menu_page(
        'Update Products',  // Page title
        'Update Products',  // Menu title
        'manage_options',   // Capability
        'update-products',  // Menu slug
        'upb_render_button_page', // Callback function
        'dashicons-update', // Icon
        25                   // Position in menu
    );
}

function upb_render_button_page() {
    ?>
    <div class="wrap">
        <h1>Update Products</h1>
        <p>Click the button below to update the products and interact with Claude:</p>
        <form method="post">
            <input type="submit" name="update_products_button" class="button button-primary" value="Update Products">
        </form>
    </div>
    <?php
    if (isset($_POST['update_products_button'])) {
        // Call the function to fetch updated descriptions and update them
        upb_get_updated_descriptions();
    }
}

function upb_get_updated_descriptions() {
    // Fetch all products
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'numberposts' => -1
    );

    // Get all products
    $products = get_posts($args);

    // Store all updated descriptions here
    $updated_descriptions = [];

    // Start output buffering to allow progressive display
    ob_start();

    // Loop through each product and get the updated description
    foreach ($products as $product) {
        // Get the current product description (post content)
        $description = $product->post_content;

        // Construct a more specific message to send to Claude
        $message = "Please update the following product description while keeping the formatting:\n";
        $message .= "Product Name: " . $product->post_title . "\n";
        $message .= "Current Description: " . $description . "\n";
        $message .= "Please provide a revised version without any additional text like product name or labels.\n";
        
        // Send the message to Claude API to get an updated description
        $updated_description = send_message_to_claude($message);

        // Store the updated description
        $updated_descriptions[$product->ID] = [
            'product_title' => $product->post_title,
            'updated_description' => $updated_description
        ];

        // Now update the product description in the database with the updated description from Claude
        $product_update = array(
            'ID'           => $product->ID,
            'post_content' => $updated_description
        );

        // Update the product post content with the new description
        wp_update_post($product_update);

        // Output a success message after each product update
        echo '<div class="updated"><h4>' . esc_html($product->post_title) . ' product description updated successfully.</h4></div>';

        // Flush the output buffer and ensure the message is displayed immediately
        ob_flush();
        flush();
    }

    // End output buffering (optional)
    ob_end_clean();
}

function send_message_to_claude($message) {
    // Replace 'your-api-key-here' with your actual API key
    $config = new Config('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');
    $client = new Client($config);

    try {
        // Send the message to Claude with an increased maxTokens value for long descriptions
        $response = $client->chat([
            'model' => 'claude-3-opus-20240229',
            'maxTokens' => 4096, // Increased token limit to handle longer descriptions
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ]
        ]);
        
        // Extract and return only the updated description
        $content = $response->getContent();
        
        // Look for the actual description text and return it
        $updated_description = '';
        if (isset($content[0]['text'])) {
            // Retrieve the updated description directly
            $updated_description = trim($content[0]['text']);
        }

        // Return the clean updated description (including possible HTML tags)
        return $updated_description;
    } catch (Exception $e) {
        // Handle any errors
        return 'Error: ' . $e->getMessage();
    }
}

?>
