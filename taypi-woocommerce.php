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

    // Hook Store API — crea sesión TAYPI y pasa checkout_token al Blocks JS
    // Este hook se ejecuta ANTES de process_payment, así que creamos la sesión aquí
    add_action('woocommerce_rest_checkout_process_payment_with_context', function ($context, $result) {
        if ($context->payment_method !== 'taypi') {
            return;
        }

        taypi_wc_log('=== Store API hook FIRED === payment_method=taypi');

        $order = $context->order;
        $order_id = $order->get_id();

        // Obtener gateway para acceder a configuración y SDK
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (! isset($gateways['taypi'])) {
            taypi_wc_log('Store API hook: gateway taypi not found');
            return;
        }

        $gateway = $gateways['taypi'];
        $environment = $gateway->get_option('environment', 'sandbox');
        $key_prefix = $environment === 'production' ? 'live' : ($environment === 'custom' ? 'custom' : 'test');
        $public_key = $gateway->get_option($key_prefix . '_public_key', '');
        $secret_key = $gateway->get_option($key_prefix . '_secret_key', '');

        if ($environment === 'production') {
            $base_url = 'https://app.taypi.pe';
        } elseif ($environment === 'custom') {
            $custom = $gateway->get_option('custom_url', '');
            $base_url = ! empty($custom) ? rtrim($custom, '/') : 'https://sandbox.taypi.pe';
        } else {
            $base_url = 'https://sandbox.taypi.pe';
        }

        taypi_wc_log('Store API hook: env=' . $environment . ' base_url=' . $base_url . ' order_id=' . $order_id);

        try {
            $client = new \Taypi\Taypi($public_key, $secret_key, ['base_url' => $base_url]);

            $reference = (string) $order_id;
            $amount = number_format((float) $order->get_total(), 2, '.', '');

            taypi_wc_log('Store API hook: creating checkout session amount=' . $amount . ' ref=' . $reference);

            $session = $client->createCheckoutSession([
                'amount'      => $amount,
                'reference'   => $reference,
                'description' => sprintf('Orden #%s — %s', $order->get_order_number(), get_bloginfo('name')),
            ], 'wc-' . $reference);

            $checkout_token = $session['checkout_token'] ?? '';
            taypi_wc_log('Store API hook: checkout_token=' . $checkout_token);

            if (! empty($checkout_token)) {
                $order->update_meta_data('_taypi_checkout_token', $checkout_token);
                $order->update_meta_data('_taypi_environment', $environment);
                $order->save();

                $order->update_status('pending', 'Esperando pago QR via TAYPI.');

                $return_url = $gateway->get_return_url($order);

                $result->set_payment_details([
                    'checkout_token' => $checkout_token,
                    'order_id'       => $order_id,
                    'return_url'     => $return_url,
                ]);
                $result->set_status('success');
                $result->set_redirect_url($return_url);

                taypi_wc_log('Store API hook: set_payment_details OK, return_url=' . $return_url);
            } else {
                taypi_wc_log('Store API hook: empty checkout_token from API');
            }
        } catch (\Taypi\TaypiException $e) {
            taypi_wc_log('Store API hook: TaypiException: ' . $e->getMessage() . ' (' . $e->errorCode . ')');
            $result->set_status('failure');
        } catch (\Exception $e) {
            taypi_wc_log('Store API hook: Exception: ' . $e->getMessage());
            $result->set_status('failure');
        }
    }, 10, 2);
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
 * Log condicional — solo escribe si debug está activo.
 */
function taypi_wc_log(string $message)
{
    $settings = get_option('woocommerce_taypi_settings', []);
    if (! empty($settings['debug']) && $settings['debug'] === 'yes') {
        $logger = wc_get_logger();
        taypi_wc_log($message);
    }
}

/**
 * AJAX: marcar orden como pagada cuando el modal confirma el pago.
 */
add_action('wp_ajax_taypi_mark_paid', 'taypi_ajax_mark_paid');
add_action('wp_ajax_nopriv_taypi_mark_paid', 'taypi_ajax_mark_paid');

function taypi_ajax_mark_paid()
{
    $logger = wc_get_logger();

    if (! wp_verify_nonce($_POST['nonce'] ?? '', 'taypi_mark_paid')) {
        taypi_wc_log('taypi_mark_paid: invalid nonce');
        wp_send_json_error('Nonce inválido');
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    $order = wc_get_order($order_id);

    if (! $order) {
        taypi_wc_log('taypi_mark_paid: order not found: ' . $order_id);
        wp_send_json_error('Orden no encontrada');
    }

    if ($order->is_paid()) {
        taypi_wc_log('taypi_mark_paid: order #' . $order_id . ' already paid');
        wp_send_json_success('Ya pagada');
    }

    $checkout_token = $order->get_meta('_taypi_checkout_token');
    $order->payment_complete($checkout_token);
    $order->add_order_note('Pago confirmado via TAYPI (modal checkout.js).');

    taypi_wc_log('taypi_mark_paid: order #' . $order_id . ' marked as paid');

    wp_send_json_success('Pagada');
}

/**
 * Registrar soporte para Checkout Blocks.
 */
add_action('woocommerce_blocks_loaded', function () {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once TAYPI_WC_PLUGIN_DIR . 'includes/class-taypi-blocks-support.php';

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function ($registry) {
                $registry->register(new Taypi_Blocks_Support());
            }
        );
    }
});

/**
 * Cargar traducciones.
 */
add_action('init', function () {
    load_plugin_textdomain('taypi-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
