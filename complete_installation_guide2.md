# Complete TGirlsOnly Admin Page Builder System

## 🚀 Installation & Setup Guide

### Prerequisites

- **PHP**: 8.2 or higher
- **Laravel**: 12.x
- **Node.js**: 18.x or higher
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Redis**: 6.0+ (for caching and sessions)
- **Storage**: Cloud storage (AWS S3, DigitalOcean Spaces) or local

### Step 1: Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies  
npm install

# Install Page Builder specific packages
composer require intervention/image spatie/laravel-medialibrary
npm install sortablejs alpinejs
```

### Step 2: Environment Configuration

```env
# Page Builder Configuration
PAGEBUILDER_CACHE_ENABLED=true
PAGEBUILDER_CACHE_TTL=3600
PAGEBUILDER_MAX_FILE_SIZE=10240
PAGEBUILDER_STORAGE_DISK=public
PAGEBUILDER_ALLOW_CUSTOM_HTML=true
PAGEBUILDER_SANITIZE_HTML=true

# Performance Settings
PAGEBUILDER_OPTIMIZE_IMAGES=true
PAGEBUILDER_LAZY_LOADING=true
PAGEBUILDER_MINIFY_CSS=true
PAGEBUILDER_MINIFY_JS=true

# Security Settings
PAGEBUILDER_WEBHOOK_SECRET=your-webhook-secret-here
PAGEBUILDER_API_RATE_LIMIT=60

# Analytics Integration
PAGEBUILDER_ANALYTICS_ENABLED=true
PAGEBUILDER_GOOGLE_ANALYTICS_ID=GA-XXXXXXXXX
```

### Step 3: Run Installation Command

```bash
# Install the complete page builder system
php artisan pagebuilder:install --force

# Run specific installation steps
php artisan migrate
php artisan db:seed --class=PageBuilderSeeder
php artisan storage:link
```

### Step 4: Build Assets

```bash
# Development build
npm run dev

# Production build
npm run build

# Watch for changes (development)
npm run watch
```

### Step 5: Configure Admin Access

```bash
# Create admin user
php artisan make:admin-user

# Or manually in tinker
php artisan tinker
>>> $user = User::create(['name' => 'Admin', 'email' => 'admin@tgirlsonly.com', 'password' => bcrypt('secure-password')]);
>>> $user->assignRole('admin');
```

---

## 🧪 Testing Framework

### Running Tests

```bash
# Run all page builder tests
php artisan test --testsuite=PageBuilder

# Run specific test categories
php artisan test tests/Feature/PageBuilder/
php artisan test tests/Unit/PageBuilder/

# Run with coverage
php artisan test --coverage
```

### Test Files Structure

```
tests/
├── Feature/
│   ├── PageBuilder/
│   │   ├── PageCreationTest.php
│   │   ├── ComponentRenderingTest.php
│   │   ├── FormBuilderTest.php
│   │   ├── SalesPageTest.php
│   │   └── CategoryManagementTest.php
│   └── Api/
│       └── PageBuilderApiTest.php
└── Unit/
    ├── Services/
    │   ├── OptimizationServiceTest.php
    │   └── AnalyticsServiceTest.php
    └── Models/
        ├── PageTest.php
        └── PageBuilderComponentTest.php
```

### Example Test Cases

```php
// tests/Feature/PageBuilder/PageCreationTest.php
class PageCreationTest extends TestCase
{
    public function test_admin_can_create_page_with_components()
    {
        $admin = User::factory()->admin()->create();
        
        $response = $this->actingAs($admin)
            ->post('/admin/pages', [
                'title' => 'Test Page',
                'slug' => 'test-page',
                'page_type' => 'landing',
                'page_builder_data' => [
                    [
                        'type' => 'hero_section',
                        'data' => [
                            'title' => 'Welcome',
                            'subtitle' => 'Test subtitle'
                        ]
                    ]
                ]
            ]);
            
        $response->assertStatus(201);
        $this->assertDatabaseHas('pages', ['title' => 'Test Page']);
    }
    
    public function test_page_renders_correctly()
    {
        $page = Page::factory()->withHeroSection()->create();
        
        $response = $this->get("/pages/{$page->slug}");
        
        $response->assertStatus(200)
                ->assertSee($page->title)
                ->assertViewIs('pages.show');
    }
}
```

---

## 📊 Performance Monitoring

### Metrics Dashboard

Access your performance dashboard at `/admin/analytics/performance`

**Key Metrics Tracked:**
- Page load times
- Component render times  
- Database query performance
- Cache hit ratios
- Image optimization stats
- User engagement metrics

### Automated Optimization

```bash
# Run daily optimization
php artisan pagebuilder:optimize --all

