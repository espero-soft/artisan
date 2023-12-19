# espero-soft/artisan

Un package Laravel pour générer des entités et des CRUD.

## Installation

Exécutez la commande suivante pour installer le package :

```bash
composer require espero-soft/artisan:dev-main
```

Publiez les fichiers de configuration avec :

```bash
    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        // Registering the MakeEntityCommand as a ClosureCommand
        $this->getArtisan()->add( new MakeEntityCommand() );
        // Registering the MakeCrudCommand as a ClosureCommand
        $this->getArtisan()->add( new MakeCrudCommand() );

        require base_path('routes/console.php');
    }
```


## Utilisation

### Générer une Entité

Pour générer une nouvelle entité, utilisez la commande suivante :

```bash
php artisan make:entity NomDeVotreEntite
```

### Générer un CRUD

Pour créer un CRUD complet pour une entité existante, utilisez la commande suivante :

```bash
php artisan make:crud NomDeVotreEntite
```

## Exemples

Voici quelques exemples d'utilisation :

```bash
php artisan make:entity Post
php artisan make:crud Post
```

## Contribuer

Toute contribution est la bienvenue ! Si vous souhaitez améliorer ce package, veuillez ouvrir une issue pour discuter des changements proposés.

## Auteur

[Espero-Soft Informatiques](https://github.com/espero-soft/artisan)

## Licence

Ce package est sous licence MIT. Consultez le fichier `LICENSE` pour plus de détails.
