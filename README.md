# SmartRecruit AI v4.0 — Website tuyển dụng và nghiên cứu NLP

**Nâng cấp giao diện và chức năng mới:** xem [README_V4.md](README_V4.md). Hướng dẫn bên dưới mô tả nền tảng v3 mà v4 kế thừa.

# SmartRecruit AI v3.0 — Khóa luận: Tuyển dụng thông minh ứng dụng NLP

Bản nâng cấp **trên nền SmartRecruit v2**: PHP 8.0+, MySQL/MariaDB, CSS/JS thuần, Python FastAPI, scikit-learn; tùy chọn Sentence-BERT và OCR Tesseract (đều chạy cục bộ). Đây là **bản triển khai nghiên cứu/MVP có mã nguồn**, không phải sản phẩm production và không được coi là đã kiểm thử end-to-end trên máy người dùng.

## I. Chức năng đã có mã nguồn

**Khách:** tìm kiếm/lọc công việc, xem mô tả, yêu cầu kỹ năng, điều kiện và quyền lợi.

**Ứng viên:** đăng ký/đăng nhập, hồ sơ cá nhân, upload/xóa/tải CV, xác nhận kỹ năng phát hiện, xem các mục học vấn/kinh nghiệm/dự án do AI gợi ý, xem bằng chứng kỹ năng, xem gợi ý/xếp hạng việc làm, lưu tin, ứng tuyển và theo dõi lịch phỏng vấn/thông báo.

**Nhà tuyển dụng:** hồ sơ doanh nghiệp, tạo/sửa/xóa/ẩn tin, xem hồ sơ ứng viên có quyền truy cập, xem điểm so khớp (không phải xác suất trúng tuyển), thay đổi trạng thái thủ công, ghi chú và đặt lịch phỏng vấn.

**Quản trị viên:** duyệt tin, quản lý tài khoản, dashboard, **nhật ký hành động v3**.

**AI:** API `GET /health`, `POST /parse`, `POST /match`, `POST /rank` dùng đọc PDF/DOCX/TXT, nhận diện email/điện thoại/kỹ năng, trích xuất theo tiêu đề phần học vấn/kinh nghiệm/dự án, bằng chứng kỹ năng, TF-IDF + cosine, tùy chọn embedding Sentence-BERT đa ngôn ngữ. **OCR PDF scan chỉ hoạt động nếu cấu hình Tesseract và `ENABLE_OCR=1`.**

## II. Nâng cấp an toàn từ v2 khi CSDL đã có tài khoản và công việc

**Không xóa DB `smartrecruit`, KHÔNG import `database.sql` lên DB cũ, KHÔNG ghi đè `config.php`.**

1. Trong phpMyAdmin kiểm tra máy chủ là `127.0.0.1:3307`; chọn `smartrecruit` → **Xuất** → SQL (lưu file dự phòng).
2. Đổi tên `C:\xampp\htdocs\SmartRecruit` cũ thành `SmartRecruit_backup_2026` (hoặc tên chưa tồn tại). **Giữ nguyên thư mục này** cho tới khi thử nghiệm thành công.
3. Giải nén **mới, sạch** thư mục `SmartRecruit` từ ZIP v3 vào `C:\xampp\htdocs\`, tuyệt đối không trộn file cũ/v2 vào file v3. Đường dẫn đúng: `C:\xampp\htdocs\SmartRecruit\index.php`.
4. Chỉ sao chép từ bản backup: `config.php` và **các CV** trong `uploads/` (không sao chép file PHP, JS, CSS hoặc `ai_service` cũ). Bảo đảm file `.htaccess` mới còn trong `uploads`.
5. Nếu CSDL đang ở v2: **chỉ import `upgrade_v3.sql`**, thêm 2 bảng `cv_consents`, `audit_events`. Nếu vẫn ở v1: phải import `upgrade.sql` của v2 trước rồi mới import `upgrade_v3.sql`. Cả hai không drop bảng cũ.
6. Trong `config.php`, xác nhận `DB_PORT='3307'`, `DB_NAME='smartrecruit'`, `AI_URL='http://127.0.0.1:8000'` cùng mật khẩu thật của MySQL XAMPP; không gửi mật khẩu cho người khác.
7. Rebuild Python venv vì di chuyển thư mục có thể làm hỏng đường dẫn môi trường ảo:

   ```powershell
   cd C:\xampp\htdocs\SmartRecruit\ai_service
   python -m venv .venv
   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
   .\.venv\Scripts\Activate.ps1
   python -m pip install --upgrade pip
   python -m pip install -r requirements.txt
   python -m uvicorn main:app --host 127.0.0.1 --port 8000 --reload
   ```

8. Xem `http://127.0.0.1:8000/health` có `version=3.0.0` và mở `http://localhost/SmartRecruit/`. Giữ cửa sổ FastAPI hoạt động khi thử AI.
9. Thử upload một file CV **TXT** mẫu ở `docs/sample_cv.txt` trước, sau đó thử PDF thật. Nếu lỗi: thử chính file tại `http://127.0.0.1:8000/docs` → `POST /parse`. Lỗi `422` mô tả nguyên nhân cụ thể.
10. Đăng nhập 3 vai trò để kiểm tra `docs/TEST_PLAN.md`. Nếu cần hoàn nguyên: đổi thư mục v3 sang tên khác và khôi phục backup; phục hồi SQL backup nếu phát hiện dữ liệu không khớp.

### Cài mới hoàn toàn (chỉ khi không có dữ liệu cần giữ)

