<?php
/**
 * Plugin Name: TAYPI - Pago QR para WooCommerce
 * Plugin URI: https://taypi.pe
 * Description: Acepta pagos QR interoperables con Yape, Plin y cualquier app bancaria conectada a la CCE.
 * Version: 1.0.0
 * Author: NEO TECHNOLOGY PERU E.I.R.L.
 * Author URI: https://neotecperu.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taypi-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.6
 *
 * @package Taypi_WooCommerce
 */

defined('ABSPATH') || exit;

define('TAYPI_WC_VERSION', '1.0.0');
define('TAYPI_WC_PLUGIN_FILE', __FILE__);
define('TAYPI_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TAYPI_WC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Declarar compatibilidad con HPOS (High-Performance Order Storage).
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Verificar que WooCommerce esté activo antes de cargar el gateway.
 */
add_action('plugins_loaded', 'taypi_wc_init', 0);

function taypi_wc_init(): void
{
    if (! class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>TAYPI:</strong> Este plugin requiere WooCommerce activo.</p></div>';
        });
        return;
    }

    // Cargar SDK PHP de TAYPI
    $autoload = TAYPI_WC_PLUGIN_DIR . 'vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    require_once TAYPI_WC_PLUGIN_DIR . 'includes/class-wc-gateway-taypi.php';

    // Registrar gateway
    add_filter('woocommerce_payment_gateways', function (array $methods): array {
        $methods[] = 'WC_Gateway_Taypi';
        return $methods;
    });
}

/**
 * Link "Configurar" en la página de plugins.
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=taypi');
    array_unshift($links, '<a href="' . esc_url($settings_url) . '">Configurar</a>');
    return $links;
});

/**
 * Cargar traducciones.
 */
add_action('init', function () {
    load_plugin_textdomain('taypi-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
