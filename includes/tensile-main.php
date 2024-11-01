<?php

add_filter('woocommerce_payment_gateways', 'wc_wtp_add_to_gateways');
function wc_wtp_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Woocommerce_Tensile_Payments';
    return $gateways;
}

add_action('plugins_loaded', 'wc_tensile_payments_gateway_init', 11);
function wc_tensile_payments_gateway_init()
{
    class WC_Woocommerce_Tensile_Payments extends WC_Payment_Gateway
    {
        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {
            $this->id = 'woocommerce_tensile_payments';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = __('Tensile', 'wc-tensile-payments');
            $this->method_description = __('Lower your payment costs by allowing customers to pay with their bank accounts while also giving to causes you and your customers care about.', 'wc-tensile-payments');
            $this->supports = array(
                'products',
                'refunds',
            );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            $this->api_endpoint = $this->get_option('api_endpoint');
            $this->checkout_app_url = $this->get_option('checkout_app_url');
            $this->sandbox_api_endpoint = $this->get_option('sandbox_api_endpoint');
            $this->sandbox_checkout_app_url = $this->get_option('sandbox_checkout_app_url');
            $this->testmode = $this->get_option('testmode');
            $this->sandbox_client_id = $this->get_option('sandbox_client_id');
            $this->sandbox_client_secret = $this->get_option('sandbox_client_secret');
            $this->client_id = $this->get_option('client_id');
            $this->client_secret = $this->get_option('client_secret');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            // Note: payment_scripts currently blank
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = apply_filters('wc_tensile_payments_form_fields', array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'wc-tensile-payments'),
                    'type' => 'checkbox',
                    'label' => __('Enable Tensile Payments', 'wc-tensile-payments'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Title', 'wc-tensile-payments'),
                    'type' => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-tensile-payments'),
                    'default' => __('Help save the earth with Tensile! <a href="https://www.tensilepayments.com/what-is-tensile" style="font-size:70%;padding:8px;text-decoration:none" target="_blank">What is Tensile?</a>', 'wc-tensile-payments'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'wc-tensile-payments'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'wc-tensile-payments'),
                    'default' => __('Tensile uses savings from credit card processing fees to offset emissions associated with your shipment at no additional cost. Securely link and pay in under a minute!', 'wc-tensile-payments'),
                    'desc_tip' => true,
                ),
                'instructions' => array(
                    'title' => __('Instructions', 'wc-tensile-payments'),
                    'type' => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page and emails.', 'wc-tensile-payments'),
                    'default' => '',
                    'desc_tip' => true,
                ),

                'testmode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'sandbox_api_endpoint' => array(
                    'title' => 'Sandbox API Endpoint',
                    'type' => 'text',
                    'default' => 'https://api-gateway-east.sandbox.tensilepayments.com',
                ),
                'sandbox_checkout_app_url' => array(
                    'title' => 'Sandbox Checkout App Url',
                    'type' => 'text',
                    'default' => 'https://checkout.sandbox.tensilepayments.com/signup/payment',
                ),
                'api_endpoint' => array(
                    'title' => 'API Endpoint',
                    'type' => 'text',
                    'default' => 'https://api-gateway-east.tensilepayments.com',
                ),
                'checkout_app_url' => array(
                    'title' => 'Checkout App Url',
                    'type' => 'text',
                    'default' => 'https://checkout.tensilepayments.com/signup/payment',
                ),
                'sandbox_client_id' => array(
                    'title' => 'Sandbox Client ID',
                    'type' => 'text',
                    'default' => '',
                ),
                'sandbox_client_secret' => array(
                    'title' => 'Sandbox Client Secret',
                    'type' => 'text',
                    'default' => '',
                ),
                'client_id' => array(
                    'title' => 'Live Client ID',
                    'type' => 'text',
                    'default' => '',
                ),
                'client_secret' => array(
                    'title' => 'Live Client Secret',
                    'type' => 'text',
                    'default' => '',
                ),
            ));
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page($order_id)
        {
            global $woocommerce;

            if (!$order_id) {
                return;
            }
            $order = wc_get_order($order_id);
            $order->reduce_order_stock();
            $woocommerce->cart->empty_cart();
            $order->update_status('wc-processing');
        }

        /**
         * process refund
         **/
        // Note: Used to process refund on Edit Order page
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            global $wpdb;
            global $woocommerce;

            $tensiledata = get_option('woocommerce_woocommerce_tensile_payments_settings');

            if ($tensiledata['testmode'] == 'yes') {
                $clientid = $tensiledata['sandbox_client_id'];
                $clientsecret = $tensiledata['sandbox_client_secret'];
                $api_endpoint = $tensiledata['sandbox_api_endpoint'];
                $checkout_app_url = $tensiledata['sandbox_checkout_app_url'];
            } else {
                $clientid = $tensiledata['client_id'];
                $clientsecret = $tensiledata['client_secret'];
                $api_endpoint = $tensiledata['api_endpoint'];
                $checkout_app_url = $tensiledata['checkout_app_url'];
            }

            $payment_id = get_post_meta($order_id, 'transaction_id', true);
            $url = "$api_endpoint/payments/$payment_id/refund";
            $post_fields = '{
							"amount" : ' . $amount . ',
							"reason": "' . $reason . '",
							"platform_name": "Woocommerce",
							"platform_order_id": "' . $order_id . '"
						}';
            $headers = array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json;v=2',
                'client_id' => $clientid,
                'client_secret' => $clientsecret,
            );
            $response = wp_safe_remote_post($url, array(
                'method' => 'POST',
                'headers' => $headers,
                'httpversion' => '1.0',
                'sslverify' => false,
                'body' => $post_fields,
            ));
            $response_body = $response['body'];
            tensile_write_log("Sending request to $url");
            $res = json_decode($response_body, true);
            tensile_write_log("Sending request to $url");
            tensile_write_log("Response");
            tensile_write_log($response_body);

            if ($res['status'] == 'ok') {
                return true;
            } else {
                $error_message = $res['debugMessage'];
                throw new Exception(__($error_message, 'woocommerce'));
                return false;
            }
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {
            if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && ($order->has_status('processing') || $order->has_status('on-hold'))) {
                echo wpautop(wptexturize(esc_attr($this->instructions))) . PHP_EOL;
            }
        }

        /* for the popup */
        public function payment_scripts()
        {

        }
        /* for the popup // */

        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            tensile_write_log("processing payment");
            global $wpdb;
            global $woocommerce;
            $tensiledata = get_option('woocommerce_woocommerce_tensile_payments_settings');
            if ($tensiledata['testmode'] == 'yes') {
                $clientid = $tensiledata['sandbox_client_id'];
                $clientsecret = $tensiledata['sandbox_client_secret'];
                $api_endpoint = $tensiledata['sandbox_api_endpoint'];
                $checkout_app_url = $tensiledata['sandbox_checkout_app_url'];
            } else {
                $clientid = $tensiledata['client_id'];
                $clientsecret = $tensiledata['client_secret'];
                $api_endpoint = $tensiledata['api_endpoint'];
                $checkout_app_url = $tensiledata['checkout_app_url'];
            }

            $order = wc_get_order($order_id);
            $subtotal = number_format($order->get_subtotal(), 2);
            $total = number_format($order->get_total(), 2);
            $tax1 = number_format($order->get_total_tax(), 2);
            $shipping_fee = number_format($order->get_total_shipping(), 2);
            $oitems = '[';
            // Get and Loop Over Order Items

            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $product = $item->get_product();
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                $subtotal1 = $item->get_subtotal();
                $total1 = $item->get_total();
                $tax = $item->get_subtotal_tax();
                $taxclass = $item->get_tax_class();
                $taxstat = $item->get_tax_status();
                $allmeta = $item->get_meta_data();
                $somemeta = $item->get_meta('_whatever', true);
                $product_type = $item->get_type();
                $oitems .= '{';
                $oitems .= '"name":"' . $product_name . '",';
                $oitems .= '"quantity":' . $quantity . ',';
                $oitems .= '"price":' . $total1;
                $oitems .= '},';
            }
            $oitems = rtrim($oitems, ",");
            $oitems .= ']';
            $success_redirect_url = site_url() . '/checkout/order-received/' . $order->get_id() . '/?key=' . $order->get_order_key();
            $cancel_redirect_url = site_url() . '/checkout';

            $url = $api_endpoint . '/payments';
            if ($order->get_shipping_address_1()) {
                tensile_write_log("Shipping is required");
                $phone = $order->get_shipping_phone();
                if ($phone == "") {
                    $phone = $order->get_billing_phone();
                }
                $post_fields = '{
								"subtotal" : ' . $subtotal . ',
								"tax":' . $tax1 . ',
								"shipping_fee": ' . $shipping_fee . ',
								"total" : ' . $total . ',
								"items" : ' . $oitems . ',
								"shipping_required" : true,
								"payment_address_editable" : false,
								"redirect_uri_success" : "' . $success_redirect_url . '",
								"redirect_uri_cancel" : "' . $cancel_redirect_url . '",
								"payment_type": "one-off",
								"platform_name":"Woocommerce",
								"platform_order_id":"' . $order_id . '",
								"shipping_address": {
									"first_name": "' . $order->get_shipping_first_name() . '",
									"last_name": "' . $order->get_shipping_last_name() . '",
									"address_line_1": "' . $order->get_shipping_address_1() . '",
									"city": "' . $order->get_shipping_city() . '",
									"state": "' . $order->get_shipping_state() . '",
									"country": "' . $order->get_shipping_country() . '",
									"zip": "' . $order->get_shipping_postcode() . '",
									"phone_number": "' . $phone . '"
								},
								"user_info": {
									"first_name": "' . $order->get_billing_first_name() . '",
									"last_name": "' . $order->get_billing_last_name() . '",
									"email": "' . $order->get_billing_email() . '",
									"phone_number": "' . $order->get_billing_phone() . '"
								}
							}';
            } else {
                tensile_write_log("Shipping not required");
                $post_fields = '{
								"subtotal" : ' . $subtotal . ',
								"tax":' . $tax1 . ',
								"total" : ' . $total . ',
								"items" : ' . $oitems . ',
								"shipping_required" : false,
								"redirect_uri_success" : "' . $success_redirect_url . '",
								"redirect_uri_cancel" : "' . $cancel_redirect_url . '",
								"payment_type": "one-off",
								"platform_name":"Woocommerce",
								"platform_order_id":"' . $order_id . '",
								"user_info": {
									"first_name": "' . $order->get_billing_first_name() . '",
									"last_name": "' . $order->get_billing_last_name() . '",
									"email": "' . $order->get_billing_email() . '",
									"phone_number": "' . $order->get_billing_phone() . '"
								}
							}';
            }

            $headers = array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json;v=2',
                'client_id' => $clientid,
                'client_secret' => $clientsecret,
            );
            $response = wp_safe_remote_post($url, array(
                'method' => 'POST',
                'headers' => $headers,
                'httpversion' => '1.0',
                'sslverify' => false,
                'body' => $post_fields,
            )
            );

            $response_body = $response['body'];
            tensile_write_log("Sending request to $url");
            $res = json_decode($response_body, true);

            tensile_write_log("Sending request to $url");

            tensile_write_log("Response");
            tensile_write_log($response_body);
            if (array_key_exists('payment_id', $res)) {
                /**
                 * comment out line below for order status in case we need to reinstate,
                 * but don't think process_payment function is used anywhere anyway
                 */
                // $order->update_status( 'wc-on-hold' );
                $rurl = $checkout_app_url . '/' . $res['payment_id'];
                update_post_meta($order_id, 'transaction_id', $res['payment_id']);
                return array(
                    'result' => 'success',
                    'redirect' => $rurl,
                );
            }
        }
    } // end \WC_Gateway_Offline class

}

