# NORP-006
## Isolation du Contexte d'Exécution et Gestion du Cycle de Vie des Ressources

---

**Licence**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (À attribuer)

---

### Statut
Stable

### Catégorie
Sémantique d'Exécution et Performance

### Version
1.2

### Date
2026-01-09

### Auteurs
Groupe de Travail NORP

---

## 1. Résumé

Cette spécification définit des exigences strictes pour l'isolation du contexte d'exécution et la gestion du cycle de vie des ressources dans les systèmes d'orchestration IA.

Elle assure que les ressources telles que connexions base de données, clients API, sessions modèles, et caches sont cloisonnées à un contexte d'exécution unique, empêchant fuites cross-exécution, réutilisation non intentionnelle, et violations frontières multi-tenant.

L'objectif est d'équilibrer optimisation performance (pooling ressources au sein exécution) avec garanties sécurité (isolation stricte entre exécutions).

---

## 2. Motivation

Les systèmes d'orchestration IA gèrent fréquemment des ressources coûteuses et avec état :
- Connexions base de données (50-200ms temps initialisation)
- Clients API avec credentials
- Sessions d'inférence modèle
- Caches en mémoire

Si les durées de vie des ressources ne sont pas strictement cloisonnées, les systèmes peuvent souffrir de :
- **Fuite données cross-exécution** (données Exécution 1 visibles à Exécution 2)
- **Réutilisation credentials cross-tenant** (violation sécurité)
- **Comportement non-déterministe** (exécution dépend état exécution antérieure)
- **Violations frontières sécurité** (isolation tenant compromise)

Cette spécification impose **l'isolation cloisonnée par exécution** comme invariant dur.

---

## 3. Périmètre

Cette spécification s'applique aux systèmes qui :
- Exécutent workflows avec frontières d'exécution identifiables
- Allouent ressources réutilisables ou avec état durant exécution
- Supportent exécutions parallèles ou concurrentes (même ou différents tenants)

### 3.1 Relation avec Autres Spécifications NORP

- **NORP-001** définit le pipeline d'exécution et étapes cycle de vie
- **NORP-002** définit isolation ressources **niveau tenant** (QUI peut accéder ressources)
- **NORP-006** définit isolation **niveau exécution** au sein d'un tenant (QUAND et COMBIEN DE TEMPS ressources existent)

**Résumé des relations** :

```
NORP-002: Isolation tenant (QUI)
  → Tenant A ne peut accéder ressources Tenant B

NORP-006: Isolation exécution (QUAND/COMBIEN TEMPS)
  → Exécution 1 ne peut réutiliser ressources Exécution 2
  → MÊME SI même tenant

Combiné (NORP-002 + NORP-006):
  → Tenant A Exécution 1 ≠ Tenant A Exécution 2
  → Tenant A Exécution 1 ≠ Tenant B Exécution 1
```

Les deux DOIVENT être implémentés pour garanties isolation complètes.

---

## 4. Terminologie

**Exécution** : Une invocation runtime unique et bornée d'un workflow.

**Contexte d'Exécution** : L'environnement isolé contenant métadonnées (tenant_id, blueprint_id, inputs) et état runtime (handles ressources, variables) pour une exécution.

**Identifiant d'Exécution** : Un identifiant unique (UUID) assigné au démarrage exécution.

**Ressource** : Tout objet avec état ou réutilisable incluant :
- Connexions base de données (PDO, JDBC, psycopg2)
- Clients API (Guzzle, Axios, requests)
- Sessions modèles (client OpenAI, SDK Anthropic)
- Caches (clients Redis, maps en mémoire)

**Pooling** : Réutilisation instance ressource à travers plusieurs nœuds au sein de la **même exécution**.

**Handle Ressource** : Une référence vers ressource externe initialisée (ex: connexion PDO, client HTTP).

