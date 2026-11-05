# Những thay đổi so với SmartRecruit AI v2

**Có mã nguồn thay đổi thật:**

- Sửa nhận diện PDF: tìm chữ ký `%PDF-` trong 1024 byte đầu; cho phép MIME phổ biến; giữ chặn file không hợp lệ.
- PHP upload CV: giải phóng khóa session trong khi chờ cURL, hiển thị đúng lỗi TCP/FastAPI HTTP 413/415/422 thay vì gộp chung.
- API `/parse` v3: phân tích các mục học vấn/kinh nghiệm/dự án/chứng chỉ theo heading, trả `skill_evidence`, `method`, `warnings` và `review_required`.
- OCR opt-in qua Tesseract cục bộ (`ENABLE_OCR=1`, `requirements-ocr.txt`); không tuyên bố OCR hoạt động nếu chưa cài Tesseract ngoài Python.
- Ứng viên bắt buộc xác nhận đồng ý xử lý CV; cho phép chỉnh danh sách kỹ năng do AI nhận diện, lưu dấu xác nhận tự khai.
- AI `/match` và `/rank`: bổ sung `required_skills`, `explanation`, `method`, `limitations`, giữ công thức v2 để đối chiếu số liệu.
- Người dùng thấy giải thích điểm gợi ý và phương thức đọc CV; nhà tuyển dụng thấy cảnh báo điểm không xác minh năng lực.
- Admin thêm `Nhật ký` trên giao diện; nhật ký hành động lưu trong `audit_events` (không log nội dung CV).
- Nâng cấp `upgrade_v3.sql` *không sửa các bảng v1/v2*; thêm `cv_consents`, `audit_events`.
- Bộ thực nghiệm CLI: Precision, Recall, F1, ROC-AUC, thêm MRR, nDCG@K, Precision@K khi có `query_id`; xuất JSON.
- Thêm `test_v3.py` kiểm thử PDF, CV scan, tài liệu lỗi, trích xuất heading, matching và metric.
- Thêm tài liệu đề cương, đánh giá, chính sách dữ liệu, kiểm thử, cùng BAT để cài/chạy nhanh AI trên Windows.

**Chưa có, không được xem là hoàn thiện:** các yêu cầu bảo mật production, workflow snapshot CV theo ứng tuyển, bảng thống kê đánh giá thực nghiệm trên dataset thật, xác minh email/quên mật khẩu, xử lý thiên lệch đầy đủ, kiểm thử end-to-end trên XAMPP của bạn. Các mục này cần ghi rõ trong phần giới hạn nghiên cứu hoặc triển khai thêm theo tiến độ.
