# Sécurité — Palabrae API

Revue du squelette et de la base métier fournie. La base est déjà solide
(fail-closed, CSPRNG, `hash_equals`, pseudonymisation, append-only). Cette note
liste les points **à traiter avant prod** et les invariants à ne pas casser.

## 🔴 À traiter en priorité

### 1. La finalité d'accès (`data_purpose`) ne doit JAMAIS venir d'un claim JWT client
`CoachingDataVoter` lit `data_purpose` dans les attributs du token. Si cette
valeur provient d'un claim que le client contrôle (JWT forgé côté front), un
attaquant pose `data_purpose = coaching` et lit la donnée nominative.

**Exigence :** la finalité doit être **dérivée côté serveur** du contexte
authentifié (quel firewall / quel endpoint « espace coaching »), via un
listener qui pose l'attribut sur le token. Ne jamais la mapper depuis le payload
JWT. À défaut, le mur RGPD est contournable.

### 2. Journal d'intégrité : sérialiser les écritures (race condition de chaînage)
`IntegrityJournal::append()` lit la tête (`seq` max), calcule `seq+1` et
`prevHash`, puis flush. Sous concurrence (PHP-FPM, plusieurs workers), deux
appels lisent la même tête → soit collision sur `seq` unique (un flush échoue,
non géré/réessayé), soit deux maillons pointant le même `prevHash` (fourche de
chaîne).

**Exigence :** sérialiser l'append — verrou applicatif PostgreSQL
(`SELECT pg_advisory_xact_lock(...)` au début d'une transaction englobante), ou
écriture via un consommateur Messenger **unique**. Sans cela, l'intégrité du
journal n'est pas garantie en charge.

### 3. `SealStore` en mémoire : inadapté au multi-process ET à la prod
`InMemorySealStore` garde la graine dans la mémoire d'un process. En PHP-FPM,
le worker qui `seal()` n'est pas celui qui `reveal()` → graine introuvable,
`reveal()` lève. Et la graine en mémoire applicative est, par principe, hors
d'un coffre audité.

**Exigence :** implémenter `SealStore` sur un **secret manager** (Vault / KMS /
Secrets Manager) avec chiffrement au repos, accès audité et rotation. La base
applicative ne doit contenir que le `commitment`, jamais la graine.

### 4. Boîte noire : interdire UPDATE/DELETE au niveau base
`IntegrityLogEntry` est append-only côté code (aucun setter), mais l'ORM
n'empêche pas un `UPDATE`/`DELETE` direct. L'append-only doit être **renforcé
en base** :

```sql
REVOKE UPDATE, DELETE ON integrity_log FROM palabrae_app;
-- idéalement : trigger BEFORE UPDATE/DELETE qui lève une exception.
```

### 5. Ancrage externe réel
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