# Optimize specific page
php artisan pagebuilder:optimize --page=homepage

# Generate performance reports
php artisan pagebuilder:report --format=json --days=30
```

### Monitoring Alerts

Configure alerts in `config/pagebuilder.php`:

```php
'monitoring' => [
    'performance_threshold' => 3000, // ms
    'error_threshold' => 5, // errors per minute
    'uptime_threshold' => 99.9, // percentage
    'alerts' => [
        'email' => ['admin@tgirlsonly.com'],
        'slack' => env('SLACK_WEBHOOK_URL'),
        'webhook' => env('MONITORING_WEBHOOK_URL')
    ]
]
```

---

## 🔌 API Documentation

### Authentication

All API endpoints require authentication via Laravel Sanctum:

```bash
# Get API token
POST /api/auth/token
{
    "email": "user@example.com",
    "password": "password",
    "device_name": "PageBuilder API"
}
```

### Core Endpoints

#### Pages API

```bash
# Get all published pages
GET /api/pages
Query Parameters:
- type: string (optional) - Filter by page type
- category: int (optional) - Filter by category ID
- per_page: int (default: 20) - Items per page

# Get specific page
GET /api/pages/{slug}

# Create page (admin only)
POST /api/pages
{
    "title": "New Page",
    "slug": "new-page",
    "page_type": "landing",
    "page_builder_data": [...],
    "is_published": true
}

# Update page (admin only)
PUT /api/pages/{id}

# Delete page (admin only)
DELETE /api/pages/{id}
```

#### Components API

```bash
# Get available components
GET /api/components

# Get component library
GET /api/components/library

# Create custom component (admin only)
POST /api/components
{
    "name": "Custom Component",
    "type": "custom_hero",
    "category": "headers",
    "html_template": "<div>...</div>",
    "props_schema": {...}
}
```

#### Forms API

```bash
# Submit form
POST /api/forms/{page_id}/submit
{
    "form_data": {
        "name": "John Doe",
        "email": "john@example.com",
        "message": "Hello!"
    }
}

# Get form submissions (admin only)
GET /api/forms/{page_id}/submissions
```

#### Analytics API

```bash
# Get page analytics
GET /api/analytics/pages/{page_id}
Query Parameters:
- days: int (default: 30) - Number of days to analyze

# Get overall analytics
GET /api/analytics/overview

# Generate analytics report
POST /api/analytics/reports
{
    "pages": [1, 2, 3],
    "metrics": ["views", "conversions"],
    "format": "json",
    "date_range": {
        "start": "2024-01-01",
        "end": "2024-01-31"
    }
}
```

### Webhooks

Configure webhooks to receive real-time updates:

```bash
POST /api/webhooks
{
    "url": "https://your-app.com/webhooks/pagebuilder",
    "events": ["page.created", "page.updated", "form.submitted"],
    "secret": "your-webhook-secret"
}
```

**Webhook Events:**
- `page.created` - New page published
- `page.updated` - Page content modified
- `page.deleted` - Page removed
- `form.submitted` - Form submission received
- `component.created` - New component added
- `analytics.daily` - Daily analytics summary

---

## 🛠️ Advanced Configuration

### Custom Component Development

#### 1. Create Component Class

```php
// app/PageBuilder/Components/CustomNewsletterComponent.php
namespace App\PageBuilder\Components;

use App\PageBuilder\Contracts\PageBuilderComponent;

class CustomNewsletterComponent implements PageBuilderComponent
{
    public function render(array $data): string
    {
        return view('components.page-builder.custom-newsletter', compact('data'))->render();
    }
    
