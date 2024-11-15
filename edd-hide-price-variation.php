<?php
/*
Plugin Name: Easy Digital Downloads - Hide Price Variation
Plugin URI: https://xtendify.com
Description: Allows administrators to hide specific price variations from customers in Easy Digital Downloads. Perfect for creating private or archiving pricing options.
Version: 1.0.0
Author: Rohit Singhal
Author URI: https://xtendify.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: edd-hide-price-variation
Domain Path: /languages
Requires at least: 5.0
Requires PHP: 7.2
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class EDD_Hide_Price_Variation {

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Check if EDD is active
        if (!$this->check_edd()) {
            return;
        }

        // Add checkbox to each price variation option
        add_action('edd_download_price_option_row', array($this, 'add_hide_variation_field'), 10, 3);

        // Save the hide variation settings
        add_action('edd_save_download', array($this, 'save_hidden_variation_fields'));

        // Filter prices on frontend
        add_filter('edd_get_variable_prices', array($this, 'filter_hidden_variations'), 10, 2);

        // Load text domain
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Check if EDD is installed and active
     *
     * @since 1.0.0
     * @return bool
     */
    private function check_edd() {
        if (!function_exists('EDD')) {
            add_action('admin_notices', array($this, 'missing_edd_notice'));
            return false;
        }
        return true;
    }

    /**
     * Display notice if EDD is not installed
     *
     * @since 1.0.0
     */
    public function missing_edd_notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="error"><p>' .
                 sprintf(
                     __('Easy Digital Downloads - Hide Price Variation requires Easy Digital Downloads to be installed and active. You can download %s here.', 'edd-hide-price-variation'),
                     '<a href="https://easydigitaldownloads.com" target="_blank">Easy Digital Downloads</a>'
                 ) .
                 '</p></div>';
        }
    }

    /**
     * Load plugin textdomain
     *
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'edd-hide-price-variation',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Add hide variation checkbox field
     *
     * @since 1.0.0
     * @param int $post_id The download post ID
     * @param int $key The price option key
     * @param array $args Additional arguments
     */
    public function add_hide_variation_field($post_id, $key, $args) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        wp_nonce_field('edd_hide_variation_nonce', 'edd_hide_variation_nonce');

        $hidden = get_post_meta($post_id, '_edd_hide_variation_' . $key, true);
        ?>
        <div class="edd-custom-price-option-section">
            <div class="edd-custom-price-option-section-content">
                <label for="edd_hide_variation_<?php echo esc_attr($key); ?>">
                    <input type="checkbox"
                           id="edd_hide_variation_<?php echo esc_attr($key); ?>"
                           name="edd_variable_prices[<?php echo esc_attr($key); ?>][hide_variation]"
                           value="1"
                           <?php checked(1, $hidden, true); ?>>
                    <?php esc_html_e('Hide this price option from customers', 'edd-hide-price-variation'); ?>
                </label>
            </div>
        </div>
        <?php
    }

    /**
     * Save hidden variation settings
     *
     * @since 1.0.0
     * @param int $post_id The download post ID
     */
    public function save_hidden_variation_fields($post_id) {
        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['edd_hide_variation_nonce']) ||
            !wp_verify_nonce($_POST['edd_hide_variation_nonce'], 'edd_hide_variation_nonce')) {
            return;
        }

        // Get prices
        $prices = edd_get_variable_prices($post_id);
        if (!$prices) {
            return;
        }

        // Sanitize and save
        foreach ($prices as $key => $price) {
            $is_hidden = isset($_POST['edd_variable_prices'][$key]['hide_variation']) ?
                        absint($_POST['edd_variable_prices'][$key]['hide_variation']) : 0;

            if ($is_hidden) {
                update_post_meta($post_id, '_edd_hide_variation_' . $key, 1);
            } else {
                delete_post_meta($post_id, '_edd_hide_variation_' . $key);
            }
        }
    }

    /**
     * Filter out hidden variations on frontend
     *
     * @since 1.0.0
     * @param array $prices Array of prices
     * @param int $download_id The download post ID
     * @return array Modified array of prices
     */
    public function filter_hidden_variations($prices, $download_id) {
        if (!is_admin() && !empty($prices)) {
            foreach ($prices as $price_id => $price) {
                if (get_post_meta($download_id, '_edd_hide_variation_' . $price_id, true)) {
                    unset($prices[$price_id]);
                }
            }
        }
        return $prices;
    }
}

// Initialize the plugin
new EDD_Hide_Price_Variation();
