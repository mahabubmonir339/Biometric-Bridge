import json
import requests


API = "https://hrm.retailsolution.com.bd/hrm_api/attendance_push.php"
TOKEN = "MY_SECRET_TOKEN"

with open("attendance_processed.json", "r") as f:
    data = json.load(f)

headers = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json"
}

res = requests.post(API, json=data, headers=headers, timeout=20)

print("Status Code:", res.status_code)
print("Response:", res.text)
headers = {
    "Authorization": "Bearer MY_SECRET_TOKEN",
    "Content-Type": "application/json"
}