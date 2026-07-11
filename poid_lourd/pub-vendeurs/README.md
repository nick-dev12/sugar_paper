# Publicité vendeurs COLObanes — 3 clips × 10 s

Kit structuré pour **Sora** (génération vidéo) : chaque clip = **1 dossier** + **3 visuels** + **1 prompt complet**.

## Structure

```
pub-vendeurs/
├── selection/                    ← banque visuels (00 → 08)
├── clip-01-promesse-revenus/     ← images 00, 01, 02
│   ├── visuels/
│   └── PROMPT_SORA.md            ★ prompt + dialogue
├── clip-02-boutique-puissance/   ← images 03, 04, 05
│   ├── visuels/
│   └── PROMPT_SORA.md
└── clip-03-lancez-votre-boutique/ ← images 06, 07, 08
    ├── visuels/
    └── PROMPT_SORA.md
```

## Répartition des 9 visuels

| Clip | Dossier | Visuels | Thème |
|------|---------|---------|--------|
| **1** | `clip-01-promesse-revenus` | 00 → 01 → 02 | Bienvenue · cash · 1 clic |
| **2** | `clip-02-boutique-puissance` | 03 → 04 → 05 | Catalogue · ma boutique · un outil |
| **3** | `clip-03-lancez-votre-boutique` | 06 → 07 → 08 | Inscription 3 étapes → **Terminer** ★ |

**Montage final** : Clip 1 + Clip 2 + Clip 3 ≈ **30 secondes**

## Utilisation Sora

1. Ouvrir le dossier du clip voulu.
2. Joindre les 3 PNG du dossier `visuels/` (si Sora accepte les références).
3. Copier le bloc **« PROMPT COMPLET — COPIER-COLLER DANS SORA »** depuis `PROMPT_SORA.md`.
4. Générer les 3 clips séparément, puis assembler dans CapCut / Premiere.

## Fichiers utiles

| Fichier | Rôle |
|---------|------|
| `VIDEO_30s_SCRIPT.md` | Vue d’ensemble montage |
| `DIALOGUE_PRESENTATION.md` | Index dialogues (renvoi vers les clips) |
| `selection/` | Tous les visuels sources |
