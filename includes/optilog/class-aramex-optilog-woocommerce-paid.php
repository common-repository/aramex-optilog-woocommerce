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
 * Class Optilog_Paid is controller to save "Paid" status
 */
class Optilog_Paid extends Aramex_Optilog
{
    /**
     * Save "Paid" status
     *
     * @return array Returns success message if status was saved
     */
    public function execute()
    {
        check_admin_referer('aramex-optilog-paid' . wp_get_current_user()->user_email);
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $id = htmlspecialchars(strip_tags(trim(sanitize_text_field($_POST['id']))));
        }
        if (isset($id)) {
            $commentdata = array(
                'comment_post_ID' => $id,
                'comment_author' => '',
                'comment_author_email' => '',
                'comment_author_url' => '',
                'comment_content' => "Paid and ready for Aramex shipment",
                'comment_type' => 'order_note',
                'user_id' => "0",
            );
            wp_new_comment($commentdata);
            $order = new WC_Order($id);
            $order->add_order_note($commentdata['comment_content']);
            $order->save();
            if (!empty($order)) {
                $order->update_status("wc-processing_aramex", "");
            }
            $response['success'] = true;
            header('Content-type: application/json');
            echo json_encode($response);
            die();
        } else {
            die("not correct data");
        }
    }
}
$optilogPaid = new Optilog_Paid();
