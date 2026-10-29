<?php
require dirname(__DIR__).'/src/bootstrap.php';
try {database()->exec(file_get_contents(dirname(__DIR__).'/migrations/001.sql'));echo "Migration complete\n";}
catch(Throwable $e){fwrite(STDERR,"migration_failed\n");exit(1);}
