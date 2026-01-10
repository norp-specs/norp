# NORP-003
## État d'Exécution Immutable et Transfert d'État Déterministe

---

**Licence**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (À attribuer)

---

### Statut
Stable

### Catégorie
Sémantique d'Exécution

### Version
1.2

### Date
2026-01-09

### Auteurs
Groupe de Travail NORP

---

## 1. Résumé

Cette spécification définit des règles obligatoires pour le transfert d'état d'exécution immutable dans les pipelines d'orchestration IA.

Elle assure que chaque étape de pipeline produit un nouvel état d'exécution immutable, qu'aucun état caché ou global n'influence l'exécution, et que les échecs préservent les états valides antérieurs.

Cette spécification adresse deux préoccupations orthogonales :
1. **Immutabilité** : L'état ne peut être muté après production
2. **Déterminisme** : Une entrée identique produit une sortie identique

Les deux sont requis pour la conformité NORP-003.

L'objectif est de garantir déterminisme, auditabilité, déboguabilité, et exécution sûre à travers les workflows IA complexes.

---

## 2. Motivation

Les systèmes d'orchestration IA échouent fréquemment à cause de :
- Mutations d'état cachées
- Contexte partagé implicite
- Effets de bord d'exécution partielle
- Échecs non-reproductibles

Un état d'exécution mutable conduit à un comportement non-déterministe, des bugs irreproductibles, et des angles morts de sécurité.

**L'état d'exécution DOIT être traité comme un enregistrement historique, pas un espace de travail mutable.**

---

## 3. Périmètre

Cette spécification s'applique aux systèmes qui exécutent des workflows utilisant plusieurs étapes de pipeline.

Elle définit comment l'état d'exécution DOIT être produit, transféré, conservé, et isolé entre les étapes.

### 3.1 Relation avec NORP-001

Cette spécification est **complémentaire à NORP-001** (Pipeline de Validation Pré-Exécution).

- **NORP-001** définit **quelles étapes s'exécutent** et dans **quel ordre**
- **NORP-003** définit **comment l'état est transféré** entre ces étapes

Un système pleinement conforme DEVRAIT implémenter les deux :
- NORP-001 pour la structure du pipeline
- NORP-003 pour le transfert d'état immutable

**Exemple de pipeline** (NORP-001 + NORP-003) :

```
[NORP-001 Étape 1] VALIDATION
  → [NORP-003] produit ValidationResult (DTO immutable)

[NORP-001 Étape 2] COMPILATION
  → [NORP-003] consomme ValidationResult
  → produit ExecutionPlan (DTO immutable)

[NORP-001 Étape 3] RÉSOLUTION DE CONTEXTE
  → [NORP-003] consomme ExecutionPlan
  → produit ExecutionContext (DTO immutable)

[NORP-001 Étape 4] EXÉCUTION
  → [NORP-003] consomme ExecutionContext
  → produit ExecutionResult (DTO immutable)
```

---

## 4. Terminologie

**État d'Exécution** : Une structure de données représentant la sortie complète d'une étape de pipeline.

**Étape** : Un pas de traitement déterministe qui consomme un état d'exécution et en produit un nouveau.

**Mutation** : Toute opération qui modifie un état d'exécution existant ou l'une de ses propriétés imbriquées.

**DTO** (Data Transfer Object / Objet de Transfert de Données) : Une structure immutable utilisée pour transférer l'état d'exécution entre étapes.

