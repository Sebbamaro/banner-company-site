<?php
declare(strict_types=1);
function setting(string $key, string $default=''): string { return (string)($_ENV[$key] ?? getenv($key) ?: $default); }
function database(): PDO {
 $db = new PDO(setting('DB_DSN'), setting('DB_USER'), setting('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 return $db;
}
function company(): array { return json_decode(file_get_contents(dirname(__DIR__).'/config/company.json'),true,512,JSON_THROW_ON_ERROR); }
