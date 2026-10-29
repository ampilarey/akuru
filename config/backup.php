<?php

/*
 * SPEC "Backups" (BK1, STATUS §5up): a nightly copy of the database and the
 * uploaded files, encrypted, kept on this server and — once a store is
 * configured — off it. A backup counts only once a restore has been tested
 * (`docs/deployment.md`).
 */
return [
    'enabled' => filter_var(env('BACKUP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // Where the copies are kept on this server. Never under public/.
    'local_path' => env('BACKUP_LOCAL_PATH', storage_path('app/backups')),

    // A filesystem disk for the copy off this server (e.g. 'backups', an
    // S3-compatible store set in config/filesystems.php). Empty: this server only.
    'disk' => env('BACKUP_DISK'),

    // The passphrase that encrypts each copy. Without one, APP_KEY is used —
    // keep a copy of whichever it is somewhere other than this server, or a
    // lost server takes the key with it.
    'passphrase' => env('BACKUP_PASSPHRASE'),

    // The uploaded and private files: everything under storage/app but the backups themselves.
    'files_root' => env('BACKUP_FILES_ROOT', storage_path('app')),
    'files_exclude' => ['backups', 'livewire-tmp', 'backup-work'],

    'binaries' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL_PATH', 'mysql'),
        'tar' => env('BACKUP_TAR_PATH', 'tar'),
    ],

    // Extra mysqldump options for a host that needs them (e.g. "--column-statistics=0"
    // for a MySQL 8 client against a MariaDB server). Space-separated.
    'mysqldump_options' => env('BACKUP_MYSQLDUMP_OPTIONS', ''),

    // Retention: the newest of each kept, the rest removed — here and off-site.
    'keep' => [
        'daily' => (int) env('BACKUP_KEEP_DAILY', 7),
        'weekly' => (int) env('BACKUP_KEEP_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_KEEP_MONTHLY', 6),
    ],

    // When the nightly run happens, Maldives time.
    'at' => env('BACKUP_AT', '03:00'),
];
