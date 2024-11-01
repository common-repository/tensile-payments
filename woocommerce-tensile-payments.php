<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.tensilepayments.com/
 * @since             1.0.0
 * @package           Woocommerce_Tensile_Payments
 *
 * @wordpress-plugin
 * Plugin Name:       Tensile Payments
 * Plugin URI:        https://wordpress.org/plugins/tensile-payments/
 * Description:       Tensile is an alternative payment method that lowers payment processing costs for merchants by up to 80% and allows customers to pay directly with their bank accounts. Customers are able to securely link and pay within your checkout page. You can help maximize your savings with Tensile by providing incentives like carbon offsets or donations to your favorite charity!
 * Version:           1.0.0
 * Author:            tensilepayments
 * Author URI:        https://www.tensilepayments.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-tensile-payments
 * Domain Path:       /languages
 */

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_tensile_payments_gateway_plugin_links');

function wc_tensile_payments_gateway_plugin_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=woocommerce_tensile_payments') . '">' . __('Configure', 'wc-woocommerce-tensile-payments') . '</a>',
    );
    return array_merge($plugin_links, $links);
}

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WOOCOMMERCE_TENSILE_PAYMENTS_VERSION', '1.0.0');
define('CWEB_FS_PATH_TENSILE', plugin_dir_path(__FILE__));
define('CWEB_WS_PATH_TENSILE', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woocommerce-tensile-payments-activator.php
 */
function activate_woocommerce_tensile_payments()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-woocommerce-tensile-payments-activator.php';
    Woocommerce_Tensile_Payments_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woocommerce-tensile-payments-deactivator.php
 */
function deactivate_woocommerce_tensile_payments()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-woocommerce-tensile-payments-deactivator.php';
    Woocommerce_Tensile_Payments_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_woocommerce_tensile_payments');
register_deactivation_hook(__FILE__, 'deactivate_woocommerce_tensile_payments');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-woocommerce-tensile-payments.php';
require plugin_dir_path(__FILE__) . 'includes/functions.php';
require plugin_dir_path(__FILE__) . 'includes/tensile-main.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woocommerce_tensile_payments()
{
    $plugin = new Woocommerce_Tensile_Payments();
    $plugin->run();
}

run_woocommerce_tensile_payments();
