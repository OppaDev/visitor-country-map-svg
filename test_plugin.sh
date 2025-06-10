#!/bin/bash
# WordPress Plugin Quick Test Script
# Run this in your WordPress root directory

echo "🧪 Testing WordPress Visitor Country Map Plugin..."

# Check WordPress installation
if [ ! -f "wp-config.php" ]; then
    echo "❌ Error: wp-config.php not found. Are you in the WordPress root directory?"
    exit 1
fi

PLUGIN_DIR="wp-content/plugins/visitor-country-map-svg"

# Check plugin directory
if [ ! -d "$PLUGIN_DIR" ]; then
    echo "❌ Error: Plugin directory not found at $PLUGIN_DIR"
    exit 1
fi

echo "✅ Plugin directory found"

# Check essential files
FILES=("visitor-country-map-svg.php" "js/visitor-svg-map.js" "world.svg" "README.md")

for file in "${FILES[@]}"; do
    if [ -f "$PLUGIN_DIR/$file" ]; then
        echo "✅ $file found"
    else
        echo "❌ $file missing"
    fi
done

# Check SVG file format
if grep -q "data-code" "$PLUGIN_DIR/world.svg" 2>/dev/null; then
    echo "✅ SVG file has proper data-code attributes"
else
    echo "⚠️  Warning: SVG file may not have proper data-code attributes"
fi

# Check WordPress database (requires wp-cli)
if command -v wp &> /dev/null; then
    echo "🔍 Checking database..."
    
    # Check if table exists
    if wp db query "SHOW TABLES LIKE 'wp_visitor_countries';" --skip-column-names | grep -q "wp_visitor_countries"; then
        echo "✅ Database table exists"
        
        # Show table structure
        echo "📊 Table structure:"
        wp db query "DESCRIBE wp_visitor_countries;"
        
        # Show record count
        COUNT=$(wp db query "SELECT COUNT(*) FROM wp_visitor_countries;" --skip-column-names)
        echo "📈 Current records: $COUNT"
    else
        echo "⚠️  Database table not found (will be created on plugin activation)"
    fi
else
    echo "⚠️  wp-cli not found, skipping database checks"
fi

echo ""
echo "🎯 Next Steps:"
echo "1. Activate plugin in WordPress admin"
echo "2. Go to Settings > Visitor Country Map"
echo "3. Use 'Simular Visita Aleatoria' to generate test data"
echo "4. Add [visitor_country_map] shortcode to a page"
echo "5. Test map display and functionality"
echo ""
echo "✅ Plugin files verification complete!"
