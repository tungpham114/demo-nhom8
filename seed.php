<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run with PHP CLI only.'); }
require_once dirname(__DIR__).'/config.php';
$password=password_hash('Demo@12345',PASSWORD_DEFAULT);
foreach ([['Khách trải nghiệm','customer@vivuviet.test','customer'],['ViVu Travel','seller@vivuviet.test','seller'],['Quản trị ViVu','admin@vivuviet.test','admin']] as [$name,$email,$role]) {
    if (!q('SELECT id FROM users WHERE email=?',[$email])->fetchColumn())
        q('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)',[$name,$email,$password,$role]);
}
$sellerUser=(int)q('SELECT id FROM users WHERE email=?',['seller@vivuviet.test'])->fetchColumn();
$seller=q('SELECT id FROM sellers WHERE user_id=?',[$sellerUser])->fetch();
if (!$seller) {
    q("INSERT INTO sellers(user_id,company_name,tax_code,address,representative,phone,travel_type,business_license,travel_license,status)
       VALUES (?,?,?,?,?,?,?,?,?,'verified')",[$sellerUser,'ViVu Travel Việt Nam','0109988776','18 Tràng Tiền, Hoàn Kiếm, Hà Nội','Nguyễn Minh An','0988888888','international','seed/demo-business.pdf','seed/demo-travel.pdf']);
    $sellerId=(int)db()->lastInsertId();
} else $sellerId=(int)$seller['id'];

$tourData=[
 ['Vịnh Hạ Long – đêm trên du thuyền','Hạ Long','Hà Nội',3,2,2890000,250000,'Phụ thu tàu & phí cảng','ha-long','Ngắm sắc nước ngọc bích trên vịnh, khám phá hang động và đón bình minh từ boong tàu.','Di chuyển Hà Nội – Hạ Long, lên du thuyền, thăm hang Sửng Sốt.\nKayak và thăm đảo Titop, ngắm hoàng hôn trên vịnh.\nĂn sáng trên tàu, trở về Hà Nội.'],
 ['Hội An – Đà Nẵng: phố cổ và biển xanh','Hội An','Đà Nẵng',3,2,2350000,180000,'Phụ thu vé tham quan','hoi-an','Một nhịp điệu thong dong giữa đèn lồng, nhà cổ và bờ biển miền Trung.','Đón khách tại Đà Nẵng, tắm biển và tham quan Ngũ Hành Sơn.\nDạo phố cổ Hội An, trải nghiệm văn hóa địa phương.\nTham quan làng nghề và trở về Đà Nẵng.'],
 ['Sa Pa – bản làng giữa tầng mây','Sa Pa','Hà Nội',3,2,2690000,150000,'Phụ thu vé thắng cảnh','sa-pa','Đi qua ruộng bậc thang, những bản nhỏ và những đỉnh núi mờ trong sương.','Khởi hành Hà Nội, đến Sa Pa và khám phá bản Cát Cát.\nTrekking qua thung lũng Mường Hoa và bản Tả Van.\nTham quan trung tâm Sa Pa, trở về Hà Nội.'],
 ['Phú Quốc – biển xanh phía cuối trời','Phú Quốc','TP. Hồ Chí Minh',4,3,4990000,380000,'Phụ thu tàu đảo','phu-quoc','Chạm vào làn nước trong và nắng vàng của đảo ngọc, tận hưởng những ngày nghỉ trọn vẹn.','Bay đến Phú Quốc, nhận phòng và ngắm hoàng hôn.\nKhám phá Nam đảo và trải nghiệm biển.\nThăm làng chài, thưởng thức ẩm thực địa phương.\nTự do mua sắm rồi trở về TP. Hồ Chí Minh.'],
 ['Đà Lạt – mùa hoa trên cao nguyên','Đà Lạt','TP. Hồ Chí Minh',3,2,2490000,120000,'Phụ thu vé tham quan','da-lat','Một Đà Lạt lãng mạn với rừng thông, mặt hồ và những con dốc dịu dàng.','Di chuyển đến Đà Lạt, tham quan hồ Xuân Hương.\nKhám phá ngoại ô, vườn hoa và nông trại.\nTham quan chợ Đà Lạt, trở về TP. Hồ Chí Minh.'],
 ['Ninh Bình – non nước Tràng An','Ninh Bình','Hà Nội',2,1,1690000,160000,'Phụ thu vé thuyền Tràng An','ninh-binh','Theo dòng sông đi giữa núi đá, ngắm một miền di sản thật gần Hà Nội.','Di chuyển Hà Nội – Ninh Bình, đi thuyền Tràng An.\nKhám phá Hang Múa, trở về Hà Nội.'],
 ['Bangkok – sắc màu xứ Chùa Vàng','Bangkok','Hà Nội',4,3,7690000,650000,'Phụ thu thuế & phí sân bay','bangkok','Khám phá chùa tháp, phố xá và nhịp sống đầy màu sắc của Bangkok.','Bay Hà Nội – Bangkok, dạo khu phố ven sông.\nThăm Wat Arun và Hoàng cung.\nKhám phá chợ địa phương và ẩm thực Thái Lan.\nTự do mua sắm, bay về Hà Nội.'],
 ['Tokyo – nhịp sống Nhật Bản','Tokyo','TP. Hồ Chí Minh',5,4,17990000,1200000,'Phụ thu thuế & phí sân bay','tokyo','Khám phá sự giao thoa giữa những khu phố hiện đại, đền cổ và văn hóa Nhật.','Bay đến Tokyo, nhận phòng và khám phá khu phố đêm.\nTham quan Asakusa, chùa Sensoji.\nKhám phá Shibuya và Harajuku.\nTrải nghiệm văn hóa Nhật Bản, mua sắm.\nBay về TP. Hồ Chí Minh.'],
];
$count=(int)q('SELECT COUNT(*) FROM tours WHERE seller_id=?',[$sellerId])->fetchColumn();
if ($count===0) foreach ($tourData as $n=>$item) {
    [$title,$destination,$origin,$days,$nights,$price,$surcharge,$label,$image,$description,$itinerary]=$item;
    $foreign=$n>=6?1:0;
    q("INSERT INTO tours(seller_id,title,destination,origin,days,nights,description,itinerary,included,excluded,pickup,transport,accommodation,required_documents,cancellation_policy,is_international,base_price,surcharge_per_person,surcharge_label,image_path,status)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'approved')",[
       $sellerId,$title,$destination,$origin,$days,$nights,$description,str_replace('\\n',"\n",$itinerary),
       'Xe/di chuyển theo chương trình, hướng dẫn viên, bữa ăn theo lịch trình, bảo hiểm du lịch.',
       'Chi tiêu cá nhân, đồ uống riêng và dịch vụ không ghi trong chương trình.',
       'Điểm tập trung trung tâm thành phố','Xe du lịch / phương tiện theo lịch trình','Khách sạn tiêu chuẩn 3 sao',
       $foreign?'Hộ chiếu còn hạn ít nhất 6 tháng; chuẩn bị trước khởi hành 14 ngày.':'Căn cước công dân hoặc giấy tờ tùy thân hợp lệ.',
       'Linh hoạt: hủy trước 7 ngày được hoàn 100%',$foreign,$price,$surcharge,$label,'assets/images/'.$image.'.jpg'
    ]);
    $tourId=(int)db()->lastInsertId();
    foreach([35,62,90] as $offset) {
        $start=(new DateTimeImmutable('today'))->modify('+'.($offset+$n*2).' days');
        $end=$start->modify('+'.($days-1).' days');
        $cutoff=$start->modify('-1 day')->format('Y-m-d').' 23:59:59';
        q('INSERT INTO departures(tour_id,start_date,end_date,capacity,price_per_person,cutoff_at,meeting_time) VALUES (?,?,?,?,?,?,?)',[$tourId,$start->format('Y-m-d'),$end->format('Y-m-d'),24,$price,$cutoff,'06:30 tại điểm tập trung']);
    }
}
echo "Seed complete. Demo logins: customer@vivuviet.test / seller@vivuviet.test / admin@vivuviet.test — password: Demo@12345\n";
