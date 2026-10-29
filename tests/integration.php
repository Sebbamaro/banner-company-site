<?php
require __DIR__.'/support.php';
use Banner\Leads;
use Banner\Queue;
$dsn=setting('TEST_DB_DSN','mysql:host=db;dbname=banner_test;charset=utf8mb4');
if(!preg_match('/dbname=([a-zA-Z0-9_]+_test)(?:;|$)/',$dsn,$match))throw new RuntimeException('Dedicated _test database required');
$db=new PDO($dsn,setting('TEST_DB_USER','root'),setting('TEST_DB_PASSWORD','local-only-root'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec(file_get_contents(dirname(__DIR__).'/migrations/001.sql'));
$db->exec('DELETE FROM jobs');$db->exec('DELETE FROM leads');$db->exec('DELETE FROM rate_limits');
$n=0;function check(bool $ok,string $name):void{global $n;if(!$ok)throw new RuntimeException($name);$n++;}
$data=['name'=>'<b>Тест</b>','phone'=>'89991234567','comment'=>'Только тест','consent'=>true,'page'=>'/kontakty/','utm'=>['utm_source'=>'test']];
$id=Leads::save($db,$data,'127.0.0.1',company(),str_repeat('s',32));
check((int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn()===1,'saved');check((int)$db->query('SELECT COUNT(*) FROM jobs')->fetchColumn()===2,'atomic jobs');
check($db->query('SELECT phone FROM leads')->fetchColumn()==='+79991234567','stored normalized');
$calls=[];$queue=new Queue($db,function($channel,$lead)use(&$calls){$calls[]=$channel;if($channel==='email')throw new RuntimeException('mock failure');},2);
check($queue->once(),'email attempt');check($queue->once(),'independent telegram');
$lead=$db->query('SELECT * FROM leads')->fetch();check($lead['email_status']==='pending' && $lead['telegram_status']==='sent','independent failure');
check(!$queue->once(),'backoff prevents early attempt');
$db->exec("UPDATE jobs SET available_at=UTC_TIMESTAMP() WHERE status='pending'");check($queue->once(),'retry');
check($db->query('SELECT email_status FROM leads')->fetchColumn()==='failed','max attempts');check(!$queue->once(),'no repeat after success');check(count(array_filter($calls,fn($x)=>$x==='telegram'))===1,'telegram exactly one confirmed send');
$id2=Leads::save($db,$data,'127.0.0.2',company(),str_repeat('s',32));$texts=[];
$success=new Queue($db,function($channel,$lead)use(&$texts){if($channel==='telegram')$texts[]=Banner\Notifications::telegramText($lead);});
$success->once();$success->once();check(str_contains($texts[0],'Подробности отправлены'),'email success reflected');
for($i=0;$i<4;$i++)Leads::save($db,$data,'127.0.0.2',company(),str_repeat('s',32));
try{Leads::save($db,$data,'127.0.0.2',company(),str_repeat('s',32));throw new LogicException('limit missing');}catch(RuntimeException $e){check($e->getMessage()==='rate_limit','rate limit');}
check((int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn()===6,'rate rejection rolls back');
// A failing Telegram adapter must not resend an already confirmed email.
$db->exec('DELETE FROM leads');$db->exec('DELETE FROM rate_limits');
Leads::save($db,$data,'127.0.0.9',company(),str_repeat('s',32));
$attempts=['email'=>0,'telegram'=>0];$second=new Queue($db,function($channel,$lead)use(&$attempts){$attempts[$channel]++;if($channel==='telegram' && $attempts[$channel]===1)throw new RuntimeException('mock');});
$second->once();$second->once();
check($db->query('SELECT email_status FROM leads')->fetchColumn()==='sent','email independent of telegram failure');
$db->exec("UPDATE jobs SET available_at=UTC_TIMESTAMP() WHERE status='pending'");$second->once();check(!$second->once(),'all done');
check($attempts===['email'=>1,'telegram'=>2],'retry only failing channel');
// Both job rows are rolled back if inserting the outbox fails.
$db->exec("CREATE TRIGGER test_reject_job BEFORE INSERT ON jobs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='mock outbox failure'");
$before=$db->query('SELECT COUNT(*) FROM leads')->fetchColumn();
try{Leads::save($db,$data,'127.0.0.10',company(),str_repeat('s',32));throw new LogicException('rollback missing');}catch(PDOException $e){check($db->query('SELECT COUNT(*) FROM leads')->fetchColumn()===$before,'atomic rollback');}
finally{$db->exec('DROP TRIGGER test_reject_job');}
$db->exec('DELETE FROM leads');$db->exec('DELETE FROM rate_limits');echo "$n database/queue assertions passed\n";
