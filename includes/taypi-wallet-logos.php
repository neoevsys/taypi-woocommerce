<?php
/**
 * Logos de wallets y bancos compatibles con TAYPI.
 * Base64 data URIs para renderizado inline sin requests externos.
 *
 * @package Taypi_WooCommerce
 */

defined('ABSPATH') || exit;

function taypi_get_wallet_logos()
{
    $base = TAYPI_WC_PLUGIN_URL . 'assets/images/wallets/';

    return [
        'yape' => ['name' => 'Yape', 'src' => $base . 'yape.png'],
        'plin' => ['name' => 'Plin', 'src' => $base . 'plin.png'],
        // Agregar más cuando estén disponibles:
        // 'panda'      => ['name' => 'Panda',      'src' => $base . 'panda.png'],
        // 'bbva'       => ['name' => 'BBVA',        'src' => $base . 'bbva.png'],
        // 'scotiabank' => ['name' => 'Scotiabank',  'src' => $base . 'scotiabank.png'],
        // 'interbank'  => ['name' => 'Interbank',   'src' => $base . 'interbank.png'],
        // 'banbif'     => ['name' => 'BanBif',      'src' => $base . 'banbif.png'],
        // 'agora'      => ['name' => 'Ya',          'src' => $base . 'agora.png'],
    ];
}
