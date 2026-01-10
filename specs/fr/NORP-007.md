# NORP-007
## Estimation des Coûts et Application du Budget d'Exécution

---

**Licence**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (À attribuer)

---

### Statut
Stable

### Catégorie
Gouvernance des Coûts

### Version
1.2

### Date
2026-01-09

### Auteurs
Groupe de Travail NORP

---

## 1. Résumé

Cette spécification définit des exigences obligatoires pour **l'estimation des coûts, l'application du budget, et l'observabilité des coûts** dans les systèmes d'orchestration IA.

Elle assure que le coût d'exécution est :
- Estimé de façon déterministe avant l'exécution
- Appliqué contre des budgets explicites
- Mesuré et rapporté après l'exécution

L'objectif est d'empêcher les dépenses incontrôlées, permettre la gouvernance, et assurer la prévisibilité des coûts systèmes IA.

---

## 2. Motivation

Les workflows IA impliquent souvent des opérations variables et potentiellement coûteuses :
- Inférence LLM (coûts tokens variables)
- Appels API externes (facturation mesurée)
- Requêtes base de données vectorielle (coûts calcul)
- Invocations outils facturées par requête

Sans contrôles coûts explicites, les systèmes peuvent souffrir de :
- **Dépassements budget** (factures inattendues $10 000+)
- **Facturation imprévisible** (workflows avec coûts variables)
- **Abus ou attaques déni de portefeuille** (workflows malveillants haute coût)
- **Manque d'auditabilité** (impossible d'attribuer coûts aux exécutions)

L'estimation et application des coûts sont donc **invariants d'exécution de première classe**.

---

## 3. Périmètre

Cette spécification s'applique aux systèmes qui :
- Exécutent workflows avec opérations facturables
- Peuvent estimer coût avant exécution
- Appliquent budgets d'exécution

### 3.1 Relation avec NORP-001

- **NORP-001** définit validation exécution et vérifications pré-exécution
- **NORP-007** étend NORP-001 en ajoutant **validation coût** comme phase obligatoire

L'estimation coût DOIT survenir :
- Après validation structurelle (NORP-004)
- Après résolution ordonnancement (NORP-005)
- **Avant démarrage exécution** (comme partie étape Résolution Contexte)

**Intégration avec pipeline NORP-001** :
```
NORP-001 Étape 1: Validation Structurelle
NORP-001 Étape 2: Compilation
NORP-001 Étape 3: Résolution Contexte
  → [NORP-007] Estimation Coût ✅
  → [NORP-007] Application Budget ✅
NORP-001 Étape 4: Exécution (seulement si budget OK)
```

---

## 4. Terminologie

**Coût Estimé** : Coût monétaire projeté calculé avant début exécution.

**Coût Réel** : Coût monétaire mesuré encouru durant exécution.

**Budget** : Coût maximum autorisé pour périmètre défini (exécution, workflow, tenant, période).

**Opération Facturable** : Toute opération avec coût monétaire (appel API LLM, requête API payante, calcul mesuré).

**Unité de Facturation** : L'unité utilisée pour tarification (ex: par 1 000 tokens, par requête, par heure calcul).

