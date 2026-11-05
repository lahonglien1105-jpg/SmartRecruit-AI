<?php
$u=sr_auth('candidate');sr_page_heading('DÀNH CHO ỨNG VIÊN','Công việc đã lưu','Những cơ hội bạn muốn xem lại khi sẵn sàng.');
$jobs=sr_all("SELECT j.*,d.job_type,d.work_mode FROM saved_jobs s JOIN jobs j ON j.id=s.job_id LEFT JOIN job_details d ON d.job_id=j.id WHERE s.user_id=? AND j.status='approved' ORDER BY s.created_at DESC",[$u['id']]);
if(!$jobs):?><div class="panel empty-state"><h2>Chưa có công việc được lưu</h2><p>Bạn có thể lưu những vị trí thú vị khi xem chi tiết tuyển dụng.</p><a class="button primary" href="<?=sr_url('home')?>">Khám phá việc làm</a></div><?php else:?><div class="job-grid"><?php foreach($jobs as $j)sr_job_card($j,true);?></div><?php endif;?>
