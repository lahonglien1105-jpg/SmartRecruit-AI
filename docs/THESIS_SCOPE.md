# Đề cương khóa luận — SmartRecruit AI v3

## 1. Tên đề tài đề xuất

**Xây dựng hệ thống hỗ trợ tuyển dụng thông minh ứng dụng xử lý ngôn ngữ tự nhiên để phân tích CV và gợi ý việc làm.**

Không khẳng định phù hợp tất cả quy định của trường/khoa; đề cương, mẫu báo cáo, số trang, tiêu chí nghiệm thu phải được giảng viên hướng dẫn phê duyệt.

## 2. Vấn đề & mục tiêu nghiên cứu

- Công việc tuyển dụng yêu cầu đối chiếu CV không cấu trúc và mô tả JD; phương pháp tìm từ khóa đơn giản có thể bỏ sót khác biệt diễn đạt.
- Xây dựng hệ thống 3 vai trò (ứng viên, nhà tuyển dụng, quản trị) có khả năng chạy trên Windows XAMPP + CPU.
- Nghiên cứu và so sánh **TF-IDF + Cosine Similarity** với **Sentence-BERT đa ngôn ngữ** trên cùng cặp CV–JD có nhãn.
- Giải thích điểm so khớp qua các kỹ năng tìm thấy/chưa tìm thấy; cho người dùng xác nhận kỹ năng và quản trị viên truy vết thao tác.
- Không tự động ra quyết định tuyển dụng; không dùng điểm như xác suất trúng tuyển.

## 3. Phạm vi, đối tượng

CV PDF/DOCX/TXT (tiếng Việt và tiếng Anh), JD chủ yếu lĩnh vực CNTT. OCR PDF scan tùy chọn. Không giải quyết phát hiện CV giả, suy đoán năng lực thực hay đánh giá phẩm chất ứng viên.

## 4. Yêu cầu chức năng và tiêu chí nghiệm thu

| ID | Yêu cầu | Kiểm chứng |
|----|---------|-----------|
| FR01 | 3 vai trò độc lập, đăng nhập bảo vệ | Ứng viên không truy cập `/applicants`; recruiter không vào `/admin_jobs` |
| FR02 | Thêm/sửa/ẩn tin, duyệt lại khi sửa | Tin sửa trở thành `pending`, chỉ admin duyệt |
| FR03 | Tìm kiếm, lọc và xem JD | Trả về đúng bộ lọc và trạng thái approved |
| FR04 | Upload PDF/DOCX/TXT, báo đúng lỗi | TXT mẫu thành công; PDF giả bị chặn; PDF scan báo OCR rõ |
| FR05 | Trích xuất email, phone, skills và mục nội dung | JSON từ `/parse`, ứng viên kiểm tra và chỉnh skills |
| FR06 | Gợi ý/ranking việc, chỉ rõ lý do | `/rank` trả score, semantic_score, skill_score, matched/missing |
| FR07 | Nộp đơn một lần, bảo vệ trùng | Unique(job_id,candidate_id), hiển thị theo vai trò |
| FR08 | Tuyển dụng thủ công, lịch phỏng vấn | Recruiter có thể cập nhật trạng thái/lịch, ứng viên nhận notice |
| FR09 | Quyền riêng tư và audit | Có consent khi upload, quyền tải CV có kiểm tra, nhật ký không chứa nội dung CV |
| FR10 | Thực nghiệm offline, xuất JSON | `evaluate.py` tính Precision/Recall/F1/AUC và ranking metric nếu có query_id |

## 5. Yêu cầu phi chức năng (đề xuất cho đánh giá)

- **Tính đúng:** prepared statements, password_hash, CSRF token, giới hạn file, role checks, chặn tải tệp trực tiếp (kiểm tra Apache thực tế).
- **Hiệu năng:** đo thời gian đọc CV và so khớp 1/10/50 JD trên laptop CPU; báo cáo trung bình và độ lệch chuẩn, không tự bịa tốc độ.
- **Khả năng sử dụng:** chức năng 3 vai trò có quy trình, thông báo lỗi dễ hiểu, responsive, form xác nhận consent.
- **Bảo trì:** code PHP tách `app/`, `views/`, `assets/`; Python tách API; migration tách riêng.
- **Đạo đức:** nghiên cứu trên CV giả lập hoặc đã xin phép và ẩn danh; không đánh giá/tiết lộ đặc điểm nhạy cảm; không tự loại ứng viên dựa vào điểm AI.

## 6. Thiết kế hệ thống

**Kiến trúc:** Client (HTML/CSS/JS) → PHP trên Apache → MySQL 3307; PHP gọi FastAPI 8000 với POST file hoặc JSON; FastAPI → PyMuPDF/python-docx/Tesseract nếu bật + TF-IDF/SBERT → JSON. File CV lưu cục bộ trong `uploads` với tên ngẫu nhiên và kiểm tra quyền tải bằng PHP.

**Các bảng:** v1: `users`, `jobs`, `resumes`, `applications`; v2: `candidate_profiles`, `recruiter_profiles`, `job_details`, `saved_jobs`, `notifications`, `user_flags`, `application_notes`, `interviews`; v3: `cv_consents`, `audit_events`.

**Use case chính:**
1. Ứng viên xác nhận consent → tải CV → API parse → xem/đính chính skills → lưu kỹ năng → gợi ý việc → ứng tuyển.
2. Recruiter đăng JD → admin duyệt → ứng viên ứng tuyển → AI tính điểm → recruiter xem giải thích và xử lý thủ công.
3. Admin duyệt tin / kiểm soát tài khoản / xem audit log.

## 7. Thực nghiệm & giá trị mới

Điểm mới cần **chứng minh** (không tự khẳng định): so sánh baseline TF-IDF và Sentence-BERT trên tiếng Việt; phân tích lỗi skill matching/đọc CV nhiều mẫu; cung cấp lý giải điểm; kết nối AI với quy trình ứng tuyển 3 vai trò. Xem `EVALUATION_PROTOCOL.md`.

## 8. Khung báo cáo đề xuất

1. Mở đầu (lý do, mục tiêu, phạm vi, phương pháp).
2. Cơ sở lý thuyết (NLP, vector hóa, TF-IDF, embedding, cosine, trích xuất PDF/OCR, chỉ số đánh giá).
3. Phân tích thiết kế (use-case, hoạt động, DFD hoặc activity, ERD, kiến trúc, an toàn dữ liệu).
4. Triển khai (môi trường, PHP/MySQL/FastAPI, CSDL, API, màn hình, kiểm thử).
5. Thực nghiệm (dữ liệu, nhãn, tách tập, bảng so sánh, ca lỗi, hạn chế).
6. Kết luận và hướng phát triển (OCR, skill normalization, mô hình tiếng Việt, security production).

**Tệp này là kế hoạch triển khai dựa trên yêu cầu phổ biến của khóa luận CNTT, không phải biểu mẫu chính thức của trường.**
