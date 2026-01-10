# NORP-001
## Pipeline de Validation Pré-Exécution pour Systèmes d'Orchestration IA

---

**Licence**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (À attribuer)

---

### Statut
Stable

### Catégorie
Architecture et Sémantique d'Exécution

### Version
1.2

### Date
2026-01-09

### Auteurs
Groupe de Travail NORP

---

## 1. Résumé

Ce document définit un pipeline de validation pré-exécution obligatoire pour les systèmes d'orchestration IA opérant en environnement de production.

Il spécifie les étapes de validation qui DOIVENT être complétées avant toute exécution impliquant des ressources externes, mutation d'état, ou opérations génératrices de coûts.

L'objectif est d'assurer le déterminisme, la sécurité, le contrôle des coûts et la prévisibilité des workflows IA complexes.

---

## 2. Motivation

Les systèmes d'orchestration IA diffèrent des moteurs de workflows traditionnels sur trois aspects critiques :

- Ils interagissent avec des systèmes probabilistes.
- Ils invoquent des services externes avec des coûts variables et souvent irréversibles.
- Ils opèrent sur des données cloisonnées par tenant, projet ou utilisateur.

En environnement de production, exécuter un workflow invalide ou partiellement validé conduit à des échecs non-déterministes, une exposition illimitée des coûts, et des violations potentielles d'isolation des données.

Ce document formalise un contrat de validation strict qui prévient ces modes de défaillance.

---

## 3. Périmètre

Cette spécification s'applique aux systèmes qui :

- Exécutent des workflows IA ou basés sur des agents.
- Représentent les workflows comme graphes dirigés ou unités d'exécution chaînées.
- Invoquent des ressources externes ou soumises à quotas.

Cette spécification s'applique **uniquement aux workflows définis statiquement**, où le graphe d'exécution complet est connu avant l'exécution.

La génération dynamique de workflows ou la mutation de graphe à l'exécution sont HORS PÉRIMÈTRE pour cette version.
Les spécifications NORP futures PEUVENT adresser de tels modèles.

---

## 4. Terminologie

- **Workflow**: Un graphe dirigé de nœuds d'exécution.
- **Nœud**: Une unité d'exécution avec des entrées et sorties déclarées.
- **Ressource**: Toute dépendance externe telle qu'une API, endpoint de modèle, base de données ou outil.
- **Exécution**: L'évaluation runtime d'un workflow.
- **Validation**: Toute opération qui vérifie la correitude sans causer d'effets de bord.

**Opération génératrice de coûts** : Toute opération qui :
- Déclenche une facturation mesurée (ex: tokens API LLM, APIs cloud payantes)
- Consomme des quotas limités (ex: limites de débit, allocations tenant)
- Alloue des ressources coûteuses ou non récupérables (ex: temps GPU)

Exemples d'opérations génératrices de coûts :
- Appel à une API LLM commerciale
- Exécution d'une API de recherche ou d'enrichissement payante
- Provisionnement de ressources de calcul cloud

Les opérations non génératrices de coûts pour cette spécification incluent :
- Calcul local
- Lectures de cache
- Journalisation
- Requêtes de base de données internes sauf si limitées par quota

