from fastapi import FastAPI
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer

app = FastAPI()

# load 1 lần duy nhất
model = SentenceTransformer('BAAI/bge-m3')

class RequestData(BaseModel):
    text: str

@app.post("/embedding")
def embed(data: RequestData):
    vector = model.encode(data.text).tolist()
    return { "vector" : vector }

