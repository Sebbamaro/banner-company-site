<?php
declare(strict_types=1);
namespace Banner;
final class Validation {
 public static function phone(string $value): ?string {
  if (mb_strlen($value)>40 || preg_match('/[^0-9+()\s-]/u',$value)) return null;
  $n=preg_replace('/\D/','',$value);
  if(strlen($n)===10) $n='7'.$n;
  if(strlen($n)===11 && $n[0]==='8') $n='7'.substr($n,1);
  return preg_match('/^7[3489]\d{9}$/',$n) ? '+'.$n : null;
 }
 public static function check(array $a): array {
  $errors=[];
  foreach(['name'=>100,'comment'=>1000] as $key=>$limit) if(!is_string($a[$key]??'') || mb_strlen($a[$key]??'')>$limit) $errors[$key]='Превышена допустимая длина поля.';
  if(!is_string($a['phone']??null) || !self::phone($a['phone'])) $errors['phone']='Введите российский номер телефона.';
  if(($a['consent']??false)!==true) $errors['consent']='Необходимо отдельное согласие на обработку данных.';
  return $errors;
 }
}
