<?php
$u=sr_auth('admin');
sr_page_heading('VẬN HÀNH HỆ THỐNG','Nhật ký gửi email','Chỉ hiển thị thông tin gửi, không hiển thị nội dung riêng tư hoặc liên kết xác minh.');
$items=sr_all('SELECT id,recipient_email,subject,status,attempts,last_error,sent_at,created_at FROM email_outbox ORDER BY id DESC LIMIT 100');
?><div class="panel table-panel"><div class="table-responsive"><table><thead><tr><th>ID</th><th>Người nhận</th><th>Chủ đề</th><th>Trạng thái</th><th>Số lần</th><th>Lỗi (nếu có)</th><th>Ngày tạo</th></tr></thead><tbody>
<?php foreach($items as $m):?><tr><td><?=h($m['id'])?></td><td><?=h($m['recipient_email'])?></td><td><?=h($m['subject'])?></td><td><span class="badge <?=($m['status']==='sent'?'success':($m['status']==='failed'?'danger':'warning'))?>"><?=h($m['status'])?></span></td><td><?=h($m['attempts'])?></td><td><?=h($m['last_error']?:'—')?></td><td><?=h($m['created_at'])?></td></tr><?php endforeach;?>
<?php if(!$items):?><tr><td colspan="7">Chưa có email nào được xếp hàng.</td></tr><?php endif;?></tbody></table></div></div><div class="privacy-note">Trạng thái sent nghĩa Resend đã chấp nhận yêu cầu; không khẳng định email đến hộp thư. Giới hạn 100 email gần nhất.</div>