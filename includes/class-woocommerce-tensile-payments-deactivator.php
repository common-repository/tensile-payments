<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.tensilepayments.com/
 * @since      1.0.0
 *
 * @package    Woocommerce_Tensile_Payments
 * @subpackage Woocommerce_Tensile_Payments/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Woocommerce_Tensile_Payments
 * @subpackage Woocommerce_Tensile_Payments/includes
 * @author     tensilepayments <admin@tensilepayments.com>
 */
class Woocommerce_Tensile_Payments_Deactivator
{
    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate()
    {
        delete_option('woocommerce_woocommerce_tensile_payments_settings');
    }
}
