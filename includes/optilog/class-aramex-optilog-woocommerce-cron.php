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

include_once __DIR__ . '/class-aramex-optilog-woocommerce.php';

/**
 * Class Cron is  controller for Cron functionality
 */
class Cron extends Aramex_Optilog
{
    /**
     * Starting method
     *
     * @return mixed|string|void
     */
    public function startCron()
    {
        /* Get orders from database with "Processing" it means "Paid" */
        $proccessingsOrders = $this->getProcessingOrders();
        /* Send request to Aramex to get Order reference like(SPLWH/2018/25157) for product */
        if (!empty($proccessingsOrders)) {
            $this->getOrderReference($proccessingsOrders);
        }
        /* Get Aramex`s orders from database which statuses are not "Processing and Completed" */
        $processedOrders = $this->getProcessedOrders();

        /* Get statuses from Aramex Optilog */
        if (!empty($processedOrders)) {
            $this->wsdlRequestSynchronizeMass($processedOrders);
        }
        /*** Quantity of products update ****/
       $this->makeRequestToOptilog();
        //die("no processed orders");
    }
}
