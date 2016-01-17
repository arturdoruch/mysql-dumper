# MySql Dumper

Tool for making mysql database backups with bzip2 compressor.

### Usage

Dump or resotre mysql database.

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
$mysqlBackup = $mysqlDumper->getBackup();

// Get all backups by default sorted by create date. 
$mysqlBackup->all();

// Get all backups sorted with custom order
$mysqlBackup->all(function ($a, $b) {
        return $a->getFilename() > $b->getFilename();
    });

// Remove the backup file
$mysqlBackup->remove($filename);

// Removes all backups excepts the newest 5 backups.
$mysqlBackup->removeOld(5)
```
