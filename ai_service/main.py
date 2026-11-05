"""SmartRecruit v3: reproducible TF-IDF / optional multilingual SBERT CV-job matching.

This is a decision-support research prototype. Scores are not hiring probabilities.
OCR is an opt-in local dependency (Tesseract), never a hidden cloud upload.
"""
from __future__ import annotations

import io
import os
import re
import unicodedata
import zipfile
from functools import lru_cache
from pathlib import Path

import numpy as np
from fastapi import FastAPI, File, HTTPException, UploadFile
from pydantic import BaseModel, Field
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

app = FastAPI(title='SmartRecruit NLP API', version='3.0.0', description='CV analysis and transparent matching; educational use')

SKILLS: dict[str, list[str]] = {
    'Python':['python'], 'PHP':['php'], 'JavaScript':['javascript','java script'], 'TypeScript':['typescript'],
    'Java':['java'], 'C++':['c++'], 'C#':['c#'], 'MySQL':['mysql'], 'PostgreSQL':['postgresql','postgres'],
    'MongoDB':['mongodb'], 'SQL Server':['sql server','mssql'], 'React':['react','reactjs','react.js'],
    'Vue':['vue','vuejs','vue.js'], 'Angular':['angular'], 'Next.js':['next.js','nextjs'],
    'Laravel':['laravel'], 'FastAPI':['fastapi'], 'Django':['django'], 'Flask':['flask'],
    'Node.js':['node.js','nodejs'], 'HTML':['html'], 'CSS':['css'], 'Bootstrap':['bootstrap'],
    'Tailwind':['tailwind'], 'Git':['git'], 'GitHub':['github'], 'Docker':['docker'],
    'Kubernetes':['kubernetes','k8s'], 'Linux':['linux'], 'AWS':['aws'], 'Azure':['azure'],
    'REST API':['rest api','restful api','api rest'], 'GraphQL':['graphql'],
    'Machine Learning':['machine learning','học máy'], 'Deep Learning':['deep learning','học sâu'],
    'NLP':['nlp','natural language processing','xử lý ngôn ngữ tự nhiên'],
    'Computer Vision':['computer vision','thị giác máy tính'],
    'TensorFlow':['tensorflow'], 'PyTorch':['pytorch'], 'Pandas':['pandas'], 'NumPy':['numpy'],
    'OpenCV':['opencv'], 'Scikit-learn':['scikit-learn','sklearn'], 'Excel':['excel'],
    'Power BI':['power bi'], 'Figma':['figma'], 'SQL':['sql'], 'Selenium':['selenium'],
    'Postman':['postman'], 'Communication':['communication','kỹ năng giao tiếp','giao tiếp'],
    'Teamwork':['teamwork','làm việc nhóm'], 'Problem Solving':['problem solving','giải quyết vấn đề'],
}

SECTION_LABELS = {
    'education':['education','học vấn','trình độ học vấn','đào tạo','bằng cấp'],
    'experience':['experience','work experience','kinh nghiệm','kinh nghiệm làm việc'],
    'projects':['projects','project','dự án','các dự án','kinh nghiệm và dự án'],
    'certifications':['certifications','certificates','chứng chỉ'],
}
MAX_UPLOAD = 5 * 1024 * 1024
MAX_TEXT = 100000


def normalize_text(value: str) -> str:
    return unicodedata.normalize('NFKC', value).lower()


def find_skill_evidence(text: str) -> dict[str, str]:
    """Evidence is a short excerpt from the user's own CV; not a confidence estimate."""
    normalized = normalize_text(text)
    found: dict[str,str] = {}
    for label, aliases in SKILLS.items():
        for alias in aliases:
            m = re.search(r'(?<![\w])' + re.escape(alias.lower()) + r'(?![\w])', normalized)
            if m:
                left = max(0, normalized.rfind('\n', 0, m.start()) + 1)
                right_pos = normalized.find('\n', m.end())
                right = len(text) if right_pos == -1 else right_pos
                excerpt = text[left:right].strip()
                found[label] = re.sub(r'\s+', ' ', excerpt)[:180] or alias
                break
    return found


def extract_skills(text: str) -> list[str]:
    return list(find_skill_evidence(text))


def extract_sections(text: str) -> dict[str, str]:
    """Heuristic headings only, not verified degrees/employment histories."""
    lines = [line.strip() for line in text.splitlines()]
    result: dict[str, list[str]] = {k: [] for k in SECTION_LABELS}
    section: str | None = None
    for line in lines:
        label = normalize_text(line.strip(': -•\t '))
        if len(label) < 65:
            matched = next((k for k, headings in SECTION_LABELS.items() if label in headings), None)
            if matched:
                section = matched
                continue
            if line.isupper() and len(line) > 3 and section and len(line) < 50:
                section = None
        if section and line and len('\n'.join(result[section])) < 1200:
            result[section].append(line)
    return {k:'\n'.join(v)[:1200] for k,v in result.items()}


