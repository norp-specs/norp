# NORP-002
## Isolation Multi-Tenant des Ressources pour Systèmes d'Orchestration IA

---

**Licence**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (À attribuer)

---

### Statut
Stable

### Catégorie
Sécurité et Sémantique d'Isolation

### Version
1.2

### Date
2026-01-09

### Auteurs
Groupe de Travail NORP

---

## 1. Résumé

Ce document définit des règles d'isolation obligatoires pour les systèmes d'orchestration IA multi-tenant.

Il spécifie comment l'identité tenant DOIT être résolue, comment les ressources DOIVENT être cloisonnées, et comment l'accès cross-tenant DOIT être empêché ou explicitement autorisé.

L'objectif est de garantir une isolation stricte des données, empêcher l'escalade de privilèges, et assurer des frontières d'exécution auditables.

---

## 2. Motivation

Les workflows IA chargent dynamiquement des ressources externes, injectent des données contextuelles dans des modèles probabilistes, et peuvent déclencher des effets de bord irréversibles.

Dans les environnements multi-tenant, une isolation insuffisante peut résulter en :
- Exposition de données cross-tenant
- Accès non autorisé à des modèles ou outils
- Violations de confidentialité et réglementaires

L'isolation tenant est donc un invariant fondamental, pas un détail d'implémentation.

---

## 3. Périmètre

Cette spécification s'applique aux systèmes qui :
- Imposent l'isolation entre deux ou plusieurs tenants
- Exécutent des workflows au nom d'entités distinctes
- Résolvent le contexte d'exécution dynamiquement

**Les systèmes mono-tenant** sont HORS PÉRIMÈTRE.

Cette spécification s'applique uniquement aux **workflows définis statiquement**.

---

## 4. Terminologie

**Tenant** : La plus petite unité d'isolation imposée par le système.

### 4.1 Cohérence du Modèle Tenant

Les systèmes DOIVENT imposer l'isolation à un **niveau unique et cohérent**.