Les mots-clés DOIT, DEVRAIT et PEUT sont à interpréter selon la [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Exigences Normatives

### 5.1 Production d'État Immutable

Chaque étape de pipeline DOIT produire un **nouvel état d'exécution**.

Les états d'exécution précédemment produits NE DOIVENT PAS être mutés.

L'état d'exécution DOIT être traité comme **immutable** une fois produit.

#### 5.1.1 Définition d'Immutabilité

Un **"nouvel état d'exécution"** signifie :
- Un **objet distinct en mémoire** (pas une référence à un état existant)
- **Aucune référence mutable partagée** avec les états précédents
- **Toutes les propriétés imbriquées sont immutables** (immutabilité profonde)

**La copie superficielle N'EST PAS suffisante** pour la conformité NORP-003.

##### Exemples

**Mutation** (INTERDITE) :

```javascript
// ❌ MAUVAIS : Modifier l'état existant
state.validated = true;
state.context.resources.push(newResource);
```

**Immutabilité** (REQUISE) :

```javascript
// ✅ BON : Produire un nouvel état
const newState = {
  ...previousState,
  validated: true,
  context: {
    ...previousState.context,
    resources: [...previousState.context.resources, newResource]
  }
};
```

##### Mécanismes spécifiques aux langages

- **JavaScript/TypeScript** : `Object.freeze()`, types `readonly`, opérateur spread (`...`)
- **PHP 8.2+** : propriétés `readonly`
- **Python** : `dataclasses(frozen=True)`, `NamedTuple`
- **Rust** : Immutabilité par défaut
- **Java** : champs `final`, collections immutables

Les systèmes DOIVENT documenter quel mécanisme d'immutabilité ils utilisent.

---

### 5.2 Frontières d'État Explicites

L'état d'exécution passé entre étapes DOIT être **explicite**.

Le partage d'état implicite via :
- Variables globales
- Singletons
- Mémoire partagée
- Conteneurs de contexte cachés

est **NON PERMIS**.

Chaque étape DOIT déclarer :
- Son **état d'entrée** requis
- Son **état de sortie** produit

---

### 5.3 Isolation des Étapes

Les étapes de pipeline NE DOIVENT PAS :
- Modifier l'état produit par les étapes précédentes
- Dépendre d'effets de bord non documentés
- Accéder aux sorties d'étapes futures

Les étapes DOIVENT opérer **uniquement** sur leur état d'entrée déclaré.

---

### 5.4 Production d'État Déterministe

Étant donné un **état d'entrée identique**, une étape de pipeline DOIT produire un **état de sortie identique**.

#### 5.4.1 Portée du Déterminisme

**Les étapes de pipeline** (validation, compilation, résolution de contexte) DOIVENT être **déterministes**.

**Les nœuds d'exécution** (appels LLM, APIs externes) PEUVENT être **non-déterministes**, à condition que :
- Le non-déterminisme soit **contenu dans l'exécution du nœud**
- Le **flux de contrôle** du pipeline reste déterministe
- La **structure de l'état** d'exécution reste déterministe (même si les valeurs varient)

**Exemple** :

```json
{
  "node_id": "llm_1",
  "output": {
    "text": "...",  // ← Valeur peut varier (LLM non-déterministe)
    "tokens": 123   // ← Structure toujours identique (déterministe)
  }
}
```

**La structure DOIT être stable** même si le texte de sortie LLM varie entre exécutions.

---

### 5.5 Sémantique d'Échec

Si une étape de pipeline échoue :
- **Aucune étape subséquente** NE PEUT s'exécuter
- **Aucune mutation partielle** de l'état antérieur n'est permise
- Le **dernier état immutable valide** DOIT rester intact

#### 5.5.1 Rétention d'État en Cas d'Échec

Le **contexte appelant** (ex: orchestrateur) DOIT conserver le dernier état d'exécution valide produit par l'étape précédente.

L'étape échouante NE DOIT PAS produire d'état partiel.

L'état des étapes réussies DOIT être **disponible pour rapporter l'erreur**.

**Exemple** :

```php
try {
    $validationResult = $validator->validate($workflow); // ✅ OK
    $executionPlan = $compiler->compile($validationResult); // ❌ Échoue
} catch (CompilerException $e) {
    // $validationResult toujours accessible ici
    log("Compilation échouée après validation réussie", [
        'validation' => $validationResult->toArray(),
        'error' => $e->getMessage()
    ]);
}
```

Des mécanismes de rollback d'état PEUVENT être implémentés mais NE DOIVENT PAS reposer sur un état mutable.

---

### 5.6 Sérialisation et Instantanés

L'état d'exécution DOIT être **sérialisable** OU fournir un **mécanisme d'instantané équivalent**.

La sérialisation permet :
- **Débogage** (inspection d'état)
- **Audit** (pistes de conformité)
- **Rejeu** (tests déterministes)
- **Points de contrôle** (récupération d'échec)

#### 5.6.1 Objets Non-Sérialisables

Un état contenant des ressources non-sérialisables (ex: connexions base de données, handles fichiers) DOIT fournir un instantané qui :
- Capture les **métadonnées de ressource** (chaîne de connexion, référence credentials)
- **Omet l'objet vivant** (handle de connexion, descripteur fichier)
- Permet la **reconstruction d'état** depuis les métadonnées

**Exemple** :

```json
// ❌ NON autorisé : Sérialiser objet connexion PDO
{
  "datasource": {
    "id": 5,
    "connection_handle": "[PDO Object]"
  }
}

// ✅ Autorisé : Sérialiser métadonnées uniquement
{
  "datasource": {
    "id": 5,
    "type": "mysql",
    "host": "db.example.com",
    "database": "prod_db"
  }
}
```

---

## 6. Comportement Fail-Safe

Les erreurs de validation d'état DOIVENT être détectées à l'étape la plus précoce possible.

L'exécution NE DOIT PAS procéder si l'état requis est manquant, malformé, ou incohérent.

Si l'immutabilité, l'isolation, ou le déterminisme ne peuvent être imposés, l'exécution DOIT être empêchée.

---

## 7. Considérations de Sécurité

Un état d'exécution mutable introduit des vecteurs d'attaque incluant :
- **Empoisonnement d'état** (modifier l'état pour escalader les privilèges)
- **Manipulation de rejeu** (altérer l'état historique)
- **Chemins d'exécution non-auditables** (effets de bord invisibles dans l'état)

L'état d'exécution DOIT être traité comme **non fiable** jusqu'à validation.

L'immutabilité limite le rayon d'explosion des échecs et impose une propriété claire des données d'exécution.

---

## 8. Guide d'Implémentation (Non-Normatif)

### 8.1 Anti-Patterns Courants

#### DTOs Mutables

❌ **MAUVAIS** :
```typescript
class ValidationResult {
  valid: boolean;
  errors: string[] = [];

  addError(msg: string) { // ← Méthode de mutation
    this.errors.push(msg);
  }
}
```

✅ **BON** :
```typescript
class ValidationResult {
  readonly valid: boolean;
  readonly errors: ReadonlyArray<string>;

  constructor(valid: boolean, errors: string[]) {
    this.valid = valid;
    this.errors = Object.freeze(errors);
  }
}
```

---

#### État Partagé Caché

❌ **MAUVAIS** :
```python
class Pipeline:
    _shared_state = {}  # Variable de classe = état mutable partagé

    def execute(self, input):
        self._shared_state['result'] = compute(input)
```

✅ **BON** :
```python
from dataclasses import dataclass

@dataclass(frozen=True)
class ExecutionState:
    result: Any

class Pipeline:
    def execute(self, input: ExecutionState) -> ExecutionState:
        return ExecutionState(result=compute(input))
```

---

#### État Passé par Référence

❌ **MAUVAIS** :
```php
function validate(array &$state): void {
    $state['validated'] = true; // Mutation via référence
}
```

✅ **BON** :
```php
function validate(array $state): ValidationResult {
    return new ValidationResult(
        validated: true,
        original: $state
    );
}
```

---

#### Copie Superficielle

❌ **MAUVAIS** :
```javascript
// Copie superficielle partage objets imbriqués
const newState = {...oldState};
newState.context.resources.push(item); // ← Mute oldState.context!
```

✅ **BON** :
```javascript
// Immutabilité profonde
const newState = {
  ...oldState,
  context: {
    ...oldState.context,
    resources: [...oldState.context.resources, item]
  }
};
```

---

#### Interface TypeScript Mutable

❌ **MAUVAIS** :
```typescript
interface ValidationResult {
  valid: boolean;
  errors: string[];
}
// Mutable par défaut
```

✅ **BON** :
```typescript
interface ValidationResult {
  readonly valid: boolean;
  readonly errors: ReadonlyArray<string>;
}
```

---

#### Mutabilité Inutile en Rust

❌ **MAUVAIS** :
```rust
struct ExecutionState {
    validated: bool,
    errors: Vec<String>
}
// Explicitement mutable
let mut state = ExecutionState { ... };
```

✅ **BON** :
```rust
#[derive(Clone)]
struct ExecutionState {
    validated: bool,
    errors: Vec<String>
}
// Immutable par défaut, cloner si nécessaire
let new_state = ExecutionState { ... };
```

---

### 8.2 Checklist de Revue de Code

Lors de la revue de code pour la conformité NORP-003 :

- [ ] Les DTOs utilisent propriétés `readonly` / `frozen` / `final`
- [ ] Pas de méthodes setter sur objets d'état d'exécution
- [ ] État passé par valeur ou référence immutable
- [ ] Pas de variables globales stockant l'état d'exécution
- [ ] Pas de singletons détenant un contexte d'exécution mutable
- [ ] Méthodes de sérialisation existent pour tous objets d'état
- [ ] Fonctions d'étape sont pures (pas d'effets de bord sur état d'entrée)
- [ ] Les échecs préservent l'état antérieur (testé)
- [ ] Immutabilité profonde imposée (objets imbriqués aussi immutables)

---

## 9. Conformité

Un système est **conforme NORP-003** si :
- Tout état d'exécution est immutable (immutabilité profonde)
- Tout état est passé explicitement entre étapes
- Aucun état global caché n'influence l'exécution
- Tous les tests de conformité obligatoires réussissent

### 9.1 Suite de Tests de Conformité

**Test 1 : Immutabilité de l'État**
- **Configuration** : Exécuter étape S → produit ExecutionState state1
- **Action** : Tenter de muter propriété state1
- **Attendu** : Mutation échoue (erreur runtime) OU mutation n'a aucun effet sur state1
- **Justification** : Prouve que l'état est vraiment immutable

**Test 2 : Pas de Dépendance État Global**
- **Configuration** : Exécuter workflow avec entrée I1 → produit sortie O1
- **Action** : Modifier variable globale ou état singleton
- **Action** : Exécuter même workflow avec même entrée I1 → produit sortie O2
- **Attendu** : O1 == O2 (prouve absence dépendance globale cachée)
- **Justification** : Assure que l'exécution est autonome

**Test 3 : Isolation des Étapes**
- **Configuration** : Étape A produit ExecutionState stateA
- **Action** : Étape B consomme stateA
- **Action** : Étape B tente de modifier stateA
- **Attendu** : stateA reste inchangé (modification échoue ou ignorée)
- **Justification** : Prouve que les étapes ne peuvent muter les états antérieurs

**Test 4 : L'Échec Préserve l'État Antérieur**
- **Configuration** : Étape A produit ExecutionState valide stateA
- **Action** : Étape B consomme stateA et échoue
- **Attendu** : stateA reste intact et accessible pour rapporter l'erreur
- **Justification** : Les étapes échouées ne corrompent pas les états réussis

Spécifications de tests complètes disponibles dans `compliance-tests/NORP-003-tests.md`.

---

### 9.2 Tests Optionnels (Recommandés)

**Test 5 : Immutabilité Profonde**
- **Configuration** : Créer ExecutionState avec objets imbriqués
- **Action** : Tenter de modifier propriété profondément imbriquée
- **Attendu** : Modification échoue ou n'a aucun effet sur l'original
- **Justification** : Détection copie superficielle

**Test 6 : Aller-Retour Sérialisation**
- **Configuration** : Étape produit ExecutionState S1
- **Action** : Sérialiser S1 → JSON, puis désérialiser → S2
- **Attendu** : S1 == S2 (structure et valeurs préservées)
- **Justification** : Assure auditabilité et capacité de rejeu

---

## 10. Considérations de Sécurité

Un état mutable ou caché introduit des vecteurs d'attaque tels que :
- **Empoisonnement d'état** (modifier l'état pour escalader privilèges)
- **Manipulation de rejeu** (altérer l'état historique pour contournement)
- **Chemins d'exécution non-auditables** (effets de bord invisibles dans instantanés d'état)

L'état d'exécution DOIT être traité comme **non fiable** jusqu'à validation.

L'immutabilité limite le rayon d'explosion des échecs et impose une propriété claire des données d'exécution.

---

## 11. Résumé de la Justification

**Principe Fondamental** : L'état d'exécution est un enregistrement historique, pas un espace de travail mutable.

L'immutabilité est la seule fondation fiable pour déterminisme, auditabilité, et orchestration sûre.

Ce principe s'applique indépendamment de la complexité d'orchestration, du langage de programmation, ou de l'infrastructure.

---

## 12. Extensions Futures

Les spécifications NORP futures PEUVENT adresser :
- Propagation d'état distribuée
- Rejeu d'exécution partielle avec points de contrôle
- Stratégies de rollback déterministes
- Versionnage d'état et suivi de lignage
- Intégration event sourcing

---

## 13. Références

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) : Mots-clés pour l'usage dans les RFC pour indiquer les niveaux d'exigence
- Principes de programmation fonctionnelle (fonctions pures, immutabilité)
- Conception de systèmes déterministes
- Architecture Redux (patterns d'immutabilité d'état)
- Patterns Event Sourcing