Les mots-clés DOIT, DEVRAIT et PEUT sont à interpréter selon la [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Exigences Normatives

### 5.1 Identité d'Exécution

Chaque exécution workflow DOIT être assignée un **identifiant d'exécution unique**.

L'identifiant d'exécution DOIT :
- Être généré au **démarrage exécution** (avant toute allocation ressource)
- Être **immutable** durant toute l'exécution
- Être **globalement unique** (UUID v4 ou équivalent)
- Être utilisé pour **cloisonner toutes ressources runtime**

**Exemple** :
```
execution_id: "exec_a1b2c3d4-e5f6-7890-abcd-ef1234567890"
```

Toutes ressources allouées durant cette exécution DOIVENT être taguées avec cet identifiant.

---

### 5.2 Durée de Vie Ressource Cloisonnée par Exécution

Les ressources DOIVENT être cloisonnées à **exactement un contexte d'exécution**.

Une ressource PEUT être réutilisée :
- À travers **plusieurs nœuds** au sein de la **MÊME exécution**
- Seulement si explicitement déclarée comme cloisonnée par exécution

Une ressource NE DOIT PAS être réutilisée :
- À travers **différentes exécutions** (même tenant, même workflow)
- À travers **différents tenants** (imposition NORP-002)
- **Après terminaison exécution** (nettoyage complété)

---

### 5.3 Règles de Pooling Ressources

**Le pooling** (réutilisation au sein exécution) est permis UNIQUEMENT au sein d'un contexte d'exécution unique.

#### 5.3.1 Ce Que Signifie le Pooling

**Pooling = Réutiliser même instance ressource** :
- Même connexion base données utilisée par 5 nœuds dans une exécution ✅
- Même client API utilisé par 3 appels LLM dans une exécution ✅

**Pooling ≠ Mise en cache résultats** :
- Stocker sortie LLM pour réutilisation (préoccupation différente, voir NORP-003)

**Pooling ≠ Réutilisation cross-exécution** :
- Connexion BD depuis Exécution 1 réutilisée par Exécution 2 ❌

---

#### 5.3.2 Bénéfices et Contraintes du Pooling

**Bénéfices** (pourquoi pooling au sein exécution est permis) :
- **Performance** : Initialisation connexion = 50-200ms. Workflow avec 10 nœuds = 500-2000ms économisés.
- **Limites ressources** : Connexions max base données = 100. Sans pooling, 10 exécutions parallèles × 10 nœuds = 100 connexions (limite atteinte).

**Contraintes** (pourquoi pooling cross-exécution est interdit) :
- **Isolation** : Données Exécution 1 NE DOIVENT PAS fuiter vers Exécution 2
- **Déterminisme** : Exécution 2 NE DOIT PAS dépendre état Exécution 1
- **Sécurité** : Credentials pour Exécution 1 doivent être invalidés avant Exécution 2

---

### 5.4 Création et Disposition Ressources

Les ressources DOIVENT :
- Être créées **paresseusement** (première utilisation) OU **avidement** (durant résolution contexte)
- Être **disposées de façon déterministe** à fin exécution

La terminaison exécution (succès **OU** échec) DOIT déclencher :
- **Nettoyage ressources** (connexions fermées, clients détruits)
- **Invalidation credentials** (tokens révoqués si applicable)
- **Éviction cache** (cache cloisonné exécution vidé)

#### 5.4.1 Garanties de Nettoyage

Le nettoyage ressources DOIT survenir **même si** :
- L'exécution échoue en milieu workflow
- Exception lancée durant exécution
- Timeout survient

Les systèmes DEVRAIENT utiliser patterns **try-finally** ou équivalents pour garantir nettoyage.

**Exemple** :

```python
def execute_workflow(workflow, context):
    try:
        # Charger ressources
        context.load_resources()

        # Exécuter nœuds
        for node in workflow.nodes:
            execute_node(node, context)

    finally:
        # TOUJOURS nettoyer (même si exception)
        context.cleanup()
```

---

### 5.5 Sémantique d'Échec

Si l'exécution échoue :
- Les ressources NE DOIVENT PAS être réutilisées par exécutions subséquentes
- Ressources partiellement initialisées DOIVENT être disposées immédiatement
- Aucun état ressource ne peut survivre la frontière exécution

---

## 6. Considérations de Sécurité

L'échec d'imposition isolation cloisonnée exécution peut résulter en :
- **Fuite credentials** (token API depuis Exécution 1 réutilisé par Exécution 2)
- **Exposition données cross-tenant** (Connexion avec credentials Tenant A réutilisée pour Tenant B)
- **Attaques rejeu** (Exécution malveillante réutilise session authentifiée depuis exécution antérieure)
- **Contamination état** (Cache depuis Exécution 1 pollue Exécution 2)

**L'isolation cloisonnée exécution est exigence sécurité**, pas seulement optimisation performance.

L'isolation DOIT prendre précédence sur performance.

---

## 7. Diagnostic et Observabilité

Les systèmes DEVRAIENT exposer :
- **Identifiant exécution dans logs** (chaque entrée log taguée avec `execution_id`)
- **Événements allocation ressource** (quand ressource créée, quelle exécution la possède)
- **Événements disposition ressource** (quand ressource détruite, succès/échec nettoyage)
- **Métadonnées propriété ressource** (quelles ressources appartiennent à quelle exécution)

**Exemple entrée log** :

```json
{
  "timestamp": "2026-01-09T10:15:30Z",
  "level": "INFO",
  "execution_id": "exec_abc123",
  "tenant_id": "acme",
  "event": "RESOURCE_ALLOCATED",
  "resource_type": "database_connection",
  "resource_id": "conn_456"
}
```

---

## 8. Guide d'Implémentation (Non-Normatif)

### 8.1 Anti-Patterns Courants

#### Anti-Pattern 1 : Pool Connexion Global

❌ **MAUVAIS** : Pool connexion statique global
```php
class GlobalDB {
    public static PDO $connection;

    public static function get(): PDO {
        if (!self::$connection) {
            self::$connection = new PDO(...);
        }
        return self::$connection; // ❌ Partagé cross-exécutions
    }
}
```

✅ **BON** : Connexion cloisonnée exécution
```php
class ExecutionContext {
    private ?PDO $connection = null;

    public function __construct(
        public readonly string $execution_id,
        public readonly string $tenant_id
    ) {}

    public function getConnection(): PDO {
        if (!$this->connection) {
            $this->connection = new PDO(...); // ✅ Cloisonné cette exécution
        }
        return $this->connection;
    }

    public function cleanup(): void {
        if ($this->connection) {
            $this->connection = null; // Fermer connexion
        }
    }
}
```

---

#### Anti-Pattern 2 : Client API Singleton

❌ **MAUVAIS** : Singleton avec credentials globaux
```python
# Singleton global (persiste cross-exécutions)
api_client = APIClient(token=GLOBAL_TOKEN)

def execute_node(node):
    result = api_client.call(node.endpoint) # ❌ Même client cross-exécutions
```

✅ **BON** : Client cloisonné exécution
```python
class ExecutionContext:
    def __init__(self, execution_id: str, credentials: dict):
        self.execution_id = execution_id
        self.client = APIClient(token=credentials['api_token']) # ✅ Cloisonné

    def get_client(self) -> APIClient:
        return self.client

    def cleanup(self):
        if self.client:
            self.client.close()
```

---

#### Anti-Pattern 3 : Cache Cross-Exécution

❌ **MAUVAIS** : Clé cache sans cloisonnement exécution
```javascript
// Cache partagé cross-exécutions
const cache = new Map();
cache.set('model_session', session); // ❌ Pas de cloisonnement exécution
```

✅ **BON** : Clés cache cloisonnées exécution
```javascript
class ExecutionContext {
    constructor(executionId, tenantId) {
        this.executionId = executionId;
        this.cache = new Map(); // ✅ Instance par exécution
    }

    setCache(key, value) {
        const scopedKey = `exec:${this.executionId}:${key}`;
        globalCache.set(scopedKey, value); // ✅ Clé cloisonnée
    }

    cleanup() {
        // Évincer toutes clés pour cette exécution
        globalCache.deletePattern(`exec:${this.executionId}:*`);
    }
}
```

---

### 8.2 Pattern Cycle de Vie Ressource (Recommandé)

```python
class ExecutionContext:
    def __init__(self, execution_id: str, tenant_id: str):
        self.execution_id = execution_id
        self.tenant_id = tenant_id
        self._resources = {}

    def get_resource(self, resource_type: str, resource_id: int):
        """Chargement paresseux ressource (pooling au sein exécution)"""
        key = f"{resource_type}:{resource_id}"

        if key not in self._resources:
            # Charger ressource (connexion BD, client API, etc.)
            self._resources[key] = self._load_resource(resource_type, resource_id)

        return self._resources[key]

    def _load_resource(self, resource_type: str, resource_id: int):
        # Charger selon type (BD, API, etc.)
        # Valider propriété tenant (NORP-002)
        pass

    def cleanup(self):
        """Disposer toutes ressources à fin exécution"""
        for resource in self._resources.values():
            if hasattr(resource, 'close'):
                resource.close()
        self._resources.clear()
```

**Usage** :
```python
try:
    context = ExecutionContext(execution_id="exec_123", tenant_id="acme")

    # Nœud 1 utilise connexion BD
    db = context.get_resource('database', 5)  # Crée connexion

    # Nœud 2 utilise même connexion BD (pooling)
    db = context.get_resource('database', 5)  # Réutilise connexion ✅

finally:
    context.cleanup()  # Toujours nettoyer
```

---

## 9. Conformité

Un système est **conforme NORP-006** si :
- Toutes ressources sont **cloisonnées exécution** (execution_id unique assigné)
- Aucune ressource ne survit **terminaison exécution** (nettoyage garanti)
- **Le pooling** est limité à exécution unique (pas réutilisation cross-exécution)
- Tous tests conformité obligatoires réussissent

### 9.1 Suite de Tests de Conformité

**Test 1 : Isolation Exécution**

**Configuration** :
- Exécuter workflow W avec execution_id = "exec_1"
- Exécuter même workflow W avec execution_id = "exec_2"

**Action** : Inspecter ressources allouées pour chaque exécution

**Attendu** :
- Exécution 1 et Exécution 2 ont **instances ressources distinctes**
- Handles ressources (connexions, clients) NE SONT PAS partagés entre exécutions

**Critères réussite** :
- ✅ ressources exec_1 ≠ ressources exec_2
- ✅ Aucune connexion ou client partagé détecté

**Justification** : Prouve isolation niveau exécution.

---

**Test 2 : Pooling Intra-Exécution**

**Configuration** :
- Workflow avec 3 nœuds accédant tous même base données (connection_id = 5)
- Exécution unique

**Action** : Exécuter workflow, monitorer allocation ressources

**Attendu** :
- **Une connexion base données** créée
- **Même instance connexion** réutilisée par les 3 nœuds
- Connexion taguée avec execution_id

**Critères réussite** :
- ✅ Seulement 1 connexion créée (pas 3)
- ✅ Les 3 nœuds utilisent même handle connexion
- ✅ Durée vie connexion = durée vie exécution

**Justification** : Prouve pooling ressources au sein exécution fonctionne (optimisation performance).

---

**Test 3 : Rejet Réutilisation Cross-Exécution**

**Configuration** :
- Exécuter workflow W1 → crée ressource R1
- Workflow W1 complète
- Exécuter workflow W2 (même tenant, même définition workflow)

**Action** : Tenter réutiliser ressource R1 depuis W1 dans W2

**Attendu** :
- Système DOIT **rejeter réutilisation** ou **créer nouvelle ressource** R2
- R1 DOIT être disposée après complétion W1
- W2 utilise R2 (distincte de R1)

**Critères réussite** :
- ✅ R1 détruite après W1
- ✅ W2 crée nouvelle ressource R2
- ✅ Pas réutilisation cross-exécution

**Justification** : Prouve absence fuite ressources entre exécutions.

---

**Test 4 : Nettoyage sur Échec**

**Configuration** :
- Workflow avec 2 nœuds
- Nœud 1 alloue connexion base données
- Nœud 2 lance exception (échec)

**Action** : Forcer échec exécution au Nœud 2

**Attendu** :
- Exécution échoue
- Connexion base données depuis Nœud 1 DOIT être **fermée/disposée**
- Nettoyage ressources survient **malgré échec**

**Critères réussite** :
- ✅ Exécution marquée FAILED
- ✅ Connexion fermée (vérifiable via logs serveur base données)
- ✅ Pas de connexions fuitées

**Justification** : Prouve nettoyage survient même sur échec (pas fuites ressources).

---

**Test 5 : Isolation Tenant + Exécution (Vérification Croisée NORP-002)**

**Configuration** :
- Tenant A exécute workflow → Exécution 1
- Tenant A exécute autre workflow → Exécution 2 (même tenant, exécution différente)

**Action** : Vérifier ressources non partagées entre Exécution 1 et Exécution 2

**Attendu** :
- Ressources cloisonnées par **tenant_id ET execution_id**
- Ressources Exécution 1 ≠ ressources Exécution 2

**Critères réussite** :
- ✅ Ressources taguées avec `tenant_id` et `execution_id`
- ✅ Pas partage malgré même tenant

**Justification** : Prouve double isolation (tenant NORP-002 + exécution NORP-006).

Spécifications tests complètes disponibles dans `compliance-tests/NORP-006-tests.md`.

---

## 10. Considérations de Sécurité

Un pooling ressources inapproprié peut résulter en :
- **Fuite credentials** (token API depuis Exec 1 accessible à Exec 2)
- **Accès données cross-tenant** (Connexion authentifiée comme Tenant A réutilisée pour Tenant B)
- **Attaques rejeu** (Session authentifiée réutilisée malicieusement)
- **Contamination état** (Cache depuis Exec 1 pollue résultats Exec 2)

**L'isolation cloisonnée exécution est exigence sécurité**, pas seulement optimisation performance.

**L'isolation DOIT prendre précédence sur performance.**

---

## 11. Résumé de la Justification

**Principe Fondamental** : Les ressources sont liées à une exécution unique et NE DOIVENT PAS survivre aux frontières exécution.

Le pooling ressources améliore performance seulement lorsque borné par garanties isolation strictes.

Ce principe s'applique indépendamment de la complexité d'orchestration, du langage de programmation, ou de l'infrastructure.

---

## 12. Extensions Futures

Les spécifications NORP futures PEUVENT définir :
- Gestion pool connexions cross-exécutions (avec isolation stricte)
- Quotas et limites ressources par exécution
- Contexte exécution distribué à travers serveurs multiples
- Pools ressources préchauffés avec pré-initialisation cloisonnée tenant

---

## 13. Références

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) : Mots-clés pour l'usage dans les RFC pour indiquer les niveaux d'exigence
- Patterns gestion cycle vie ressources
- Meilleures pratiques pooling connexions
- OWASP Resource Injection Prevention

