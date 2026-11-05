# Kế hoạch kiểm thử SmartRecruit AI v3

## A. Chuẩn bị

- [ ] MySQL `3307` chạy và đã sao lưu database, chạy `upgrade_v3.sql` sau `upgrade.sql` (nếu cần).
- [ ] Apache `80`, FastAPI `8000` chạy; `/health` trả `version: 3.0.0`.
- [ ] Thư mục mã nguồn v3 giải nén **sạch**, `config.php` và `uploads` khôi phục từ backup.
- [ ] `python -m pytest -q` đạt; tất cả file PHP `php -l` không lỗi cú pháp.

## B. Ứng viên và CV

- [ ] Không tích consent -> PHP từ chối tải CV.
- [ ] Chọn TXT `docs/sample_cv.txt` -> trả email, skills, các mục có tiêu đề nếu có.
- [ ] PDF có lớp văn bản -> parse thành công; PDF giả -> 415; PDF scan -> 422 kèm hướng dẫn OCR.
- [ ] Nếu bật OCR Tesseract, PDF scan -> thử nghiệm thành công/đánh dấu cảnh báo OCR và đối chiếu thủ công.
- [ ] Sửa danh sách kỹ năng -> lưu `parsed_json.manual_review`, xem gợi ý thay đổi.
- [ ] Xóa CV -> không còn trong hồ sơ, `cv_consents` được xóa.
- [ ] CV tải trực tiếp `/uploads/randomname.pdf` -> Apache trả 403.
- [ ] Hai ứng viên không tải CV của nhau; recruiter chỉ tải CV của người đã ứng tuyển tin của mình.

## C. Nhà tuyển dụng, quản trị

- [ ] Tạo/sửa/ẩn tin, kiểm tra quyền sở hữu và reapproval sau edit.
- [ ] Admin duyệt, thông báo, nhật ký ghi `job.approved`.
- [ ] Ứng viên ứng tuyển, ứng tuyển trùng bị chặn, điểm hiển thị phù hợp nhưng không tự loại.
- [ ] Recruiter xem điểm chi tiết, ghi chú, cập nhật trạng thái, đặt lịch phỏng vấn.
- [ ] Admin khóa tài khoản thường, không thể khóa admin; xem trang nhật ký riêng.
- [ ] POST không có CSRF -> 419.

## D. Xử lý sự cố và kiểm thử đo đạc

- [ ] Tắt MySQL -> website có lỗi kết nối rõ, khởi động lại MySQL không mất dữ liệu.
- [ ] Tắt FastAPI -> upload báo mất kết nối; ứng tuyển vẫn lưu không có điểm (nếu CV đã có).
- [ ] Đo latency đọc 5 loại CV và ranking 10/50 JD; ghi thông số CPU/RAM, số lần lặp, median/p95.
- [ ] Tạo tập CSV đã gán nhãn, chạy `evaluate.py` trên TF-IDF và SBERT, kiểm tra Precision/Recall/F1/AUC, ranking metric khi có query_id.
- [ ] Xem log PHP/Python nhưng không đưa thông tin nhạy cảm vào báo cáo.

## E. Kết luận kiểm thử

Mỗi test ghi: ID, thời gian, vai trò, bước thao tác, dữ liệu đầu vào, kết quả mong đợi, thực tế, Pass/Fail, ảnh minh họa và mã lỗi. Mọi mục chưa tự chạy thực tế phải để **Chưa kiểm thử**, không mặc định Pass chỉ vì code đã viết.
