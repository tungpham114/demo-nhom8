<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

const APP_NAME = 'ViVu Việt';
const APP_DIR = 'TOURBUSINESSPROPOSAL8';

function db(): PDO {
    static $connection = null;
    if ($connection) return $connection;
    $host = getenv('TOUR_DB_HOST') ?: '127.0.0.1';
    $port = getenv('TOUR_DB_PORT') ?: '3306';
    $name = 'TOURBUSINESSPROPOSAL8';
    $user = getenv('TOUR_DB_USER') ?: 'root';
    $password = getenv('TOUR_DB_PASSWORD') ?: '';
    $connection = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET time_zone = '+07:00'");
    return $connection;
}
function q(string $sql, array $args=[]): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt;
}
function h(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(int|float $number): string { return number_format((float)$number, 0, ',', '.') . ' ₫'; }
function url(string $page='', array $params=[]): string { return 'index.php?' . http_build_query(['page'=>$page] + $params); }
function go(string $page='', array $params=[]): never { header('Location: '.url($page,$params)); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function hidden_csrf(): string { return '<input type="hidden" name="csrf" value="'.h(csrf()).'">'; }
function me(): ?array { return $_SESSION['user'] ?? null; }
function require_role(string $role): array {
    $user = me();
    if (!$user) { $_SESSION['return_to'] = $_SERVER['REQUEST_URI'] ?? url(); go('login'); }
    if ($user['role'] !== $role) throw new DomainException('Bạn không có quyền thực hiện thao tác này.');
    return $user;
}
function flash(string $message, string $type='ok'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function notice(int $userId, string $message): void { q('INSERT INTO notifications(user_id,message) VALUES (?,?)',[$userId,$message]); }
function audit(?int $actorId, string $type, int $id, string $action, string $detail=''): void {
    q('INSERT INTO audit_log(actor_id,object_type,object_id,action,detail) VALUES (?,?,?,?,?)',[$actorId,$type,$id,$action,$detail]);
}
function must(bool $ok, string $message): void { if (!$ok) throw new DomainException($message); }
function dt(string $date): string { return date('d/m/Y H:i',strtotime($date)); }
function day(string $date): string { return date('d/m/Y',strtotime($date)); }
function badge(string $status): string {
    $map=['draft'=>'Nháp','pending'=>'Chờ duyệt','approved'=>'Đã duyệt','selling'=>'Đang bán','rejected'=>'Bị từ chối','paused'=>'Tạm ngừng',
        'verified'=>'Đã xác minh','open'=>'Mở bán','closed'=>'Đóng bán','cancelled'=>'Đã hủy',
        'pending_seller'=>'Chờ Seller xác nhận','confirmed'=>'Đã xác nhận','deposited'=>'Đã đặt cọc','paid'=>'Đã thanh toán','completed'=>'Hoàn thành','expired'=>'Hết hạn'];
    return '<span class="badge status-'.h($status).'">'.h($map[$status] ?? $status).'</span>';
}
function balance_deadline(string $start, bool $international): string {
    $d=new DateTimeImmutable($start, new DateTimeZone('Asia/Ho_Chi_Minh'));
    $days=$international ? 10 : 3;
    if ($international) {
        while ($days > 0) { $d=$d->modify('-1 day'); if ((int)$d->format('N') < 6) $days--; }
    } else $d=$d->modify('-3 days');
    return $d->format('Y-m-d').' 23:59:59';
}
function departure_rows(int $tourId): array {
    return q("SELECT d.*, d.capacity - COALESCE((SELECT SUM(b.people) FROM bookings b WHERE b.departure_id=d.id AND b.status IN ('pending_seller','confirmed','deposited','paid','completed')),0) AS remaining
              FROM departures d WHERE d.tour_id=? ORDER BY d.start_date",[$tourId])->fetchAll();
}
function public_tour(int $id): ?array {
    $tour=q("SELECT t.*,s.company_name,s.status AS seller_status FROM tours t JOIN sellers s ON s.id=t.seller_id WHERE t.id=?",[$id])->fetch();
    return $tour && $tour['status']==='approved' && $tour['seller_status']==='verified' ? $tour : null;
}
function booking_for(int $id, bool $lock=false): ?array {
    return q("SELECT b.*, d.tour_id,d.start_date,d.end_date,d.cutoff_at,d.meeting_time,t.title,t.image_path,t.is_international,t.pickup,t.seller_id,s.user_id AS seller_user_id
      FROM bookings b JOIN departures d ON d.id=b.departure_id JOIN tours t ON t.id=d.tour_id
      JOIN sellers s ON s.id=t.seller_id WHERE b.id=?".($lock?' FOR UPDATE':''),[$id])->fetch() ?: null;
}
