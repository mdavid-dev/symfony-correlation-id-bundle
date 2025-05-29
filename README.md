En tant que développeur Symfony, je souhaite un bundle qui gère automatiquement l'ID de corrélation pour mes requêtes afin de faciliter le suivi et le débogage dans mon application et potentiellement à travers plusieurs services.

Pour cela, le bundle devra :

1.  **Gérer l'ID de corrélation pour les requêtes HTTP :**
    * Si une requête entrante contient un header d'ID de corrélation, le bundle doit le récupérer et le rendre disponible.
    * Si la requête entrante ne contient pas d'ID, le bundle doit en générer un.
    * L'ID de corrélation courant doit être propagé dans la réponse HTTP via le même header.

2.  **Être facile à installer :**
    * Le bundle doit s'installer via Composer sans nécessiter de configuration manuelle complexe (la configuration se fera principalement via un fichier YAML).

3.  **Permettre la configuration du format de l'ID :**
    * Je dois pouvoir configurer le format de l'ID de corrélation (par exemple, UUID, incrémental) via un fichier YAML.

4.  **S'intégrer avec Monolog :**
    * Le bundle doit modifier la sortie de Monolog pour inclure l'ID de corrélation dans les logs.
    * Je dois pouvoir configurer le format de la sortie de Monolog (par exemple, le champ où l'ID est ajouté) via le fichier YAML.

5.  **Propager l'ID de corrélation via Symfony Messenger :**
    * Lors de l'envoi d'un message via Messenger, l'ID de corrélation courant doit être automatiquement propagé avec le message (via un Stamp).
    * Lors de la réception d'un message, l'ID de corrélation (s'il est présent dans le Stamp) doit être rendu disponible pour le contexte de traitement du message.

6.  **Permettre la configuration du nom du header HTTP :**
    * Je dois pouvoir configurer le nom du header HTTP utilisé pour l'ID de corrélation via le fichier YAML.

7.  **Fournir un service pour accéder à l'ID de corrélation courant :**
    * Un service injectable doit être disponible pour que je puisse récupérer l'ID de corrélation actuel dans n'importe quel service de mon application.

**Critères d'acceptation (par fonctionnalité) :**

1.  **Gestion HTTP :**
    * Vérifier que si un header est présent dans la requête, il est utilisé.
    * Vérifier que si aucun header n'est présent, un ID est généré.
    * Vérifier que l'ID courant est présent dans l'en-tête de la réponse.

2.  **Installation :**
    * L'installation via `composer require` ne doit pas nécessiter d'étapes manuelles supplémentaires pour activer les fonctionnalités de base.

3.  **Format de l'ID :**
    * La configuration YAML permet de choisir différents formats d'ID.
    * Les ID générés correspondent au format configuré.

4.  **Intégration Monolog :**
    * Les logs contiennent l'ID de corrélation dans le format configuré.
    * La configuration YAML permet de définir le format de sortie de Monolog lié à l'ID.

5.  **Intégration Messenger :**
    * Les messages envoyés via Messenger contiennent un Stamp avec l'ID de corrélation.
    * L'ID de corrélation du Stamp est accessible lors du traitement du message.

6.  **Nom du header HTTP :**
    * La configuration YAML permet de définir le nom du header HTTP utilisé.
    * L'ID de corrélation est lu et écrit dans le header configuré.

7.  **Service d'accès :**
    * Un service peut être injecté pour récupérer l'ID de corrélation courant.
    * L'ID retourné par le service correspond à l'ID de la requête courante ou à celui généré.
