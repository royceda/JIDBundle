Suivi OpenSource JobScheduler 
=============================

Ce module permet de suivre les traitements JobScheduler en publiant directement les informations stockées par ce dernier dans la base de données. Il permet:
- d'avoir les mêmes fonctionnalités que le client lourd "Dashboard"
- de disposer des informations en temps réel sans consommation des ressources du JobScheduler
- d'avoir une vue globale ou, au contraire, filtrée de ces traitements
- d'agir sur les traitements en fonction du rôle de l'utilisateur

Comme son nom l'indique, le suivi ne traiote que les jobs terminés ou en cours, pour avoir une vision du plan courant, il faut ajouter le module Plan.

Pré-requis
----------
Modules:
- Core
- User

Configuration
-------------
Le suivi utilise les données envoyées directement par JobScheduler permettant un suivi en temps réel.

### Lecture des informations dans la base de données

Contenu de **app/config/parameters.yml**:

    repository_name:     Test SOS-Paris
    repository_dbname:   scheduler
    repository_host:     %database_host%
    repository_port:     %database_port%
    repository_user:     %database_user%
    repository_password: %database_password%
    repository_driver:   %database_driver%

__v1.5.0__