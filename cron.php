<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__.'/config.php';
$rows=q("SELECT b.id,b.code,b.status,b.customer_id,b.seller_deadline,b.deposit_deadline,b.balance_deadline,t.seller_id,s.user_id AS seller_user_id
  FROM bookings b JOIN departures d ON d.id=b.departure_id JOIN tours t ON t.id=d.tour_id JOIN sellers s ON s.id=t.seller_id
  WHERE (b.status='pending_seller' AND b.seller_deadline<=NOW())
     OR (b.status='confirmed' AND b.deposit_deadline<=NOW())
     OR (b.status='deposited' AND b.balance_deadline<=NOW())")->fetchAll();
$processed=0;
foreach($rows as $row) {
    db()->beginTransaction();
    try {
        $b=q('SELECT * FROM bookings WHERE id=? FOR UPDATE',[$row['id']])->fetch();
        if (!$b || $b['status']!==$row['status']) { db()->commit(); continue; }
        $next=$b['status']==='pending_seller'?'rejected':($b['status']==='confirmed'?'expired':'cancelled');
        q('UPDATE bookings SET status=?,cancellation_reason=? WHERE id=?',[$next,'Tự động xử lý quá hạn',$b['id']]);
        audit(null,'booking',(int)$b['id'],'deadline_'.$next);
        notice((int)$b['customer_id'],'Booking '.$b['code'].' đã '.($next==='rejected'?'bị từ chối do Seller quá hạn phản hồi':($next==='expired'?'hết hạn thanh toán':'bị hủy do quá hạn trả phần còn lại')).'.');
        notice((int)$row['seller_user_id'],'Booking '.$b['code'].' chuyển trạng thái quá hạn: '.$next.'.');
        db()->commit(); $processed++;
    } catch(Throwable $e) { db()->rollBack(); throw $e; }
}
$finished=q("SELECT b.id,b.code,b.customer_id,s.user_id AS seller_user_id FROM bookings b
  JOIN departures d ON d.id=b.departure_id JOIN tours t ON t.id=d.tour_id JOIN sellers s ON s.id=t.seller_id
  WHERE b.status='paid' AND d.end_date<CURDATE()")->fetchAll();
foreach($finished as $row) {
    db()->beginTransaction();
    try {
        $changed=q("UPDATE bookings SET status='completed' WHERE id=? AND status='paid'",[$row['id']])->rowCount();
        if ($changed) {
            audit(null,'booking',(int)$row['id'],'completed');
            notice((int)$row['customer_id'],'Booking '.$row['code'].' đã hoàn thành hành trình.');
            notice((int)$row['seller_user_id'],'Booking '.$row['code'].' đã hoàn thành hành trình.');
            $processed++;
        }
        db()->commit();
    } catch(Throwable $e) { db()->rollBack(); throw $e; }
}
echo "Processed $processed booking transitions.\n";
