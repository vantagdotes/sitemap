<?php
/*
 * Plugin Name:       Multilingual Sitemap generator
 * Plugin URI:        https://github.com/vantagdotes/
 * Description:       This is a simple WordPress plugin that generates a multilingual sitemap for your website.
 * Version:           1.0
 * Requires at least: 6.3
 * Requires PHP:      7.3
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
    if (isset($_POST['generate_sitemap'])) {
        vantag_generate_sitemap();
        echo '<div class="updated"><p>Sitemap generado exitosamente. <a href="' . home_url('/sitemap.xml') . '" target="_blank">Ver Sitemap</a></p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Generador de Sitemap</h1>
        <form method="post">
            <p>Haz clic en el botón para generar el sitemap.</p>
            <p><input type="submit" name="generate_sitemap" class="button button-primary" value="Generar Sitemap"></p>
        </form>
    </div>
    <?php
    $sitemap_path = ABSPATH . 'sitemap.xml';
    if (file_exists($sitemap_path)) {
        $xml = simplexml_load_file($sitemap_path);
        echo '<h2>Contenido del Sitemap</h2>';
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead><tr><th>URL</th><th>Fecha de Modificación</th></tr></thead>';
        echo '<tbody>';
        foreach ($xml->url as $url) {
            echo '<tr>';
            echo '<td>' . esc_html($url->loc) . '</td>';
            echo '<td>' . esc_html($url->lastmod) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No se encontró un sitemap existente. Por favor, genera uno nuevo.</p>';
    }
}

function vantag_generate_sitemap() {
    $posts = get_posts(array(
        'numberposts' => -1,
        'post_type' => array('post', 'page'),
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_noindex',
                'compare' => 'NOT EXISTS'
            )
        )
    ));

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

    foreach ($posts as $post) {
        $noindex = get_post_meta($post->ID, '_noindex', true);
        if ($noindex) {
            continue;
        }

        $url = $xml->addChild('url');
        $url->addChild('loc', get_permalink($post->ID));
        $url->addChild('lastmod', get_the_modified_time('c', $post->ID));
    }

    $xml->asXML(ABSPATH . 'sitemap.xml');
}
?>