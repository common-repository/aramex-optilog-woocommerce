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

include_once __DIR__ . '../../core/class-aramex-helper.php';

/**
 * Class Aramex_Optilog is a parent controller for Bulk functionality
 */
class Aramex_Optilog
{
    /**
     * Temporary Array
     * @var  array
     */
    private $temporary = [];
    
    /**
     * Get orders with "processing" status
     *
     * @param array $ids Id of order
     * @return array Orders
     */
    protected function getProcessingOrders($ids = [])
    {
        $filters = array(
            'post__in'   => $ids,
            'post_status' => 'wc-processing_aramex',
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'orderby' =>'modified',
            'order' => 'ASC'
        );

        $my_query = new WP_Query($filters);
        $proccessingsOrders = $my_query->posts;
        return $proccessingsOrders;
    }

    /**
     * Get order reference
     *
     * @param array $proccessings Orders Orders with "processing" status
     * @return string|array Result from "optilog" server
     */
    protected function getOrderReference($proccessingsOrders)
    {
        $response = [];
        foreach ($proccessingsOrders as $order) {
            $item = [];
            $order = wc_get_order($order->ID);
            $orderData = $order->get_data();
            $products = $order->get_items();
            $i= 0;
            foreach ($products as $key=>$value) {
                $product = $value->get_product()->get_data();
                $productData = [];
                foreach ($order->get_items('line_item') as $item2) {
                    $item1 = $item2->get_data();
                    $product_id = ($item1['variation_id'])? $item1['variation_id']: $item1['product_id'];
                    $productData[$product_id] = $item1['quantity'];
                }
                $item['ShippingOrderItem'][$i]['SKU'] = $product['sku'];
                $item['ShippingOrderItem'][$i]['UnitCost'] = $product['price'];
                $item['ShippingOrderItem'][$i]['Comments'] = "";
                $item['ShippingOrderItem'][$i]['Quantity'] = $productData[$product['id']];
                $item['ShippingOrderItem'][$i]['Reference1'] = $orderData['currency'];
                $item['ShippingOrderItem'][$i]['Reference2'] = "";
                $item['ShippingOrderItem'][$i]['Reference3'] = $product['price'] * $productData[$product['id']];
                $item['ShippingOrderItem'][$i]['Reference4'] = "";
                $item['ShippingOrderItem'][$i]['Reference5'] = $orderData['total'];
                $i++;
            }
       
            foreach ($orderData['shipping_lines'] as $key => $value) {
                $shipmentData = $value->get_data();
                $shippingDescription = $shipmentData['name'];
            }
                
            $params = array(
                'Request' =>
                array(
                    'AccountDetails' => Aramex_Optilog_Helper::getAccountDetails(),
                    'SO' => array(
                        'Bonded' => 1,
                        'BondedType' => 2,
                        'OrderNumber' => $orderData['id'],
                        'ConsigneeName' => (isset($orderData['shipping']['first_name'])) ? $orderData['shipping']['first_name'] ." ". $orderData['shipping']['last_name'] : '',
                        'ConsigneeCity' => (isset($orderData['shipping']['city'])) ? $orderData['shipping']['city'] : '',
                        'ConsigneeAttention' => "",
                        'ConsigneeZipCode' => (isset($orderData['shipping']['postcode'])) ? $orderData['shipping']['postcode'] : '',
                        'ConsigneeCountryCode' => (isset($orderData['shipping']['country'])) ? $orderData['shipping']['country'] : '',
                        'ConsigneeAddress' => (isset($orderData['shipping']['address_1'])) ? $orderData['shipping']['address_1'] ." ". $orderData['shipping']['address_2'] ." ". $orderData['shipping']['state'] : '',
                        'Carrier' => (isset($shippingDescription))? $shippingDescription : "",
                        'ClearanceAgent' => "Aramex",
                        'ConsigneeReference' => (isset($orderData['shipping']['company'])) ? $orderData['shipping']['company'] : '',
                        'ConsigneePhone' => (isset($orderData['shipping']['shipping_phone'])) ? $orderData['shipping']['shipping_phone'] : '',
                        'Items' => $item
                    )
                ),
            );

            try {
                $soapClient = new SoapClient(Aramex_Optilog_Helper::getPath() . 'OptilogAPI.WSDL');
                $results = $soapClient->CreateSO($params);
                if ($results->CreateSOResult->HasErrors) {
                    $response['error'] = 'Aramex: ' . $results->CreateSOResult->ErrorDescription;
                    if (strpos($results->CreateSOResult->ErrorDescription, "not found")) {
                        $this->saveCommentAndStatus($orderData['id'], "Aramex Optilog: " . $results->CreateSOResult->ErrorDescription, "wc-blocked_aramex");
                    }
                } else {
                    $orderReference = $results->CreateSOResult->OrderReference ? $results->CreateSOResult->OrderReference : "";
                    $response['success'][$orderData['id']] = "Order: " . $orderData['id']. ", order reference: ". $orderReference;
                    $this->saveCommentAndStatus($orderData['id'],  "Aramex Optilog: Order Reference - " . $orderReference, "wc-processed_aramex");
                }
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
                return $response;
            }
        }
        return $response;
    }


