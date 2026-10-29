<?php
require __DIR__.'/support.php';
use Banner\Validation;
use Banner\Notifications;
$count=0;
function check(bool $ok,string $name): void {global $count;if(!$ok)throw new RuntimeException($name);$count++;}
foreach(['+7 (999) 123-45-67','8 999 123 45 67','9991234567'] as $v)check(Validation::phone($v)==='+79991234567','normalization');
foreach(['123','+1 999 1234567','+7<script>9991234567',str_repeat('9',41)] as $v)check(Validation::phone($v)===null,'invalid phone');
$a=['phone'=>'89991234567','consent'=>true,'name'=>'Имя','comment'=>'текст'];check(!Validation::check($a),'valid');
foreach([['consent'=>false],['consent'=>'true'],['name'=>[]],['comment'=>str_repeat('я',1001)]] as $change)check((bool)Validation::check(array_replace($a,$change)),'invalid fields');
foreach(['sent'=>'Подробности отправлены на почту','pending'=>'Отправка на почту ожидается','failed'=>'Проверьте обработчик уведомлений'] as $state=>$text){$message=Notifications::telegramText(['id'=>123,'email_status'=>$state,'phone'=>'SECRET']);check(str_contains($message,$text)&&!str_contains($message,'SECRET'),'telegram privacy');}
$body=Notifications::emailBody(['id'=>123,'created_at'=>'2026-01-01','name'=>'<script>bad</script>','phone'=>'+79991234567','comment'=>'<img src=x onerror=bad>','page'=>'/','utm'=>'{}']);
check(!str_contains($body['html'],'<script>') && str_contains($body['html'],'&lt;img'),'html escaping');
check(str_contains($body['text'],'+79991234567'),'plain text email');
echo "$count unit assertions passed\n";