Les modèles valides incluent :
- **Isolation niveau organisation** (les utilisateurs partagent les ressources de l'organisation)
- **Isolation niveau utilisateur** (la plus stricte, aucun partage)
- **Modèles hiérarchiques** (ex: organisation avec sous-équipes), à condition que la frontière tenant imposée soit explicite et cohérente

**Exemple** : Tenant = `organization_id`, avec permissions niveau équipe au sein du périmètre organisation.

Les systèmes DOIVENT documenter leur modèle tenant explicitement.

---

**Contexte d'Exécution** : L'identité tenant résolue et les permissions sous lesquelles un workflow s'exécute.

**Ressource** : Toute dépendance externe telle qu'une API, endpoint de modèle, base de données, stockage de fichiers, ou outil.

Les mots-clés DOIT, DEVRAIT et PEUT sont à interpréter selon la [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Exigences Normatives

### 5.1 Résolution du Tenant

Avant qu'une ressource ne soit accédée, le système DOIT résoudre une **identité tenant unique** pour l'exécution.

#### 5.1.1 Algorithme de Résolution du Tenant

L'identité tenant DOIT être résolue selon l'**ordre de priorité** suivant :

1. **Contexte d'exécution explicite** (ex: en-tête API `X-Tenant-ID`, paramètre d'exécution)
2. **Identité du principal authentifié** (ex: claim JWT, métadonnées de clé API)
3. **Métadonnées de propriété du workflow** (ex: `created_by_tenant_id`)

La résolution tenant DOIT être **idempotente** : résoudre deux fois produit le même résultat.

#### 5.1.2 Résolution de Conflits

Si plusieurs sources fournissent des **identités tenant différentes** :

- Si une **source de priorité supérieure** existe, elle DOIT être utilisée
- Si deux sources **au même niveau de priorité** sont en conflit, l'exécution DOIT être **rejetée**
- La propriété du workflow NE DOIT **JAMAIS surcharger** un contexte d'exécution explicite

**Exemples** :

| Scénario | En-tête API | Claim JWT | Propriétaire Workflow | Résolution |
|----------|-------------|-----------|----------------------|------------|
| 1 | `acme` | `globex` | `acme` | **REJET** (conflit au même niveau de priorité) |
| 2 | `acme` | - | `globex` | **Utiliser `acme`** (en-tête > propriété) |
| 3 | - | `acme` | `acme` | **Utiliser `acme`** (cohérent) |
| 4 | `acme` | `acme` | `globex` | **Utiliser `acme`** (contexte > propriété) |

---

### 5.2 Cloisonnement Obligatoire des Ressources

Tous les accès aux ressources DOIVENT être **explicitement cloisonnés** au tenant résolu.

#### 5.2.1 Mécanismes de Cloisonnement des Ressources

Les mécanismes de cloisonnement valides incluent, sans s'y limiter :

- **Filtres niveau base de données** : `WHERE tenant_id = ?`
- **Row-Level Security (RLS)** : Politiques PostgreSQL, vues MySQL
- **Chemins API préfixés tenant** : `/api/tenants/{tenant_id}/resources`
- **En-têtes cloisonnés tenant** : `X-Tenant-ID: acme`
- **Chemins fichiers préfixés tenant** : `/storage/tenants/{tenant_id}/`
- **Clés cache cloisonnées tenant** : `tenant:{tenant_id}:resource:{id}`

Le mécanisme de cloisonnement DOIT garantir que :
- Les ressources d'autres tenants **NE PEUVENT PAS être accédées**, même si leurs identifiants sont connus
- Supprimer la contrainte tenant exposerait des données cross-tenant (preuve d'isolation)

Les implémentations utilisant Row-Level Security ou fonctionnalités équivalentes de base de données DOIVENT s'assurer que les politiques sont imposées **au niveau base de données**, pas au niveau applicatif.

#### 5.2.2 Test de Vérification d'Isolation

Les implémenteurs DEVRAIENT vérifier la correction de l'isolation en :

1. Exécutant une requête **AVEC filtre tenant** → Nombre de résultats = **N**
2. Exécutant la même requête **SANS filtre tenant** → Nombre de résultats = **M**
3. Vérifiant **M > N** (prouve que l'isolation est active)

**Exemple** :
```sql
-- AVEC filtre
SELECT COUNT(*) FROM datasources WHERE tenant_id = 'acme';
→ 15

-- SANS filtre
SELECT COUNT(*) FROM datasources;
→ 1547

-- Conclusion: 1547 > 15 → Isolation vérifiée ✅
```

Si **M == N**, alors :
- Un seul tenant existe (environnement de test invalide)
- L'isolation peut ne pas être imposée (nécessite investigation)

---

### 5.3 Pas d'Accès Global Implicite

Les ressources NE DOIVENT PAS être accessibles globalement par défaut.

#### 5.3.1 Critères de Ressource Globale

Une ressource PEUT être déclarée **globale** seulement si :
- Elle ne contient **AUCUNE donnée spécifique à un tenant**
- L'accès est en **lecture seule** OU les écritures sont isolées par tenant
- L'accès est **entièrement auditable**
- La ressource est **explicitement marquée** comme globale (ex: `is_global = true`)

**Exemples de ressources globales valides** :
- Endpoints LLM publics (Claude, GPT-4) avec journalisation des requêtes cloisonnée par tenant
- APIs publiques (météo, prix actions) avec suivi de quota par tenant

**Exemples de ressources globales INVALIDES** :
- Base de données partagée avec données tenant mélangées
- Stockage fichiers inscriptible sans préfixes tenant
- Ressources "par défaut" accessibles sans flag global explicite

---

### 5.4 Isolation au Moment de la Validation

Durant la validation, le système DOIT vérifier que :
- Toutes les ressources référencées **existent dans le périmètre tenant**
- Le contexte d'exécution a la **permission** d'accéder à chaque ressource
- Les ressources sont **actives et disponibles**

Les ressources hors du périmètre tenant DOIVENT être **rejetées durant la validation**, pas différées à l'exécution.

---

### 5.5 Isolation au Moment de l'Exécution

Durant l'exécution :
- Toutes les **opérations LECTURE** DOIVENT être cloisonnées par tenant
- Toute tentative de lire des ressources **hors du périmètre tenant** DOIT échouer immédiatement

L'exécution NE DOIT PAS introduire un accès plus large que ce qui a été validé.

---

### 5.6 Pas d'Effets de Bord Cross-Tenant

Les **opérations ÉCRITURE** NE DOIVENT PAS affecter des données hors du périmètre tenant résolu.

Cela inclut :
- Écrire dans le stockage d'un tenant étranger
- Déclencher des outils ou APIs au nom d'un autre tenant
- Muter un état partagé sans autorisation explicite

#### 5.6.1 Collaboration Cross-Tenant Autorisée (Optionnel)

Les opérations cross-tenant sont **INTERDITES par défaut**.

Les exceptions sont permises **seulement si** :
- Une **autorisation explicite** existe (ex: ressource partagée avec entrée ACL)
- L'autorisation est **vérifiée durant la Résolution de Contexte** (pas à l'exécution)
- Les opérations sont **entièrement journalisées** avec identifiants tenant source + tenant destination

Les systèmes supportant la collaboration cross-tenant DOIVENT documenter :
- **Modèle d'autorisation** (ACLs, RBAC, etc.)
- **Exigences de piste d'audit**
- **Mécanismes de révocation**

---

## 6. Comportement Fail-Safe

Si une règle d'isolation ne peut être vérifiée ou imposée, l'exécution DOIT être **empêchée**.

Le comportement fail-safe inclut :
- Rejeter l'exécution
- Produire une `PERMISSION_ERROR` explicite
- Journaliser la tentative de violation d'isolation

---

## 7. Considérations de Sécurité

Les définitions de workflow DOIVENT être traitées comme **entrée non fiable**.

Les mécanismes d'isolation DOIVENT assumer des **tentatives adverses** de contournement des frontières tenant.

Les implémenteurs DEVRAIENT assumer que :
- Les définitions de workflow peuvent être malveillantes
- Les identifiants de ressource peuvent être sondés systématiquement
- Des attaques par timing peuvent être utilisées pour inférer des données tenant

---

## 8. Guide d'Implémentation (Non-Normatif)

### 8.1 Anti-Patterns Courants

#### SQL/ORM
❌ **MAUVAIS** : `SELECT * FROM resources WHERE id = ?`
✅ **BON** : `SELECT * FROM resources WHERE id = ? AND tenant_id = ?`

#### API REST
❌ **MAUVAIS** : `GET /api/resources/123`
✅ **BON** : `GET /api/tenants/{tenant_id}/resources/123`

#### Cache
❌ **MAUVAIS** : `cache.get("workflow_123")`
✅ **BON** : `cache.get("tenant:acme:workflow_123")`

#### Stockage Fichiers
❌ **MAUVAIS** : `/storage/uploads/file.pdf`
✅ **BON** : `/storage/tenants/acme/uploads/file.pdf`

#### MongoDB
❌ **MAUVAIS** : `db.resources.findOne({_id: "123"})`
✅ **BON** : `db.resources.findOne({_id: "123", tenant_id: "acme"})`

---

### 8.2 Checklist de Revue de Code

Lors de la revue de code pour la conformité NORP-002 :

- [ ] Chaque requête base de données inclut un filtre tenant
- [ ] Chaque appel API inclut un identifiant tenant (en-tête, chemin, ou paramètre query)
- [ ] Chaque opération fichier utilise des chemins préfixés tenant
- [ ] Les clés cache incluent des identifiants tenant
- [ ] Pas de SQL brut sans clause `WHERE` tenant
- [ ] Pas de requêtes ORM sans scope tenant
- [ ] Ressources globales explicitement marquées (`is_global = true`)
- [ ] Opérations cross-tenant ont vérification ACL

---

## 9. Conformité

Un système est **conforme NORP-002** si :
- Toutes les exigences obligatoires (Sections 5.1–5.6) sont implémentées
- La résolution tenant survient **avant tout accès aux ressources**
- Tous les accès aux ressources sont **cloisonnés par tenant**
- Tous les tests de conformité obligatoires **réussissent**

### 9.1 Suite de Tests de Conformité

**Test 1 : Rejet de Conflit Tenant**
- **Configuration** : Workflow détenu par Tenant A, contexte d'exécution = Tenant B (conflit)
- **Attendu** : Rejet avec `PERMISSION_ERROR`
- **Justification** : Empêche l'exécution cross-tenant accidentelle

**Test 2 : Rejet de Ressource Cross-Tenant**
- **Configuration** : Ressource R détenue par Tenant A, contexte d'exécution = Tenant B
- **Entrée** : Workflow référence Ressource R
- **Attendu** : Rejet durant la Résolution de Contexte
- **Erreur** : `PERMISSION_ERROR`

**Test 3 : Accès Ressource Globale**
- **Configuration** : Ressource G marquée comme globale (`is_global = true`)
- **Entrée** : Workflow exécuté par Tenant A référence Ressource G
- **Attendu** : Accès accordé (ressource globale accessible)

**Test 4 : Tentative d'Escalade Runtime**
- **Configuration** : Workflow validé avec Ressource R1 (Tenant A)
- **Entrée** : Durant l'exécution, code tente d'accéder Ressource R2 (Tenant B)
- **Attendu** : Échec immédiat avec `PERMISSION_ERROR`
- **Justification** : Les vérifications runtime empêchent l'escalade

**Test 5 : Isolation des Effets de Bord**
- **Configuration** : Workflow exécuté par Tenant A contient opération écriture
- **Entrée** : Exécuter workflow
- **Attendu** : Écritures UNIQUEMENT dans le périmètre Tenant A
- **Vérification** : Le stockage Tenant B reste inchangé

Spécifications de tests complètes disponibles dans `compliance-tests/NORP-002-tests.md`.

---

## 10. Résumé de la Justification

**Principe Fondamental** : L'isolation tenant est un invariant non-négociable.

Toute ambiguïté dans la résolution tenant ou le cloisonnement des ressources invalide la confiance système et la conformité réglementaire.

Ce principe s'applique indépendamment de la complexité d'orchestration, du langage de programmation, ou de l'infrastructure.

---

## 11. Extensions Futures

Les spécifications NORP futures PEUVENT définir :
- Modèles tenant hiérarchiques avec héritage
- Contrats de partage de ressources cross-tenant
- Contextes d'exécution zero-trust
- Politiques d'isolation auditables
- Migration et portabilité tenant

---

## 12. Références

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) : Mots-clés pour l'usage dans les RFC pour indiquer les niveaux d'exigence
- Meilleures Pratiques de Sécurité SaaS Multi-Tenant
- Principes d'Architecture Zero Trust (NIST SP 800-207)
- OWASP Multi-Tenancy Cheat Sheet

---

## 13. Remerciements

Cette spécification est dérivée des patterns d'isolation multi-tenant en production chez NeuraScope.

Les auteurs remercient les relecteurs sécurité et les premiers adopteurs pour leurs retours.

---

## Annexe A : Historique des Révisions

| Version | Date | Changements |
|---------|------|-------------|
| 1.2 | 2026-01-09 | Statut mis à jour vers Stable. Ajout algorithme résolution conflits, test vérification isolation, règles collaboration cross-tenant, anti-patterns, checklist revue code. |
| 1.1 | 2026-01-08 | Ajout algorithme résolution tenant, mécanismes cloisonnement, critères ressources globales. |
| 1.0 | 2026-01-07 | Brouillon initial. |

---

## Citation

```bibtex
@techreport{norp002-2026,
  title={{NORP-002: Isolation Multi-Tenant des Ressources pour Systèmes d'Orchestration IA}},
  author={{Groupe de Travail NORP}},
  institution={NeuraScope},
  year={2026},
  month={Janvier},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/fr/NORP-002},
  license={CC BY 4.0}
}
```

---

**NORP-002 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Sous licence CC BY 4.0**
