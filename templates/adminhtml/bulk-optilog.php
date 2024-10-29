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

/**
 * Display template for Bulk-Optilog functionality
 *
 * @return string Bulk-Optilog template
 */
function aramex_optilog_display_bulk_in_admin()
{
    $get_userdata = get_userdata(get_current_user_id());
    if (!$get_userdata->allcaps['edit_shop_order'] || !$get_userdata->allcaps['read_shop_order'] || !$get_userdata->allcaps['edit_shop_orders'] || !$get_userdata->allcaps['edit_others_shop_orders']
        || !$get_userdata->allcaps['publish_shop_orders'] || !$get_userdata->allcaps['read_private_shop_orders']
        || !$get_userdata->allcaps['edit_private_shop_orders'] || !$get_userdata->allcaps['edit_published_shop_orders']) {
        return false;
    } ?>

    <div class="optilog_background" style="display:none;">
        <div class="optilog_bulk">
            <div class="optilog_popup-inner-top">
            <button class="optilogclose primary  button-primary optilog_close" type="button "><?php echo esc_html__('Close',
                    'aramex'); ?></button>
</div>

                    <div class="clear:both"></div>
                    <p class="optilog-middle">
                        <img src="<?php echo esc_url(plugins_url() . '/aramex-optilog-woocommerce/assets/img/preloader.gif'); ?>"
                             alt="<?php echo esc_html__('Please wait...', 'optilog'); ?>"
                             title="<?php echo esc_html__('Please wait...', 'optilog'); ?>"
                             /> 
                         </p>
                             <p class="optilog-middle"><?php echo esc_html__('Please wait...', 'optilog'); ?></p>

        <div class="optilog_popup-inner-bottom">
        </div>

        </div>
    </div>

    <script type="text/javascript">
        jQuery.noConflict();
        (function ($) {
            $(document).ready(function () {

                    $('.page-title-action').first().after("<a class=' page-title-action' style='margin-left:15px;' id='bulk_synhronize_optilog_order'><?php echo esc_html__('Bulk Synhronize Optilog Order',
                    'optilog'); ?> </a>");    
                     $('.page-title-action').first().after("<a class=' page-title-action' style='margin-left:15px;' id='create_aramex_optilog_shipment'><?php echo esc_html__('Bulk Submit Optilog Order',
                    'optilog'); ?> </a>");                
            });

            $(document).ready(function () {
            
            $('.optilogclose').click(function () {
                optilogclose();
            });

            $('#create_aramex_optilog_shipment').click(function () {
                var marker = "bulk_submit_optilog_order";
                shipment_optilog_send(marker);
            });

            $('#bulk_synhronize_optilog_order').click(function () {
                var marker = "bulk_synhronize_optilog_order";
                shipment_optilog_send(marker);
            });


            });

            function optiologredirect() {
                window.location.reload(true);
            }

            var selectedItems = [];
            function check_items() {
                $('.iedit input:checked').each(function () {
                        selectedItems.push($(this).val());
                    });
                if (selectedItems.length === 0) {
                    alert("Select orders, please");
                    return false;
                } else {
                    return true;
                }
            }
            
            function optilogclose() {
                $(".optilog_background").fadeOut(500);
                $(".optilog_bulk").fadeOut(500);
            }

            function optilogopen() {
                    $(".aramex_loader").css("display","none");
                    $(".optilog_background").fadeIn(500);
                    $(".optilog_bulk").fadeIn(500);
            }

            function shipment_optilog_send(marker) {
                var check = check_items();
                if (check === false) {
                    return false;
                }

                var _wpnonce = "<?php echo esc_js(wp_create_nonce('aramex-optilog-check' . wp_get_current_user()->user_email)); ?>";
                $('.optilog_bulk p.optilog_message').remove();
                optilogopen();


                var postData = {
                    action: 'the_aramex_optilog_index',
                    selectedOrders: selectedItems,
                    marker: marker,
                    optilog: "optilog",
                    _wpnonce: _wpnonce
                };

                jQuery.post(ajaxurl, postData, function(response) {
                    var message = "";
                    $('.popup-loading').css('display', 'none');
                    console.log(response.error);
                    if (response.error) {
                        $.each(response.error, function (index, value) {
                            message = "<p class='optilog_message' style='color:red;'>" + value + "</p>";
                        });
                    }

                    if (response.success) {
                        $.each(response.success, function (index, value) {
                            message = message + "<p class='optilog_message' style='black: red; text-align:left;'>" + value + "</p>";
                        });
                    }

                    $.each(response.errors, function (index, value) {
                        console.log(value);
                        if (value !== "") {
                            message = message + "<p class='optilog_message' style='color: red; text-align:left;'> Order:  " + value + " is not valid.</p>";
                        }
                    });

                    $('.optilog_bulk p').remove();
                    $('.optilog_bulk img').remove();
                    $('.optilog_bulk').append(message);
                    $(".optilog_close").click(function () {
                        optiologredirect();
                    });
                });

            }
        })(jQuery);
    </script>
<?php 
}  
