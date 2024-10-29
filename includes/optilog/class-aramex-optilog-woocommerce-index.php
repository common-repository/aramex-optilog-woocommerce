<?php
/**
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
class Optilog_Method extends Aramex_Optilog
{
    /**
     * Execute status functionality
     *
     * @return array Response from Optilog server
     */
    public function execute()
    {
        check_admin_referer('aramex-optilog-check' . wp_get_current_user()->user_email);
        $post = [];
        $marker = "";
        if (isset($_POST['selectedOrders']) && is_array($_POST['selectedOrders']) && !empty($_POST['selectedOrders'])) {
            foreach ($_POST['selectedOrders'] as $value) {
                $post[] = htmlspecialchars(strip_tags(trim(sanitize_text_field($value))));
            }
        }
        if (isset($_POST['marker']) && !empty($_POST['marker'])) {
            $marker = htmlspecialchars(strip_tags(trim(sanitize_text_field($_POST['marker']))));
        }
        $orders = [];
        $response = [];
        $successIds = [];

        if (!empty($post) && $marker == "bulk_submit_optilog_order") {
            /* Get orders from database with "Processing" it means "Paid" */
            $processingOrders = $this->getProcessingOrders($post);

            if (!empty($processingOrders)) {
                foreach ($processingOrders as $order) {
                    if (in_array($order->ID, $post)) {
                        $orders[] = $order;
                        $successIds[] = $order->ID;
                    }
                }
                $response['errors'] = array_diff($post, $successIds);
                if (!empty($orders)) {
                    /* Send request to Aramex to get Order reference like(SPLWH/2018/25157) for product */
                    $result = $this->getOrderReference($orders);
                    if (isset($result['error'])) {
                        $response['error'][] = $result['error'];
                    }
                    if (isset($result['success'])) {
                        $response['success'] = array_reverse($result['success']);
                    }
                } else {
                    $response['errors'] = $post;
                    header('Content-type: application/json');
                    echo json_encode($response);
                    die();
                }
                header('Content-type: application/json');
                echo json_encode($response);
                die();
            } else {
                $response['errors'] = $post;
                header('Content-type: application/json');
                echo json_encode($response);
                die();
            }
        } elseif (!empty($post) && $marker == "bulk_synhronize_optilog_order") {
            if (!empty($post)) {
                /* Get Aramex`s orders from database which statuses are not "Processing and Completed" */
                $processedOrders = $this->getProcessedOrders($post);
                /* Get statuses from Aramex Optilog */
                if (!empty($processedOrders)) {
                   foreach ($processedOrders as $order) {
                        if (in_array($order->ID, $post)) {
                            $orders[] = $order;
                            $successIds[] = $order->ID;
                        }
                    }
                    $response['errors'] = array_diff($post, $successIds);
                     if (!empty($orders)) {
                        $result = $this->wsdlRequestSynchronizeMass($orders);
                        if (isset($result['error'])) {
                            $response['error'][] = $result['error'];
                        }
                        if (isset($result['success'])) {
                            $response['success'] = array_reverse($result['success']);
                        }
                    } else {
                        $response['errors'] = $post;
                        header('Content-type: application/json');
                        echo json_encode($response);
                        die();
                    }
                    header('Content-type: application/json');
                    echo json_encode($response);
                    die();
                } else {
                    $response['errors'] = array_reverse($post);
                    header('Content-type: application/json');
                    echo json_encode($response);
                    die();
                }
            }
        } else {
            die("not correct data");
        }
    }
}
$optilogMethod = new Optilog_Method();