Les mots-clés DOIT, DEVRAIT et PEUT sont à interpréter selon la [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Exigences Normatives

### 5.1 Pipeline Pré-Exécution Obligatoire

Tout système d'orchestration IA conforme à cette spécification DOIT implémenter les étapes de pipeline suivantes dans un ordre strict :

1. Validation Structurelle
2. Compilation
3. Résolution de Contexte
4. Exécution
5. Agrégation
6. Comptabilité Post-Exécution

Aucune étape NE PEUT être sautée ou réordonnée.

**Note** : L'ordre strict fait référence aux dépendances inter-étapes. Les implémentations PEUVENT paralléliser les opérations au sein d'une étape.

#### 5.1.1 Justification de l'Ordre des Étapes

Cet ordonnancement est obligatoire car :

- La Validation Structurelle détecte l'invalidité du graphe en temps linéaire sans allouer de ressources externes.
- La Compilation requiert un graphe acyclique validé pour produire un plan d'exécution déterministe.
- La Résolution de Contexte vérifie les ressources externes seulement après que la correction interne soit prouvée, empêchant l'accès inutile aux ressources et l'exposition aux coûts.

---

### 5.2 Validation Structurelle

La validation structurelle DOIT inclure :

- Détection de cycles dans le graphe dirigé.
- Vérification que tous les nœuds référencés existent.
- Validation des dépendances déclarées.

Si une erreur structurelle est détectée, le workflow DOIT être rejeté immédiatement.

#### 5.2.1 Exigences de Détection de Cycles

La détection de cycles DOIT :
- Opérer en temps **O(V+E)** ou mieux, où V = nœuds, E = arêtes.
- Détecter **tous les cycles**, y compris ceux dans les sous-graphes déconnectés.
- **Rejeter le workflow** si un cycle est détecté.

Les implémentations PEUVENT utiliser DFS (Parcours en Profondeur), validation topologique, ou algorithmes équivalents.

La validation structurelle DOIT se terminer **sans allouer de ressources externes** (pas d'appels réseau, pas de connexions base de données).

---

### 5.3 Compilation

La Compilation DOIT transformer un workflow validé en **plan d'exécution déterministe**.

L'étape de compilation DOIT :
- Produire un ordre d'exécution valide qui respecte toutes les dépendances de nœuds.
- Échouer si aucun ordre d'exécution valide n'existe.

Le tri topologique (algorithme de Kahn, parcours DFS post-ordre, ou équivalent) DEVRAIT être utilisé.

#### 5.3.1 Exigence de Déterminisme

**Ordre d'exécution déterministe** signifie :
- Étant donnée la **même définition de workflow**, le système DOIT produire le **même ordre d'exécution** à travers plusieurs exécutions.
- Lorsque **plusieurs ordres valides** existent (ex: nœuds indépendants A et B), le système DOIT appliquer une **règle de départage cohérente** (ex: tri lexicographique par ID de nœud).

---

### 5.4 Résolution de Contexte

La Résolution de Contexte DOIT vérifier que :

- Toutes les ressources référencées **existent**.
- Le contexte d'exécution a la **permission** d'accéder à chaque ressource.
- Les ressources sont **actives et disponibles**.

**Les vérifications de contrôle d'accès DOIVENT survenir durant la validation** et NE DOIVENT PAS être différées au moment de l'exécution.

#### 5.4.1 Fenêtre de Validité du Contexte

La Résolution de Contexte capture un **instantané** de l'état des ressources et permissions au temps T.

Les implémentations DEVRAIENT :
- Exécuter les workflows **immédiatement** après validation pour minimiser la dérive d'état.
- **Re-valider** le contexte si l'exécution est retardée au-delà d'un seuil raisonnable (RECOMMANDÉ : 5 minutes ou moins).

Les implémentations PEUVENT :
- Verrouiller les ressources durant la transition validation-vers-exécution.
- Implémenter l'expiration de contexte avec re-validation automatique.

---

### 5.5 Sémantique d'Exécution et d'Exécution Partielle

L'exécution DOIT commencer **seulement après** la complétion réussie de toutes les étapes de validation.

Si un nœud échoue durant l'exécution :
- Le workflow DOIT être marqué comme **ÉCHOUÉ**.
- Les sorties des nœuds exécutés avec succès PEUVENT être préservées pour débogage ou nouvelle tentative.
- **Les effets de bord NE SONT PAS automatiquement annulés** sauf si une sémantique transactionnelle est explicitement supportée.

#### 5.5.1 Sémantique Transactionnelle (Optionnel)

Les systèmes supportant l'annulation DOIVENT :
- Spécifier **quels types de nœuds** supportent l'annulation (ex: écritures base de données mais pas appels LLM).
- Documenter le comportement d'annulation dans leur guide d'implémentation.

---

### 5.6 Mise en Cache de Validation (Optionnel)

Les résultats de validation PEUVENT être mis en cache si :
- La définition du workflow n'a pas changé.
- Les ressources référencées n'ont pas changé.
- Le contexte d'exécution et les permissions n'ont pas changé.

Les implémentations mettant en cache la validation DOIVENT :
- **Invalider le cache** lorsqu'une dépendance change.
- Documenter la logique d'invalidation de cache.

---

## 6. Principe Fail-Fast

**Les erreurs structurelles ou sémantiques** détectées durant la validation DOIVENT empêcher l'exécution.

La gestion d'erreur runtime PEUT implémenter des **tentatives ou mécanismes de repli** pour les **échecs transitoires** (ex: timeouts réseau, indisponibilité temporaire de ressource).

La gestion runtime NE DOIT PAS contourner les exigences de validation ou compenser des étapes de validation manquantes.

---

## 7. Taxonomie des Erreurs

Les erreurs de validation de workflow DOIVENT être classifiées comme :

- **STRUCTURAL_ERROR**: Cycle dans le graphe, référence de nœud manquante, dépendance invalide
- **RESOURCE_ERROR**: Ressource externe manquante, indisponible ou injoignable
- **PERMISSION_ERROR**: Droits d'accès insuffisants aux ressources requises
- **COST_ERROR**: Coût estimé dépasse le seuil configuré

Les erreurs niveau système DOIVENT être rapportées séparément :

- **VALIDATOR_FAILURE**: Le processus de validation a planté, expiré, ou rencontré une erreur interne
- **COMPILER_FAILURE**: L'étape de compilation a échoué en interne
- **CONTEXT_FAILURE**: Impossible de charger ou résoudre le contexte d'exécution

**Les erreurs de workflow** sont corrigibles par l'utilisateur.
**Les erreurs niveau système** indiquent une défaillance d'infrastructure ou de plateforme.

Chaque erreur DEVRAIT inclure :
- Un **code d'erreur lisible par machine** (ex: `STRUCTURAL_ERROR_CYCLE_DETECTED`)
- Un **message lisible par humain**
- **Métadonnées de localisation** (ID de nœud, arête, identifiant de ressource)

---

## 8. Guide d'Implémentation (Non-Normatif)

Il est conseillé aux implémenteurs que :
- Les algorithmes de détection de cycles (ex: DFS) opèrent typiquement en temps O(V+E).
- La validation peut souvent éviter les appels réseau via des métadonnées de ressources mises en cache.
- La résolution de contexte peut bénéficier du pooling de connexions pour réduire la surcharge.

---

## 9. Conformité

Un système est **conforme NORP-001** s'il implémente toutes les étapes obligatoires et réussit la suite de tests de conformité définie dans `compliance-tests/NORP-001-tests.md`.

### 9.1 Suite de Tests de Conformité (Résumé)

**Test 1 : Rejet de Cycle**
- Entrée : Workflow avec cycle `A→B→C→A`
- Attendu : Rejet durant la Validation Structurelle
- Erreur : `STRUCTURAL_ERROR`

**Test 2 : Ordre d'Exécution Déterministe**
- Entrée : Même workflow validé deux fois
- Attendu : Ordre d'exécution identique les deux fois

**Test 3 : Rejet de Ressource Manquante**
- Entrée : Workflow référençant une ressource non-existante
- Attendu : Rejet durant la Résolution de Contexte
- Erreur : `RESOURCE_ERROR`

**Test 4 : Précédence de Validation Fail-Fast**
- Entrée : Workflow avec à la fois un cycle et une ressource manquante
- Attendu : Rejet à l'étape de Validation Structurelle (avant vérification des ressources)

Spécifications de tests complètes disponibles dans `compliance-tests/NORP-001-tests.md`.

---

## 10. Considérations de Sécurité

L'échec de validation des workflows avant exécution peut résulter en :
- **Accès non autorisé aux ressources** (fuites de données cross-tenant)
- **Attaques d'amplification de coûts** (workflows malveillants déclenchant des appels API illimités)
- **Déni de service** (épuisement de ressources via graphes invalides)

Les implémenteurs DOIVENT s'assurer que les étapes de validation s'exécutent dans un **contexte de sécurité équivalent à l'exécution** (mêmes vérifications de permissions, même isolation tenant).

Une validation et isolation strictes sont obligatoires pour une opération sécurisée.

---

## 11. Résumé de la Justification

**Principe Fondamental** : Un workflow invalide ou non sécurisé NE DOIT PAS être exécuté.

Cet invariant est fondamental pour tout système d'orchestration IA de niveau production.

Ce principe s'applique indépendamment de la complexité d'orchestration, du langage de programmation, ou de l'infrastructure.

---

## 12. Extensions Futures

Les spécifications NORP futures PEUVENT définir :
- Sémantique d'exécution partielle avec points de contrôle
- Garanties d'exécution distribuée
- Mécanismes de rollback transactionnel
- Exigences d'observabilité et d'audit
- Standards de génération dynamique de workflows

---

## 13. Références

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) : Mots-clés pour l'usage dans les RFC pour indiquer les niveaux d'exigence
- Théorie des Graphes Acycliques Dirigés (DAG)
- Algorithmes de tri topologique (Kahn, parcours DFS post-ordre)
- Cormen, T. H., et al. (2009). *Introduction to Algorithms* (3ème éd.). MIT Press.

---

## 14. Remerciements

Cette spécification est dérivée du code de production et de l'expérience opérationnelle chez NeuraScope.

Les auteurs remercient les premiers relecteurs et contributeurs pour leurs retours.

---

## Annexe A : Historique des Révisions

| Version | Date | Changements |
|---------|------|-------------|
| 1.2 | 2026-01-09 | Statut mis à jour vers Stable. Taxonomie d'erreurs simplifiée. Tests de conformité condensés. |
| 1.1 | 2026-01-08 | Ajout de la justification d'ordre des étapes, exigences de détection de cycles, clarification du déterminisme. |
| 1.0 | 2026-01-06 | Brouillon initial. |

---

## Citation

```bibtex
@techreport{norp001-2026,
  title={{NORP-001: Pipeline de Validation Pré-Exécution pour Systèmes d'Orchestration IA}},
  author={{Groupe de Travail NORP}},
  institution={NeuraScope},
  year={2026},
  month={Janvier},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/fr/NORP-001},
  license={CC BY 4.0}
}
```

---

**NORP-001 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Sous licence CC BY 4.0**
