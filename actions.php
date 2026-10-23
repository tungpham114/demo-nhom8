<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';

function post_action(): void {
    must(hash_equals(csrf(), (string)($_POST['csrf'] ?? '')), 'Phiên biểu mẫu đã hết hạn. Vui lòng tải lại trang.');
    $action=(string)($_POST['action'] ?? '');
    $user=me();
    switch ($action) {
    case 'register':
        $name=trim((string)($_POST['name'] ?? ''));
        $email=mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $password=(string)($_POST['password'] ?? '');
        must($name!=='' && filter_var($email,FILTER_VALIDATE_EMAIL) && strlen($password)>=8,'Vui lòng nhập họ tên, email hợp lệ và mật khẩu từ 8 ký tự.');
        q('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),'customer']);
        $id=(int)db()->lastInsertId(); session_regenerate_id(true);
        $_SESSION['user']=['id'=>$id,'name'=>$name,'email'=>$email,'role'=>'customer'];
        flash('Tạo tài khoản thành công.'); go('home');
    case 'login':
        $email=mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $row=q('SELECT * FROM users WHERE email=?',[$email])->fetch();
        must($row && password_verify((string)($_POST['password'] ?? ''),$row['password_hash']),'Email hoặc mật khẩu không đúng.');
        session_regenerate_id(true); $_SESSION['user']=['id'=>(int)$row['id'],'name'=>$row['name'],'email'=>$row['email'],'role'=>$row['role']];
        $return=$_SESSION['return_to'] ?? '';
        unset($_SESSION['return_to']);
        flash('Chào mừng trở lại, '.$row['name'].'.');
        if (is_string($return) && str_starts_with($return,'/TOURBUSINESSPROPOSAL8/')) { header('Location: '.$return); exit; }
        go($row['role']==='seller'?'seller':($row['role']==='admin'?'admin':'home'));
    case 'logout':
        $_SESSION=[]; session_regenerate_id(true); flash('Bạn đã đăng xuất.'); go('home');
    case 'seller_otp_send':
        must(!$user,'Đăng xuất trước khi tạo tài khoản Seller riêng.');
        $name=trim((string)($_POST['name'] ?? ''));
        $email=mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $password=(string)($_POST['password'] ?? '');
        must($name!=='' && filter_var($email,FILTER_VALIDATE_EMAIL) && strlen($password)>=8,'Cần họ tên, email hợp lệ và mật khẩu tối thiểu 8 ký tự.');
        must(!q('SELECT id FROM users WHERE email=?',[$email])->fetch(),'Email đã thuộc tài khoản khác.');
        $otp=(string)random_int(100000,999999);
        $_SESSION['seller_signup']=['name'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'otp_hash'=>password_hash($otp,PASSWORD_DEFAULT),'otp_expires'=>time()+600,'verified'=>false];
        // Localhost demonstration only: no SMTP credentials or real email provider.
        $_SESSION['demo_otp']=$otp;
        go('seller_register');
    case 'seller_otp_verify':
        $signup=$_SESSION['seller_signup'] ?? null;
        must($signup && !$signup['verified'] && $signup['otp_expires']>=time(),'Mã OTP không còn hiệu lực. Vui lòng gửi lại.');
        must(password_verify((string)($_POST['otp'] ?? ''),$signup['otp_hash']),'Mã OTP không đúng.');
        $_SESSION['seller_signup']['verified']=true;
        $_SESSION['seller_signup']['otp_expires']=time()+1800;
        unset($_SESSION['demo_otp']);
        flash('Đã xác minh email trong bản thử nghiệm. Hãy hoàn tất hồ sơ doanh nghiệp.'); go('seller_register');
    case 'seller_otp_resend':
        $signup=$_SESSION['seller_signup'] ?? null;
        must($signup && !$signup['verified'],'Không có phiên xác minh email đang chờ.');
        $otp=(string)random_int(100000,999999);
        $_SESSION['seller_signup']['otp_hash']=password_hash($otp,PASSWORD_DEFAULT);
        $_SESSION['seller_signup']['otp_expires']=time()+600;
        $_SESSION['demo_otp']=$otp;
        flash('Đã tạo mã mới trong bản thử nghiệm; mã cũ không còn hiệu lực.'); go('seller_register');
    case 'seller_register':
        must(!$user,'Vui lòng đăng xuất trước khi tạo tài khoản Seller riêng.');
        $signup=$_SESSION['seller_signup'] ?? null;
        must($signup && $signup['verified'] && $signup['otp_expires']>=time(),'Cần xác minh email trước khi gửi hồ sơ Seller.');
        $name=$signup['name']; $email=$signup['email'];
        $company=trim((string)($_POST['company'] ?? ''));
        $tax=trim((string)($_POST['tax_code'] ?? ''));
        $phone=trim((string)($_POST['phone'] ?? ''));
        must($company!=='' && preg_match('/^[0-9]{10}([0-9]{3})?$/',$tax) && preg_match('/^0[0-9]{9,10}$/',$phone),'Kiểm tra lại doanh nghiệp, mã số thuế hoặc số điện thoại.');
        must(trim((string)($_POST['address'] ?? ''))!=='' && trim((string)($_POST['representative'] ?? ''))!=='' && !empty($_POST['quota_agreement']),'Cần nhập đủ thông tin và đồng ý cam kết quỹ chỗ.');
        $paths=[];
        foreach (['business_license','travel_license'] as $key) {
            $file=$_FILES[$key] ?? null;
            must($file && $file['error']===UPLOAD_ERR_OK && $file['size']>0 && $file['size']<=5*1024*1024,'Cần hai giấy phép PDF hoặc ảnh, tối đa 5 MB mỗi tệp.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            must(in_array($mime,['application/pdf','image/jpeg','image/png'],true),'Giấy phép chỉ nhận PDF, JPG hoặc PNG.');
            $extension=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime];
            $paths[$key]='private/'.bin2hex(random_bytes(16)).'.'.$extension;
            must(move_uploaded_file($file['tmp_name'],__DIR__.'/'.$paths[$key]),'Không lưu được giấy phép.');
        }
        db()->beginTransaction();
        try {
            q('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)',[$name,$email,$signup['password_hash'],'seller']);
            $id=(int)db()->lastInsertId();
            q('INSERT INTO sellers(user_id,company_name,tax_code,address,representative,phone,travel_type,business_license,travel_license) VALUES (?,?,?,?,?,?,?,?,?)',
              [$id,$company,$tax,trim((string)$_POST['address']),trim((string)$_POST['representative']),$phone,($_POST['travel_type'] ?? '')==='international'?'international':'domestic',$paths['business_license'],$paths['travel_license']]);
            $sellerId=(int)db()->lastInsertId(); audit($id,'seller',$sellerId,'submitted');
            db()->commit();
        } catch (Throwable $e) { db()->rollBack(); foreach($paths as $path) @unlink(__DIR__.'/'.$path); throw $e; }
        unset($_SESSION['seller_signup'],$_SESSION['demo_otp']);
        session_regenerate_id(true); $_SESSION['user']=['id'=>$id,'name'=>$name,'email'=>$email,'role'=>'seller'];
        flash('Hồ sơ Seller đã gửi. Quản trị viên cần xác minh trước khi bạn đăng tour.'); go('seller');
    case 'seller_resubmit':
        $sellerUser=require_role('seller');
        $seller=q('SELECT * FROM sellers WHERE user_id=?',[$sellerUser['id']])->fetch();
        must($seller && $seller['status']==='rejected','Chỉ hồ sơ bị từ chối mới gửi lại được.');
        $company=trim((string)($_POST['company'] ?? ''));
        $tax=trim((string)($_POST['tax_code'] ?? ''));
        $phone=trim((string)($_POST['phone'] ?? ''));
        must($company!=='' && preg_match('/^[0-9]{10}([0-9]{3})?$/',$tax) && preg_match('/^0[0-9]{9,10}$/',$phone),'Thông tin doanh nghiệp không hợp lệ.');
        must(trim((string)($_POST['address'] ?? ''))!=='' && trim((string)($_POST['representative'] ?? ''))!=='','Cần địa chỉ và người đại diện.');
        must(!empty($_POST['quota_agreement']),'Cần xác nhận lại cam kết quỹ chỗ.');
        $paths=[];
        foreach(['business_license','travel_license'] as $key) {
            $file=$_FILES[$key] ?? null;
            if (!$file || $file['error']===UPLOAD_ERR_NO_FILE) { $paths[$key]=$seller[$key]; continue; }
            must($file['error']===UPLOAD_ERR_OK && $file['size']>0 && $file['size']<=5*1024*1024,'Giấy phép tối đa 5 MB.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            must(in_array($mime,['application/pdf','image/jpeg','image/png'],true),'Giấy phép chỉ nhận PDF/JPG/PNG.');
            $ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime];
            $paths[$key]='private/'.bin2hex(random_bytes(16)).'.'.$ext;
            must(move_uploaded_file($file['tmp_name'],__DIR__.'/'.$paths[$key]),'Không lưu được tệp.');
        }
        q("UPDATE sellers SET company_name=?,tax_code=?,address=?,representative=?,phone=?,travel_type=?,business_license=?,travel_license=?,status='pending',rejection_reason=NULL WHERE id=? AND status='rejected'",
          [$company,$tax,trim((string)($_POST['address'] ?? '')),trim((string)($_POST['representative'] ?? '')),$phone,($_POST['travel_type'] ?? '')==='international'?'international':'domestic',$paths['business_license'],$paths['travel_license'],$seller['id']]);
        audit($sellerUser['id'],'seller',(int)$seller['id'],'resubmitted');
        flash('Đã gửi lại hồ sơ Seller để xác minh.'); go('seller');
    case 'seller_review':
        $admin=require_role('admin'); $id=(int)($_POST['seller_id'] ?? 0); $decision=(string)($_POST['decision'] ?? '');
        must(in_array($decision,['verified','rejected'],true),'Quyết định không hợp lệ.');
        $reason=trim((string)($_POST['reason'] ?? '')); must($decision!=='rejected'||$reason!=='','Khi từ chối phải nhập lý do.');
        $count=q("UPDATE sellers SET status=?,rejection_reason=? WHERE id=? AND status='pending'",[$decision,$reason?:null,$id])->rowCount();
        must($count===1,'Hồ sơ đã được xử lý hoặc không tồn tại.');
        $seller=q('SELECT user_id FROM sellers WHERE id=?',[$id])->fetch();
        audit($admin['id'],'seller',$id,$decision,$reason);
        notice((int)$seller['user_id'],$decision==='verified'?'Hồ sơ Seller đã được xác minh.':'Hồ sơ Seller bị từ chối: '.$reason);
        flash('Đã xử lý hồ sơ Seller.'); go('admin');
    case 'tour_save':
        $sellerUser=require_role('seller');
        $seller=q('SELECT * FROM sellers WHERE user_id=?',[$sellerUser['id']])->fetch();
        must($seller && $seller['status']==='verified','Seller cần được xác minh trước khi đăng tour.');
        $id=(int)($_POST['tour_id'] ?? 0); $submit=($_POST['intent'] ?? '')==='submit';
        $fields=['title','destination','origin','description','itinerary','included','excluded','pickup','transport','accommodation','required_documents','cancellation_policy','surcharge_label'];
        $data=[]; foreach($fields as $field) $data[$field]=trim((string)($_POST[$field] ?? ''));
        $data['days']=(int)($_POST['days'] ?? 0); $data['nights']=(int)($_POST['nights'] ?? 0);
        $data['base_price']=(int)($_POST['base_price'] ?? 0); $data['surcharge_per_person']=(int)($_POST['surcharge_per_person'] ?? 0);
        must($data['days']>=0 && $data['nights']>=0 && $data['base_price']>=0 && $data['surcharge_per_person']>=0,'Số ngày và các khoản giá không được âm.');
        if ($data['surcharge_label']==='') $data['surcharge_label']='Phụ thu dịch vụ';
        $data['is_international']=empty($_POST['is_international'])?0:1;
        $data['image_path']=(string)($_POST['image_path'] ?? 'assets/images/ha-long.jpg');
        $allowed=array_map(fn($x)=>'assets/images/'.$x.'.jpg',['ha-long','hoi-an','sa-pa','phu-quoc','da-lat','ninh-binh','bangkok','tokyo']);
        if (isset($_FILES['tour_image']) && $_FILES['tour_image']['error']!==UPLOAD_ERR_NO_FILE) {
            $file=$_FILES['tour_image'];
            must($file['error']===UPLOAD_ERR_OK && $file['size']>0 && $file['size']<=5*1024*1024,'Ảnh tour tối đa 5 MB.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            must(in_array($mime,['image/jpeg','image/png'],true),'Ảnh tour chỉ nhận JPG hoặc PNG.');
            $data['image_path']='assets/tours/'.bin2hex(random_bytes(16)).($mime==='image/png'?'.png':'.jpg');
            must(move_uploaded_file($file['tmp_name'],__DIR__.'/'.$data['image_path']),'Không lưu được ảnh tour.');
        } else must(in_array($data['image_path'],$allowed,true) || (bool)preg_match('~^assets/tours/[0-9a-f]{32}\.(jpg|png)$~',$data['image_path']),'Ảnh không hợp lệ.');
        if ($submit) {
            foreach(['title','destination','origin','description','itinerary','included','excluded','pickup','transport','accommodation','cancellation_policy'] as $field) must($data[$field]!=='','Thông tin tour chưa đủ: '.$field.'.');
            must($data['days']>=1 && $data['nights']>=0 && $data['base_price']>0 && $data['surcharge_per_person']>=0,'Số ngày và giá phải hợp lệ.');
            must(count(array_filter(preg_split('/\R/u',$data['itinerary']) ?: []))===$data['days'],'Số dòng lịch trình phải bằng số ngày của tour.');
            must(!$data['is_international'] || $data['required_documents']!=='','Tour quốc tế phải ghi giấy tờ cần chuẩn bị.');
        }
        if ($id) {
            $tour=q('SELECT * FROM tours WHERE id=? AND seller_id=?',[$id,$seller['id']])->fetch(); must((bool)$tour,'Không tìm thấy tour thuộc Seller này.');
            if (in_array($tour['status'],['approved','paused'],true)) {
                must($submit,'Bản sửa tour đang bán cần gửi duyệt lại.');
                q('UPDATE tours SET pending_payload=?,rejection_reason=NULL WHERE id=?',[json_encode($data,JSON_UNESCAPED_UNICODE),$id]);
                audit($sellerUser['id'],'tour',$id,'revision_submitted');
            } else {
                must($tour['status']!=='pending','Tour đang chờ duyệt.');
                $sets=implode(',',array_map(fn($f)=>"$f=?",array_keys($data)));
                q("UPDATE tours SET $sets,status=?,rejection_reason=NULL WHERE id=?",[...array_values($data),$submit?'pending':'draft',$id]);
                audit($sellerUser['id'],'tour',$id,$submit?'submitted':'saved_draft');
            }
        } else {
            $columns=implode(',',array_keys($data)); $marks=implode(',',array_fill(0,count($data),'?'));
            q("INSERT INTO tours(seller_id,$columns,status) VALUES (?,$marks,?)",[$seller['id'],...array_values($data),$submit?'pending':'draft']);
            $id=(int)db()->lastInsertId(); audit($sellerUser['id'],'tour',$id,$submit?'submitted':'saved_draft');
        }
        flash($submit?'Tour đã gửi kiểm duyệt.':'Đã lưu nháp tour.'); go('seller_tours');
    case 'tour_clone':
        $sellerUser=require_role('seller');
        $seller=q('SELECT * FROM sellers WHERE user_id=?',[$sellerUser['id']])->fetch();
        must($seller && $seller['status']==='verified','Seller chưa được xác minh.');
        $source=q('SELECT * FROM tours WHERE id=? AND seller_id=?',[(int)($_POST['tour_id'] ?? 0),$seller['id']])->fetch();
        must((bool)$source,'Tour nguồn không tồn tại.');
        $fields=['title','destination','origin','days','nights','description','itinerary','included','excluded','pickup','transport','accommodation','required_documents','cancellation_policy','is_international','base_price','surcharge_per_person','surcharge_label','image_path'];
        $columns=implode(',',$fields); $marks=implode(',',array_fill(0,count($fields),'?'));
        q("INSERT INTO tours(seller_id,$columns,status) VALUES (?,$marks,'draft')",[$seller['id'],...array_map(fn($key)=>$source[$key],$fields)]);
        $id=(int)db()->lastInsertId();
        audit($sellerUser['id'],'tour',$id,'cloned','Sao chép từ tour #'.$source['id']);
        flash('Đã nhân bản tour thành một bản nháp độc lập, chưa sao chép lịch và booking.'); go('seller_tour_form',['id'=>$id]);
    case 'tour_review':
        $admin=require_role('admin'); $id=(int)($_POST['tour_id'] ?? 0); $decision=(string)($_POST['decision'] ?? '');
        must(in_array($decision,['approved','rejected'],true),'Quyết định không hợp lệ.');
        $reason=trim((string)($_POST['reason'] ?? '')); must($decision!=='rejected'||$reason!=='','Cần lý do từ chối.');
        db()->beginTransaction();
        try {
            $tour=q('SELECT * FROM tours WHERE id=? FOR UPDATE',[$id])->fetch();
            must($tour && ($tour['status']==='pending' || $tour['pending_payload']!==null),'Tour không chờ duyệt.');
            if ($tour['pending_payload']!==null) {
                if ($decision==='approved') {
                    $data=json_decode($tour['pending_payload'],true,512,JSON_THROW_ON_ERROR);
                    $sets=implode(',',array_map(fn($f)=>"$f=?",array_keys($data)));
                    q("UPDATE tours SET $sets,pending_payload=NULL,rejection_reason=NULL WHERE id=?",[...array_values($data),$id]);
                    if ((int)$data['base_price'] !== (int)$tour['base_price'])
                        q('UPDATE departures SET price_per_person=? WHERE tour_id=? AND start_date>CURDATE()',[(int)$data['base_price'],$id]);
                } else q('UPDATE tours SET pending_payload=NULL,rejection_reason=? WHERE id=?',[$reason,$id]);
            } else q('UPDATE tours SET status=?,rejection_reason=? WHERE id=?',[$decision,$reason?:null,$id]);
            audit($admin['id'],'tour',$id,$decision,$reason);
            $owner=q('SELECT user_id FROM sellers WHERE id=?',[$tour['seller_id']])->fetch();
            notice((int)$owner['user_id'],$decision==='approved'?'Tour "'.$tour['title'].'" đã được duyệt.':'Tour "'.$tour['title'].'" bị từ chối: '.$reason);
            db()->commit();
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
        flash('Đã xử lý tour.'); go('admin');
    case 'tour_pause':
        $seller=require_role('seller'); $id=(int)($_POST['tour_id'] ?? 0);
        $tour=q('SELECT t.* FROM tours t JOIN sellers s ON s.id=t.seller_id WHERE t.id=? AND s.user_id=?',[$id,$seller['id']])->fetch();
        must($tour && in_array($tour['status'],['approved','paused'],true),'Tour không thể đổi trạng thái.');
        $status=$tour['status']==='paused'?'approved':'paused';
        q('UPDATE tours SET status=? WHERE id=?',[$status,$id]); audit($seller['id'],'tour',$id,$status); flash('Đã cập nhật trạng thái tour.'); go('seller_tours');
    case 'departure_save':
        $seller=require_role('seller'); $tourId=(int)($_POST['tour_id'] ?? 0);
        $tour=q('SELECT t.* FROM tours t JOIN sellers s ON s.id=t.seller_id WHERE t.id=? AND s.user_id=?',[$tourId,$seller['id']])->fetch();
        must($tour && $tour['status']==='approved','Chỉ tour đã duyệt mới thêm lịch khởi hành.');
        $date=(string)($_POST['start_date'] ?? '');
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        must($start && $start->format('Y-m-d')===$date && $start>new DateTimeImmutable('today'),'Ngày khởi hành phải ở tương lai.');
        $capacity=(int)($_POST['capacity'] ?? 0); must($capacity>=1 && $capacity<=999,'Quỹ chỗ phải từ 1 đến 999.');
        $price=(int)($_POST['price_per_person'] ?? 0); must($price>0,'Giá/người của lịch phải lớn hơn 0.');
        must(!empty($_POST['quota_agreement']),'Cần xác nhận cam kết quỹ chỗ chỉ bán qua sàn.');
        $end=$start->modify('+'.max(0,(int)$tour['days']-1).' days');
        $cutoff=$start->modify('-1 day')->format('Y-m-d').' 23:59:59';
        must(strtotime($cutoff)>time(),'Đã qua hạn chốt bán cho lịch này.');
        q('INSERT INTO departures(tour_id,start_date,end_date,capacity,price_per_person,cutoff_at,meeting_time) VALUES (?,?,?,?,?,?,?)',[$tourId,$date,$end->format('Y-m-d'),$capacity,$price,$cutoff,trim((string)($_POST['meeting_time'] ?? ''))]);
        audit($seller['id'],'departure',(int)db()->lastInsertId(),'opened');
        flash('Đã mở lịch khởi hành.'); go('seller_tours');
    case 'departure_toggle':
        $seller=require_role('seller'); $id=(int)($_POST['departure_id'] ?? 0);
        $d=q('SELECT d.* FROM departures d JOIN tours t ON t.id=d.tour_id JOIN sellers s ON s.id=t.seller_id WHERE d.id=? AND s.user_id=?',[$id,$seller['id']])->fetch();
        must($d && $d['status']!=='cancelled' && strtotime($d['cutoff_at'])>time(),'Không thể mở/đóng lịch này.');
        $status=$d['status']==='open'?'closed':'open'; q('UPDATE departures SET status=? WHERE id=?',[$status,$id]);
        audit($seller['id'],'departure',$id,$status); flash('Đã cập nhật lịch khởi hành.'); go('seller_tours');
    case 'booking_create':
        $customer=require_role('customer'); $depId=(int)($_POST['departure_id'] ?? 0); $people=(int)($_POST['people'] ?? 0);
        must($people>=1 && $people<=20,'Số khách phải từ 1 đến 20.');
        $contact=trim((string)($_POST['contact_name'] ?? ''));
        $phone=trim((string)($_POST['contact_phone'] ?? ''));
        $email=trim((string)($_POST['contact_email'] ?? ''));
        must($contact!=='' && preg_match('/^0[0-9]{9,10}$/',$phone) && filter_var($email,FILTER_VALIDATE_EMAIL),'Thông tin người liên hệ chưa hợp lệ.');
        must(!empty($_POST['accept_policy']),'Cần đọc và đồng ý chính sách hủy/đổi.');
        db()->beginTransaction();
        try {
            $d=q('SELECT * FROM departures WHERE id=? FOR UPDATE',[$depId])->fetch(); must((bool)$d,'Không tìm thấy lịch khởi hành.');
            $tour=public_tour((int)$d['tour_id']);
            must($tour && $d['status']==='open' && strtotime($d['cutoff_at'])>time(),'Tour hoặc lịch khởi hành hiện không mở bán.');
            must((int)($_POST['quoted_base'] ?? -1)===(int)$d['price_per_person']
                && (int)($_POST['quoted_surcharge'] ?? -1)===(int)$tour['surcharge_per_person']
                && (string)($_POST['quoted_policy'] ?? '')===$tour['cancellation_policy'],
                'Giá hoặc chính sách vừa thay đổi. Vui lòng xem lại trang tóm tắt trước khi đặt.');
            $occupied=(int)q("SELECT COALESCE(SUM(people),0) FROM bookings WHERE departure_id=? AND status IN ('pending_seller','confirmed','deposited','paid','completed')",[$depId])->fetchColumn();
            must((int)$d['capacity']-$occupied >= $people,'Lịch vừa hết chỗ. Vui lòng chọn lịch khác.');
            $duplicate=(int)q("SELECT COUNT(*) FROM bookings WHERE customer_id=? AND departure_id=? AND status='pending_seller'",[$customer['id'],$depId])->fetchColumn();
            must($duplicate===0,'Bạn đã có booking đang chờ Seller xác nhận cho lịch này.');
            $now=new DateTimeImmutable();
            $sellerDeadline=min($now->modify('+24 hours')->getTimestamp(),strtotime($d['cutoff_at']));
            must($sellerDeadline>time(),'Đã hết thời gian nhận booking.');
            $base=(int)$d['price_per_person']; $surcharge=(int)$tour['surcharge_per_person']; $total=($base+$surcharge)*$people;
            $code='VV'.date('ymd').strtoupper(substr(bin2hex(random_bytes(5)),0,8));
            $balance=balance_deadline($d['start_date'],(bool)$tour['is_international']);
            q('INSERT INTO bookings(code,departure_id,customer_id,people,contact_name,contact_phone,contact_email,note,base_unit,surcharge_unit,surcharge_label,total,policy_snapshot,seller_deadline,balance_deadline) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$code,$depId,$customer['id'],$people,$contact,$phone,$email,trim((string)($_POST['note'] ?? '')),$base,$surcharge,$tour['surcharge_label'],$total,$tour['cancellation_policy'],date('Y-m-d H:i:s',$sellerDeadline),$balance]);
            $id=(int)db()->lastInsertId(); audit($customer['id'],'booking',$id,'created','Giữ '.$people.' chỗ, tổng '.money($total));
            $seller=q('SELECT user_id FROM sellers WHERE id=?',[$tour['seller_id']])->fetch();
            notice((int)$seller['user_id'],'Booking '.$code.' cần xác nhận trước '.date('d/m H:i',$sellerDeadline).'.');
            db()->commit();
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
        flash('Đã giữ '.$people.' chỗ. Seller sẽ xác nhận trước hạn hiển thị.'); go('booking',['id'=>$id]);
    case 'passengers_save':
        $customer=require_role('customer'); $id=(int)($_POST['booking_id'] ?? 0);
        $b=booking_for($id); must($b && (int)$b['customer_id']===$customer['id'],'Không tìm thấy booking.');
        must(in_array($b['status'],['pending_seller','confirmed','deposited'],true),'Không thể sửa danh sách hành khách ở trạng thái này.');
        $names=$_POST['passenger_name'] ?? []; must(is_array($names) && count($names)===(int)$b['people'],'Cần khai báo đúng số hành khách.');
        $items=[];
        foreach($names as $i=>$name) {
            $name=trim((string)$name); $gender=(string)($_POST['gender'][$i] ?? ''); $dob=(string)($_POST['dob'][$i] ?? ''); $phone=(string)($_POST['passenger_phone'][$i] ?? '');
            must($name!=='' && in_array($gender,['female','male','other'],true) && strtotime($dob) && preg_match('/^0[0-9]{9,10}$/',$phone),'Hành khách '.($i+1).' chưa đủ thông tin.');
            $passport=trim((string)($_POST['passport'][$i] ?? '')); $expiry=(string)($_POST['passport_expiry'][$i] ?? ''); $nationality=trim((string)($_POST['nationality'][$i] ?? ''));
            if ($b['is_international']) must($passport!=='' && $nationality!=='' && strtotime($expiry) >= strtotime($b['end_date'].' +6 months'),'Tour quốc tế yêu cầu hộ chiếu còn hạn ít nhất 6 tháng từ ngày kết thúc tour và quốc tịch.');
            $items[]=[$name,$gender,$dob,$phone,$passport?:null,$expiry?:null,$nationality?:null];
        }
        db()->beginTransaction();
        try {
            $prior=q('SELECT full_name,date_of_birth FROM passengers WHERE booking_id=? ORDER BY id',[$id])->fetchAll();
            if ($prior) foreach($prior as $i=>$p) must($p['full_name']===$items[$i][0] && $p['date_of_birth']===$items[$i][2],'Không được thay người sau khi đã lưu. Muốn đổi người, hãy hủy và đặt lại.');
            q('DELETE FROM passengers WHERE booking_id=?',[$id]);
            foreach($items as $item) q('INSERT INTO passengers(booking_id,full_name,gender,date_of_birth,phone,passport_number,passport_expiry,nationality) VALUES (?,?,?,?,?,?,?,?)',[$id,...$item]);
            audit($customer['id'],'booking',$id,'passengers_saved'); db()->commit();
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
        flash('Đã lưu đủ danh sách hành khách.'); go('booking',['id'=>$id]);
    case 'booking_decide':
        $seller=require_role('seller'); $id=(int)($_POST['booking_id'] ?? 0); $decision=(string)($_POST['decision'] ?? '');
        must(in_array($decision,['confirm','reject'],true),'Quyết định không hợp lệ.');
        $reason=trim((string)($_POST['reason'] ?? '')); must($decision!=='reject'||$reason!=='','Cần nêu lý do từ chối.');
        db()->beginTransaction();
        try {
            $b=booking_for($id,true);
            must($b && (int)$b['seller_user_id']===$seller['id'] && $b['status']==='pending_seller' && strtotime($b['seller_deadline'])>time(),'Booking không còn chờ bạn xác nhận.');
            if ($decision==='confirm') {
                $deadline=date('Y-m-d H:i:s',min(time()+86400,strtotime($b['cutoff_at'])));
                must(strtotime($deadline)>time(),'Đã hết hạn chốt bán.');
                $forced=strtotime($b['balance_deadline'])<=strtotime($deadline) ? 1:0;
                q("UPDATE bookings SET status='confirmed',confirmed_at=NOW(),deposit_deadline=?,full_required=? WHERE id=?",[$deadline,$forced,$id]);
                notice((int)$b['customer_id'],'Booking '.$b['code'].' đã được Seller xác nhận. Thanh toán trước '.dt($deadline).'.');
            } else {
                q("UPDATE bookings SET status='rejected',cancellation_reason=? WHERE id=?",[$reason,$id]);
                notice((int)$b['customer_id'],'Booking '.$b['code'].' bị từ chối: '.$reason);
            }
            audit($seller['id'],'booking',$id,$decision,$reason); db()->commit();
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
        flash($decision==='confirm'?'Đã xác nhận booking. Khách có thể cọc hoặc trả đủ.':'Đã từ chối booking và trả lại chỗ.'); go('seller_bookings');
    case 'demo_pay':
        $customer=require_role('customer'); $id=(int)($_POST['booking_id'] ?? 0);
        db()->beginTransaction();
        try {
            $b=booking_for($id,true);
            must($b && (int)$b['customer_id']===$customer['id'],'Booking không thuộc tài khoản này.');
            must(in_array($b['status'],['confirmed','deposited'],true),'Chỉ booking đã được Seller xác nhận mới được thanh toán.');
            $count=(int)q('SELECT COUNT(*) FROM passengers WHERE booking_id=?',[$id])->fetchColumn();
            must($count===(int)$b['people'],'Cần hoàn tất danh sách hành khách trước khi thanh toán.');
            $choice=(string)($_POST['payment_choice'] ?? '');
            must(in_array($choice,['deposit','full'],true),'Chọn hình thức thanh toán.');
            must($b['status']!=='confirmed' || strtotime($b['deposit_deadline'])>time(),'Đã quá hạn thanh toán ban đầu.');
            must($b['status']!=='deposited' || strtotime($b['balance_deadline'])>time(),'Đã quá hạn trả phần còn lại.');
            must(!($choice==='deposit' && ((int)$b['full_required']===1 || $b['status']==='deposited')),'Booking này chỉ được trả toàn bộ số còn lại.');
            $amount=$choice==='deposit'?(int)ceil((int)$b['total']/2):(int)$b['total']-(int)$b['paid_amount'];
            must($amount>0,'Booking đã thanh toán đủ.');
            $ref='LOCAL-'.strtoupper(bin2hex(random_bytes(9)));
            q('INSERT INTO payments(booking_id,reference,amount,status) VALUES (?,?,?,?)',[$id,$ref,$amount,'success']);
            $paid=(int)$b['paid_amount']+$amount; $status=$paid>=(int)$b['total']?'paid':'deposited';
            q('UPDATE bookings SET paid_amount=?,status=? WHERE id=?',[$paid,$status,$id]);
            audit($customer['id'],'booking',$id,$status,'Giao dịch mô phỏng '.$ref.' / '.money($amount));
            notice((int)$b['seller_user_id'],'Booking '.$b['code'].' vừa '.($status==='paid'?'thanh toán đủ.':'đặt cọc 50%.'));
            db()->commit();
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
        flash('Giao dịch mô phỏng thành công: '.money($amount).'.'); go('booking',['id'=>$id]);
    default: throw new DomainException('Thao tác không được hỗ trợ.');
    }
}
