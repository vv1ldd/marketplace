import os
import json

log_path = "/Users/w1ld/.gemini/antigravity/brain/07620f79-70a0-4511-857a-361c0e1098b0/.system_generated/logs/overview.txt"
target_file = "resources/views/partner/dashboard.blade.php"

if not os.path.exists(log_path):
    print(f"Log path does not exist: {log_path}")
    exit(1)

best_content = None
best_length = 0

with open(log_path, 'r', encoding='utf-8', errors='ignore') as f:
    for idx, line in enumerate(f):
        if not line.strip():
            continue
        # Search for occurrences of dashboard.blade.php content indicators
        if "B2B Sovereign Console SPA" in line or "B2B Sovereign Console SPA Styles" in line:
            # Let's see if we can find JSON payloads or raw text
            if len(line) > best_length:
                best_length = len(line)
                best_content = line
                print(f"Found candidate line {idx} of length {len(line)}")

if best_content:
    print(f"Extracting code from the best candidate of length {best_length}...")
    
    # Try parsing as JSON to find code contents
    try:
        data = json.loads(best_content)
        # Look for write_to_file or replace_file_content tool calls or responses
        # Or look for any key that contains the code
        found = False
        if "tool_calls" in data:
            for tc in data["tool_calls"]:
                if tc.get("name") in ["write_to_file", "replace_file_content"]:
                    args = tc.get("args", {})
                    if isinstance(args, str):
                        args = json.loads(args)
                    code = args.get("CodeContent") or args.get("ReplacementContent")
                    if code and "B2B Sovereign Console" in code:
                        with open(target_file, "w", encoding="utf-8") as out:
                            out.write(code)
                        print("SUCCESS: Recovered from tool call args!")
                        found = True
                        break
        if not found:
            # Let's do a regex/substring match to extract everything between <!DOCTYPE html> and </html>
            start_idx = best_content.find("<!DOCTYPE html>")
            end_idx = best_content.find("</html>")
            if start_idx != -1 and end_idx != -1:
                code = best_content[start_idx:end_idx + 7]
                # Replace escaped newlines or unicode escapes if present
                code = code.replace("\\n", "\n").replace('\\"', '"').replace("\\/", "/")
                with open(target_file, "w", encoding="utf-8") as out:
                    out.write(code)
                print("SUCCESS: Recovered from raw HTML boundaries!")
            else:
                print("Could not find HTML boundaries in the best candidate.")
    except Exception as e:
        print(f"Error parsing best candidate: {e}")
        # Try raw recovery
        start_idx = best_content.find("<!DOCTYPE html>")
        end_idx = best_content.find("</html>")
        if start_idx != -1 and end_idx != -1:
            code = best_content[start_idx:end_idx + 7]
            code = code.replace("\\n", "\n").replace('\\"', '"').replace("\\/", "/")
            with open(target_file, "w", encoding="utf-8") as out:
                out.write(code)
            print("SUCCESS: Recovered raw text fallback!")
else:
    print("No B2B dashboard indicators found in overview.txt.")
