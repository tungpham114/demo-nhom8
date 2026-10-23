<?php
declare(strict_types=1);

function top(string $title, string $section=''): void {
    $user=me(); $flash=$_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    $title=h($title);
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#173c4b"><title>'.$title.' · ViVu Việt</title><link rel="icon" href="assets/logo.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/fonts.css"><link rel="stylesheet" href="assets/style.css"><script defer src="assets/app.js"></script></head><body>';
    echo '<div class="utility"><div class="container">Du lịch Việt Nam và thế giới, chọn tour rõ giá & rõ chỗ <span>• Sàn kết nối các đơn vị lữ hành đã xác minh</span></div></div>';
    echo '<header class="site-header"><div class="container nav"><a href="'.url('home').'" class="brand"><img src="assets/logo.svg" alt="Logo ViVu Việt"><span>ViVu <b>Việt</b><small>Đi xa theo cách của bạn</small></span></a><nav aria-label="Điều hướng chính"><a class="'.($section==='home'?'active':'').'" href="'.url('home').'">Trang chủ</a><a class="'.($section==='tours'?'active':'').'" href="'.url('tours').'">Khám phá tour</a><a href="'.url('compare').'">So sánh</a>';
    if ($user && $user['role']==='customer') echo '<a href="'.url('bookings').'">Booking của tôi</a>';
    if ($user && $user['role']==='seller') echo '<a href="'.url('seller').'">Cổng Seller</a>';
    if ($user && $user['role']==='admin') echo '<a href="'.url('admin').'">Quản trị</a>';
    echo '</nav><div class="nav-user">';
    if ($user) {
        echo '<a class="hello" href="'.url('notices').'">Xin chào, '.h($user['name']).'</a><form method="post">'.hidden_csrf().'<input type="hidden" name="action" value="logout"><button class="link-button">Đăng xuất</button></form>';
    } else echo '<a href="'.url('login').'" class="login-link">Đăng nhập</a><a href="'.url('register').'" class="btn btn-small">Đăng ký</a>';
    echo '</div><button class="menu-toggle" aria-label="Mở menu" aria-expanded="false">☰</button></div></header>';
    if ($flash) echo '<div class="container flash '.h($flash['type']).'">'.h($flash['message']).'</div>';
}
function foot(): void {
    echo '<footer><div class="container foot-grid"><div><a class="brand foot-brand" href="'.url('home').'"><img src="assets/logo.svg" alt=""><span>ViVu <b>Việt</b><small>Đi xa theo cách của bạn</small></span></a><p>Góc nhìn mới cho mỗi hành trình. Tour do các Seller đã xác minh đăng bán và sàn kiểm duyệt nội dung.</p></div><div><b>Khám phá</b><a href="'.url('tours').'">Tìm tour</a><a href="'.url('compare').'">So sánh tour</a><a href="'.url('seller_register').'">Trở thành Seller</a></div><div><b>Minh bạch</b><p>Giá hiển thị từ giá cơ bản, chưa gồm phụ thu. Tổng tiền được xác nhận trước khi giữ chỗ.</p><p>Thanh toán trong bản localhost là mô phỏng, không thu tiền thật.</p></div></div><div class="container copyright">© '.date('Y').' ViVu Việt · Bản chạy thử phục vụ đồ án TOURBUSINESSPROPOSAL8</div></footer></body></html>';
}
function render_error(string $message): void {
    top('Thông báo'); echo '<main class="container section narrow"><div class="panel error-panel"><span class="eyebrow">THÔNG BÁO</span><h1>Chưa thể tiếp tục</h1><p>'.h($message).'</p><a class="btn" href="'.url('home').'">Về trang chủ</a> <button class="btn btn-outline" onclick="history.back()">Quay lại</button></div></main>'; foot();
}
function action_form(string $action, string $extra=''): string {
    return '<form method="post" '.$extra.'>'.hidden_csrf().'<input type="hidden" name="action" value="'.h($action).'">';
}
function tour_matches(array $t, array $filters): bool {
    $needle=trim((string)($filters['q'] ?? '')); $dest=trim((string)($filters['destination'] ?? ''));
    $norm=static function($v){ $v=mb_strtolower((string)$v); $v=strtr($v,['đ'=>'d','Đ'=>'d']); return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v) ?: $v; };
    if ($dest!=='' && !str_contains($norm($t['destination']),$norm($dest))) return false;
    if ($needle!=='' && !str_contains($norm($t['title'].' '.$t['destination'].' '.$t['origin'].' '.$t['itinerary'].' '.$t['company_name']),$norm($needle))) return false;
    if (!empty($filters['max_price']) && (int)($t['display_price'] ?? $t['base_price'])>(int)$filters['max_price']) return false;
    if (!empty($filters['days']) && (int)$t['days']>(int)$filters['days']) return false;
    return true;
}
function available_tours(array $filters=[]): array {
    $tours=q("SELECT t.*,s.company_name FROM tours t JOIN sellers s ON s.id=t.seller_id WHERE t.status='approved' AND s.status='verified' ORDER BY t.id DESC")->fetchAll();
    $results=[]; $people=max(1,(int)($filters['people'] ?? 1));
    foreach ($tours as $tour) {
        $dates=departure_rows((int)$tour['id']);
        $open=array_values(array_filter($dates, static function($d) use($filters,$people) {
            return $d['status']==='open' && strtotime($d['cutoff_at'])>time() && (int)$d['remaining']>=$people
              && (empty($filters['date']) || $d['start_date'] >= $filters['date']);
        }));
        if (!$open) continue;
        $tour['next_departure']=$open[0]; $tour['display_price']=(int)$open[0]['price_per_person']; $tour['open_count']=count($open);
        if (!tour_matches($tour,$filters)) continue;
        $results[]=$tour;
    }
    if (($filters['sort'] ?? '')==='price_desc') usort($results,fn($a,$b)=>(int)$b['display_price']<=>(int)$a['display_price']);
    else usort($results,fn($a,$b)=>strcmp($a['next_departure']['start_date'],$b['next_departure']['start_date']));
    return $results;
}
function card(array $t): void {
    $id=(int)$t['id']; $dep=$t['next_departure'];
    echo '<article class="tour-card"><a class="photo" href="'.url('detail',['id'=>$id]).'"><img loading="lazy" src="'.h($t['image_path']).'" alt="'.h($t['destination']).'"><span class="photo-tag">'.($t['is_international']?'Quốc tế':'Trong nước').'</span></a><div class="card-body"><div class="card-meta">'.h($t['origin']).' → '.h($t['destination']).' <span>· '.(int)$t['days'].'N'.(int)$t['nights'].'Đ</span></div><h3><a href="'.url('detail',['id'=>$id]).'">'.h($t['title']).'</a></h3><p class="seller-line">✓ '.h($t['company_name']).' · Đã xác minh</p><div class="card-departure">Khởi hành gần nhất <b>'.day($dep['start_date']).'</b> <span>Còn '.(int)$dep['remaining'].' chỗ</span></div><div class="card-bottom"><div><small>Giá lịch này / người · chưa gồm phụ thu</small><strong>'.money((int)$t['display_price']).'</strong></div><a class="round-arrow" aria-label="Xem '.h($t['title']).'" href="'.url('detail',['id'=>$id]).'">↗</a></div><label class="compare-check"><input type="checkbox" class="compare-box" value="'.$id.'"> So sánh tour này</label></div></article>';
}
function search_form(array $f=[]): void {
    echo '<form class="searchbar" method="get"><input type="hidden" name="page" value="tours"><label>Điểm đến<input name="destination" placeholder="Bạn muốn đi đâu?" value="'.h((string)($f['destination'] ?? '')).'"></label><label>Khởi hành từ<input name="date" type="date" min="'.date('Y-m-d').'" value="'.h((string)($f['date'] ?? '')).'"></label><label>Số khách<input name="people" type="number" min="1" max="20" value="'.max(1,(int)($f['people'] ?? 2)).'"></label><button class="btn" type="submit">Tìm tour <span>↗</span></button></form>';
}
function render_page(string $page): void {
    switch ($page) {
    case 'home':
        top('Tour du lịch cho mọi hành trình','home');
        echo '<main><section class="hero"><img class="hero-img" src="assets/images/ha-long.jpg" alt="Vịnh Hạ Long nhìn từ trên cao"><div class="hero-shade"></div><div class="container hero-content"><span class="eyebrow light">VI VU VIỆT · CHỌN TOUR THEO CÁCH BẠN MUỐN</span><h1>Hành trình đẹp<br>bắt đầu từ <em>sự an tâm.</em></h1><p>Khám phá tour trên khắp Việt Nam và thế giới. Lịch khởi hành, quỹ chỗ và tổng tiền được trình bày rõ trước khi bạn đặt.</p>'.search_form_html().'</div><span class="hero-caption">Ảnh thực tế · Vịnh Hạ Long, Việt Nam</span></section>';
        echo '<section class="container section"><div class="section-head"><div><span class="eyebrow">ĐI ĐÂU CŨNG CÓ ĐIỀU ĐỂ NHỚ</span><h2>Hành trình đang mở bán</h2><p>Từ biển xanh, phố cổ đến những miền núi đầy cảm hứng.</p></div><a class="text-link" href="'.url('tours').'">Xem tất cả tour ↗</a></div><div class="card-grid">';
        foreach(array_slice(available_tours(),0,6) as $t) card($t);
        echo '</div></section><section class="trust-band"><div class="container trust-grid"><div><span>01</span><h3>Seller đã xác minh</h3><p>Doanh nghiệp lữ hành cung cấp hồ sơ và được quản trị viên duyệt trước khi bán tour.</p></div><div><span>02</span><h3>Chỗ được giữ ngay</h3><p>Quỹ chỗ được kiểm tra và tạm giữ khi bạn xác nhận đặt, trong lúc chờ Seller phản hồi.</p></div><div><span>03</span><h3>Biết tổng tiền trước khi đặt</h3><p>Giá cơ bản và phụ thu được tách rõ trên màn hình tóm tắt để bạn quyết định.</p></div></div></section></main>';
        foot(); return;
    case 'tours':
        $f=$_GET; $tours=available_tours($f);
        top('Tìm tour','tours'); echo '<main class="container section"><div class="page-intro"><span class="eyebrow">VI VU VIỆT / KHÁM PHÁ</span><h1>Tìm hành trình của bạn</h1><p>Lọc theo điểm đến, lịch khởi hành và số người; mỗi tour hiển thị một lần với lịch còn chỗ gần nhất.</p></div>'; search_form($f);
        echo '<form class="filters" method="get"><input type="hidden" name="page" value="tours"><input type="hidden" name="destination" value="'.h((string)($f['destination'] ?? '')).'"><input type="hidden" name="date" value="'.h((string)($f['date'] ?? '')).'"><input type="hidden" name="people" value="'.max(1,(int)($f['people'] ?? 2)).'"><label>Từ khóa <input name="q" placeholder="Tên tour, địa danh, Seller" value="'.h((string)($f['q'] ?? '')).'"></label><label>Thời lượng <select name="days"><option value="">Tất cả</option><option value="3" '.(($f['days'] ?? '')==='3'?'selected':'').'>Tối đa 3 ngày</option><option value="5" '.(($f['days'] ?? '')==='5'?'selected':'').'>Tối đa 5 ngày</option></select></label><label>Giá cơ bản tối đa <select name="max_price"><option value="">Không giới hạn</option><option value="3000000" '.(($f['max_price'] ?? '')==='3000000'?'selected':'').'>3 triệu</option><option value="7000000" '.(($f['max_price'] ?? '')==='7000000'?'selected':'').'>7 triệu</option></select></label><label>Sắp xếp <select name="sort"><option value="date">Ngày gần nhất</option><option value="price_desc" '.(($f['sort'] ?? '')==='price_desc'?'selected':'').'>Giá giảm dần</option></select></label><button class="btn btn-outline" type="submit">Áp dụng</button></form>';
        echo '<div class="result-line"><b>'.count($tours).' tour phù hợp</b><span>Giá trên thẻ chưa gồm phụ thu</span></div>';
        if ($tours) {echo '<div class="card-grid">'; foreach($tours as $t) card($t); echo '</div>';} else echo '<div class="empty"><h2>Chưa có tour phù hợp</h2><p>Thử thay đổi điểm đến, lịch hoặc số khách.</p><a class="btn" href="'.url('tours').'">Xem tất cả</a></div>';
        echo '<div id="compare-tray" class="compare-tray" hidden><span><b id="compare-count">0</b>/3 tour đã chọn</span><button type="button" id="compare-go" class="btn btn-small">So sánh ngay ↗</button></div></main>'; foot(); return;
    case 'detail':
        $tour=public_tour((int)($_GET['id'] ?? 0)); must((bool)$tour,'Tour không còn khả dụng.');
        $deps=array_values(array_filter(departure_rows((int)$tour['id']),fn($d)=>$d['status']==='open' && strtotime($d['cutoff_at'])>time() && $d['remaining']>0));
        must((bool)$deps,'Tour hiện chưa có lịch mở bán.');
        top($tour['title'],'tours'); detail_content($tour,$deps); foot(); return;
    case 'compare':
        $ids=array_slice(array_unique(array_filter(array_map('intval',explode(',',(string)($_GET['ids'] ?? ''))))),0,3);
        $tours=[]; foreach($ids as $id) { $t=public_tour($id); if($t) { $dates=array_values(array_filter(departure_rows($id),fn($d)=>$d['status']==='open' && strtotime($d['cutoff_at'])>time() && $d['remaining']>0)); $t['currently_open']=(bool)$dates; $t['display_price']=$dates?(int)$dates[0]['price_per_person']:(int)$t['base_price']; $tours[]=$t; } }
        top('So sánh tour','tours'); echo '<main class="container section"><div class="page-intro"><span class="eyebrow">ĐẶT CÁC HÀNH TRÌNH CẠNH NHAU</span><h1>So sánh tour</h1><p>Chọn tối đa ba tour tại trang khám phá. Sao chép đường dẫn để chia sẻ bảng so sánh.</p></div>';
        if (!$tours) echo '<div class="empty"><h2>Chưa chọn tour để so sánh</h2><a class="btn" href="'.url('tours').'">Khám phá tour</a></div>';
        else { echo '<div class="compare-grid">'; foreach($tours as $t) echo '<article class="panel"><img class="compare-img" src="'.h($t['image_path']).'" alt="'.h($t['destination']).'"><h2>'.h($t['title']).'</h2>'.(!$t['currently_open']?'<span class="badge">Không còn mở bán</span>':'').'<p>'.h($t['origin']).' → '.h($t['destination']).'</p><p>Thời lượng: <b>'.(int)$t['days'].' ngày '.(int)$t['nights'].' đêm</b></p><p>Giá cơ bản từ lịch gần nhất: <b>'.money((int)$t['display_price']).'</b> / người</p><p>Phụ thu: <b>'.money((int)$t['surcharge_per_person']).'</b> / người</p><p>Gồm: '.h($t['included']).'</p><p>Không gồm: '.h($t['excluded']).'</p><p>Seller: '.h($t['company_name']).'</p>'.($t['currently_open']?'<a class="btn btn-wide" href="'.url('detail',['id'=>$t['id']]).'">Xem tour ↗</a>':'').'</article>'; echo '</div>'; }
        echo '</main>'; foot(); return;
    case 'login': case 'register': case 'seller_register': auth_page($page); return;
    case 'booking_form': booking_form_page(); return;
    case 'booking_summary': booking_summary_page(); return;
    case 'booking': booking_page(); return;
    case 'bookings': bookings_page(); return;
    case 'seller': seller_home(); return;
    case 'seller_tours': seller_tours_page(); return;
    case 'seller_tour_form': seller_tour_form_page(); return;
    case 'seller_tour': seller_tour_page(); return;
    case 'seller_bookings': seller_bookings_page(); return;
    case 'admin': admin_page(); return;
    case 'notices': notices_page(); return;
    default: throw new DomainException('Không tìm thấy trang bạn yêu cầu.');
    }
}
function search_form_html(): string {
    ob_start(); search_form(); return (string)ob_get_clean();
}
function detail_content(array $t,array $deps): void {
    $fromPrice=min(array_map(fn($d)=>(int)$d['price_per_person'],$deps));
    echo '<main class="container section detail"><a class="back-link" href="'.url('tours').'">← Tất cả tour</a><div class="detail-title"><div><span class="eyebrow">'.($t['is_international']?'TOUR QUỐC TẾ':'KHÁM PHÁ VIỆT NAM').'</span><h1>'.h($t['title']).'</h1><p>'.h($t['origin']).' → '.h($t['destination']).'　·　'.(int)$t['days'].' ngày '.(int)$t['nights'].' đêm　·　Seller ✓ '.h($t['company_name']).'</p></div><a class="btn btn-outline share-link" href="#" data-share>Chia sẻ ↗</a></div><div class="detail-visual"><img src="'.h($t['image_path']).'" alt="Ảnh thực tế '.h($t['destination']).'"><div class="visual-label">'.h($t['destination']).' · Hành trình cùng ViVu Việt</div></div><div class="detail-grid"><div class="detail-main"><div class="panel"><h2>Điểm nhấn hành trình</h2><p>'.nl2br(h($t['description'])).'</p><div class="detail-facts"><div><small>Khởi hành</small><b>'.h($t['origin']).'</b></div><div><small>Phương tiện</small><b>'.h($t['transport']).'</b></div><div><small>Điểm đón</small><b>'.h($t['pickup']).'</b></div><div><small>Lưu trú</small><b>'.h($t['accommodation']).'</b></div></div></div><div class="panel"><h2>Lịch trình từng ngày</h2>';
    foreach(preg_split('/\R/u',$t['itinerary']) ?: [] as $i=>$line) if(trim($line)!=='') echo '<div class="timeline-item"><span>'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'</span><p>'.h($line).'</p></div>';
    echo '</div><div class="panel"><h2>Dịch vụ & lưu ý</h2><div class="two-cols"><div><h3>Đã bao gồm</h3><p>'.nl2br(h($t['included'])).'</p></div><div><h3>Chưa bao gồm</h3><p>'.nl2br(h($t['excluded'])).'</p></div></div><hr><h3>Giấy tờ cần chuẩn bị</h3><p>'.h($t['required_documents'] ?: 'Giấy tờ tùy thân hợp lệ theo hướng dẫn của Seller.').'</p><h3>Chính sách hủy / đổi</h3><p>'.h($t['cancellation_policy']).' · Chính sách được cố định khi bạn đặt tour.</p></div></div><aside class="booking-aside"><div class="panel sticky"><span class="eyebrow">LỊCH KHỞI HÀNH CÒN CHỖ</span><p class="price-label">Giá cơ bản từ</p><div class="big-price">'.money($fromPrice).' <small>/ người</small></div><p class="surcharge-note">Chưa gồm phụ thu. '.h($t['surcharge_label']).': '.money((int)$t['surcharge_per_person']).'/người. Tổng tiền hiển thị trước khi xác nhận đặt.</p><div class="departure-list">';
    foreach($deps as $d) echo '<a href="'.url('booking_form',['departure_id'=>$d['id']]).'" class="departure-row"><span><b>'.day($d['start_date']).'</b><small>Đến '.day($d['end_date']).' · Còn '.(int)$d['remaining'].' chỗ · '.money((int)$d['price_per_person']).'/người</small></span><span>Chọn lịch →</span></a>';
    echo '</div><p class="safe-note">🔒 Chỗ được giữ sau khi xác nhận đặt. Seller phản hồi trong tối đa 24 giờ.</p></div></aside></div></main>';
}

