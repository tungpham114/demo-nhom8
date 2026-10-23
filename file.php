<?php
declare(strict_types=1);
require __DIR__.'/config.php';
$user=me();
if (!$user || !in_array($user['role'],['admin','seller'],true)) { http_response_code(403); exit('Không có quyền truy cập.'); }
$seller=q('SELECT * FROM sellers WHERE id=?',[(int)($_GET['id'] ?? 0)])->fetch();
if (!$seller || ($user['role']==='seller' && (int)$seller['user_id']!==$user['id'])) { http_response_code(404); exit('Không tìm thấy tệp.'); }
$type=(string)($_GET['type'] ?? '');
$path=$type==='business'?$seller['business_license']:($type==='travel'?$seller['travel_license']:'');
if (!$path || !str_starts_with($path,'private/')) { http_response_code(404); exit('Tệp không có trong bản demo.'); }
$full=realpath(__DIR__.'/'.$path); $root=realpath(__DIR__.'/private');
if (!$full || !$root || !str_starts_with($full,$root.DIRECTORY_SEPARATOR)) { http_response_code(404); exit('Tệp không tồn tại.'); }
header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($full));
header('Content-Disposition: inline; filename="giay-phep.'.pathinfo($full,PATHINFO_EXTENSION).'"');
header('X-Content-Type-Options: nosniff');
readfile($full);
