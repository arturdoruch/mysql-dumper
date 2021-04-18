# MySQL Dumper

Tool to backup MySQL databases with a bzip2 compressor.

### Usage

Dump or restore MySQL database.

```php
// To use bzip2 compressor, set the six argument to "true".
$mysqlDumper = new MySqlDumper($host, $name, $user, $pass, $backupDir, true);

// Dump mysql database and compress with bzip2
$mysqlDumper->dump();
 
// Restore database from backup file
$mysqlDumper->restore($filename);
```

Manage backup files.
```php
$backupManager = $mysqlDumper->getBackupManager();

// Get all backups by default sorted by create date. 
$backupManager->all();

// Get all backups sorted with custom order
$backupManager->all(function ($a, $b) {
    return $a->getFilename() > $b->getFilename();
});

// Remove the backup file
$backupManager->remove($filename);

// Removes all backups excepts the newest 5 backups.
$backupManager->removeOld(5)
```
