# Sécurité — Palabrae API

Revue du squelette et de la base métier fournie. La base est déjà solide
(fail-closed, CSPRNG, `hash_equals`, pseudonymisation, append-only). Cette note
liste les points **à traiter avant prod** et les invariants à ne pas casser.

## ✅ Corrigé

### A. La finalité d'accès (`data_purpose`) est posée côté serveur (anti-forge)

> **Risque initial :** si `data_purpose` provenait d'un claim contrôlé par le
> client (JWT forgé), un attaquant posait `data_purpose = coaching` et lisait la
> donnée nominative.

- `DataPurposeResolver` = **source de vérité unique**, dérivée du chemin de la
  route (`/api/coaching/*` → COACHING, `/api/compliance/*` → COMPLIANCE, sinon
  `null` → refus). Jamais de donnée cliente.
- `DataPurposeContextListener` = **seul écrivain** de l'attribut (priorité 7) :
  efface toute valeur préexistante puis pose la finalité serveur. Une valeur
  forgée est systématiquement écrasée.
- `CoachingDataVoter::resolvePurpose()` n'accepte plus qu'une **instance d'enum**.
- `DISCIPLINARY` n'est jamais dérivable ici (contexte isolé).
- Couvert par `tests/Unit/DataPurposeResolverTest.php`.

> ⚠️ Garde-fou : ne **jamais** mapper un claim JWT vers l'attribut `data_purpose`.

### B. Journal d'intégrité : append sérialisé (plus de race condition)

> **Risque initial :** `IntegrityJournal::append()` lisait la tête (`seq` max)
> puis insérait sans sérialisation → sous concurrence, collision sur `seq` unique
> ou fourche de chaîne.

- L'append s'exécute dans une **transaction englobante** ouverte par
  `wrapInTransaction()`, qui prend d'abord un **verrou consultatif
  transactionnel** PostgreSQL : `SELECT pg_advisory_xact_lock(<clé>)`.
- Les append concurrents attendent puis lisent une tête à jour (READ COMMITTED) ;
  le verrou est libéré automatiquement au commit/rollback. La chaîne ne peut plus
  fourcher ni dupliquer un `seq`.
- ⚠️ Spécifique PostgreSQL. Sur un autre SGBD, basculer sur un consommateur
  Messenger **unique** (single-writer) pour conserver la sérialisation.

### C. Boîte noire : append-only renforcé en base (trigger PL/pgSQL)

> **Risque initial :** `IntegrityLogEntry` est append-only côté code (aucun
> setter), mais l'ORM n'empêche pas un `UPDATE`/`DELETE` SQL direct.

- Migration `Version20260623160000` : trigger `trg_integrity_log_append_only`
  (`BEFORE UPDATE OR DELETE`) qui lève une exception — le journal est append-only
  au niveau du SGBD, pas seulement en code.
- Choix d'un **trigger** plutôt qu'un `REVOKE` : l'app se connecte comme
  propriétaire de la table, lequel conserve ses privilèges malgré un REVOKE ; le
  trigger s'applique à toutes les sessions.
- ⚠️ PostgreSQL uniquement. Durcissement complémentaire : faire tourner l'app
  sous un rôle **non propriétaire** + `REVOKE UPDATE, DELETE ON integrity_log`.

### D. `SealStore` persistant multi-process (coffre dédié hors base)

> **Risque initial :** `InMemorySealStore` perdait la graine entre deux process
> (PHP-FPM, commandes) → `reveal()` échouait ; et une graine en mémoire
> applicative n'est pas dans un coffre audité.

- `CacheSealStore` : coffre persistant adossé à un **pool de cache dédié**
  (`seal_store.pool`), isolé du cache applicatif ET de la base. `seal()` et
  `reveal()` peuvent tomber dans des process différents.
- Invariant préservé : la graine ne touche jamais la base applicative.
- Binding DI basculé (`config/services.yaml`) ; `InMemorySealStore` conservé
  pour les tests.
- ⚠️ **Reste pour la prod** (voir ci-dessous) : remplacer le pool filesystem par
  un secret manager (chiffrement au repos, accès audité, rotation).

## 🔴 À traiter en priorité

### 1. `SealStore` prod : adapter secret manager
Le coffre dev (`CacheSealStore` + pool filesystem) corrige le multi-process mais
n'offre pas chiffrement au repos / audit / rotation, et un `cache:clear` agressif
pourrait l'effacer. **Exigence prod :** pointer `seal_store.pool` (ou une
nouvelle implémentation de `SealStore`) vers Vault / KMS / Secrets Manager.

### 2. Ancrage externe réel
`LoggerAnchorSink` écrit dans un log **sous contrôle de l'exploitant** — ce
contre quoi l'ancrage est censé protéger. Brancher un puits hors de portée :
horodatage RFC 3161, e-mail signé à un auditeur, ou OpenTimestamps. Sinon, la
réécriture en bloc du journal reste indétectable.

## 🟠 Défense en profondeur

### 6. Accès nominatif : encapsuler plutôt que documenter
`Deviation::getOperatorRefForCoaching()` et
`SpotCheckSlot::getOperatorRefForCoaching()` sont `public` : n'importe quel code
peut les appeler **en contournant le voter**. La protection repose aujourd'hui
sur la discipline du développeur.

**Recommandation :** canaliser toute lecture nominative par un service unique
qui appelle `isGranted('VIEW_COACHING_DATA', ...)` en interne, et garder un test
qui échoue si un nouveau chemin lit ces champs sans passer par le voter.

### 7. JWT : révocation et durée de vie
Token stateless non révocable avant expiration. Comme l'app peut émettre des
`HARD_STOP`, prévoir TTL court (déjà 1 h) **+ refresh tokens** et une liste de
révocation (logout, compromission). Stocker le secret/passphrase JWT hors dépôt.

### 8. Secrets et configuration
`APP_SECRET`, `JWT_PASSPHRASE`, `DATABASE_URL`, `POSTGRES_PASSWORD` ont des
valeurs **placeholder** dans `.env` / `compose.yaml`. Les surcharger via
`.env.local` (gitignoré) en local et via les variables d'environnement / le
secret store du serveur en prod. Les clés JWT `*.pem` sont gitignorées.

### 9. CORS
`CORS_ALLOW_ORIGIN` est restreint à localhost par défaut — en prod, le fixer
aux origines réelles du front. Jamais `*` sur une API authentifiée.

## ✅ Déjà en place (à préserver)

- Comparaisons à temps constant (`hash_equals`) dans le journal et le commit-reveal.
- Aléa cryptographique (`random_bytes` / `random_int`) ; interdiction explicite des PRNG seedés.
- Pseudonymisation de `operatorRef` (matricule, pas le nom) + TTL de minimisation RGPD sur `CoachingRecord`.
- Voter **fail-closed** : sans finalité COACHING explicite, lecture refusée.
- Requêtes Doctrine paramétrées (aucune concaténation SQL) ; agrégat `ComplianceSummary` qui ne projette jamais le nominatif.
- Pagination bornée (max 100), firewalls stateless, hash de mot de passe `auto`.

## Invariants à ne jamais casser

1. La donnée d'erreur nominative n'est lisible qu'à finalité **COACHING**.
2. Le chemin manager/conformité ne **SELECT** jamais `operatorRef`.
3. Le contrôle surprise n'est déclenché par **aucun humain** (scheduler + CSPRNG).
4. Le journal est **append-only** (code **et** base).
5. La graine ne touche jamais la base tant que le plan est `SEALED`.
