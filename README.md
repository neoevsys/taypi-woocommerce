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
2. Se abre un modal con un codigo QR dinamico (EMVCo)
3. El cliente escanea desde **Yape, Plin o cualquier app bancaria**
4. El pago se confirma en tiempo real y la orden se procesa automaticamente

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
# Descargar y copiar al directorio de plugins
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

| Campo | Descripcion |
|---|---|
| **Activar** | Habilita TAYPI como metodo de pago |
| **Titulo** | Nombre visible en el checkout (default: "Pago QR — Yape, Plin y mas") |
| **Modo sandbox** | Usa el entorno de pruebas sin cobros reales |
| **Public Key** | Clave publica de tu cuenta TAYPI |
| **Secret Key** | Clave secreta (nunca se comparte) |
| **Webhook Secret** | Secret para verificar notificaciones de pago |

### Webhook

Configura esta URL en tu [panel de TAYPI](https://app.taypi.pe):

```
https://tutienda.com/wc-api/taypi_webhook/
```

## Arquitectura

```
┌─────────────────────────────────────────────────────────┐
│  WooCommerce Checkout                                   │
│                                                         │
│  ┌─────────────┐    ┌──────────────┐    ┌────────────┐  │
│  │  Gateway     │───>│ taypi-php   │───>│ TAYPI API  │  │
│  │  (PHP)       │    │ SDK         │    │            │  │
│  └─────────────┘    └──────────────┘    └────────────┘  │
│         │                                      │        │
│         v                                      v        │
│  ┌─────────────┐                      ┌────────────┐   │
│  │ checkout.js  │                      │  Webhook   │   │
│  │ (CDN modal)  │                      │  Handler   │   │
│  └─────────────┘                      └────────────┘   │
└─────────────────────────────────────────────────────────┘
```

- **Backend:** Usa el [SDK PHP de TAYPI](https://github.com/neoevsys/taypi-php) (`taypi/taypi-php`) para crear sesiones de pago
- **Frontend:** Usa `checkout.js` desde CDN para mostrar el modal con QR
- **Webhooks:** Confirmacion automatica de pago via firma HMAC-SHA256

## Limites

| Concepto | Valor |
|---|---|
| Monto minimo | S/ 1.00 |
| Monto maximo | S/ 1,500.00 |
| Moneda | PEN (Soles) |
| Expiracion QR | 24 horas |

Si el carrito supera S/ 1,500, el metodo de pago TAYPI no se muestra en el checkout.

## Depuracion

Activa el **Log de depuracion** en la configuracion del plugin. Los logs se guardan en:

**WooCommerce > Estado > Registros > taypi**

## Compatibilidad

- [x] WooCommerce HPOS (High-Performance Order Storage)
- [x] WooCommerce Checkout clasico
- [x] WordPress Multisite
- [ ] WooCommerce Checkout Blocks (proximamente)

## Desarrollo

```bash
git clone https://github.com/neoevsys/taypi-woocommerce.git
cd taypi-woocommerce
composer install
```

## Publicar nueva version

1. Actualiza la version en `taypi-woocommerce.php` y `readme.txt`
2. Commit y push
3. Crea un tag: `git tag 1.0.1 && git push origin 1.0.1`
4. GitHub Actions despliega automaticamente a WordPress.org

## Licencia

GPLv2 or later — [NEO TECHNOLOGY PERU E.I.R.L.](https://neotecperu.com)
