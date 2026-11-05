<?php
$u=sr_auth('admin');
$events=sr_all('SELECT a.*,u.name actor FROM audit_events a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 100');
sr_page_heading('KIỂM SOÁT VÀ TRUY VẾT','Nhật ký hoạt động','Ghi nhận thao tác quan trọng, không lưu nội dung CV hay mật khẩu vào nhật ký.');
?><div class="panel table-panel"><div class="table-responsive"><table><thead><tr><th>Thời điểm</th><th>Người thực hiện</th><th>Hành động</th><th>Đối tượng</th></tr></thead><tbody>
<?php foreach ($events as $e):?><tr><td><?=h($e['created_at'])?></td><td><?=h($e['actor']??'Tài khoản đã xóa')?></td><td><?=h($e['action'])?></td><td><?=h($e['entity_type'])?> #<?=h($e['entity_id'])?></td></tr><?php endforeach;?>
<?php if (!$events):?><tr><td colspan="4">Chưa có sự kiện nào từ phiên bản v3.</td></tr><?php endif;?></tbody></table></div></div>
<div class="privacy-note">Nhật ký chỉ lưu 100 sự kiện gần nhất ở giao diện; bảng dữ liệu không tự xóa. Cần chính sách lưu trữ/xóa khi triển khai thực tế.</div>
