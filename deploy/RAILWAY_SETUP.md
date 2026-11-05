# Triển khai SmartRecruit AI v5 lên Railway — PHP + FastAPI + MySQL + Resend

> File ZIP là source sẵn sàng **để triển khai**, không phải bằng chứng đã có website công khai. Để tạo URL thật, cần triển khai trên tài khoản Railway, xác thực cấu hình và tạo domain. Không đưa CV hoặc dữ liệu MySQL thật của ứng viên lên môi trường thử nghiệm.

## 1. Chuẩn bị

- GitHub: tạo repository **private** chứa các file từ thư mục `SmartRecruit` (đưa `Dockerfile` ở root repo); kiểm tra `config.php`, `.env`, `uploads/` không được commit.
- Railway: cần đăng nhập và có project. Có thể tính phí hosting/volume/MySQL.
- Resend: tạo API key, xác minh domain riêng để gửi được tới Gmail của bất kỳ ứng viên nào. Sender thử nghiệm `onboarding@resend.dev` chỉ gửi đến tài khoản Resend của bạn.
- SQL: sao lưu database cũ trước khi chuyển. Trên database mới chạy lần lượt `database.sql`, `upgrade.sql`, `upgrade_v3.sql`, `upgrade_v5.sql`. Trên database đã ở v4 chỉ chạy `upgrade_v5.sql`.

## 2. Tạo 3 service Railway

**A. Database:** New → Database → MySQL; tên service đặt **MySQL**. Để database private; không mở public TCP nếu không cần. Import file `database.sql` để tạo database **smartrecruit** (dùng tài khoản có quyền CREATE DATABASE), sau đó lần lượt `upgrade.sql`, `upgrade_v3.sql`, `upgrade_v5.sql`; đặt `DB_NAME=smartrecruit` cho Web. Biến `MYSQLDATABASE` mặc định do Railway cung cấp có thể là `railway`, KHÔNG được trỏ web tới `railway` nếu SQL tạo database `smartrecruit`. Làm theo hướng dẫn Railway MySQL Connect (có thể dùng MySQL Workbench qua TCP proxy tạm thời, sau đó tắt lại). Không dùng MySQL `root` XAMPP localhost trên cloud.

**B. AI:** New → GitHub Repo → cùng repository, chọn **Root Directory `/ai_service`**. Railway sẽ phát hiện `ai_service/Dockerfile`. Đặt service tên `ai`, variable `PORT=8000`. Không tạo public domain cho service này, dùng private domain `ai.railway.internal` qua mạng riêng Railway. Sau deploy, kiểm tra log khởi động Uvicorn.

**C. Web:** New → GitHub Repo → cùng repository, **Root Directory `/`**. Railway sẽ dùng `Dockerfile` ở root. Cấu hình variables:

```text
PORT=8080
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=smartrecruit
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASS=${{MySQL.MYSQLPASSWORD}}
AI_URL=http://ai.railway.internal:8000
MAIL_VERIFY_REQUIRED=1
RESEND_API_KEY=...  (trong Railway Variables, KHÔNG đưa vào GitHub)
MAIL_FROM=no-reply@your-verified-domain.com
MAIL_FROM_NAME=SmartRecruit AI
```

Cần bảo đảm tên service `MySQL` và `ai` đúng thực tế; nếu tên khác, sửa tham chiếu và private domain tương ứng. Không ghi mật khẩu/API key vào file trong repository.

Trong Web Settings → Networking → **Generate Domain**. Sau khi Railway cấp `https://<ten>.up.railway.app`, thêm variable **`APP_URL=https://<ten>.up.railway.app`** và redeploy. APP_URL này dùng tạo liên kết xác minh email và trang xem thông báo. Dùng custom domain nếu có và cấu hình DNS/TLS theo Railway.

**D. CV bền vững:** Gắn Railway Volume vào **dịch vụ Web**, mount path `/var/www/html/uploads`. Đây là nơi PHP lưu CV. Nếu không gắn volume, CV có thể mất sau redeploy. Cần lên lịch backup CV và MySQL, giới hạn quyền truy cập của CV. Vùng source HTML public chỉ chứa `public/`, không công khai `uploads/` hoặc `app/`.

