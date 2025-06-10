<?php
/**
 * Plugin Name: Visitor Country Map - Top 5 SVG
 * Plugin URI: https://tusitio.com/
 * Description: Muestra un mapa SVG interactivo resaltando el top 5 de países con más visitas y una leyenda.
 * Version: 2.5
 * Author: Tu Nombre (Adaptado para SVG)
 * License: GPL2
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// === LÓGICA DE BASE DE DATOS Y REGISTRO DE VISITAS (SIN CAMBIOS SIGNIFICATIVOS) ===

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

    // Añadir opción para la API Key de MapTiler si no existe (puede ser útil para futuras funcionalidades)
    if (false === get_option('visitor_country_map_maptiler_api_key')) {
        add_option('visitor_country_map_maptiler_api_key', '');
    }
}

function get_visitor_country_map_country_details() { // Renombrada para evitar conflicto si se usa la anterior
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
    if (isset($data['error']) && $data['error']) { return array('country_code' => 'XX', 'country_name' => 'Unknown (API Response Error: ' . esc_html($data['reason']) . ')' ); }
    if (isset($data['country_code']) && isset($data['country_name'])) {
        return array('country_code' => strtoupper($data['country_code']), 'country_name' => $data['country_name']);
    }
    return array('country_code' => 'XX', 'country_name' => 'Unknown');
}

function register_visitor_country_map_visit() { // Renombrada para evitar conflicto
    if (is_admin() || wp_doing_ajax() || is_robots() || is_feed() || is_trackback()) { return; }
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider|mediapartners/i', $_SERVER['HTTP_USER_AGENT'])) { return; }

    // Evitar registrar visitas en cada carga para usuarios logueados para no sesgar demasiado rápido
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
        set_transient('visitor_country_registered_' . get_current_user_id(), true, HOUR_IN_SECONDS); // Registrar una vez por hora para usuarios logueados
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
        'height' => 'auto', // Altura del contenedor del mapa y leyenda. 'auto' o un valor como '600px'.
        'map_max_width' => '100%', // Ancho máximo del mapa SVG.
        'default_country_color' => '#E0E0E0', // Color para países no destacados (gris claro)
        'border_color' => '#FFFFFF', // Color de borde para los países en el SVG
    ), $atts);

    // Encolar nuestro script personalizado para el mapa SVG
    wp_enqueue_script('visitor-country-svg-map-script', plugin_dir_url(__FILE__) . 'js/visitor-svg-map.js', array('jquery'), '1.0', true);

    $top_countries_data = get_visitor_country_map_statistics(5);
    
    // Paleta de 5 colores. Puedes personalizarlos.
    $colors = ['#FF5733', '#33FF57', '#3357FF', '#FF33A1', '#F1C40F']; // Naranja, Verde, Azul, Rosa, Amarillo
    
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

    // Pasar datos a JavaScript
    wp_localize_script('visitor-country-svg-map-script', 'visitorSvgMapData', array(
        'countriesToColor'   => $countries_to_color_js,
        'defaultFillColor'   => esc_js($atts['default_country_color']),
        'borderColor'        => esc_js($atts['border_color']),
    ));

    // SVG del mapa (es muy largo, considera ponerlo en un archivo separado y hacer un include)
    // Para este ejemplo, lo incluyo directamente. Asegúrate de que las comillas dentro del SVG estén escapadas si es necesario.
    // El SVG que proporcionaste ya tiene data-code y clases jvm-region, lo cual es perfecto.
    $svg_map_html = '
    <div id="map-world" class="w-100 h-100 jvm-container" style="background-color: transparent;">
        <svg width="797" height="342"> {/* Attributes from your SVG */}
            {/* Contenido completo de tu SVG aquí. Es demasiado largo para incluirlo completo en esta respuesta. */}
            {/* Ejemplo de cómo se vería una parte: */}
            <g id="jvm-regions-group" transform="scale(0.7760270086047388) translate(63.513055062973656, 0)">
                <path d="M651.84,230.21l-0.6,-2.0l-1.36,-1.71l-2.31,-0.11l-0.41,0.48l0.2,0.94l-0.53,0.99l-0.72,-0.36l-0.68,0.35l-1.2,-0.36l-0.37,-2.0l-0.81,-1.86l0.39,-1.46l-0.22,-0.47l-1.14,-0.53l0.29,-0.5l1.48,-0.94l0.03,-0.65l-1.55,-1.22l0.55,-1.14l1.61,0.94l1.04,0.15l0.18,1.54l0.34,0.35l5.64,0.63l-0.84,1.64l-1.22,0.34l-0.77,1.51l0.07,0.47l1.37,1.37l0.67,-0.19l0.42,-1.39l1.21,3.84l-0.03,1.21l-0.33,-0.15l-0.4,0.28Z" data-code="BD" fill="var(--tblr-bg-surface-secondary)" stroke="var(--tblr-border-color)" stroke-width="2" fill-rule="evenodd" class="jvm-region jvm-element"></path>
                <path d="M429.29,144.05l1.91,0.24l2.1,-0.63l2.63,1.99l-0.21,1.66l-0.69,0.4l-0.18,1.2l-1.66,-1.13l-1.39,0.15l-2.73,-2.7l-1.17,-0.18l-0.16,-0.52l1.54,-0.5Z" data-code="BE" fill="var(--tblr-bg-surface-secondary)" stroke="var(--tblr-border-color)" stroke-width="2" fill-rule="evenodd" class="jvm-region jvm-element"></path>
                {/* ... RESTO DE TUS PATHS DEL SVG ... */}
                 <path d="M781.12,166.87l1.81,0.68l1.62,-0.97l0.39,2.42l-3.35,0.75l-2.23,2.88l-3.63,-1.9l-0.56,0.2l-1.26,3.05l-2.16,0.03l-0.29,-2.51l1.08,-2.03l2.45,-0.16l0.37,-0.33l1.25,-5.94l2.47,2.71l2.03,1.12ZM773.56,187.34l-0.91,2.22l0.37,1.52l-1.14,1.75l-3.02,1.26l-4.58,0.27l-3.34,3.01l-1.25,-0.8l-0.09,-1.9l-0.46,-0.38l-4.35,0.62l-3.0,1.32l-2.85,0.05l-0.37,0.27l0.13,0.44l2.32,1.89l-1.54,4.34l-1.26,0.9l-0.79,-0.7l0.56,-2.27l-0.21,-0.45l-1.47,-0.75l-0.74,-1.4l2.12,-0.84l1.26,-1.7l2.45,-1.42l1.83,-1.91l4.78,-0.81l2.6,0.57l0.44,-0.21l2.39,-4.66l1.29,1.06l0.5,0.01l5.1,-4.02l1.69,-3.73l-0.38,-3.4l0.9,-1.61l2.14,-0.44l1.23,3.72l-0.07,2.18l-2.23,2.84l-0.04,3.16ZM757.78,196.26l0.19,0.56l-1.01,1.21l-1.16,-0.68l-1.28,0.65l-0.69,1.45l-1.02,-0.5l0.01,-0.93l1.14,-1.38l1.57,0.14l0.85,-0.98l1.4,0.46Z" data-code="JP" fill="var(--tblr-bg-surface-secondary)" stroke="var(--tblr-border-color)" stroke-width="2" fill-rule="evenodd" class="jvm-region jvm-element"></path>
                 <path d="M133.12,200.41l0.2,0.47l9.63,3.33l6.96,-0.02l0.4,-0.4l0.0,-0.74l3.77,0.0l3.55,2.93l1.39,2.83l1.52,1.04l2.08,0.82l0.47,-0.14l1.46,-2.0l1.73,-0.04l1.59,0.98l2.05,3.35l1.47,1.56l1.26,3.14l2.18,1.02l2.26,0.58l-1.18,3.72l-0.42,5.04l1.79,4.89l1.62,1.89l0.61,1.52l1.2,1.42l2.55,0.66l1.37,1.1l7.54,-1.89l1.86,-1.3l1.14,-4.3l4.1,-1.21l3.57,-0.11l0.32,0.3l-0.06,0.94l-1.26,1.45l-0.67,1.71l0.38,0.7l-0.72,2.27l-0.49,-0.3l-1.0,0.08l-1.0,1.39l-0.47,-0.11l-0.53,0.47l-4.26,-0.02l-0.4,0.4l-0.0,1.06l-1.1,0.26l0.1,0.44l1.82,1.44l0.56,0.91l-3.19,0.21l-1.21,2.09l0.24,0.72l-0.2,0.44l-2.24,-2.18l-1.45,-0.93l-2.22,-0.69l-1.52,0.22l-3.07,1.16l-10.55,-3.85l-2.86,-1.96l-3.78,-0.92l-1.08,-1.19l-2.62,-1.43l-1.18,-1.54l-0.38,-0.81l0.66,-0.63l-0.18,-0.53l0.52,-0.76l0.01,-0.91l-2.0,-3.82l-2.21,-2.63l-2.53,-2.09l-1.19,-1.62l-2.2,-1.17l-0.3,-0.43l0.34,-1.48l-0.21,-0.45l-1.23,-0.6l-1.36,-1.2l-0.59,-1.78l-1.54,-0.47l-2.44,-2.55l-0.16,-0.9l-1.33,-2.03l-0.84,-1.99l-0.16,-1.33l-1.81,-1.1l-0.97,0.05l-1.31,-0.7l-0.57,0.22l-0.4,1.12l0.72,3.77l3.51,3.89l0.28,0.78l0.53,0.26l0.41,1.43l1.33,1.73l1.58,1.41l0.8,2.39l1.43,2.41l0.13,1.32l0.37,0.36l1.04,0.08l1.67,2.28l-0.85,0.76l-0.66,-1.51l-1.68,-1.54l-2.91,-1.87l0.06,-1.82l-0.54,-1.68l-2.91,-2.03l-0.55,0.09l-1.95,-1.1l-0.88,-0.94l0.68,-0.08l0.93,-1.01l0.08,-1.78l-1.93,-1.94l-1.46,-0.77l-3.75,-7.56l4.88,-0.42Z" data-code="MX" fill="var(--tblr-bg-surface-secondary)" stroke="var(--tblr-border-color)" stroke-width="2" fill-rule="evenodd" class="jvm-region jvm-element"></path>
                 <path d="M892.72,99.2l1.31,0.53l1.41,-0.37l1.89,0.98l1.89,0.42l-1.32,0.58l-2.9,-1.53l-2.08,0.22l-0.26,-0.15l0.07,-0.67ZM183.22,150.47l0.37,1.47l1.12,0.85l4.23,0.7l2.39,0.98l2.17,-0.38l1.85,0.5l-1.55,0.65l-3.49,2.61l-0.16,0.77l0.5,0.39l2.33,-0.61l1.77,1.02l5.15,-2.4l-0.31,0.65l0.25,0.56l1.36,0.38l1.71,1.16l4.7,-0.88l0.67,0.85l1.31,0.21l0.58,0.58l-1.34,0.17l-2.18,-0.32l-3.6,0.89l-2.71,3.25l0.35,0.9l0.59,-0.0l0.55,-0.6l-1.36,4.65l0.29,3.09l0.67,1.58l0.61,0.45l1.77,-0.44l1.6,-1.96l0.14,-2.21l-0.82,-1.96l0.11,-1.13l1.19,-2.37l0.44,-0.33l0.48,0.75l0.4,-0.29l0.4,-1.37l0.6,-0.47l0.24,-0.8l1.69,0.49l1.65,1.08l-0.03,2.37l-1.27,1.13l-0.0,1.13l0.87,0.36l1.66,-1.29l0.5,0.17l0.5,2.6l-2.49,3.75l0.17,0.61l1.54,0.62l1.48,0.17l1.92,-0.44l4.72,-2.15l2.16,-1.8l-0.05,-1.24l0.75,-0.22l3.92,0.36l2.12,-1.05l0.21,-0.4l-0.28,-1.48l3.27,-2.4l8.32,-0.02l0.56,-0.82l1.9,-0.77l0.93,-1.51l0.74,-2.37l1.58,-1.98l0.92,0.62l1.47,-0.47l0.8,0.66l-0.0,4.09l1.96,2.6l-2.34,1.31l-5.37,2.09l-1.83,2.72l0.02,1.79l0.83,1.59l0.54,0.23l-6.19,0.94l-2.2,0.89l-0.23,0.48l0.45,0.29l2.99,-0.46l-2.19,0.56l-1.13,0.0l-0.15,-0.32l-0.48,0.08l-0.76,0.82l0.22,0.67l0.32,0.06l-0.41,1.62l-1.27,1.58l-1.48,-1.07l-0.49,-0.04l-0.16,0.46l0.52,1.58l0.61,0.59l0.03,0.79l-0.95,1.38l-1.21,-1.22l-0.27,-2.27l-0.35,-0.35l-0.42,0.25l-0.48,1.27l0.33,1.41l-0.97,-0.27l-0.48,0.24l0.18,0.5l1.52,0.83l0.1,2.52l0.79,0.51l0.52,3.42l-1.42,1.88l-2.47,0.8l-1.71,1.66l-1.31,0.25l-1.27,1.03l-0.43,0.99l-2.69,1.78l-2.64,3.03l-0.45,2.12l0.45,2.08l0.85,2.38l1.09,1.9l0.04,1.2l1.16,3.06l-0.18,2.69l-0.55,1.43l-0.47,0.21l-0.89,-0.23l-0.49,-1.18l-0.87,-0.56l-2.75,-5.16l0.48,-1.68l-0.72,-1.78l-2.01,-2.38l-1.12,-0.53l-2.72,1.18l-1.47,-1.35l-1.57,-0.68l-2.99,0.31l-2.17,-0.3l-2.0,0.19l-1.15,0.46l-0.19,0.58l0.39,0.63l0.14,1.34l-0.84,-0.2l-0.84,0.46l-1.58,-0.07l-2.08,-1.44l-2.09,0.33l-1.91,-0.62l-3.73,0.84l-2.39,2.07l-2.54,1.22l-1.45,1.41l-0.61,1.38l0.34,3.71l-0.29,0.02l-3.5,-1.33l-1.25,-3.11l-1.44,-1.5l-2.24,-3.56l-1.76,-1.09l-2.27,-0.01l-1.71,2.07l-1.76,-0.69l-1.16,-0.74l-1.52,-2.98l-3.93,-3.16l-4.34,-0.0l-0.4,0.4l-0.0,0.74l-6.5,0.02l-9.02,-3.14l-0.34,-0.71l-5.7,0.49l-0.43,-1.29l-1.62,-1.61l-1.14,-0.38l-0.55,-0.88l-1.28,-0.13l-1.01,-0.77l-2.22,-0.27l-0.43,-0.3l-0.36,-1.58l-2.4,-2.83l-2.01,-3.85l-0.06,-0.9l-2.92,-3.26l-0.33,-2.29l-1.3,-1.66l0.52,-2.37l-0.09,-2.57l-0.78,-2.3l0.95,-2.82l0.61,-5.68l-0.47,-4.27l-1.46,-4.08l3.19,0.79l1.26,2.83l0.69,0.08l0.69,-1.14l-1.1,-4.79l68.76,-0.0l0.4,-0.4l0.14,-0.86ZM32.44,67.52l1.73,1.97l0.55,0.05l0.99,-0.79l3.65,0.24l-0.09,0.62l0.32,0.45l3.83,0.77l2.61,-0.43l5.19,1.4l4.84,0.43l1.89,0.57l3.42,-0.7l6.14,1.87l-0.03,38.06l0.38,0.4l2.39,0.11l2.31,0.98l3.9,3.99l0.55,0.04l2.4,-2.03l2.16,-1.04l1.2,1.71l3.95,3.14l4.09,6.63l4.2,2.29l0.06,1.83l-1.02,1.23l-1.16,-1.08l-2.04,-1.03l-0.67,-2.89l-3.28,-3.03l-1.65,-3.57l-6.35,-0.32l-2.82,-1.01l-5.26,-3.85l-6.77,-2.04l-3.53,0.3l-4.81,-1.69l-3.25,-1.63l-2.78,0.8l-0.28,0.46l0.44,2.21l-3.91,0.96l-2.26,1.27l-2.3,0.65l-0.27,-1.65l1.05,-3.42l2.49,-1.09l0.16,-0.6l-0.69,-0.96l-0.55,-0.1l-3.19,2.12l-1.78,2.56l-3.55,2.61l-0.04,0.61l1.56,1.52l-2.07,2.29l-5.11,2.57l-0.77,1.66l-3.76,1.77l-0.92,1.73l-2.69,1.38l-1.81,-0.22l-6.95,3.32l-3.97,0.91l4.85,-2.5l2.59,-1.86l3.26,-0.52l1.19,-1.4l3.42,-2.1l2.59,-2.27l0.42,-2.68l1.23,-2.1l-0.04,-0.46l-0.45,-0.11l-2.68,1.03l-0.63,-0.49l-0.53,0.03l-1.05,1.04l-1.36,-1.54l-0.66,0.08l-0.32,0.62l-0.58,-1.14l-0.56,-0.16l-2.41,1.42l-1.07,-0.0l-0.17,-1.75l0.3,-1.71l-1.61,-1.33l-3.41,0.59l-1.96,-1.63l-1.57,-0.84l-0.15,-2.21l-1.7,-1.43l0.82,-1.88l1.99,-2.12l0.88,-1.92l1.71,-0.24l2.04,0.51l1.87,-1.77l1.91,0.25l1.91,-1.23l0.17,-0.43l-0.47,-1.82l-1.07,-0.7l1.39,-1.17l0.12,-0.45l-0.39,-0.26l-1.65,0.07l-2.66,0.88l-0.75,0.78l-1.92,-0.8l-3.46,0.44l-3.44,-0.91l-1.06,-1.61l-2.65,-1.99l2.91,-1.43l5.5,-2.0l1.52,0.0l-0.26,1.62l0.41,0.46l5.29,-0.16l0.3,-0.65l-2.03,-2.59l-3.14,-1.68l-1.79,-2.12l-2.4,-1.83l-3.09,-1.24l1.04,-1.69l4.23,-0.14l3.36,-2.07l0.73,-2.27l2.39,-1.99l2.42,-0.52l4.65,-1.97l2.46,0.23l3.71,-2.35l3.5,0.89ZM37.6,123.41l-2.25,1.23l-0.95,-0.69l-0.29,-1.24l3.21,-1.63l1.42,0.21l0.67,0.7l-1.8,1.42ZM31.06,234.03l0.98,0.47l0.74,0.87l-1.77,1.07l-0.44,-1.53l0.49,-0.89ZM29.34,232.07l0.18,0.05l0.08,0.05l-0.16,0.03l-0.11,-0.14ZM25.16,230.17l0.05,-0.03l0.18,0.22l-0.13,-0.01l-0.1,-0.18ZM5.89,113.26l-1.08,0.41l-2.21,-1.12l1.53,-0.4l1.62,0.28l0.14,0.83Z" data-code="US" fill="var(--tblr-bg-surface-secondary)" stroke="var(--tblr-border-color)" stroke-width="2" fill-rule="evenodd" class="jvm-region jvm-element"></path>
            </g>
            {/* Otros grupos <g> si los tienes, como jvm-regions-labels-group, etc. */}
        </svg>
    </div>';


    ob_start();
    ?>
    <div class="visitor-country-map-wrapper" style="display: flex; flex-direction: column; align-items: center; height: <?php echo esc_attr($atts['height']); ?>; gap: 20px; position: relative;">
        <div class="visitor-svg-map-container" style="max-width: <?php echo esc_attr($atts['map_max_width']); ?>; width: 100%; overflow: auto;">
            <?php echo $svg_map_html; // Muestra el SVG ?>
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
            height: auto; /* Para mantener la proporción */
            display: block;
        }
        /* Para asegurar que los CSS variables de Tabler (si los usas) estén definidos o usar fallbacks */
        :root {
            --tblr-primary: #206bc4; /* Color primario de Tabler por defecto */
            --tblr-border-color: #e6e7e9; /* Color de borde de Tabler por defecto */
            --tblr-bg-surface-secondary: #f1f5f9; /* Un gris claro para países no destacados */
        }
        .jvm-region { /* Para asegurar que el borde sea visible si JS no lo establece */
            stroke: var(--tblr-border-color, #CCCCCC);
            stroke-width: 0.5; /* Ajusta según sea necesario */
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('visitor_country_map', 'visitor_country_map_shortcode_output'); // Shortcode renombrado


// === PÁGINA DE ADMINISTRACIÓN (Adaptada) ===
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

// === DESINSTALACIÓN ===
register_uninstall_hook(__FILE__, 'visitor_country_map_uninstall');
function visitor_country_map_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_countries';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    delete_option('visitor_country_map_maptiler_api_key');
}
?>