<?php
declare(strict_types=1);
namespace Banner;
use PDO;
final class Leads {
 public static function save(PDO $db,array $data,string $ip,array $cfg,string $salt): int {
  if(strlen($salt)<24) throw new \RuntimeException('configuration');
  $db->beginTransaction();
  try {
   $key=hash_hmac('sha256',$ip,$salt); $now=time();
   $db->prepare('INSERT IGNORE INTO rate_limits(key_hash,window_start,hits) VALUES(?,?,0)')->execute([$key,$now]);
   $s=$db->prepare('SELECT * FROM rate_limits WHERE key_hash=? FOR UPDATE'); $s->execute([$key]); $r=$s->fetch();
   $hits=$now-(int)$r['window_start']>=600 ? 0 : (int)$r['hits'];
   if($hits>=5) throw new \RuntimeException('rate_limit');
   $db->prepare('UPDATE rate_limits SET hits=?,window_start=? WHERE key_hash=?')->execute([$hits+1,$hits===0?$now:$r['window_start'],$key]);
   $page=is_string($data['page']??null)?$data['page']:'/';
   if(!preg_match('~^/[a-zA-Z0-9/_\-.]*$~D',$page) || strlen($page)>255) $page='/';
   $utm=[]; foreach(['utm_source','utm_medium','utm_campaign','utm_content','utm_term'] as $k) if(is_string($data['utm'][$k]??null)) $utm[$k]=mb_substr($data['utm'][$k],0,150);
   $s=$db->prepare('INSERT INTO leads(created_at,name,phone,comment,page,utm,consent_at,consent_version) VALUES(UTC_TIMESTAMP(),?,?,?,?,?,UTC_TIMESTAMP(),?)');
   $s->execute([trim($data['name']??''),Validation::phone($data['phone']),trim($data['comment']??''),$page,json_encode($utm,JSON_UNESCAPED_UNICODE),$cfg['consentVersion']]);
   $id=(int)$db->lastInsertId();
   $s=$db->prepare('INSERT INTO jobs(lead_id,channel,available_at) VALUES(?,?,UTC_TIMESTAMP())');
   foreach(['email','telegram'] as $channel) $s->execute([$id,$channel]);
   $db->commit(); return $id;
  } catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
