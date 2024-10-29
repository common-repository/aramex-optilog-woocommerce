<?php
/*
Plugin Name:  Aramex Optilog WooCommerce
Plugin URI:   https://aramex.com
Description:  Aramex Optilog WooCommerce plugin
Version:      1.0.0
Author:       aramex.com
Author URI:   https://www.aramex.com/solutions-services/developers-solutions-center
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  optilog
Domain Path:  /languages
 */

if (!class_exists('Aramex_Optilog_Shipping_Method')) {

    /**
     * Class Aramex optilog shipping
     */
    class Aramex_Optilog_Shipping_Method extends WC_Shipping_Method
    {
        /**
         * Constructor
         *
         * @return void
         */
        public function __construct()
        {
            $this->id = 'aramex_optilog';
            $this->method_title = __('Aramex Optilog Settings', 'optilog');
            $this->init();
            $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
            $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Aramex Optilog Shipping', 'optilog');
        }

        /**
         * Init Optilog settings
         *
         * @return void
         */
        public function init()
        {
            // Load the settings API
            $this->init_form_fields();
            // Save settings in admin if you have any defined
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Define settings field for this shipping
         *
         * @return void
         */
        public function init_form_fields()
        {
            $this->form_fields = include('data-aramex-optilog-settings.php');
        }

        /**
         * Calculate the shipping cost.
         *
         * @param array $package Package
         * @return void
         */
        public function calculate_shipping($package = array())
        {
            return false;
        }
    }
}
