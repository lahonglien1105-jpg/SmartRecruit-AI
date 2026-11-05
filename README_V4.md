# SmartRecruit AI v4.0 — Giao diện hiện đại, tìm việc nâng cao

Đây là bản **nâng cấp trực tiếp trên SmartRecruit AI v3.0**, giữ nguyên PHP + MySQL/MariaDB + Python FastAPI. Không cần React, Node.js, hoặc thư viện giao diện ngoài. Phần AI/NLP vẫn là phiên bản v3 đã có; v4 tập trung vào thiết kế, trải nghiệm và các cải tiến nghiệp vụ có thể trình diễn.

## I. Điểm mới đã viết mã

**Giao diện**
- Trang chủ thiết kế lại: hero hiện đại, minh họa luồng phân tích CV, các từ khóa tìm kiếm nhanh, giới thiệu tính năng.
- Hệ thống màu navy/xanh dương, card việc làm mới, sidebar phân vai trò, chuyển trạng thái có phản hồi trực quan.
- Chế độ **sáng/tối** bằng nút góc phải thanh trên; ghi nhớ lựa chọn trong trình duyệt.
- Bố cục thích ứng điện thoại/tablet, menu di động có thuộc tính truy cập, liên kết bỏ qua điều hướng, focus rõ khi dùng bàn phím.
- Cải thiện kiểu hiển thị của dashboard, biểu mẫu, thông báo và kết quả AI; CSS v4 nằm ở `assets/v4.css` để giảm ảnh hưởng lên code gốc.

**Chức năng đã bổ sung thực sự**
- Lọc tin theo **cấp bậc** (thực tập, junior, middle, senior), kết hợp các bộ lọc có sẵn: từ khóa, địa điểm, hình thức, loại công việc.
- **Sắp xếp** tin theo mới nhất/cũ nhất/tên A–Z, cập nhật kết quả khi chọn; giữ tham số lọc qua các trang.
- **Lưu/bỏ lưu công việc ngay từ thẻ việc làm** với CSRF và quyền ứng viên; trở về trang danh sách thay vì luôn chuyển qua chi tiết.
- **Chia sẻ liên kết** trên trang chi tiết công việc (sao chép URL khi trình duyệt hỗ trợ).
- **Checklist 4 bước trên dashboard ứng viên**: có CV, hoàn thiện hồ sơ liên hệ, đã lưu việc, đã ứng tuyển. Tiến độ tính từ dữ liệu thực trong MySQL, không phải đánh giá năng lực AI.
- JavaScript cải thiện điều khiển menu, tự nộp biểu mẫu sắp xếp, ngăn submit trùng, nút sao chép link.

**Tính năng v3 giữ nguyên**: 3 vai trò, duyệt tin, quản lý tuyển dụng/ứng viên, upload CV, kiểm tra kỹ năng AI, ranking TF-IDF/Sentence-BERT, OCR tùy chọn, lịch phỏng vấn, thông báo, audit log.

## II. Nâng cấp lên v4 KHÔNG mất dữ liệu

