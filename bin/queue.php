<?php
require dirname(__DIR__).'/src/bootstrap.php';
try {
 $queue=new Banner\Queue(database(),Banner\Notifications::send(...),max(1,min(10,(int)setting('MAX_ATTEMPTS','5'))));
 for($i=0;$i<100 && $queue->once();$i++){}
} catch(Throwable $e){fwrite(STDERR,"queue_unavailable\n");exit(1);}
