# Como Converter Nuvens de Pontos para Potree

## O que é necessário?

Para visualizar nuvens de pontos com **alta performance** no Potree, os arquivos `.las`, `.laz` ou `.ply` precisam ser convertidos para o formato Potree.

## Passo 1: Baixar o PotreeConverter

1. Acesse: https://github.com/potree/PotreeConverter/releases
2. Baixe a versão mais recente para Windows
3. Extraia o arquivo ZIP

## Passo 2: Converter os Arquivos

### Converter arquivo único:
```bash
PotreeConverter.exe arquivo.las -o pasta_saida
```

### Converter múltiplos arquivos:
```bash
PotreeConverter.exe arquivo1.ply arquivo2.ply arquivo3.ply -o pasta_saida
```

### Converter todos os arquivos de uma pasta:
```bash
PotreeConverter.exe pasta_com_arquivos/*.las -o pasta_saida
```

## Passo 3: Estrutura de Saída

Após a conversão, você terá uma estrutura assim:
```
pasta_saida/
├── cloud.js
├── hierarchy.bin
├── metadata.json
└── r/
    ├── r0.bin
    ├── r1.bin
    └── ...
```

## Passo 4: Organizar no Projeto

Coloque a pasta convertida em:
```
projetos/{cidade}/{nome_projeto}/potree_converted/{nome_nuvem}/
```

Exemplo:
```
projetos/Belo_Horizonte/Projeto_ABC/potree_converted/
├── Base/
│   ├── cloud.js
│   ├── hierarchy.bin
│   └── r/
└── Layers/
    ├── cloud.js
    ├── hierarchy.bin
    └── r/
```

## Opções de Conversão Úteis

```bash
# Conversão com mais qualidade
PotreeConverter.exe arquivo.las -o saida --spacing 0.01

# Conversão mais rápida (menos qualidade)
PotreeConverter.exe arquivo.las -o saida --spacing 0.1

# Limitar número de pontos
PotreeConverter.exe arquivo.las -o saida --max-points 10000000
```

## Alternativa: Usar PLY Diretamente

Se você não quiser converter, o sistema já suporta arquivos `.ply` diretamente usando Three.js, mas a performance será menor para arquivos grandes (>100MB).

## Estrutura Atual do Sistema

O sistema verifica automaticamente:

1. **Primeiro**: Procura por conversões Potree em `potree_converted/`
2. **Se não encontrar**: Usa arquivos PLY originais em `2_densification/point_cloud/`

## Performance

| Método | Tamanho Máximo Recomendado | Performance |
|--------|---------------------------|-------------|
| Potree | Ilimitado | ⭐⭐⭐⭐⭐ Excelente |
| PLY direto | ~100MB | ⭐⭐⭐ Boa |

## Dica

Para melhor experiência, sempre converta arquivos grandes (>50MB) para Potree!

