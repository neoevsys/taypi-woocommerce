<?php
/**
 * WooCommerce Payment Gateway — TAYPI
 *
 * Usa el SDK taypi/taypi-php para comunicación con el API
 * y checkout.js (CDN) para el modal de pago QR.
 *
 * @package Taypi_WooCommerce
 */

defined('ABSPATH') || exit;

class WC_Gateway_Taypi extends WC_Payment_Gateway
{
    /** @var string Entorno: production, sandbox, custom */
    private $taypi_environment = 'sandbox';

    /** @var string Clave pública TAYPI */
    private $taypi_public_key = '';

    /** @var string Clave secreta TAYPI */
    private $taypi_secret_key = '';

    /** @var string Secret para verificar webhooks */
    private $taypi_webhook_secret = '';

    /** @var string Habilitar logging */
    private $taypi_debug = 'no';

    /** @var object|null Cliente SDK */
    private $taypi_client = null;

    public function __construct()
    {
        $this->id                 = 'taypi';
        $this->icon               = TAYPI_WC_PLUGIN_URL . 'assets/images/taypi-logo.png';
        $this->has_fields         = false;
        $this->method_title       = 'TAYPI - Pago QR';
        $this->method_description = 'Acepta pagos QR interoperables con Yape, Plin y cualquier app bancaria conectada a la CCE.';
        $this->supports           = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title                = $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->taypi_environment    = $this->get_option('environment', 'sandbox');
        $key_prefix = $this->taypi_environment === 'production' ? 'live' : ($this->taypi_environment === 'custom' ? 'custom' : 'test');
        $this->taypi_public_key     = $this->get_option($key_prefix . '_public_key', '');
        $this->taypi_secret_key     = $this->get_option($key_prefix . '_secret_key', '');
        $this->taypi_webhook_secret = $this->get_option('webhook_secret');
        $this->taypi_debug          = $this->get_option('debug');

        // Guardar configuración
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // Scripts del checkout
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);

        // Webhook endpoint: /wc-api/taypi_webhook
        add_action('woocommerce_api_taypi_webhook', [$this, 'handle_webhook']);

        // AJAX para crear sesión de checkout
        add_action('wp_ajax_taypi_create_session', [$this, 'ajax_create_session']);
        add_action('wp_ajax_nopriv_taypi_create_session', [$this, 'ajax_create_session']);

