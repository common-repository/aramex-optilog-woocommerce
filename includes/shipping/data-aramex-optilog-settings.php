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

return array(
    'enabled' => array(
        'title' => __('Enable', 'optilog'),
        'type' => 'checkbox',
        'description' => __('Enable Optilog shipping', 'optilog'),
        'default' => 'yes'
    ),
    'freight' => array(
        'title' => __('Client information', 'optilog'),
        'type' => 'title',
    ),
    'accountEntity' => array(
        'title' => __('Account Entity', 'optilog'),
        'type' => 'text',
    ),
    'accountNumber' => array(
        'title' => __('Account Number', 'optilog'),
        'type' => 'text',
    ),
    'accountPin' => array(
        'title' => __('Account Pin', 'optilog'),
        'type' => 'text',
    ),
    'siteCode' => array(
        'title' => __('Site Code', 'optilog'),
        'type' => 'text',
    ),
    'autoMode' => array(
        'title' => __('Auto Mode', 'optilog'),
        'type' => 'select',
        'options' => array(
            '0' => __('No', 'optilog'),
            '1' => __('Yes', 'optilog'),
        )
    ),
    'levelUpdate' => array(
        'title' => __('Stock level update', 'optilog'),
        'type' => 'select',
        'options' => array(
            '0' => __('No', 'optilog'),
            '1' => __('Yes', 'optilog'),
        )
    ),
    'delay' => array(
        'title' => __('Delay for Auto Mode', 'optilog'),
        'type' => 'text',
        'default' => '5',
        'description' => __('Delay in min.', 'optilog'),
    ),
    'email' => array(
        'title' => __('Inform customer by e-mail about shipment', 'optilog'),
        'type' => 'select',
        'options' => array(
            '0' => __('No', 'optilog'),
            '1' => __('Yes', 'optilog'),
        )
    )

);
