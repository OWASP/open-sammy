# SAMMY Open Source v2

This repository hosts the open source version of SAMMY - the OWASP SAMM tool.

# License

This project is licensed under the Creative Commons Attribution-ShareAlike 4.0 International License. See the [LICENSE](LICENSE) file for details.

# SAMMY v2

* The setup comes with a predefined user injected in the database:
    - username: admin@example.com
    - password: admin
    - mfa key: AB4FHDUHYVGW7IAB (add this key to your authenticator app manually)

# How to run it without docker

## Requirements

* MySQL or MariaDB
* Redis (only if you want to run it in `APP_ENV=prod`)
* php8.2+
* composer

## Optional

* Symfony CLI (https://symfony.com/download)

## How to run it

1. Create `.env.local` file with your local setup. Example with MariaDB:
    - in case of any other DB here you can find proper connection
      string - https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url

```dotenv
DATABASE_URL=mysql://root:root@127.0.0.1:3306/sammy?serverVersion=11.3.2-MariaDB
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
APP_ENV=dev
APP_DEBUG=1
```

2. install & run

```bash
composer install
./scripts/setup_database.sh
# if you have symfony cli
symfony server:start --allow-http
# else
php -S 0.0.0.0:8000 -t ./public
open http://127.0.0.1:8000
```

# How to run it with docker

```bash
# 1. start DB and Redis
docker compose up -d db redis
# 2. now we can start our application
docker compose up -d --build app
# 3. sync SAMM model. Note, this step syncs the SAMM model from the core GitHub repo. You only have to run this the very first time and upon every SAMM model update.
docker compose exec app ./scripts/sync_samm.sh
# 4. sync DSOMM model. Note, this step syncs the DSOMM model from the core GitHub repo. You only have to run this the very first time and upon every DSOMM model update.
docker compose exec app ./scripts/sync_dsomm.sh
# 5. Enjoy
open http://127.0.0.1:8000
```

# Mailing

* If you want to use mailing feature you have to add following to your `.env.local` or `compose.yaml` file. All fields are Required. Also, server
  should use proper SSL by default.
    - for `.env.lcoal`
      ```dotenv
        PHPMAILER_SMTP_HOST=
        PHPMAILER_SMTP_PORT=
        PHPMAILER_SMTP_USERNAME=
        PHPMAILER_SMTP_PASSWORD=
        PHPMAILER_SMTP_DEFAULT_SENDER=
        PHPMAILER_SMTP_USE_AUTH=
        PHPMAILER_SMTP_DEFAULT_ENCRYPTION=
      ```
        - for `compose.yaml` under `app` section under `environment`
      ```yaml
      - PHPMAILER_SMTP_HOST=
      - PHPMAILER_SMTP_PORT=
      - PHPMAILER_SMTP_USERNAME=
      - PHPMAILER_SMTP_PASSWORD=
      - PHPMAILER_SMTP_DEFAULT_SENDER=
      - PHPMAILER_SMTP_USE_AUTH=
      - PHPMAILER_SMTP_DEFAULT_ENCRYPTION=
      ```
* If you are using docker you do not have to do anything else. There is a cronjob which runs every 2 minutes.
* If you are running this locally you have to run following command manually:

```bash
php ./bin/console app:process-mailing
```

# DSOMM Support
We have support for assessments for DSOMM model. By default you will have DSOMM model in your database with the needed things. If you wish you can import custom DSOMM variation. For example you can have model with more/less domains and practices. You can have many DSOMM variations simultaneously. 
````
php ./bin/console app:sync-from-dsomm --source="path/to/dsomm-file.yaml"
````
Optionally you can pass metamodel ID if you wish to perform updates to existing DSOMM variation
````
php ./bin/console app:sync-from-dsomm --source="path/to/dsomm-file.yaml" --metamodel=33
````

We expect single YAML file with all the data structure as below. By default we will use this file
https://github.com/devsecopsmaturitymodel/DevSecOps-MaturityModel-data/blob/main/src/assets/YAML/generated/generated.yaml