        // JS para ocultar/mostrar campos en admin
        add_action('admin_footer', [$this, 'admin_toggle_fields_js']);
    }

    /**
     * Campos de configuración en WooCommerce > Ajustes > Pagos > TAYPI.
     */
    public function init_form_fields()
    {
        $webhook_url = home_url('/wc-api/taypi_webhook/');

        $this->form_fields = [
            'enabled' => [
                'title'   => 'Activar/Desactivar',
                'type'    => 'checkbox',
                'label'   => 'Activar TAYPI como método de pago',
                'default' => 'no',
            ],
            'title' => [
                'title'       => 'Título',
                'type'        => 'text',
                'description' => 'Nombre del método de pago que ve el cliente.',
                'default'     => 'Pago QR — Yape, Plin y más',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Descripción',
                'type'        => 'textarea',
                'description' => 'Descripción que ve el cliente al elegir este método.',
                'default'     => 'Paga escaneando un código QR desde Yape, Plin o cualquier app bancaria.',
            ],
            'environment' => [
                'title'       => 'Entorno',
                'type'        => 'select',
                'description' => 'Selecciona el entorno de TAYPI. Cada entorno usa sus propias credenciales.',
                'default'     => 'sandbox',
                'options'     => [
                    'production' => 'Producción (app.taypi.pe)',
                    'sandbox'    => 'Sandbox (sandbox.taypi.pe)',
                    'custom'     => 'Personalizado',
                ],
            ],
            'custom_url' => [
                'title'       => 'URL personalizada',
                'type'        => 'text',
                'description' => 'URL base del entorno personalizado (incluir https://).',
                'default'     => '',
            ],
            'section_production' => [
                'title'       => 'Credenciales de Producción',
                'type'        => 'title',
                'description' => 'Claves para el entorno de producción (app.taypi.pe).',
            ],
            'live_public_key' => [
                'title'       => 'Public Key',
                'type'        => 'text',
                'description' => 'Clave pública de producción (taypi_pk_live_...).',
                'default'     => '',
            ],
            'live_secret_key' => [
                'title'       => 'Secret Key',
                'type'        => 'password',
                'description' => 'Clave secreta de producción. Nunca la compartas.',
                'default'     => '',
            ],
            'section_sandbox' => [
                'title'       => 'Credenciales de Sandbox',
                'type'        => 'title',
                'description' => 'Claves para el entorno de pruebas (sandbox.taypi.pe).',
            ],
            'test_public_key' => [
                'title'       => 'Public Key',
                'type'        => 'text',
                'description' => 'Clave pública de sandbox (taypi_pk_test_...).',
                'default'     => '',
            ],
            'test_secret_key' => [
                'title'       => 'Secret Key',
                'type'        => 'password',
                'description' => 'Clave secreta de sandbox.',
                'default'     => '',
            ],
            'section_custom' => [
                'title'       => 'Credenciales Personalizadas',
                'type'        => 'title',
                'description' => 'Claves para el entorno personalizado.',
            ],
            'custom_public_key' => [
                'title'       => 'Public Key',
                'type'        => 'text',
                'description' => 'Clave pública del entorno personalizado.',
                'default'     => '',
            ],
            'custom_secret_key' => [
                'title'       => 'Secret Key',
                'type'        => 'password',
                'description' => 'Clave secreta del entorno personalizado.',
                'default'     => '',
            ],
            'webhook_secret' => [
                'title'       => 'Webhook Secret',
                'type'        => 'password',
                'description' => 'Secret para verificar las notificaciones de pago. '
                    . '<br>URL del webhook: <code>' . esc_html($webhook_url) . '</code>'
                    . '<br>Configura esta URL en tu panel de TAYPI.',
            ],
            'debug' => [
                'title'       => 'Log de depuración',
                'type'        => 'checkbox',
                'label'       => 'Registrar eventos en WooCommerce > Estado > Registros',
                'default'     => 'no',
            ],
        ];
    }

    /**
     * Verificar disponibilidad del gateway.
     */
    public function is_available()
    {
        if ($this->enabled !== 'yes') {
            return false;
        }

        if (empty($this->taypi_public_key) || empty($this->taypi_secret_key)) {
            return false;
        }

        // Solo PEN
        if (get_woocommerce_currency() !== 'PEN') {
            return false;
        }

        // Monto máximo permitido por QR interoperable: S/1,500
        if (WC()->cart && WC()->cart->get_total('edit') > 1500) {
            return false;
        }

        // SSL obligatorio en producción
        if ($this->taypi_environment === 'production' && ! is_ssl()) {
            return false;
        }

        return true;
    }

    /**
     * Descripción + logos en el checkout.
     */
    public function payment_fields()
    {
        if ($this->taypi_environment !== 'production') {
            $env_label = $this->taypi_environment === 'sandbox' ? 'SANDBOX' : strtoupper($this->taypi_environment);
            echo '<p style="background:#fff3cd;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:12px;">'
                . '⚠️ <strong>MODO ' . esc_html($env_label) . '</strong> — No se realizarán cobros reales.</p>';
        }

        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        echo '<div style="display:flex;gap:8px;align-items:center;margin-top:8px;">'
            . '<img src="' . esc_url(TAYPI_WC_PLUGIN_URL . 'assets/images/yape.svg') . '" alt="Yape" height="28">'
            . '<img src="' . esc_url(TAYPI_WC_PLUGIN_URL . 'assets/images/plin.svg') . '" alt="Plin" height="28">'
            . '<span style="font-size:12px;color:#71717a;">y más</span>'
            . '</div>';
    }

    /**
     * Cargar scripts del checkout.
     */
    public function payment_scripts()
    {
        if (! is_checkout() && ! isset($_GET['pay_for_order'])) {
            return;
        }

        if ($this->enabled !== 'yes' || empty($this->taypi_public_key)) {
            return;
        }

        $base_url = $this->get_base_url();

        // SDK checkout.js de TAYPI (CDN)
        wp_enqueue_script(
            'taypi-checkout-sdk',
            $base_url . '/v1/checkout.js',
            [],
            null,
            true
        );

        // JS del plugin
        wp_enqueue_script(
            'taypi-checkout',
            TAYPI_WC_PLUGIN_URL . 'assets/js/taypi-checkout.js',
            ['jquery', 'taypi-checkout-sdk'],
            TAYPI_WC_VERSION,
            true
        );

        wp_localize_script('taypi-checkout', 'taypi_params', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('taypi_create_session'),
            'public_key' => $this->taypi_public_key,
            'gateway_id' => $this->id,
            'return_url' => wc_get_checkout_url(),
            'i18n'       => [
                'error'   => 'Error al procesar el pago. Intenta de nuevo.',
                'expired' => 'El código QR expiró. Intenta de nuevo.',
            ],
        ]);

        // CSS
        wp_enqueue_style(
            'taypi-checkout',
            TAYPI_WC_PLUGIN_URL . 'assets/css/taypi-checkout.css',
            [],
            TAYPI_WC_VERSION
        );
    }

    /**
     * Procesar pago — crea la orden como "pending" y retorna datos para el JS.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (! $order) {
            wc_add_notice('Orden no encontrada.', 'error');
            return ['result' => 'failure'];
        }

        try {
            $client = $this->get_client();

            $reference = (string) $order->get_id();

            $session = $client->createCheckoutSession([
                'amount'      => number_format((float) $order->get_total(), 2, '.', ''),
                'reference'   => $reference,
                'description' => sprintf('Orden #%s — %s', $order->get_order_number(), get_bloginfo('name')),
            ], 'wc-' . $reference);

            $checkout_token = $session['checkout_token'] ?? '';

            if (empty($checkout_token)) {
                throw new \Taypi\TaypiException('No se recibió token de checkout.', 'EMPTY_TOKEN');
            }

            // Guardar metadata
            $order->update_meta_data('_taypi_checkout_token', $checkout_token);
            $order->update_meta_data('_taypi_environment', $this->taypi_environment);
            $order->save();

            // Reducir stock y vaciar carrito se hace cuando el webhook confirma el pago
            $order->update_status('pending', 'Esperando pago QR via TAYPI.');

            $this->log('Sesión creada para orden #' . $order->get_order_number() . ': ' . $checkout_token);

            return [
                'result'   => 'success',
                'redirect' => false,
                'taypi'    => [
                    'checkout_token' => $checkout_token,
                    'return_url'     => $this->get_return_url($order),
                ],
            ];
        } catch (\Taypi\TaypiException $e) {
            $this->log('Error creando sesión: ' . $e->getMessage() . ' (' . $e->errorCode . ')');
            wc_add_notice('Error al crear el pago: ' . esc_html($e->getMessage()), 'error');
            return ['result' => 'failure'];
        }
    }

    /**
     * AJAX: crear sesión de checkout (fallback si process_payment no funciona con modal).
     */
    public function ajax_create_session()
    {
        check_ajax_referer('taypi_create_session', 'nonce');

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order    = wc_get_order($order_id);

        if (! $order) {
            wp_send_json_error(['message' => 'Orden no encontrada.']);
        }

        try {
            $client    = $this->get_client();
            $reference = (string) $order->get_id();

            $session = $client->createCheckoutSession([
                'amount'      => number_format((float) $order->get_total(), 2, '.', ''),
                'reference'   => $reference,
                'description' => sprintf('Orden #%s — %s', $order->get_order_number(), get_bloginfo('name')),
            ], 'wc-' . $reference);

            $checkout_token = $session['checkout_token'] ?? '';

            $order->update_meta_data('_taypi_checkout_token', $checkout_token);
            $order->save();

            wp_send_json_success([
                'checkout_token' => $checkout_token,
                'return_url'     => $this->get_return_url($order),
            ]);
        } catch (\Taypi\TaypiException $e) {
            $this->log('AJAX error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Procesar webhook de TAYPI (POST /wc-api/taypi_webhook).
     */
    public function handle_webhook()
    {
        $payload   = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_TAYPI_SIGNATURE'] ?? '';

        $this->log('Webhook recibido: ' . substr($payload, 0, 500));

        // Verificar firma HMAC-SHA256
        if (empty($this->taypi_webhook_secret)) {
            $this->log('Webhook: webhook_secret no configurado.');
            status_header(403);
            echo wp_json_encode(['error' => 'Webhook secret no configurado']);
            exit;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $this->taypi_webhook_secret);
        if (! hash_equals($expected, $signature)) {
            $this->log('Webhook: firma inválida.');
            status_header(403);
            echo wp_json_encode(['error' => 'Firma inválida']);
            exit;
        }

        $data = json_decode($payload, true);

        if (! $data || empty($data['event'])) {
            status_header(400);
            echo wp_json_encode(['error' => 'Payload inválido']);
            exit;
        }

        $event      = sanitize_text_field($data['event']);
        $payment_id = sanitize_text_field($data['payment_id'] ?? '');
        $reference  = sanitize_text_field($data['reference'] ?? '');

        $this->log("Webhook evento: {$event}, referencia: {$reference}, payment_id: {$payment_id}");

        // Buscar orden por referencia (order ID)
        $order = wc_get_order((int) $reference);

        if (! $order) {
            $this->log("Webhook: orden no encontrada para referencia: {$reference}");
            status_header(404);
            echo wp_json_encode(['error' => 'Orden no encontrada']);
            exit;
        }

        switch ($event) {
            case 'payment.completed':
                if ($order->is_paid()) {
                    $this->log("Webhook: orden #{$reference} ya estaba pagada. Ignorando.");
                    break;
                }

                $order->payment_complete($payment_id);
                $order->add_order_note(sprintf(
                    'Pago confirmado via TAYPI. ID: %s. Wallet: %s.',
                    $payment_id,
                    sanitize_text_field($data['payer_wallet'] ?? 'N/A')
                ));
                $order->update_meta_data('_taypi_payment_id', $payment_id);
                $order->update_meta_data('_taypi_paid_at', sanitize_text_field($data['paid_at'] ?? ''));
                $order->update_meta_data('_taypi_payer_wallet', sanitize_text_field($data['payer_wallet'] ?? ''));
                $order->save();

                $this->log("Webhook: orden #{$reference} marcada como pagada.");
                break;

            case 'payment.expired':
                if (! $order->is_paid() && $order->get_status() === 'pending') {
                    $order->update_status('cancelled', 'Pago QR expirado (24 horas).');
                    $this->log("Webhook: orden #{$reference} cancelada por expiración.");
                }
                break;

            case 'payment.failed':
                if (! $order->is_paid()) {
                    $order->update_status('failed', 'Pago rechazado via TAYPI.');
                    $this->log("Webhook: orden #{$reference} marcada como fallida.");
                }
                break;

            default:
                $this->log("Webhook: evento no manejado: {$event}");
        }

        status_header(200);
        echo wp_json_encode(['status' => 'received']);
        exit;
    }

    /**
     * Obtener URL base según el entorno configurado.
     */
    private function get_base_url()
    {
        switch ($this->taypi_environment) {
            case 'production':
                return 'https://app.taypi.pe';
            case 'custom':
                $custom = $this->get_option('custom_url', '');
                return ! empty($custom) ? rtrim($custom, '/') : 'https://sandbox.taypi.pe';
            case 'sandbox':
            default:
                return 'https://sandbox.taypi.pe';
        }
    }

    /**
     * Obtener cliente SDK de TAYPI.
     */
    private function get_client()
    {
        if ($this->taypi_client === null) {
            $base_url = $this->get_base_url();

            $this->taypi_client = new \Taypi\Taypi(
                $this->taypi_public_key,
                $this->taypi_secret_key,
                ['base_url' => $base_url]
            );
        }

        return $this->taypi_client;
    }

    /**
     * JS para ocultar/mostrar campos según entorno seleccionado.
     */
    public function admin_toggle_fields_js()
    {
        if (! isset($_GET['section']) || $_GET['section'] !== 'taypi') {
            return;
        }
        ?>
        <script type="text/javascript">
        (function($) {
            function toggleTaypiFields() {
                var env = $('#woocommerce_taypi_environment').val();

                // Custom URL
                $('#woocommerce_taypi_custom_url').closest('tr').toggle(env === 'custom');

                // Secciones de credenciales
                // Production
                $('[id="woocommerce_taypi_section_production"]').closest('table').prev('h3').toggle(env === 'production');
                $('[id="woocommerce_taypi_section_production"]').closest('table').prev('p').toggle(env === 'production');
                $('#woocommerce_taypi_live_public_key').closest('tr').toggle(env === 'production');
                $('#woocommerce_taypi_live_secret_key').closest('tr').toggle(env === 'production');

                // Sandbox
                $('#woocommerce_taypi_test_public_key').closest('tr').toggle(env === 'sandbox');
                $('#woocommerce_taypi_test_secret_key').closest('tr').toggle(env === 'sandbox');

                // Custom
                $('#woocommerce_taypi_custom_public_key').closest('tr').toggle(env === 'custom');
                $('#woocommerce_taypi_custom_secret_key').closest('tr').toggle(env === 'custom');
            }

            $(document).ready(function() {
                toggleTaypiFields();
                $('#woocommerce_taypi_environment').on('change', toggleTaypiFields);
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Log si debug está activado.
     */
    private function log(string $message)
    {
        if ($this->taypi_debug === 'yes') {
            $logger = wc_get_logger();
            $logger->info($message, ['source' => 'taypi']);
        }
    }
}
