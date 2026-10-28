# Tournoi_enoa
Gestionnaire de tournoi du volley pour l'association sportive ENOA

## Branches et déploiement

| Branche   | Rôle                         | Déployée sur                                       |
|-----------|------------------------------|----------------------------------------------------|
| `main`    | Production                   | https://tournoi.180-149-198-67.sslip.io            |
| `preprod` | Pré-production (recette)     | https://preprod.tournoi.180-149-198-67.sslip.io    |
| `feature/*` | Développement d'une évolution | — (non déployée)                                |

Règles (ruleset GitHub `protection-main-preprod`, sans exception) :

- aucun push direct sur `main` ni `preprod` : toute modification passe par une **Pull Request** ;
- une PR ne peut être fusionnée que si le job **`tests`** est vert (tests PHP sur une vraie base MySQL + test JS du minuteur) ;
- force push et suppression de `main` / `preprod` interdits.

Circuit : `feature/*` → PR vers `preprod` → déploiement automatique en pré-prod → recette → PR `preprod` → `main` → déploiement automatique en production.
Si un test échoue, rien n'est déployé (`.github/workflows/deploy.yml` : le job `deploy` dépend du job `tests`).