    public function getSchema(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true],
            'description' => ['type' => 'text', 'required' => false],
            'placeholder' => ['type' => 'string', 'default' => 'Enter your email'],
            'button_text' => ['type' => 'string', 'default' => 'Subscribe']
        ];
    }
    
    public function getDefaultData(): array
    {
        return [
            'title' => 'Subscribe to our newsletter',
            'description' => 'Get the latest updates',
            'placeholder' => 'Enter your email',
            'button_text' => 'Subscribe'
        ];
    }
}
```

#### 2. Create Component Template

```blade
{{-- resources/views/components/page-builder/custom-newsletter.blade.php --}}
<section class="newsletter-section py-16 bg-blue-600">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">
            {{ $data['title'] ?? 'Subscribe to our newsletter' }}
        </h2>
        
        @if($data['description'] ?? false)
            <p class="text-xl text-blue-100 mb-8">
                {{ $data['description'] }}
            </p>
        @endif
        
        <form 
            class="flex flex-col sm:flex-row gap-4 max-w-md mx-auto"
            x-data="newsletter()"
            @submit.prevent="subscribe"
        >
            <input 
                type="email" 
                placeholder="{{ $data['placeholder'] ?? 'Enter your email' }}"
                x-model="email"
                required
                class="flex-1 px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-blue-300"
            >
            <button 
                type="submit"
                :disabled="loading"
                class="px-6 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-50"
            >
                <span x-show="!loading">{{ $data['button_text'] ?? 'Subscribe' }}</span>
                <span x-show="loading">Subscribing...</span>
            </button>
        </form>
    </div>
</section>

