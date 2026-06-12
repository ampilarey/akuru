# MySQL Setup for Akuru Institute

## 🗄️ **Database Configuration**

The Akuru LMS is now configured to use **MySQL only** (no SQLite support).

### **Local Development Setup**

1. **Install MySQL** (if not already installed):
   ```bash
   # macOS with Homebrew
   brew install mysql
   brew services start mysql
   
   # Ubuntu/Debian
   sudo apt update
   sudo apt install mysql-server
   sudo systemctl start mysql
   
   # Windows
   # Download and install MySQL from https://dev.mysql.com/downloads/
   ```

2. **Create Database**:
   ```sql
   CREATE DATABASE akuru_institute;
   CREATE USER 'akuru_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON akuru_institute.* TO 'akuru_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Configure Environment**:
   Create `.env` file with MySQL settings:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=akuru_institute
   DB_USERNAME=akuru_user
   DB_PASSWORD=your_password
   ```

4. **Run Migrations**:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

### **Production Setup (cPanel)**

1. **Create MySQL Database**:
   - Go to cPanel → MySQL Databases
   - Create database: `akuruedu_akuru_institute`
   - Create user: `akuruedu_akuru_user`
   - Assign user to database with ALL PRIVILEGES

2. **Update Production .env**:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=akuruedu_akuru_institute
   DB_USERNAME=akuruedu_akuru_user
   DB_PASSWORD=your_secure_password
   ```

3. **Run Migrations on Production**:
   ```bash
   cd /home/akuruedu/akuru-institute
   php artisan migrate
   php artisan db:seed
   ```

### **Benefits of MySQL Only**

✅ **Consistency**: Same database engine in development and production  
✅ **Performance**: Better performance for complex queries  
✅ **Features**: Full SQL features and constraints  
✅ **Scalability**: Better handling of large datasets  
✅ **Backup**: Easier backup and restore procedures  
✅ **Monitoring**: Better monitoring and optimization tools  

### **Migration from SQLite**

If you have existing SQLite data, you can export and import:

1. **Export SQLite Data**:
   ```bash
   sqlite3 database/database.sqlite .dump > data_export.sql
   ```

2. **Convert for MySQL**:
   - Remove SQLite-specific syntax
   - Update data types if needed
   - Import to MySQL

### **Troubleshooting**

**Connection Issues**:
- Check MySQL service is running
- Verify credentials in `.env`
- Ensure database exists
- Check firewall settings

**Migration Issues**:
- Ensure MySQL user has CREATE/ALTER privileges
- Check for foreign key constraints
- Verify table structure matches expectations

### **Performance Optimization**

1. **MySQL Configuration**:
   ```ini
   # In my.cnf
   innodb_buffer_pool_size = 256M
   innodb_log_file_size = 64M
   query_cache_size = 32M
   ```

2. **Laravel Optimization**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

**Note**: All migrations and models are now optimized for MySQL. SQLite support has been completely removed.
