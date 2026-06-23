# Palabrae — API

Moteur d'intégrité procédurale. **La machine prévient, rappelle, propose — la décision reste à l'humain.**

API REST (Symfony 7.1 + API Platform 4 + Doctrine ORM/PostgreSQL) construite **autour des fichiers métier existants** : noyau d'intégrité, gradient de gravité, mur RGPD coaching/disciplinaire, et planificateur de contrôles surprise *commit-reveal*.

## Architecture

```
src/
├── Entity/              Doctrine
│   ├── Investigation        Noyau générique : une instance de procédure (site-jour)
│   ├── ControlPoint         Verticale HACCP : un CCP (code, gravité, leçon)
│   ├── Deviation            Écart procédural neutre (le « fait », operatorRef muré)
│   ├── CoachingRecord       Trace d'accompagnement, finalité COACHING + TTL
│   ├── MicroLesson          Couche enseignante (le pourquoi + le geste)
│   ├── IntegrityLogEntry    Maillon de la boîte noire (append-only, chaîné)
│   ├── SpotCheckPlan/Slot   Plan scellé de contrôles surprise
│   └── User                 Compte applicatif (auth JWT)
├── Enum/                Severity, DataPurpose, PlanStatus, SlotStatus
├── Service/
│   ├── DeviationHandler     Escalade proportionnée + coaching muré
│   ├── IntegrityJournal     Boîte noire chaînée + ancrage externe
│   └── SealedPlanner        Schéma commit-reveal (anti-triche prouvable)
├── Integrity/          Abstractions : RandomnessSource, SealStore, AnchorSink (+ impls MVP)
├── Security/          CoachingDataVoter — le mur RGPD, structurel
├── ApiResource/       ComplianceSummary — vue responsable agrégée & anonyme
└── State/             ComplianceSummaryProvider
```

Le **noyau** (`Investigation`, `IntegrityLogEntry`, `SealedPlanner`, abstractions `Integrity`) est générique et dépolitisé. La **verticale restauration** (`ControlPoint`, micro-leçons HACCP) s'y greffe. C'est l'architecture « un cœur, des verticales » du mémoire.

## Démarrage

```bash
# 1. Dépendances
composer install

# 2. Base de données (PostgreSQL via Docker)
cp .env.local.example .env.local   # puis renseigner DATABASE_URL + secrets
docker compose up -d database

# 3. Clés JWT (jamais versionnées)
php bin/console lexik:jwt:generate-keypair

# 4. Schéma + données de démo
php bin/console doctrine:database:create
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# 5. Lancer
symfony serve     # ou php -S 127.0.0.1:8000 -t public
```

Documentation interactive de l'API : `GET /api/docs`.

## Authentification

```bash
# Obtenir un token
curl -X POST http://127.0.0.1:8000/api/auth \
  -H 'Content-Type: application/json' \
  -d '{"email":"responsable@demo.test","password":"demo-responsable"}'

# Appeler une ressource protégée
curl http://127.0.0.1:8000/api/compliance/summary \
  -H 'Authorization: Bearer <TOKEN>'
```

Comptes de démo (fixtures, à supprimer hors démo) : `operateur@demo.test` / `demo-operateur`, `responsable@demo.test` / `demo-responsable`.

## Surface API

Par **choix de sécurité**, les entités sensibles (`Deviation`, `CoachingRecord`, `SpotCheckSlot`) **ne sont pas auto-exposées** : elles portent le champ nominatif `operatorRef`, muré derrière `CoachingDataVoter`. La seule lecture des écarts offerte au pilotage est `ComplianceSummary` — un **agrégat anonyme** réservé à `ROLE_RESPONSABLE`. Exposer une nouvelle ressource est une décision explicite à prendre point par point.

## Tests & qualité

```bash
php bin/phpunit          # tests unitaires (escalade, commit-reveal)
vendor/bin/phpstan       # analyse statique (niveau 6)
```

## Sécurité

Voir [`SECURITY.md`](SECURITY.md) — points durs MVP→prod, et revue des invariants à ne pas casser.