---

## 14. Remerciements

Cette spécification est dérivée des patterns d'exécution en production chez NeuraScope, incluant le Blueprint Runtime Engine et l'architecture de transfert d'état basée sur DTOs.

Les auteurs remercient les relecteurs pour leurs retours sur les mécanismes d'imposition d'immutabilité.

---

## Annexe A : Historique des Révisions

| Version | Date | Changements |
|---------|------|-------------|
| 1.2 | 2026-01-09 | Statut mis à jour vers Stable. Ajout définition immutabilité (5.1.1), portée déterminisme (5.4.1), rétention état échec (5.5.1), guidance sérialisation (5.6.1), anti-patterns (8.1), checklist revue code (8.2), tests conformité (9.1), liaison NORP-001 (3.1). |
| 1.0 | 2026-01-07 | Brouillon initial. |

---

## Annexe B : DTOs de Référence (Non-Normatif)

### ValidationResult (PHP)

```php
readonly class ValidationResult {
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly float $estimated_cost,
    ) {}

    public function toArray(): array {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'estimated_cost' => $this->estimated_cost,
        ];
    }
}
```

---

### ExecutionPlan (TypeScript)

```typescript
interface ExecutionPlan {
  readonly nodes: ReadonlyArray<Node>;
  readonly execution_order: ReadonlyArray<string>;
  readonly parallel_groups: ReadonlyArray<ParallelGroup>;
  readonly estimated_duration_ms: number;
}

// Usage
const plan: ExecutionPlan = {
  nodes: Object.freeze([...]),
  execution_order: Object.freeze(['A', 'B', 'C']),
  parallel_groups: Object.freeze([...]),
  estimated_duration_ms: 1500
};
```

---

### ExecutionContext (Python)

```python
from dataclasses import dataclass
from typing import Dict, Any

@dataclass(frozen=True)
class ExecutionContext:
    tenant_id: str
    blueprint_id: str
    execution_id: str
    inputs: Dict[str, Any]
    variables: Dict[str, Any]
    started_at: str

    def to_dict(self) -> dict:
        return {
            'tenant_id': self.tenant_id,
            'blueprint_id': self.blueprint_id,
            'execution_id': self.execution_id,
            'inputs': self.inputs,
            'variables': self.variables,
            'started_at': self.started_at
        }
```

---

## Citation

```bibtex
@techreport{norp003-2026,
  title={{NORP-003: État d'Exécution Immutable et Transfert d'État Déterministe}},
  author={{Groupe de Travail NORP}},
  institution={NeuraScope},
  year={2026},
  month={Janvier},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/fr/NORP-003},
  license={CC BY 4.0}
}
```

---

**NORP-003 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Sous licence CC BY 4.0**