def inspect_pdf_header(data: bytes) -> bool:
    """PDF header may legally be preceded by a small prefix in real-world files."""
    return b'%PDF-' in data[:1024]


def parse_document(data: bytes, filename: str) -> tuple[str, str, list[str]]:
    ext = Path(filename.lower()).suffix
    warnings: list[str] = []
    if ext == '.pdf':
        if not inspect_pdf_header(data):
            raise HTTPException(415, 'Tệp không có chữ ký PDF hợp lệ')
        import fitz
        with fitz.open(stream=data, filetype='pdf') as doc:
            if doc.needs_pass:
                raise HTTPException(422, 'PDF có mật khẩu, hãy xuất CV không khóa trước khi tải')
            if len(doc) > 40:
                raise HTTPException(422, 'CV vượt quá 40 trang')
            text = '\n'.join(page.get_text('text') for page in doc).strip()
            if len(text) < 40:
                if os.getenv('ENABLE_OCR','0') != '1':
                    raise HTTPException(422, 'PDF có rất ít văn bản. Nếu là PDF scan, bật OCR theo README hoặc dùng PDF có lớp văn bản.')
                try:
                    import pytesseract
                    from PIL import Image
                    if os.getenv('TESSERACT_CMD'):
                        pytesseract.pytesseract.tesseract_cmd = os.environ['TESSERACT_CMD']
                    texts=[]
                    for page in list(doc)[:10]:
                        pix=page.get_pixmap(matrix=fitz.Matrix(1.7,1.7), alpha=False)
                        image=Image.open(io.BytesIO(pix.tobytes('png')))
                        texts.append(pytesseract.image_to_string(image,lang=os.getenv('OCR_LANG','vie+eng')))
                    text='\n'.join(texts).strip()
                    warnings.append('Đã sử dụng OCR, cần người dùng xác minh kết quả; chỉ xử lý tối đa 10 trang đầu.')
                except (ImportError, OSError, RuntimeError, Exception) as e:
                    raise HTTPException(422, 'Không chạy được OCR. Kiểm tra Tesseract, gói vie/eng và requirements-ocr.txt.') from e
            return text, 'ocr' if warnings else 'pdf-text', warnings
    if ext == '.docx':
        if not data.startswith(b'PK'):
            raise HTTPException(415, 'Tệp DOCX không hợp lệ')
        try:
            with zipfile.ZipFile(io.BytesIO(data)) as archive:
                members=archive.infolist()
                if len(members)>1500 or sum(x.file_size for x in members)>30*1024*1024:
                    raise HTTPException(413, 'DOCX có quá nhiều dữ liệu nén')
                if 'word/document.xml' not in archive.namelist():
                    raise HTTPException(415, 'Không tìm thấy nội dung Word trong DOCX')
            from docx import Document
            doc=Document(io.BytesIO(data))
            result=[p.text for p in doc.paragraphs]
            for table in doc.tables:
                for row in table.rows:
                    result.append(' | '.join(cell.text for cell in row.cells))
            return '\n'.join(result).strip(), 'docx-text', warnings
        except zipfile.BadZipFile as exc:
            raise HTTPException(415, 'DOCX bị lỗi cấu trúc nén') from exc
    if ext == '.txt':
        return data.decode('utf-8-sig',errors='replace').strip(),'text',warnings
    raise HTTPException(415, 'Chỉ hỗ trợ PDF, DOCX hoặc TXT')


@app.get('/health')
def health():
    return {'status':'ok','version':'3.0.0','engine':'sbert' if os.getenv('USE_SBERT')=='1' else 'tfidf','ocr_enabled':os.getenv('ENABLE_OCR')=='1'}


