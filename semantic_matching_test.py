from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity

model = SentenceTransformer("all-MiniLM-L6-v2")

cv = """
Tôi là lập trình viên Backend có kinh nghiệm với
Python, FastAPI, REST API, MySQL và Git.
Có kiến thức về xây dựng API và phát triển ứng dụng web.
"""
jobs = {
    "Backend Developer": """
    Backend Developer

    Yêu cầu:
    Python, FastAPI, REST API, PostgreSQL, Git.
    Có kinh nghiệm phát triển Web API.
    """,

    "Frontend Developer": """
    Frontend Developer

    Yêu cầu:
    React, TypeScript, JavaScript, HTML, CSS.
    Có kinh nghiệm phát triển giao diện web.
    """,

    "Data Analyst": """
    Data Analyst

    Yêu cầu:
    SQL, Excel, Power BI, Python.
    Có khả năng phân tích và trực quan hóa dữ liệu.
    """
}

cv_embedding = model.encode([cv])

results = []

for job_name, job_description in jobs.items():

    job_embedding = model.encode([job_description])

    score = cosine_similarity(
        cv_embedding,
        job_embedding
    )[0][0]

    results.append((job_name, score))

results.sort(key=lambda x: x[1], reverse=True)

print("=== SEMANTIC MATCHING RESULT ===")

for job_name, score in results:
    print(f"{job_name}: {score:.4f}")