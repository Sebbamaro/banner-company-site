<?php
declare(strict_types=1);
namespace Banner;
use PHPMailer\PHPMailer\PHPMailer;
final class Notifications {
 public static function telegramText(array $lead): string {
  $id=(int)$lead['id'];
  return match($lead['email_status']) {
   'sent'=>"Новая заявка №$id. Подробности отправлены на почту",
   'failed'=>"Заявка №$id сохранена, но отправить письмо не удалось. Проверьте обработчик уведомлений",
   default=>"Новая заявка №$id сохранена. Отправка на почту ожидается"
  };
 }
 public static function emailBody(array $lead): array {
  $text="Заявка №{$lead['id']}\nДата (UTC): {$lead['created_at']}\nИмя: {$lead['name']}\nТелефон: {$lead['phone']}\nКомментарий: {$lead['comment']}\nСтраница: {$lead['page']}\nИсточник: {$lead['utm']}";
  return ['text'=>$text,'html'=>'<pre>'.htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</pre>'];
 }
 public static function send(string $channel,array $lead): void {
  if(setting('APP_MODE')!=='production') throw new \RuntimeException('delivery_disabled');
  if($channel==='email') {
   foreach(['SMTP_HOST','SMTP_FROM','MAIL_TO'] as $k) if(!setting($k)) throw new \RuntimeException('configuration');
   $mail=new PHPMailer(true); $mail->isSMTP(); $mail->Host=setting('SMTP_HOST');$mail->Port=(int)setting('SMTP_PORT','587');
   $mail->SMTPAuth=setting('SMTP_USER')!=='';$mail->Username=setting('SMTP_USER');$mail->Password=setting('SMTP_PASSWORD');
   $security=setting('SMTP_SECURITY','tls');if(!in_array($security,['tls','ssl'],true))throw new \RuntimeException('configuration');
   $mail->SMTPSecure=$security;$mail->Timeout=15;$mail->Timelimit=20;$mail->CharSet='UTF-8';
   $mail->setFrom(setting('SMTP_FROM'));$mail->addAddress(setting('MAIL_TO'));
   $mail->Subject='Новая заявка с сайта №'.(int)$lead['id'];
   $body=self::emailBody($lead);$mail->isHTML(true);$mail->Body=$body['html'];$mail->AltBody=$body['text'];$mail->send();
  } else {
   if(!setting('TELEGRAM_TOKEN') || !setting('TELEGRAM_CHAT_ID'))throw new \RuntimeException('configuration');
   $ch=curl_init('https://api.telegram.org/bot'.setting('TELEGRAM_TOKEN').'/sendMessage');
   curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['chat_id'=>setting('TELEGRAM_CHAT_ID'),'text'=>self::telegramText($lead)]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15]);
   $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
   if($status!==200 || !(json_decode((string)$body,true)['ok']??false))throw new \RuntimeException('telegram_transport');
  }
 }
}
