<?php
require dirname(__DIR__).'/src/bootstrap.php';
try {
 $days=(int)setting('RETENTION_DAYS','90');if($days<1)throw new RuntimeException();
 $db=database();$s=$db->prepare('DELETE FROM leads WHERE created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY)');$s->execute([$days]);
 $db->exec('DELETE FROM rate_limits WHERE window_start < UNIX_TIMESTAMP()-86400');echo "Retention cleanup complete\n";
} catch(Throwable $e){fwrite(STDERR,"cleanup_failed\n");exit(1);}