/**
 * Display field value on the order edit page
 */
add_action('woocommerce_admin_order_data_after_billing_address', 'display_transactionid_admin_order_meta', 10, 1);

function display_transactionid_admin_order_meta($order)
{
    echo '<p><strong>' . __('Transaction Id') . ':</strong> <br/>' . esc_attr(get_post_meta($order->get_id(), 'transaction_id', true)) . '</p>';
}

function process_tensile_first_api($order_id)
{
    tensile_write_log("processing payment");
    global $wpdb;
    global $woocommerce;
    $tensiledata = get_option('woocommerce_woocommerce_tensile_payments_settings');
    if ($tensiledata['testmode'] == 'yes') {
        $clientid = $tensiledata['sandbox_client_id'];
        $clientsecret = $tensiledata['sandbox_client_secret'];
        $api_endpoint = $tensiledata['sandbox_api_endpoint'];
        $checkout_app_url = $tensiledata['sandbox_checkout_app_url'];
    } else {
        $clientid = $tensiledata['client_id'];
        $clientsecret = $tensiledata['client_secret'];
        $api_endpoint = $tensiledata['api_endpoint'];
        $checkout_app_url = $tensiledata['checkout_app_url'];
    }

    $order = wc_get_order($order_id);
    $subtotal = number_format($order->get_subtotal(), 2);
    $total = number_format($order->get_total(), 2);
    $tax1 = number_format($order->get_total_tax(), 2);
    $shipping_fee = number_format($order->get_total_shipping(), 2);

    $cstm_ship_to_diff_add_checked = get_post_meta($order_id, 'cstm_ship_to_diff_add_checked', true);

    $oitems = '[';

    // Get and Loop Over Order Items
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $product = $item->get_product();
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();
        $subtotal1 = $item->get_subtotal();
        $total1 = $item->get_total();
        $tax = $item->get_subtotal_tax();
        $taxclass = $item->get_tax_class();
        $taxstat = $item->get_tax_status();
        $allmeta = $item->get_meta_data();
        $somemeta = $item->get_meta('_whatever', true);
        $product_type = $item->get_type();
        $oitems .= '{';
        $oitems .= '"name":"' . $product_name . '",';
        $oitems .= '"quantity":' . $quantity . ',';
        $oitems .= '"price":' . $total1;
        $oitems .= '},';
    }
    $oitems = rtrim($oitems, ",");
    $oitems .= ']';
    $success_redirect_url = site_url() . '/checkout/order-received/' . $order->get_id() . '/?key=' . $order->get_order_key();
    $cancel_redirect_url = site_url() . '/checkout';
    $url = $api_endpoint . '/payments';

    if ($order->get_shipping_address_1()) {
        $shipping_required = 'true';
        tensile_write_log("Shipping is required");
    } else {
        /**
         * Keep this block for now until we want shipping required
         * to be optional to account for digital goods and services.
         */
        $shipping_required = 'true';
    }

    $shipping_phone = $order->get_shipping_phone();

    if ($shipping_phone == "") {
        $shipping_phone = $order->get_billing_phone();
    }

    // ship to differet address checked
    if ($cstm_ship_to_diff_add_checked == 'checked') {
        $billing_shipping_first_name = $order->get_shipping_first_name();
        $billing_shipping_last_name = $order->get_shipping_last_name();
        $billing_shipping_address_line_1 = $order->get_shipping_address_1();
        $billing_shipping_address_line_2 = $order->get_shipping_address_2();
        $billing_shipping_city = $order->get_shipping_city();
        $billing_shipping_state = $order->get_shipping_state();
        $billing_shipping_country = $order->get_shipping_country();
        $billing_shipping_zip = $order->get_shipping_postcode();
        $billing_shipping_phone_number = $shipping_phone;
    } else {
        $billing_shipping_first_name = $order->get_billing_first_name();
        $billing_shipping_last_name = $order->get_billing_last_name();
        $billing_shipping_address_line_1 = $order->get_billing_address_1();
        $billing_shipping_address_line_2 = $order->get_billing_address_2();
        $billing_shipping_city = $order->get_billing_city();
        $billing_shipping_state = $order->get_billing_state();
        $billing_shipping_country = $order->get_billing_country();
        $billing_shipping_zip = $order->get_billing_postcode();
        $billing_shipping_phone_number = $order->get_billing_phone();
    }

    $post_fields = '{
			"subtotal" : ' . $subtotal . ',
			"tax":' . $tax1 . ',
			"shipping_fee": ' . $shipping_fee . ',
			"total" : ' . $total . ',
			"items" : ' . $oitems . ',
			"shipping_required" : ' . $shipping_required . ',
			"payment_address_editable" : false,
			"redirect_uri_success" : "' . $success_redirect_url . '",
			"redirect_uri_cancel" : "' . $cancel_redirect_url . '",
			"payment_type": "one-off",
			"platform_name":"Woocommerce",
			"platform_order_id":"' . $order_id . '",
			"shipping_address": {
				"first_name": "' . $billing_shipping_first_name . '",
				"last_name": "' . $billing_shipping_last_name . '",
				"address_line_1": "' . $billing_shipping_address_line_1 . '",
				"address_line_2": "' . $billing_shipping_address_line_2 . '",
				"city": "' . $billing_shipping_city . '",
				"state": "' . $billing_shipping_state . '",
				"country": "' . $billing_shipping_country . '",
				"zip": "' . $billing_shipping_zip . '",
				"phone_number": "' . $billing_shipping_phone_number . '"
			},
			"user_info": {
				"first_name": "' . $order->get_billing_first_name() . '",
				"last_name": "' . $order->get_billing_last_name() . '",
				"email": "' . $order->get_billing_email() . '",
				"phone_number": "' . $order->get_billing_phone() . '"
			}
		}';

    $headers = array(
        'Content-Type' => 'application/json',
        'Accept' => 'application/json;v=2',
        'client_id' => $clientid,
        'client_secret' => $clientsecret,
    );

    $response = wp_safe_remote_post($url, array(
        'method' => 'POST',
        'headers' => $headers,
        'httpversion' => '1.0',
        'sslverify' => false,
        'body' => $post_fields,
    )
    );

    $response_body = $response['body'];

    tensile_write_log("Sending request to $url");

    $res = json_decode($response_body, true);
    tensile_write_log("Response");
    tensile_write_log($response);

    if (array_key_exists('payment_id', $res)) {
        // keep line below in case we want to change the order status or reinstate
        // $order->update_status( 'wc-on-hold' );
        $rurl = $checkout_app_url . '/' . $res['payment_id'];
        update_post_meta($order_id, 'transaction_id', $res['payment_id']);
        return $rurl;
    } else {
        return false;
    }
}
