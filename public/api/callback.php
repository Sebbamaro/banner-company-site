<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $status,array $data): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') reply(405,['message'=>'Используйте форму на сайте.']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>16000) reply(413,['message'=>'Слишком большой запрос.']);
try {
 require dirname(__DIR__,2).'/src/bootstrap.php';
 if(($_SERVER['HTTP_ORIGIN']??'')!==setting('APP_ORIGIN')) reply(403,['message'=>'Отправьте заявку с сайта компании.']);
 $raw=file_get_contents('php://input',false,null,0,16001);
 if(strlen($raw)>16000) reply(413,['message'=>'Слишком большой запрос.']);
 $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
 if(!is_array($data)) reply(422,['message'=>'Некорректная заявка.']);
 $errors=Banner\Validation::check($data);
 if($errors) reply(422,['message'=>'Проверьте заполнение формы.','errors'=>$errors]);
 if(!empty($data['website'])) reply(422,['message'=>'Не удалось отправить заявку.']);
 if(setting('APP_MODE')==='demo') reply(200,['demo'=>true,'message'=>'Тестовая отправка: данные проверены, заявка не сохранена и уведомления не отправлены.']);
 if(setting('APP_MODE')!=='production') throw new RuntimeException('configuration');
 $cfg=company();
 if(!$cfg['domain'] || str_contains($cfg['name'],'[') || str_contains($cfg['details'],'[')) throw new RuntimeException('configuration');
 $id=Banner\Leads::save(database(),$data,$_SERVER['REMOTE_ADDR']??'unknown',$cfg,setting('RATE_SALT'));
 reply(201,['id'=>$id,'message'=>'Заявка принята. Мы свяжемся с вами в рабочее время']);
} catch(JsonException $e){reply(400,['message'=>'Некорректный формат запроса.']);
} catch(Throwable $e){
 if($e->getMessage()==='rate_limit') reply(429,['message'=>'Слишком много отправок. Попробуйте через 10 минут.']);
 $reason=$e instanceof PDOException?'database':(in_array($e->getMessage(),['configuration','dependencies_missing'],true)?'configuration':'request');
 error_log('callback_unavailable_'.$reason);
 reply(503,['message'=>'Сейчас отправка недоступна. Повторите попытку позже.']);
}
