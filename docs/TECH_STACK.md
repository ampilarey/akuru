# Technology Stack - Akuru Institute LMS

## Overview
Complete breakdown of technologies, packages, and infrastructure used in the Akuru Institute Learning Management System.

---

## 🖥️ Backend Technologies

### Core Framework
- **Laravel 12.0** - PHP web framework
  - Version: `^12.0` (Latest stable)
  - Purpose: MVC architecture, routing, authentication, database ORM
  - Why chosen: Mature framework, excellent documentation, large community

### PHP & Server Requirements
- **PHP 8.4+** - Server-side programming language
  - Minimum: PHP 8.2
  - Recommended: PHP 8.4
  - Extensions required:
    - `mbstring` - Multi-byte string support
    - `openssl` - Encryption and SSL support
    - `pdo` - Database abstraction layer
    - `pdo_mysql` - MySQL database driver
    - `tokenizer` - PHP tokenizer
    - `xml` - XML parsing
    - `ctype` - Character type checking
    - `json` - JSON processing
    - `bcmath` - Arbitrary precision mathematics
    - `fileinfo` - File information detection
    - `zip` - ZIP archive handling

### Database
- **MySQL 8.0+** - Relational database
  - Current database: `akuru_institute`
  - Total tables: 66+
  - Features used:
    - Foreign key constraints
    - JSON columns
    - Full-text search
    - Indexing for performance

### Database ORM
- **Eloquent ORM** (Laravel built-in)
  - Active Record pattern
  - Relationship management
  - Query builder with fluent interface
  - Model factories for testing

---

## 🎨 Frontend Technologies

### Templating Engine
- **Blade** (Laravel built-in)
  - Template inheritance
  - Component system
  - Directives for PHP logic
  - Multi-language support

### CSS Framework
- **TailwindCSS 3.4.18** - Utility-first CSS framework
  - Responsive design utilities
  - Custom color palette for Islamic design
  - RTL support configuration
  - Forms and typography plugins

### JavaScript Libraries
- **Alpine.js 3.15.0** - Lightweight JavaScript framework
  - Reactive templating
  - Component-based architecture
  - Minimal bundle size
  - Perfect for server-rendered applications

### Build Tools
- **Vite 7.1.10** - Fast build tool
  - Hot Module Replacement (HMR)
  - Asset bundling and optimization
  - TypeScript support
  - Modern JavaScript compilation

### Additional Frontend Packages
- **@tailwindcss/forms** - Form styling utilities
- **@tailwindcss/typography** - Typography utilities
- **@fullcalendar/core** - Calendar component
- **@fullcalendar/daygrid** - Day grid view
- **@fullcalendar/timegrid** - Time grid view
- **@fullcalendar/interaction** - Calendar interactions
- **dayjs** - Date manipulation library
- **axios** - HTTP client for API requests

---

## 🔧 Backend Packages & Libraries

### Authentication & Authorization
- **Laravel Breeze 2.3** - Authentication scaffolding
  - Login/register forms
  - Password reset functionality
  - Email verification
  - Profile management

- **Spatie Laravel Permission 6.21** - Role and permission management
  - Role-based access control (RBAC)
  - Permission inheritance
  - Middleware integration
  - Artisan commands

### Multi-language Support
- **mcamara/laravel-localization 2.3** - Localization package
  - URL-based language switching (/en/, /ar/, /dv/)
  - RTL/LTR automatic detection
  - Translation file management
  - Locale middleware

### Islamic Calendar & Dates
- **alkoumi/laravel-hijri-date 1.0** - Hijri calendar support
  - Islamic date conversion
  - Hijri calendar display
  - Islamic event tracking
  - Prayer time awareness

### Image Processing
- **intervention/image 3.11** - Image manipulation
  - Image resizing and cropping
  - Format conversion
  - Watermarking
  - Optimization

### Push Notifications
- **laravel-notification-channels/fcm 5.1** - Firebase Cloud Messaging
  - Push notification delivery
  - Device registration
  - Topic-based messaging
  - Cross-platform support

### Social Authentication
- **laravel/socialite 5.23** - Social login
  - Google OAuth integration
  - Microsoft OAuth integration
  - Extensible for other providers
  - Security best practices

### HTTP Client
- **guzzlehttp/guzzle 7.10** - HTTP client library
  - API integrations
  - SMS Gateway communication
  - Webhook handling
  - Request/response handling

### Development Tools
- **laravel/tinker 2.10.1** - REPL for Laravel
  - Interactive PHP shell
  - Model testing
  - Quick debugging
  - Artisan command testing

---

## 🧪 Testing & Quality Assurance

### Testing Framework
- **PHPUnit 11.5.3** - Unit testing framework
  - Test-driven development
  - Database testing
  - API testing
  - Mock object support

### Code Quality Tools
- **Laravel Pint 1.24** - Code style fixer
  - PSR-12 compliance
  - Laravel coding standards
  - Automated code formatting

### Development Environment
- **Laravel Sail 1.41** - Docker development environment
  - Containerized services
  - Consistent development setup
  - Easy service management

### Logging & Debugging
- **Laravel Pail 1.2.2** - Real-time log monitoring
  - Live log streaming
  - Error tracking
  - Performance monitoring
  - Debug information

---

## 📱 External Integrations

### SMS Gateway
- **Custom SMS Integration** - Local SMS provider
  - OTP delivery for authentication
  - Bulk SMS broadcasting
  - Delivery status tracking
  - Cost monitoring

### Firebase Services
- **Firebase Cloud Messaging (FCM)** - Push notifications
  - Cross-platform notifications
  - Topic subscriptions
  - Device management
  - Analytics integration

