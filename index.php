<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_once __DIR__.'/actions.php';
require_once __DIR__.'/pages.php';

try {
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) post_action();
    $page=(string)($_GET['page'] ?? 'home');
    render_page($page);
} catch (PDOException $e) {
    $message=$e->getCode()==='23000'?'Dữ liệu đã tồn tại (email hoặc mã số thuế). Vui lòng kiểm tra lại.':'Lỗi kết nối hoặc truy vấn CSDL. Kiểm tra config.php và bước cài đặt.';
    error_log((string)$e);
    render_error($message);
} catch (DomainException $e) {
    render_error($e->getMessage());
} catch (Throwable $e) {
    error_log((string)$e);
    render_error('Có lỗi xảy ra. Vui lòng thử lại; xem nhật ký PHP để biết chi tiết.');
}