    /**
     * Make request to Aramex server to get Status
     *
     * @param objecy $orders Order objects
     * @return array Messages from Aramex server
     */
    protected function wsdlRequestSynchronizeMass($orders)
    {
        $response = [];
        foreach ($orders as $order) {
            $orderReference = "";
            $latestNotes = wc_get_order_notes(array(
                'order_id' => $order->ID,
                'orderby'  => 'date_created_gmt',
            ));

            foreach ($latestNotes as $status) {
                if (isset($status->content) && strpos($status->content, "Aramex Optilog: Order Reference - ") === 0) {
                    $orderReference = ltrim($status->content, "Aramex Optilog: Order Reference - ");
                    break;
                }
            }
            $res = $this->wsdlRequestStatus($orderReference);

            if (isset($res["success"])) {
                $status = $res['success']['OrderStatus'];
                switch ($status) {
                    case -3:
                        $status = 'cancelled_aramex';
                        break;
                    case -2:
                        $status = 'issued_aramex';
                        break;
                    case -1:
                        $status = 'invoiced_aramex';
                        break;
                    case 0:
                        $status = 'new_aramex';
                        break;
                    case 1:
                        $status = 'part_allocated_aramex';
                        break;
                    case 2:
                        $status = 'allocated_aramex';
                        break;
                    case 3:
                        $status = 'staged_aramex';
                        break;
                    case 4:
                        $status = 'part_picked_aramex';
                        break;
                    case 5:
                        $status = 'picked_aramex';
                        break;
                    case 6:
                        $status = 'part_loaded_aramex';
                        break;
                    case 7:
                        $status = 'loaded_aramex';
                        break;
                    case 8:
                        $status = 'part_shipped_aramex';
                        break;
                    case 9:
                        $status = 'shipped_aramex';
                        break;
                }
                $AWBNumber = ($res['success']['AWBNumber']) ? $res['success']['AWBNumber'] : false;
                if (isset($res['success']['OrderStatus'])) {
                    //$totalLineItems = $this->getTotalItemsInfo($latestNotes);
                    //$this->partialFullfilled($order, $AWBNumber, $res, $totalLineItems['skuQuantity']);
					//die("gg");
                    //$this->completeOrder($order, $AWBNumber, $totalLineItems['skuQuantity']);

                    $totalIssuedItems = array();
                    if ($status == 'issued_aramex') {
                        // Total quontity of "ordered" products in order
                         $totalLineItems = $this->getTotalItemsInfo($latestNotes);
                    }
                    if (is_object($res['success']['Items']->KeyValueOfstringstring) && $status == 'issued_aramex') {
                        if ($totalLineItems['total'] == $res['success']['Items']->KeyValueOfstringstring->Value) {
                            // if all lineitems were fulfilled
                            $this->completeOrder($order, $AWBNumber, $totalLineItems['skuQuantity']);
                        } else {
                            $this->partialFullfilled($order, $AWBNumber, $res, $totalLineItems['skuQuantity']);
                        }
                    } else {
                        if ($status == 'issued_aramex') {
                            $total = 0;
                            foreach ($res['success']['Items']->KeyValueOfstringstring as $listItem) {
                                $total = $total + $listItem->Value;
                            }
                            if ($totalLineItems['total'] == $total) {
                                $this->completeOrder($order, $AWBNumber, $totalLineItems['skuQuantity']);
                            } else {
                                foreach ($res['success']['Items']->KeyValueOfstringstring as $listItem) {
                                    $this->partialFullfilled($order, $AWBNumber, $res, $totalLineItems['skuQuantity']);
                                }
                            }
                        }
                    }
                    
                    //get name of Status in database
                    if (ltrim($order->post_status, "wc-") != $status) {

                        $this->saveStatus($order->ID, 'wc-' . $status);
                    }
                }
                $response['success'][$order->ID] = "Order: " . $order->ID. ", status: ". wc_get_order_status_name( $status );
            } else {
                // log Aramex: Cannot find order with reference = SPLWH/2018/165
                if (strpos($res["error"], "not found")) {
                    $this->saveStatus($order->ID,  'wc-blocked_aramex');
                }
                $response['error'][$order->ID]= $res["error"];
            }
        }

        return $response;
    }

