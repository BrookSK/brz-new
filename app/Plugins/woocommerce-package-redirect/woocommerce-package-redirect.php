<?php
/*
Plugin Name: WooCommerce Package Redirect
Description: Plugin que integra redirecionamento de encomendas com WooCommerce.
Version: 1.1.19
Author: Gustavo Ferreira
*/

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    wp_die('Erro: Execute <code>composer install</code> no diretório do plugin.');
}

add_action('plugins_loaded', 'wc_package_redirect_init');

function wc_package_redirect_init() {
    if (class_exists('WC_Product')) {
        $load_files = function($dir) {
            $files = glob($dir . '*.php');
            foreach ($files as $file) {
                include_once $file;
            }
        };

        $directories = [
            __DIR__ . '/includes/',
            __DIR__ . '/includes/settings/',
            __DIR__ . '/includes/correios-packet/',
            __DIR__ . '/includes/utils/'
        ];

        foreach ($directories as $dir) {
            $load_files($dir);
        }
    }
}