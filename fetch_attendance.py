# from zk import ZK
# from config import DEVICE_IP, DEVICE_PORT
# import json

# zk = ZK(DEVICE_IP, port=DEVICE_PORT, timeout=5)
# conn = zk.connect()

# logs = conn.get_attendance()

# data = []
# for log in logs:
#     data.append({
#         "uid": log.uid,
#         "user_id": log.user_id,
#         "time": log.timestamp.strftime("%Y-%m-%d %H:%M:%S"),
#         "status": log.status
#     })

# conn.disconnect()

# print(json.dumps(data))


# from zk import ZK
# from config import DEVICE_IP, DEVICE_PORT, TIMEOUT
# import json
# import os

# # Device connection
# zk = ZK(DEVICE_IP, port=DEVICE_PORT, timeout=TIMEOUT)
# conn = zk.connect()

# logs = conn.get_attendance()
# data = []

# for log in logs:
#     data.append({
#         "uid": log.uid,
#         "user_id": log.user_id,
#         "time": log.timestamp.strftime("%Y-%m-%d %H:%M:%S"),
#         "status": log.status
#     })

# conn.disconnect()

# # Save to file
# output_file = os.path.join(os.getcwd(), "attendance.json")
# with open(output_file, "w") as f:
#     json.dump(data, f, indent=4)

# print(f"Attendance saved to {output_file}")


from zk import ZK
from config import DEVICE_IP, DEVICE_PORT, TIMEOUT
import json
import os

# Connect to device
zk = ZK(DEVICE_IP, port=DEVICE_PORT, timeout=TIMEOUT)
conn = zk.connect()

logs = conn.get_attendance()
data = []

for log in logs:
    data.append({
        "uid": log.uid,
        "user_id": log.user_id,
        "time": log.timestamp.strftime("%Y-%m-%d %H:%M:%S"),
        "status": log.status
    })

conn.disconnect()

# Save JSON file
output_file = os.path.join(os.getcwd(), "attendance.json")
with open(output_file, "w") as f:
    json.dump(data, f, indent=4)

print(f"Attendance saved to {output_file}")


