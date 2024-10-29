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
if (!defined('WPINC')) {
    die;
}


ini_set('default_socket_timeout', 5000);
/**
 * Plugin activation check
 *
 * @return void
 */
function aramex_optilog_activation_check()
{
    if (!class_exists('SoapClient')) {
        deactivate_plugins(basename(__FILE__));
        wp_die(__('Sorry, but you cannot run this plugin, it requires the', 'aramex') . "<a href='http://php.net/manual/en/class.soapclient.php'>SOAP</a>" . __(' support on your server/hosting to function.', 'optilog'));
    }
}
register_activation_hook(__FILE__, 'aramex_optilog_activation_check');

/*
 * Check if WooCommerce is active
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    /**
     * Include file
     *
     * @return void
     */
    function aramex_optilog_shipping_method()
    {
        include_once('includes/shipping/class-aramex-optilog-woocommerce-shipping.php');
    }
    include_once(plugin_dir_path(__DIR__) . '/woocommerce/woocommerce.php');

    add_action('woocommerce_shipping_init', 'aramex_optilog_shipping_method');
    add_action('woocommerce_product_meta_start', 'aramex_optilog_shipping_method');

    /**
     * Add Optilog shipping method
     *
     * @param array $methods Shipping methods
     * @return array Added shipping methods
     */
    function add_aramex_optilog_shipping_method($methods)
    {
        $methods[] = 'Aramex_Optilog_Shipping_Method';
        return $methods;
    }
    add_filter('woocommerce_shipping_methods', 'add_aramex_optilog_shipping_method');

    /**
     * Get plugins file
     *
     * @return string Plugin`s file
     */
    function aramex_optilog_plugin_plugin_path()
    {
        // gets the absolute path to this plugin directory
        return untrailingslashit(plugin_dir_path(__FILE__));
    }
    add_action('woocommerce_admin_order_data_after_shipping_address', 'optilog_paid');

    /**
     * Register Bulk template
     *
     * @return void
     */
    function optilog_paid()
    {
        global $post_type;
        if ($post_type == 'shop_order' && isset($_GET['post'])) {
            include_once('templates/adminhtml/paid.php');

            $latestNotes = wc_get_order_notes(array(
                'order_id' => sanitize_text_field($_GET['post']),
                'orderby' => 'date_created_gmt',
            ));
            $paid = false;
            foreach ($latestNotes as $status) {
                if (isset($status->content) && strpos($status->content, "Paid and ready for Aramex shipment") === 0) {
                    $paid = true;
                    break;
                }
            }
            optilog_paid_admin($paid);
        }
    }

    /**
     * Register custom style
     *
     * @return void
     */
    function load_optilog_aramex_wp_admin_style()
    {
        wp_register_style('optilog_wp_admin_css', plugin_dir_url(__FILE__) . 'assets/css/optilog.css');
        wp_enqueue_style('optilog_wp_admin_css');
    }
    add_action('admin_enqueue_scripts', 'load_optilog_aramex_wp_admin_style');
    add_action('admin_footer', 'custom_aramex_optilog_bulk_admin_footer');

    /**
     * Register Bulk template
     *
     * @return void
     */
    function custom_aramex_optilog_bulk_admin_footer()
    {
        global $post_type;
        if ($post_type == 'shop_order' && isset($_GET['post_type'])) {
            include_once('templates/adminhtml/bulk-optilog.php');
            aramex_optilog_display_bulk_in_admin();
        }
    }

    /**
     * Get list of order statuses
     *
     * @return void Order`s statuses
     */
    function aramex_optilog_get_list_order_statuses()
    {
        return $statuses = [
            'wc-processing_aramex' => __('Processing(Aramex)', 'optilog'),
            'wc-fresh_aramex' => __('Fresh(Aramex)', 'optilog'),
            'wc-processed_aramex' => __('Processed(Aramex)', 'optilog'),
            'wc-new_aramex' => __('New(Aramex)', 'optilog'),
            'wc-allocated_aramex' => __('Allocated(Aramex)', 'optilog'),
            'wc-part_allocated_aramex' => __('Part Allocated(Aramex)', 'optilog'),
            'wc-picked_aramex' => __('Picked(Aramex)', 'optilog'),
            'wc-part_picked_aramex' => __('Part Picked(Aramex)', 'optilog'),
            'wc-cancelled_aramex' => __('Cancelled(Aramex)', 'optilog'),
            'wc-issued_aramex' => __('Issued(Aramex)', 'optilog'),
            'wc-blocked_aramex' => __('Blocked(Aramex)', 'optilog'),
            'wc-invoiced_aramex' => __('Invoiced(Aramex)', 'optilog'),
            'wc-staged_aramex' => __('Staged(Aramex)', 'optilog'),
            'wc-shipped_aramex' => __('Shipped(Aramex)', 'optilog'),
            'wc-part_loaded_aramex' => __('Part Loaded(Aramex)', 'optilog'),
            'wc-loaded_aramex' => __('Loaded(Aramex)', 'optilog'),
            'wc-part_shipped_aramex' => __('Part Shipped(Aramex)', 'optilog'),
        ];
    }

    /**
     * Register Status of orders
     *
     * @return void
     */
    function register_aramex_optilog_new_order_statuses()
    {
        $statuses = aramex_optilog_get_list_order_statuses();
        foreach ($statuses as $key => $value) {
            register_post_status($key, array(
                'label' => _x($value, 'Order status', 'optilog'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop($value . ' <span class="count">(%s)</span>', $value . '<span class="count">(%s)</span>', 'optilog')
                )
            );
        }
    }
    add_action('init', 'register_aramex_optilog_new_order_statuses');

    /**
     * Show status in order edit page`s dropdown
     *
     * @return array Order`s statuses
     */
    function aramex_optilog_add_order_statuses($order_statuses)
    {
        $statuses = aramex_optilog_get_list_order_statuses();
        foreach ($statuses as $key => $value) {
            $order_statuses[$key] = $value;
        }
        return $order_statuses;
    }
    add_filter('wc_order_statuses', 'aramex_optilog_add_order_statuses');

////////////
    function aramex_optilog_cron_test()
    {
        include_once('includes/optilog/class-aramex-optilog-woocommerce-cron.php');
        $cron = new Cron();
        $cron->startCron();
    }
    //add_action('admin_footer', 'aramex_optilog_cron_test');

    /**
     * Set custom interval for cron
     *
     * @return array Settings for Cron
     */
    function aramex_optilog_cron_interval($raspisanie)
    {
        $settings = get_option('woocommerce_aramex_optilog_settings');
        $raspisanie['every_custom_min'] = array(
            'interval' => $settings['delay'] * 60,
            'display' => 'Every ' . $settings['delay'] * 60
        );
        return $raspisanie;
    }
    add_filter('cron_schedules', 'aramex_optilog_cron_interval');

    if (!wp_next_scheduled('aramex_optilog', [])) {
        wp_schedule_event(time(), 'every_custom_min', 'aramex_optilog', []);
    }

    if (defined('DOING_CRON') && DOING_CRON) {
        add_action('aramex_optilog', 'aramex_optilog_go_cron', 10, 3);
    }

    /**
     * Set custom interval for cron
     *
     * @return void
     */
    function aramex_optilog_go_cron()
    {
        $settings = get_option('woocommerce_aramex_optilog_settings');
        if ($settings['autoMode'] != 1) {
            return false;
        }
          include_once('includes/optilog/class-aramex-optilog-woocommerce-cron.php');
          $cron = new Cron();
          $cron->startCron();
    }

    /**
     * Deactivate aramex_optilog cron for deinstalling process
     *
     * @return void
     */
    function aramex_optilog_deactivation()
    {
        wp_clear_scheduled_hook('aramex_optilog');
    }
    register_deactivation_hook(__FILE__, 'aramex_optilog_deactivation');


    //add_filter('woocommerce_order_number', 'change_woocommerce_order_number');

    function change_woocommerce_order_number($order_id)
    {
        $prefix = 'VKK/';
        $suffix = '/TS';
        $new_order_id = $prefix . $order_id . $suffix;
        return $new_order_id;
    }

    /**
     * Deactivate aramex_optilog cron for deinstalling process
     *
     * @return void
     */
    function aramex_optilog_load_classes()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/optilog/class-aramex-optilog-woocommerce-paid.php';
        $optilogPaid = new Optilog_Paid();
        add_action('wp_ajax_the_aramex_optilog_paid', array(
            $optilogPaid,
            'execute'
        ));

        require_once plugin_dir_path(__FILE__) . 'includes/optilog/class-aramex-optilog-woocommerce-index.php';
        $optilogMethod = new Optilog_Method();
        add_action('wp_ajax_the_aramex_optilog_index', array(
            $optilogMethod,
            'execute'
        ));
    }
    add_action('init', 'aramex_optilog_load_classes');

}