<script>
function newsletter() {
    return {
        email: '',
        loading: false,
        
        async subscribe() {
            this.loading = true;
            
            try {
                const response = await fetch('/api/newsletter/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ email: this.email })
                });
                
                if (response.ok) {
                    this.email = '';
                    // Show success message
                    this.$dispatch('newsletter-subscribed');
                }
            } catch (error) {
                console.error('Subscription failed:', error);
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
```

#### 3. Register Component

```php
// app/Providers/PageBuilderServiceProvider.php
public function boot()
{
    PageBuilderComponent::register('custom_newsletter', CustomNewsletterComponent::class);
}
```

### Theme System Integration

#### 1. Create Theme Configuration

```php
// config/pagebuilder-themes.php
return [
    'default' => [
        'name' => 'TGirlsOnly Default',
        'primary_color' => '#3B82F6',
        'secondary_color' => '#64748B',
        'font_family' => 'Inter',
        'components' => [
            'hero_section' => [
                'default_background' => 'gradient',
                'text_color' => 'white'
            ]
        ]
    ],
    
    'dark' => [
        'name' => 'Dark Theme',
        'primary_color' => '#1F2937',
        'secondary_color' => '#374151',
        'font_family' => 'Inter',
        'background_color' => '#111827'
    ],
    
    'creator_focused' => [
        'name' => 'Creator Focused',
        'primary_color' => '#EC4899',
        'secondary_color' => '#F472B6',
        'font_family' => 'Poppins',
        'components' => [
            'hero_section' => [
                'default_style' => 'split',
                'show_creator_stats' => true
            ]
        ]
    ]
];
```

#### 2. Theme Switcher Component

```php
// app/Livewire/ThemeSwitcher.php
class ThemeSwitcher extends Component
{
    public string $currentTheme = 'default';
    public array $availableThemes = [];
    
    public function mount()
    {
        $this->availableThemes = config('pagebuilder-themes');
        $this->currentTheme = session('pagebuilder_theme', 'default');
    }
    
    public function switchTheme(string $theme)
    {
        $this->currentTheme = $theme;
        session(['pagebuilder_theme' => $theme]);
        
        $this->dispatch('theme-changed', theme: $theme);
    }
    
    public function render()
    {
        return view('livewire.theme-switcher');
    }
}
```

---

## 🔧 Troubleshooting Guide

### Common Issues

#### 1. Components Not Loading

**Symptoms:** Components don't appear in sidebar or render incorrectly

**Solutions:**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Rebuild assets
npm run build

# Re-seed components
php artisan db:seed --class=PageBuilderSeeder --force
```

#### 2. File Upload Failures

**Symptoms:** Images/files fail to upload

**Solutions:**
```bash
# Check storage permissions
chmod -R 755 storage/
chmod -R 755 public/storage/

# Verify storage link
php artisan storage:link

# Check file size limits
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

#### 3. Performance Issues

**Symptoms:** Slow page loading, high server response times

**Solutions:**
```bash
# Enable optimization
php artisan pagebuilder:optimize --all

# Check query performance
php artisan telescope:install  # For debugging

# Enable Redis caching
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

#### 4. Form Submissions Not Working

**Symptoms:** Forms submit but data isn't saved

**Solutions:**
```bash
# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Verify form configuration
php artisan pagebuilder:validate-forms

# Check email configuration
php artisan tinker
>>> Mail::raw('test', function($m) { $m->to('test@example.com'); });
```

### Debug Mode

Enable comprehensive debugging:

```env
PAGEBUILDER_DEBUG=true
PAGEBUILDER_LOG_LEVEL=debug
```

### Health Check

```bash
# Run comprehensive health check
php artisan pagebuilder:health-check

# Check specific components
php artisan pagebuilder:health-check --component=database
php artisan pagebuilder:health-check --component=storage
php artisan pagebuilder:health-check --component=cache
```

---

## 📈 Production Deployment

### Pre-deployment Checklist

- [ ] Run all tests: `php artisan test`
- [ ] Optimize autoloader: `composer install --optimize-autoloader --no-dev`
- [ ] Cache configuration: `php artisan config:cache`
- [ ] Cache routes: `php artisan route:cache`
- [ ] Cache views: `php artisan view:cache`
- [ ] Build production assets: `npm run build`
- [ ] Set up monitoring and alerts
- [ ] Configure backup strategy
- [ ] Test error handling
- [ ] Verify SSL certificates
- [ ] Set up CDN for static assets

### Deployment Script

```bash
#!/bin/bash
# deploy-pagebuilder.sh

echo "🚀 Starting Page Builder deployment..."

# Backup database
php artisan pagebuilder:backup-database

# Put application in maintenance mode
php artisan down --message="Upgrading Page Builder..."

# Pull latest code
git pull origin main

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Run migrations
php artisan migrate --force

# Clear and cache everything
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize page builder
php artisan pagebuilder:optimize --all

# Restart services
sudo supervisorctl restart laravel-worker
sudo service nginx reload

# Bring application back online
php artisan up

echo "✅ Deployment completed successfully!"

# Run post-deployment tests
php artisan pagebuilder:health-check
```

### Monitoring Setup

```bash
# Install monitoring tools
composer require sentry/sentry-laravel
composer require spatie/laravel-uptime-monitor

# Configure monitoring
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
php artisan vendor:publish --provider="Spatie\UptimeMonitor\UptimeMonitorServiceProvider"

# Set up monitoring cron job
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔐 Security Best Practices

### Content Security

1. **Sanitize User Input**
   ```php
   // HTML Purifier integration
   'custom_html' => 'required|string|html_purifier',
   ```

2. **File Upload Security**
   ```php
   // Validate file types and scan for malware
   'upload' => 'required|file|mimes:jpg,png,pdf|max:2048|virus_scan',
   ```

3. **Rate Limiting**
   ```php
   // Protect API endpoints
   Route::middleware(['throttle:60,1'])->group(function () {
       Route::post('/api/forms/{page}/submit', [FormController::class, 'submit']);
   });
   ```

### Access Control

1. **Role-Based Permissions**
   ```php
   // Middleware for admin-only features
   Route::middleware(['auth', 'role:admin'])->group(function () {
       Route::resource('admin/pages', PageController::class);
   });
   ```

2. **Component Access Control**
   ```php
   // Restrict dangerous components
   if ($component['type'] === 'custom_html' && !auth()->user()->can('use-custom-html')) {
       throw new UnauthorizedException();
   }
   ```

---

## 📚 Additional Resources

### Documentation Links

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Livewire Documentation](https://livewire.laravel.com/docs)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Flux UI Documentation](https://flux-ui.com/docs)

### Community Resources

- [Page Builder Discord Channel](#)
- [GitHub Repository](https://github.com/tgirlsonly/page-builder)
- [Video Tutorials](#)
- [Community Examples](#)

### Support

For technical support and customization requests:

- **Email**: pagebuilder-support@tgirlsonly.com
- **Documentation**: [Internal Wiki Link]
- **Bug Reports**: [GitHub Issues]
- **Feature Requests**: [Feature Request Form]

---

## 🎯 Success Metrics

After successful installation, you should achieve:

- **Performance**: Page load times under 3 seconds
- **Conversion**: Form submission rates above 5%
- **SEO**: Page performance scores above 90
- **User Experience**: Bounce rates below 40%
- **Uptime**: 99.9% availability
- **Security**: Zero security vulnerabilities

## 🔄 Regular Maintenance

### Daily Tasks
- Monitor error logs
- Check performance metrics
- Review form submissions

### Weekly Tasks
- Run optimization scripts
- Update security patches
- Backup database and files

### Monthly Tasks
- Performance review and optimization
- User feedback analysis
- Feature usage statistics review
- Security audit

### Quarterly Tasks
- Full system backup and disaster recovery test
- Comprehensive security review
- Performance benchmarking
- User experience analysis

---

*This completes the comprehensive TGirlsOnly Admin Page Builder System. The system provides everything needed for a production-ready, scalable, and maintainable page builder platform specifically designed for content creator platforms.*