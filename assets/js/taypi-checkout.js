/**
 * TAYPI Checkout — Integración con WooCommerce
 *
 * Usa checkout.js (CDN) para abrir el modal de pago QR
 * después de que WooCommerce procesa la orden.
 *
 * @package Taypi_WooCommerce
 */
(function ($) {
    'use strict';

    if (typeof taypi_params === 'undefined') {
        return;
    }

    var processing = false;

    // Interceptar el submit del checkout cuando el gateway es TAYPI
    $(document.body).on('checkout_place_order_taypi', function () {
        if (processing) {
            return false;
        }
        return true; // Dejar que WooCommerce procese
    });

    // Escuchar la respuesta de WooCommerce después de process_payment
    $(document.body).on('checkout_error', function () {
        processing = false;
    });

    // Interceptar respuesta exitosa de WooCommerce
    $(document).ajaxComplete(function (event, xhr, settings) {
        if (!settings.url || settings.url.indexOf('wc-ajax=checkout') === -1) {
            return;
        }

        var response;
        try {
            response = JSON.parse(xhr.responseText);
        } catch (e) {
            return;
        }

        // Solo interceptar si es una respuesta de TAYPI
        if (!response || response.result !== 'success' || !response.taypi) {
            return;
        }

        // Prevenir que WooCommerce haga redirect
        if (window.wc_checkout_form) {
            window.wc_checkout_form.submit_error = function() {};
        }

        processing = true;
        var taypiData = response.taypi;

        openTaypiModal(taypiData.checkout_token, taypiData.return_url);
    });

    /**
     * Abrir el modal de TAYPI con checkout.js
     */
    function openTaypiModal(checkoutToken, returnUrl) {
        if (typeof Taypi === 'undefined') {
            alert(taypi_params.i18n.error);
            processing = false;
            return;
        }

        Taypi.publicKey = taypi_params.public_key;

        Taypi.open({
            sessionToken: checkoutToken,

            onSuccess: function (result) {
                processing = false;
                // Redirigir a la página de confirmación
                window.location.href = returnUrl;
            },

            onExpired: function () {
                processing = false;
                showNotice(taypi_params.i18n.expired);
            },

            onClose: function () {
                processing = false;
                // Usuario cerró el modal sin pagar — puede reintentar
            }
        });
    }

    /**
     * Mostrar aviso en el checkout
     */
    function showNotice(message) {
        var $notices = $('.woocommerce-notices-wrapper').first();
        if ($notices.length === 0) {
            $notices = $('form.checkout').prev('.woocommerce-notices-wrapper');
        }

        $notices.html(
            '<div class="woocommerce-error" role="alert">' + message + '</div>'
        );

        $('html, body').animate({ scrollTop: $notices.offset().top - 100 }, 500);
    }

})(jQuery);
