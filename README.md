# Bundle Symfony - Correlation ID

## User Story

En tant que développeur Symfony, je souhaite un bundle qui gère automatiquement l'ID de corrélation pour mes requêtes afin de faciliter le suivi et le débogage dans mon application et potentiellement à travers plusieurs services.

---

## Fonctionnalités

### 1. Gérer l'ID de corrélation pour les requêtes HTTP

- Si une requête entrante contient un header d'ID de corrélation, le bundle doit le récupérer et le rendre disponible
- Si la requête entrante ne contient pas d'ID, le bundle doit en générer un
- L'ID de corrélation courant doit être propagé dans la réponse HTTP via le même header
- **Validation** : L'ID reçu doit être validé (format, longueur max) pour éviter les injections malveillantes
- **Sources de confiance** : Possibilité de configurer si on accepte l'ID provenant du header ou si on en génère toujours un nouveau

### 2. Gérer l'ID de corrélation pour les commandes CLI

- Lors de l'exécution d'une commande console, un ID de corrélation doit être généré automatiquement
- L'ID généré pour CLI doit avoir un préfixe configurable (par défaut : `CLI-`)
- Possibilité de passer un ID de corrélation via une option de commande : `--correlation-id=xxx`
- L'ID doit être accessible dans le contexte d'exécution de la commande

### 3. Propager l'ID de corrélation vers les clients HTTP sortants

- Lors d'appels HTTP sortants via Symfony HttpClient, l'ID de corrélation courant doit être automatiquement ajouté dans les headers
- Cette propagation doit être configurable (activable/désactivable)
- Le nom du header utilisé pour la propagation doit être cohérent avec la configuration

### 4. Propager l'ID de corrélation via Symfony Messenger

- Lors de l'envoi d'un message via Messenger, l'ID de corrélation courant doit être automatiquement propagé avec le message (via un Stamp)
- Lors de la réception d'un message, l'ID de corrélation (s'il est présent dans le Stamp) doit être rendu disponible pour le contexte de traitement du message
- Reset automatique de l'ID entre chaque traitement de message (important pour les workers long-running)
- Cette propagation doit être configurable (activable/désactivable)

### 5. S'intégrer avec Monolog

- Le bundle doit modifier la sortie de Monolog pour inclure l'ID de corrélation dans les logs
- Configuration du nom de la clé où l'ID est ajouté dans les logs (par défaut : `correlation_id`)
- Configuration pour activer/désactiver l'intégration avec Monolog
- L'ID doit apparaître dans tous les logs (HTTP, CLI, Messenger)

### 6. Être facile à installer

- Le bundle doit s'installer via Composer : `composer require vendor/correlation-id-bundle`
- Configuration automatique via Symfony Flex si possible
- Fonctionnement "zero-config" avec des valeurs par défaut sensées
- Configuration optionnelle via fichier YAML

### 7. Permettre la configuration du format de l'ID

- Formats supportés par défaut :
  - `uuid_v4` : UUID version 4 (par défaut)
  - `uuid_v7` : UUID version 7 (time-ordered)
  - `ulid` : ULID (Universally Unique Lexicographically Sortable Identifier)
- Possibilité de définir un générateur custom via une interface

### 8. Permettre la configuration du nom du header HTTP

- Configuration du nom du header HTTP utilisé pour l'ID de corrélation
- Valeur par défaut : `X-Correlation-ID`
- Appliqué aux requêtes entrantes, réponses sortantes et requêtes HTTP sortantes

### 9. Fournir un service pour accéder à l'ID de corrélation courant

- Un service injectable (`CorrelationIdStorage` ou similaire) doit être disponible
- Le service permet de récupérer l'ID de corrélation actuel
- Le service permet de définir manuellement un ID si nécessaire (cas avancés)
- Utilisation du `RequestStack` pour le stockage interne (compatible avec sub-requests)

### 10. Extensibilité

- Interface `CorrelationIdGeneratorInterface` pour créer des générateurs custom
- Événements dispatché :
  - `CorrelationIdGeneratedEvent` : Quand un nouvel ID est généré
  - `CorrelationIdRetrievedEvent` : Quand un ID est récupéré depuis le header

### 11. Gestion des sous-requêtes Symfony

- Les sub-requests (ESI, forward) doivent conserver le même ID de corrélation que la requête principale
- Pas de génération d'un nouvel ID pour les sub-requests

---

## Configuration par défaut

```yaml
correlation_id:
    # Nom du header HTTP
    header_name: 'X-Correlation-ID'
    
    # Générateur d'ID (uuid_v4, uuid_v7, ulid, ou service custom)
    generator: 'uuid_v4'
    
    # Validation des IDs entrants
    validation:
        enabled: true
        max_length: 255
        # Pattern regex pour valider le format (null = pas de validation pattern)
        pattern: null
    
    # Accepter l'ID du header entrant ou toujours générer un nouveau
    trust_header: true
    
    # Intégration Monolog
    monolog:
        enabled: true
        # Nom de la clé dans les logs
        key: 'correlation_id'
    
    # Propagation vers HttpClient
    http_client:
        enabled: true
    
    # Propagation via Messenger
    messenger:
        enabled: true
    
    # Configuration CLI
    cli:
        enabled: true
        prefix: 'CLI-'
        # Permettre --correlation-id en option
        allow_option: true
