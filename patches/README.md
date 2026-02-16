# Vendor Patches

## PHP 8.5 PDO deprecation fix

`vendor/laravel/framework/config/database.php` has been patched to fix the PDO::MYSQL_ATTR_SSL_CA deprecation warning in PHP 8.5. The constant is now conditionally used: `Pdo\Mysql::ATTR_SSL_CA` on PHP 8.5+ and `PDO::MYSQL_ATTR_SSL_CA` on older versions.

**Note:** This patch will be overwritten when you run `composer update`. You may need to re-apply it after updating Laravel. The fix is also in `config/database.php`, which is under your control.
