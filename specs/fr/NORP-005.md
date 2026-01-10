# NORP-005
## Ordonnancement Topologique Déterministe pour Workflows d'Orchestration IA

---

**Licence**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (À attribuer)

---

### Statut
Stable

### Catégorie
Compilation et Sémantique d'Exécution

### Version
1.2

### Date
2026-01-09

### Auteurs
Groupe de Travail NORP

---

## 1. Résumé

Cette spécification définit les exigences d'ordonnancement topologique déterministe pour les workflows d'orchestration IA représentés comme graphes dirigés.

Elle standardise comment les systèmes conformes DOIVENT produire un ordre d'exécution logique déterministe, incluant règles de départage obligatoires, compatibilité d'exécution parallèle, diagnostics, et une suite de tests de conformité.

L'objectif est d'assurer reproductibilité, auditabilité, et exécution prévisible à travers les workflows IA complexes.

---

## 2. Motivation

Les systèmes d'orchestration IA exécutent fréquemment des workflows comme graphes de dépendances. Lorsque plusieurs ordres topologiques valides existent, un ordonnancement non-déterministe cause :

- **Comportement non-reproductible** et pistes d'audit
- **Profils de coût et latence incohérents** (ordre différent = timing différent = coûts différents)
- **Débogage difficile** (impossible de reproduire le problème si l'ordre varie)
- **Résultats divergents** à travers déploiements et runtimes

L'ordonnancement déterministe est donc une exigence de production, pas un détail d'implémentation.

---

## 3. Périmètre

Cette spécification s'applique aux systèmes qui compilent des workflows en plan d'exécution dérivé d'un graphe de dépendances dirigé.

### 3.1 Relation avec NORP-001 et NORP-004

Cette spécification est **complémentaire** à :

- **NORP-001 Section 5.3** (Compilation) : Définit l'exigence haut-niveau pour ordonnancement d'exécution déterministe
- **NORP-004** (Détection Cycles) : Définit le rejet obligatoire de graphes cycliques durant validation structurelle

**NORP-005 spécifie** comment l'ordonnancement déterministe DOIT être implémenté et vérifié **après** qu'un graphe soit validé comme acyclique.

Un système conforme DOIT implémenter :
- **NORP-004** (ou équivalent) pour assurer que le graphe d'entrée est acyclique
- **NORP-005** pour produire ordonnancement déterministe depuis DAG validé

**Résumé des relations** :
```
NORP-004: Validation graphe (rejeter si cycle)
    ↓
NORP-005: Ordonnancement déterministe (si DAG valide)
    ↓
NORP-001: Exécution (utilisant ordre déterministe)
```

---

## 4. Terminologie

**Workflow** : Un graphe dirigé de nœuds et arêtes.

**Nœud** : Une unité d'exécution.

**Arête** : Une relation de dépendance dirigée (si arête A→B existe, A doit s'exécuter avant B).

**Ordre topologique** : Un ordonnancement de nœuds tel que pour chaque arête U→V, U apparaît avant V dans la séquence.

**Ordre logique** : Une séquence déterministe dérivée des dépendances et règles de départage (utilisé pour auditabilité).

**Exécution physique** : L'ordonnancement runtime de l'exécution des nœuds, qui peut être parallélisé pour performance.

**Règle de départage** : Une règle déterministe appliquée lorsque plusieurs nœuds sont également éligibles pour ordonnancement.

Les mots-clés DOIT, DEVRAIT et PEUT sont à interpréter selon la [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Exigences Normatives

### 5.1 Ordonnancement Déterministe

Étant données une **définition de workflow identique** et une **configuration de départage identique**, un système conforme DOIT produire le **même ordre d'exécution logique** à travers les exécutions.

L'ordre d'exécution DOIT être **indépendant de** :
- Timing ou latence runtime
- Ordre d'insertion de nœuds dans la représentation d'entrée
- Variabilité d'infrastructure (charge serveur, délais réseau)
- Randomisation de fonction de hachage

Si le workflow est cyclique, le système DOIT le **rejeter avant ordonnancement**, cohérent avec NORP-004.

---

#### 5.1.1 Exigences d'Algorithme de Tri Topologique

Les implémentations conformes DOIVENT utiliser une approche de tri topologique qui :

- Opère en temps **O(V + E)** pour le composant de traversée de graphe
- Produit un **ordre logique déterministe** en appliquant une règle de départage déterministe chaque fois que plusieurs nœuds sont éligibles
- **Rejette le workflow** si un ordre complet ne peut être produit (cycle détecté)

Les implémentations PEUVENT utiliser **l'algorithme de Kahn**, **parcours DFS post-ordre**, ou méthodes équivalentes, à condition que le déterminisme et sémantique de rejet soient satisfaits.

---

#### 5.1.2 Tri Topologique Déterministe (Référence Optionnelle)

Les implémentations PEUVENT fournir un algorithme de tri topologique déterministe explicite.

Lorsque plusieurs nœuds sont éligibles pour exécution au même niveau de dépendance, l'algorithme DOIT appliquer la règle de départage documentée de façon cohérente.

La référence suivante illustre **l'algorithme de Kahn avec départage déterministe** utilisant ordonnancement lexicographique.

**Pseudocode** :

```python
def topological_sort_deterministic(graph):
    """
    Algorithme de Kahn avec départage déterministe.

    Retourne: Ordre d'exécution logique déterministe
    Complexité: O(V log V + E) dû au tri
    """
    in_degree = {node: 0 for node in graph}

    # Calculer degrés entrants
    for node in graph:
        for dep in graph[node]:
            in_degree[dep] += 1

    # Sélection déterministe nœuds degré entrant zéro
    queue = sorted([n for n in graph if in_degree[n] == 0])  # ← Départage
    result = []

    while queue:
        current = queue.pop(0)
        result.append(current)

        newly_eligible = []
        for dep in graph[current]:
            in_degree[dep] -= 1
            if in_degree[dep] == 0:
                newly_eligible.append(dep)

        # Réinsertion déterministe (départage)
        queue = sorted(queue + newly_eligible)  # ← Départage

    # Détection cycle (si tous nœuds pas triés)
    if len(result) != len(graph):
        raise CycleDetectedError("Tri topologique échoué : cycle existe")

    return result
```

**Clé pour déterminisme** : Les appels `sorted()` assurent ordonnancement cohérent lorsque plusieurs nœuds ont degré entrant zéro.

**Complexité** : O(V log V + E) dû au tri, acceptable pour production.

**Note** : Cet exemple est **non-normatif** et fourni pour clarté. Les implémentations PEUVENT utiliser algorithmes alternatifs à condition que toutes exigences normatives soient satisfaites.

---

### 5.2 Règles de Départage

Lorsque plusieurs nœuds sont éligibles pour ordonnancement à la même étape, le système DOIT appliquer une **règle de départage cohérente**.

La règle de départage DOIT être :
- **Déterministe** (mêmes entrées = même sortie)
- **Documentée** (spécifiée publiquement)
- **Stable à travers exécutions** (ne change pas entre exécutions)

Les mécanismes de départage valides incluent, sans s'y limiter :
- Ordonnancement lexicographique des identifiants de nœuds
- Champs clés d'ordonnancement stables (ex: `created_at`, `insertion_index`)
- Métadonnées de priorité explicites

Les systèmes DOIVENT documenter quelle règle de départage est utilisée et comment les conflits sont résolus.

---

#### 5.2.1 Exemples de Départage

**Exemple 1 : Lexicographique par ID Nœud**

Nœuds : `Z`, `A`, `M` (pas de dépendances, tous éligibles simultanément)

Départage : Ordre lexicographique ascendant par ID nœud
Ordre logique : `["A", "M", "Z"]`

---

**Exemple 2 : Métadonnées Priorité Explicite**

```json
{
  "nodes": [
    {"id": "A", "priority": 2, "depends_on": []},
    {"id": "B", "priority": 1, "depends_on": []}
  ]
}
```

Départage : Priorité numérique ascendante, puis lexicographique par ID
Ordre logique : `["B", "A"]` (B a priorité 1 < A priorité 2)

---

**Exemple 3 : Préservation Ordre d'Insertion**

Nœuds insérés en séquence : `A`, puis `C`, puis `B`

Départage : Préserver ordre d'insertion
Ordre logique : `["A", "C", "B"]`

**Note** : L'ordre d'insertion requiert métadonnées stables (ex: champ `insertion_index`) pour être déterministe à travers redémarrages système.

---

### 5.3 Compatibilité Exécution Parallèle

L'ordonnancement logique déterministe DOIT coexister avec l'exécution physique parallèle.

Le système DOIT pouvoir :
- Produire un **ordre logique déterministe** pour auditabilité et reproductibilité
- Identifier quels nœuds sont **éligibles à s'exécuter en parallèle** lorsque les dépendances le permettent

L'exécution parallèle PEUT survenir lorsque les nœuds n'ont pas de relation de dépendance.

---

#### 5.3.1 Ordre Logique vs Exécution Physique

**L'ordre logique** est indépendant du timing runtime et représente la **séquence déterministe respectant les dépendances**.

**L'exécution physique** est l'ordonnancement runtime réel, qui PEUT être parallélisé pour performance.

**Exemple** :
- **Ordre logique** : `[A, B, C, D]`
- **Exécution physique** :
  - Temps T0 : A s'exécute
  - Temps T1 : B et C s'exécutent **en parallèle** (les deux dépendent uniquement de A)
  - Temps T2 : D s'exécute (après complétion B et C)

Le système DOIT :
- Produire ordre logique de façon déterministe (toujours `[A, B, C, D]`)
- PEUT exécuter B et C en parallèle (optimisation physique)
- Journaliser événements et pistes audit utilisant **ordre logique**, pas ordre complétion physique

**Exemple piste audit** :
```json
{
  "execution_id": "exec_123",
  "logical_order": ["A", "B", "C", "D"],
  "physical_execution": [
    {"node": "A", "started_at": "10:00:00", "completed_at": "10:00:05"},
    {"node": "B", "started_at": "10:00:05", "completed_at": "10:00:10"},
    {"node": "C", "started_at": "10:00:05", "completed_at": "10:00:08"},
    {"node": "D", "started_at": "10:00:10", "completed_at": "10:00:15"}
  ]
}
```

**Note** : C complété avant B physiquement, mais ordre logique reste `[B, C]` (déterministe).

---

## 6. Diagnostics Déterministes

Si l'ordonnancement échoue, le système DOIT retourner un **diagnostic déterministe**.

Les erreurs d'échec d'ordonnancement DOIVENT inclure :
- **Type d'erreur** : `STRUCTURAL_ERROR`
- **Code d'erreur** : `ORDERING_FAILED`
- Champ **Raison** identifiant la classe d'échec

Les raisons valides incluent :
- `CYCLE_DETECTED` (cycle existe, ordonnancement impossible)
- `MISSING_NODE_REFERENCE` (nœud dépend d'un nœud non-existant)
- `INVALID_DEPENDENCY` (structure de dépendance malformée)

**Exemple diagnostic minimal** :

```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "ORDERING_FAILED",
  "reason": "CYCLE_DETECTED",
  "message": "Ordre topologique n'a pu être produit à cause cycle dans graphe"
}
```

---

## 7. Considérations de Sécurité

L'ordonnancement non-déterministe peut être exploité pour :
- **Cacher comportement malveillant** derrière variance de timing (ordres différents = effets de bord observables différents)
- **Produire pistes audit incohérentes** (violations conformité indétectables)
- **Déclencher patterns amplification coûts** via ordonnancement imprévisible

L'ordonnancement déterministe fait donc partie de **l'intégrité workflow** et **sécurité opérationnelle**.

---

## 8. Guide d'Implémentation (Non-Normatif)

### 8.1 Anti-Patterns Courants

#### Anti-Pattern 1 : S'Appuyer sur Ordre Complétion Runtime

❌ **MAUVAIS** : Inférer ordre exécution depuis quels nœuds terminent en premier
```javascript
// Attendre tous nœuds complètent, enregistrer ordre complétion
const order = [];
nodes.forEach(n => n.on('complete', () => order.push(n.id)));
// ❌ Non-déterministe (dépend timing runtime)
```

✅ **BON** : Persister ordre logique depuis compilation, indépendant timing runtime
```javascript
// Calculer ordre logique AVANT exécution
const logicalOrder = topologicalSort(workflow.graph);
// Exécuter utilisant ordre logique (ou parallélisé respectant dépendances)
executeWorkflow(workflow, logicalOrder);
```

---

#### Anti-Pattern 2 : Départage Non-Documenté

❌ **MAUVAIS** : Dépendre ordre itération hash map
```python
# Ordre itération dict Python (dépendant implémentation avant 3.7)
eligible = {node: data for node, data in graph.items() if ready(node)}
next_node = list(eligible.keys())[0]  # ❌ Non-déterministe
```

✅ **BON** : Ordonnancement lexicographique explicite ou clés stables
```python
eligible = [node for node in graph if ready(node)]
next_node = sorted(eligible)[0]  # ✅ Déterministe (lexicographique)
```

---

#### Anti-Pattern 3 : Ordonnancement Partiel Graphe

❌ **MAUVAIS** : Ordonner seulement nœuds atteignables depuis racine sélectionnée
```php
// Ordonner seulement nœuds atteignables depuis point d'entrée
$order = orderFromRoot($graph, $entryNode);
```

✅ **BON** : Ordonner graphe workflow complet
```php
// Ordonner TOUS nœuds (incluant composantes déconnectées)
$order = topologicalSort($graph->getAllNodes());
```

**Pourquoi** : Nœuds déconnectés peuvent contenir cycles (détectables seulement avec validation graphe complet).

---

### 8.2 Considérations de Performance (Non-Normatif)

Performance typique tri topologique sur charges production :
- **10 nœuds** : <1ms
- **100 nœuds** : ~5ms
- **1 000 nœuds** : ~50ms
- **10 000 nœuds** : ~500ms

Avec départage déterministe (tri), complexité devient O(V log V + E), ce qui est acceptable pour production.

---

## 9. Conformité

Un système est **conforme NORP-005** si :
- Il implémente toutes exigences obligatoires (Sections 5 et 6)
- Il rejette workflows cycliques avant ordonnancement (conformité NORP-004 assumée)
- Il produit ordonnancement logique déterministe à travers exécutions répétées
- Il réussit tous tests conformité obligatoires

### 9.1 Suite de Tests de Conformité

**Test 1 : Ordre Déterministe - DAG Simple**

**Entrée** :
```json
{
  "nodes": [
    {"id": "A", "depends_on": []},
    {"id": "B", "depends_on": ["A"]}
  ]
}
```

**Action** : Valider et compiler deux fois

**Attendu** : Ordre logique identique les deux fois : `["A", "B"]`

**Justification** : Prouve déterminisme basique sur chaîne dépendance simple.

---

**Test 2 : Cohérence Départage**

**Entrée** :
```json
{
  "nodes": [
    {"id": "Z", "depends_on": []},
    {"id": "A", "depends_on": []},
    {"id": "M", "depends_on": []}
  ]
}
```

**Règle départage** : Lexicographique ascendant par ID nœud

**Action** : Compiler deux fois

**Attendu** : Ordre logique identique les deux fois : `["A", "M", "Z"]`

**Justification** : Prouve que règle départage appliquée de façon cohérente lorsque plusieurs nœuds également éligibles.

---

**Test 3 : Déterminisme Pattern Diamant**

**Entrée** :
```json
{
  "nodes": [
    {"id": "A", "depends_on": []},
    {"id": "B", "depends_on": ["A"]},
    {"id": "C", "depends_on": ["A"]},
    {"id": "D", "depends_on": ["B", "C"]}
  ]
}
```

**Attendu** :
- Ordre logique **respecte dépendances** (A avant B/C, B/C avant D)
- Ordre est **cohérent à travers exécutions**
- Si départage lexicographique par ID, ordre logique est : `["A", "B", "C", "D"]`

**Alternative valide** (si départage diffère) : `["A", "C", "B", "D"]` (de façon cohérente)

**Justification** : Prouve déterminisme sur graphes avec ordres valides multiples.

---

**Test 4 : Identification Éligibilité Parallèle**

**Entrée** : (Même pattern diamant que Test 3)

**Sortie Attendue** :
```json
{
  "logical_order": ["A", "B", "C", "D"],
  "parallel_groups": [
    {"level": 0, "nodes": ["A"]},
    {"level": 1, "nodes": ["B", "C"], "parallel": true},
    {"level": 2, "nodes": ["D"]}
  ]
}
```

**Justification** : Vérifie que :
- Ordre exécution logique est déterministe (`[A, B, C, D]`)
- Nœuds B et C identifiés comme **éligibles parallèle** (même niveau dépendance)
- Nœud D **non éligible** jusqu'à ce que toutes dépendances (B et C) complètent
- Parallélisme n'altère pas garanties ordonnancement logique

---

**Test 5 : Vérification Croisée Rejet Cycle**

**Entrée** :
```json
{
  "nodes": [
    {"id": "A", "depends_on": ["B"]},
    {"id": "B", "depends_on": ["A"]}
  ]
}
```

**Attendu** :
- **Rejet avant ordonnancement** (conformité NORP-004)
- Type erreur : `STRUCTURAL_ERROR`
- Raison erreur : `CYCLE_DETECTED`

**Justification** : Assure détection cycle (NORP-004) survient avant tentative ordonnancement (NORP-005).

Spécifications tests complètes disponibles dans `compliance-tests/NORP-005-tests.md`.

---

## 10. Considérations de Sécurité

L'ordonnancement non-déterministe peut être exploité pour :
- **Cacher comportement malveillant** derrière variance timing (ordres différents = effets de bord observables différents)
- **Produire pistes audit incohérentes** (violations conformité indétectables)
- **Déclencher amplification coûts** via ordonnancement imprévisible (nœuds coûteux exécutés de façon redondante)

L'ordonnancement déterministe fait donc partie de **l'intégrité workflow** et **sécurité opérationnelle**.

---

## 11. Résumé de la Justification

**Principe Fondamental** : Un ordonnancement d'exécution reproductible est obligatoire pour débogage, auditabilité, et confiance dans systèmes d'orchestration IA.

Le déterminisme garantit que des workflows identiques produisent plans d'exécution identiques, permettant réponse incident fiable, audits conformité, et attribution coûts.

Ce principe s'applique indépendamment de la complexité d'orchestration, du langage de programmation, ou de l'infrastructure.

---

## 12. Extensions Futures

Les spécifications NORP futures PEUVENT définir :
- Ordonnancement basé priorité avec sémantique formelle
- Ordonnancement adaptatif basé métriques runtime (avec déterminisme préservé)
- Garanties ordonnancement distribué à travers déploiements multi-régions
- Préservation ordonnancement sous mutations graphe

---

## 13. Références

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) : Mots-clés pour l'usage dans les RFC pour indiquer les niveaux d'exigence
- Cormen, T. H., et al. (2009). *Introduction to Algorithms* (3ème éd.). MIT Press. (Chapitre 22 : Tri Topologique)
- Kahn, A. B. (1962). "Topological sorting of large networks". *Communications of the ACM*.
- Principes de conception systèmes déterministes

---

## 14. Remerciements

Cette spécification est dérivée de l'implémentation de tri topologique dans NeuraScope BlueprintCompiler (testé en production sur 10 000+ workflows).

Les auteurs remercient les relecteurs pour leurs retours sur sémantique de départage et compatibilité exécution parallèle.

---

## Annexe A : Exemples de Workflows

### A.1 DAG Simple

```json
{
  "name": "Workflow Séquentiel Simple",
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": []},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]}
  ]
}
```

**Ordre logique** : `["A", "B"]` (déterministe, un seul ordre valide)

---

### A.2 DAG Diamant

```json
{
  "name": "Workflow Pattern Diamant",
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": []},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]},
    {"id": "C", "type": "transform", "depends_on": ["A"]},
    {"id": "D", "type": "output", "depends_on": ["B", "C"]}
  ]
}
```

**Ordre logique** (départage lexicographique) : `["A", "B", "C", "D"]`

**Groupes parallèles** :
- Niveau 0 : [A]
- Niveau 1 : [B, C] (éligibles parallèle)
- Niveau 2 : [D]

---

### A.3 DAG Compatible Parallélisme

```json
{
  "name": "Workflow ETL avec Parallélisme",
  "nodes": [
    {"id": "extract", "type": "datasource", "depends_on": []},
    {"id": "summarize", "type": "llm_call", "depends_on": ["extract"]},
    {"id": "classify", "type": "llm_call", "depends_on": ["extract"]},
    {"id": "publish", "type": "output", "depends_on": ["summarize", "classify"]}
  ]
}
```

**Ordre logique** (lexicographique) : `["extract", "classify", "summarize", "publish"]`

**Groupes parallèles** :
- Niveau 0 : [extract]
- Niveau 1 : [classify, summarize] (éligibles parallèle - les deux dépendent uniquement d'extract)
- Niveau 2 : [publish]

**Note** : Ordre logique place "classify" avant "summarize" (départage alphabétique), mais exécution physique peut les exécuter en parallèle.

---

## Annexe B : Historique des Révisions

| Version | Date | Changements |
|---------|------|-------------|
| 1.2 | 2026-01-09 | Statut mis à jour vers Stable. Ajout relation NORP-001 et NORP-004 (3.1), exigences algorithme (5.1.1), pseudocode Kahn déterministe (5.1.2), exemples départage (5.2.1), clarification ordre logique vs physique (5.3.1), diagnostics déterministes (Section 6), anti-patterns (8.1), tests conformité (9.1), exemples workflows (Annexe A). |
| 1.0 | 2026-01-07 | Brouillon initial. |

---

## Citation

```bibtex
@techreport{norp005-2026,
  title={{NORP-005: Ordonnancement Topologique Déterministe pour Workflows d'Orchestration IA}},
  author={{Groupe de Travail NORP}},
  institution={NeuraScope},
  year={2026},
  month={Janvier},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/fr/NORP-005},
  license={CC BY 4.0}
}
```

---

**NORP-005 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Sous licence CC BY 4.0**