function auth_page(string $page): void {
    if ($page==='seller_register' && !empty($_GET['restart'])) unset($_SESSION['seller_signup'],$_SESSION['demo_otp']);
    top($page==='login'?'Đăng nhập':($page==='seller_register'?'Đăng ký Seller':'Đăng ký tài khoản'));
    echo '<main class="auth-bg"><div class="container"><div class="auth-card panel"><span class="eyebrow">VI VU VIỆT · TÀI KHOẢN</span>';
    if ($page==='login') {
        echo '<h1>Chào mừng trở lại</h1><p>Đăng nhập để theo dõi booking hoặc xử lý công việc tại cổng Seller.</p>'.action_form('login').'<label>Email<input type="email" name="email" required autocomplete="email"></label><label>Mật khẩu<input type="password" name="password" required autocomplete="current-password"></label><button class="btn btn-wide">Đăng nhập</button></form><div class="auth-links"><a href="'.url('register').'">Tạo tài khoản khách</a><a href="'.url('seller_register').'">Đăng ký bán tour</a></div>';
    } elseif ($page==='register') {
        echo '<h1>Bắt đầu hành trình</h1><p>Tài khoản Khách hàng cho phép đặt và theo dõi tour.</p>'.action_form('register').'<label>Họ và tên<input name="name" required autocomplete="name"></label><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Mật khẩu (ít nhất 8 ký tự)<input type="password" name="password" required minlength="8" autocomplete="new-password"></label><button class="btn btn-wide">Tạo tài khoản</button></form><div class="auth-links"><a href="'.url('login').'">Đã có tài khoản?</a><a href="'.url('seller_register').'">Tôi là Seller</a></div>';
    } else {
        $signup=$_SESSION['seller_signup'] ?? null;
        if (!$signup || $signup['otp_expires']<time()) {
            echo '<h1>Trở thành Seller</h1><p>Bước 1/3 · Tạo tài khoản Seller riêng và xác minh email.</p>'.action_form('seller_otp_send').'<label>Họ tên người đăng ký<input name="name" required></label><label>Email riêng cho Seller<input name="email" type="email" required></label><label>Mật khẩu (tối thiểu 8 ký tự)<input name="password" type="password" minlength="8" required></label><button class="btn btn-wide">Gửi mã xác minh →</button></form><p class="muted">Bản localhost hiển thị mã OTP tại bước tiếp theo thay cho gửi email thật.</p>';
        } elseif (!$signup['verified']) {
            echo '<h1>Xác minh email</h1><p>Bước 2/3 · Nhập mã gồm 6 chữ số cho <b>'.h($signup['email']).'</b>.</p><div class="info-note">Mã thử nghiệm trên localhost: <strong>'.h($_SESSION['demo_otp'] ?? '').'</strong> · Hiệu lực đến '.date('H:i',$signup['otp_expires']).'. Không gửi email thật.</div>'.action_form('seller_otp_verify').'<label>Mã OTP<input name="otp" inputmode="numeric" pattern="[0-9]{6}" required></label><button class="btn btn-wide">Xác nhận mã →</button></form><div class="auth-links"><a href="'.url('seller_register',['restart'=>1]).'">Nhập email khác</a>'.action_form('seller_otp_resend').'<button class="link-button">Gửi lại mã</button></form></div>';
        } else {
            echo '<h1>Hồ sơ doanh nghiệp</h1><p>Bước 3/3 · Email '.h($signup['email']).' đã xác minh. Hai giấy phép sẽ được quản trị viên kiểm tra.</p>'.action_form('seller_register','enctype="multipart/form-data"').'<div class="form-grid"><label>Doanh nghiệp<input name="company" required></label><label>Mã số thuế<input name="tax_code" pattern="[0-9]{10}([0-9]{3})?" required></label><label>Người đại diện<input name="representative" required></label><label>Số điện thoại<input name="phone" required></label><label>Loại hình lữ hành<select name="travel_type"><option value="domestic">Nội địa</option><option value="international">Quốc tế</option></select></label><label class="span-2">Địa chỉ doanh nghiệp<input name="address" required></label><label>Giấy phép kinh doanh (PDF/JPG/PNG)<input name="business_license" type="file" accept=".pdf,.jpg,.jpeg,.png" required></label><label>Giấy phép lữ hành (PDF/JPG/PNG)<input name="travel_license" type="file" accept=".pdf,.jpg,.jpeg,.png" required></label></div><label class="check-line"><input type="checkbox" name="quota_agreement" value="1" required> Tôi cam kết quỹ chỗ khai báo trên sàn chỉ được bán qua sàn.</label><button class="btn btn-wide">Gửi hồ sơ xác minh</button></form>';
        }
    }
    echo '</div></div></main>'; foot();
}
function booking_form_page(): void {
    $customer=require_role('customer'); $id=(int)($_GET['departure_id'] ?? 0);
    $d=q('SELECT * FROM departures WHERE id=?',[$id])->fetch(); must((bool)$d,'Không tìm thấy lịch khởi hành.');
    $t=public_tour((int)$d['tour_id']); must($t && $d['status']==='open' && strtotime($d['cutoff_at'])>time(),'Lịch này không còn nhận đặt.');
    $remaining=0; foreach(departure_rows((int)$t['id']) as $row) if((int)$row['id']===$id) $remaining=(int)$row['remaining'];
    must($remaining>0,'Lịch đã hết chỗ.');
    top('Thông tin đặt tour');
    echo '<main class="container section booking-flow"><div class="steps"><b>01 Thông tin</b><span>02 Tóm tắt</span><span>03 Chờ Seller</span><span>04 Thanh toán</span></div><a class="back-link" href="'.url('detail',['id'=>$t['id']]).'">← Quay về chi tiết tour</a><div class="checkout-grid"><section class="panel"><span class="eyebrow">BƯỚC 01 / THÔNG TIN ĐẶT TOUR</span><h1>Ai sẽ đồng hành cùng bạn?</h1><p>Điền thông tin người liên hệ. Danh sách hành khách được bổ sung sau khi đặt và trước khi thanh toán.</p><form method="post" action="'.url('booking_summary').'">'.hidden_csrf().'<input type="hidden" name="departure_id" value="'.$id.'"><div class="form-grid"><label>Số khách (còn '.$remaining.' chỗ)<input type="number" name="people" min="1" max="'.min(20,$remaining).'" value="1" required></label><label>Họ tên người liên hệ<input name="contact_name" value="'.h($customer['name']).'" required></label><label>Số điện thoại<input name="contact_phone" pattern="0[0-9]{9,10}" required></label><label>Email<input type="email" name="contact_email" value="'.h($customer['email']).'" required></label><label class="span-2">Ghi chú cho Seller (không bắt buộc)<textarea name="note" rows="3" placeholder="Yêu cầu hỗ trợ, điểm đón mong muốn..."></textarea></label></div><button type="submit" class="btn btn-large">Tiếp tục <span>→</span></button></form></section><aside class="panel order-summary"><img src="'.h($t['image_path']).'" alt="'.h($t['destination']).'"><h3>'.h($t['title']).'</h3><p>Khởi hành '.day($d['start_date']).' · '.(int)$t['days'].' ngày</p><hr><p>Giá cơ bản <b>'.money((int)$d['price_per_person']).'/người</b></p><p>'.h($t['surcharge_label']).' <b>'.money((int)$t['surcharge_per_person']).'/người</b></p><small>Giá hiển thị trên danh sách tour chưa gồm phụ thu; tổng tiền ở bước tiếp theo.</small></aside></div></main>'; foot();
}
function booking_summary_page(): void {
    require_role('customer');
    must($_SERVER['REQUEST_METHOD']==='POST' && hash_equals(csrf(),(string)($_POST['csrf'] ?? '')),'Vui lòng điền thông tin đặt tour trước.');
    $id=(int)($_POST['departure_id'] ?? 0); $people=(int)($_POST['people'] ?? 0);
    $d=q('SELECT * FROM departures WHERE id=?',[$id])->fetch(); must((bool)$d,'Lịch không tồn tại.');
    $t=public_tour((int)$d['tour_id']); must($t && $d['status']==='open' && strtotime($d['cutoff_at'])>time(),'Lịch không còn mở bán.');
    must($people>=1 && $people<=20,'Số khách không hợp lệ.');
    $contact=trim((string)($_POST['contact_name'] ?? '')); $phone=trim((string)($_POST['contact_phone'] ?? '')); $email=trim((string)($_POST['contact_email'] ?? ''));
    must($contact!=='' && preg_match('/^0[0-9]{9,10}$/',$phone) && filter_var($email,FILTER_VALIDATE_EMAIL),'Thông tin liên hệ không hợp lệ.');
    $base=(int)$d['price_per_person']*$people; $surcharge=(int)$t['surcharge_per_person']*$people; $total=$base+$surcharge;
    $sellerDeadline=min(time()+86400,strtotime($d['cutoff_at']));
    $balance=balance_deadline($d['start_date'],(bool)$t['is_international']);
    top('Kiểm tra đặt tour'); echo '<main class="container section booking-flow"><div class="steps"><span>01 Thông tin</span><b>02 Tóm tắt</b><span>03 Chờ Seller</span><span>04 Thanh toán</span></div><div class="page-intro"><span class="eyebrow">BƯỚC 02 / KIỂM TRA TRƯỚC KHI ĐẶT</span><h1>Rõ ràng trước khi lên đường.</h1><p>Kiểm tra tổng tiền và chính sách. Booking chỉ được tạo sau khi bạn xác nhận bên dưới.</p></div><div class="checkout-grid"><div class="panel"><h2>'.h($t['title']).'</h2><div class="summary-rows"><p>Lịch khởi hành <b>'.day($d['start_date']).'</b></p><p>Số khách <b>'.$people.' người</b></p><p>Người liên hệ <b>'.h($contact).' · '.h($phone).'</b></p><p>Email <b>'.h($email).'</b></p><p>Giá cơ bản <b>'.$people.' × '.money((int)$d['price_per_person']).' = '.money($base).'</b></p><p>'.h($t['surcharge_label']).' <b>'.$people.' × '.money((int)$t['surcharge_per_person']).' = '.money($surcharge).'</b></p><p class="summary-total">Tổng tiền cố định khi đặt <strong>'.money($total).'</strong></p><p>Số tiền cọc dự kiến (50%) <b>'.money((int)ceil($total/2)).'</b></p></div><div class="info-note">Seller xác nhận trong tối đa 24 giờ, dự kiến trước <b>'.date('d/m/Y H:i',$sellerDeadline).'</b>. Sau khi Seller xác nhận, hệ thống mới cho phép cọc hoặc thanh toán 100%. Nếu hạn trả phần còn lại đến sớm, booking sẽ yêu cầu thanh toán 100%.</div><p><b>Hạn trả phần còn lại:</b> '.dt($balance).' · <b>Đơn vị nhận tiền:</b> sàn ViVu Việt (mô phỏng trên localhost).</p><p><b>Chính sách hủy/đổi:</b> '.h($t['cancellation_policy']).'</p>'.action_form('booking_create');
    foreach(['departure_id'=>$id,'people'=>$people,'contact_name'=>$contact,'contact_phone'=>$phone,'contact_email'=>$email,'note'=>(string)($_POST['note'] ?? ''),'quoted_base'=>$d['price_per_person'],'quoted_surcharge'=>$t['surcharge_per_person'],'quoted_policy'=>$t['cancellation_policy']] as $key=>$value) echo '<input type="hidden" name="'.h($key).'" value="'.h((string)$value).'">';
    echo '<label class="check-line"><input type="checkbox" name="accept_policy" value="1" required> Tôi đã đọc và đồng ý chính sách hủy/đổi và tổng tiền nêu trên.</label><button class="btn btn-large">Xác nhận đặt & giữ chỗ →</button></form><p class="muted">Hệ thống kiểm tra lại quỹ chỗ ngay tại thời điểm bấm xác nhận.</p></div><aside class="panel order-summary"><img src="'.h($t['image_path']).'" alt="'.h($t['destination']).'"><h3>'.h($t['destination']).'</h3><p>Điểm đón: '.h($t['pickup']).'</p><a class="text-link" href="'.url('booking_form',['departure_id'=>$id]).'">← Sửa thông tin đặt tour</a></aside></div></main>'; foot();
}
function booking_page(): void {
    $user=me(); must((bool)$user,'Vui lòng đăng nhập.'); $id=(int)($_GET['id'] ?? 0); $b=booking_for($id);
    must($b && ((int)$b['customer_id']===$user['id'] || ($user['role']==='seller' && (int)$b['seller_user_id']===$user['id']) || $user['role']==='admin'),'Không tìm thấy booking hoặc bạn không có quyền xem.');
    $passengers=q('SELECT * FROM passengers WHERE booking_id=? ORDER BY id',[$id])->fetchAll();
    $payments=q('SELECT * FROM payments WHERE booking_id=? ORDER BY id',[$id])->fetchAll();
    top('Booking '.$b['code']);
    echo '<main class="container section booking-flow"><a class="back-link" href="'.url($user['role']==='seller'?'seller_bookings':'bookings').'">← Danh sách booking</a><div class="page-intro"><span class="eyebrow">BOOKING / '.h($b['code']).'</span><h1>'.h($b['title']).'</h1><p>'.badge($b['status']).' · Khởi hành '.day($b['start_date']).' · '.(int)$b['people'].' khách</p></div><div class="checkout-grid"><div><section class="panel"><h2>Tiến trình đặt tour</h2><div class="progress-list"><div class="done">✓ Đặt thành công, đã tạm giữ chỗ <small>'.dt($b['created_at']).'</small></div><div class="'.($b['status']==='pending_seller'?'current':'done').'">'.($b['status']==='pending_seller'?'○':'✓').' Seller xác nhận <small>'.($b['status']==='pending_seller'?'Hạn '.dt($b['seller_deadline']):($b['confirmed_at']?'Xác nhận '.dt($b['confirmed_at']):'Trạng thái cuối: '.h($b['status']))).'</small></div><div class="'.(in_array($b['status'],['confirmed','deposited','paid'],true)?'current':'').'">○ Khai báo hành khách & thanh toán <small>'.($b['deposit_deadline']?'Hạn ban đầu '.dt($b['deposit_deadline']):'Chờ Seller xác nhận').'</small></div></div>';
    if ($b['status']==='pending_seller') echo '<div class="info-note">Booking đã giữ '.(int)$b['people'].' chỗ. Seller có tối đa 24 giờ để xác nhận hoặc từ chối. Chưa thể thanh toán tại bước này.</div>';
    if (in_array($b['status'],['rejected','expired','cancelled'],true)) echo '<div class="info-note">Booking kết thúc. Chỗ đã được trả về lịch. '.h($b['cancellation_reason'] ?: '').'</div>';
    echo '</section><section class="panel"><h2>Danh sách hành khách</h2><p>Cần khai báo đủ '.(int)$b['people'].' khách trước khi thanh toán. Khi đã lưu, không thể thay người.</p>';
    if (in_array($b['status'],['pending_seller','confirmed','deposited'],true) && (int)$b['customer_id']===$user['id']) {
        echo action_form('passengers_save').'<input type="hidden" name="booking_id" value="'.$id.'">';
        for($i=0;$i<(int)$b['people'];$i++) {
            $p=$passengers[$i] ?? [];
            echo '<div class="passenger"><h3>Hành khách '.($i+1).'</h3><div class="form-grid"><label>Họ tên<input name="passenger_name[]" value="'.h($p['full_name'] ?? '').'" required></label><label>Giới tính<select name="gender[]"><option value="female" '.(($p['gender'] ?? '')==='female'?'selected':'').'>Nữ</option><option value="male" '.(($p['gender'] ?? '')==='male'?'selected':'').'>Nam</option><option value="other" '.(($p['gender'] ?? '')==='other'?'selected':'').'>Khác</option></select></label><label>Ngày sinh<input type="date" name="dob[]" value="'.h($p['date_of_birth'] ?? '').'" required></label><label>Số điện thoại<input name="passenger_phone[]" value="'.h($p['phone'] ?? '').'" required></label>';
            if ($b['is_international']) echo '<label>Số hộ chiếu<input name="passport[]" value="'.h($p['passport_number'] ?? '').'" required></label><label>Hộ chiếu hết hạn<input type="date" name="passport_expiry[]" value="'.h($p['passport_expiry'] ?? '').'" required></label><label>Quốc tịch<input name="nationality[]" value="'.h($p['nationality'] ?? '').'" required></label>';
            echo '</div></div>';
        }
        echo '<button class="btn">Lưu danh sách hành khách</button></form>';
    } elseif ($passengers) {
        foreach($passengers as $i=>$p) echo '<p class="passenger-read">'.($i+1).'. <b>'.h($p['full_name']).'</b> · '.day($p['date_of_birth']).'</p>';
    } else echo '<p>Chưa có danh sách hành khách.</p>';
    echo '</section>';
    if ($b['status']==='paid') echo '<section class="panel voucher"><span class="eyebrow">PHIẾU XÁC NHẬN ĐẶT TOUR</span><h2>Hẹn gặp bạn trên hành trình!</h2><p>Mã booking: <b>'.h($b['code']).'</b> · Điểm đón: '.h($b['pickup']).' · Giờ tập trung: '.h($b['meeting_time'] ?: 'Seller sẽ thông báo').'</p><p>Đã thanh toán mô phỏng: <b>'.money((int)$b['paid_amount']).'</b>. Chính sách: '.h($b['policy_snapshot']).'.</p><button class="btn btn-outline" onclick="window.print()">In phiếu xác nhận</button></section>';
    echo '</div><aside><div class="panel sticky order-summary"><img src="'.h($b['image_path']).'" alt="'.h($b['title']).'"><h3>Tóm tắt thanh toán</h3><p>Giá cơ bản <b>'.(int)$b['people'].' × '.money((int)$b['base_unit']).'</b></p><p>'.h($b['surcharge_label']).' <b>'.(int)$b['people'].' × '.money((int)$b['surcharge_unit']).'</b></p><hr><p>Tổng tiền <strong>'.money((int)$b['total']).'</strong></p><p>Đã thanh toán <b>'.money((int)$b['paid_amount']).'</b></p><p>Còn lại <b>'.money((int)$b['total']-(int)$b['paid_amount']).'</b></p><p class="muted">Hạn trả phần còn lại: '.dt($b['balance_deadline']).'</p>';
    if (in_array($b['status'],['confirmed','deposited'],true) && (int)$b['customer_id']===$user['id']) {
        echo '<div class="payment-box"><h3>Thanh toán bản thử nghiệm</h3><p>Không kết nối cổng và không thu tiền thật.</p>';
        if(count($passengers)===(int)$b['people']) {
            echo action_form('demo_pay').'<input type="hidden" name="booking_id" value="'.$id.'">';
            if ($b['status']==='confirmed' && !$b['full_required']) echo '<label class="radio-choice"><input name="payment_choice" type="radio" value="deposit" checked> Đặt cọc 50% · '.money((int)ceil((int)$b['total']/2)).'</label>';
            echo '<label class="radio-choice"><input name="payment_choice" type="radio" value="full" '.(($b['status']==='deposited'||$b['full_required'])?'checked':'').'> '.($b['status']==='deposited'?'Trả phần còn lại':'Thanh toán 100%').' · '.money((int)$b['total']-(int)$b['paid_amount']).'</label>';
            if ($b['full_required']) echo '<p class="info-note">Lịch khởi hành gần: booking bắt buộc thanh toán 100%.</p>';
            echo '<button class="btn btn-wide">Ghi nhận giao dịch mô phỏng</button></form>';
        } else echo '<p class="info-note">Hoàn tất danh sách hành khách để mở bước thanh toán.</p>';
        echo '</div>';
    }
    if ($payments) {echo '<hr><h3>Lịch sử giao dịch</h3>'; foreach($payments as $p) echo '<p>'.h($p['reference']).'<br><b>'.money((int)$p['amount']).'</b> · '.dt($p['created_at']).'</p>';}
    echo '</div></aside></div></main>'; foot();
}
function bookings_page(): void {
    $user=require_role('customer'); $rows=q('SELECT b.*,t.title,t.image_path,d.start_date FROM bookings b JOIN departures d ON d.id=b.departure_id JOIN tours t ON t.id=d.tour_id WHERE b.customer_id=? ORDER BY b.id DESC',[$user['id']])->fetchAll();
    top('Booking của tôi'); echo '<main class="container section"><div class="page-intro"><span class="eyebrow">TÀI KHOẢN / BOOKING</span><h1>Hành trình của tôi</h1><p>Theo dõi phản hồi Seller, bổ sung hành khách và hoàn tất thanh toán.</p></div>';
    if (!$rows) echo '<div class="empty"><h2>Chưa có booking nào</h2><a class="btn" href="'.url('tours').'">Tìm tour đầu tiên</a></div>';
    else foreach($rows as $b) echo '<a class="booking-row panel" href="'.url('booking',['id'=>$b['id']]).'"><img src="'.h($b['image_path']).'" alt=""><div><small>'.h($b['code']).' · Khởi hành '.day($b['start_date']).'</small><h2>'.h($b['title']).'</h2><p>'.(int)$b['people'].' khách · '.money((int)$b['total']).'</p></div>'.badge($b['status']).'<span>Chi tiết →</span></a>';
    echo '</main>'; foot();
}

