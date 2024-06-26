<?php
/*
 * Plugin Name:       Multilingual Sitemap Generator
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
    if (isset($_POST['generate_sitemap'])) {
        if (!isset($_POST['generate_sitemap_nonce_field']) || !wp_verify_nonce($_POST['generate_sitemap_nonce_field'], 'generate_sitemap_nonce')) {
            die('Security error. Please go back and try again.');
        }
        vantag_generate_sitemap();
        echo '<div class="updated"><p>Sitemap successfully generated. <a href="' . esc_url(home_url('/sitemap.xml')) . '" target="_blank">View sitemap</a></p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Sitemap Generator</h1>
        <form method="post">
            <?php wp_nonce_field('generate_sitemap_nonce', 'generate_sitemap_nonce_field'); ?>
            <p>Click the button to generate the sitemap.</p>
            <p><input type="submit" name="generate_sitemap" class="button button-primary" value="Generate sitemap"></p>
        </form>
    </div>
    <?php

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
            echo '<td>' . esc_html(gmdate('H:i | d-m-Y', strtotime($url->lastmod))) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>An existing Sitemap was not found. Please generate a new one.</p>';
    }
}

function blocked_robotstxt($url) {
    $robots_txt_url = home_url('/robots.txt');
    $robots_txt = wp_remote_get($robots_txt_url);
    if (is_wp_error($robots_txt)) {
        return false; // No se pudo obtener el archivo robots.txt
    }

    $robots_txt_body = wp_remote_retrieve_body($robots_txt);
    $parsed_url = parse_url($url);
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';

    $lines = explode("\n", $robots_txt_body);
    $user_agent = '*';
    $disallow_paths = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (stripos($line, 'User-agent:') === 0) {
            $user_agent = trim(substr($line, 11));
        }
        if (stripos($line, 'Disallow:') === 0 && $user_agent === '*') {
            $disallow_path = trim(substr($line, 9));
            if ($disallow_path) {
                $disallow_paths[] = $disallow_path;
            }
        }
    }

    foreach ($disallow_paths as $disallow_path) {
        $disallow_regex = '#^' . str_replace(['*', '?'], ['.*', '\?'], $disallow_path) . '#';
        if (preg_match($disallow_regex, $path . $query)) {
            return true; // La URL está bloqueada por robots.txt
        }
    }

    return false; // La URL no está bloqueada
}

function vantag_generate_sitemap() {
    $args = array(
        'public'   => true,
        '_builtin' => false
    );
    $custom_post_types = get_post_types($args, 'names', 'and');

    $post_types = array_merge(array('post', 'page'), $custom_post_types);

    $posts = get_posts(array(
        'numberposts' => -1,
        'post_type' => $post_types,
        'post_status' => 'publish'
    ));

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

    foreach ($posts as $post) {
        $post_content = get_post_field('post_content', $post->ID);
        if (strpos($post_content, 'noindex') !== false) {
            continue;
        }

        $yoast_noindex = get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);
        $aioseo_noindex = get_post_meta($post->ID, '_aioseo_noindex', true);
        $rankmath_noindex = get_post_meta($post->ID, 'rank_math_robots', true);

        if ($yoast_noindex == '1' || $aioseo_noindex == '1' || $rankmath_noindex == '1') {
            continue;
        }

        $post_url = get_permalink($post->ID);
        if (!blocked_robotstxt($post_url)) {
            $url = $xml->addChild('url');
            $url->addChild('loc', $post_url);
            $url->addChild('lastmod', get_the_modified_time('c', $post->ID));
        }
    }

    $xml->asXML(ABSPATH . 'sitemap.xml');
}
?>