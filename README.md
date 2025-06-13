# Visitor Country Map - Top 5 SVG Plugin

## Versión 2.9.3 - Mejoras de Estructura CSS

### 🎯 Mejoras Implementadas

Esta versión introduce una **refactorización completa de la estructura CSS** para mejorar la mantenibilidad, el rendimiento y las mejores prácticas de WordPress.

#### ✅ Principales Cambios:

1. **Hoja de Estilos Separada**
   - Creado archivo `css/visitor-country-map-styles.css`
   - Eliminados todos los estilos inline (`style=""`) del HTML
   - Removido el bloque `<style>` embebido del shortcode
   - Mejor organización y mantenibilidad del código CSS

2. **Implementación de WordPress Standards**
   - Uso de `wp_enqueue_style()` para cargar la hoja de estilos
   - Implementación de `wp_add_inline_style()` para estilos dinámicos
   - Versionado correcto de archivos CSS/JS para cache busting
   - Dependencias adecuadas en el enqueue system

3. **HTML Más Limpio y Semántico**
   - Clases CSS específicas y descriptivas
   - Estructura HTML más clara y mantenible
   - Mejor separación de presentación y contenido
   - Reducción significativa del tamaño del HTML generado

4. **Estilos Dinámicos Mejorados**
   - Los atributos del shortcode (`height`, `map_max_width`) ahora se manejan con `wp_add_inline_style()`
   - CSS dinámico generado de forma segura con `esc_attr()`
   - Mejor especificidad de CSS

#### 📁 Estructura de Archivos:

```
visitor-country-map-svg/
├── css/
│   └── visitor-country-map-styles.css    # 🆕 Hoja de estilos separada
├── js/
│   └── visitor-svg-map.js                # JavaScript del mapa
├── visitor-country-map-svg.php           # 🔄 Plugin principal actualizado
└── world.svg                             # Archivo del mapa SVG
```

#### 🎨 Características de la Nueva Hoja de Estilos:

- **Organización modular** con comentarios explicativos
- **Responsive design** optimizado para móviles, tablets y desktop
- **Soporte para modo oscuro** (`prefers-color-scheme: dark`)
- **Mejoras de accesibilidad** (`prefers-reduced-motion: reduce`)
- **Transiciones y animaciones** suaves y profesionales
- **Clases semánticas** fáciles de entender y mantener

#### 🚀 Beneficios de los Cambios:

1. **Mejor Rendimiento**
   - CSS cacheado por el navegador
   - HTML más ligero y rápido de procesar
   - Menos código duplicado

2. **Mantenibilidad Mejorada**
   - Cambios de estilo centralizados en un archivo
   - Clases CSS reutilizables
   - Código más legible y organizado

3. **Mejores Prácticas de WordPress**
   - Uso correcto del sistema de enqueue
   - Versionado adecuado para cache busting
   - Compatibilidad con otros plugins y temas

4. **Flexibilidad Aumentada**
   - Fácil personalización mediante CSS
   - Temas pueden sobrescribir estilos fácilmente
   - Mejor especificidad de selectores CSS

### 📋 Clases CSS Principales:

```css
.visitor-country-map-wrapper          # Contenedor principal
.visitor-map-main-container           # Contenedor del layout principal
.visitor-svg-map-container            # Contenedor del mapa SVG
.visitor-country-map-legend           # Panel de estadísticas/leyenda
#visitor-map-top-list                 # Lista del top 5 países
.country-color-indicator              # Indicador de color del país
.country-info                         # Información del país
.country-name                         # Nombre del país
.country-stats                        # Estadísticas del país
.country-rank                         # Ranking del país
.visitor-map-tooltip                  # Tooltip del mapa
.country-details                      # Detalles expandidos del país
```

### 🔧 Uso del Shortcode:

El shortcode mantiene la misma funcionalidad y opciones:

```php
[visitor_country_map]

// Con opciones personalizadas:
[visitor_country_map height="500px" map_max_width="800px" default_country_color="#DDDDDD"]
```

#### Parámetros del Shortcode:

- `height` - Altura del contenedor del mapa (default: "600px")
- `map_max_width` - Ancho máximo del contenedor (default: "100%")
- `default_country_color` - Color de países sin datos (default: "#E0E0E0")
- `border_color` - Color de bordes del mapa (default: "#FFFFFF")
- `svg_file_name` - Nombre del archivo SVG (default: "world.svg")
- `show_stats` - Mostrar estadísticas (default: "true")
- `animation` - Habilitar animaciones (default: "true")

### 🎯 Migración y Compatibilidad:

- **Totalmente compatible** con versiones anteriores
- **No requiere cambios** en el uso del shortcode
- **Mantiene toda la funcionalidad** existente
- **Mejora automática** del rendimiento y mantenibilidad

### 📱 Responsive Design:

- **Tablets (≤1024px)**: Layout en columna, mapa de 400px
- **Móviles (≤768px)**: Mapa de 300px, padding reducido
- **Móviles pequeños (≤480px)**: Mapa de 250px, elementos optimizados

### 🌙 Características Adicionales:

- **Modo oscuro automático** basado en preferencias del sistema
- **Animaciones reducidas** para usuarios con sensibilidad al movimiento
- **Tooltips mejorados** con mejor posicionamiento
- **Estados hover** más suaves y profesionales

---

## Instalación y Uso

1. Subir la carpeta del plugin a `/wp-content/plugins/`
2. Activar el plugin desde el panel de WordPress
3. Usar el shortcode `[visitor_country_map]` en cualquier página o entrada
4. Personalizar mediante el panel de administración en `Ajustes > Visitor Country Map`

## Soporte

Para soporte técnico o reportar problemas, contactar al desarrollador del plugin.

---

**Versión**: 2.9.3  
**Compatibilidad**: WordPress 5.0+  
**Licencia**: GPL2
