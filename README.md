# Visitor Country Map SVG - WordPress Plugin

## Descripción

Plugin de WordPress que muestra un mapa SVG interactivo destacando los top 5 países con más visitas de tu sitio web. El plugin registra automáticamente las visitas de cada país y proporciona una interfaz visual atractiva para mostrar estadísticas de visitantes.

## Características Principales

### 📊 **Registro Automático de Visitas**
- Detección automática del país del visitante usando múltiples servicios de geolocalización
- Prevención de registros duplicados por IP/sesión
- Filtrado de bots y crawlers
- Transacciones de base de datos para integridad de datos

### 🗺️ **Mapa Interactivo SVG**
- Mapa mundial SVG completamente interactivo
- Colores personalizables para países destacados
- Efectos hover con información detallada
- Tooltips con datos de visitas
- Animaciones suaves y responsive

### 📈 **Estadísticas Avanzadas**
- Top 5 países con más visitas
- Porcentajes de participación
- Fechas de primera y última visita
- Sistema de caché para optimizar rendimiento
- Panel de administración completo

### ⚙️ **Configuración Flexible**
- Múltiples opciones de personalización via shortcode
- Colores personalizables
- Dimensiones ajustables
- Archivos SVG intercambiables

## Instalación

1. Descargar o clonar el plugin en la carpeta `wp-content/plugins/`
2. Activar el plugin desde el panel de administración de WordPress
3. Asegurarse de que el archivo `world.svg` esté presente en la raíz del plugin

## Uso

### Shortcode Básico
```
[visitor_country_map]
```

### Shortcode con Opciones
```
[visitor_country_map 
    height="600px" 
    map_max_width="900px" 
    default_country_color="#E8E8E8" 
    border_color="#FFFFFF"
    svg_file_name="world.svg"
    show_stats="true"
    animation="true"
]
```

### Parámetros Disponibles

| Parámetro | Descripción | Valor por Defecto |
|-----------|-------------|-------------------|
| `height` | Altura total del contenedor | `auto` |
| `map_max_width` | Ancho máximo del mapa | `100%` |
| `default_country_color` | Color hexadecimal para países no destacados | `#E0E0E0` |
| `border_color` | Color de los bordes del mapa | `#FFFFFF` |
| `svg_file_name` | Nombre del archivo SVG a cargar | `world.svg` |
| `show_stats` | Mostrar estadísticas y leyenda | `true` |
| `animation` | Habilitar animaciones | `true` |

## Funcionalidades Técnicas

### Sistema de Geolocalización
El plugin utiliza múltiples servicios de geolocalización para máxima precisión:
- ipapi.co
- ipgeolocation.io
- ip-api.com

### Caché Inteligente
- Cache automático de estadísticas por 30 minutos
- Limpieza manual de caché desde el panel de administración
- Optimización de consultas a base de datos

### Seguridad
- Validación de datos de entrada
- Sanitización de parámetros
- Prevención de inyección SQL
- Verificación de permisos de usuario

## Estructura de Base de Datos

La tabla `wp_visitor_countries` almacena:
- `country_code`: Código ISO de 2 letras del país
- `country_name`: Nombre completo del país
- `visit_count`: Número total de visitas
- `first_visit`: Fecha y hora de la primera visita
- `last_visit`: Fecha y hora de la última visita

## Panel de Administración

Accesible desde **Ajustes > Visitor Country Map**:
- Configuración de API Keys (opcional)
- Estadísticas completas por país
- Limpieza manual de caché
- Instrucciones de uso del shortcode

## Interactividad del Mapa

### Efectos del Mouse
- **Hover**: Cambia color del país y muestra tooltip
- **Click**: Muestra información detallada del país
- **Sincronización**: Hover en la leyenda resalta el país correspondiente

### Información Mostrada
- Nombre del país
- Código del país
- Número total de visitas
- Porcentaje del total de visitas

## Responsive Design

El plugin está optimizado para:
- Dispositivos móviles
- Tablets
- Pantallas de escritorio
- Diferentes resoluciones

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- Archivo SVG del mapa mundial

## Archivos del Plugin

```
visitor-country-map-svg/
├── visitor-country-map-svg.php    # Archivo principal
├── js/
│   └── visitor-svg-map.js         # JavaScript para interactividad
├── world.svg                      # Mapa SVG del mundo
└── README.md                      # Este archivo
```

## Personalización Avanzada

### Cambiar Colores del Top 5
Los colores se pueden modificar en el archivo PHP:
```php
$colors = ['#FF5733', '#33FF57', '#3357FF', '#FF33A1', '#F1C40F'];
```

### Usar un SVG Personalizado
1. Colocar el archivo SVG en la raíz del plugin
2. Asegurar que los países tengan atributos `data-code` con códigos ISO
3. Usar el parámetro `svg_file_name` en el shortcode

### Modificar Duración del Cache
```php
add_option('visitor_country_map_cache_duration', 60); // 60 minutos
```

## Solución de Problemas

### El mapa no se colorea
- Verificar que el archivo SVG existe y es accesible
- Comprobar que los países en el SVG tengan `data-code` correcto
- Revisar la consola del navegador para errores JavaScript

### No se registran visitas
- Verificar que las IPs no sean locales (127.0.0.1, localhost)
- Comprobar que los servicios de geolocalización estén disponibles
- Revisar logs de error de WordPress

### Problemas de rendimiento
- Limpiar caché desde el panel de administración
- Verificar que el cache esté funcionando
- Considerar aumentar la duración del cache

## Changelog

### Versión 2.6 (Actual)
- ✅ Interactividad completa del mapa
- ✅ Tooltips informativos
- ✅ Sistema de caché optimizado
- ✅ Mejoras en la base de datos
- ✅ Panel de administración mejorado
- ✅ Efectos hover y animaciones
- ✅ Responsive design completo
- ✅ Sincronización mapa-leyenda

### Mejoras Implementadas
- **JavaScript avanzado**: Interactividad completa con efectos hover, tooltips y sincronización
- **Base de datos mejorada**: Campos adicionales para primera/última visita
- **Sistema de transacciones**: Mayor integridad de datos
- **Cache inteligente**: Optimización del rendimiento
- **Panel de administración**: Estadísticas detalladas y limpieza de cache
- **Responsive**: Adaptación completa a dispositivos móviles

## Soporte

Para soporte técnico o reportar bugs, contactar al desarrollador o crear un issue en el repositorio del proyecto.

## Licencia

GPL2 - Compatible con WordPress
