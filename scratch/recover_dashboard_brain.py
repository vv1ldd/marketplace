import os

brain_dir = "/Users/w1ld/.gemini/antigravity/brain"
matched_files = []

for root, dirs, files in os.walk(brain_dir):
    for f in files:
        fpath = os.path.join(root, f)
        try:
            with open(fpath, 'r', encoding='utf-8', errors='ignore') as file:
                content = file.read()
            if "tab-finance" in content or "История операций" in content or "Как работает Hold" in content:
                print(f"Found match: {fpath} (size: {len(content)})")
                matched_files.append((fpath, len(content)))
        except Exception as e:
            pass

print(f"Total matched files: {len(matched_files)}")
# Let's save a copy of the largest matched file if any
if matched_files:
    largest_file = max(matched_files, key=lambda x: x[1])[0]
    print(f"Largest matched file: {largest_file}")
