# Quy trình thực nghiệm khoa học: AI so khớp CV – công việc

## Đầu vào và quyền sử dụng

Dữ liệu thật chỉ được dùng khi đã có quyền sử dụng, thông báo và xin đồng ý. Loại bỏ họ tên, địa chỉ, email, điện thoại, mã số định danh trước khi đưa vào tập đánh giá. Không đưa CV thật lên GitHub, cloud công khai hoặc báo cáo phụ lục. Có thể xây tập CV/JD **giả lập** để chạy thử, nhưng phải ghi rõ giả lập.

## Cách gán nhãn

CSV có cột bắt buộc: `resume_text,job_text,required_skills,label`. `label=1` khi người gán nhãn đánh giá CV đáp ứng yêu cầu tuyển dụng ở mức có thể cân nhắc; `0` khi không phù hợp. Nếu có một CV so với nhiều JD, thêm `query_id` cố định cho CV đó. Có thể thêm `resume_id,job_id,source,annotator_id,split` ở kho dữ liệu riêng (không import vào script nếu không cần).

Khuyến nghị có ít nhất hai người gán nhãn độc lập và một người phân xử điểm bất đồng; mô tả tiêu chí bắt buộc và tiêu chí ưu tiên. Báo cáo mức thống nhất giữa người gán nhãn (Cohen's kappa khi phù hợp). Không cho người gán nhãn xem điểm dự đoán trước khi đặt nhãn.

## Tách dữ liệu, tránh rò rỉ

- Chia theo **CV/person hoặc query_id**, không chia ngẫu nhiên từng cặp khi cùng CV xuất hiện nhiều cặp.
- Dùng tập **development** để thử hệ số và chọn ngưỡng `--threshold`. Đóng băng cấu hình trước khi đo **test** một lần.
- So sánh cả hai thuật toán trên cùng dữ liệu, cùng bước tiền xử lý và cùng điều kiện đánh giá. Không so sánh TF-IDF trên train với SBERT trên test khác.
- Báo cáo rõ phiên bản Python, thư viện, tên mô hình embedding, cấu hình máy CPU/GPU, thời gian chạy.

## Chỉ số đo

- Binary matching: Precision, Recall, F1, ROC-AUC (nếu đủ cả hai nhãn).
- Ranking (có `query_id`): Precision@K, MRR, nDCG@K. Có thể đo latency trên máy chạy thử, số lần lỗi parser, tỷ lệ nhận diện kỹ năng chính xác.
- Trích xuất CV: tự gán nhãn kỹ năng/email/điện thoại theo CV chuẩn; báo cáo precision/recall của từng loại, lỗi OCR.
- Có thể báo cáo trường hợp thất bại, CV tiếng Việt đa cột, từ viết tắt, thiếu kỹ năng và trích xuất sai.

## Lệnh mẫu

```powershell
cd C:\xampp\htdocs\SmartRecruit\ai_service
.\.venv\Scripts\Activate.ps1
python evaluate.py evaluation/sample_pairs.csv --threshold 0.5 --output evaluation/tfidf_demo.json
# Sau khi cài requirements-sbert.txt và tải mô hình (cần Internet), sử dụng tập dữ liệu y hệt:
$env:USE_SBERT='1'
python evaluate.py evaluation/sample_pairs.csv --threshold 0.5 --output evaluation/sbert_demo.json
```

**Quan trọng:** `sample_pairs.csv` chỉ có 8 cặp giả lập. Precision/Recall/F1 = 0 với một ngưỡng không phù hợp vẫn là kết quả hợp lệ cho thấy vấn đề về hiệu chỉnh điểm, không nên tự ý đổi ngưỡng dựa trên tập test để có kết quả đẹp.

## Biểu mẫu kết quả khi có dữ liệu thực nghiệm

| Phương pháp | Precision | Recall | F1 | AUC | MRR | nDCG@5 | p95 latency |
|-----------|----------:|-------:|---:|----:|----:|-------:|------------:|
| TF-IDF + overlap | Chưa đo | Chưa đo | Chưa đo | Chưa đo | Chưa đo | Chưa đo | Chưa đo |
| SBERT đa ngôn ngữ + overlap | Chưa đo | Chưa đo | Chưa đo | Chưa đo | Chưa đo | Chưa đo | Chưa đo |

Không dùng bảng kết quả giả để thay số liệu thực nghiệm. Cần lưu lại file kết quả JSON, bản dataset được phép sử dụng và cam kết ẩn danh.
