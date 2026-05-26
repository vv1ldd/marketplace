import os
import json

log_path = "/Users/w1ld/.gemini/antigravity/brain/50d5bfa3-001c-4f5c-8d43-f7ac7a8724fc/.system_generated/logs/overview.txt"
target_file = "resources/views/partner/dashboard.blade.php"

with open(log_path, 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

lines = content.split('\n')
print(f"Log has {len(lines)} lines")

for idx, line in enumerate(lines):
    if target_file in line:
        try:
            data = json.loads(line)
            source = data.get("source", "UNKNOWN")
            step = data.get("step_index", -1)
            t_type = data.get("type", "UNKNOWN")
            print(f"Line {idx} (Step {step}, Source {source}, Type {t_type}): {line[:120]}")
            # If it's a model tool call, print the tool names called
            if "tool_calls" in data:
                for tc in data["tool_calls"]:
                    args = tc.get("args", {})
                    if isinstance(args, str):
                        try:
                            args = json.loads(args)
                        except:
                            pass
                    print(f"    Tool: {tc['name']}, Args: {list(args.keys())}")
        except Exception as e:
            print(f"Line {idx} (RAW): {line[:120]}")
