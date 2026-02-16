from zk import ZK
from config import DEVICE_IP, DEVICE_PORT, TIMEOUT
import json
import os
from collections import defaultdict
from datetime import datetime, time

# 2PM boundary
TWO_PM = time(14, 0, 0)

# ==========================
# Step 1: Connect to ZK device and fetch attendance
# ==========================
zk = ZK(DEVICE_IP, port=DEVICE_PORT, timeout=TIMEOUT)
conn = zk.connect()

logs = conn.get_attendance()  # list of attendance logs
conn.disconnect()

# Convert logs to JSON-like dict
data = []
for log in logs:
    data.append({
        "uid": log.uid,
        "user_id": log.user_id,
        "time": log.timestamp.strftime("%Y-%m-%d %H:%M:%S"),
        "status": log.status
    })

# Optional: save raw device JSON (if needed)
raw_file = os.path.join(os.getcwd(), "attendance.json")
with open(raw_file, "w") as f:
    json.dump(data, f, indent=4)
print(f"Raw attendance saved to {raw_file}")

# ==========================
# Step 2: Process attendance (2PM logic)
# ==========================
attendance = defaultdict(list)

# Group by user + date
for log in data:
    user = log["user_id"]
    dt = datetime.strptime(log["time"], "%Y-%m-%d %H:%M:%S")
    date = dt.strftime("%Y-%m-%d")

    attendance[(user, date)].append(dt)

final_data = []

for (user, date), times in attendance.items():
    times.sort()

    check_in = None
    check_out = None

    # 2PM এর আগে প্রথম time
    before_2pm = [t for t in times if t.time() < TWO_PM]
    if before_2pm:
        check_in = before_2pm[0]

    # 2PM এর পরে শেষ time
    after_2pm = [t for t in times if t.time() > TWO_PM]
    if after_2pm:
        check_out = after_2pm[-1]

    final_data.append({
        "user_id": user,
        "date": date,
        "check_in": check_in.strftime("%H:%M:%S") if check_in else None,
        "check_out": check_out.strftime("%H:%M:%S") if check_out else None
    })

# ==========================
# Step 3: Save processed attendance JSON
# ==========================
processed_file = os.path.join(os.getcwd(), "attendance_processed.json")
with open(processed_file, "w") as f:
    json.dump(final_data, f, indent=4)

print(f"Processed attendance saved to {processed_file}")
