<?php
/**
 * Plugin Name: Visitor Country Map - Top 5 SVG
 * Plugin URI: https://tusitio.com/
 * Description: Muestra un mapa SVG interactivo resaltando el top 5 de países con más visitas y una leyenda, cargando el SVG desde un archivo.
 * Version: 2.8
 * Author: Tu Nombre (Versión Corregida)
 * License: GPL2
 * 
 * CORRECCIONES APLICADAS EN v2.8:
 * ✅ Mapa SVG se visualiza correctamente en contenedor responsive
 * ✅ Geolocalización mejorada con múltiples APIs de respaldo
 * ✅ Sistema de conteo de visitas optimizado (evita duplicados por IP/día)
 * ✅ JavaScript actualizado para trabajar con formato SVG actual
 * ✅ UI moderna y responsive para móviles y desktop
 * ✅ Sistema de cache optimizado para mejor rendimiento
 * ✅ Prevención de errores y mejor manejo de excepciones
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// === LÓGICA DE BASE DE DATOS Y REGISTRO DE VISITAS ===

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
        first_visit datetime DEFAULT CURRENT_TIMESTAMP,
        last_visit datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY country_code (country_code),
        KEY visit_count (visit_count),
        KEY last_visit (last_visit)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (false === get_option('visitor_country_map_maptiler_api_key')) {
        add_option('visitor_country_map_maptiler_api_key', '');
    }
    
    // Opciones adicionales del plugin
    if (false === get_option('visitor_country_map_cache_duration')) {
        add_option('visitor_country_map_cache_duration', 30); // Cache por 30 minutos por defecto
    }
}

function get_visitor_country_map_country_details() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Obtener IP real si está detrás de un proxy
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    
    $ip = trim($ip);
    
    // Para pruebas locales - SIEMPRE usar IPs de prueba en localhost
    if (in_array($ip, ['127.0.0.1', '::1']) || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || empty($ip)) {
        // Array de IPs de diferentes países para pruebas
        $test_ips = [
            '8.8.8.8',         // Estados Unidos
            '190.233.248.204', // Perú
            '201.218.15.255',  // Argentina
            '181.65.163.255',  // Colombia
            '81.9.15.10',      // España
            '1.1.1.1',         // Australia
            '103.102.166.224', // Indonesia
            '200.115.53.193',  // México
            '189.54.134.97',   // Brasil
            '195.154.133.20'   // Francia
        ];
        $ip = $test_ips[array_rand($test_ips)];
        error_log("Visitor Map: Using test IP for local development: " . $ip);
    }
    
    // Intentar múltiples servicios de geolocalización
    $services = [
        "http://ip-api.com/json/{$ip}?fields=status,country,countryCode",
        "https://ipapi.co/{$ip}/json/",
        "https://api.country.is/{$ip}"
    ];
    
    foreach ($services as $service_url) {
        $response = wp_remote_get($service_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress Visitor Country Map Plugin'
            )
        ));
        
        if (is_wp_error($response)) { 
            error_log("Visitor Map: Error with service {$service_url}: " . $response->get_error_message());
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) { 
            error_log("Visitor Map: Invalid JSON from {$service_url}: " . $body);
            continue;
        }

        // Mapear diferentes formatos de respuesta
        $country_code = '';
        $country_name = '';
        
        // Para ip-api.com
        if (isset($data['countryCode']) && isset($data['country'])) {
            $country_code = strtoupper($data['countryCode']);
            $country_name = $data['country'];
        }
        // Para ipapi.co
        elseif (isset($data['country_code'])) {
            $country_code = strtoupper($data['country_code']);
            $country_name = $data['country_name'] ?? $data['country'] ?? '';
        }
        // Para country.is
        elseif (isset($data['country'])) {
            $country_code = strtoupper($data['country']);
            // Lista básica de nombres de países por código ISO
            $country_names = [
                'US' => 'United States', 'PE' => 'Peru', 'AR' => 'Argentina', 'CO' => 'Colombia',
                'ES' => 'Spain', 'AU' => 'Australia', 'ID' => 'Indonesia', 'MX' => 'Mexico',
                'BR' => 'Brazil', 'FR' => 'France', 'DE' => 'Germany', 'GB' => 'United Kingdom',
                'CA' => 'Canada', 'IN' => 'India', 'CN' => 'China', 'RU' => 'Russia',
                'JP' => 'Japan', 'IT' => 'Italy', 'NL' => 'Netherlands', 'SE' => 'Sweden'
            ];
            $country_name = $country_names[$country_code] ?? $country_code;
        }
        
        if (!empty($country_code) && !empty($country_name) && $country_code !== 'XX') {
            error_log("Visitor Map: Successfully detected country: {$country_name} ({$country_code}) for IP: {$ip}");
            return array('country_code' => $country_code, 'country_name' => $country_name);
        }
    }
    
    error_log("Visitor Map: Could not determine country for IP: " . $ip);
    return array('country_code' => 'XX', 'country_name' => 'Unknown');
}

function register_visitor_country_map_visit() { 
    // Verificaciones de seguridad y condiciones
    if (is_admin() || wp_doing_ajax() || is_robots() || is_feed() || is_trackback()) { 
        return; 
    }
    
    // Verificar si es un bot
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider|mediapartners/i', $_SERVER['HTTP_USER_AGENT'])) { 
        return; 
    }

    // Prevenir registros duplicados - Usar una clave única por IP y día
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $today = date('Y-m-d');
    $transient_key = 'visitor_country_registered_' . md5($ip . $today);
    
    if (get_transient($transient_key)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_countries';
    $country_data = get_visitor_country_map_country_details();

    if ($country_data['country_code'] === 'XX' || empty($country_data['country_code'])) { 
        error_log("Visitor Map: Not registering visit - Invalid country data");
        return; 
    }

    // Registrar o actualizar visita
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE country_code = %s", $country_data['country_code']));
    
    if ($existing) {
        $result = $wpdb->update($table_name,
            array(
                'visit_count' => $existing->visit_count + 1, 
                'last_visit' => current_time('mysql'),
                'country_name' => $country_data['country_name']
            ),
            array('country_code' => $country_data['country_code']),
            array('%d', '%s', '%s'), 
            array('%s')
        );
    } else {
        $result = $wpdb->insert($table_name,
            array(
                'country_code' => $country_data['country_code'], 
                'country_name' => $country_data['country_name'], 
                'visit_count' => 1, 
                'last_visit' => current_time('mysql'),
                'first_visit' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s')
        );
    }

    if ($result !== false) {
        // Establecer transient para evitar registros duplicados por 24 horas
        set_transient($transient_key, true, DAY_IN_SECONDS);
        
        // Log para debugging
        error_log("Visitor Map: Registered visit from " . $country_data['country_name'] . " (" . $country_data['country_code'] . ")");
        
        // Limpiar cache de estadísticas
        delete_transient('visitor_country_map_stats_cache');
        delete_transient('visitor_country_map_stats_cache_5');
    } else {
        error_log("Visitor Map: Error registering visit from " . $country_data['country_name']);
    }
}
add_action('wp', 'register_visitor_country_map_visit');

function get_visitor_country_map_statistics($limit = 0) {
    // Implementar cache para mejorar performance
    $cache_key = 'visitor_country_map_stats_cache' . ($limit > 0 ? '_' . $limit : '');
    $cached_results = get_transient($cache_key);
    
    if ($cached_results !== false) {
        return $cached_results;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_countries';
    $query = "SELECT country_code, country_name, visit_count, first_visit, last_visit FROM $table_name ORDER BY visit_count DESC";
    if ($limit > 0) { 
        $query .= $wpdb->prepare(" LIMIT %d", $limit); 
    }
    
    $results = $wpdb->get_results($query);
    
    // Cache por 10 minutos para development
    set_transient($cache_key, $results, 10 * MINUTE_IN_SECONDS);
    
    return $results;
}

// === SHORTCODE Y LÓGICA DEL MAPA SVG ===

function visitor_country_map_shortcode_output($atts) {
    $atts = shortcode_atts(array(
        'height' => '600px', 
        'map_max_width' => '100%', 
        'default_country_color' => '#E0E0E0', 
        'border_color' => '#FFFFFF', 
        'svg_file_name' => 'world.svg',
        'show_stats' => 'true',
        'animation' => 'true',
    ), $atts);

    wp_enqueue_script('visitor-country-svg-map-script', plugin_dir_url(__FILE__) . 'js/visitor-svg-map.js', array('jquery'), '2.8', true);
    
    $top_countries_data = get_visitor_country_map_statistics(5);
    $colors = ['#FF5733', '#33FF57', '#3357FF', '#FF33A1', '#F1C40F']; 
    
    $country_highlights_for_legend = [];
    $countries_to_color_js = [];
    $country_data_js = [];
    $total_visits = 0;

    foreach ($top_countries_data as $index => $country) {
        if (isset($colors[$index])) {
            $country_highlights_for_legend[] = [
                'code'   => $country->country_code,
                'name'   => $country->country_name,
                'visits' => (int) $country->visit_count,
                'color'  => $colors[$index],
                'last_visit' => $country->last_visit
            ];
            $countries_to_color_js[$country->country_code] = $colors[$index];
        }
    }

    // Incluir datos de todos los países para tooltips
    $all_countries_data = get_visitor_country_map_statistics();
    foreach ($all_countries_data as $country) {
        $country_data_js[$country->country_code] = [
            'name' => $country->country_name,
            'visits' => (int) $country->visit_count,
            'last_visit' => $country->last_visit
        ];
        $total_visits += (int) $country->visit_count;
    }

    wp_localize_script('visitor-country-svg-map-script', 'visitorSvgMapData', array(
        'countriesToColor'   => $countries_to_color_js,
        'defaultFillColor'   => esc_js($atts['default_country_color']),
        'borderColor'        => esc_js($atts['border_color']),
        'countryData'        => $country_data_js,
        'totalVisits'        => $total_visits,
        'animation'          => $atts['animation'] === 'true'
    ));

    // Cargar SVG desde archivo
    $svg_file_path = plugin_dir_path(__FILE__) . sanitize_file_name($atts['svg_file_name']);
    $svg_map_html = '';

    if (file_exists($svg_file_path) && is_readable($svg_file_path)) {
        $svg_content = file_get_contents($svg_file_path);
        
        if (strpos(strtolower(trim($svg_content)), '<svg') === 0) {
            // Asegurar que el SVG tenga un ID para JavaScript
            if (strpos($svg_content, 'id="map-world"') === false) {
                $svg_content = str_replace('<svg', '<svg id="map-world"', $svg_content);
            }
            $svg_map_html = '<div class="svg-container">' . $svg_content . '</div>';
        } else {
            $svg_map_html = '<p>Error: El archivo <code>' . esc_html($atts['svg_file_name']) . '</code> no parece tener el formato SVG esperado.</p>';
        }
    } else {
        $svg_map_html = '<p>Error: No se pudo cargar el archivo del mapa (<code>' . esc_html($atts['svg_file_name']) . '</code>).</p>';
        if (current_user_can('manage_options')) {
            $svg_map_html .= '<p>Ruta esperada: <code>' . esc_html($svg_file_path) . '</code></p>';
        }
    }

    ob_start();
    ?>
    <div class="visitor-country-map-wrapper" style="width: 100%; max-width: <?php echo esc_attr($atts['map_max_width']); ?>; margin: 0 auto;">
        <div class="visitor-svg-map-container" style="width: 100%; height: <?php echo esc_attr($atts['height']); ?>; background: #f8f9fa; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <?php echo $svg_map_html; ?>
        </div>
        
        <?php if ($atts['show_stats'] === 'true'): ?>
        <div class="visitor-country-map-legend" style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1.2em; color: #333; text-align: center; border-bottom: 2px solid #007cba; padding-bottom: 10px;">🌍 Top 5 Países Visitantes</h3>
            
            <?php if ($total_visits > 0): ?>
            <div style="margin-bottom: 15px; text-align: center; color: #666; font-size: 0.95em;">
                <strong>Total de visitas registradas: <?php echo number_format($total_visits); ?></strong>
            </div>
            <?php endif; ?>

            <ul id="visitor-map-top-list" style="list-style: none; padding: 0; margin: 0;">
                <?php if (empty($country_highlights_for_legend)): ?>
                    <li style="color: #777; text-align: center; padding: 20px;">
                        <em>Aún no hay datos de visitas registrados.</em>
                    </li>
                <?php else: ?>
                    <?php foreach ($country_highlights_for_legend as $index => $country): ?>
                        <li data-country="<?php echo esc_attr($country['code']); ?>" style="margin-bottom: 12px; display: flex; align-items: center; font-size: 0.95em; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 8px; transition: all 0.3s ease; cursor: pointer;">
                            <span style="display: inline-block; width: 24px; height: 24px; background-color: <?php echo esc_attr($country['color']); ?>; margin-right: 12px; border: 2px solid #fff; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                            <div style="flex: 1;">
                                <span style="font-weight: bold; color: #333; font-size: 1em;"><?php echo esc_html($country['name']); ?></span>
                                <div style="color: #666; font-size: 0.85em; margin-top: 2px;">
                                    <?php echo esc_html(number_format($country['visits'])); ?> visita<?php echo ($country['visits'] !== 1) ? 's' : ''; ?>
                                    <?php if ($total_visits > 0): ?>
                                        (<?php echo number_format(($country['visits'] / $total_visits) * 100, 1); ?>%)
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align: right; color: #007cba; font-weight: bold; font-size: 1.1em;">
                                #<?php echo $index + 1; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
        .visitor-country-map-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .svg-container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .svg-container svg {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
        }
        
        .visitor-map-tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 1000;
            pointer-events: none;
            display: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            max-width: 200px;
        }
        
        .visitor-country-map-legend li:hover {
            background: rgba(255,255,255,0.9) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .visitor-country-map-legend li.highlighted {
            background: rgba(0, 124, 186, 0.1) !important;
            border-left: 4px solid #007cba;
        }
        
        @media (max-width: 768px) {
            .visitor-svg-map-container {
                height: 300px !important;
            }
            
            .visitor-country-map-legend {
                padding: 15px !important;
            }
            
            .visitor-country-map-legend li {
                flex-direction: column;
                align-items: flex-start !important;
                text-align: left;
            }
            
            .visitor-country-map-legend li > div:last-child {
                margin-top: 5px;
                align-self: flex-end;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('visitor_country_map', 'visitor_country_map_shortcode_output'); 

// === PÁGINA DE ADMINISTRACIÓN ===
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
    
    // Manejar limpieza de caché
    if (isset($_POST['clear_cache']) && wp_verify_nonce($_POST['clear_cache_nonce'], 'clear_visitor_cache')) {
        // Limpiar todos los caches relacionados
        delete_transient('visitor_country_map_stats_cache');
        delete_transient('visitor_country_map_stats_cache_5');
        // Limpiar cualquier cache de estadísticas con límite
        for ($i = 1; $i <= 10; $i++) {
            delete_transient('visitor_country_map_stats_cache_' . $i);
        }
        add_settings_error('visitor_country_map_messages', 'cache_cleared', __('Cache limpiado correctamente.', 'visitor-country-map'), 'updated');
    }
    
    // Manejar forzar visita para pruebas
    if (isset($_POST['force_visit']) && wp_verify_nonce($_POST['force_visit_field'], 'force_visit_nonce')) {
        $test_ips = [
            '8.8.8.8' => 'Estados Unidos',
            '190.233.248.204' => 'Perú', 
            '201.218.15.255' => 'Argentina',
            '181.65.163.255' => 'Colombia',
            '81.9.15.10' => 'España'
        ];
        $random_ip = array_rand($test_ips);
        
        // Simular la IP temporalmente
        $original_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SERVER['REMOTE_ADDR'] = $random_ip;
        
        // Limpiar transients para permitir nuevo registro
        $today = date('Y-m-d');
        $transient_key = 'visitor_country_registered_' . md5($random_ip . $today);
        delete_transient($transient_key);
        
        // Registrar visita
        register_visitor_country_map_visit();
        
        // Restaurar IP original
        $_SERVER['REMOTE_ADDR'] = $original_ip;
        
        add_settings_error('visitor_country_map_messages', 'visit_simulated', '✅ Visita simulada registrada desde ' . $test_ips[$random_ip] . ' (' . $random_ip . ')', 'updated');
    }
    
    if (isset($_GET['settings-updated'])) {
        add_settings_error('visitor_country_map_messages', 'visitor_country_map_message', __('Ajustes guardados.', 'visitor-country-map'), 'updated');
    }
    settings_errors('visitor_country_map_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="notice notice-info">
            <p><strong>📋 Estado del Plugin:</strong></p>
            <ul>
                <li>✅ Plugin activo y funcionando</li>
                <li>📁 Archivo SVG: <?php echo file_exists(plugin_dir_path(__FILE__) . 'world.svg') ? '✅ Encontrado' : '❌ No encontrado'; ?></li>
                <li>📊 Total de países registrados: <?php echo count(get_visitor_country_map_statistics()); ?></li>
            </ul>
        </div>
        
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
        <p>Shortcode con opciones personalizadas:</p>
        <code>[visitor_country_map height="500px" map_max_width="800px" default_country_color="#DDDDDD"]</code>
        
        <hr>
        <h2>🔧 Herramientas de Desarrollo</h2>
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <h4>🧪 Simular Visita (Para Pruebas)</h4>
            <p>Haz clic para registrar una visita desde un país aleatorio:</p>
            <form method="post" style="margin: 10px 0;">
                <input type="hidden" name="force_visit" value="1">
                <?php wp_nonce_field('force_visit_nonce', 'force_visit_field'); ?>
                <button type="submit" class="button button-secondary">🌍 Simular Visita Aleatoria</button>
            </form>
        </div>
        
        <hr>
        <h2>📊 Estadísticas por País</h2>
        <?php
        $statistics = get_visitor_country_map_statistics();
        if (!empty($statistics)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>País</th>
                    <th>Código</th>
                    <th>Visitas</th>
                    <th>Primera Visita</th>
                    <th>Última Visita</th>
                    <th>%</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $total_visits = array_sum(array_column($statistics, 'visit_count'));
            foreach ($statistics as $stat): ?>
                <tr>
                    <td><?php echo esc_html($stat->country_name); ?></td>
                    <td><?php echo esc_html($stat->country_code); ?></td>
                    <td><strong><?php echo esc_html(number_format($stat->visit_count)); ?></strong></td>
                    <td><?php echo isset($stat->first_visit) ? esc_html(wp_date(get_option('date_format'), strtotime($stat->first_visit))) : 'N/A'; ?></td>
                    <td><?php echo esc_html(wp_date(get_option('date_format'), strtotime($stat->last_visit))); ?></td>
                    <td><?php echo $total_visits > 0 ? esc_html(number_format(($stat->visit_count / $total_visits) * 100, 1)) . '%' : '0%'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #007cba; border-radius: 5px;">
            <form method="post" style="display: inline;">
                <input type="hidden" name="clear_cache" value="1">
                <?php wp_nonce_field('clear_visitor_cache', 'clear_cache_nonce'); ?>
                <button type="submit" class="button button-secondary">🗑️ Limpiar Cache</button>
            </form>
        </div>
        
        <?php else: ?>
        <p>Aún no hay estadísticas de visitantes. 
            <br><small>💡 <strong>Consejo:</strong> Usa el botón "Simular Visita Aleatoria" arriba para generar datos de prueba.</small>
        </p>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_init', 'visitor_country_map_settings_init');
function visitor_country_map_settings_init() {
    register_setting('visitor_country_map_options', 'visitor_country_map_maptiler_api_key', 'sanitize_text_field');

    add_settings_section(
        'visitor_country_map_section_api',
        __('Configuración de API (Opcional)', 'visitor-country-map'),
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
           placeholder="Tu API Key (opcional)">
    <p class="description">
        API Key opcional para funcionalidades futuras.
    </p>
    <?php
}

// === DESINSTALACIÓN ===
register_uninstall_hook(__FILE__, 'visitor_country_map_uninstall');
function visitor_country_map_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_countries';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    delete_option('visitor_country_map_maptiler_api_key');
    delete_option('visitor_country_map_cache_duration');
    
    // Limpiar todos los transients
    delete_transient('visitor_country_map_stats_cache');
    for ($i = 1; $i <= 10; $i++) {
        delete_transient('visitor_country_map_stats_cache_' . $i);
    }
}
?>