1. **Dừng thao tác website, sao lưu database** `smartrecruit` từ phpMyAdmin. Xác nhận kết nối đúng MySQL XAMPP trên **cổng 3307** (không phải MySQL80/3306).
2. Đổi tên thư mục hiện tại `C:\xampp\htdocs\SmartRecruit` thành `SmartRecruit_backup` (nếu đã có tên này, dùng tên khác). **Không xóa bản cũ.**
3. Giải nén ZIP v4 vào `C:\xampp\htdocs\`, đảm bảo có `C:\xampp\htdocs\SmartRecruit\index.php`.
4. Từ backup, chỉ chuyển **`config.php`** và file CV cũ trong **`uploads/`** sang thư mục mới; không trộn `views`, `app`, `assets`, `index.php`, `ai_service` cũ.
5. Nếu DB đã chạy **v3**, **không cần import SQL nào** cho các tính năng v4. Nếu DB mới chỉ có v2, chạy `upgrade_v3.sql` sau khi sao lưu. Nếu chỉ có v1 thì chạy `upgrade.sql` rồi `upgrade_v3.sql` theo hướng dẫn v3.
6. Bật Apache và MySQL trong XAMPP. Trong `config.php`, kiểm tra cổng MySQL `3307`, tên DB `smartrecruit` và `AI_URL=http://127.0.0.1:8000`.
7. Khởi động AI bằng `start_ai.bat`. Nếu chưa có `.venv` trong bộ nguồn mới, chạy `install_ai.bat` trước. **Không sao chép `.venv` từ thư mục cũ**; đường dẫn môi trường ảo có thể bị sai. Bật OCR hoặc SBERT theo tài liệu `README.md` nếu cần.
8. Mở `http://localhost/SmartRecruit/` và nhấn **Ctrl + Shift + R** để bỏ bộ nhớ đệm CSS/JS.
9. Thử đăng nhập, tìm việc/lọc, lưu công việc, tải CV, ứng tuyển, xem nhà tuyển dụng và duyệt tin của admin. Chỉ khi mọi việc chạy tốt mới dọn bản backup.

**Bảo mật**: Dự án demo chạy trên `localhost`, chưa được kiểm thử toàn bộ trên máy của người dùng. Không đưa MySQL mật khẩu lên GitHub, không mở website ra Internet công khai trước khi bổ sung bảo mật và rà soát quyền truy cập.

## III. Kiểm tra nhanh sau nâng cấp

| Thử nghiệm | Kết quả mong đợi |
|---|---|
| Khách xem trang chủ | Hero và danh sách tin hiển thị tốt trên desktop/mobile |
| Tìm PHP + chọn Junior | Kết quả chỉ chứa tin đáp ứng bộ lọc |
| Đổi sắp xếp A–Z | Các tin xếp theo tiêu đề |
| Ứng viên lưu tin từ thẻ công việc | Trạng thái đổi sang đã lưu và xuất hiện trong trang Đã lưu |
| Ứng viên bỏ lưu ở trang Đã lưu | Tin biến mất khỏi trang Đã lưu |
| Ứng viên hoàn thiện checklist | Tiến độ tổng quan tăng đúng theo dữ liệu |
| Nút giao diện tối | Màu chuyển và giữ khi làm mới trang |
| Chia sẻ chi tiết tin | URL được sao chép (nếu có Clipboard API) |
| Nhà tuyển dụng/admin | Các luồng cũ vẫn hoạt động |
| AI Upload / Rank | FastAPI phản hồi, hoặc báo lỗi có thông tin như v3 |

## IV. Thứ tự các chức năng nên phát triển cho khóa luận (CHƯA LÀM trong v4)

1. **Lưu snapshot CV theo từng đơn ứng tuyển** để nhà tuyển dụng thấy đúng bản CV vào thời điểm nộp.
2. **Nâng NLP**: trích xuất học vấn/kinh nghiệm có cấu trúc, chuẩn hóa kỹ năng tiếng Việt/Anh, so sánh TF-IDF và Sentence-BERT trên tập dữ liệu có nhãn.
3. **Gợi ý có giải thích** và đánh giá không thiên lệch, không sử dụng score để loại ứng viên tự động.
4. Quản lý tiến trình tuyển dụng theo Kanban, lọc ứng viên theo vòng phỏng vấn, gửi email nhắc lịch.
5. CV builder nhiều mẫu, nhiều phiên bản CV, lịch sử thay đổi, cho phép xóa/thu hồi dữ liệu.
6. Quản trị: dashboard xu hướng, xuất báo cáo CSV/Excel, kiểm soát hành động và nhật ký.
7. Bảo mật/phát hành: bảo vệ dữ liệu CV và privacy, email verification/reset, rate limiting, thử tải, kiểm thử xuyên vai trò.

**Đề tài gợi ý:** Xây dựng hệ thống hỗ trợ tuyển dụng thông minh ứng dụng NLP trong phân tích CV và gợi ý việc làm. Cần thực nghiệm và đánh giá bằng dữ liệu có nhãn thay vì chỉ trình diễn giao diện.
