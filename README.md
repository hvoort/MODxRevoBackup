Backup (and restore) Script
===============
for MODx Revolution (traditional) 


###How to use this script:
First create a backup zip:

1. Copy this script into the root of an existing MODx installation.
2. Call the script and run step (1 create backup).
3. Make sure you cleaned the files (after step 1) for security reasons!.
  - ./export-db/
  - ./[the zip]
  - ./[the script]

Then install this on another server:

1. Copy zip + script to the root of the new server.
2. Call the script and run step (2, 3 and 4).
3. Make sure you cleaned the files (after step 4) for security reasons!.
  - ./export-db/
  - ./bigdump.php
  - ./[the zip]
  - ./[the script]
