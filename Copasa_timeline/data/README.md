# Pasta data/

Esta pasta contém o arquivo `projects.json` gerado pelo script `generate_projects_json.py`.

## Arquivos

- `projects.json`: JSON com a lista de todos os projetos Pix4D encontrados

## Como gerar/atualizar

Execute o script Python na raiz do projeto:

```bash
python generate_projects_json.py
```

O arquivo será gerado automaticamente nesta pasta.

## Nota

Este arquivo está no `.gitignore` e não será versionado, pois pode ser regenerado a qualquer momento.
