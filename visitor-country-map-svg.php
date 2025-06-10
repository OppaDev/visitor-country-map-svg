<?php
/**
 * Plugin Name: Visitor Country Map - Top 5 SVG
 * Plugin URI: https://tusitio.com/
 * Description: Muestra un mapa SVG interactivo resaltando el top 5 de países con más visitas y una leyenda, cargando el SVG desde un archivo.
 * Version: 2.6 
 * Author: Tu Nombre (Adaptado para SVG desde archivo)
 * License: GPL2
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// === LÓGICA DE BASE DE DATOS Y REGISTRO DE VISITAS (SIN CAMBIOS) ===

register_activation_hook(__FILE__, 'visitor_country_map_create_table');
function visitor_country_map_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_countries';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        country_code varchar(2) NOT NULL,
        country_name varchar(100) NOT NULL,
        visit_count mediumint(9) NOT NULL DEFAULT 1,
        last_visit datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY country_code (country_code)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (false === get_option('visitor_country_map_maptiler_api_key')) {
        add_option('visitor_country_map_maptiler_api_key', '');
    }
}

function get_visitor_country_map_country_details() {
    $ip = $_SERVER['REMOTE_ADDR'];
    // Descomentar para pruebas locales con IPs aleatorias
    
    if (in_array($ip, ['127.0.0.1', '::1'])) {
        $test_ips = ['8.8.8.8', '190.233.248.204', '201.218.15.255', '181.65.163.255', '81.9.15.10', '1.1.1.1', '103.102.166.224']; // US, PE, AR, CO, ES, AU, ID
        $ip = $test_ips[array_rand($test_ips)];
    }
    
    $response = wp_remote_get("https://ipapi.co/{$ip}/json/", array('user-agent' => 'WordPress Visitor Country Map Plugin/' . get_bloginfo('version')));
    if (is_wp_error($response)) { return array('country_code' => 'XX', 'country_name' => 'Unknown (API Error)'); }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) { return array('country_code' => 'XX', 'country_name' => 'Unknown (JSON Error)');}
    if (isset($data['error']) && $data['error']) { return array('country_code' => 'XX', 'country_name' => 'Unknown (API Response Error: ' . esc_html(isset($data['reason']) ? $data['reason'] : 'Unknown reason') . ')' ); }
    if (isset($data['country_code']) && isset($data['country_name'])) {
        return array('country_code' => strtoupper($data['country_code']), 'country_name' => $data['country_name']);
    }
    return array('country_code' => 'XX', 'country_name' => 'Unknown');
}

function register_visitor_country_map_visit() { 
    if (is_admin() || wp_doing_ajax() || is_robots() || is_feed() || is_trackback()) { return; }
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider|mediapartners/i', $_SERVER['HTTP_USER_AGENT'])) { return; }

    if (is_user_logged_in() && get_transient('visitor_country_registered_' . get_current_user_id())) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_countries';
    $country_data = get_visitor_country_map_country_details();
    if ($country_data['country_code'] === 'XX' || empty($country_data['country_code'])) { return; }

    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE country_code = %s", $country_data['country_code']));
    if ($existing) {
        $wpdb->update($table_name,
            array('visit_count' => $existing->visit_count + 1, 'last_visit' => current_time('mysql')),
            array('country_code' => $country_data['country_code']),
            array('%d', '%s'), array('%s')
        );
    } else {
        $wpdb->insert($table_name,
            array('country_code' => $country_data['country_code'], 'country_name' => $country_data['country_name'], 'visit_count' => 1, 'last_visit' => current_time('mysql')),
            array('%s', '%s', '%d', '%s')
        );
    }

    if (is_user_logged_in()) {
        set_transient('visitor_country_registered_' . get_current_user_id(), true, HOUR_IN_SECONDS); 
    }
}
add_action('wp', 'register_visitor_country_map_visit');

function get_visitor_country_map_statistics($limit = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_countries';
    $query = "SELECT country_code, country_name, visit_count FROM $table_name ORDER BY visit_count DESC";
    if ($limit > 0) { $query .= $wpdb->prepare(" LIMIT %d", $limit); }
    return $wpdb->get_results($query);
}

// === SHORTCODE Y LÓGICA DEL MAPA SVG ===

function visitor_country_map_shortcode_output($atts) {
    $atts = shortcode_atts(array(
        'height' => 'auto', 
        'map_max_width' => '100%', 
        'default_country_color' => '#E0E0E0', 
        'border_color' => '#FFFFFF', 
    ), $atts);

    wp_enqueue_script('visitor-country-svg-map-script', plugin_dir_url(__FILE__) . 'js/visitor-svg-map.js', array('jquery'), '1.0', true);

    $top_countries_data = get_visitor_country_map_statistics(5);
    $colors = ['#FF5733', '#33FF57', '#3357FF', '#FF33A1', '#F1C40F']; 
    
    $country_highlights_for_legend = [];
    $countries_to_color_js = [];

    foreach ($top_countries_data as $index => $country) {
        if (isset($colors[$index])) {
            $country_highlights_for_legend[] = [
                'code'   => $country->country_code,
                'name'   => $country->country_name,
                'visits' => (int) $country->visit_count,
                'color'  => $colors[$index]
            ];
            $countries_to_color_js[$country->country_code] = $colors[$index];
        }
    }

    wp_localize_script('visitor-country-svg-map-script', 'visitorSvgMapData', array(
        'countriesToColor'   => $countries_to_color_js,
        'defaultFillColor'   => esc_js($atts['default_country_color']),
        'borderColor'        => esc_js($atts['border_color']),
    ));

    // --- MODIFICACIÓN PRINCIPAL: Cargar SVG desde archivo ---
    $svg_file_path = plugin_dir_path(__FILE__) . 'world.svg';
    $svg_map_html = '';

    if (file_exists($svg_file_path) && is_readable($svg_file_path)) {
        // Es importante envolver el contenido del SVG en el div con id="map-world"
        // si el archivo world.svg solo contiene la etiqueta <svg>...</svg>
        // Si world.svg ya contiene <div id="map-world">...</div>, puedes asignar directamente.
        $svg_content = file_get_contents($svg_file_path);
        // Asumimos que world.svg solo contiene la etiqueta <svg> y su contenido.
        // Si no es así y ya tiene el div#map-world, esta línea no es necesaria o debe ajustarse.
        if (strpos(strtolower(trim($svg_content)), '<svg') === 0) {
             $svg_map_html = '<div id="map-world" class="w-100 h-100 jvm-container" style="background-color: transparent;">' . $svg_content . '</div>';
        } else {
            // Si el archivo SVG ya tiene la estructura de div esperada, o es diferente
            // $svg_map_html = $svg_content; // O maneja el error
            $svg_map_html = '<p>Error: El archivo world.svg no parece tener el formato esperado (debe empezar con <svg>).</p>';
             if (current_user_can('manage_options')) {
                $svg_map_html .= '<p>Ruta comprobada: ' . esc_html($svg_file_path) . '</p>';
            }
        }

    } else {
        $svg_map_html = '<p>Error: No se pudo cargar el archivo del mapa (world.svg).';
        if (current_user_can('manage_options')) { // Mostrar más detalles a los administradores
            $svg_map_html .= ' Por favor, asegúrate de que el archivo <code>world.svg</code> exista en la raíz de la carpeta del plugin y sea legible.';
            $svg_map_html .= ' Ruta comprobada: ' . esc_html($svg_file_path);
        }
        $svg_map_html .= '</p>';
    }
    // --- FIN DE LA MODIFICACIÓN ---

    ob_start();
    ?>
    <div class="visitor-country-map-wrapper" style="display: flex; flex-direction: column; align-items: center; height: <?php echo esc_attr($atts['height']); ?>; gap: 20px; position: relative;">
        <div class="visitor-svg-map-container" style="max-width: <?php echo esc_attr($atts['map_max_width']); ?>; width: 100%; overflow: auto;">
            <?php echo $svg_map_html; // Muestra el SVG cargado desde el archivo ?>
        </div>
        <div class="visitor-country-map-legend" style="padding: 15px; background-color: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px; width: 100%; max-width: <?php echo esc_attr($atts['map_max_width']); ?>; box-sizing: border-box;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1em; color: #333;">Top 5 Países Visitantes:</h3>
            <ul id="visitor-map-top-list" style="list-style: none; padding: 0; margin: 0;">
                <?php if (empty($country_highlights_for_legend)): ?>
                    <li style="color: #777;">Aún no hay datos de visitas.</li>
                <?php else: ?>
                    <?php foreach ($country_highlights_for_legend as $country): ?>
                        <li style="margin-bottom: 8px; display: flex; align-items: center; font-size: 0.9em;">
                            <span style="display: inline-block; width: 18px; height: 18px; background-color: <?php echo esc_attr($country['color']); ?>; margin-right: 8px; border: 1px solid #ccc; border-radius: 3px;"></span>
                            <span style="font-weight: bold; color: #555;"><?php echo esc_html($country['name']); ?>:</span>
                            <span style="margin-left: 8px; color: #777;"><?php echo esc_html(number_format_i18n($country['visits'])); ?> visita<?php echo ($country['visits'] !== 1) ? 's' : ''; ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <style>
        .visitor-svg-map-container svg {
            width: 100%;
            height: auto; 
            display: block;
        }
        /* Fallbacks para variables CSS (Tabler) */
        :root {
            --tblr-primary: #206bc4; 
            --tblr-border-color: #e6e7e9; 
            --tblr-bg-surface-secondary: #f1f5f9; 
        }
        .jvm-region { 
            stroke: var(--tblr-border-color, #CCCCCC);
            stroke-width: 0.5; 
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('visitor_country_map', 'visitor_country_map_shortcode_output'); 


// === PÁGINA DE ADMINISTRACIÓN (SIN CAMBIOS RESPECTO A LA VERSIÓN ANTERIOR) ===
add_action('admin_menu', 'visitor_country_map_admin_menu');
function visitor_country_map_admin_menu() {
    add_options_page(
        'Visitor Country Map Settings',
        'Visitor Country Map',
        'manage_options',
        'visitor-country-map-settings',
        'visitor_country_map_admin_page_html'
    );
}

function visitor_country_map_admin_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['settings-updated'])) {
        add_settings_error('visitor_country_map_messages', 'visitor_country_map_message', __('Ajustes guardados.', 'visitor-country-map'), 'updated');
    }
    settings_errors('visitor_country_map_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('visitor_country_map_options');
            do_settings_sections('visitor_country_map_options');
            submit_button('Guardar Ajustes');
            ?>
        </form>
        
        <hr>
        <h2>Cómo usar el Mapa de Países SVG</h2>
        <p>Para mostrar el mapa SVG con el top 5 de países en cualquier página o entrada, usa el shortcode:</p>
        <code>[visitor_country_map]</code>
        <p>Puedes personalizar algunos aspectos:</p>
        <code>[visitor_country_map height="500px" map_max_width="800px" default_country_color="#DDDDDD"]</code>
        <p>Atributos disponibles:
            <ul>
                <li><code>height</code>: Altura total del contenedor (mapa + leyenda). Ejemplo: <code>500px</code>, <code>auto</code>.</li>
                <li><code>map_max_width</code>: Ancho máximo del mapa. Ejemplo: <code>800px</code>, <code>100%</code>.</li>
                <li><code>default_country_color</code>: Color hexadecimal para los países no destacados. Ejemplo: <code>#DDDDDD</code>.</li>
                <li><code>border_color</code>: Color hexadecimal para los bordes de los países en el SVG. Ejemplo: <code>#FFFFFF</code>.</li>
            </ul>
        </p>
        <p><strong>Nota:</strong> La API Key de MapTiler Cloud (configurable arriba) no es utilizada por este mapa SVG, pero puede ser útil para futuras funcionalidades o si deseas implementar otros tipos de mapas.</p>
        
        <hr>
        <h2>Estadísticas Completas por País</h2>
        <?php
        $statistics = get_visitor_country_map_statistics();
        if (!empty($statistics)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>País</th><th>Código</th><th>Visitas</th></tr></thead>
            <tbody>
            <?php foreach ($statistics as $stat): ?>
                <tr>
                    <td><?php echo esc_html($stat->country_name); ?></td>
                    <td><?php echo esc_html($stat->country_code); ?></td>
                    <td><strong><?php echo esc_html(number_format_i18n($stat->visit_count)); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Aún no hay estadísticas de visitantes.</p>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_init', 'visitor_country_map_settings_init');
function visitor_country_map_settings_init() {
    register_setting('visitor_country_map_options', 'visitor_country_map_maptiler_api_key', 'sanitize_text_field');

    add_settings_section(
        'visitor_country_map_section_api',
        __('Configuración de API (Opcional para SVG)', 'visitor-country-map'),
        null, 
        'visitor_country_map_options'
    );

    add_settings_field(
        'visitor_country_map_field_maptiler_api_key',
        __('MapTiler API Key', 'visitor-country-map'),
        'visitor_country_map_field_maptiler_api_key_cb',
        'visitor_country_map_options',
        'visitor_country_map_section_api',
        ['label_for' => 'visitor_country_map_maptiler_api_key_id']
    );
}

function visitor_country_map_field_maptiler_api_key_cb($args) {
    $option = get_option('visitor_country_map_maptiler_api_key');
    ?>
    <input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
           name="visitor_country_map_maptiler_api_key"
           value="<?php echo esc_attr($option); ?>"
           class="regular-text"
           placeholder="Tu API Key de MapTiler (opcional para SVG)">
    <p class="description">
        <?php _e('Introduce tu API Key de MapTiler Cloud. No es necesaria para el mapa SVG actual, pero podría usarse para otras visualizaciones de mapas.', 'visitor-country-map'); ?>
        <a href="https://cloud.maptiler.com/account/keys/" target="_blank">Obtén tu clave aquí</a>.
    </p>
    <?php
}

// === DESINSTALACIÓN (SIN CAMBIOS) ===
register_uninstall_hook(__FILE__, 'visitor_country_map_uninstall');
function visitor_country_map_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_countries';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    delete_option('visitor_country_map_maptiler_api_key');
}
?>