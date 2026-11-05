from fastapi.testclient import TestClient
from main import app, extract_skills

client = TestClient(app)


def test_health():
    result = client.get('/health')
    assert result.status_code == 200
    assert result.json()['status'] == 'ok'


def test_skills_boundary():
    assert 'Java' not in extract_skills('JavaScript React')
    assert 'JavaScript' in extract_skills('JavaScript React')
    assert 'PHP' in extract_skills('PHP + MySQL')


def test_parse_txt():
    response = client.post('/parse', files={'file': ('resume.txt', b'test@example.com\nPython MySQL', 'text/plain')})
    assert response.status_code == 200
    assert response.json()['email'] == 'test@example.com'
    assert 'Python' in response.json()['skills']


def test_matching_skills():
    response = client.post('/match', json={
        'resume_text': 'I have skills in PHP, MySQL and JavaScript',
        'job_text': 'Seeking PHP MySQL Docker developer',
        'required_skills': 'PHP, MySQL, Docker',
    })
    assert response.status_code == 200
    result = response.json()
    assert 'Docker' in result['missing_skills']
    assert 'PHP' in result['matched_skills']
    assert result['score'] >= 0


def test_rank_sorted():
    response = client.post('/rank', json={
        'resume_text': 'PHP MySQL JavaScript',
        'jobs': [
            {'id': 1, 'text': 'PHP MySQL JavaScript', 'required_skills': 'PHP, MySQL'},
            {'id': 2, 'text': 'Figma design UX', 'required_skills': 'Figma'},
        ],
    })
    assert response.status_code == 200
    results = response.json()['results']
    assert results[0]['id'] == 1
    assert results[0]['score'] >= results[1]['score']
