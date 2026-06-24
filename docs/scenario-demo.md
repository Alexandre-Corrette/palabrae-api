# Palabrae — scénario de démo terrain

Public : 3 gérants de cantine + 2 opérateurs. Objectif : **prouver la demande**,
pas vendre une UI. Le front est volontairement brut — on montre le *comportement*
du produit, pas son esthétique.

Positionnement : **on ne vend pas un logiciel HACCP** (les cantines en ont déjà).
On vend **l'exercice procédural aléatoire** — comme un exercice d'alerte au feu,
mais pour les gestes de sécurité alimentaire. Tiré au sort, sans accabler le
personnel, exploitable par l'équipe, et qui forme au passage. La vision : que ça
devienne un **standard de sécurité**.

---

## 0. Préparation (5 min avant, hors public)

```bash
cd ~/www/palabrae-api
docker compose up -d database
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
symfony serve -d

# Programmer un exercice pour pouvoir le réaliser pendant la démo
php bin/console palabrae:spotcheck:seal SITE-LEOLAGRANGE --label=midi --min=2 --max=2
```

Ouvrir **http://127.0.0.1:8000/demo.html**. Comptes :
`operateur@demo.test` / `demo-operateur` — `responsable@demo.test` / `demo-responsable`.

---

## 1. Le pitch (30 s)

> « Vous avez déjà un logiciel HACCP : il **décrit** la procédure. Nous, on
> l'**éprouve**. Palabrae déclenche, à un moment imprévisible et tiré au sort, un
> **exercice de contrôle** — comme un exercice d'alerte au feu. Personne ne tient
> le bouton : ni le salarié, ni le manager. L'équipe s'entraîne en conditions
> réelles, sans être accablée, repart avec le bon geste, et vous gardez une trace
> exploitable. L'objectif : que l'exercice procédural aléatoire devienne un
> standard de sécurité, au même titre que l'exercice incendie. »

---

## 2. Acte 1 — l'exercice se déclenche (le héros)

1. *« Ce matin, le système a programmé tout seul un exercice sur ce service, à
   une heure que personne ne connaît. »* (Montrer que c'est déjà scellé.)
2. Connecté en **Opérateur** → bloc **Exercice du jour** → *Réaliser l'exercice*.
   Le système a tiré au sort un point ; l'opérateur le vérifie.
3. Insister sur l'autonomie : *« Ni l'employé ni le chef ne déclenchent. On ne
   peut ni l'anticiper, ni le déplacer. C'est ça qui le rend crédible — comme une
   alarme incendie surprise. »*

## 3. Acte 2 — l'exercice forme et trie selon le risque

4. Pendant l'exercice, si tout est bon → *Conforme*. *« Le geste juste est
   reconnu, pas seulement l'erreur. »*
5. Sinon → *Signaler un écart*. Faire deux cas pour montrer le tri par gravité :
   - **Propreté** (cosmétique) → simple rappel, rien de lourd.
   - **Étiquetage allergènes** (critique) → lot bloqué, enregistrement scellé,
     et surtout la **micro-leçon** : le pourquoi + le geste. *« L'exercice n'est
     pas une sanction, c'est une répétition : on apprend le bon geste sur le
     moment. »*

## 4. Acte 3 — le quotidien (mécanique secondaire)

6. La liste des points est juste le **vivier dans lequel l'exercice tire au
   sort** — pas une checklist de plus à cocher (ça, c'est leur outil HACCP
   existant). Le montrer rapidement, ne pas s'y attarder.

## 5. Acte 4 — le manager : audit-readiness + progression

7. Reconnecté en **Responsable** → *Conformité du service* puis *Contrôles
   programmés*.
8. Le double bénéfice qui fait payer :
   - **Prêt pour l'audit** : *« Quand la DDPP débarque, votre équipe a déjà
     répété, et vous avez une trace infalsifiable. »*
   - **Montée en compétence** : *« Vos équipes tournent ; chaque exercice forme
     au geste. »*
9. Montrer réalisés / **manqués** : *« Un exercice non fait à temps se voit — et
   c'est prouvable, pas déclaratif. »*

## 6. Acte 5 — la clôture prouvable (la boîte noire)

```bash
php bin/console palabrae:spotcheck:reveal "SITE-LEOLAGRANGE:$(date +%F):midi"
```

10. Sortie : **graine révélée**, réalisés vs manqués. *« On révèle le tirage en
    fin de créneau : vous, un auditeur, la DDPP, n'importe qui peut recalculer et
    vérifier que l'exercice était fixé d'avance. Ni avancé, ni masqué. »*

---

## Les 4 phrases à marteler

1. **On éprouve la procédure, on ne la re-décrit pas** — l'exercice, pas une
   énième checklist.
2. **Comme un exercice d'alerte au feu** — aléatoire, normal, non punitif, et
   ça prépare au vrai contrôle.
3. **L'équipe se forme en faisant** — la micro-leçon au moment du geste.
4. **Prouvable** — la trace honnête vous défend sans discussion.

## Questions probables → réponses courtes

- *« Et si l'exercice tombe en plein coup de feu, c'est ingérable ! »* → Les
  exercices sont **aléatoires mais jamais pendant le rush** : la plage du service
  (ex. 12 h-14 h) est bloquée dans le système. Ça tombe avant ou après, jamais
  pendant. *(Voir feature « plage interdite ».)*
- *« J'ai déjà un logiciel HACCP. »* → Tant mieux — il décrit vos procédures.
  Palabrae ne le remplace pas, il les met à l'épreuve par tirage au sort. Les
  deux se complètent.
- *« C'est de la surveillance ? »* → C'est un exercice collectif, comme une
  alarme incendie. Le responsable agit sur des tendances pour réduire le risque ;
  l'accompagnement d'une personne se passe ailleurs, et n'est conservé que peu de
  temps (RGPD). L'esprit : épauler la personne qui fait le geste.

---

## Après la démo — ce qu'on écoute

On ne cherche pas l'applaudissement, on cherche le signal d'achat : *« combien
ça coûte ? », « est-ce que mon assureur / la DDPP le reconnaît ? », « est-ce que
je peux l'imposer comme standard à mes sites ? »*. Noter chaque objection : c'est
la vraie donnée de cette étape.
