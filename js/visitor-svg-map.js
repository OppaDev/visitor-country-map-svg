// js/visitor-svg-map.js
jQuery(document).ready(function($) {
    if (typeof visitorSvgMapData === 'undefined') {
        console.warn('Visitor SVG Map Data (visitorSvgMapData) no encontrada. El mapa no se coloreará.');
        return;
    }

    const countriesToColor = visitorSvgMapData.countriesToColor;
    const defaultFillColor = visitorSvgMapData.defaultFillColor || '#E0E0E0';
    const borderColor = visitorSvgMapData.borderColor || '#FFFFFF';
    const countryData = visitorSvgMapData.countryData || {};

    // Buscar el SVG en diferentes contenedores posibles
    let $svgMap = $('#map-world');
    if ($svgMap.length === 0) {
        $svgMap = $('.svg-container svg');
    }
    if ($svgMap.length === 0) {
        $svgMap = $('.visitor-svg-map-container svg');
    }

    if ($svgMap.length === 0) {
        console.warn('SVG para el mapa de países no encontrado.');
        return;
    }

    console.log('SVG Map encontrado:', $svgMap);
    console.log('Países a colorear:', countriesToColor);
    console.log('Datos de países:', countryData);

    // Verificar cuántos paths hay en el SVG
    const allPaths = $svgMap.find('path[data-code]');
    console.log('Total de paths encontrados:', allPaths.length);

    // Crear tooltip
    let tooltip = $('<div class="visitor-map-tooltip"></div>').appendTo('body');

    // Buscar elementos path con data-code (formato del SVG actual)
    $svgMap.find('path[data-code]').each(function() {
        const $path = $(this);
        const countryCode = $path.data('code');

        console.log('Procesando país:', countryCode);

        // Aplicar estilos base
        $path.attr('stroke', borderColor);
        $path.attr('stroke-width', '0.5');
        $path.css({
            'cursor': 'pointer',
            'transition': 'all 0.3s ease'
        });

        // Aplicar colores
        if (countryCode && countriesToColor.hasOwnProperty(countryCode)) {
            console.log('Aplicando color', countriesToColor[countryCode], 'a', countryCode);
            $path.attr('fill', countriesToColor[countryCode]);
        } else {
            $path.attr('fill', defaultFillColor);
        }

        // Eventos del mouse para interactividad
        $path.on('mouseenter', function(e) {
            // Efecto hover
            const currentFill = $(this).attr('fill');
            const darkerFill = darkenColor(currentFill, 20);
            $(this).attr('fill', darkerFill);
            $(this).attr('stroke-width', '1');

            // Mostrar tooltip
            if (countryCode && countryData[countryCode]) {
                const data = countryData[countryCode];
                const tooltipContent = `
                    <strong>${data.name}</strong><br>
                    Visitas: ${data.visits.toLocaleString()}<br>
                    Código: ${countryCode}
                `;
                tooltip.html(tooltipContent).show();
            } else {
                // Mostrar tooltip básico para países sin datos
                const tooltipContent = `
                    <strong>País: ${countryCode}</strong><br>
                    Sin visitas registradas
                `;
                tooltip.html(tooltipContent).show();
            }
        });

        $path.on('mouseleave', function() {
            // Restaurar color original
            if (countryCode && countriesToColor.hasOwnProperty(countryCode)) {
                $(this).attr('fill', countriesToColor[countryCode]);
            } else {
                $(this).attr('fill', defaultFillColor);
            }
            $(this).attr('stroke-width', '0.5');
            
            // Ocultar tooltip
            tooltip.hide();
        });

        $path.on('mousemove', function(e) {
            // Actualizar posición del tooltip
            tooltip.css({
                left: e.pageX + 10,
                top: e.pageY - 10
            });
        });

        $path.on('click', function() {
            // Animación de click
            $(this).animate({
                'stroke-width': '2'
            }, 100).animate({
                'stroke-width': '0.5'
            }, 100);

            // Mostrar información detallada si existe
            if (countryCode && countryData[countryCode]) {
                showCountryDetails(countryData[countryCode], countryCode);
            }
        });
    });

    // Función para oscurecer color
    function darkenColor(color, percent) {
        if (color.startsWith('#')) {
            const num = parseInt(color.slice(1), 16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) - amt;
            const G = (num >> 8 & 0x00FF) - amt;
            const B = (num & 0x0000FF) - amt;
            return '#' + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
        }
        return color;
    }

    // Función para mostrar detalles del país
    function showCountryDetails(data, countryCode) {
        // Actualizar la información en la leyenda
        const $legend = $('.visitor-country-map-legend');
        let $detailsDiv = $legend.find('.country-details');
        
        if ($detailsDiv.length === 0) {
            $detailsDiv = $('<div class="country-details"></div>').appendTo($legend);
        }

        const percentage = calculatePercentage(data.visits);
        const detailsHtml = `
            <hr style="margin: 15px 0;">
            <h4 style="margin: 10px 0; color: #333;">Información Detallada:</h4>
            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; border-left: 4px solid #007cba;">
                <strong style="color: #007cba;">${data.name} (${countryCode})</strong><br>
                <span style="color: #666;">
                    Visitas totales: <strong>${data.visits.toLocaleString()}</strong><br>
                    Porcentaje del total: <strong>${percentage}%</strong>
                </span>
            </div>
        `;
        
        $detailsDiv.html(detailsHtml);
        
        // Scroll suave hacia la información
        $('html, body').animate({
            scrollTop: $detailsDiv.offset().top - 100
        }, 500);
    }

    // Función para calcular porcentaje
    function calculatePercentage(visits) {
        const totalVisits = Object.values(countryData).reduce((sum, country) => sum + country.visits, 0);
        return totalVisits > 0 ? ((visits / totalVisits) * 100).toFixed(1) : 0;
    }

    // Función para resaltar país en la leyenda
    function highlightLegendItem(countryCode) {
        $('.visitor-country-map-legend li').removeClass('highlighted');
        $(`.visitor-country-map-legend li[data-country="${countryCode}"]`).addClass('highlighted');
    }

    // Eventos para la leyenda
    $('.visitor-country-map-legend li').each(function() {
        const $item = $(this);
        const countryCode = $item.data('country');
        
        $item.css('cursor', 'pointer');
        
        $item.on('mouseenter', function() {
            if (countryCode) {
                const $countryPath = $svgMap.find(`path[data-code="${countryCode}"]`);
                if ($countryPath.length) {
                    const currentFill = $countryPath.attr('fill');
                    const darkerFill = darkenColor(currentFill, 20);
                    $countryPath.attr('fill', darkerFill);
                    $countryPath.attr('stroke-width', '1');
                }
            }
            $(this).css('background-color', '#f0f0f0');
        });

        $item.on('mouseleave', function() {
            if (countryCode) {
                const $countryPath = $svgMap.find(`path[data-code="${countryCode}"]`);
                if ($countryPath.length) {
                    if (countriesToColor.hasOwnProperty(countryCode)) {
                        $countryPath.attr('fill', countriesToColor[countryCode]);
                    } else {
                        $countryPath.attr('fill', defaultFillColor);
                    }
                    $countryPath.attr('stroke-width', '0.5');
                }
            }
            $(this).css('background-color', 'transparent');
        });

        $item.on('click', function() {
            if (countryCode && countryData[countryCode]) {
                showCountryDetails(countryData[countryCode], countryCode);
            }
        });
    });
});