    /**
     * Get status from Aramex`s server side
     *
     * @param string $orderReference Order Reference
     * @return array Aramex`s server result
     */
    private function wsdlRequestStatus($orderReference)
    {
        $params = array(
            'Request' =>
            array(
                'AccountDetails' => Aramex_Optilog_Helper::getAccountDetails(),
                'OrderReference' => $orderReference
            ),
        );
        $response = array();
        try {
            $soapClient = new SoapClient(Aramex_Optilog_Helper::getPath() . 'OptilogAPI.WSDL');
            $results = $soapClient->GetSOStatus($params);
            if ($results->GetSOStatusResult->HasErrors) {
                $response['error'] = ' Aramex: ' . $results->GetSOStatusResult->ErrorDescription;
            } else {
                $response['success']['AWBNumber'] = $results->GetSOStatusResult->AWBNumber;
                $response['success']['OrderStatus'] = $results->GetSOStatusResult->OrderStatus;
                $response['success']['Items'] = $results->GetSOStatusResult->Items;
            }
        } catch (Exception $e) {
            $response['error'][] = $e->getMessage();
        }

        return $response;
    }

    /**
     * Get orders with which statuses are not "Processing and Issued"
     *
     * @param array $ids Id of order
     * @return array $proccessedOrders Objects of proccessed orders
     */
    protected function getProcessedOrders($ids = [])
    {
        $filters = array(
            'post_status' => array('wc-processed_aramex', 'wc-invoiced_aramex', 'wc-new_aramex', 'wc-part_allocated_aramex', 'wc-allocated_aramex', 'wc-staged_aramex', 'wc-part_picked_aramex', 'wc-picked_aramex', 'wc-part_loaded_aramex', 'wc-loaded_aramex', 'wc-part_shipped_aramex', 'wc-shipped_aramex', 'wc-issued_aramex'),
            'post_type' => 'shop_order',
            'post__in'   => $ids,
            'posts_per_page' => -1,
            'orderby' =>'modified',
            'order' => 'ASC'
        );

        $my_query = new WP_Query($filters);
        $proccessedOrders = $my_query->posts;
        return $proccessedOrders;
    }

