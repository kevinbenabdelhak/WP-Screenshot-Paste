<?php 

/**
 * Plugin Name: WP Screenshot Paste
 * Plugin URI: https://kevin-benabdelhak.fr/plugins/wp-screenshot-paste/
 * Description: WP Screenshot Paste est un plugin conçu pour vous permettre de coller facilement une image ou une capture d’écran (Ctrl+V) directement dans la médiathèque WordPress, l’éditeur TinyMCE ou l’éditeur classique, avec affichage d’un loader et insertion automatique dans le contenu. Possibilité de générer automatiquement alt, légende, titre et description avec gpt-4.1 (clé api requise)
 * Version: 1.2
 * Author: Kevin Benabdelhak
 * Author URI: https://kevin-benabdelhak.fr/
 * Contributors: kevinbenabdelhak
 */

if (!defined('ABSPATH')) {
    exit;
}

// Autoriser protocole data:
add_filter('kses_allowed_protocols', function ($protocols) {
    if (!in_array('data', $protocols)) {
        $protocols[] = 'data';
    }
    return $protocols;
}, 10, 1);



require_once plugin_dir_path(__FILE__) . 'options.php';
require_once plugin_dir_path(__FILE__) . 'script/ajax.php';
require_once plugin_dir_path(__FILE__) . 'script/upload.php';

