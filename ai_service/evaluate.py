"""Reproducible offline CV-JD evaluation. No database or real CVs are queried.

Input CSV required: resume_text,job_text,required_skills,label (0/1)
Optional: query_id (one CV with multiple jobs); relevant jobs label=1.
Never tune threshold on the final test set. Synthetic samples are only smoke tests.
"""
from __future__ import annotations

import argparse
import csv
import json
import os
from collections import defaultdict
from pathlib import Path
from sklearn.metrics import precision_score, recall_score, f1_score, roc_auc_score, ndcg_score
from main import JobInput, calculate_rows


def evaluate(rows:list[dict],threshold:float=0.5,at_k:int=5)->dict:
    if not rows:
        raise ValueError('CSV phải có ít nhất một dòng')
    if threshold<0 or threshold>1:
        raise ValueError('threshold nằm trong [0, 1]')
    y=[int(r['label']) for r in rows]
    if any(label not in (0,1) for label in y):
        raise ValueError('label phải là 0 hoặc 1')
    scores=[]
    for i,row in enumerate(rows):
        matched=calculate_rows(str(row['resume_text']),[JobInput(id=i,text=str(row['job_text']),required_skills=str(row['required_skills']))])[0]
        scores.append(matched['score']/100.0)
    preds=[int(score>=threshold) for score in scores]
    result={'n_pairs':len(rows),'threshold':threshold,'precision':round(float(precision_score(y,preds,zero_division=0)),4),
            'recall':round(float(recall_score(y,preds,zero_division=0)),4),
            'f1':round(float(f1_score(y,preds,zero_division=0)),4),
            'positive_rate':round(sum(y)/len(y),4),'engine':'sbert' if os.getenv('USE_SBERT')=='1' else 'tfidf'}
    if len(set(y))>1:
        result['roc_auc']=round(float(roc_auc_score(y,scores)),4)
    if all(row.get('query_id') for row in rows):
        by_group=defaultdict(list)
        for row,score in zip(rows,scores):
            by_group[str(row['query_id'])].append((int(row['label']),score))
        precision_k=[];reciprocal=[];ndcg=[]
        for pairs in by_group.values():
            ordered=sorted(pairs,key=lambda x:-x[1]);top=ordered[:at_k]
            precision_k.append(sum(label for label,_ in top)/min(at_k,len(ordered)))
            reciprocal.append(next((1/rank for rank,(label,_) in enumerate(ordered,1) if label),0.0))
            if len(pairs)>1:
                y_true=[[label for label,_ in pairs]]
                y_score=[[score for _,score in pairs]]
                ndcg.append(float(ndcg_score(y_true,y_score,k=at_k)))
        result.update({'n_queries':len(by_group),f'precision_at_{at_k}':round(sum(precision_k)/len(precision_k),4),
                       'mrr':round(sum(reciprocal)/len(reciprocal),4)})
        if ndcg:
            result[f'ndcg_at_{at_k}']=round(sum(ndcg)/len(ndcg),4)
    return result


def read_csv(filename:Path)->list[dict]:
    with filename.open(encoding='utf-8-sig',newline='') as f:
        reader=csv.DictReader(f)
        needed={'resume_text','job_text','required_skills','label'}
        if not reader.fieldnames or not needed.issubset(reader.fieldnames):
            raise ValueError('Thiếu cột: '+', '.join(sorted(needed-set(reader.fieldnames or []))))
        return list(reader)


def main():
    parser=argparse.ArgumentParser(description='Đánh giá CV–JD offline (dữ liệu đã có quyền sử dụng)')
    parser.add_argument('dataset',type=Path)
    parser.add_argument('--threshold',type=float,default=0.5,help='Chọn bằng tập validation, không chỉnh trên test')
    parser.add_argument('--at-k',type=int,default=5)
    parser.add_argument('--output',type=Path,help='Đường dẫn lưu kết quả JSON')
    args=parser.parse_args()
    if args.at_k<1: parser.error('--at-k phải >=1')
    result=evaluate(read_csv(args.dataset),args.threshold,args.at_k)
    result.update({'dataset':str(args.dataset.name),'warning':'Chỉ số trên dữ liệu demo không chứng minh hiệu quả thực tế; không dùng để tự động loại ứng viên.'})
    print(json.dumps(result,ensure_ascii=False,indent=2))
    if args.output:
        args.output.parent.mkdir(parents=True,exist_ok=True)
        args.output.write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')


if __name__=='__main__':
    main()
