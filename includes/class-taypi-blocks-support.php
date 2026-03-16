<?php
/**
 * WooCommerce Checkout Blocks — Soporte TAYPI
 *
 * @package Taypi_WooCommerce
 */

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Taypi_Blocks_Support extends AbstractPaymentMethodType
{
    protected $name = 'taypi';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_taypi_settings', []);
    }

    public function is_active()
    {
        return ! empty($this->get_setting('enabled')) && $this->get_setting('enabled') === 'yes';
    }

    public function get_payment_method_script_handles()
    {
        $asset_url = TAYPI_WC_PLUGIN_URL . 'assets/js/taypi-blocks.js';

        wp_register_script(
            'taypi-blocks',
            $asset_url,
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'],
            TAYPI_WC_VERSION,
            true
        );

        // Registrar checkout.js de TAYPI como dependencia disponible
        $environment = $this->get_setting('environment', 'sandbox');
        if ($environment === 'production') {
            $base_url = 'https://app.taypi.pe';
        } elseif ($environment === 'custom') {
            $custom = $this->get_setting('custom_url', '');
            $base_url = ! empty($custom) ? rtrim($custom, '/') : 'https://sandbox.taypi.pe';
        } else {
            $base_url = 'https://sandbox.taypi.pe';
        }

        wp_register_script(
            'taypi-checkout-sdk',
            $base_url . '/v1/checkout.js',
            [],
            null,
            true
        );

        wp_enqueue_script('taypi-checkout-sdk');

        return ['taypi-blocks'];
    }

    public function get_payment_method_data()
    {
        $environment = $this->get_setting('environment', 'sandbox');
        $key_prefix = $environment === 'production' ? 'live' : ($environment === 'custom' ? 'custom' : 'test');

        require_once TAYPI_WC_PLUGIN_DIR . 'includes/taypi-wallet-logos.php';
        $logos = [];
        foreach (taypi_get_wallet_logos() as $key => $wallet) {
            if (! empty($wallet['src'])) {
                $logos[] = ['name' => $wallet['name'], 'src' => $wallet['src']];
            }
        }

        return [
            'title'           => $this->get_setting('title', 'Pago QR — Yape, Plin y más'),
            'description'     => $this->get_setting('description', 'Paga escaneando un código QR desde Yape, Plin o cualquier app bancaria.'),
            'supports'        => ['products'],
            'public_key'      => $this->get_setting($key_prefix . '_public_key', ''),
            'environment'     => $environment,
            'logo_url'        => TAYPI_WC_PLUGIN_URL . 'assets/images/taypi-logo.png',
            'wallets'         => $logos,
            'is_sandbox'      => $environment !== 'production',
            'mark_paid_nonce' => wp_create_nonce('taypi_mark_paid'),
            'debug'           => $this->get_setting('debug', 'no') === 'yes',
        ];
    }
}
