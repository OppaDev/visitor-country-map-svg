// js/visitor-svg-map.js
jQuery(document).ready(function($) {
    if (typeof visitorSvgMapData === 'undefined') {
        console.warn('Visitor SVG Map Data (visitorSvgMapData) no encontrada. El mapa no se coloreará.');
        return;
    }

    const countriesToColor = visitorSvgMapData.countriesToColor;
    const defaultFillColor = visitorSvgMapData.defaultFillColor || '#E0E0E0'; // Gris claro por defecto
    const borderColor = visitorSvgMapData.borderColor || '#FFFFFF'; // Blanco por defecto

    // Selecciona el SVG dentro del contenedor específico para evitar conflictos
    const $svgMap = $('#map-world svg'); 

    if ($svgMap.length === 0) {
        console.warn('SVG para el mapa de países no encontrado.');
        return;
    }

    $svgMap.find('path.jvm-region').each(function() {
        const $path = $(this);
        const countryCode = $path.data('code');

        // Aplicar color de borde general
        $path.attr('stroke', borderColor);
        // $path.attr('stroke-width', '0.5'); // Puedes ajustar el grosor del borde si es necesario

        if (countryCode && countriesToColor.hasOwnProperty(countryCode)) {
            $path.attr('fill', countriesToColor[countryCode]);
        } else {
            // Si el país no está en el top 5, usa el color por defecto
            // Esto sobrescribirá los colores `color-mix` originales del SVG para los no-top-5
            // Si quieres mantener los colores originales del SVG para no-top-5, comenta la siguiente línea.
            $path.attr('fill', defaultFillColor);
        }
    });

    // Si tu SVG usa variables CSS como --tblr-primary y no están definidas, los colores no se mostrarán.
    // El PHP incluye un <style> block con fallbacks para estas variables.
});