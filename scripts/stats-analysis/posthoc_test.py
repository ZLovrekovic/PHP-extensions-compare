import pandas as pd
import scikit_posthocs as sp
import os

# Učitaj CSV fajl sa podacima
df = pd.read_csv("rezultat.csv")

# Napravi folder za rezultate ako ne postoji
os.makedirs("rezultat", exist_ok=True)

# Imena metrika za koje želiš da radiš test
metrike = {
    "time_ms": "Vreme izvršavanja",
    "memory_peak_kb": "Zauzeće memorije"
}

# Radi Dunn test po metriki i sačuvaj rezultate
for kolona, naziv in metrike.items():
    p_values = sp.posthoc_dunn(df, val_col=kolona, group_col='method', p_adjust='bonferroni')
    output_path = f"rezultat/posthoc_{kolona}.csv"
    p_values.to_csv(output_path)
    print(f"Dunnov test za '{naziv}' je sačuvan u: {output_path}")
