import requests, json
from fetch_attendance import data
from config import VPS_API, API_KEY

r = requests.post(
    VPS_API,
    json={
        "token": API_KEY,
        "attendance": data
    },
    timeout=10
)

print(r.text)
