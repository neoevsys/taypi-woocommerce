/**
 * TAYPI — WooCommerce Checkout Blocks Integration
 *
 * Registra TAYPI como método de pago en el checkout por bloques.
 * Usa checkout.js (CDN) para abrir el modal QR después de crear la orden.
 */
(function () {
    'use strict';

    function log() {
        if (settings.debug) {
            console.log.apply(console, ['[TAYPI]'].concat(Array.prototype.slice.call(arguments)));
        }
    }
    function warn() {
        if (settings.debug) {
            console.warn.apply(console, ['[TAYPI]'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    log('taypi-blocks.js cargado');

    var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
    var createElement = window.wp.element.createElement;
    var useState = window.wp.element.useState;
    var useEffect = window.wp.element.useEffect;
    var getSetting = window.wc.wcSettings.getSetting;
    var decodeEntities = window.wp.htmlEntities.decodeEntities;

    var settings = getSetting('taypi_data', {});
    log(' settings:', JSON.stringify(settings));

    var title = decodeEntities(settings.title || 'Pago QR — Yape, Plin y más');

    /**
     * Label del método de pago (radio button)
     */
    var Label = function (props) {
        return createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
            createElement('img', {
                src: settings.logo_url,
                alt: 'TAYPI',
                style: { height: '24px', width: '24px', borderRadius: '4px' }
            }),
            createElement('span', null, title)
        );
    };

    /**
     * Contenido cuando el método está seleccionado
     */
    var Content = function (props) {
        var eventRegistration = props.eventRegistration;
        var emitResponse = props.emitResponse;
        var onPaymentSetup = eventRegistration.onPaymentSetup;
        var onCheckoutSuccess = eventRegistration.onCheckoutSuccess;

        useEffect(function () {
            log(' useEffect: registering callbacks');

            // Al procesar pago: enviar datos mínimos al servidor
            var unsubSetup = onPaymentSetup(function () {
                log(' onPaymentSetup FIRED - sending payment_method: taypi');
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            payment_method: 'taypi'
                        }
                    }
                };
            });

            // Al recibir respuesta exitosa del servidor: abrir modal QR
            var unsubSuccess = onCheckoutSuccess(function (data) {
                log(' onCheckoutSuccess FIRED');
                log(' onCheckoutSuccess data:', JSON.stringify(data, null, 2));

                // WC Blocks pone los details en processingResponse O en paymentResult según la versión
                var paymentDetails = {};
                if (data && data.processingResponse && data.processingResponse.paymentDetails) {
                    paymentDetails = data.processingResponse.paymentDetails;
                    log(' paymentDetails from processingResponse:', JSON.stringify(paymentDetails));
                } else if (data && data.paymentResult && data.paymentResult.paymentDetails) {
                    paymentDetails = data.paymentResult.paymentDetails;
                    log(' paymentDetails from paymentResult:', JSON.stringify(paymentDetails));
                } else {
                    log(' paymentDetails: searching all data keys...');
                    log(' data keys:', Object.keys(data || {}));
                }

                var redirectUrl = (data ? data.redirectUrl : '') || paymentDetails.return_url || '';
                log(' redirectUrl:', redirectUrl);

                // Si no hay checkout_token, el servidor no lo envió
                if (!paymentDetails.checkout_token) {
                    warn(' NO checkout_token found in paymentDetails! Keys available:', Object.keys(paymentDetails));
                    warn(' Falling through to default redirect behavior');
                    return true;
                }

                log(' checkout_token found:', paymentDetails.checkout_token);
                log(' Checking if Taypi SDK (checkout.js) is loaded...');

                // Retornar Promise — el checkout NO redirige hasta que se resuelva
                return new Promise(function (resolve) {
                    if (typeof Taypi === 'undefined') {
                        warn(' Taypi SDK NOT loaded! typeof Taypi =', typeof Taypi);
                        warn(' window.Taypi =', window.Taypi);
                        resolve({
                            type: emitResponse.responseTypes.ERROR,
                            message: 'Error cargando el sistema de pago. Recarga la página.',
                            messageContext: emitResponse.noticeContexts.PAYMENTS
                        });
                        return;
                    }

                    log(' Taypi SDK loaded OK. Setting publicKey:', settings.public_key);
                    Taypi.publicKey = settings.public_key;

                    log(' Opening Taypi modal with sessionToken:', paymentDetails.checkout_token);

                    try {
                        Taypi.open({
                            sessionToken: paymentDetails.checkout_token,

                            onSuccess: function (result) {
                                log(' Modal onSuccess:', JSON.stringify(result));

                                // Notificar al servidor que el pago se completó
                                var orderId = paymentDetails.order_id;
                                if (orderId) {
                                    log(' Notifying server: order ' + orderId + ' paid');
                                    var xhr = new XMLHttpRequest();
                                    xhr.open('POST', '/wp-admin/admin-ajax.php', false); // síncrono
                                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                    xhr.send('action=taypi_mark_paid&order_id=' + orderId + '&nonce=' + settings.mark_paid_nonce);
                                    log(' Server response:', xhr.responseText);
                                }

                                // Redirigir a página de gracias
                                if (redirectUrl) {
                                    log(' Redirecting to:', redirectUrl);
                                    window.location.href = redirectUrl;
                                }

                                resolve({
                                    type: emitResponse.responseTypes.SUCCESS,
                                    redirectUrl: redirectUrl
                                });
                            },

                            onExpired: function () {
                                log(' Modal onExpired');
                                resolve({
                                    type: emitResponse.responseTypes.ERROR,
                                    message: 'El código QR expiró. Intenta de nuevo.',
                                    messageContext: emitResponse.noticeContexts.PAYMENTS,
                                    retry: true
                                });
                            },

                            onClose: function () {
                                log(' Modal onClose (user closed without paying)');
                                resolve({
                                    type: emitResponse.responseTypes.ERROR,
                                    message: 'Pago cancelado. Puedes intentar de nuevo.',
                                    messageContext: emitResponse.noticeContexts.PAYMENTS,
                                    retry: true
                                });
                            }
                        });
                        log(' Taypi.open() called successfully');
                    } catch (err) {
                        warn(' Error calling Taypi.open():', err);
                        resolve({
                            type: emitResponse.responseTypes.ERROR,
                            message: 'Error al abrir el pago: ' + err.message,
                            messageContext: emitResponse.noticeContexts.PAYMENTS
                        });
                    }
                });
            });

            return function () {
                unsubSetup();
                unsubSuccess();
            };
        }, [onPaymentSetup, onCheckoutSuccess, emitResponse]);

        return createElement(
            'div',
            null,
            settings.is_sandbox && createElement(
                'p',
                {
                    style: {
                        background: '#fff3cd',
                        padding: '8px 12px',
                        borderRadius: '6px',
                        fontSize: '13px',
                        marginBottom: '12px'
                    }
                },
                '⚠️ ',
                createElement('strong', null, 'MODO SANDBOX'),
                ' — No se realizarán cobros reales.'
            ),
            createElement('p', null, decodeEntities(settings.description || '')),
            createElement(
                'div',
                { style: { display: 'flex', gap: '6px', alignItems: 'center', marginTop: '8px', flexWrap: 'wrap' } },
                (settings.wallets || []).map(function (w, i) {
                    return createElement('img', {
                        key: i,
                        src: w.src,
                        alt: w.name,
                        title: w.name,
                        style: { height: '32px', width: '32px', borderRadius: '8px', objectFit: 'cover' }
                    });
                }),
                createElement('span', { style: { fontSize: '12px', color: '#71717a' } }, 'y más')
            )
        );
    };

    /**
     * Registrar método de pago en Checkout Blocks
     */
    registerPaymentMethod({
        name: 'taypi',
        label: createElement(Label, null),
        content: createElement(Content, null),
        edit: createElement(Content, null),
        canMakePayment: function (args) {
            var currency = args.cartTotals ? args.cartTotals.currency_code : 'unknown';
            var total = args.cartTotals ? args.cartTotals.total_price : 0;
            log(' canMakePayment: currency=' + currency + ' total=' + total + ' public_key=' + (settings.public_key ? 'SET' : 'EMPTY'));

            if (currency !== 'PEN') {
                log(' canMakePayment: FALSE (not PEN)');
                return false;
            }
            if (parseInt(total) > 150000) {
                log(' canMakePayment: FALSE (exceeds 1500)');
                return false;
            }
            if (!settings.public_key) {
                log(' canMakePayment: FALSE (no public key)');
                return false;
            }
            log(' canMakePayment: TRUE');
            return true;
        },
        ariaLabel: title,
        placeOrderButtonLabel: 'Pagar con QR',
        supports: {
            features: settings.supports || ['products']
        }
    });

    log(' registerPaymentMethod called successfully');
})();
