<p align="center">
  <img src="assets/images/taypi-logo.png" alt="TAYPI" width="80">
</p>

<h1 align="center">TAYPI — Pago QR para WooCommerce</h1>

<p align="center">
  Acepta pagos QR interoperables con Yape, Plin y cualquier app bancaria conectada a la CCE.
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/taypi-woocommerce/"><img src="https://img.shields.io/wordpress/plugin/v/taypi-woocommerce?label=WordPress.org&color=4f46e5" alt="WordPress Plugin Version"></a>
  <a href="https://wordpress.org/plugins/taypi-woocommerce/"><img src="https://img.shields.io/wordpress/plugin/dt/taypi-woocommerce?color=22c55e" alt="Downloads"></a>
  <a href="https://wordpress.org/plugins/taypi-woocommerce/"><img src="https://img.shields.io/wordpress/plugin/rating/taypi-woocommerce?color=f59e0b" alt="Rating"></a>
  <img src="https://img.shields.io/badge/WooCommerce-7.0%2B-96588a" alt="WooCommerce">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4" alt="PHP">
  <img src="https://img.shields.io/badge/Licencia-GPLv2-blue" alt="License">
</p>

---

## Como funciona

```
Cliente elige "Pago QR" → Se muestra QR en modal → Escanea con Yape/Plin → Pago confirmado
```

1. El cliente selecciona **"Pago QR — Yape, Plin y mas"** en el checkout
2. Hace click en **"Pagar con QR"**
3. Se abre un modal con un codigo QR dinamico (EMVCo)
4. El cliente escanea desde **Yape, Plin o cualquier app bancaria**
5. El pago se confirma en tiempo real, la orden se marca como pagada y redirige a la pagina de gracias

## Checkout Blocks y Clasico

El plugin funciona con **ambas versiones** del checkout de WooCommerce:

| Checkout | Soporte | Archivo JS |
|---|---|---|
| **Checkout Blocks** (Gutenberg) | ✅ Completo | `taypi-blocks.js` |
| **Checkout Clasico** (shortcode) | ✅ Completo | `taypi-checkout.js` |

La deteccion es automatica. No requiere configuracion adicional.

## Requisitos

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- Moneda: **Soles (PEN)**
- SSL activo en produccion
- Cuenta en [TAYPI](https://app.taypi.pe)

## Instalacion

### Desde WordPress

1. Ve a **Plugins > Añadir nuevo** y busca "TAYPI"
2. Instala y activa el plugin
3. Ve a **WooCommerce > Ajustes > Pagos > TAYPI - Pago QR**
4. Ingresa tus claves API

### Manual

```bash
cd wp-content/plugins/
git clone https://github.com/neoevsys/taypi-woocommerce.git
cd taypi-woocommerce
composer install --no-dev
```

### Composer

```bash
composer require taypi/taypi-woocommerce
```

## Configuracion

### Entornos

El plugin soporta 3 entornos, cada uno con sus propias credenciales:

| Entorno | URL | Uso |
|---|---|---|
| **Produccion** | app.taypi.pe | Cobros reales |
| **Sandbox** | sandbox.taypi.pe | Pruebas sin cobros |
| **Personalizado** | URL configurable | Desarrollo interno |

Al seleccionar un entorno, solo se muestran los campos de credenciales correspondientes.

### Campos de configuracion

| Campo | Descripcion |
|---|---|
| **Activar** | Habilita TAYPI como metodo de pago |
| **Titulo** | Nombre visible en el checkout (default: "Pago QR — Yape, Plin y mas") |
| **Entorno** | Produccion, Sandbox o Personalizado |
| **Public Key** | Clave publica del entorno seleccionado |
| **Secret Key** | Clave secreta del entorno seleccionado |
| **Webhook Secret** | Secret para verificar notificaciones de pago |
| **Log de depuracion** | Activa logging en PHP y JS |

### Webhook

Configura esta URL en tu [panel de TAYPI](https://app.taypi.pe):

```
https://tutienda.com/wc-api/taypi_webhook/
```

## Arquitectura

```
┌──────────────────────────────────────────────────────────────┐
│  WooCommerce Checkout (Blocks o Clasico)                     │
│                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────────┐   │
│  │ Store API /   │───>│ taypi-php   │───>│  TAYPI API    │   │
│  │ Gateway (PHP) │    │ SDK         │    │               │   │
│  └──────────────┘    └──────────────┘    └───────────────┘   │
│         │                                       │            │
│         v                                       v            │
│  ┌──────────────┐                      ┌───────────────┐     │
│  │ checkout.js   │                      │  Webhook /    │     │
│  │ (CDN modal)   │                      │  AJAX mark    │     │
│  └──────────────┘                      └───────────────┘     │
└──────────────────────────────────────────────────────────────┘
```

- **Backend:** Usa el [SDK PHP de TAYPI](https://github.com/neoevsys/taypi-php) (`taypi/taypi-php`) para crear sesiones de pago
- **Frontend Blocks:** `taypi-blocks.js` registra el metodo en `wcBlocksRegistry`, maneja `onPaymentSetup` y `onCheckoutSuccess` para abrir el modal QR
- **Frontend Clasico:** `taypi-checkout.js` intercepta el checkout via jQuery y abre el modal
- **Modal QR:** `checkout.js` desde CDN de TAYPI renderiza el QR y detecta el pago
- **Confirmacion:** AJAX `taypi_mark_paid` marca la orden como pagada + webhook como respaldo

## Flujo de pago (Checkout Blocks)

```
1. Cliente click "Pagar con QR"
2. onPaymentSetup → envia payment_method: taypi
3. Store API hook → crea sesion TAYPI via SDK → obtiene checkout_token
4. set_payment_details({ checkout_token }) → llega al JS
5. onCheckoutSuccess → lee checkout_token de processingResponse
6. Taypi.open({ sessionToken }) → muestra modal con QR
7. Cliente escanea QR con Yape/Plin
8. Modal detecta pago → onSuccess
9. AJAX taypi_mark_paid → orden marcada como pagada
10. Redirect a pagina de gracias
```

## Limites

| Concepto | Valor |
|---|---|
| Monto minimo | S/ 1.00 |
| Monto maximo | S/ 1,500.00 |
| Moneda | PEN (Soles) |
| Expiracion QR | 24 horas |

Si el carrito supera S/ 1,500, el metodo de pago TAYPI no se muestra en el checkout.

## Depuracion

Activa el **Log de depuracion** en la configuracion del plugin. Cuando esta activo:

- **PHP:** Registra cada paso en **WooCommerce > Estado > Registros > taypi-woocommerce**
- **JS:** Registra en la consola del navegador con prefijo `[TAYPI]`

Cuando esta desactivado, no se genera ningun log.

## Compatibilidad

- [x] WooCommerce Checkout Blocks (Gutenberg)
- [x] WooCommerce Checkout Clasico (shortcode)
- [x] WooCommerce HPOS (High-Performance Order Storage)
- [x] WordPress Multisite

## Desarrollo

```bash
git clone https://github.com/neoevsys/taypi-woocommerce.git
cd taypi-woocommerce
composer install
```

## Publicar nueva version

1. Actualiza la version en `taypi-woocommerce.php` y `readme.txt`
2. Commit y push
3. Crea un tag: `git tag 1.0.2 && git push origin 1.0.2`
4. GitHub Actions despliega automaticamente a WordPress.org

## Licencia

GPLv2 or later — [NEO TECHNOLOGY PERU E.I.R.L.](https://neotecperu.com)
