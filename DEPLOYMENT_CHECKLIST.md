# WordPress Plugin Deployment Checklist

## 🔍 Pre-Deployment Testing

### 1. WordPress Environment Setup
- [ ] Install WordPress in local environment
- [ ] Copy plugin to `/wp-content/plugins/visitor-country-map-svg/`
- [ ] Verify all files are present:
  - [ ] `visitor-country-map-svg.php`
  - [ ] `js/visitor-svg-map.js`
  - [ ] `world.svg`
  - [ ] `README.md`

### 2. Plugin Activation Testing
- [ ] Activate plugin in WordPress admin
- [ ] Check for activation errors in debug.log
- [ ] Verify database table creation (`wp_visitor_countries`)
- [ ] Confirm admin menu appears under Settings

### 3. Functionality Testing
- [ ] Test admin panel access at **Settings > Visitor Country Map**
- [ ] Use "Simular Visita Aleatoria" button to generate test data
- [ ] Verify statistics table populates correctly
- [ ] Test cache clearing functionality

### 4. Shortcode Testing
- [ ] Create test page/post
- [ ] Add shortcode: `[visitor_country_map]`
- [ ] Verify SVG map loads and displays
- [ ] Test country highlighting for top 5 countries
- [ ] Verify tooltips and hover effects work
- [ ] Test responsive behavior on mobile

### 5. Advanced Testing
- [ ] Test with custom shortcode parameters:
  ```
  [visitor_country_map height="500px" map_max_width="800px" default_country_color="#F0F0F0"]
  ```
- [ ] Verify geolocation works with real visitor IPs
- [ ] Test duplicate visit prevention (same IP within 24 hours)
- [ ] Monitor performance with multiple concurrent visits

## 🐛 Troubleshooting Common Issues

### SVG Map Not Displaying
1. Check file path: `/wp-content/plugins/visitor-country-map-svg/world.svg`
2. Verify SVG contains `path[data-code]` elements
3. Check browser console for JavaScript errors
4. Ensure jQuery is loaded

### Geolocation Shows "Unknown"
1. Test with external IP (not localhost)
2. Check if APIs are accessible (firewall/hosting restrictions)
3. Verify internet connection
4. Check browser console for API errors

### Statistics Not Updating
1. Verify database table exists and has correct structure
2. Check WordPress debug logs for SQL errors
3. Test with different IP addresses
4. Clear plugin cache manually

### Admin Panel Not Accessible
1. Verify user has 'manage_options' capability
2. Check for PHP errors in WordPress debug log
3. Ensure plugin is properly activated

## ⚡ Performance Optimization

### Recommended Settings
- Cache duration: 10-30 minutes (adjustable in code)
- Database cleanup: Consider adding cron job for old data
- API rate limiting: Monitor geolocation API usage

### Monitoring Points
- Database query performance
- API response times
- Memory usage during peak traffic
- Cache hit rates

## 🔒 Security Checklist
- [ ] All user inputs sanitized
- [ ] Database queries use prepared statements
- [ ] Admin functions check user capabilities
- [ ] Nonce verification for admin actions
- [ ] File access restricted (no direct access to PHP files)

## 🚀 Production Deployment

### Final Steps
1. Test in staging environment identical to production
2. Backup production database before activation
3. Monitor error logs for first 24 hours after deployment
4. Set up monitoring for database growth and performance
5. Document any custom configurations for future reference

## 📊 Success Metrics
- [ ] Map displays correctly across different browsers
- [ ] Visitor countries are detected and recorded accurately
- [ ] Admin panel provides useful statistics
- [ ] Plugin doesn't impact site performance
- [ ] No PHP or JavaScript errors in logs