Les mots-clés DOIT, DEVRAIT et PEUT sont à interpréter selon la [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Exigences Normatives

### 5.1 Estimation des Coûts

Les systèmes DOIVENT calculer estimation coût avant exécution.

L'estimation coût DOIT :
- Inclure **toutes opérations facturables** dans workflow
- Être **déterministe** pour workflows identiques
- Retourner **valeur numérique > 0** lorsque opérations facturables existent
- Être calculée **avant début exécution** (fail-fast si budget dépassé)

---

#### 5.1.1 Comptage Tokens

Pour appels LLM, les systèmes DOIVENT estimer usage tokens avant exécution.

Si un **tokenizer natif** pour modèle cible est disponible (ex: `tiktoken`, `sentencepiece`), les systèmes DEVRAIENT utiliser comptages tokens exacts.

Si aucun tokenizer natif disponible, les systèmes DOIVENT utiliser **approximation conservative**.

**Approximations recommandées** :

- Pour texte **principalement anglais** :
  ```
  estimated_tokens = caractères / 4
  ```

- Pour texte **multilingue ou mixte** :
  ```
  estimated_tokens = caractères × 0.3
  ```

Ces approximations sont basées sur moyennes empiriques observées à travers tokenizers LLM modernes (GPT, Claude, Llama).

**Exemple** :
```
Prompt: "Explain quantum computing"
Nombre caractères: 25
Tokens estimés: 25 / 4 ≈ 6 tokens
```

Les systèmes DOIVENT documenter quelle méthode estimation est utilisée (tokenizer natif vs approximation).

---

#### 5.1.2 Modèle de Tarification

Les systèmes DOIVENT associer chaque opération facturable avec **modèle de tarification**.

La tarification DOIT spécifier :
- **Prix unitaire entrée** (ex: $0.010 par 1K tokens entrée)
- **Prix unitaire sortie** (ex: $0.030 par 1K tokens sortie)
- **Unité facturation** (ex: par 1 000 tokens, par requête API)

La tarification DEVRAIT être :
- Récupérée depuis APIs fournisseur si disponible (tarification dynamique)
- Mise à jour trimestriellement minimum (si codée en dur)
- Documentée avec date dernière mise à jour

---

#### 5.1.3 Formule de Coût

Pour appels LLM, le coût estimé DOIT être calculé comme :

```
coût_estimé =
  (tokens_entrée / unité_facturation × prix_entrée) +
  (tokens_sortie / unité_facturation × prix_sortie)
```

**Exemple** :
```
Modèle: GPT-4 Turbo
Tarification: $0.010 par 1K entrée, $0.030 par 1K sortie
Unité facturation: 1 000 tokens

Prompt: 1 000 tokens (entrée)
Sortie max: 500 tokens

Calcul coût:
  Coût entrée:  (1000 / 1000) × $0.010 = $0.010
  Coût sortie:  (500 / 1000) × $0.030  = $0.015
  Total:        $0.010 + $0.015        = $0.025
```

**Coût total workflow** est la somme de toutes estimations nœuds.

---

#### 5.1.4 Estimation Conservative

L'estimation coût DEVRAIT être **conservative** (préférer surestimation à sous-estimation).

**Estimation conservative signifie** :
- Inclure marge sécurité pour tenir compte de :
  - Variation comptage tokens (+10-20%)
  - Changements tarification API
  - Tentatives retry (échecs transitoires)

**Marge recommandée** : **20% à 50%** au-dessus coût minimum attendu.

**Exemple** :
```
Coût minimum attendu: $1.00
Estimation conservative: $1.20 (marge 20%) à $1.50 (marge 50%)
```

Les systèmes DEVRAIENT documenter leur pourcentage marge sécurité.

---

### 5.2 Définition du Budget

Les budgets PEUVENT être définis à **niveaux multiples** :

- **Budget par exécution** : Coût maximum pour exécution workflow unique
- **Budget par workflow** : Coût maximum par définition workflow (cumulatif)
- **Budget par tenant** : Coût maximum par tenant par période (quotidien, mensuel)

Les systèmes DOIVENT documenter quels niveaux budget sont supportés.

---

#### 5.2.1 Exemples Périmètre Budget

**Exemple 1 : Budget Par Exécution**
```json
{
  "workflow_id": "wf_123",
  "budget": {
    "type": "per_execution",
    "limit_usd": 10.00
  }
}
```

Si coût estimé > $10 → Rejeter exécution

---

**Exemple 2 : Budget Quotidien Par Tenant**
```json
{
  "tenant_id": "acme",
  "budget": {
    "type": "daily",
    "limit_usd": 1000.00,
    "spent_today_usd": 850.00
  }
}
```

Si (dépensé_aujourd'hui + coût_estimé) > $1000 → Rejeter exécution

---

**Exemple 3 : Budget Cumulatif Par Workflow**
```json
{
  "workflow_id": "wf_123",
  "budget": {
    "type": "cumulative",
    "limit_usd": 5000.00,
    "total_spent_usd": 4700.00
  }
}
```

Si (total_dépensé + coût_estimé) > $5000 → Rejeter ou avertir

---

#### 5.2.2 Timing Application Budget

L'application budget DOIT survenir :

**1. Pré-exécution** (OBLIGATOIRE) :
- Coût estimé DOIT être comparé au budget applicable
- Exécution DOIT être bloquée si budget dépassé, sauf si **surcharge explicite** fournie (confirmation utilisateur)

L'application budget DEVRAIT survenir :

**2. Durant exécution** (RECOMMANDÉ) :
- Systèmes DEVRAIENT suivre coût réel incrémentalement
- Systèmes DEVRAIENT appliquer budget au runtime

Les systèmes supportant **application runtime** DOIVENT :
- Suivre coût réel par opération facturable
- **Avorter exécution** lorsque budget dépassé en milieu exécution
- Émettre **événement violation budget** pour observabilité et audit

Les systèmes qui NE supportent PAS application runtime DOIVENT clairement **documenter cette limitation**.

**Application pré-exécution** (pseudocode) :

```python
if estimated_cost > budget:
    if not user_confirmed:
        raise BudgetExceededError(
            f"Coût estimé ${estimated_cost:.2f} dépasse budget ${budget:.2f}"
        )
```

**Application runtime** (optionnel, pseudocode) :

```python
accumulated_cost = 0

for node in workflow.nodes:
    result = execute_node(node)
    accumulated_cost += result.cost

    if accumulated_cost > budget:
        abort_execution()
        raise BudgetExceededError(
            f"Coût réel ${accumulated_cost:.2f} a dépassé budget ${budget:.2f} durant exécution"
        )
```

---

### 5.3 Transparence Coût et Observabilité

Les systèmes DOIVENT exposer :
- **Coût estimé** avant exécution (retourné durant validation)
- **Coût réel** après exécution (journalisé et retourné)
- **Seuil budget** utilisé (quel niveau budget appliqué)
- **Décision application** (autorisé, bloqué, surchargé avec confirmation utilisateur)

Les diagnostics coût DOIVENT être **lisibles par machine** (format structuré).

**Exemple diagnostic** :

```json
{
  "execution_id": "exec_abc123",
  "estimated_cost_usd": 2.50,
  "budget_usd": 10.00,
  "enforcement_decision": "ALLOWED",
  "breakdown": [
    {"node_id": "summarize", "model": "gpt-4", "estimated_cost": 2.00},
    {"node_id": "classify", "model": "claude-haiku", "estimated_cost": 0.50}
  ]
}
```

---

### 5.4 Suivi Coût Réel

Les systèmes DEVRAIENT suivre **coût réel** durant exécution.

Le suivi coût réel permet :
- **Validation estimations** (comparaison estimé vs réel)
- **Raffinement modèles estimation** (améliorer précision au fil temps)
- **Détection anomalies** (réel >> estimé = problème potentiel)

Déviation significative depuis estimations (>50%) DEVRAIT être journalisée comme avertissement.

---

## 6. Considérations de Sécurité

Le manque d'application coût peut permettre :
- **Attaques déni de portefeuille** (workflows malveillants conçus pour maximiser coûts)
- **Abus APIs payantes** (usage non autorisé augmentant factures)
- **Consommation ressources illimitée** (workflows incontrôlés)

La gouvernance coût est donc **exigence sécurité**, pas seulement prudence financière.

Les implémenteurs DEVRAIENT assumer :
- Workflows peuvent être conçus pour maximiser coûts
- Tentatives contournement budget surviendront
- Estimation coût peut être contournée (opérations facturables non déclarées)

---

## 7. Résumé de la Justification

**Principe Fondamental** : La prévisibilité coût est obligatoire pour systèmes orchestration IA dignes confiance.

L'exécution sans estimation coût et application budget expose organisations à risque financier illimité.

Ce principe s'applique indépendamment de la complexité d'orchestration, du langage de programmation, ou de l'infrastructure.

---

## 8. Guide d'Implémentation (Non-Normatif)

### 8.1 Anti-Patterns Courants

#### Anti-Pattern 1 : Pas d'Estimation Coût

❌ **MAUVAIS** : Exécuter sans estimer coût
```python
# Exécuter directement sans vérification coût
execute_workflow(workflow)
```

✅ **BON** : Estimer et valider budget d'abord
```python
estimate = estimate_cost(workflow)
validate_budget(estimate, budget)
execute_workflow(workflow)
```

**Pourquoi** : Empêche factures surprises et dépassements budget.

---

#### Anti-Pattern 2 : Sous-Estimation (Pas Marge Sécurité)

❌ **MAUVAIS** : Utiliser coût minimum exact
```python
estimate = exact_minimum_cost(workflow)
```

✅ **BON** : Ajouter marge conservative
```python
exact_cost = calculate_cost(workflow)
estimate = exact_cost * 1.3  # Marge sécurité 30%
```

**Pourquoi** : Comptages tokens LLM varient, tarification API change, retries ajoutent coût.

---

#### Anti-Pattern 3 : Ignorer Budget au Runtime

❌ **MAUVAIS** : Continuer exécution malgré dépassement budget
```python
if actual_cost > budget:
    log.warning("Budget dépassé, on continue quand même")
    continue_execution()  # ❌ Ignore budget
```

✅ **BON** : Avorter exécution lorsque budget dépassé
```python
if actual_cost > budget:
    abort_execution()
    raise BudgetExceededError("Coût réel a dépassé budget durant exécution")
```

**Pourquoi** : Application runtime empêche coûts incontrôlés.

---

### 8.2 Implémentation Estimation Coût (Recommandée)

```python
def estimate_workflow_cost(workflow, pricing_table):
    """
    Estimer coût total workflow basé sur nœuds LLM.

    Retourne: Coût estimé en USD
    """
    total_cost = 0.0

    for node in workflow.nodes:
        if node.type == 'llm_call':
            # Obtenir tarification pour modèle
            pricing = pricing_table.get(node.config['model'])

            # Estimer tokens entrée
            prompt = node.config['prompt']
            input_tokens = len(prompt) / 4  # Approximation anglais

            # Tokens sortie max
            output_tokens = node.config.get('max_tokens', 1000)

            # Calculer coût
            input_cost = (input_tokens / 1000) * pricing['input']
            output_cost = (output_tokens / 1000) * pricing['output']

            node_cost = input_cost + output_cost
            total_cost += node_cost

    # Marge conservative (30%)
    conservative_estimate = total_cost * 1.3

    return round(conservative_estimate, 4)
```

---

## 9. Conformité

Un système est **conforme NORP-007** si :
- Coût est **estimé avant exécution** (exigence pré-exécution)
- Budgets sont **appliqués** (exécution bloquée si dépassé, sauf surcharge)
- **Diagnostics sont exposés** (coût estimé, budget, décision)
- Tous tests conformité obligatoires **réussissent**

### 9.1 Suite de Tests de Conformité

**Test 1 : Estimation Coût Pré-Exécution**

**Entrée** : Workflow avec 1 appel LLM (GPT-4, max_tokens=1000)

**Action** : Valider workflow (phase pré-exécution)

**Attendu** :
- Estimation coût retournée **avant exécution**
- Estimation > $0 (non-zéro pour opération facturable)
- Estimation inclut coûts tokens entrée et sortie

**Critères réussite** :
- ✅ Estimation calculée durant phase validation
- ✅ Estimation > 0
- ✅ Exécution N'A PAS démarré encore

**Justification** : Prouve estimation coût survient pré-exécution (conformité NORP-001).

---

**Test 2 : Application Budget - Rejet**

**Configuration** :
- Budget exécution = $1.00
- Workflow avec coût estimé = $5.00
- Confirmation utilisateur = false

**Action** : Tenter exécuter workflow

**Attendu** :
- Exécution **REJETÉE**
- Type erreur : `BUDGET_EXCEEDED`
- Message erreur inclut coût estimé et limite budget
- **Aucun appel LLM exécuté** (fail-fast)

**Critères réussite** :
- ✅ Exécution bloquée
- ✅ Type erreur = `BUDGET_EXCEEDED`
- ✅ Zéro opération facturable exécutée

**Justification** : Prouve application budget empêche exécutions hors-budget.

---

**Test 3 : Application Budget - Surcharge Utilisateur**

**Configuration** :
- Budget exécution = $1.00
- Workflow avec coût estimé = $5.00
- Confirmation utilisateur = **true** (surcharge explicite)

**Action** : Exécuter workflow

**Attendu** :
- Exécution **AUTORISÉE** (utilisateur confirmé)
- Avertissement journalisé dépassement budget
- Exécution procède normalement

**Critères réussite** :
- ✅ Exécution complète
- ✅ Surcharge journalisée
- ✅ Avertissement émis

**Justification** : Prouve utilisateur peut surcharger budget pour workflows légitimes haute coût.

---

**Test 4 : Suivi Coût Réel**

**Entrée** : Workflow avec 2 appels LLM (coûts connus)

**Action** : Exécuter workflow à complétion

**Attendu** :
- Coût réel calculé **post-exécution**
- Coût réel journalisé
- Coût réel ≈ coût estimé (dans plage raisonnable)

**Critères réussite** :
- ✅ Coût réel retourné après exécution
- ✅ Coût réel suivi par nœud
- ✅ Coût réel total = somme coûts nœuds

**Justification** : Prouve suivi coût réel fonctionne (pour audit et raffinement modèle).

---

**Test 5 : Validation Estimation Conservative**

**Objectif** : Vérifier que estimations coût sont conservatives (tendent à surestimer, pas sous-estimer).

**Configuration** : Exécuter 100 workflows avec appels LLM variés

**Action** :
- Pour chaque workflow : Comparer coût_estimé vs coût_réel
- Compter combien de fois : coût_estimé >= coût_réel

**Attendu** :
- **≥80% des exécutions** : coût_estimé >= coût_réel
- Prouve estimation est conservative (surestimation sûre)

**Critères réussite** :
- ✅ Dans au moins 80 sur 100 exécutions : estimé >= réel
- ✅ Erreur estimation moyenne : -10% à +50% (négatif = sous-estimation, positif = surestimation)

**Justification** : Validation statistique que modèle estimation est conservatif.

---

## 10. Considérations de Sécurité

Le manque contrôles coût permet :
- **Attaques déni de portefeuille** (attaquant soumet workflows haute coût pour drainer budget)
- **Amplification coûts** (workflow malveillant avec boucles déclenchant facture $10 000+)
- **Épuisement quota** (consommer budget mensuel tenant entier en minutes)

La gouvernance coût est donc **exigence sécurité**.

Les implémenteurs DEVRAIENT assumer :
- Workflows peuvent être conçus pour maximiser coûts
- Valeurs budget peuvent être manipulées
- Estimation peut être contournée si pas appliquée

---

## 11. Résumé de la Justification

**Principe Fondamental** : Exécution sans estimation coût et application budget expose organisations à risque financier illimité.

Prévisibilité coût, transparence, et application sont **non-négociables** pour systèmes IA production.

Ce principe s'applique indépendamment de la complexité d'orchestration, du langage de programmation, ou de l'infrastructure.

---

## 12. Extensions Futures

Les spécifications NORP futures PEUVENT définir :
- Support multi-devises et conversion
- Mises à jour tarification dynamiques depuis APIs fournisseur
- Suggestions optimisation coûts (ex: "Utiliser Claude Haiku au lieu GPT-4 pour économiser $2")
- Mécanismes allocation coûts et refacturation
- Gestion quota et limitation débit

---

## 13. Références

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) : Mots-clés pour l'usage dans les RFC pour indiquer les niveaux d'exigence
- Documentation Tarification OpenAI
- Tarification Anthropic Claude
- Meilleures pratiques gouvernance coût cloud
- Principes FinOps Foundation

---

## 14. Remerciements

Cette spécification est dérivée de l'implémentation estimation coût dans NeuraScope BlueprintValidator (testé en production sur 10 000+ workflows).

Les auteurs remercient les relecteurs pour retours sur modèles tarification et sémantique application budget.

---

## Annexe A : Exemple Workflow Estimation Coût

### Workflow Complet avec Décomposition Coût

```json
{
  "name": "Workflow Traitement Contenu",
  "nodes": [
    {
      "id": "summarize",
      "type": "llm_call",
      "config": {
        "model": "gpt-4-turbo",
        "prompt": "Résumer cet article: [1000 caractères]",
        "max_tokens": 500
      }
    },
    {
      "id": "classify",
      "type": "llm_call",
      "config": {
        "model": "claude-3-haiku",
        "prompt": "Classifier sentiment: [500 caractères]",
        "max_tokens": 200
      }
    }
  ]
}
```

### Calcul Coût

**Nœud 1 : "summarize" (GPT-4 Turbo)**
- Prompt: 1000 caractères → ~250 tokens (chars / 4)
- Sortie max: 500 tokens
- Tarification: Entrée $0.010/1K, Sortie $0.030/1K
- Coût:
  - Entrée: (250 / 1000) × $0.010 = $0.0025
  - Sortie: (500 / 1000) × $0.030 = $0.015
  - **Total Nœud 1**: $0.0175

**Nœud 2 : "classify" (Claude 3 Haiku)**
- Prompt: 500 caractères → ~125 tokens
- Sortie max: 200 tokens
- Tarification: Entrée $0.00025/1K, Sortie $0.00125/1K
- Coût:
  - Entrée: (125 / 1000) × $0.00025 = $0.00003125
  - Sortie: (200 / 1000) × $0.00125 = $0.00025
  - **Total Nœud 2**: $0.00028125

**Coût total estimé**: $0.0175 + $0.00028125 = **$0.01778125**

**Estimation conservative (marge 30%)**: $0.01778 × 1.3 = **$0.023** (~$0.03)

---

## Annexe B : Référence Tarification LLM (Non-Normatif)

**⚠️ La tarification est sujette à changement.**

Ce tableau reflète tarification publiquement disponible au **2026-01-09** (2026 Q1).

Les systèmes DEVRAIENT :
- Revoir tarification au moins **trimestriellement**
- Mettre à jour tarification lorsque fournisseurs publient changements
- Récupérer tarification **dynamiquement depuis APIs fournisseur** si disponible

**Dernière mise à jour** : 2026-01-09
**Prochaine revue recommandée** : 2026-04-01

---

### Tableau Tarification (2026 Q1)

| Fournisseur | Modèle | Entrée ($/1K tokens) | Sortie ($/1K tokens) |
|-------------|--------|----------------------|----------------------|
| **OpenAI** | GPT-4 Turbo | $0.010 | $0.030 |
| **OpenAI** | GPT-3.5 Turbo | $0.0005 | $0.0015 |
| **Anthropic** | Claude 3.5 Sonnet | $0.003 | $0.015 |
| **Anthropic** | Claude 3 Haiku | $0.00025 | $0.00125 |
| **Mistral** | Mistral Large | $0.004 | $0.012 |
| **Mistral** | Mistral Small | $0.001 | $0.003 |
| **Local (MLX)** | Llama 3 70B | $0.000 | $0.000 |
| **Local (MLX)** | Qwen 2.5 | $0.000 | $0.000 |

**Notes** :
- Modèles locaux (MLX, Ollama) ont coût API zéro mais encourent coûts infrastructure (électricité, amortissement matériel)
- Tarification précise au 2026-01-09, sujette à changements fournisseur

---

## Annexe C : Historique des Révisions

| Version | Date | Changements |
|---------|------|-------------|
| 1.2 | 2026-01-09 | Statut mis à jour vers Stable. Ajout algorithme comptage tokens (5.1.1), modèle tarification (5.1.2), formule coût (5.1.3), estimation conservative (5.1.4), niveaux budget (5.2), exemples périmètre budget (5.2.1), timing application (5.2.2), transparence coût (5.3), suivi coût réel (5.4), considérations sécurité (Section 6), anti-patterns (8.1), implémentation estimation coût (8.2), tests conformité (9.1), exemple workflow avec décomposition (Annexe A), tableau tarification (Annexe B), liaison NORP-001 (3.1). |
| 1.0 | 2026-01-07 | Brouillon initial. |

---

## Citation

```bibtex
@techreport{norp007-2026,
  title={{NORP-007: Estimation des Coûts et Application du Budget d'Exécution}},
  author={{Groupe de Travail NORP}},
  institution={NeuraScope},
  year={2026},
  month={Janvier},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/fr/NORP-007},
  license={CC BY 4.0}
}
```

---

**NORP-007 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Sous licence CC BY 4.0**
