#!/bin/bash

# Akuru Institute - Deployment Preparation Script
# This script prepares your Laravel app for cPanel deployment

echo "🚀 Akuru Institute - Deployment Preparation"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: artisan file not found. Please run this script from the Laravel root directory.${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Step 1: Installing production dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Composer dependencies installed${NC}"
else
    echo -e "${RED}❌ Composer install failed${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}🏗️  Step 2: Building frontend assets...${NC}"
npm run build
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Assets built successfully${NC}"
else
    echo -e "${RED}❌ Asset build failed${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}🧹 Step 3: Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo -e "${GREEN}✅ Caches cleared${NC}"

echo ""
echo -e "${YELLOW}⚡ Step 4: Optimizing application...${NC}"
php artisan optimize
echo -e "${GREEN}✅ Application optimized${NC}"

echo ""
echo -e "${YELLOW}📝 Step 5: Generating production .env template...${NC}"
cat > .env.production << 'EOL'
APP_NAME="Akuru Institute"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Indian/Maldives
APP_URL=https://akuru.edu.mv

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=akuru_edu_mv_db
DB_USERNAME=akuru_edu_mv_user
DB_PASSWORD=

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.akuru.edu.mv

MAIL_MAILER=smtp
MAIL_HOST=smtp.akuru.edu.mv
MAIL_PORT=587
MAIL_USERNAME=noreply@akuru.edu.mv
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@akuru.edu.mv
MAIL_FROM_NAME="${APP_NAME}"
EOL

echo ""
echo -e "${YELLOW}🔑 Generating new APP_KEY for production...${NC}"
NEW_KEY=$(php artisan key:generate --show)
sed -i '' "s|APP_KEY=|APP_KEY=$NEW_KEY|" .env.production
echo -e "${GREEN}✅ Production .env template created with new key${NC}"

echo ""
echo -e "${YELLOW}📦 Step 6: Creating deployment archive...${NC}"
cd ..
zip -r akuru-deploy-$(date +%Y%m%d-%H%M%S).zip akuru-institute \
    -x "*.git*" \
    -x "*node_modules/*" \
    -x "*storage/logs/*" \
    -x "*storage/framework/cache/*" \
    -x "*storage/framework/sessions/*" \
    -x "*storage/framework/testing/*" \
    -x "*storage/framework/views/*" \
    -x "*.env" \
    -x "*tests/*" \
    -x "*.md" \
    -q

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Deployment archive created: akuru-deploy-$(date +%Y%m%d-%H%M%S).zip${NC}"
    echo ""
    echo -e "${GREEN}📦 Archive location: $(pwd)/akuru-deploy-*.zip${NC}"
else
    echo -e "${RED}❌ Failed to create archive${NC}"
    exit 1
fi

cd akuru-institute

echo ""
echo "=========================================="
echo -e "${GREEN}🎉 Preparation Complete!${NC}"
echo ""
echo "📋 Next Steps:"
echo "1. Upload the .zip file to your cPanel File Manager"
echo "2. Extract it in your home directory"
echo "3. Copy .env.production to .env and update database credentials"
echo "4. Follow the DEPLOYMENT_GUIDE.md for complete instructions"
echo ""
echo -e "${YELLOW}⚠️  Important:${NC}"
echo "- Update database credentials in .env on the server"
echo "- Run migrations after uploading"
echo "- Set up SSL certificate"
echo "- Configure cron jobs"
echo ""
echo "Good luck! 🚀"

