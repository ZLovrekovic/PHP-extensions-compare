import pandas as pd
from scipy.stats import kruskal

# Učitaj CSV
df = pd.read_csv("rezultat.csv", encoding="utf-8-sig")

# Grupiši po metodi
grupe_vreme = [grupa["time_ms"].values for _, grupa in df.groupby("method")]
grupe_memorija = [grupa["memory_peak_kb"].values for _, grupa in df.groupby("method")]

# Kruskal-Wallis test za vreme
stat_vreme, p_vreme = kruskal(*grupe_vreme)
rez_vreme = f"[Vreme izvršavanja] Kruskal-Wallis H = {stat_vreme:.4f}, p = {p_vreme:.6f}"
print(rez_vreme)

# Kruskal-Wallis test za memoriju
stat_mem, p_mem = kruskal(*grupe_memorija)
rez_mem = f"[Zauzeće memorije] Kruskal-Wallis H = {stat_mem:.4f}, p = {p_mem:.6f}"
print(rez_mem)

# Snimi rezultate u fajl
with open("kw_test.txt", "w", encoding="utf-8") as f:
    f.write(rez_vreme + "\n")
    f.write(rez_mem + "\n")