@app.post('/parse')
async def parse(file: UploadFile=File(...)):
    filename=file.filename or ''
    if Path(filename.lower()).suffix not in {'.pdf','.docx','.txt'}:
        raise HTTPException(415,'Chỉ hỗ trợ PDF, DOCX, TXT')
    data=await file.read(MAX_UPLOAD+1)
    if len(data)>MAX_UPLOAD:
        raise HTTPException(413,'Kích thước tối đa 5MB')
    try:
        text, method, warnings = parse_document(data,filename)
    except HTTPException:
        raise
    except Exception as exc:
        raise HTTPException(422,'Tệp không đọc được. Kiểm tra cấu trúc tệp hoặc thử xuất lại CV.') from exc
    if len(text.strip())<20:
        raise HTTPException(422,'CV không đủ văn bản để phân tích, hãy kiểm tra OCR hoặc xuất lại PDF có lớp văn bản.')
    text=text[:MAX_TEXT]
    emails=re.findall(r'[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}',text)
    phones=re.findall(r'(?<!\d)(?:\+84|0)[\d .()-]{8,13}\d(?!\d)',text)
    sections=extract_sections(text)
    evidence=find_skill_evidence(text)
    return {'text':text,'skills':list(evidence),'skill_evidence':evidence,'email':emails[0] if emails else None,
            'phone':phones[0].strip() if phones else None,'sections':sections,'characters':len(text),
            'method':method,'warnings':warnings,'review_required':True}


class MatchInput(BaseModel):
    resume_text:str=Field(min_length=1,max_length=100000)
    job_text:str=Field(min_length=1,max_length=50000)
    required_skills:str=Field(default='',max_length=10000)


class JobInput(BaseModel):
    id:int
    text:str=Field(min_length=1,max_length=50000)
    required_skills:str=Field(default='',max_length=10000)


class RankInput(BaseModel):
    resume_text:str=Field(min_length=1,max_length=100000)
    jobs:list[JobInput]=Field(max_length=100)


@lru_cache(maxsize=1)
def get_model():
    from sentence_transformers import SentenceTransformer
    return SentenceTransformer('sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2',device='cpu')


def semantic_many(resume:str, descriptions:list[str]) -> np.ndarray:
    if not descriptions:
        return np.array([],dtype=float)
    texts=[resume[:6000]] + [d[:6000] for d in descriptions]
    if os.getenv('USE_SBERT','0')=='1':
        embeddings=get_model().encode(texts,normalize_embeddings=True,show_progress_bar=False)
        return np.clip(np.dot(embeddings[1:],embeddings[0]),0.0,1.0)
    try:
        matrix=TfidfVectorizer(ngram_range=(1,2),max_features=10000,sublinear_tf=True).fit_transform(texts)
        return np.clip(cosine_similarity(matrix[0],matrix[1:]).ravel(),0.0,1.0)
    except ValueError:
        return np.zeros(len(descriptions),dtype=float)


def calculate_rows(resume_text:str,jobs:list[JobInput]) -> list[dict]:
    similarities=semantic_many(resume_text,[j.text for j in jobs])
    resume_skills=set(extract_skills(resume_text))
    result=[]
    for job,sim in zip(jobs,similarities):
        required=set(extract_skills(job.required_skills or job.text))
        matched=sorted(required&resume_skills)
        missing=sorted(required-resume_skills)
        skill_ratio=len(matched)/len(required) if required else None
        combined=float(sim) if skill_ratio is None else .65*float(sim)+.35*skill_ratio
        result.append({'id':job.id,'score':round(100*combined,2),
                       'semantic_score':round(100*float(sim),2),
                       'skill_score':round(100*skill_ratio,2) if skill_ratio is not None else None,
                       'matched_skills':matched,'missing_skills':missing,
                       'required_skills':sorted(required),'method':'semantic_only' if skill_ratio is None else 'weighted_65_35',
                       'explanation':'65% tương đồng văn bản + 35% tỷ lệ kỹ năng nhận diện' if skill_ratio is not None else 'Chỉ dùng tương đồng văn bản: chưa nhận diện được kỹ năng yêu cầu',
                       'limitations':'Điểm chỉ phản ánh sự trùng khớp văn bản/từ điển; không xác minh năng lực hoặc khả năng trúng tuyển.'})
    return result


@app.post('/match')
def match(req:MatchInput):
    if not req.resume_text.strip() or not req.job_text.strip():
        raise HTTPException(422,'Nội dung CV/mô tả không được để trống')
    result=calculate_rows(req.resume_text,[JobInput(id=0,text=req.job_text,required_skills=req.required_skills)])[0]
    result['engine']='sbert' if os.getenv('USE_SBERT')=='1' else 'tfidf'
    return result


@app.post('/rank')
def rank(req:RankInput):
    if not req.resume_text.strip():
        raise HTTPException(422,'Nội dung CV không được để trống')
    result=calculate_rows(req.resume_text,req.jobs)
    result.sort(key=lambda r:(-r['score'],r['id']))
    return {'engine':'sbert' if os.getenv('USE_SBERT')=='1' else 'tfidf','results':result}
