<?php
/*
 * Plugin Name:       Multilingual Sitemap Generator
 * Plugin URI:        https://github.com/vantagdotes/
 * Description:       Advanced multilingual sitemap generator compatible with Yoast SEO, WPML, Polylang, and other translation plugins.
 * Version:           1.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            VANTAG.es
 * Author URI:        https://vantag.es
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       sitemap-gen
 */

defined('ABSPATH') or die('Exit...');

// Registrar opciones al activar el plugin
register_activation_hook(__FILE__, function() {
    add_option('sitemap_gen_settings', [
        'change_freq' => 'daily',
        'exclude_noindex' => true,
        'max_urls' => 50000,
        'multilingual' => true
    ]);
});

// Añadir menú en el backend
function vantag_sitemapgen_menu() {
    add_submenu_page(
        'tools.php',
        'Sitemap Generator',
        'Sitemap Generator',
        'manage_options',
        'sitemap_gen',
        'vantag_sitemapgen_page'
    );
}
add_action('admin_menu', 'vantag_sitemapgen_menu');

// Cargar estilos
function vantag_sitemapgen_enqueue_styles() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'sitemap_gen') return;
    wp_enqueue_style('sitemap-gen-style', plugins_url('assets/style.css', __FILE__), [], '1.1.0');
}
add_action('admin_enqueue_scripts', 'vantag_sitemapgen_enqueue_styles');

