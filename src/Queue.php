<?php
declare(strict_types=1);
namespace Banner;
use PDO;
final class Queue {
 public function __construct(private PDO $db,private \Closure $send,private int $max=5){}
 public function once(): bool {
  $db=$this->db;$db->beginTransaction();
  try {
   // Keep row locks through the bounded network call: another worker skips this job.
   // Lock the lead too, so the Telegram snapshot observes the email result.
   $job=$db->query("SELECT * FROM jobs WHERE status='pending' AND available_at<=UTC_TIMESTAMP() ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
   if(!$job){$db->commit();return false;}
   $q=$db->prepare('SELECT * FROM leads WHERE id=? FOR UPDATE');$q->execute([$job['lead_id']]);$lead=$q->fetch();
   $attempt=(int)$job['attempts']+1;$state='sent';$error=null;
   try {($this->send)($job['channel'],$lead);} catch(\Throwable $e){$state=$attempt>=$this->max?'failed':'pending';$error='transport_or_configuration';}
   $delay=min(3600,60*(2**min($attempt-1,6)));
   $q=$db->prepare('UPDATE jobs SET status=?,attempts=?,error_code=?,available_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=?');$q->execute([$state,$attempt,$error,$delay,$job['id']]);
   $channel=$job['channel']==='email'?'email':'telegram';
   $db->prepare("UPDATE leads SET {$channel}_status=?,{$channel}_attempts=? WHERE id=?")->execute([$state,$attempt,$job['lead_id']]);
   $db->commit();return true;
  } catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
