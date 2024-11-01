<?php

defined('ABSPATH') or exit;

add_action("wp_ajax_tensile_create_woo_order", "tensile_create_woo_order");

add_action("wp_ajax_nopriv_tensile_create_woo_order", "tensile_create_woo_order");

function tensile_create_woo_order()
{

    global $woocommerce;
    $loaderimage = CWEB_WS_PATH_TENSILE . 'public/images/loading.gif';

    $form_data = wc_clean($_POST['form_data']);
    $ship_to_diff_add_checked = wc_clean($_POST['ship_to_diff_add_checked']);

    $cart = WC()->cart;
    $checkout = WC()->checkout();
    $order_id = $checkout->create_order(array());
    $order = wc_get_order($order_id);

    update_post_meta($order_id, '_customer_user', get_current_user_id());
    update_post_meta($order_id, '_payment_method', 'woocommerce_tensile_payments');
    update_post_meta($order_id, 'cstm_ship_to_diff_add_checked', $ship_to_diff_add_checked);

    $woocommerce_process_checkout_nonce = $form_data['woocommerce-process-checkout-nonce'];
    $billing_first_name = sanitize_text_field(trim($form_data['billing_first_name']));

    /* add addresses */
    $billing_address = array(
        'first_name' => $billing_first_name,
        'last_name' => $form_data['billing_last_name'],
        'company' => $form_data['billing_company'],
        'email' => $form_data['billing_email'],
        'phone' => $form_data['billing_phone'],
        'address_1' => $form_data['billing_address_1'],
        'address_2' => $form_data['billing_address_2'],
        'city' => $form_data['billing_city'],
        'state' => $form_data['billing_state'],
        'postcode' => $form_data['billing_postcode'],
        'country' => $form_data['billing_country'],
    );

    $shipping_address = array(
        'first_name' => $form_data['shipping_first_name'],
        'last_name' => $form_data['shipping_last_name'],
        'company' => $form_data['shipping_company'],
        'email' => $form_data['billing_email'],
        'phone' => $form_data['shipping_phone'],
        'address_1' => $form_data['shipping_address_1'],
        'address_2' => $form_data['shipping_address_2'],
        'city' => $form_data['shipping_city'],
        'state' => $form_data['shipping_state'],
        'postcode' => $form_data['shipping_postcode'],
        'country' => $form_data['shipping_country'],
    );

    $order->set_address($billing_address, 'billing');

    if ($ship_to_diff_add_checked == "notchecked") {
        $order->set_address($billing_address, 'shipping');
    } else {
        $order->set_address($shipping_address, 'shipping');
    }

    /* add addresses // */
    /* for shipping charges */
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    // Loop through shipping packages from WC_Session (They can be multiple in some cases)
    foreach (WC()->cart->get_shipping_packages() as $package_id => $package) {
        // Check if a shipping for the current package exist
        if (WC()->session->__isset('shipping_for_package_' . $package_id)) {
            // Loop through shipping rates for the current package
            foreach (WC()->session->get('shipping_for_package_' . $package_id)['rates'] as $shipping_rate_id => $shipping_rate) {
                if (in_array($shipping_rate_id, $chosen_shipping_methods)) {
                    $rate_id = $shipping_rate->get_id(); // same thing that $shipping_rate_id variable (combination of the shipping method and instance ID)
                    $method_id = $shipping_rate->get_method_id(); // The shipping method slug
                    $instance_id = $shipping_rate->get_instance_id(); // The instance ID
                    $label_name = $shipping_rate->get_label(); // The label name of the method
                    $cost = $shipping_rate->get_cost(); // The cost without tax
                    $tax_cost = $shipping_rate->get_shipping_tax(); // The tax cost
                    $taxes = $shipping_rate->get_taxes(); // The taxes details (array)

                    // cstm
                    $item = new WC_Order_Item_Shipping();
                    $item->set_method_title($label_name);
                    $item->set_method_id($method_id); // set an existing Shipping method rate ID
                    $item->set_total($cost); // (optional)
                    $item->calculate_taxes($tax_cost);
                    $order->add_item($item);
                    // cstm
                }
            }
        }
    }

    /* for shipping charges // */
    $order->calculate_totals();
    // keep line below in case we want to change the order status or reinstate
    // $order->update_status( 'wc-on-hold' );

    $process_tensile_first_api_url = process_tensile_first_api($order_id);

    // url returned
    if ($process_tensile_first_api_url) {
        echo json_encode(array('status' => 'ok', 'url' => $process_tensile_first_api_url, 'orderid' => $order_id, 'loaderimg' => $loaderimage, 'message' => 'OK'));
    } else {
        echo json_encode(array('status' => '2', 'message' => 'Issue with Tensile apis, Please contact Administrator'));
    }
    exit;
}

add_action("wp_ajax_tensile_cncl_woo_order", "tensile_cncl_woo_order");
add_action("wp_ajax_nopriv_tensile_cncl_woo_order", "tensile_cncl_woo_order");

function tensile_cncl_woo_order()
{
    global $woocommerce;
    $order_id = trim(sanitize_text_field($_POST['this_order_id']));

    if (!empty($order_id)) {
        $order = wc_get_order($order_id);
        $order->update_status('wc-cancelled');

        esc_attr_e("order cancelled");
    }
    exit;
}

add_action('wp_footer', 'tensile_checkout_script');

function tensile_checkout_script()
{
    if (is_checkout()) {

        ?>

					<input type="hidden" name="tensile_model_order_id" value="" class="tensile_model_order_id">

					<div id="tensile-popup-background"></div>
					<div class="modal" id="tensilemodel" role="dialog">
						<div id="tensile-popup">
							<!-- here will be iframe -->
						</div>
						<div id="tensile-close-popup-button" data-dismiss="modal">✕</div>
					</div>

				<?php

    }
}

add_action('wp_head', 'tensile_include_ajax_url');

function tensile_include_ajax_url()
{
    $adminurl = esc_url(admin_url('admin-ajax.php'));
    echo "<input type='hidden' value='$adminurl' class='customajaxurl' name='customajaxurl'>";
}

add_filter('woocommerce_order_button_html', 'tensile_remove_place_order_button_for_specific_payments');

function tensile_remove_place_order_button_for_specific_payments($button)
{
    // HERE define your targeted payment(s) method(s) in the array
    $loaderimage = CWEB_WS_PATH_TENSILE . 'public/images/loading.gif';
    $targeted_payments_methods = array('woocommerce_tensile_payments');
    $chosen_payment_method = WC()->session->get('chosen_payment_method'); // The chosen payment

    // For matched payment(s) method(s), we remove place order button (on checkout page)
    if (in_array($chosen_payment_method, $targeted_payments_methods) && !is_wc_endpoint_url()) {
        $button = '<a href="javascript:void(0)" class="button alt tensileplaceorderbtn"> Place order <img src="' . $loaderimage . '" class="tensile_btn_loader"> </a>';
    }
    return $button;
}

add_action('woocommerce_thankyou', 'tensile_order_status_to_processing_on_thankyoupage', 4);

function tensile_order_status_to_processing_on_thankyoupage($order_id)
{
    global $woocommerce;

    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    $woocommerce->cart->empty_cart();
    $order->update_status('processing');
}

function tensile_write_log($log)
{
    if (is_array($log) || is_object($log)) {
        error_log(print_r($log, true));
    } else {
        error_log($log);
    }
}
