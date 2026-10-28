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

### Vérifier que les tests bloquent bien le déploiement

- **Lancement manuel** (droit d'écriture sur le dépôt requis) : *Actions → CI/CD → Run workflow*, branche `preprod` :
  - case **« Simuler un échec » cochée** → un test volontairement faux (`tests/DemoEchecTest.php`) est activé : `tests` ❌, `deploy` **skipped** ;
  - case **décochée** → seuls les vrais tests tournent : `tests` ✅, `deploy` ✅.
- **PR de démonstration** laissée ouverte (jamais fusionnée) : `demo/test-en-echec` → `preprod`, tests en échec, fusion bloquée.
- En local : `php tests/run.php` (normal) / `DEMO_ECHEC=1 php tests/run.php` (échec simulé).
