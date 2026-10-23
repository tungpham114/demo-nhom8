# ViVu Việt · TOURBUSINESSPROPOSAL8

Dự án PHP 8.1+ / MySQL 8 hoặc MariaDB 10.6+ chạy trên XAMPP. Tên thư mục trong `htdocs` **và** tên CSDL đều là `TOURBUSINESSPROPOSAL8`. Giao diện tiếng Việt, hình ảnh và font nằm trong dự án; không cần CDN hoặc npm khi chạy.

## Cài đặt trên Windows với XAMPP

1. Giải nén thư mục `TOURBUSINESSPROPOSAL8` vào `C:\xampp\htdocs\TOURBUSINESSPROPOSAL8`. Tránh lồng hai thư mục cùng tên.
2. Bật **Apache** và **MySQL** trong XAMPP Control Panel.
3. Mở `http://localhost/phpmyadmin`, chọn **Import**, nhập `database/TOURBUSINESSPROPOSAL8.sql`. File SQL tự tạo CSDL và bảng bằng `utf8mb4`.
4. Dữ liệu mẫu: mở Command Prompt và chạy:

   ```bat
   cd C:\xampp\htdocs\TOURBUSINESSPROPOSAL8
   C:\xampp\php\php.exe database\seed.php
   ```

5. Truy cập `http://localhost/TOURBUSINESSPROPOSAL8/` hoặc `http://localhost/TOURBUSINESSPROPOSAL8/index.php`.

Mặc định ứng dụng dùng MySQL `root`, mật khẩu rỗng, cổng `3306`. Nếu XAMPP khác cấu hình, khai báo biến môi trường `TOUR_DB_HOST`, `TOUR_DB_PORT`, `TOUR_DB_USER`, `TOUR_DB_PASSWORD` trước khi chạy Apache và PHP CLI. CSDL vẫn mang tên cố định `TOURBUSINESSPROPOSAL8`.

Nếu cần tải tệp gần mức 5 MB, đặt `upload_max_filesize = 6M` và `post_max_size = 12M` trong `C:\xampp\php\php.ini`, rồi khởi động lại Apache.

**Tài khoản demo** (chỉ dùng localhost):

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Khách hàng | `customer@vivuviet.test` | `Demo@12345` |
| Seller đã xác minh | `seller@vivuviet.test` | `Demo@12345` |
| Quản trị viên | `admin@vivuviet.test` | `Demo@12345` |

Chạy `seed.php` nhiều lần không nhân đôi dữ liệu đã tạo. Các lịch mẫu cách ngày chạy seed khoảng 35, 62, 90 ngày. Nếu để quá lâu, bạn có thể thêm lịch mới từ cổng Seller.

## Thử luồng nghiệp vụ

1. Ở vai trò **Khách**, tìm tour → xem chi tiết → chọn lịch → điền người liên hệ → nút **Tiếp tục** → kiểm tra giá cơ bản, phụ thu, tổng tiền và chính sách → **Xác nhận đặt & giữ chỗ**. Hệ thống khóa lịch và trừ quỹ chỗ trong giao dịch CSDL, tạo trạng thái `Chờ Seller xác nhận`.
2. Tại trang booking, nhập đủ hành khách. Đăng xuất, đăng nhập **Seller** → **Booking cần xử lý** → xác nhận booking trong hạn. Khách chưa thể thanh toán trước bước này.
3. Trở lại tài khoản **Khách** → booking → chọn **đặt cọc 50%** hoặc **trả 100%**. Đây là **giao dịch mô phỏng** và không thu tiền thật. Nếu hạn trả phần còn lại đến trước/trùng hạn cọc, hệ thống chỉ cho trả 100%. Trả đủ để hiển thị phiếu xác nhận có thể in.
4. **Seller** → **Tour của tôi** → **Xem tour** (UC15) → tạo lịch khởi hành, đóng/mở bán, xem lịch sử; hoặc tạo tour nháp và gửi duyệt.
5. **Quản trị** → duyệt/từ chối hồ sơ Seller và tour. Bản sửa tour đang bán nằm ở hàng chờ riêng, thông tin công khai chỉ đổi sau khi được duyệt.

