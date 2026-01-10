# NORP-004
## Détection de Cycles et Validité de Graphe pour Systèmes d'Orchestration IA

---

**Licence**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (À attribuer)

---

### Statut
Stable

### Catégorie
Validation Structurelle

### Version
1.2

### Date
2026-01-09

### Auteurs
Groupe de Travail NORP

---

## 1. Résumé

Cette spécification définit des exigences obligatoires pour détecter et rejeter les cycles structurels dans les graphes de workflows d'orchestration IA.

Elle assure que les workflows forment un **Graphe Acyclique Dirigé (DAG)** valide avant compilation ou exécution, empêchant les deadlocks, l'exécution non-déterministe, la consommation infinie de ressources, et les comportements de retry non sûrs.

L'objectif est de garantir une exécution de workflow sûre, déterministe et prévisible.

---

## 2. Motivation

Les workflows d'orchestration IA expriment communément les dépendances d'exécution comme graphes dirigés.

Les cycles structurels dans de tels graphes causent :
- **Deadlocks** et boucles d'exécution infinies
- **Ordre d'exécution non-déterministe** (impossible d'établir une séquence valide)
- **Consommation illimitée de ressources** (CPU, mémoire, tokens LLM)
- **Sémantique de retry et échec non définie** (que réessayer si dépendance circulaire ?)

La détection de cycles DOIT donc survenir **avant la compilation ou l'exécution** et DOIT rejeter les workflows invalides de façon déterministe.

---

## 3. Périmètre

Cette spécification s'applique aux systèmes qui exécutent des workflows représentés comme graphes de dépendances dirigés.

### 3.1 Relation avec NORP-001

Cette spécification est une **extension de NORP-001 Section 5.2** (Validation Structurelle).

- **NORP-001** mandate que la validation structurelle DOIT inclure la détection de cycles
- **NORP-004** spécifie **comment la détection de cycles DOIT être implémentée**, validée et diagnostiquée

Un système implémentant à la fois NORP-001 et NORP-004 atteint une **sécurité structurelle complète**.

---

### 3.2 Boucles vs Cycles Structurels

Cette spécification adresse les **cycles structurels** dans les graphes de dépendances (arêtes cycliques entre nœuds).

**Les constructions de boucle explicites** avec sémantique d'itération bornée sont **HORS PÉRIMÈTRE**.

**Exemple DANS LE PÉRIMÈTRE** (cycle structurel - INTERDIT) :
```
Nœud A → Nœud B → Nœud C → Nœud A
```

**Exemple HORS PÉRIMÈTRE** (construction de boucle bornée - peut être permise) :
```
Loop(iterations = 10) {
  Nœud X → Nœud Y
}
```

Les spécifications NORP futures (ex: NORP-011) PEUVENT adresser la sémantique de boucle explicite.

---

## 4. Terminologie

**Nœud** : Une unité d'exécution au sein d'un workflow.

**Arête** : Une dépendance dirigée d'un nœud vers un autre.

**Cycle** : Un chemin dans le graphe où un nœud est atteignable depuis lui-même en suivant les arêtes dirigées.

**DAG** (Directed Acyclic Graph / Graphe Acyclique Dirigé) : Un graphe dirigé sans cycles.

**Arête arrière** : Une arête pointant vers un nœud actuellement dans la pile de récursion (terminologie DFS).

