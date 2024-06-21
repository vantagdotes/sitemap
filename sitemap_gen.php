<?php
/*
 * Plugin Name:       Multilingual Sitemap generator
 * Plugin URI:        https://github.com/vantagdotes/
 * Description:       Multilingual Sitemap generator, which solves the problems of Yoast SEO compatibility plugins with Loco Translate, WPML, Polylang, etc.
 * Version:           1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            VANTAG.es
 * Author URI:        https://vantag.es
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       sitemap-gen
 */

defined('ABSPATH') or die('You shouldn\'t be here...');

function vantag_sitemapgen_menu() {
    add_submenu_page(
        'tools.php',
        'Sitemap gen',
        'Sitemap gen',
        'manage_options',
        'sitemap_gen',
        'vantag_sitemapgen_page'
    );
}
add_action('admin_menu', 'vantag_sitemapgen_menu');

function vantag_sitemapgen_page() {
    
    //Si se pulsa el boton, generar sitemap.xml en la raiz del proyecto
    if (isset($_POST['generate_sitemap'])) {
    
        // Verificar nonce
    if ( !isset($_POST['generate_sitemap_nonce_field']) || !wp_verify_nonce($_POST['generate_sitemap_nonce_field'], 'generate_sitemap_nonce') ) {
        die('Security error. Please go back and try again.');
    }

        vantag_generate_sitemap();
        echo '<div class="updated"><p>Sitemap successfully generated.<a href="' . esc_url(home_url('/sitemap.xml')) . '" target="_blank">View sitemap</a></p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Sitemap Generator</h1>

        <!--generar sitemap-->
        <form method="post">
            <?php wp_nonce_field('generate_sitemap_nonce', 'generate_sitemap_nonce_field'); ?>
            <p>Click the button to generate the sitemap.</p>
            <p><input type="submit" name="generate_sitemap" class="button button-primary" value="Generate sitemap"></p>
        </form>
    </div>
    <?php

    // Si la web tiene sitemap, mostrarlo por pantalla
    $sitemap_path = ABSPATH . 'sitemap.xml';
    if (file_exists($sitemap_path)) {
        $xml = simplexml_load_file($sitemap_path);
        echo '<h2>Sitemap</h2>';
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead><tr><th>URL</th><th>Date of modification</th></tr></thead>';
        echo '<tbody>';
        foreach ($xml->url as $url) {
            echo '<tr>';
            echo '<td><a href="' . esc_url($url->loc) . '" target="_blank">' . esc_html($url->loc) . '</a></td>';
            echo '<td>' . esc_html( gmdate( 'H:i | d-m-Y' ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>An existing Sitemap was not found. Please generate a new one.</p>'; // Si no tiene sitemap
    }
}

function vantag_generate_sitemap() {
    $posts = get_posts(array(
        'numberposts' => -1,
        'post_type' => array('post', 'page'),
        'post_status' => 'publish'
    ));

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

    foreach ($posts as $post) {
        $post_content = get_post_field('post_content', $post->ID);
        if (strpos($post_content, 'noindex') !== false) { // Verifica si el post tiene la meta etiqueta noindex
            continue;
        }

        // noindex con los plugins SEO mas usados
        $yoast_noindex = get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);
        $aioseo_noindex = get_post_meta($post->ID, '_aioseo_noindex', true);
        $rankmath_noindex = get_post_meta($post->ID, 'rank_math_robots', true);

        if ($yoast_noindex == '1' || $aioseo_noindex == '1' || $rankmath_noindex == '1') {
            continue;
        }

        $url = $xml->addChild('url');
        $url->addChild('loc', get_permalink($post->ID));
        $url->addChild('lastmod', get_the_modified_time('c', $post->ID));
    }

    $xml->asXML(ABSPATH . 'sitemap.xml');
}
?>