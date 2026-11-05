<?php
$u=sr_auth('candidate');$cv=sr_one('SELECT * FROM resumes WHERE user_id=?',[$u['id']]);
sr_page_heading('AI JOB MATCHING','Việc làm AI gợi ý','Khám phá vị trí phù hợp với kỹ năng và nội dung CV của bạn.');
if(!$cv):?><div class="panel empty-state"><div class="empty-icon">✦</div><h2>Hãy tải CV lên để bắt đầu</h2><p>AI cần nội dung CV để phân tích kỹ năng và đưa ra gợi ý phù hợp.</p><a class="button primary" href="<?=sr_url('resume')?>">Tải CV ngay</a></div><?php else:
$jobs=sr_all("SELECT j.*,d.job_type,d.work_mode,d.level FROM jobs j LEFT JOIN job_details d ON d.job_id=j.id WHERE j.status='approved' AND (d.deadline IS NULL OR d.deadline>=CURDATE()) ORDER BY j.id DESC LIMIT 70");
$scoreRows=[];$source='fallback';
if($jobs){
 $payload=[];foreach($jobs as $j)$payload[]=['id'=>(int)$j['id'],'text'=>$j['title']."\n".$j['description']."\n".$j['skills'],'required_skills'=>$j['skills']];
 $rank=sr_ai('/rank',['resume_text'=>mb_substr(sr_resume_match_text($cv),0,14000),'jobs'=>$payload],25);
 if($rank && isset($rank['results']) && is_array($rank['results'])){
   $source=(string)($rank['engine']??'AI');foreach($rank['results'] as $item)$scoreRows[(int)$item['id']]=$item;
 }
 if(!$scoreRows){
    $cvSkills=array_map('mb_strtolower',sr_skills($cv['skills']));
    foreach($jobs as $j){$req=sr_skills($j['skills']);$m=[];$missing=[];
      foreach($req as $sk){if(in_array(mb_strtolower($sk),$cvSkills,true))$m[]=$sk;else $missing[]=$sk;}
      $score=count($req)?round(count($m)/count($req)*100,2):0;
      $scoreRows[(int)$j['id']]=['id'=>(int)$j['id'],'score'=>$score,'semantic_score'=>null,'skill_score'=>$score,'matched_skills'=>$m,'missing_skills'=>$missing];
    }
 }
 usort($jobs,fn($a,$b)=>($scoreRows[(int)$b['id']]['score']??0)<=>($scoreRows[(int)$a['id']]['score']??0));
 $jobs=array_slice($jobs,0,12);
}
?><div class="ai-banner"><span class="ai-star">✦</span><div><strong>Phân tích từ CV: <?=h($cv['original_name'])?></strong><p><?=count(sr_skills($cv['skills']))?> kỹ năng được phát hiện · <?=count($jobs)?> công việc được xếp hạng và hiển thị</p><small><?=($source==='fallback'?'AI Service không phản hồi. Điểm đang được tính tạm bằng kỹ năng trùng khớp.':('Thuật toán: '.h(strtoupper($source))))?> · Điểm chỉ mang tính tham khảo.</small></div><a class="button white sm" href="<?=sr_url('resume')?>">Đổi CV</a></div>
<?php if(!$jobs):?><div class="panel empty-state"><h3>Chưa có công việc để gợi ý</h3><p>Hãy quay lại khi có tin tuyển dụng được phê duyệt.</p></div><?php else:?><div class="recommendation-list"><?php foreach($jobs as $j):$score=$scoreRows[(int)$j['id']]??[];$value=(float)($score['score']??0);?><article class="panel recommendation"><div class="recommendation-top"><div class="company-logo"><?=h(sr_initials($j['company']))?></div><div class="recommendation-copy"><small><?=h($j['company'])?> · <?=h($j['location']?:'Toàn quốc')?></small><h3><a href="<?=sr_url('job',['id'=>$j['id']])?>"><?=h($j['title'])?></a></h3><div class="tag-row"><span class="chip"><?=h(sr_status($j['job_type']??'full_time'))?></span><span class="chip"><?=h(sr_status($j['work_mode']??'onsite'))?></span></div></div><div class="match-gauge"><strong><?=number_format($value,0)?>%</strong><small>Điểm so khớp</small></div></div><div class="match-track"><span style="width:<?=min(100,max(0,$value))?>%"></span></div><div class="match-details"><div><b>Kỹ năng đáp ứng</b><p><?=h(implode(', ',(array)($score['matched_skills']??[]))?:'Chưa phát hiện')?></p></div><div><b>Cần bổ sung</b><p><?=h(implode(', ',(array)($score['missing_skills']??[]))?:'Không phát hiện')?></p></div></div><div class="score-explain"><strong>Giải thích:</strong> <?=h($score['explanation']??'Điểm dựa trên kỹ năng trùng khớp; API không khả dụng')?><div>Điểm tương đồng: <?=isset($score['semantic_score'])&&$score['semantic_score']!==null?h($score['semantic_score']).'%':'N/A'?> · Điểm kỹ năng: <?=isset($score['skill_score'])&&$score['skill_score']!==null?h($score['skill_score']).'%':'N/A'?></div></div><div class="rec-footer"><span><?=h($j['salary']?:'Thỏa thuận')?></span><a class="button secondary sm" href="<?=sr_url('job',['id'=>$j['id']])?>">Xem và ứng tuyển →</a></div></article><?php endforeach;?></div><?php endif;endif;?>