---

## 14. Remerciements

Cette spécification est dérivée de l'implémentation ContextManager dans NeuraScope (testé en production avec 10 000+ exécutions).

Les auteurs remercient les relecteurs pour retours sur sémantique pooling ressources et garanties isolation.

---

## Annexe A : Pattern Exemple Contexte Exécution

### Implémentation Complète ExecutionContext (Python)

```python
from dataclasses import dataclass
from typing import Dict, Any, Optional
import uuid

@dataclass
class ExecutionContext:
    execution_id: str
    tenant_id: str
    blueprint_id: str
    inputs: Dict[str, Any]

    def __post_init__(self):
        self._resources: Dict[str, Any] = {}

    def get_resource(self, resource_type: str, resource_id: int):
        """
        Chargement paresseux ressource avec cloisonnement exécution.
        Pooling au sein exécution: même resource_id retourne même instance.
        """
        key = f"{resource_type}:{resource_id}"

        if key not in self._resources:
            # Charger ressource (BD, API, session modèle)
            self._resources[key] = self._load_resource(resource_type, resource_id)

            print(f"[{self.execution_id}] Ressource allouée: {key}")

        return self._resources[key]

    def _load_resource(self, resource_type: str, resource_id: int):
        # Valider propriété tenant (NORP-002)
        # Charger connexion/client selon type
        if resource_type == 'database':
            return create_db_connection(resource_id, self.tenant_id)
        elif resource_type == 'api':
            return create_api_client(resource_id, self.tenant_id)
        else:
            raise ValueError(f"Type ressource inconnu: {resource_type}")

    def cleanup(self):
        """
        Disposer toutes ressources à fin exécution.
        DOIT être appelé dans bloc finally.
        """
        for key, resource in self._resources.items():
            if hasattr(resource, 'close'):
                resource.close()
                print(f"[{self.execution_id}] Ressource disposée: {key}")

        self._resources.clear()
```

