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
 * Display template for "Paid" functionality
 *
 * @param boolean $paid Status of order(paid or not)
 * @return string "Paid"  template
 */
function optilog_paid_admin($paid)
{
    $get_userdata = get_userdata(get_current_user_id());
    if (!$get_userdata->allcaps['edit_shop_order'] || !$get_userdata->allcaps['read_shop_order'] || !$get_userdata->allcaps['edit_shop_orders'] || !$get_userdata->allcaps['edit_others_shop_orders'] || !$get_userdata->allcaps['publish_shop_orders'] || !$get_userdata->allcaps['read_private_shop_orders'] || !$get_userdata->allcaps['edit_private_shop_orders'] || !$get_userdata->allcaps['edit_published_shop_orders']) {
        return false;
    }
    ?>
    <div style="clear:both; padding-top:10px;">
        <a  <?php
    if ($paid === true) {
        echo "disabled";
    }

    ?> class=' button-primary ' style='margin-top:15px; margin-left:15px; display:none;' id='optilog_paid'><?php echo esc_html__('Paid and ready for Aramex shipment', 'optilog'); ?> </a>
    </div>
<?php } ?>
<div class="optilog_background" style="display:none;">
    <div class="optilog_bulk">
        <div class="optilog_popup-inner-top">
            <button class='optilogclose primary  button-primary optilog_close' type='button '><?php echo esc_html__('Close', 'optilog'); ?></button>
        </div>
        <div class="clear:both"></div>
        <p class="optilog-middle">
            <img src="<?php echo esc_url(plugins_url() . '/aramex-optilog-woocommerce/assets/img/preloader.gif'); ?>"
                 alt="<?php echo esc_html__('Please wait...', 'optilog'); ?>"
                 title="<?php echo esc_html__('Please wait...', 'optilog'); ?>"
                 /> 
        </p>
        <p class="optilog-middle"><?php echo esc_html__('Please wait...', 'optilog'); ?></p>
    </div>
</div>
<script type="text/javascript">
    jQuery.noConflict();
    (function ($) {

        $(document).ready(function () {
            $("#optilog_paid").insertBefore($("#order_data"));
            $("#optilog_paid").css("display", "inline-block");
            $("#optilog_paid").click(function () {
                var attr = $(this).attr('disabled');
                if (typeof attr !== typeof undefined && attr !== false) {
                    return false;
                }
                $(".aramex_loader").css("display", "none");
                $(".order_in_background").fadeIn(500);
                $(".aramex_bulk").fadeIn(500);
                optilogsend();
            });

            $('.optilogclose').click(function () {
                optilogclose();
            });

        });

        function optilogclose() {
            $(".optilog_background").fadeOut(500);
            $(".optilog_bulk").fadeOut(500);
        }

        function optilogopen() {
            $(".aramex_loader").css("display", "none");
            $(".optilog_background").fadeIn(500);
            $(".optilog_bulk").fadeIn(500);
        }
        function optiologredirect() {
                window.location.reload(true);
         }
        function optilogsend() {
            var _wpnonce = "<?php echo esc_js(wp_create_nonce('aramex-optilog-paid' . wp_get_current_user()->user_email)); ?>";
            $('.optilog_bulk p.optilog_message').remove();
            optilogopen();

            var postData = {
                action: 'the_aramex_optilog_paid',
                id: '<?php echo esc_js(sanitize_text_field($_GET['post'])); ?>',
                _wpnonce: _wpnonce
            };

            jQuery.post(ajaxurl, postData, function(request) {
                optilogclose();
                $("#optilog_paid").attr("disabled", true);
                alert("Success!");
                optiologredirect();
            });
        }
    })(jQuery);
</script>