    /**
     * Save comments
     *
     * @param string $orderId Id of order
     * @param string $comment Text of comment
     * @param string $status Current status
     * @return void
     */
    private function saveCommentAndStatus($orderId, $comment = null, $status)
    {
        $commentdata = array(
                            'comment_post_ID' => $orderId,
                            'comment_author' => '',
                            'comment_author_email' => '',
                            'comment_author_url' => '',
                            'comment_content' => $comment,
                            'comment_type' => 'order_note',
                            'user_id' => "0",
                        );
        wp_new_comment($commentdata);
        $order = new WC_Order($orderId);
        $order->add_order_note($commentdata['comment_content']);
        $order->save();
        if (!empty($order)) {
            $order->update_status($status, "");
        }
    }

    /**
     * Save status
     *
     * @param string $orderId Order id
     * @param string $status Current status
     * @return void
     */
    private function saveStatus($orderId,  $status)
    {

        $order = new WC_Order($orderId);
        if (!empty($order)) {
            $order->update_status($status, "");
        }
    }
    /**
     * Get quantity of issued items from database
     *
     * @param array $latestNotes Latest notes
     * @return array Information about Total Items
     */
    private function getTotalItemsInfo($latestNotes)
    {
        $listOfNotes = [];
        foreach ($latestNotes as $note) {
            if (isset($note->content) && strpos($note->content, "Issued iteems: ") === 0) {
                $arrayTemp = explode("Tracking", $note->content);
                $listOfNotes[] = explode("/", ltrim($arrayTemp[0], "Issued iteems: "));
            }
        }

        $temporary = [];
        if (!empty($listOfNotes)) {
            foreach ($listOfNotes as $key =>  $value) {
                foreach ($value as $key1 => $value1) {
                    if (!empty($value1)) {
                        $temporary[$key][] = explode(" ", $value1);
                    }
                }
            }
        }

        $skuQuantity = [];
        if (!empty($temporary)) {
            foreach ($temporary as $key =>  $value) {
                foreach ($value as $key1 => $value1) {
                    if (!empty($value1[0])) {
                        if (array_key_exists($value1[0], $skuQuantity)) {
                            $skuQuantity[$value1[0]] = $skuQuantity[$value1[0]] + (int)$value1[2];
                        } else {
                            $skuQuantity[$value1[0]] = (int)$value1[2];
                        }
                    }
                }
            }
        }

        $total = 0;
        if (!empty($skuQuantity)) {
            foreach ($skuQuantity as  $value) {
                $total = $total + (int)$value;
            }
        }

        return [
        "skuQuantity"=>$skuQuantity,
        "total"=>$total
    ];
    }
    /**
     * Partial fulfillment of order
     *
     * @param object $order Object of order
     * @param string $tracking_number Tracking Number
     * @param string $res Information from Optilog server
     * @param string $itemsShipped Information about already shipped orders
     * @return void
     */
    private function partialFullfilled($order, $tracking_number, $res, $itemsShipped)
    {
        $itemsFromAramex = [];
        if (is_object($res['success']['Items']->KeyValueOfstringstring)) {
            $itemsFromAramex[$res['success']['Items']->KeyValueOfstringstring->Key] = $res['success']['Items']->KeyValueOfstringstring->Value;
        } else {
            foreach ($res['success']['Items']->KeyValueOfstringstring as $listItem) {
                $itemsFromAramex[$listItem->Key] = $listItem->Value;
            }
        }
/*
        $itemsFromAramex = [
    "010"=> "47",
    "011"=> "18"
];
*/

        $order = wc_get_order($order->ID);
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product()->get_data();

            // if we got shipped orders from database
            if (!empty($itemsShipped)) {
                if ((int)$itemsShipped[$product['sku']] < (int) $itemsFromAramex[$product['sku']]) {
                    //we save a difference
                    $items[] = [$product['sku'], (int) $itemsFromAramex[$product['sku']] - (int) $itemsShipped[$product['sku']]];
                }
            } else {
                // if we din`t get shipped orders from database than we save data from Optilog directly
                $items[] = [ $product['sku'], (int) $itemsFromAramex[$product['sku']]];
            }
        }

