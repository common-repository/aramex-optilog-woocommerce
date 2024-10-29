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

if (!class_exists('Aramex_Optilog_Helper')) {

    /**
     * Class Aramex_Helper is a helper
     */
    class Aramex_Optilog_Helper
    {
        /**
         * Path to WSDL file
         *
         * @var string Path to WSDL file
         */
        protected $wsdlBasePath;

        /**
         * Get path to WSDL file
         *
         * @return string Path to WSDL file
         */
        public static function getPath()
        {
            return __DIR__ . '/../../wsdl/';
        }

        /**
         * Get Account Details
         *
         * @return array Account Details
         */
        public static function getAccountDetails()
        {
            $settings = get_option('woocommerce_aramex_optilog_settings');
            return array(
                    'AccountEntity' => $settings['accountEntity'],
                    'AccountNumber' => $settings['accountNumber'],
                    'AccountPin' => $settings['accountPin'],
                    'SiteCode' => $settings['siteCode'],
                    'autoMode' => $settings['autoMode'],
                    'levelUpdate' => $settings['levelUpdate'],
                    'delay' => $settings['delay'],
                    'email' => $settings['email'],
                );
        }
        /**
        * Make parts from array
        * @param Array $list
        * @param int $p
        * @return multitype:multitype:
        */
       public static function partition(array $list, $p)
       {
           $listlen = count($list);
           $partlen = floor($listlen / $p);
           $partrem = $listlen % $p;
           $partition = array();
           $mark = 0;
           for ($px = 0; $px < $p; $px ++) {
               $incr = ($px < $partrem) ? $partlen + 1 : $partlen;
               $partition[$px] = array_slice($list, $mark, $incr);
               $mark += $incr;
           }
           return $partition;
       }
    }
}
