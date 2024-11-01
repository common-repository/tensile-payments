<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.tensilepayments.com/
 * @since      1.0.0
 *
 * @package    Woocommerce_Tensile_Payments
 * @subpackage Woocommerce_Tensile_Payments/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Woocommerce_Tensile_Payments
 * @subpackage Woocommerce_Tensile_Payments/includes
 * @author     tensilepayments <admin@tensilepayments.com>
 */
class Woocommerce_Tensile_Payments_i18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'woocommerce-tensile-payments',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
