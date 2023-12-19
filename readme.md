```markdown
# espero-soft/artisan

Un package Laravel pour générer des entités et des CRUD.

## Installation

Pour installer le package, exécutez la commande suivante :

```bash
composer require espero-soft/artisan:dev-main
```

## Utilisation

### Générer une Entité

Pour créer une nouvelle entité, utilisez la commande suivante :

```bash
php artisan make:entity NomDeVotreEntite
```

### Générer un CRUD

Pour générer un CRUD complet pour une entité existante, utilisez la commande suivante :

```bash
php artisan make:crud NomDeVotreEntite
```

## Exemples

Quelques exemples d'utilisation :

```bash
php artisan make:entity Post
php artisan make:crud Post
```

## Contribuer

Les contributions sont les bienvenues ! Si vous souhaitez améliorer ce package, veuillez ouvrir une issue pour discuter des changements proposés.

## Auteur

[Espero-Soft Informatiques](https://github.com/espero-soft/artisan)

## Licence

Ce package est sous licence MIT. Consultez le fichier `LICENSE` pour plus de détails.