Giải nén v3 và import `database.sql` vào MySQL, sao chép `config.example.php` thành `config.php` và sửa port/mật khẩu, khởi động FastAPI như trên. Tạo admin qua CLI:

```powershell
C:\xampp\php\php.exe create_admin.php admin@example.com MatKhauMoi123
```

## III. OCR PDF scan (tùy chọn)

Để chạy trên Windows, cài **Tesseract OCR** riêng (ứng dụng ngoài Python), thêm dữ liệu ngôn ngữ `vie`/`eng`, cài:

```powershell
cd C:\xampp\htdocs\SmartRecruit\ai_service
.\.venv\Scripts\Activate.ps1
python -m pip install -r requirements-ocr.txt
$env:ENABLE_OCR='1'
$env:OCR_LANG='vie+eng'
# Chỉ cần dòng dưới nếu tesseract.exe chưa nằm trong PATH
$env:TESSERACT_CMD='C:\Program Files\Tesseract-OCR\tesseract.exe'
python -m uvicorn main:app --host 127.0.0.1 --port 8000 --reload
```

Nếu `OCR_LANG='vie+eng'` không có dữ liệu tương ứng sẽ báo lỗi. Chất lượng OCR có thể kém với CV nhiều cột, font trang trí hoặc bảng biểu. **Chỉ đọc tối đa 10 trang OCR, tối đa 40 trang PDF, file tối đa 5MB.** Kết quả bắt buộc người dùng xác minh.

## IV. TF-IDF so với Sentence-BERT

Mặc định dùng TF-IDF (nhẹ, offline, hợp laptop không GPU). Để thử Sentence-BERT (mô hình tải từ Hugging Face trong lần đầu):

```powershell
cd C:\xampp\htdocs\SmartRecruit\ai_service
.\.venv\Scripts\Activate.ps1
python -m pip install -r requirements-sbert.txt
$env:USE_SBERT='1'
python -m uvicorn main:app --host 127.0.0.1 --port 8000 --reload
```

Đây là **chế độ tùy chọn**, có thể chạy chậm trên CPU. Khi so sánh hai phương pháp, giữ cố định tập dữ liệu và hệ số tính điểm. `score` là điểm so khớp quy đổi 0–100, không được diễn giải là xác suất được tuyển. Nếu phát hiện yêu cầu kỹ năng: `score = 0.65 * semantic_similarity + 0.35 * skill_overlap`; nếu không có yêu cầu kỹ năng được nhận diện, chỉ sử dụng tương đồng văn bản. Kỹ năng xác nhận thủ công do ứng viên tự khai, không được coi là đã kiểm chứng.

## V. Thực nghiệm có thể tái lập

```powershell
cd C:\xampp\htdocs\SmartRecruit\ai_service
.\.venv\Scripts\Activate.ps1
python -m pip install -r requirements-dev.txt
python -m pytest -q
python evaluate.py evaluation/sample_pairs.csv --threshold 0.5 --output evaluation/demo_results.json
```

Nếu có nhiều JD cho mỗi CV, thêm cột `query_id` vào CSV để tính `precision@k`, `MRR`, `nDCG@k`. Dữ liệu mẫu rất ít và **giả lập**; không dùng chỉ số minh họa làm thành tích khoa học. Chi tiết xem `docs/EVALUATION_PROTOCOL.md`.

## VI. Cấu trúc & tài liệu khóa luận

- `app/actions.php`: nghiệp vụ và bảo vệ CSRF/role, upload CV, consent, audit.
- `ai_service/main.py`: parser, OCR tùy chọn, skill evidence, matching, explanation.
- `ai_service/evaluate.py`: CLI đánh giá có báo cáo JSON và metric ranking.
- `upgrade_v3.sql`: migration thêm bảng, an toàn với dữ liệu v2.
- `views/admin_audit.php`: lịch sử thao tác quan trọng của ứng dụng.
- `docs/THESIS_SCOPE.md`: đề cương, yêu cầu chức năng, thiết kế, nghiệm thu.
- `docs/EVALUATION_PROTOCOL.md`: nhãn, tập dữ liệu, thiết kế thực nghiệm, báo cáo kết quả.
- `docs/DATA_POLICY.md`: đồng ý xử lý dữ liệu, giới hạn, rủi ro và quyền riêng tư.
- `docs/TEST_PLAN.md`: checklist kiểm thử thủ công và tự động.

**Giới hạn chưa triển khai đầy đủ:** reset mật khẩu qua email, xác thực email, lưu snapshot CV theo từng đơn, quét virus upload, cơ chế dữ liệu cá nhân chuẩn production, kiểm soát quyền/rate-limiting cấp hạ tầng, đánh giá công bằng trên dữ liệu thực và xác thực trên XAMPP Windows người dùng. Bổ sung các mục này theo mức độ ưu tiên trước khi đưa website ra Internet.

## VII. Tham khảo (chỉ ý tưởng, không sao chép trực tiếp)

- https://github.com/codeeurRitesh/AI-Resume-Screening-Job-Matching-System
- https://github.com/omrautxo/automated-resume-analyzer
- https://github.com/thrinathpolanki/resume_screening_ai

### Cách chạy nhanh AI cho Windows

Lần đầu nhấp đúp `install_ai.bat` (tạo `.venv`, cài thư viện), các lần sau nhấp đúp `start_ai.bat`. Nếu cần SBERT hoặc OCR, dùng PowerShell theo phần III–IV để cấu hình biến môi trường và cài gói tùy chọn. Hai file BAT không chứa mật khẩu và không sửa MySQL.
