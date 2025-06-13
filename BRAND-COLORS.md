# Paleta de Colores CEPEIGE - Visitor Country Map Plugin

## 🎨 Identidad Visual Corporativa

Esta actualización del plugin implementa una paleta de colores completamente alineada con la marca **CEPEIGE** (Centro Panamericano de Estudios e Investigaciones Geográficas).

### 🎯 Colores Principales Extraídos del Logo

#### **Colores Primarios**
- **Naranja Corporativo**: `#FF6B35` - Color dominante del logo, usado para acentos principales
- **Azul Marino**: `#1E3A8A` - Color del globo terrestre y texto principal del logo
- **Azul Claro**: `#3B82F6` - Tonalidades del globo, usado para elementos secundarios
- **Azul Accent**: `#60A5FA` - Variación clara para hover y estados activos

#### **Colores de Soporte**
- **Blanco**: `#FFFFFF` - Backgrounds y contraste
- **Gris Claro**: `#F8FAFC` - Backgrounds sutiles
- **Gris Medio**: `#64748B` - Texto secundario
- **Gris Oscuro**: `#374151` - Texto principal alternativo

### 🌈 Implementación en Variables CSS

```css
:root {
    /* Colores principales de CEPEIGE */
    --cepeige-primary-orange: #FF6B35;
    --cepeige-primary-blue: #1E3A8A;
    --cepeige-secondary-blue: #3B82F6;
    --cepeige-accent-blue: #60A5FA;
    
    /* Gradientes corporativos */
    --cepeige-gradient-primary: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
    --cepeige-gradient-secondary: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
    --cepeige-gradient-bg: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
}
```

### 📊 Paleta para el Top 5 de Países

Los países del ranking ahora usan colores extraídos directamente de la marca:

1. **#FF6B35** - Naranja principal CEPEIGE (País #1)
2. **#1E3A8A** - Azul marino CEPEIGE (País #2)
3. **#3B82F6** - Azul claro CEPEIGE (País #3)
4. **#60A5FA** - Azul accent CEPEIGE (País #4)
5. **#FF8C42** - Naranja claro complementario (País #5)

### 🎨 Aplicación Visual

#### **Contenedor Principal**
- Fondo con gradiente sutil que refleja los colores corporativos
- Bordes y sombras con tonalidades azules de la marca
- Elementos hover con el naranja corporativo

#### **Panel de Estadísticas**
- Marco con el naranja corporativo `#FF6B35`
- Títulos en azul marino `#1E3A8A`
- Gradiente de fondo corporativo
- Elementos interactivos con colores de la marca

#### **Lista del Top 5**
- Indicadores de color con bordes corporativos
- Nombres de países en azul marino CEPEIGE
- Rankings con gradiente naranja-azul
- Estados hover con colores de la marca

#### **Tooltips y Detalles**
- Fondo con gradiente azul corporativo
- Bordes naranjas para destacar
- Tipografía en colores de la marca

### 🌙 Modo Oscuro CEPEIGE

El modo oscuro mantiene la identidad corporativa:
- Fondos oscuros con acentos en colores CEPEIGE
- Contraste adecuado manteniendo los colores de marca
- Elementos interactivos con naranja y azul corporativo

### 📱 Responsive y Accesibilidad

- Colores optimizados para diferentes tamaños de pantalla
- Contrastes que cumplen estándares WCAG
- Colores corporativos consistentes en todos los breakpoints

### 🎯 Consistencia de Marca

#### **Elementos que Reflejan CEPEIGE:**
- **Globo Terrestre**: Representado en los iconos y colores azules
- **Investigación Geografia**: Colores que evocan tierra (naranja) y océano (azul)
- **Profesionalismo**: Paleta sobria pero distintiva
- **Tradición**: Colores que reflejan la historia desde 1973

#### **Filosofía de Color:**
- **Naranja**: Representa el dinamismo y la innovación en investigación
- **Azul**: Simboliza la confianza, estabilidad y conocimiento geográfico
- **Gradientes**: Evocan la continuidad y conexión global

### 🔧 Uso Técnico

#### **En Shortcode:**
```php
[visitor_country_map default_country_color="#CBD5E1"]
```

#### **Personalización CSS:**
```css
.visitor-country-map-wrapper {
    --cepeige-primary-orange: #FF6B35; /* Personalizable */
}
```

### 📈 Beneficios de la Implementación

1. **Coherencia Visual**: Alineación total con la marca CEPEIGE
2. **Profesionalismo**: Colores corporativos que inspiran confianza
3. **Reconocimiento**: Fortalece la identidad visual institucional
4. **Versatilidad**: Paleta que funciona en diferentes contextos
5. **Accesibilidad**: Contrastes apropiados para todos los usuarios

---

**Versión**: 2.9.3  
**Implementación**: Paleta Corporativa CEPEIGE  
**Fecha**: Junio 2025  
**Autor**: OppaDev
