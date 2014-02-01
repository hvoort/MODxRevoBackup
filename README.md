Backup (and restore) Script
===============
_for MODx Revolution (traditional) with mysql database_

Purpose:
- Backup (**complete**; files and database)
- Restore (**complete**; files, database and new environment config)
- Move to another server (by backup & restore)

###Disclaimer
This script is provided "AS-IS" without warranty of any kind.

###Requirements
- MODx Revolution (traditional), (tested with 2.2.8-pl)
- MODx package: "databackup-1.1.7-pl"
- PHP Modules (CURL, PDO + mysql driver, ZipArchive)
- Write access


###How to use this script:
First create a backup:

1. Make sure your ip is in the ip-whitelist or remove the ip check. (top of ziproot.php)
2. Copy this script into the root of an existing MODx installation.
3. Call the script and run step (1 create backup).
4. Make sure you cleaned the files (after step 1) for security reasons!.
  - ./export-db/
  - ./[the zip]
  - ./[the script]

Then restore the backup on another server:

1. Copy zip + script to the root of the new server.
2. Call the script and run step (2, 3 and 4).
3. Make sure you cleaned the files (after step 4) for security reasons!.
  - ./export-db/
  - ./bigdump.php
  - ./[the zip]
  - ./[the script]

### Thanks to 
- Ozerov for sql import ([bigdump](http://www.ozerov.de/bigdump/))
- Jgulledge19 for sql dump ([databackup](http://modx.com/extras/package/databackup))
- Bootstrap for lay-out ([bootstrap](http://getbootstrap.com/))
- Jquery for interaction ([jQuery](http://jquery.com/))