Xử lý quá hạn bằng lệnh PHP CLI định kỳ (ví dụ Windows Task Scheduler mỗi phút):

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\TOURBUSINESSPROPOSAL8\cron.php
```

Tác vụ chuyển booking chờ Seller quá hạn thành `Bị từ chối`, booking đã xác nhận quá hạn cọc thành `Hết hạn`, booking đã cọc quá hạn trả nốt thành `Đã hủy`, và booking đã trả đủ thành `Hoàn thành` sau ngày kết thúc tour. Các trạng thái từ chối/hết hạn/hủy không còn chiếm quỹ chỗ. Các mốc nhắc hạn gửi email/SMS chưa tích hợp, chỉ có thông báo trong ứng dụng khi trạng thái đổi.

## Đối chiếu URD, SRS và điểm đã cập nhật

| Luồng | Trang/mã | Quy tắc đã nối |
| --- | --- | --- |
| TB-01 / UC13, UC23 | Đăng ký Seller, Quản trị | Xác minh email bằng OTP hiển thị trong bản thử nghiệm, hồ sơ và hai giấy phép, chờ xác minh, duyệt/từ chối có lý do và gửi lại hồ sơ bị từ chối. |
| TB-02 / UC15, CN05, CN06 | Tour của tôi, Xem tour, form tour, duyệt tour, lịch | **Bổ sung Xem tour trong UC15**; nháp → chờ duyệt → duyệt → lịch mở bán; nhân bản tour không sao chép lịch; lịch có quỹ chỗ và giá/người; bản sửa cần duyệt lại; dừng tour không hủy booking đang có. |
| TB-03 / UC01, UC06 | Tìm, chi tiết, đặt, tóm tắt, booking | Chỉ tour đã duyệt của Seller xác minh có lịch mở bán; khóa lịch và kiểm tra chỗ khi tạo booking; Seller có hạn 24 giờ hoặc đến hạn chốt bán. |
| NV6 trong URD | Booking, thanh toán mô phỏng | Chỉ mở sau Seller xác nhận và đủ hành khách; cọc 50% hoặc trả đủ; trả nốt trước hạn; mô phỏng voucher sau trả đủ. |
| Giá hiển thị theo yêu cầu mới | Thẻ tour, chi tiết, tóm tắt | **Giá cơ bản chưa gồm phụ thu** trên thẻ/chi tiết; tổng tiền và số cọc tính cả phụ thu trên tóm tắt. Đây là thay đổi so với quy tắc “giá đã gồm phụ thu” trong URD. |

Trang chi tiết không có tùy chọn phòng. Các lựa chọn phòng/option trong tài liệu cũ chưa được đưa vào bản chạy thử; dữ liệu phụ thu là khoản bắt buộc theo người và được ghi riêng. Tên hệ thống/nhãn hiệu là **ViVu Việt** theo URD; tên thư mục và CSDL theo yêu cầu là **TOURBUSINESSPROPOSAL8**.

## Phạm vi của bản localhost

Đây là bản nghiệp vụ chạy được, không phải hệ thống thanh toán thật. OTP Seller hiển thị ngay trên localhost để thử luồng, **không gửi email**. Chưa có: gửi email/SMS thực tế, quên mật khẩu, cổng thanh toán và webhook xác thực, hóa đơn VAT pháp lý, trả/hoàn tiền ngân hàng, payout Seller sau 4 ngày, cấu hình chính sách do admin thay đổi, danh sách tour đã lưu, đánh giá, hủy lịch có hoàn tiền, nhập Excel hành khách, cơ chế chống lạm dụng cho môi trường công khai. Chỉ dùng mô phỏng thanh toán để kiểm tra trạng thái. Trước khi dùng thật cần thay mô phỏng bằng cổng thanh toán, xử lý idempotent webhook, chính sách thuế/hoàn tiền, phân quyền và kiểm thử bảo mật.

## Cấu trúc

- `index.php`, `pages.php`, `actions.php`, `config.php`: định tuyến, giao diện, hành động và kết nối.
- `database/TOURBUSINESSPROPOSAL8.sql`: schema InnoDB và khóa ngoại.
- `database/seed.php`: ba tài khoản demo, tám tour và lịch khởi hành.
- `cron.php`: chuyển trạng thái quá hạn từ CLI.
- `file.php`, `private/`: đọc giấy phép có kiểm tra quyền; Apache bị chặn truy cập trực tiếp vào `private/`.
- `assets/`: logo ViVu Việt, giao diện, font và ảnh tour lưu cục bộ.

## Ảnh và font

Ảnh chụp thực tế từ Unsplash, lưu trong `assets/images/`:

| Điểm đến | Tác giả · ảnh gốc |
| --- | --- |
| Hạ Long | [Ryan Le](https://unsplash.com/photos/aerial-view-of-islands-in-a-bay-8-hEg1OuFn8) |
| Hội An | [Jess Silaya](https://unsplash.com/photos/a-street-lined-with-yellow-buildings-and-lots-of-balloons-ixoDBfBZlBU) |
| Đà Lạt | [THUY PHAM](https://unsplash.com/photos/a-city-in-the-mountains-9mSFninqIcw) |
| Sa Pa | [Daniele Franchi](https://unsplash.com/photos/terraced-rice-fields-and-village-in-the-hills-zaxhTnx8WFc) |
| Phú Quốc | [Vivu Vietnam](https://unsplash.com/photos/a-beach-with-palm-trees-and-a-white-sand-beach-r6CIzamxaek) |
| Ninh Bình | [Manoa Angelo](https://unsplash.com/photos/a-group-of-people-in-small-boats-on-a-river-C2xFKlOR0PE) |
| Bangkok | [Teodor Kuduschiev](https://unsplash.com/photos/wat-arun-temple-on-chao-phraya-river-bangkok-wd3tQvk0WXA) |
| Tokyo | [kiki](https://unsplash.com/photos/a-group-of-people-walking-across-a-street-next-to-tall-buildings-WUfCxlr-KHU) |

Ảnh dùng theo [Unsplash License](https://unsplash.com/license). Font Be Vietnam Pro đi kèm theo SIL Open Font License tại `assets/fonts/OFL.txt`.
