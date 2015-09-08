Suivi OpenSource JobScheduler 
=============================


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