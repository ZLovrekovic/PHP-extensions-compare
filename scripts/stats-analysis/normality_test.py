import pandas as pd
import matplotlib.pyplot as plt
import scipy.stats as stats
import numpy as np
import os

def test_normalnosti(podaci, naziv, metoda):
    folder = "rezultat"
    if not os.path.exists(folder):
        os.makedirs(folder)

    # Proračunaj parametre normalne raspodele
    mean = podaci.mean()
    std = podaci.std()

    # Priprema grafa
    plt.figure(figsize=(12, 5))

    # Q-Q plot
    plt.subplot(1, 2, 1)
    stats.probplot(podaci, dist="norm", plot=plt)
    plt.title(f'Q-Q plot - {naziv} ({metoda})')

    # Histogram sa PDF linijom
    plt.subplot(1, 2, 2)
    count, bins, ignored = plt.hist(podaci, bins=20, density=True, edgecolor='black', alpha=0.6)
    x = np.linspace(min(podaci), max(podaci), 100)
    pdf = stats.norm.pdf(x, mean, std)
    plt.plot(x, pdf, 'r--', linewidth=2)
    plt.title(f'Histogram + Normal PDF - {naziv} ({metoda})')

    plt.tight_layout()
    plt.savefig(f'{folder}/{naziv}_{metoda}.png')
    plt.close()

    # Testovi normalnosti
    shapiro = stats.shapiro(podaci)
    dagostino = stats.normaltest(podaci)

    return shapiro.pvalue, dagostino.pvalue

def main():
    df = pd.read_csv("rezultat.csv", encoding="utf-8-sig")

    folder = "rezultat"
    if not os.path.exists(folder):
        os.makedirs(folder)

    with open(f"{folder}/pvrednosti.txt", "w", encoding="utf-8") as f:
        f.write("Metoda, Tip podatka, Shapiro-Wilk p-vrednost, D'Agostino p-vrednost\n")
        for metoda, grupa in df.groupby("method"):
            print(f"Obrađujem metodu: {metoda}")

            # Vreme izvršavanja
            p_shapiro_vreme, p_dagostino_vreme = test_normalnosti(
                grupa["time_ms"], "Vreme_izvrsavanja", metoda
            )
            f.write(f"{metoda}, time_ms, {p_shapiro_vreme:.6f}, {p_dagostino_vreme:.6f}\n")

            # Zauzeće memorije
            p_shapiro_mem, p_dagostino_mem = test_normalnosti(
                grupa["memory_peak_kb"], "Zauzece_memorije", metoda
            )
            f.write(f"{metoda}, memory_peak_kb, {p_shapiro_mem:.6f}, {p_dagostino_mem:.6f}\n")

    print("✅ Analiza završena. Rezultati i grafikoni su sačuvani u folderu 'rezultat'.")

if __name__ == "__main__":
    main()
