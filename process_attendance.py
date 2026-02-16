import json
from collections import defaultdict
from datetime import datetime

with open("attendance.json", "r") as f:
    logs = json.load(f)

attendance = defaultdict(list)

# group by user + date
for log in logs:
    user = log["user_id"]
    dt = datetime.strptime(log["time"], "%Y-%m-%d %H:%M:%S")
    date = dt.strftime("%Y-%m-%d")

    attendance[(user, date)].append(dt)

final_data = []

for (user, date), times in attendance.items():
    times.sort()
    final_data.append({
        "user_id": user,
        "date": date,
        "check_in": times[0].strftime("%H:%M:%S"),
        "check_out": times[-1].strftime("%H:%M:%S")
    })

# save processed JSON
with open("attendance_processed.json", "w") as f:
    json.dump(final_data, f, indent=4)

print("Processed attendance saved to attendance_processed.json")
