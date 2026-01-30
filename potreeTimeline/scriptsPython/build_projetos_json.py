# -*- coding: utf-8 -*-
"""
Gera projetos.json a partir da pasta de projetos.
Só inclui pastas que tenham 2_densification/point_cloud/potree.
Ordena por data extraída do nome da pasta (timeline).
Conferência e ajuste do JSON são manuais após executar o script.
"""

import os
import re
import json
from datetime import datetime
from pathlib import Path
from typing import Optional, Tuple

# Pasta onde estão as subpastas de projetos (cada subpasta = um projeto)
PASTA_PROJETOS = r"D:\Xampp_novo\htdocs\copasa\projetos\Juatuba"

# Ano base para regra: mês >= 7 -> ano base; mês < 7 -> ano base + 1
ANO_BASE = 2025

# Sufixo obrigatório para considerar que o projeto tem nuvem (potree)
POTREE_SUBPATH = os.path.join("2_densification", "point_cloud", "potree")


def _ano_pelo_mes(mes: int) -> int:
    """Retorna ano conforme regra: mês >= 7 -> ANO_BASE, senão ANO_BASE + 1."""
    return ANO_BASE if mes >= 7 else ANO_BASE + 1


def extrair_data(nome_pasta: str) -> Tuple[Optional[str], Optional[str]]:
    """
    Extrai (date_iso, label_data) do nome da pasta.
    date_iso: "YYYY-MM-DD" para ordenação; None se sem data.
    label_data: "DD/MM/YY" para exibição; None se sem data.
    Formatos suportados:
      - Cidade_DD-MM (ex: Juatuba_06-11, Juatuba_15-01)
      - Cidade_DD-MM-YY ou Cidade_DD-MM-YYYY
      - Cidade_DD_DD-MM (usa a última data, trecho DD-MM)
    """
    # DD-MM-YYYY ou DD-MM-YY (ano explícito) — pega a última ocorrência
    for m in re.finditer(r"(\d{1,2})-(\d{1,2})-(\d{2,4})\b", nome_pasta):
        d, mes, ano = int(m.group(1)), int(m.group(2)), m.group(3)
        if len(ano) == 2:
            ano = int(ano)
            ano = 2000 + ano if ano < 50 else 1900 + ano
        else:
            ano = int(ano)
        if 1 <= mes <= 12 and 1 <= d <= 31:
            try:
                dt = datetime(ano, mes, d)
                return dt.strftime("%Y-%m-%d"), dt.strftime("%d/%m/%y")
            except ValueError:
                pass

    # DD-MM (sem ano): usar regra do ano pelo mês — pega a última ocorrência
    for m in re.finditer(r"(\d{1,2})-(\d{1,2})\b", nome_pasta):
        d, mes = int(m.group(1)), int(m.group(2))
        if 1 <= mes <= 12 and 1 <= d <= 31:
            ano = _ano_pelo_mes(mes)
            try:
                dt = datetime(ano, mes, d)
                return dt.strftime("%Y-%m-%d"), dt.strftime("%d/%m/%y")
            except ValueError:
                pass

    return None, None


def main():
    script_dir = Path(__file__).resolve().parent
    json_path = script_dir / "projetos.json"

    if not os.path.isdir(PASTA_PROJETOS):
        print(f"AVISO: Pasta não encontrada: {PASTA_PROJETOS}")
        print("Ajuste PASTA_PROJETOS no script e execute novamente.")
        with open(json_path, "w", encoding="utf-8") as f:
            json.dump([], f, ensure_ascii=False, indent=2)
        return

    projetos = []
    for nome in sorted(os.listdir(PASTA_PROJETOS)):
        path_projeto = os.path.join(PASTA_PROJETOS, nome)
        if not os.path.isdir(path_projeto):
            continue
        potree_path = os.path.join(path_projeto, POTREE_SUBPATH)
        if not os.path.isdir(potree_path):
            continue

        date_iso, label_data = extrair_data(nome)
        if date_iso and label_data:
            label = f"{nome} ({label_data})"
            projetos.append({
                "id": nome,
                "label": label,
                "date": date_iso,
                "offset": [0, 0, 0],
            })
            print(f"Projeto {nome} tem data {label_data}")
        else:
            projetos.append({
                "id": nome,
                "label": nome,
                "date": None,
                "offset": [0, 0, 0],
            })
            print(f"Projeto {nome} (sem data)")

    # Ordenar: com data por date_iso (mais antigo primeiro); sem data no fim
    def sort_key(p):
        return (0, p["date"] or "") if p["date"] else (1, p["id"])

    projetos.sort(key=sort_key)

    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(projetos, f, ensure_ascii=False, indent=2)

    print(f"\nArquivo gerado: {json_path}")
    print(f"Total de projetos: {len(projetos)}")


if __name__ == "__main__":
    main()
