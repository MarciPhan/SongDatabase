#!/usr/bin/env python3
"""
Deduplikace + propojení souborů s písněmi v songs.json
Spustit: python3 link_files.py
"""
import json, os, re, shutil

BASE = os.path.dirname(os.path.abspath(__file__))
DB = os.path.join(BASE, "data", "songs.json")
PDF_DIR = os.path.join(BASE, "pdf")
OPENLP_DIR = os.path.join(BASE, "openlp")

with open(DB, "r", encoding="utf-8") as f:
    data = json.load(f)

# Záloha
shutil.copy2(DB, DB + ".bak")
print("Záloha vytvořena.\n")

def norm(s):
    """Normalizace pro porovnání (case-insensitive, bez interpunkce, bez přídavků)"""
    s = s.lower().strip()
    # Odstraň příponu
    s = re.sub(r'\.(pdf|xml|sng|txt)$', '', s, flags=re.I)
    # Odstraň přídavky
    s = re.sub(r'[\s_-]+(pěvecký sbor|kompakt|méně akordů|rozepsano|nevánoční verze)$', '', s)
    s = re.sub(r'\s+\d+$', '', s)  # trailing číslo
    # Odstraň interpunkci
    s = re.sub(r'[_\-,\.!?:;–—\'\"()]', ' ', s)
    s = re.sub(r'\s+', ' ', s).strip()
    return s

# === 1. DEDUPLIKACE ===
print("=== DEDUPLIKACE ===")
unique = {}
merged = 0

for song in data:
    key = norm(song["name"])
    if key in unique:
        e = unique[key]
        # Sloučit historii
        h = list(set((e.get("history") or []) + (song.get("history") or [])))
        h.sort(reverse=True)
        e["history"] = h
        e["count"] = len(h)
        if h:
            e["last"] = h[0]
        # Doplnit prázdná pole
        for f in ["author", "category", "tempo", "tags", "pdf", "openlp"]:
            if not e.get(f) and song.get(f):
                e[f] = song[f]
        print(f"  Sloučeno: '{song['name']}' → '{e['name']}'")
        merged += 1
    else:
        unique[key] = song.copy()

data = list(unique.values())
print(f"Sloučeno: {merged} duplicit")
print(f"Písní po deduplikaci: {len(data)}\n")

# === 2. PROPOJENÍ SOUBORŮ ===
print("=== PROPOJENÍ SOUBORŮ ===")

# Lookup: norm(název) → index
lookup = {norm(s["name"]): i for i, s in enumerate(data)}

def link_files(directory, field):
    if not os.path.isdir(directory):
        print(f"  Složka {directory} neexistuje!")
        return
    linked = 0
    unmatched = []
    for f in sorted(os.listdir(directory)):
        if f.startswith('.'):
            continue
        key = norm(f)
        if key in lookup:
            idx = lookup[key]
            if not data[idx].get(field):
                data[idx][field] = f
                linked += 1
                print(f"  [{field}] '{data[idx]['name']}' ← {f}")
        else:
            unmatched.append(f)
    
    print(f"  Propojeno: {linked}")
    if unmatched:
        print("  Nepropojené:")
        for u in unmatched:
            print(f"    - {u}  (norm: '{norm(u)}')")

print("\n--- PDF ---")
link_files(PDF_DIR, "pdf")

print("\n--- OpenLP ---")
link_files(OPENLP_DIR, "openlp")

# Uložit
with open(DB, "w", encoding="utf-8") as f:
    json.dump(data, f, ensure_ascii=False, indent=4)
print(f"\n✅ Hotovo! songs.json aktualizován ({len(data)} písní).")