Les mots-clés DOIT, DEVRAIT et PEUT sont à interpréter selon la [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Exigences Normatives

### 5.1 Graphe Acyclique Obligatoire

Les graphes de workflow DOIVENT être **acycliques** (former un DAG valide).

Tout cycle détecté DOIT résulter en **rejet immédiat** du workflow.

La tolérance de cycles ou résolution automatique N'EST PAS permise par défaut.

---

### 5.2 Timing de Validation

La détection de cycles DOIT survenir :
- **Avant la compilation**
- **Avant l'exécution**
- Comme partie de la **validation structurelle** (NORP-001 Étape 1)

L'exécution de graphes cycliques N'EST PAS permise.

La détection de cycles NE DOIT PAS être différée au runtime.

---

### 5.3 Exigences Algorithmiques

La détection de cycles DOIT opérer en temps **O(V + E)** ou mieux, où :
- **V** = nombre de nœuds
- **E** = nombre d'arêtes (relations de dépendance)

Les algorithmes avec **complexité super-linéaire** (ex: O(V²), O(V³)) NE SONT PAS conformes.

---

#### 5.3.1 Algorithme de Parcours en Profondeur (Référence)

Les implémentations conformes basées sur DFS DOIVENT :
- Suivre les **nœuds visités** (nœuds entièrement explorés)
- Suivre la **pile de récursion** (nœuds actuellement en exploration)
- Détecter les **arêtes arrière** (arête vers nœud dans pile récursion = cycle)

**Pseudocode** :

```python
def detect_cycle(graph):
    """
    Détecter cycles dans graphe dirigé utilisant DFS.

    Retourne: True si cycle existe, False sinon
    Complexité: O(V + E)
    """
    visited = set()
    rec_stack = set()

    def dfs(node):
        # Arête arrière détectée → CYCLE
        if node in rec_stack:
            return True

        # Déjà entièrement exploré
        if node in visited:
            return False

        # Marquer comme en visite
        visited.add(node)
        rec_stack.add(node)

        # Explorer dépendances
        for dependency in graph[node]:
            if dfs(dependency):
                return True

        # Backtrack (retirer de pile récursion)
        rec_stack.remove(node)
        return False

    # Vérifier tous nœuds (gère sous-graphes déconnectés)
    for node in graph.all_nodes():
        if dfs(node):
            return True

    return False
```

**Preuve de complexité** :
- Chaque nœud visité **une fois** → O(V)
- Chaque arête traversée **une fois** → O(E)
- **Total** : O(V + E)

---

#### 5.3.2 Alternative : Validation par Tri Topologique (Optionnel)

La détection de cycles PEUT aussi être effectuée via **tri topologique** (algorithme de Kahn) :

```python
def detect_cycle_kahn(graph):
    """
    Détecter cycles via tri topologique.

    Si tous nœuds peuvent être triés, aucun cycle n'existe.
    Complexité: O(V + E)
    """
    in_degree = {node: 0 for node in graph}

    # Calculer degrés entrants
    for node in graph:
        for dep in graph[node]:
            in_degree[dep] += 1

    # Commencer avec nœuds degré entrant zéro
    queue = [n for n in graph if in_degree[n] == 0]
    sorted_count = 0

    while queue:
        current = queue.pop(0)
        sorted_count += 1

        for dep in graph[current]:
            in_degree[dep] -= 1
            if in_degree[dep] == 0:
                queue.append(dep)

    # Si tous nœuds pas triés → CYCLE existe
    return sorted_count != len(graph)
```

DFS et Kahn sont tous deux **conformes NORP-004**.

---

### 5.4 Rejet Déterministe et Diagnostics

Lorsqu'un cycle est détecté, le système DOIT rejeter le workflow **de façon déterministe**.

Aucun plan d'exécution partiel NE PEUT être produit.

#### 5.4.1 Format de Diagnostic

Les erreurs de détection de cycles DOIVENT inclure :
- **Type d'erreur** : `STRUCTURAL_ERROR`
- **Code d'erreur** : `CYCLE_DETECTED`
- **Au moins un chemin de cycle** (séquence de nœuds formant le cycle)

**Exemple de diagnostic minimal** :

```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "message": "Cycle détecté dans le graphe de workflow",
  "cycle_path": ["A", "B", "C", "A"]
}
```

Les systèmes PEUVENT inclure informations additionnelles (cycles multiples, visualisation graphe, suggestions correction).

---

#### 5.4.2 Complétude du Diagnostic

Les systèmes DEVRAIENT détecter et rapporter **TOUS les cycles** dans le graphe (meilleure expérience utilisateur).

Les systèmes DOIVENT rapporter **au moins UN cycle** (exigence minimale).

**Exemple de diagnostic complet** (recommandé) :

```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "message": "2 cycles détectés dans le graphe de workflow",
  "cycles": [
    {"path": ["A", "B", "C", "A"]},
    {"path": ["D", "E", "D"]}
  ]
}
```

---

### 5.5 Pas de Rupture Implicite de Cycle

Le système NE DOIT PAS automatiquement :
- Retirer des arêtes pour briser les cycles
- Réordonner les dépendances
- Insérer délais, gardes, ou exécution conditionnelle
- Modifier la structure du graphe de workflow pour contourner les cycles

**La résolution de cycle est la responsabilité de l'auteur du workflow.**

La transformation automatique de graphe N'EST PAS permise sans consentement explicite de l'utilisateur.

---

## 6. Comportement Fail-Safe

Si la détection de cycles ne peut être complétée de façon fiable (ex: timeout algorithme, épuisement mémoire), l'exécution DOIT être empêchée.

Échouer à détecter les cycles DOIT être traité comme un **échec de validation**, pas une réussite.

---

## 7. Considérations de Sécurité

Les graphes cycliques peuvent être exploités pour :
- **Attaques de déni de service** (exécution infinie consommant ressources)
- **Retries infinies** (nœud échoué ré-exécuté indéfiniment)
- **Amplification de coûts** (appels LLM dans cycle = facturation illimitée)
- **Épuisement de ressources** (mémoire, connexions, quotas)

La détection de cycles est donc une **étape de validation critique pour la sécurité**.

Le rejet strict réduit la surface d'attaque et augmente la prévisibilité système.

---

## 8. Guide d'Implémentation (Non-Normatif)

### 8.1 Anti-Patterns Courants

#### Détection de Cycles au Runtime

❌ **MAUVAIS** : Détecter cycles durant l'exécution
```python
def execute_node(node):
    if node.execution_count > 100:
        raise Exception("Cycle possible détecté après 100 itérations")
    node.execution_count += 1
    # Exécuter logique nœud
```

✅ **BON** : Détecter cycles avant l'exécution
```python
# Valider AVANT exécution
if detect_cycle(workflow.graph):
    raise StructuralError("Cycle détecté dans graphe")

# Puis exécuter (acyclique garanti)
execute_workflow(workflow)
```

**Pourquoi** : La détection runtime gaspille ressources, peut ne pas détecter tous cycles, limite d'itération peu claire.

---

#### Validation Partielle de Graphe

❌ **MAUVAIS** : Valider seulement nœuds sélectionnés
```javascript
// Vérifier seulement nœuds modifiés par utilisateur
for (const node of selectedNodes) {
    if (hasCycle(node)) {
        reject();
    }
}
```

✅ **BON** : Valider graphe complet
```javascript
// Valider graphe workflow complet
if (hasCycle(workflow.allNodes)) {
    reject("Cycle détecté dans workflow");
}
```

**Pourquoi** : Le cycle peut exister dans sous-graphe non-sélectionné.

---

#### Ignorer Erreurs Détection Cycles

❌ **MAUVAIS** : Continuer exécution malgré cycle détecté
```php
try {
    detectCycles($graph);
} catch (CycleException $e) {
    Log::warning("Cycle détecté, on continue quand même");
    // Continuer exécution
}
```

✅ **BON** : Fail-fast sur détection cycle
```php
if (detectCycles($graph)) {
    throw new StructuralError("Cycle détecté : impossible d'exécuter workflow");
}
```

**Pourquoi** : Les cycles rendent l'exécution non-déterministe et potentiellement infinie.

---

### 8.2 Considérations de Performance

- Détection de cycles sur 1 000 nœuds se termine typiquement en **<10ms**
- Grands graphes (10 000+ nœuds) peuvent requérir **50-100ms**
- Algorithmes dépassant ces limites PEUVENT indiquer implémentation incorrecte

---

## 9. Conformité

Un système est **conforme NORP-004** si :
- Il rejette tous graphes cycliques avant compilation/exécution
- La détection de cycles opère en temps **O(V + E)**
- Il produit diagnostics déterministes (même workflow = même erreur)
- Il réussit tous les tests de conformité obligatoires

### 9.1 Suite de Tests de Conformité

**Test 1 : Rejet Cycle Simple**
- **Entrée** : Workflow avec cycle `A → B → A`
- **Attendu** : Rejet avec `STRUCTURAL_ERROR`
- **Diagnostic** : Chemin cycle `A→B→A`

**Test 2 : Rejet Cycle Multi-Nœuds**
- **Entrée** : Workflow avec cycle `A → B → C → A`
- **Attendu** : Rejet avec `STRUCTURAL_ERROR`
- **Diagnostic** : Chemin cycle `A→B→C→A`

**Test 3 : Rejet Auto-Boucle**
- **Entrée** : Workflow avec auto-boucle `A → A`
- **Attendu** : Rejet avec `STRUCTURAL_ERROR`
- **Diagnostic** : Auto-boucle détectée sur nœud A

**Test 4 : Sous-Graphe Déconnecté avec Cycle**
- **Entrée** : DAG valide (A→B) + cycle dans sous-graphe déconnecté (C→D→C)
- **Attendu** : Rejet avec `STRUCTURAL_ERROR`
- **Diagnostic** : Cycle détecté dans sous-graphe `C→D→C`
- **Justification** : Prouve que graphe entier validé, pas seulement composantes connexes

**Test 5 : DAG Valide (Test Négatif)**
- **Entrée** : Workflow sans cycles `A → B → C`
- **Attendu** : Validation **réussit** (pas d'erreur)
- **Justification** : Prouve absence de faux positifs

Spécifications de tests complètes disponibles dans `compliance-tests/NORP-004-tests.md`.

---

## 10. Résumé de la Justification

**Principe Fondamental** : Un workflow avec dépendances cycliques ne peut être exécuté de façon déterministe et DOIT être rejeté.

Cet invariant est fondamental pour une orchestration sûre et prévisible.

Ce principe s'applique indépendamment de la complexité d'orchestration, du langage de programmation, ou de l'infrastructure.

---

## 11. Extensions Futures

Les spécifications NORP futures PEUVENT définir :
- Constructions de boucle explicites avec sémantique bornée (NORP-011)
- Primitives d'exécution itérative avec garanties de terminaison formelles
- Nœuds de répétition déclaratifs avec garanties sans cycle
- Cycles conditionnels avec conditions de sortie explicites

---

## 12. Références

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) : Mots-clés pour l'usage dans les RFC pour indiquer les niveaux d'exigence
- Cormen, T. H., et al. (2009). *Introduction to Algorithms* (3ème éd.). MIT Press. (Chapitre 22 : Algorithmes de Graphe Élémentaires)
- Théorie des Graphes Acycliques Dirigés (DAG)
- Algorithme de Parcours en Profondeur (DFS)
- Algorithmes de tri topologique (Kahn, parcours DFS post-ordre)

---

## 13. Remerciements

Cette spécification est dérivée de l'implémentation de détection de cycles dans NeuraScope BlueprintValidator (testé en production sur 10 000+ workflows).

Les auteurs remercient les relecteurs pour leurs retours sur les exigences algorithmiques et formats de diagnostic.

---

## Annexe A : Exemples de Workflows

### A.1 Cycle Simple (INVALIDE)

```json
{
  "name": "Workflow Invalide - Cycle Simple",
  "nodes": [
    {
      "id": "fetch_data",
      "type": "datasource",
      "depends_on": ["process_data"]
    },
    {
      "id": "process_data",
      "type": "llm_call",
      "depends_on": ["fetch_data"]
    }
  ]
}
```

**Diagnostic** :
```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "message": "Cycle détecté dans graphe workflow",
  "cycle_path": ["fetch_data", "process_data", "fetch_data"]
}
```

---

### A.2 Cycle Multi-Nœuds (INVALIDE)

```json
{
  "name": "Workflow Invalide - Cycle Trois Nœuds",
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": ["C"]},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]},
    {"id": "C", "type": "transform", "depends_on": ["B"]}
  ]
}
```

**Diagnostic** :
```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "cycle_path": ["A", "B", "C", "A"]
}
```

---

### A.3 DAG Valide (VALIDE)

```json
{
  "name": "Workflow Valide - DAG",
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": []},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]},
    {"id": "C", "type": "transform", "depends_on": ["A"]},
    {"id": "D", "type": "output", "depends_on": ["B", "C"]}
  ]
}
```

**Validation** : ✅ Aucun cycle détecté

**Ordres d'exécution valides** :
- `[A, B, C, D]`
- `[A, C, B, D]`

Les deux ordres respectent les dépendances.

---

## Annexe B : Historique des Révisions

| Version | Date | Changements |
|---------|------|-------------|
| 1.2 | 2026-01-09 | Statut mis à jour vers Stable. Ajout pseudocode algorithme DFS (5.3.1), alternative Kahn (5.3.2), format diagnostic (5.4.1), complétude diagnostic (5.4.2), anti-patterns (8.1), tests conformité (9.1), exemples workflows (Annexe A), liaison NORP-001 (3.1), clarification boucles vs cycles (3.2). |
| 1.0 | 2026-01-07 | Brouillon initial. |

---

## Citation

```bibtex
@techreport{norp004-2026,
  title={{NORP-004: Détection de Cycles et Validité de Graphe pour Systèmes d'Orchestration IA}},
  author={{Groupe de Travail NORP}},
  institution={NeuraScope},
  year={2026},
  month={Janvier},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/fr/NORP-004},
  license={CC BY 4.0}
}
```

---

**NORP-004 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Sous licence CC BY 4.0**