**E. Worker gửi email (tùy chọn nhưng khuyến nghị):** Tạo service thứ tư dùng cùng repo và cùng các biến `DB_*`, `RESEND_API_KEY`, `MAIL_FROM`, `MAIL_FROM_NAME`; root `/`, start command `php /var/www/html/bin/mail_worker.php --loop`. Worker dùng để gửi lại email bị lỗi do mạng/rate limit, không cần public domain. Web vẫn thử gửi ngay khi có sự kiện. Trong sản phẩm thực tế, nên dùng worker là cơ chế gửi chính để giảm thời gian chờ HTTP.

## 3. Gửi email thật bằng Resend

- Tại Resend, xác minh domain, đặt SPF/DKIM theo hướng dẫn. Chọn sender trùng domain, ví dụ `SmartRecruit AI <no-reply@yourdomain.com>`.
- Khai báo `RESEND_API_KEY` (bắt đầu `re_...`) và `MAIL_FROM` ở web + worker.
- Sự kiện: đăng ký gửi xác minh (24 giờ, resend sau 90 giây), ứng tuyển gửi nhà tuyển dụng đã xác minh, cập nhật đơn và lịch phỏng vấn gửi ứng viên đã xác minh, phản hồi lịch gửi nhà tuyển dụng.
- Đăng nhập sản phẩm cần `MAIL_VERIFY_REQUIRED=1` nếu đã cấu hình Resend. Tài khoản **cũ** chưa có dòng `user_emails` vẫn đăng nhập được để tránh khóa dữ liệu cũ; họ cần vào Hồ sơ → Gửi email xác minh để nhận thông báo email.
- Trong phpMyAdmin, xem trạng thái:

```sql
SELECT id,recipient_email,subject,status,attempts,left(last_error,160) AS last_error,created_at
FROM email_outbox ORDER BY id DESC LIMIT 30;
```

`sent` nghĩa Resend đã **chấp nhận gửi**, không bảo đảm người nhận đã đọc hoặc email đã đến Inbox. Với email `failed/pending`, xem `last_error`, kiểm tra sender domain, API key, hạn mức và worker.

## 4. Tránh các lỗi phổ biến

- Web trả `503`: thiếu DB variables hoặc MySQL chưa sẵn sàng.
- Không đọc được CV: AI_URL sai/private network service `ai` chưa chạy, hoặc PDF là ảnh scan chưa bật OCR.
- Có email pending: thiếu `RESEND_API_KEY`/`MAIL_FROM`, domain chưa verify hoặc Resend đang giới hạn gửi.
- Link xác minh dẫn tới localhost: chưa đặt `APP_URL` bằng URL Railway HTTPS rồi redeploy.
- Website localhost có `/SmartRecruit` nhưng web Railway chạy **ở gốc domain** `/`.
- Web v5 vẫn sử dụng **bảng legacy**. Không xóa các bảng `users`, `jobs`, `applications`, `resumes` hiện tại.
- Cần giới hạn admin, chống brute-force trên server, chính sách lưu CV, CSRF, session HTTPS, kiểm soát người xem CV và sao lưu trước khi nhận CV thật. Bản thử nghiệm KHÔNG được xem là đã kiểm thử an ninh production đầy đủ.

## 5. Hướng dẫn CLI nếu không dùng GitHub

Theo Railway CLI docs, sau khi đăng nhập và chọn project:

```powershell
npm install -g @railway/cli
railway login
railway init
railway up
```

Nhưng do dự án có **hai Dockerfile** và MySQL, sử dụng Dashboard tạo nhiều services từ GitHub như ở trên dễ kiểm soát hơn. Domain công khai phải được Railway tạo thật; không tự đặt địa chỉ `*.up.railway.app`.

## 6. Tạo admin sau khi import

Nếu là MySQL mới, bạn cần tạo admin bằng CLI *bên trong môi trường web đã cấu hình DB*:

```bash
php /var/www/html/create_admin.php admin@example.com 'THAY_BANG_MAT_KHAU_MANH_TU_10_KY_TU'
```

Thực hiện qua Railway SSH/CLI terminal của service Web. Không ghi mật khẩu trong GitHub hoặc các ảnh chụp công khai.
