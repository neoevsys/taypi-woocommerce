=== TAYPI - Pago QR para WooCommerce ===
Contributors: neotecperu
Tags: woocommerce, payment gateway, qr, yape, plin
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Acepta pagos QR interoperables en tu tienda WooCommerce. Tus clientes pagan con Yape, Plin o cualquier app bancaria.

== Description ==

TAYPI permite a comercios en Peru recibir pagos QR interoperables directamente en WooCommerce. Tus clientes escanean un codigo QR desde Yape, Plin o cualquier app bancaria conectada a la CCE (Camara de Compensacion Electronica) y el pago se confirma en tiempo real.

**Funcionalidades:**

* Generacion de codigos QR dinamicos EMVCo en cada compra
* Compatible con Yape, Plin y todas las apps bancarias del Peru
* Confirmacion de pago en tiempo real via webhook
* Modo sandbox para pruebas sin cobros reales
* Panel de transacciones en tu cuenta TAYPI
* Monto maximo por transaccion: S/ 1,500.00

**Como funciona:**

1. El cliente elige "Pago QR" en el checkout
2. Se muestra un codigo QR en un modal
3. El cliente escanea con Yape, Plin o su app bancaria
4. El pago se confirma automaticamente y la orden se procesa

**Requisitos:**

* Cuenta activa en [TAYPI](https://app.taypi.pe)
* WooCommerce 7.0 o superior
* Moneda configurada en Soles (PEN)
* SSL activo en produccion

**Servicios externos**

Este plugin se conecta a los servidores de TAYPI para procesar pagos. Cuando un cliente realiza una compra, se crea una sesion de pago en los servidores de TAYPI y se carga un archivo JavaScript externo para mostrar el codigo QR.

* Servidor de produccion: [https://app.taypi.pe](https://app.taypi.pe)
* Servidor sandbox: [https://sandbox.taypi.pe](https://sandbox.taypi.pe)
* JavaScript externo: https://app.taypi.pe/v1/checkout.js (o sandbox.taypi.pe en modo pruebas)
* Terminos de servicio: [https://taypi.pe/terminos](https://taypi.pe/terminos)
* Politica de privacidad: [https://taypi.pe/privacidad](https://taypi.pe/privacidad)

Los datos transmitidos incluyen: monto de la orden, referencia (ID de orden) y descripcion. No se transmiten datos personales del cliente a TAYPI.

== Installation ==

1. Sube la carpeta `taypi-woocommerce` al directorio `/wp-content/plugins/`
2. Activa el plugin en WordPress > Plugins
3. Ve a WooCommerce > Ajustes > Pagos > TAYPI - Pago QR
4. Ingresa tus claves API (obtenlas en [app.taypi.pe](https://app.taypi.pe))
5. Configura la URL del webhook en tu panel TAYPI: `https://tutienda.com/wc-api/taypi_webhook/`
6. Activa el modo sandbox para pruebas iniciales

= Instalacion via Composer =

Si gestionas dependencias con Composer:

`composer require taypi/taypi-woocommerce`

== Frequently Asked Questions ==

= Que billeteras son compatibles? =

Yape, Plin y cualquier app bancaria conectada a la CCE del Peru (BCP, BBVA, Interbank, Scotiabank, BanBif, etc.).

= Necesito cuenta en TAYPI? =

Si. Registrate gratis en [app.taypi.pe](https://app.taypi.pe) para obtener tus claves API.

= Cual es el monto maximo por transaccion? =

S/ 1,500.00 por transaccion QR, segun regulacion de la CCE.

= Cual es la comision? =

Consulta las tarifas actualizadas en [taypi.pe](https://taypi.pe).

= Funciona con otras monedas? =

No. TAYPI opera exclusivamente con Soles peruanos (PEN) a traves de la CCE.

= El plugin es compatible con HPOS? =

Si. El plugin es totalmente compatible con High-Performance Order Storage de WooCommerce.

= Como pruebo sin cobrar? =

Activa el "Modo sandbox" en la configuracion del plugin y usa tus claves de sandbox.

== Screenshots ==

1. Configuracion del plugin en WooCommerce > Ajustes > Pagos
2. Metodo de pago en el checkout del cliente
3. Modal con codigo QR para escanear

== Changelog ==

= 1.0.0 =
* Lanzamiento inicial
* Pagos QR via TAYPI API con checkout.js
* Modo sandbox y produccion
* Webhooks automaticos para confirmacion de pago
* Compatible con HPOS

== Upgrade Notice ==

= 1.0.0 =
Lanzamiento inicial del plugin.
