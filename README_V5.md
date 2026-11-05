# SmartRecruit AI v5 — Email tự động + phản hồi phỏng vấn + bộ deploy Railway

Dựa trên bản v4.1, giữ nguyên PHP + MySQL + FastAPI + giao diện/AI hiện có.

## Các thay đổi

- Xác minh email bằng token ngẫu nhiên 32 bytes **lưu hash SHA-256**, hết hạn sau 24 giờ, giới hạn gửi lại 90 giây.
- Email thông báo giao dịch: tài khoản, ứng tuyển mới đến nhà tuyển dụng, cập nhật trạng thái đơn, lịch phỏng vấn đến ứng viên, phản hồi phỏng vấn đến nhà tuyển dụng.
- Ứng viên có thể **xác nhận** hoặc **đề nghị đổi lịch** phỏng vấn từ trang *Đơn ứng tuyển*; phản hồi hiển thị tại trang ứng viên của nhà tuyển dụng.
- Tùy chọn nhận email tại Hồ sơ; quản trị viên xem trang **Nhật ký gửi email**; lưu **email_outbox** để kiểm tra trạng thái/gửi lại khi lỗi. Gửi qua **Resend HTTPS**, tương thích Railway các gói có giới hạn SMTP.
- File `Dockerfile`, `ai_service/Dockerfile`, `deploy/RAILWAY_SETUP.md` để deploy PHP, API và MySQL thành các dịch vụ riêng, Railway tự cấp URL HTTPS sau khi deploy thành công.
- Sửa parser tiêu đề `KINH NGHIỆM VÀ DỰ ÁN` để hiểu thành **dự án**, không tự gán kinh nghiệm làm việc nếu CV chỉ có đồ án.

## Nâng cấp từ v4 trên XAMPP

1. **Backup** database `smartrecruit` trên cổng **3307**, thư mục source và `uploads/` trước khi đụng vào file.
2. Giải nén phiên bản v5 vào thư mục mới, giữ bản cũ làm backup. Chuyển sang bản mới **chỉ `config.php` và file CV trong `uploads`**, không chép `app/`, `views/`, `ai_service/` cũ.
3. Trên DB v4 chạy **một lần** `upgrade_v5.sql`. Trên DB mới chạy lần lượt `database.sql`, `upgrade.sql`, `upgrade_v3.sql`, `upgrade_v5.sql`.
4. Apache/MySQL chạy như trước, bật FastAPI qua `start_ai.bat`. Đăng nhập và dùng chức năng cũ bình thường dù chưa cấu hình Resend.
5. Để email **thực sự gửi**, tạo API key Resend, dùng domain đã xác minh. Trên Windows, nếu dùng XAMPP, có thể thêm các dòng `putenv('RESEND_API_KEY=...'); putenv('MAIL_FROM=no-reply@domain-da-xac-minh.com'); putenv('APP_URL=http://localhost/SmartRecruit');` ở đầu file `config.php` (tệp này được `.gitignore`, **không đăng lên GitHub**), trước khi bootstrap gọi mailer. Hoặc đặt biến môi trường ở hệ điều hành rồi khởi động lại XAMPP Control Panel và Apache. Trên hosting set variables bằng Railway Dashboard. Không gửi keys qua chat, không commit secrets.
6. Với hệ thống public: `MAIL_VERIFY_REQUIRED=1` và `APP_URL=https://<domain Railway thực tế>`.
7. Kiểm tra email trong bảng `email_outbox` bằng SQL ở `deploy/RAILWAY_SETUP.md`.

**Lưu ý**: Chưa có Resend key thì chỉ thông báo trong website, không email ra Gmail; nút "Gửi email xác minh" sẽ báo chưa cấu hình. Các tài khoản v4 không được tự mặc định là email đã xác minh; cần xác minh qua Hồ sơ để nhận email.

## Luồng kiểm thử chính

- Đăng ký bằng email thật, mở thư xác minh, nhấp liên kết (hoặc nút gửi lại).
- Nhà tuyển dụng **đã xác minh email** đăng tin → admin duyệt → ứng viên tải CV và ứng tuyển → recruiter nhận email và notification.
- Nhà tuyển dụng đổi `reviewing`, `shortlisted` → ứng viên nhận email + thông báo.
- Nhà tuyển dụng lên lịch phỏng vấn → ứng viên nhận email có giờ/địa điểm/link → vào Đơn ứng tuyển chọn Xác nhận hoặc Đề nghị đổi lịch → recruiter nhận email và xem phản hồi tại hồ sơ ứng viên.
- Tắt tạm Resend để kiểm tra email **pending**; bật lại và chạy `php bin/mail_worker.php --once` rồi kiểm tra retry.
- Kiểm tra email không nhận khi chưa xác minh và khi tắt tùy chọn thông báo.

## Cảnh báo chất lượng

- `sent` nghĩa API bên gửi đã nhận thư, không đảm bảo thư tới Inbox; cần webhooks nếu muốn theo dõi `delivered/bounced`.
- Các file CV chứa dữ liệu cá nhân, không public uploads. `APP_URL` phải chính xác vì liên kết trong email được tạo từ đây.
- Chưa chạy kiểm thử end-to-end trên MySQL và tài khoản Railway của người dùng; không tuyên bố đã phát hành URL công khai.
