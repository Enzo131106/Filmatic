# Filmatic

Filmatic est une application web réalisée en équipe dans le cadre d'une SAÉ du BUT Métiers du Multimédia et de l'Internet à l'IUT de Tarbes. Elle permet d'organiser des séances de cinéma et de choisir collectivement les films et les lieux de projection.

## Aperçu

![Page d'accueil de Filmatic avec le carrousel des événements](assets/img/filmatic-demo.webp)

*Aperçu de démonstration de la page d'accueil avec des événements fictifs, réalisé à partir d'une version statique de l'interface.*

## Fonctionnalités

- Inscription, connexion et profils.
- Création de séances par les organisateurs et administrateurs.
- Vote par classement (méthode de Borda) pour les films et les lieux.
- Notation des séances et consultation des résultats.
- Boîte à idées, modération et gestion des demandes de rôle.
- Administration des utilisateurs et des événements.

## Technologies

PHP, PDO, MySQL ou MariaDB, HTML, CSS, JavaScript, Bootstrap, jQuery et jQuery UI. Certaines bibliothèques et polices sont chargées depuis des services externes : une connexion Internet est nécessaire pour retrouver l'apparence et les interactions complètes.

## Installation locale

Prérequis : PHP 8.2 ou supérieur avec PDO et pdo_mysql, MySQL ou MariaDB, et un navigateur. Un environnement local comme XAMPP ou WampServer convient également.

1. Cloner le dépôt ou télécharger son archive ZIP et la décompresser.
2. Importer `database/filmatic_db.sql` depuis phpMyAdmin ou avec `mysql -u root -p < database/filmatic_db.sql` (adapter le compte à votre installation). Le script crée le schéma sans supprimer les tables existantes. Utiliser une base neuve.
3. Créer un compte MySQL local dédié à Filmatic, disposant des droits SELECT, INSERT, UPDATE et DELETE sur `filmatic_db.*`.
4. Copier `config.example.php` vers `config.local.php`, puis renseigner les paramètres de votre base. Ce fichier est ignoré par Git.
5. Depuis la racine du projet, lancer `php -S 127.0.0.1:8000`, puis ouvrir <http://127.0.0.1:8000/>. Avec XAMPP ou WampServer, placer le dossier dans le répertoire web local et ouvrir son URL locale.
6. Créer un compte de démonstration via l'inscription. Pour tester l'administration, attribuer le rôle admin à ce compte depuis la console SQL ou phpMyAdmin (remplacer le pseudo ci-dessous par le vôtre) :

```sql
UPDATE users SET role = 'admin' WHERE username = 'demo_admin';
```

Se déconnecter puis se reconnecter. Aucun compte ni mot de passe de démonstration n'est fourni par défaut.

## Organisation

```text
admin/          Administration
assets/         Feuille de style, logo et capture de démonstration
database/       Schéma SQL vierge
recruitements/  Demandes de rôle
db.php          Connexion PDO
security.php    Sessions et protection des formulaires
config.example.php
index.php       Accueil
```

## Auteurs et droits

Projet collectif réalisé dans le cadre d'une SAÉ. Les droits sur les contributions originales appartiennent à leurs auteurs respectifs. Consulter le fichier LICENSE. Les bibliothèques tierces restent soumises à leurs licences respectives.

## Périmètre

Projet pédagogique présenté comme une démonstration locale, non hébergé en production. Utiliser uniquement des données fictives. La mise en production nécessiterait des tests fonctionnels et un audit de sécurité complémentaires.
