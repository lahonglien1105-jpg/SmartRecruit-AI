import io
import os
import zipfile
import fitz
import pytest
from fastapi.testclient import TestClient
from main import app,extract_sections,extract_skills,find_skill_evidence,calculate_rows,JobInput,parse_document
from evaluate import evaluate

client=TestClient(app)


def test_pdf_with_prefix_is_accepted():
    doc=fitz.open()
    page=doc.new_page()
    page.insert_text((72,72),'Ho so PHP MySQL JavaScript. Contact test@example.com')
    pdf=doc.tobytes()
    response=client.post('/parse',files={'file':('resume.pdf',pdf,'application/pdf')})
    assert response.status_code==200,response.text
    assert response.json()['method']=='pdf-text'
    assert 'MySQL' in response.json()['skills']


def test_corrupt_pdf_fails_without_500():
    response=client.post('/parse',files={'file':('resume.pdf',b'not a real pdf' * 3,'application/pdf')})
    assert response.status_code==415


def test_scanned_pdf_explains_ocr(monkeypatch):
    monkeypatch.setenv('ENABLE_OCR','0')
    doc=fitz.open();doc.new_page();pdf=doc.tobytes()
    response=client.post('/parse',files={'file':('scan.pdf',pdf,'application/pdf')})
    assert response.status_code==422
    assert 'OCR' in response.json()['detail']


def test_docx_bad_structure():
    response=client.post('/parse',files={'file':('resume.docx',b'PKnot_a_zipfile'*5,'application/zip')})
    assert response.status_code in (415,422)


def test_sections_vietnamese():
    content='HỌC VẤN\nĐại học Tài nguyên và Môi trường\nKINH NGHIỆM\nDự án cá nhân: quản lý quán cà phê\nDỰ ÁN\nWeb PHP MySQL'
    result=extract_sections(content)
    assert 'Đại học' in result['education']
    assert 'quản lý' in result['experience']
    assert 'PHP' in result['projects']


def test_javascript_does_not_infer_java():
    assert 'Java' not in extract_skills('JavaScript')
    assert 'JavaScript' in extract_skills('JavaScript')
    assert 'PHP' in find_skill_evidence('Có PHP, MySQL trong dự án')


def test_matching_disclosures():
    rows=calculate_rows('PHP MySQL',[JobInput(id=1,text='Tuyển PHP Docker',required_skills='PHP, Docker')])
    assert rows[0]['method']=='weighted_65_35'
    assert rows[0]['skill_score']==50.0
    assert 'Docker' in rows[0]['missing_skills']


def test_evaluation_smoke():
    records=[{'resume_text':'PHP MySQL','job_text':'PHP MySQL','required_skills':'PHP, MySQL','label':'1','query_id':'cv1'},
             {'resume_text':'PHP MySQL','job_text':'Figma UX design','required_skills':'Figma','label':'0','query_id':'cv1'}]
    result=evaluate(records,threshold=0.5,at_k=2)
    assert result['n_pairs']==2
    assert 'mrr' in result
    assert result['precision']==1.0


def test_combined_experience_and_project_heading():
    from main import extract_sections
    sections=extract_sections("KINH NGHIỆM VÀ DỰ ÁN\n08/2025 - 02/2026 | Dự án website quản lý quán cà phê\nSử dụng PHP, MySQL\nHỌC VẤN\nĐại học")
    assert 'Dự án website quản lý' in sections['projects']
    assert not sections['experience'], 'Không tự suy diễn đồ án là kinh nghiệm làm việc'
    assert 'Đại học' in sections['education']
