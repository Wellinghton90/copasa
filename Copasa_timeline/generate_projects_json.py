#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script para gerar JSON de projetos Pix4D

Este script escaneia a pasta de projetos e gera um arquivo JSON
com todos os projetos encontrados, independente do padrão de nome.

Uso:
    python generate_projects_json.py

O arquivo JSON será gerado em: data/projects.json
"""

import os
import json
import re
from datetime import datetime
from pathlib import Path
from typing import List, Dict, Optional

# Configurações
BASE_PATH = r'D:\Xampp_novo\htdocs\copasa\projetos\Juatuba'
HTML_SUBPATH = r'3_dsm_ortho\2_mosaic\google_tiles'
OUTPUT_FILE = 'data/projects.json'

# Meses em português
MONTHS = {
    1: 'Janeiro', 2: 'Fevereiro', 3: 'Março', 4: 'Abril',
    5: 'Maio', 6: 'Junho', 7: 'Julho', 8: 'Agosto',
    9: 'Setembro', 10: 'Outubro', 11: 'Novembro', 12: 'Dezembro'
}


def parse_project_date(date_string: str) -> Optional[datetime]:
    """
    Converte string de data do formato DD-MM-YY para datetime
    
    Args:
        date_string: Data no formato DD-MM-YY
        
    Returns:
        Objeto datetime ou None se inválido
    """
    if not date_string:
        return None
    
    # Valida formato básico DD-MM-YY
    match = re.match(r'^(\d{2})-(\d{2})-(\d{2})$', date_string)
    if not match:
        return None
    
    day = int(match.group(1))
    month = int(match.group(2))
    year = int(match.group(3))
    
    # Valida valores básicos
    if day < 1 or day > 31 or month < 1 or month > 12:
        return None
    
    # Converte ano de 2 dígitos para 4 dígitos
    # Assume anos 00-50 como 2000-2050 e 51-99 como 1951-1999
    full_year = (2000 + year) if year <= 50 else (1900 + year)
    
    try:
        date = datetime(full_year, month, day)
        # Valida se a data é válida (ex: 31/02 não existe)
        if date.day != day or date.month != month or date.year != full_year:
            return None
        return date
    except ValueError:
        return None


def format_for_display(date: datetime) -> str:
    """
    Formata datetime para exibição amigável
    
    Args:
        date: Objeto datetime
        
    Returns:
        Data formatada (ex: "02 de Dezembro de 2025")
    """
    day = date.strftime('%d')
    month = MONTHS[date.month]
    year = date.strftime('%Y')
    return f'{day} de {month} de {year}'


def extract_date_from_project_name(project_name: str) -> Optional[str]:
    """
    Tenta extrair data do nome do projeto
    
    Suporta múltiplos formatos:
    - Juatuba_DD-MM_YY
    - Qualquer padrão com DD-MM-YY ou DD-MM-YYYY
    
    Args:
        project_name: Nome do projeto
        
    Returns:
        Data extraída no formato DD-MM-YY ou None
    """
    # Padrão 1: Juatuba_DD-MM_YY
    match = re.match(r'^[^_]+_(\d{2})-(\d{2})_(\d{2})$', project_name)
    if match:
        return f'{match.group(1)}-{match.group(2)}-{match.group(3)}'
    
    # Padrão 2: Qualquer DD-MM-YY no nome
    match = re.search(r'(\d{2})-(\d{2})-(\d{2})', project_name)
    if match:
        return f'{match.group(1)}-{match.group(2)}-{match.group(3)}'
    
    # Padrão 3: DD-MM-YYYY
    match = re.search(r'(\d{2})-(\d{2})-(\d{4})', project_name)
    if match:
        day = match.group(1)
        month = match.group(2)
        year = match.group(3)[-2:]  # Pega últimos 2 dígitos
        return f'{day}-{month}-{year}'
    
    return None


def find_html_file(project_path: Path) -> Optional[str]:
    """
    Encontra o arquivo HTML do mosaico na pasta google_tiles
    
    Args:
        project_path: Caminho do projeto
        
    Returns:
        Caminho relativo do HTML ou None se não encontrado
    """
    google_tiles_path = project_path / HTML_SUBPATH
    
    if not google_tiles_path.exists() or not google_tiles_path.is_dir():
        return None
    
    # Busca qualquer arquivo HTML na pasta google_tiles
    for file in google_tiles_path.iterdir():
        if file.is_file() and file.suffix.lower() == '.html':
            # Retorna caminho relativo a partir do BASE_PATH
            relative_path = file.relative_to(Path(BASE_PATH))
            return str(relative_path).replace('\\', '/')
    
    return None


def scan_projects() -> List[Dict]:
    """
    Escaneia a pasta de projetos e retorna lista de projetos encontrados
    
    Returns:
        Lista de projetos com seus dados
    """
    projects = []
    base_path = Path(BASE_PATH)
    
    if not base_path.exists() or not base_path.is_dir():
        print(f'ERRO: Pasta não encontrada: {BASE_PATH}')
        return projects
    
    print(f'Escanando projetos em: {BASE_PATH}')
    
    # Itera sobre todos os itens na pasta
    for item in base_path.iterdir():
        # Ignora arquivos, apenas diretórios
        if not item.is_dir():
            continue
        
        # Ignora pastas ocultas
        if item.name.startswith('.'):
            continue
        
        project_name = item.name
        print(f'  Verificando: {project_name}')
        
        # Tenta encontrar o arquivo HTML
        html_path_relative = find_html_file(item)
        if not html_path_relative:
            print(f'    ⚠️  HTML não encontrado, ignorando...')
            continue
        
        # Tenta extrair data do nome
        date_string = extract_date_from_project_name(project_name)
        if not date_string:
            print(f'    ⚠️  Data não encontrada no nome, usando nome como data...')
            # Se não conseguir extrair, usa o nome do projeto como data
            date_string = project_name
        
        # Parse da data
        date_obj = parse_project_date(date_string)
        
        if date_obj:
            date_display = format_for_display(date_obj)
            date_sortable = date_obj.strftime('%Y-%m-%d')
        else:
            # Se não conseguir parsear, usa valores padrão
            date_display = project_name
            date_sortable = '0000-00-00'
            print(f'    ⚠️  Data inválida, usando valores padrão')
        
        # Constrói URL para o endpoint
        html_url = f'api/view_project.php?path={html_path_relative}'
        
        project_data = {
            'name': project_name,
            'date': date_string,
            'date_display': date_display,
            'date_sortable': date_sortable,
            'html_path': html_url
        }
        
        projects.append(project_data)
        print(f'    ✅ Projeto adicionado: {project_name} ({date_display})')
    
    # Ordena por data (mais antigo primeiro)
    projects.sort(key=lambda x: x['date_sortable'])
    
    return projects


def main():
    """Função principal"""
    print('=' * 60)
    print('Gerador de JSON de Projetos Pix4D')
    print('=' * 60)
    print()
    
    # Escaneia projetos
    projects = scan_projects()
    
    if not projects:
        print()
        print('⚠️  Nenhum projeto encontrado!')
        print('   Verifique se o caminho BASE_PATH está correto.')
        return
    
    # Cria estrutura de resposta no formato esperado pela API
    response_data = {
        'success': True,
        'data': projects,
        'meta': {
            'total': len(projects)
        }
    }
    
    # Cria diretório data/ se não existir
    output_path = Path(OUTPUT_FILE)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    
    # Salva JSON
    with open(output_path, 'w', encoding='utf-8') as f:
        json.dump(response_data, f, ensure_ascii=False, indent=2)
    
    print()
    print('=' * 60)
    print(f'✅ JSON gerado com sucesso!')
    print(f'   Arquivo: {output_path.absolute()}')
    print(f'   Total de projetos: {len(projects)}')
    print('=' * 60)
    
    # Mostra resumo
    print()
    print('Resumo dos projetos:')
    for i, project in enumerate(projects, 1):
        print(f'  {i}. {project["name"]} - {project["date_display"]}')


if __name__ == '__main__':
    main()
