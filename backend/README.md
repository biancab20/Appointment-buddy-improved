## Setup

1. Install Docker Desktop on Windows or Mac, or Docker Engine on Linux.
1. Clone the project

## Usage

1. Navigate to the backend directory:
```bash
cd backend
```

In a terminal, from the cloned project folder, run:
```bash
docker compose up
```

### Composer Autoload

This template is configured to use Composer for PSR-4 autoloading:

- Namespace `App\\` is mapped to `app/src/`.

To install dependencies and generate the autoloader, run:

```bash
docker compose run --rm php composer install
```

If you add new classes or change namespaces, regenerate the autoloader:

```bash
docker compose run --rm php composer dump-autoload
```

### Stopping the docker container

If you want to stop the containers, press Ctrl+C. 

Or run:
```bash
docker compose down
```

## Credentials

For testing purposes, use the following credentials:

    Tutor account: 
        Email: tutor@example.com
        Password: password

    Admin account:
        Email: admin@example.com
        Password: password

## Notes

- I created a DatabaseSeeder, therefore I assumed a database export is no longer necessary. Just start the project and you'll have already testing data :)