function current_seller(): array {
    $user=require_role('seller');
    $seller=q('SELECT * FROM sellers WHERE user_id=?',[$user['id']])->fetch();
    must((bool)$seller,'Chưa có hồ sơ Seller.');
    return $seller;
}
function portal_head(string $title, string $eyebrow): void {
    echo '<div class="portal-hero"><div class="container"><span class="eyebrow light">'.h($eyebrow).'</span><h1>'.h($title).'</h1><p>Quản lý tour, lịch khởi hành và booking trên cùng một hệ thống.</p></div></div>';
}
function seller_home(): void {
    $seller=current_seller(); top('Cổng Seller'); portal_head($seller['company_name'],'CỔNG SELLER / TỔNG QUAN');
    echo '<main class="container section">';
    if ($seller['status']!=='verified') {
        echo '<div class="panel"><h2>Hồ sơ Seller '.badge($seller['status']).'</h2><p>'.($seller['status']==='rejected'?'Lý do: '.h($seller['rejection_reason']).'. Bạn có thể bổ sung và gửi lại bên dưới.':'Quản trị viên đang kiểm tra hai giấy phép đã gửi. Bạn có thể đăng tour sau khi hồ sơ được phê duyệt.').'</p></div>';
        if ($seller['status']==='rejected') echo '<div class="panel"><h2>Bổ sung và gửi lại hồ sơ</h2><p>Mã Seller được giữ nguyên. Có thể thay giấy phép; để trống nếu tệp cũ còn đúng.</p>'.action_form('seller_resubmit','enctype="multipart/form-data"').'<div class="form-grid"><label>Doanh nghiệp<input name="company" value="'.h($seller['company_name']).'" required></label><label>Mã số thuế<input name="tax_code" value="'.h($seller['tax_code']).'" required></label><label>Địa chỉ<input name="address" value="'.h($seller['address']).'" required></label><label>Người đại diện<input name="representative" value="'.h($seller['representative']).'" required></label><label>Số điện thoại<input name="phone" value="'.h($seller['phone']).'" required></label><label>Loại hình<select name="travel_type"><option value="domestic">Nội địa</option><option value="international" '.($seller['travel_type']==='international'?'selected':'').'>Quốc tế</option></select></label><label>Thay giấy phép kinh doanh<input type="file" name="business_license" accept=".pdf,.jpg,.jpeg,.png"></label><label>Thay giấy phép lữ hành<input type="file" name="travel_license" accept=".pdf,.jpg,.jpeg,.png"></label></div><label class="check-line"><input type="checkbox" name="quota_agreement" required value="1"> Tôi xác nhận lại cam kết quỹ chỗ chỉ bán qua sàn.</label><button class="btn">Gửi lại hồ sơ →</button></form></div>';
    } else {
        $tourCount=(int)q('SELECT COUNT(*) FROM tours WHERE seller_id=?',[$seller['id']])->fetchColumn();
        $waitCount=(int)q("SELECT COUNT(*) FROM bookings b JOIN departures d ON d.id=b.departure_id JOIN tours t ON t.id=d.tour_id WHERE t.seller_id=? AND b.status='pending_seller'",[$seller['id']])->fetchColumn();
        echo '<div class="stats"><a class="stat panel" href="'.url('seller_tours').'"><span>Tour của tôi</span><strong>'.$tourCount.'</strong><small>Xem danh sách, xem tour, sửa và mở lịch ↗</small></a><a class="stat panel" href="'.url('seller_bookings').'"><span>Chờ xác nhận</span><strong>'.$waitCount.'</strong><small>Xác nhận hoặc từ chối trong 24 giờ ↗</small></a><a class="stat panel" href="'.url('seller_tour_form').'"><span>Đăng tour mới</span><strong>＋</strong><small>Tạo nháp, gửi kiểm duyệt ↗</small></a></div>';
    }
    echo '</main>'; foot();
}
function seller_tours_page(): void {
    $seller=current_seller(); top('Tour của tôi'); portal_head('Tour của tôi','UC15 / QUẢN LÝ TOUR'); echo '<main class="container section"><div class="section-head"><div><h2>Danh sách tour</h2><p>Chọn “Xem tour” để kiểm tra bản hiển thị và lịch khởi hành.</p></div>'.($seller['status']==='verified'?'<a class="btn" href="'.url('seller_tour_form').'">+ Tạo tour</a>':'').'</div>';
    $rows=q('SELECT * FROM tours WHERE seller_id=? ORDER BY updated_at DESC',[$seller['id']])->fetchAll();
    if (!$rows) echo '<div class="empty"><h2>Chưa có tour</h2><p>Hãy tạo tour và gửi quản trị viên kiểm duyệt.</p></div>';
    foreach($rows as $t) {
        $selling=$t['status']==='approved' && (bool)array_filter(departure_rows((int)$t['id']),fn($d)=>$d['status']==='open' && strtotime($d['cutoff_at'])>time() && $d['remaining']>0);
        echo '<div class="seller-tour panel"><img src="'.h($t['image_path']).'" alt="'.h($t['destination']).'"><div class="seller-tour-text">'.badge($selling?'selling':$t['status']).($t['pending_payload']?'<span class="badge">Bản sửa chờ duyệt</span>':'').'<h3>'.h($t['title']).'</h3><p>'.h($t['destination']).' · Giá cơ bản '.money((int)$t['base_price']).'/người</p><div class="inline-actions"><a class="btn btn-small" href="'.url('seller_tour',['id'=>$t['id']]).'">Xem tour</a>';
        if ($t['status']!=='pending' && !$t['pending_payload']) echo '<a class="btn btn-outline btn-small" href="'.url('seller_tour_form',['id'=>$t['id']]).'">Sửa & gửi duyệt</a>';
        if ($seller['status']==='verified') echo action_form('tour_clone','class="inline-form"').'<input type="hidden" name="tour_id" value="'.(int)$t['id'].'"><button class="link-button">Nhân bản</button></form>';
        if (in_array($t['status'],['approved','paused'],true)) echo action_form('tour_pause','class="inline-form"').'<input type="hidden" name="tour_id" value="'.(int)$t['id'].'"><button class="link-button">'.($t['status']==='paused'?'Mở bán lại':'Tạm ngừng').'</button></form>';
        echo '</div></div></div>';
    }
    echo '</main>'; foot();
}
function seller_tour_form_page(): void {
    $seller=current_seller(); must($seller['status']==='verified','Hồ sơ Seller chưa được duyệt.');
    $id=(int)($_GET['id'] ?? 0); $t=[];
    if ($id) { $t=q('SELECT * FROM tours WHERE id=? AND seller_id=?',[$id,$seller['id']])->fetch(); must($t && $t['status']!=='pending' && !$t['pending_payload'],'Tour đang chờ kiểm duyệt.'); }
    top($id?'Cập nhật tour':'Tạo tour mới'); portal_head($id?'Cập nhật tour':'Đăng hành trình mới','SELLER / UC15');
    echo '<main class="container section narrow-wide"><a class="back-link" href="'.url('seller_tours').'">← Tour của tôi</a><div class="panel"><h2>'.($id?'Sửa tour & gửi duyệt lại':'Thông tin tour').'</h2>';
    if ($id && in_array($t['status'],['approved','paused'],true)) echo '<div class="info-note">Bản đang bán vẫn giữ nguyên trong lúc bản sửa chờ duyệt. Booking cũ không đổi giá và chính sách.</div>';
    if (!empty($t['rejection_reason'])) echo '<div class="info-note">Lý do từ chối trước đó: '.h($t['rejection_reason']).'</div>';
    echo action_form('tour_save','enctype="multipart/form-data"').'<input type="hidden" name="tour_id" value="'.$id.'"><div class="form-grid"><label class="span-2">Tên tour<input name="title" value="'.h($t['title'] ?? '').'" required></label><label>Điểm khởi hành<input name="origin" value="'.h($t['origin'] ?? '').'" required></label><label>Điểm đến<input name="destination" value="'.h($t['destination'] ?? '').'" required></label><label>Số ngày<input type="number" name="days" min="1" max="30" value="'.h((string)($t['days'] ?? 3)).'" required></label><label>Số đêm<input type="number" name="nights" min="0" max="29" value="'.h((string)($t['nights'] ?? 2)).'" required></label><label>Giá cơ bản/người (₫)<input type="number" name="base_price" min="1" value="'.h((string)($t['base_price'] ?? '')).'" required></label><label>Phụ thu bắt buộc/người (₫)<input type="number" name="surcharge_per_person" min="0" value="'.h((string)($t['surcharge_per_person'] ?? 0)).'" required></label><label class="span-2">Tên khoản phụ thu<input name="surcharge_label" value="'.h($t['surcharge_label'] ?? 'Phụ thu dịch vụ').'" required></label><label class="span-2">Mô tả hành trình<textarea name="description" rows="4" required>'.h($t['description'] ?? '').'</textarea></label><label class="span-2">Lịch trình (mỗi ngày một dòng)<textarea name="itinerary" rows="5" required>'.h($t['itinerary'] ?? '').'</textarea></label><label>Dịch vụ bao gồm<textarea name="included" rows="3" required>'.h($t['included'] ?? '').'</textarea></label><label>Không bao gồm<textarea name="excluded" rows="3" required>'.h($t['excluded'] ?? '').'</textarea></label><label>Điểm đón<input name="pickup" value="'.h($t['pickup'] ?? '').'" required></label><label>Phương tiện<input name="transport" value="'.h($t['transport'] ?? '').'" required></label><label>Nơi lưu trú<input name="accommodation" value="'.h($t['accommodation'] ?? '').'" required></label><label>Giấy tờ cần chuẩn bị<input name="required_documents" value="'.h($t['required_documents'] ?? '').'"></label><label>Chính sách hủy (khung sàn)<select name="cancellation_policy"><option value="Linh hoạt: hủy trước 7 ngày được hoàn 100%" '.(($t['cancellation_policy'] ?? '')==='Linh hoạt: hủy trước 7 ngày được hoàn 100%'?'selected':'').'>Linh hoạt · hủy trước 7 ngày hoàn 100%</option><option value="Tiêu chuẩn: hủy trước 14 ngày được hoàn 100%" '.(($t['cancellation_policy'] ?? '')==='Tiêu chuẩn: hủy trước 14 ngày được hoàn 100%'?'selected':'').'>Tiêu chuẩn · hủy trước 14 ngày hoàn 100%</option></select></label><label>Ảnh minh họa thực tế<select name="image_path">';
    foreach(['ha-long'=>'Hạ Long','hoi-an'=>'Hội An','sa-pa'=>'Sa Pa','phu-quoc'=>'Phú Quốc','da-lat'=>'Đà Lạt','ninh-binh'=>'Ninh Bình','bangkok'=>'Bangkok','tokyo'=>'Tokyo'] as $file=>$name) { $path='assets/images/'.$file.'.jpg'; echo '<option value="'.$path.'" '.(($t['image_path'] ?? '')===$path?'selected':'').'>'.$name.'</option>'; }
    if (isset($t['image_path']) && str_starts_with($t['image_path'],'assets/tours/')) echo '<option value="'.h($t['image_path']).'" selected>Ảnh Seller đã tải lên</option>';
    echo '</select></label><label class="span-2">Hoặc tải ảnh tour (JPG/PNG, tối đa 5 MB)<input type="file" name="tour_image" accept=".jpg,.jpeg,.png"></label><label class="check-line span-2"><input type="checkbox" name="is_international" value="1" '.(!empty($t['is_international'])?'checked':'').'> Tour quốc tế/qua cửa khẩu (yêu cầu giấy tờ và hạn trả phần còn lại sớm hơn)</label></div><div class="inline-actions">';
    if (!$id || in_array($t['status'],['draft','rejected'],true)) echo '<button class="btn btn-outline" type="submit" name="intent" value="draft" formnovalidate>Lưu nháp</button>';
    echo '<button class="btn" type="submit" name="intent" value="submit">Gửi kiểm duyệt →</button></div></form></div></main>'; foot();
}
function seller_tour_page(): void {
    $seller=current_seller(); $id=(int)($_GET['id'] ?? 0);
    $t=q('SELECT * FROM tours WHERE id=? AND seller_id=?',[$id,$seller['id']])->fetch(); must((bool)$t,'Tour không thuộc tài khoản Seller này.');
    $deps=departure_rows($id); $history=q("SELECT a.*,u.name FROM audit_log a LEFT JOIN users u ON u.id=a.actor_id WHERE a.object_type='tour' AND a.object_id=? ORDER BY a.id DESC LIMIT 12",[$id])->fetchAll();
    top('Xem tour · '.$t['title']); portal_head('Xem tour','SELLER / UC15 / GD XEM TOUR');
    $selling=$t['status']==='approved' && (bool)array_filter($deps,fn($d)=>$d['status']==='open' && strtotime($d['cutoff_at'])>time() && $d['remaining']>0);
    echo '<main class="container section"><a class="back-link" href="'.url('seller_tours').'">← Tour của tôi</a><div class="detail-grid"><div><div class="panel seller-detail"><img class="compare-img" src="'.h($t['image_path']).'" alt="'.h($t['destination']).'"><p>'.badge($selling?'selling':$t['status']).($t['pending_payload']?' <span class="badge">Bản sửa chờ duyệt</span>':'').'</p><h2>'.h($t['title']).'</h2><p>'.h($t['origin']).' → '.h($t['destination']).' · '.(int)$t['days'].'N'.(int)$t['nights'].'Đ</p><p>'.nl2br(h($t['description'])).'</p><div class="detail-facts"><div><small>Giá cơ bản</small><b>'.money((int)$t['base_price']).'/người</b></div><div><small>Phụ thu</small><b>'.money((int)$t['surcharge_per_person']).'/người</b></div></div><h3>Lịch trình</h3><p>'.nl2br(h($t['itinerary'])).'</p><h3>Dịch vụ</h3><p><b>Bao gồm:</b> '.h($t['included']).'<br><b>Không gồm:</b> '.h($t['excluded']).'</p><h3>Giấy tờ & chính sách</h3><p>'.h($t['required_documents'] ?: 'Giấy tờ tùy thân hợp lệ').' · '.h($t['cancellation_policy']).'</p>';
    if ($t['rejection_reason']) echo '<p class="info-note">Lý do từ chối: '.h($t['rejection_reason']).'</p>';
    if ($t['pending_payload']) echo '<p class="info-note">Bản sửa của bạn đang chờ quản trị viên duyệt. Thông tin bên trên là bản hiện có.</p>';
    if ($t['status']!=='pending' && !$t['pending_payload']) echo '<a class="btn btn-outline" href="'.url('seller_tour_form',['id'=>$id]).'">Sửa tour ↗</a>';
    echo '</div><div class="panel"><h2>Lịch sử thay đổi</h2>'; foreach($history as $item) echo '<p class="history-line">'.dt($item['created_at']).' · '.h($item['name'] ?? 'Hệ thống').' · '.h($item['action']).' '.h($item['detail']).'</p>'; echo '</div></div><aside><div class="panel"><h2>Lịch khởi hành & quỹ chỗ</h2>';
    foreach($deps as $d) echo '<div class="dep-admin"><b>'.day($d['start_date']).'</b> '.badge($d['status']).'<p>Còn '.(int)$d['remaining'].' / '.(int)$d['capacity'].' chỗ · Giá '.money((int)$d['price_per_person']).'/người · Chốt '.dt($d['cutoff_at']).'</p>'.($d['status']!=='cancelled' && strtotime($d['cutoff_at'])>time()?action_form('departure_toggle').'<input type="hidden" name="departure_id" value="'.(int)$d['id'].'"><button class="link-button">'.($d['status']==='open'?'Đóng bán':'Mở bán lại').'</button></form>':'').'</div>';
    if ($t['status']==='approved') echo '<hr><h3>Thêm lịch khởi hành</h3>'.action_form('departure_save').'<input type="hidden" name="tour_id" value="'.$id.'"><label>Ngày khởi hành<input type="date" name="start_date" min="'.date('Y-m-d',strtotime('+3 days')).'" required></label><label>Tổng chỗ dành cho sàn<input type="number" name="capacity" min="1" max="999" required></label><label>Giá/người của lịch (₫)<input type="number" name="price_per_person" min="1" value="'.(int)$t['base_price'].'" required></label><label>Giờ tập trung (không bắt buộc)<input name="meeting_time" placeholder="06:30 tại điểm đón"></label><label class="check-line"><input type="checkbox" name="quota_agreement" value="1" required> Quỹ chỗ này chỉ bán qua sàn.</label><button class="btn btn-wide">Mở lịch khởi hành</button></form>';
    else echo '<p>Chỉ thêm lịch sau khi tour được duyệt và đang hoạt động.</p>';
    echo '</div></aside></div></main>'; foot();
}
function seller_bookings_page(): void {
    $seller=current_seller(); top('Booking của Seller'); portal_head('Booking cần xử lý','SELLER / ĐƠN ĐẶT TOUR');
    $rows=q('SELECT b.*,t.title,d.start_date FROM bookings b JOIN departures d ON d.id=b.departure_id JOIN tours t ON t.id=d.tour_id WHERE t.seller_id=? ORDER BY b.id DESC',[$seller['id']])->fetchAll();
    echo '<main class="container section">';
    if(!$rows) echo '<div class="empty"><h2>Chưa có booking nào</h2></div>';
    foreach($rows as $b) {
        echo '<article class="panel seller-booking"><div><span class="eyebrow">'.h($b['code']).'</span><h2>'.h($b['title']).'</h2><p>'.day($b['start_date']).' · '.(int)$b['people'].' khách · '.h($b['contact_name']).' · '.h($b['contact_phone']).'</p><p>Tổng tiền '.money((int)$b['total']).' · Đã trả '.money((int)$b['paid_amount']).'</p><a class="text-link" href="'.url('booking',['id'=>$b['id']]).'">Xem chi tiết & hành khách ↗</a></div><div>'.badge($b['status']);
        if($b['status']==='pending_seller' && strtotime($b['seller_deadline'])>time()) {
            echo '<p class="muted">Hạn '.dt($b['seller_deadline']).'</p>'.action_form('booking_decide').'<input type="hidden" name="booking_id" value="'.(int)$b['id'].'"><label>Lý do nếu từ chối<input name="reason" placeholder="Bắt buộc khi từ chối"></label><div class="inline-actions"><button class="btn btn-small" name="decision" value="confirm">Xác nhận</button><button class="btn btn-outline btn-small" name="decision" value="reject">Từ chối</button></div></form>';
        }
        echo '</div></article>';
    }
    echo '</main>'; foot();
}
function admin_page(): void {
    require_role('admin'); top('Quản trị ViVu Việt'); portal_head('Trung tâm kiểm duyệt','QUẢN TRỊ SÀN / HÀNG ĐỢI');
    $sellers=q("SELECT * FROM sellers WHERE status='pending' ORDER BY id DESC")->fetchAll();
    $tours=q("SELECT t.*,s.company_name FROM tours t JOIN sellers s ON s.id=t.seller_id WHERE t.status='pending' OR t.pending_payload IS NOT NULL ORDER BY t.updated_at DESC")->fetchAll();
    echo '<main class="container section"><div class="section-head"><div><span class="eyebrow">TB-01 · XÁC MINH SELLER</span><h2>Hồ sơ chờ xác minh <span class="count">'.count($sellers).'</span></h2></div></div>';
    if (!$sellers) echo '<div class="empty compact">Không có hồ sơ mới.</div>';
    foreach($sellers as $s) echo '<article class="panel review-card"><div><h3>'.h($s['company_name']).'</h3><p>MST '.h($s['tax_code']).' · '.h($s['representative']).' · '.h($s['phone']).'</p><p>'.h($s['address']).' · '.($s['travel_type']==='international'?'Lữ hành quốc tế':'Lữ hành nội địa').'</p><div class="inline-actions"><a href="file.php?type=business&id='.(int)$s['id'].'" target="_blank" rel="noopener">Giấy phép kinh doanh ↗</a><a href="file.php?type=travel&id='.(int)$s['id'].'" target="_blank" rel="noopener">Giấy phép lữ hành ↗</a></div></div>'.action_form('seller_review').'<input type="hidden" name="seller_id" value="'.(int)$s['id'].'"><label>Lý do nếu từ chối<input name="reason" placeholder="Bắt buộc khi từ chối"></label><div class="inline-actions"><button name="decision" value="verified" class="btn btn-small">Xác minh</button><button name="decision" value="rejected" class="btn btn-outline btn-small">Từ chối</button></div></form></article>';
    echo '<div class="section-head second-head"><div><span class="eyebrow">TB-02 · KIỂM DUYỆT NỘI DUNG</span><h2>Tour chờ duyệt <span class="count">'.count($tours).'</span></h2></div></div>';
    if (!$tours) echo '<div class="empty compact">Không có tour cần kiểm duyệt.</div>';
    foreach($tours as $t) {
        $data=$t['pending_payload']?json_decode($t['pending_payload'],true):$t;
        echo '<article class="panel review-card"><div><span class="eyebrow">'.($t['pending_payload']?'BẢN SỬA · TOUR VẪN BÁN THEO BẢN ĐÃ DUYỆT':'TOUR MỚI').'</span><h3>'.h($data['title']).'</h3><p>'.h($t['company_name']).' · '.h($data['origin']).' → '.h($data['destination']).' · '.(int)$data['days'].' ngày</p><p>Giá cơ bản '.money((int)$data['base_price']).'/người · Phụ thu '.money((int)$data['surcharge_per_person']).'/người</p><details><summary>Xem nội dung kiểm duyệt</summary><p>'.nl2br(h($data['itinerary'])).'</p><p><b>Gồm:</b> '.h($data['included']).' · <b>Không gồm:</b> '.h($data['excluded']).'</p><p><b>Giấy tờ:</b> '.h($data['required_documents']).' · <b>Chính sách:</b> '.h($data['cancellation_policy']).'</p><img class="review-img" src="'.h($data['image_path']).'" alt="Ảnh tour"></details></div>'.action_form('tour_review').'<input type="hidden" name="tour_id" value="'.(int)$t['id'].'"><label>Lý do nếu từ chối<input name="reason" placeholder="Bắt buộc khi từ chối"></label><div class="inline-actions"><button name="decision" value="approved" class="btn btn-small">Duyệt tour</button><button name="decision" value="rejected" class="btn btn-outline btn-small">Từ chối</button></div></form></article>';
    }
    echo '</main>'; foot();
}
function notices_page(): void {
    $user=me(); must((bool)$user,'Vui lòng đăng nhập.');
    $rows=q('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50',[$user['id']])->fetchAll();
    top('Thông báo'); echo '<main class="container section narrow-wide"><div class="page-intro"><span class="eyebrow">CẬP NHẬT TÀI KHOẢN</span><h1>Thông báo</h1></div><div class="panel">';
    if (!$rows) echo '<p>Chưa có thông báo nào.</p>';
    foreach($rows as $n) echo '<div class="history-line"><small>'.dt($n['created_at']).'</small><p>'.h($n['message']).'</p></div>';
    echo '</div></main>'; foot();
}
