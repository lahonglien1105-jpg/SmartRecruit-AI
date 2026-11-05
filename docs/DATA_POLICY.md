# Quyền riêng tư, đồng ý xử lý CV và giới hạn bảo mật

- Khi upload CV, ứng viên phải tích đồng ý xử lý CV cho mục đích phân tích NLP/gợi ý việc làm/ứng tuyển. Ghi phiên bản đồng ý và thời gian vào `cv_consents`.
- CV gốc và văn bản trích xuất lưu trên máy chủ cục bộ (MySQL + `uploads`). Không gọi API AI ngoài Internet theo mặc định. Nếu bật SBERT, thư viện tải **mô hình** lần đầu qua mạng, không tự gửi CV tới Hugging Face qua endpoint ứng dụng này.
- CV là dữ liệu cá nhân, có thể chứa thông tin sức khỏe, quê quán, ngày sinh, giới tính v.v. Không sử dụng những thuộc tính này để đưa ra quyết định tuyển dụng hay xếp hạng; code tính điểm chỉ dựa văn bản tổng thể và kỹ năng nhận diện, nên vẫn có nguy cơ thiên lệch gián tiếp và cần đánh giá thực nghiệm.
- Người dùng có thể xóa CV khỏi hồ sơ; ứng dụng **chưa** hỗ trợ toàn diện quyền xóa dữ liệu liên quan đơn cũ/thông báo/bản sao lưu, cần nêu giới hạn rõ khi bảo vệ hoặc trước khi triển khai thật.
- File CV được bảo vệ bằng `uploads/.htaccess` và kiểm tra quyền qua `app/download.php`, **cần kiểm tra Apache có `AllowOverride` phù hợp**. Môi trường production cần đặt file ngoài webroot.
- `audit_events` lưu người thao tác, tên hành động, loại/ID đối tượng, thời gian; không lưu toàn văn CV, mật khẩu, email trong nhật ký.
- Không dùng CV thật của người khác làm dữ liệu khóa luận khi chưa có quyền. Không công khai dataset chứa PII. Nếu cần công bố, chỉ sử dụng mẫu giả lập hoặc văn bản đã ẩn danh và được phép chia sẻ.
- Bảo mật sản phẩm thực tế vẫn thiếu các yêu cầu như HTTPS, quét mã độc, rate limiting theo IP/tài khoản, phân tích upload zip bomb toàn diện, lịch xóa tự động, mã hóa dữ liệu nhạy cảm khi lưu, quyền truy cập vận hành và cơ chế bảo lưu CV tại thời điểm ứng tuyển.
- Điểm AI là **điểm trùng khớp**, không phải tỷ lệ tuyển dụng thành công hay phán quyết về năng lực.