---

### Exemple Usage

```python
# Exécution 1
try:
    ctx1 = ExecutionContext(
        execution_id=str(uuid.uuid4()),
        tenant_id="acme",
        blueprint_id="bp_123",
        inputs={"x": 5}
    )

    # Nœud 1 alloue connexion BD
    db = ctx1.get_resource('database', 5)  # Crée connexion

    # Nœud 2 réutilise même connexion (pooling)
    db = ctx1.get_resource('database', 5)  # ✅ Réutilise (même exécution)

finally:
    ctx1.cleanup()  # ✅ Toujours nettoyer

# Exécution 2 (exécution différente, même tenant)
try:
    ctx2 = ExecutionContext(
        execution_id=str(uuid.uuid4()),  # Nouvel ID exécution
        tenant_id="acme",  # Même tenant
        blueprint_id="bp_123",
        inputs={"x": 10}
    )

    # Nœud 1 alloue NOUVELLE connexion (pas réutilisation ctx1)
    db = ctx2.get_resource('database', 5)  # ✅ Crée NOUVELLE connexion

finally:
    ctx2.cleanup()
```

**Résultat** :
- Exécution 1 : 1 connexion créée, réutilisée à travers nœuds, disposée
- Exécution 2 : NOUVELLE connexion créée (pas réutilisation depuis Exec 1), disposée

---

## Annexe B : Historique des Révisions

| Version | Date | Changements |
|---------|------|-------------|
| 1.2 | 2026-01-09 | Statut mis à jour vers Stable. Ajout identité exécution (5.1), durée vie cloisonnée exécution (5.2), règles pooling (5.3), garanties nettoyage (5.4.1), sémantique échec (5.5), considérations sécurité (Section 6), guidance observabilité (Section 7), anti-patterns (8.1), pattern cycle vie ressource (8.2), tests conformité (9.1), exemple ExecutionContext complet (Annexe A), liaison NORP-001/002 (3.1). |
| 1.0 | 2026-01-07 | Brouillon initial. |

---

## Citation

```bibtex
@techreport{norp006-2026,
  title={{NORP-006: Isolation du Contexte d'Exécution et Gestion du Cycle de Vie des Ressources}},
  author={{Groupe de Travail NORP}},
  institution={NeuraScope},
  year={2026},
  month={Janvier},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/fr/NORP-006},
  license={CC BY 4.0}
}
```

---

**NORP-006 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Sous licence CC BY 4.0**