// Página de administración
function vantag_sitemapgen_page() {
    $settings = get_option('sitemap_gen_settings', ['change_freq' => 'daily', 'exclude_noindex' => true, 'max_urls' => 50000, 'multilingual' => true]);

    if (isset($_POST['generate_sitemap']) && check_admin_referer('generate_sitemap_nonce')) {
        try {
            $start_time = microtime(true);
            vantag_generate_sitemap($settings);
            $execution_time = round(microtime(true) - $start_time, 2);
            echo '<div class="notice notice-success"><p>Sitemap generated successfully in ' . $execution_time . ' seconds. <a href="' . esc_url(home_url('/sitemap.xml')) . '" target="_blank">View sitemap</a></p></div>';
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error generating sitemap: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    if (isset($_POST['save_settings']) && check_admin_referer('save_settings_nonce')) {
        $settings['change_freq'] = sanitize_text_field($_POST['change_freq']);
        $settings['exclude_noindex'] = isset($_POST['exclude_noindex']);
        $settings['max_urls'] = absint($_POST['max_urls']);
        $settings['multilingual'] = isset($_POST['multilingual']);
        update_option('sitemap_gen_settings', $settings);
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    ?>
    <div class="wrap sitemap-gen-wrap">
        <h1>Multilingual Sitemap Generator</h1>
        
        <div class="sitemap-gen-section">
            <h2>Generate Sitemap</h2>
            <form method="post">
                <?php wp_nonce_field('generate_sitemap_nonce'); ?>
                <p>Generate a fresh sitemap including all public content.</p>
                <input type="submit" name="generate_sitemap" class="button button-primary" value="Generate Sitemap">
            </form>
        </div>

        <div class="sitemap-gen-section">
            <h2>Settings</h2>
            <form method="post">
                <?php wp_nonce_field('save_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Change Frequency</th>
                        <td>
                            <select name="change_freq">
                                <?php foreach (['hourly', 'daily', 'weekly', 'monthly', 'yearly'] as $freq): ?>
                                    <option value="<?php echo $freq; ?>" <?php selected($settings['change_freq'], $freq); ?>><?php echo ucfirst($freq); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Exclude Noindex Pages</th>
                        <td><input type="checkbox" name="exclude_noindex" <?php checked($settings['exclude_noindex']); ?>> Exclude pages marked as noindex</td>
                    </tr>
                    <tr>
                        <th>Max URLs</th>
                        <td><input type="number" name="max_urls" value="<?php echo esc_attr($settings['max_urls']); ?>" min="1" max="50000"> (Max 50,000 per sitemap.org spec)</td>
                    </tr>
                    <tr>
                        <th>Multilingual Support</th>
                        <td><input type="checkbox" name="multilingual" <?php checked($settings['multilingual']); ?>> Include translated URLs (WPML/Polylang)</td>
                    </tr>
                </table>
                <input type="submit" name="save_settings" class="button button-secondary" value="Save Settings">
            </form>
        </div>

        <?php vantag_display_sitemap_preview(); ?>
    </div>
    <?php
}

// Mostrar vista previa del sitemap
function vantag_display_sitemap_preview() {
    $sitemap_path = ABSPATH . 'sitemap.xml';
    if (!file_exists($sitemap_path)) {
        echo '<p class="sitemap-gen-no-preview">No sitemap found. Generate one to see the preview.</p>';
        return;
    }

    $xml = simplexml_load_file($sitemap_path);
    if (!$xml) {
        echo '<p class="sitemap-gen-no-preview">Error loading sitemap XML.</p>';
        return;
    }

    echo '<div class="sitemap-gen-section">';
    echo '<h2>Sitemap Preview</h2>';
    echo '<table class="widefat fixed">';
    echo '<thead><tr><th>URL</th><th>Change Frequency</th></tr></thead>';
    echo '<tbody>';
    foreach ($xml->url as $url) {
        echo '<tr>';
        echo '<td><a href="' . esc_url($url->loc) . '" target="_blank">' . esc_html($url->loc) . '</a></td>';
        echo '<td>' . esc_html($url->changefreq ?? 'N/A') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

// Verificar bloqueo por robots.txt
function blocked_robotstxt($url) {
    $robots_txt = wp_remote_get(home_url('/robots.txt'), ['timeout' => 5]);
    if (is_wp_error($robots_txt)) return false;

    $body = wp_remote_retrieve_body($robots_txt);
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '' . (isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '');

    $lines = explode("\n", $body);
    $disallow_paths = [];
    $in_all_users = false;

    foreach ($lines as $line) {
        $line = trim($line);
        if (stripos($line, 'User-agent: *') === 0) $in_all_users = true;
        elseif (stripos($line, 'User-agent:') === 0) $in_all_users = false;
        elseif ($in_all_users && stripos($line, 'Disallow:') === 0) {
            $disallow_paths[] = trim(substr($line, 9));
        }
    }

    foreach ($disallow_paths as $path_pattern) {
        if (!$path_pattern) continue;
        $regex = '#^' . str_replace(['*', '?'], ['.*', '\?'], $path_pattern) . '#';
        if (preg_match($regex, $path)) return true;
    }
    return false;
}

// Obtener URL final tras redirecciones
function get_final_redirected_url($url, $max_redirects = 5) {
    if ($max_redirects <= 0) return $url;
    $response = wp_remote_head($url, ['redirection' => 0, 'timeout' => 5]);
    if (is_wp_error($response)) return $url;

    $location = wp_remote_retrieve_header($response, 'location');
    if ($location) return get_final_redirected_url($location, $max_redirects - 1);
    return $url;
}

// Generar el sitemap
function vantag_generate_sitemap($settings) {
    $post_types = array_merge(['post', 'page'], get_post_types(['public' => true, '_builtin' => false], 'names'));
    $posts = get_posts([
        'numberposts' => $settings['max_urls'],
        'post_type' => $post_types,
        'post_status' => 'publish',
        'suppress_filters' => false // Para soporte multilingüe
    ]);

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
    $url_count = 0;

    foreach ($posts as $post) {
        if ($settings['exclude_noindex']) {
            if (strpos($post->post_content, 'noindex') !== false ||
                get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true) == '1' ||
                get_post_meta($post->ID, '_aioseo_noindex', true) == '1' ||
                in_array('noindex', (array)get_post_meta($post->ID, 'rank_math_robots', true))) {
                continue;
            }
        }

        $url = get_final_redirected_url(get_permalink($post->ID));
        if (!blocked_robotstxt($url)) {
            $entry = $xml->addChild('url');
            $entry->addChild('loc', esc_url($url));
            $entry->addChild('changefreq', $settings['change_freq']);
            $url_count++;
        }

        // Soporte multilingüe (WPML/Polylang)
        if ($settings['multilingual']) {
            if (function_exists('icl_get_languages')) { // WPML
                $langs = icl_get_languages();
                foreach ($langs as $lang) {
                    $translated_url = apply_filters('wpml_permalink', $url, $lang['language_code']);
                    if ($translated_url && !blocked_robotstxt($translated_url) && $translated_url !== $url) {
                        $entry = $xml->addChild('url');
                        $entry->addChild('loc', esc_url($translated_url));
                        $entry->addChild('changefreq', $settings['change_freq']);
                        $url_count++;
                    }
                }
            } elseif (function_exists('pll_the_languages')) { // Polylang
                $langs = pll_the_languages(['raw' => 1]);
                foreach ($langs as $lang) {
                    $translated_id = pll_get_post($post->ID, $lang['slug']);
                    if ($translated_id && $translated_id != $post->ID) {
                        $translated_url = get_permalink($translated_id);
                        if (!blocked_robotstxt($translated_url)) {
                            $entry = $xml->addChild('url');
                            $entry->addChild('loc', esc_url($translated_url));
                            $entry->addChild('changefreq', $settings['change_freq']);
                            $url_count++;
                        }
                    }
                }
            }
        }

        if ($url_count >= $settings['max_urls']) break;
    }

    if ($url_count === 0) throw new Exception('No valid URLs found for the sitemap.');
    if (!is_writable(ABSPATH)) throw new Exception('Cannot write to root directory. Check permissions.');

    $xml->asXML(ABSPATH . 'sitemap.xml');
}