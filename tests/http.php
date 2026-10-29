<?php
// Isolated fixture: never edits the real company configuration or production DB.
require __DIR__.'/support.php';
$dsn=setting('TEST_DB_DSN','mysql:host=db;dbname=banner_test;charset=utf8mb4');
if(!preg_match('/dbname=[a-zA-Z0-9_]+_test(?:;|$)/',$dsn))throw new RuntimeException('Test database required');
$root=dirname(__DIR__);$tmp=sys_get_temp_dir().'/banner-http-'.bin2hex(random_bytes(6));
mkdir($tmp);mkdir($tmp.'/config');mkdir($tmp.'/public');mkdir($tmp.'/public/api');mkdir($tmp.'/src');
foreach(glob($root.'/src/*.php') as $f)copy($f,$tmp.'/src/'.basename($f));
copy($root.'/public/api/callback.php',$tmp.'/public/api/callback.php');symlink($root.'/vendor',$tmp.'/vendor');
$cfg=company();$cfg['name']='Тестовая компания (fixture)';$cfg['details']='Тестовый оператор';$cfg['domain']='https://fixture.example';file_put_contents($tmp.'/config/company.json',json_encode($cfg));
$env=['PATH'=>getenv('PATH'),'APP_MODE'=>'production','APP_ORIGIN'=>'http://127.0.0.1:8091','RATE_SALT'=>str_repeat('t',32),'DB_DSN'=>$dsn,'DB_USER'=>setting('TEST_DB_USER','root'),'DB_PASSWORD'=>setting('TEST_DB_PASSWORD','local-only-root')];
$db=new PDO($dsn,$env['DB_USER'],$env['DB_PASSWORD']);$db->exec('DELETE FROM leads');$db->exec('DELETE FROM rate_limits');
$proc=null;$n=0;
function check(bool $ok,string $name):void{global $n;if(!$ok)throw new RuntimeException($name);$n++;}
function start(array $env):mixed {global $tmp;return proc_open([PHP_BINARY,'-S','127.0.0.1:8091','-t',$tmp.'/public'],[0=>['file','/dev/null','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes,$tmp,$env);}
function request(array $data,string $origin='http://127.0.0.1:8091'):array {
 $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nOrigin: $origin\r\n",'content'=>json_encode($data),'ignore_errors'=>true,'timeout'=>5]]);
 $body=file_get_contents('http://127.0.0.1:8091/api/callback.php',false,$ctx);preg_match('/ (\d{3}) /',$http_response_header[0],$m);return [(int)$m[1],json_decode($body,true)];
}
try {
 $proc=start($env);usleep(400000);
 $valid=['phone'=>'89991234567','name'=>'PRIVATE_TEST_NAME','comment'=>'PRIVATE_TEST_COMMENT','consent'=>true];
 [$code,$r]=request($valid);check($code===201 && isset($r['id']),'actual saved HTTP success');
 check((int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn()===1,'saved before response');
 check((int)$db->query('SELECT COUNT(*) FROM jobs')->fetchColumn()===2,'queue committed');
 foreach([['phone'=>'123'],['consent'=>false],['website'=>'spam'],['comment'=>str_repeat('я',1001)]] as $bad){[$code]=request(array_replace($valid,$bad));check($code===422,'invalid HTTP request');}
 [$code]=request($valid,'https://untrusted.example');check($code===403,'origin rejected');
 check((int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn()===1,'invalid requests not stored');
 proc_terminate($proc);proc_close($proc);$proc=null;
 $env['DB_DSN']='mysql:host=127.0.0.1;port=1;dbname=unreachable_test';$proc=start($env);usleep(400000);
 [$code,$r]=request($valid);check($code===503 && !isset($r['id']),'database outage is not success');
 $log=file_get_contents($tmp.'/server.log');check(!str_contains($log,'PRIVATE_TEST_')&&!str_contains($log,'89991234567'),'no PII in technical logs');
 echo "$n HTTP assertions passed\n";
} finally {
 if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}
 $db->exec('DELETE FROM leads');$db->exec('DELETE FROM rate_limits');
 unlink($tmp.'/vendor');foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($tmp);
}