        $string ="";
        $tracking_number =  $tracking_number ? $tracking_number: rand(100, 1000);
        foreach ($items as  $value) {
            $string  .= "/" . implode(' - ', $value). " pcs.";
        }
        $string  = $string ."/";
        $string  = "Issued iteems: " . $string . " Tracking number: <a href='https://www.aramex.com/track/results?mode=0&ShipmentNumber=". $tracking_number. "' target='_blank' >" . $tracking_number. "</a>";
        // if we have something to save
        if (!empty($items)) {
            $this->saveCommentAndStatus($order->get_id(), $string, 'wc-issued_aramex');
            $this->sendEmail($order, $tracking_number);
        }
    }
    
    /**
     * Send email
     *
     * @param object $order Object of order
     * @param string $tracking_number Tracking number
     * @return void
     */
    private function sendEmail($order, $tracking_number)
    {
        $settings = Aramex_Optilog_Helper::getAccountDetails();
        if ($settings['email'] != 1) {
            return false;
        }
        /* sending mail */
        global $woocommerce;
        $mailer = $woocommerce->mailer();
        $message_body = sprintf(__('<p>Dear <b>%s</b> </p>'), $order->shipping_first_name . " " . $order->shipping_last_name);
        $message_body .= sprintf(__('<p>Your order ID is #%s </p>'), $order->ID);
        $message_body .= sprintf(__('<p>Created Tracking number: %s </p>'), $tracking_number);
        $message_body .= __("<p>You can track shipment on <a href='https://www.aramex.com/track/results?mode=0&ShipmentNumber=". $tracking_number. "' target='_blank' >" . $tracking_number. "</a> </p>");
        $message_body .= __('<p>If you have any questions, please feel free to contact us <b>' . get_option('admin_email') .' </b> </p>',
                            'optilog');
        $message = $mailer->wrap_message(
        // Message head and message body.
        sprintf(__('Aramex shipment #%s created', 'optilog'), $tracking_number), $message_body);
            // Cliente email
        $emailsTo = $order->shipping_email;
        $mailheader = array();
        try {
            $mailer->send($emailsTo, sprintf(__('Aramex shipment #%s created', 'aramex'), $tracking_number), $message, $mailheader);
        } catch (Exception $ex) {
            aramex_errors()->add('error', $ex->getMessage());
        }
    }
    /**
     * Complete order
     *
     * @param object $order Object of order
     * @param string $tracking_number Tracking number
     * @param array $itemsShipped Already shipped items
     * @return void
     */
    private function completeOrder($order, $tracking_number, $itemsShipped)
    {
        $order = wc_get_order($order->ID);
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product()->get_data();

            // if we got shipped orders from database
            if (!empty($itemsShipped)) {
                $items[] = [$product['sku'], (int) $itemsShipped[$product['sku']]];
            }
        }

        $string ="";
        $tracking_number =  $tracking_number ? $tracking_number: rand(100, 1000);
        foreach ($items as  $value) {
            $string  .= "/" . implode(' - ', $value). " pcs.";
        }
        $string  = $string ."/";
        $string  = "Issued iteems: " . $string . " Tracking number: <a href='https://www.aramex.com/track/results?mode=0&ShipmentNumber=". $tracking_number. "' target='_blank' >" . $tracking_number. "</a>";
        // if we have something to save
        if (!empty($items)) {
            $this->saveCommentAndStatus($order->get_id(), $string, 'completed');
            $this->sendEmail($order, $tracking_number);
        }
    }
    /**
     * Make request to Optilog
     *
     * @return void
     */
    protected function makeRequestToOptilog()
    {
        $this->temporary = [];
            
        $settings = Aramex_Optilog_Helper::getAccountDetails();
        if ($settings['levelUpdate'] != 1) {
            return false;
        }
        $skus = [];
        $toSave = [];
        $statuses = [];

        $args = array(
               'post_type' => 'product',
               'posts_per_page' => -1,
           );
        $products = get_posts($args);
        if (!empty($products)) {
            foreach ($products as $product):
        $product_s = wc_get_product($product->ID);
            if ($product_s->get_type() == 'variable') {
                $args = array(
                'post_parent' => $product->ID,
                'post_type'   => 'product_variation',
                'numberposts' => -1,
            );
                $variations = $product_s->get_available_variations();
                foreach ($variations as $variant) {
                    $variation_id = $variant['variation_id'];
                    $variation_obj = new WC_Product_variation($variation_id);
                    $stock = $variation_obj->get_stock_quantity();
                    $skus[] = $variation_obj->get_sku();
                    $this->temporary[$variation_obj->get_sku()] = ["id" =>$variation_obj->get_id(), "quantity" =>$stock ];
                }
            } else {
                $skus[] = $product_s->get_sku();
                $stock = $product_s->get_stock_quantity();
                $this->temporary[$product_s->get_sku()] = ["id" =>$product_s->get_id(), "quantity" =>$stock ];
            }
            endforeach;
        }
        if (count($skus) > 1500) {
            $parts = (int) ceil(count($skus) / 1500);
            $arraySkus = Aramex_Optilog_Helper::partition($skus, $parts);
            foreach ($arraySkus as $sku) {
                $statuses[] = $this->processWsdlLevel($sku);
            }
        } else {
            $statuses[] = $this->processWsdlLevel($skus);
        }

        if (!empty($statuses)) {
            foreach ($statuses as $status) {
                if (isset($status['success'])) {
                    foreach ($status as $statusInner) {
                        foreach ($statusInner as $statusInnerInner) {
                            $toSave[$this->temporary[$statusInnerInner['SKU']]['id']] = [
                                "Available" =>$statusInnerInner['Available'],
                                "Sku" =>$statusInnerInner['SKU'],
                                ];
                        }
                    }
                }
            }
        }
        $this->saveLevel($toSave);
    }

    /**
     * Get stock level
     *
     * @param array Skus of products
     * @return array  Result from Optilog
     */
    private function processWsdlLevel($skus)
    {
        $response = [];
        if (count($skus > 0)) {
            $params = array(
                'Request' =>
                array(
                    'AccountDetails' => Aramex_Optilog_Helper::getAccountDetails(),
                    'SKUs' => $skus
                )
            );
            try {
                $soapClient = new SoapClient(Aramex_Optilog_Helper::getPath() . 'OptilogAPI.WSDL');
                $results = $soapClient->GetStock($params);
                if ($results->GetStockResult->HasErrors) {
                    $response['error'] = '' . $results->GetStockResult->ErrorDescription;
                } else {
                    $message = array();
                    foreach ($results->GetStockResult->ItemsStock as $key => $item) {
                        if (count($item) == 1) {
                            $message[$key]['Available'] = $item->Available;
                            $message[$key]['SKU'] = $item->SKU;
                        } else {
                            foreach ($item as $key => $item1) {
                                $message[$key]['Available'] = $item1->Available;
                                $message[$key]['SKU'] = $item1->SKU;
                            }
                        }
                    }
                    $response['success'] = $message;
                }
            } catch (Exception $e) {
                $response['error'] = $e->getMessage();
            }
            return $response;
        }
    }

    /**
     * Save stock level to database
     *
     * @param array Product id and sku
     * @return void
     */
    private function saveLevel($toSave)
    {
        foreach ($toSave as $item) {
            $beforeOptilog = $this->temporary[$item['Sku']];
            if ($beforeOptilog['quantity'] != $item['Available']) {
                wc_update_product_stock($beforeOptilog['id'], $item['Available'], 'set');
            }
        }
    }
}