### Email Services
- **SMTP Configuration** - Email delivery
  - Laravel Mail system
  - Queue-based sending
  - Template management
  - Delivery tracking

---

## 🏗️ Infrastructure & Hosting

### Web Server
- **Apache/Nginx** (via cPanel)
  - URL rewriting
  - SSL/TLS termination
  - Static file serving
  - Gzip compression

### Hosting Environment
- **cPanel Shared Hosting**
  - Domain: akuru.edu.mv
  - PHP version management
  - Database management
  - SSL certificate (Let's Encrypt)

### File Storage
- **Laravel Filesystem** - File abstraction
  - Local file storage
  - Public/private directories
  - File upload handling
  - Image optimization

### Caching
- **Laravel Cache** - Application caching
  - Database query caching
  - View caching
  - Route caching
  - Configuration caching

### Queue System
- **Laravel Queue** - Background job processing
  - Database driver (default)
  - SMS sending queues
  - Email sending queues
  - Job monitoring

---

## 🔐 Security Features

### Authentication Security
- **CSRF Protection** - Cross-site request forgery prevention
- **XSS Protection** - Cross-site scripting prevention
- **SQL Injection Prevention** - Parameterized queries
- **Rate Limiting** - Request throttling

### Data Protection
- **Password Hashing** - Bcrypt encryption
- **Session Security** - Secure session management
- **Input Validation** - Request validation rules
- **File Upload Security** - File type and size restrictions

### HTTPS & SSL
- **SSL/TLS Encryption** - Secure data transmission
- **HSTS Headers** - HTTP Strict Transport Security
- **Secure Cookies** - HTTPOnly and Secure flags

---

## 📊 Performance Considerations

### Database Optimization
- **Query Optimization** - Efficient database queries
- **Index Strategy** - Proper database indexing
- **Connection Pooling** - Database connection management
- **Query Caching** - Reduced database load

### Frontend Optimization
- **Asset Bundling** - Combined and minified assets
- **Image Optimization** - Compressed images
- **Lazy Loading** - Deferred resource loading
- **CDN Integration** - Content delivery network

### Caching Strategy
- **Application Caching** - Laravel cache system
- **Browser Caching** - HTTP caching headers
- **Database Query Caching** - Query result caching
- **View Caching** - Compiled view caching

---

## 🛠️ Development Environment

### Local Development
- **Laravel Herd** or **Valet** - Local development environment
- **Composer** - PHP dependency management
- **NPM** - Node.js package management
- **Git** - Version control

### Development Tools
- **VSCode** or **PhpStorm** - IDE recommendations
- **Laravel Debugbar** - Development debugging
- **Laravel Telescope** - Application insights
- **Browser DevTools** - Frontend debugging

### Environment Configuration
- **Environment Variables** - Configuration management
- **Database Seeding** - Test data generation
- **Artisan Commands** - Custom CLI tools
- **Migration System** - Database version control

---

## 📈 Monitoring & Analytics

### Application Monitoring
- **Laravel Logging** - Application event logging
- **Error Tracking** - Exception monitoring
- **Performance Metrics** - Response time tracking
- **User Analytics** - Usage patterns

### Database Monitoring
- **Query Logging** - Database performance tracking
- **Slow Query Detection** - Performance bottleneck identification
- **Connection Monitoring** - Database health checks
- **Backup Verification** - Data integrity checks

---

## 🚀 Deployment & CI/CD

### Deployment Process
- **Git-based Deployment** - Version control integration
- **Composer Autoload** - Optimized class loading
- **Asset Compilation** - Production asset building
- **Configuration Caching** - Optimized settings loading

### Production Optimizations
- **OPcache** - PHP opcode caching
- **Database Connection Pooling** - Efficient database usage
- **Queue Workers** - Background job processing
- **Cron Jobs** - Scheduled task execution

---

## 📋 Package Versions Summary

### Core Laravel Ecosystem
```json
{
    "laravel/framework": "^12.0",
    "laravel/breeze": "^2.3",
    "laravel/socialite": "^5.23",
    "laravel/tinker": "^2.10.1"
}
```

### Authentication & Permissions
```json
{
    "spatie/laravel-permission": "^6.21"
}
```

### Multi-language & Localization
```json
{
    "mcamara/laravel-localization": "^2.3",
    "alkoumi/laravel-hijri-date": "^1.0"
}
```

### Frontend Dependencies
```json
{
    "tailwindcss": "^3.1.0",
    "alpinejs": "^3.4.2",
    "vite": "^7.0.4",
    "@fullcalendar/core": "^6.1.19"
}
```

### External Integrations
```json
{
    "laravel-notification-channels/fcm": "^5.1",
    "intervention/image": "^3.11",
    "guzzlehttp/guzzle": "^7.10"
}
```

---

## 🔄 Updates & Maintenance

### Security Updates
- **Monthly Laravel Updates** - Framework security patches
- **Dependency Updates** - Package vulnerability fixes
- **PHP Version Updates** - Language security improvements
- **SSL Certificate Renewal** - Automated renewal process

### Performance Monitoring
- **Regular Performance Audits** - System optimization reviews
- **Database Performance Monitoring** - Query optimization
- **Frontend Performance Testing** - Page load optimization
- **User Experience Monitoring** - Real-time performance tracking

---

**Last Updated**: October 16, 2025  
**Laravel Version**: 12.0  
**PHP Version**: 8.4+  
**Database**: MySQL 8.0+  
**Total Dependencies**: 50+ packages
