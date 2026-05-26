log_path = "/Users/w1ld/.gemini/antigravity/brain/07620f79-70a0-4511-857a-361c0e1098b0/.system_generated/logs/overview.txt"

with open(log_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

line = lines[1029]
print(f"Total line length: {len(line)}")
# Print it in chunks of 2000 characters so it doesn't get truncated by terminal output
for i in range(0, len(line), 2000):
    print(f"--- Chunk {i//2000} ---")
    print(line[i:i+2000])
