# Mini Banking API

Backend REST didattico in PHP/Slim per l'esercitazione descritta in `MiniBankingAPI.md`.

Non contiene frontend: gli studenti useranno questi endpoint JSON per costruire un frontend Angular in laboratorio.

## Avvio locale

Serve Docker Desktop oppure Docker Engine con il plugin Compose.

Il progetto funziona anche senza file `.env`, usando i valori predefiniti del `docker-compose.yaml`. Per personalizzare porte, credenziali o CORS:

```bash
cp .env.example .env
```

Poi avvia:

```bash
docker compose up --build
```

Endpoint base:

- API: http://localhost:8080
- Health check: http://localhost:8080/up
- phpMyAdmin opzionale: http://localhost:8081

Per usare phpMyAdmin:

```bash
docker compose --profile tools up --build
```

Credenziali database didattiche predefinite:

- Server: `db`
- Database: `bank`
- Utente: `bank`
- Password: `bank`

## Reset completo

Per ricreare database e dipendenze installate nei volumi Docker:

```bash
docker compose down -v
docker compose up --build
```

## Endpoint

Gli endpoint sono disponibili sia senza prefisso sia con prefisso `/api`, per esempio:

- `GET /accounts/1/balance`
- `GET /api/accounts/1/balance`

Movimenti:

- `GET /accounts/1/transactions`
- `GET /accounts/1/transactions/5`
- `POST /accounts/1/deposits`
- `POST /accounts/1/withdrawals`
- `PUT /accounts/1/transactions/5`
- `DELETE /accounts/1/transactions/5`

Saldo e conversioni:

- `GET /accounts/1/balance`
- `GET /accounts/1/balance/convert/fiat?to=USD`
- `GET /accounts/1/balance/convert/crypto?to=BTC`

## Esempi di chiamata

Lista movimenti:

```bash
curl http://localhost:8080/accounts/1/transactions
```

Dettaglio movimento:

```bash
curl http://localhost:8080/accounts/1/transactions/1
```

Deposito:

```bash
curl -X POST http://localhost:8080/accounts/1/deposits \
  -H 'Content-Type: application/json' \
  -d '{"amount": 50, "description": "Versamento laboratorio"}'
```

Prelievo:

```bash
curl -X POST http://localhost:8080/accounts/1/withdrawals \
  -H 'Content-Type: application/json' \
  -d '{"amount": 20, "description": "Acquisto materiale"}'
```

Modifica descrizione:

```bash
curl -X PUT http://localhost:8080/accounts/1/transactions/1 \
  -H 'Content-Type: application/json' \
  -d '{"description": "Descrizione aggiornata"}'
```

Eliminazione:

```bash
curl -X DELETE http://localhost:8080/accounts/1/transactions/3
```

Regola scelta: si puo' eliminare solo l'ultimo movimento del conto, così non si invalidano i saldi intermedi salvati in `balance_after`.

Saldo:

```bash
curl http://localhost:8080/accounts/1/balance
```

Conversione fiat con Frankfurter:

```bash
curl 'http://localhost:8080/accounts/1/balance/convert/fiat?to=USD'
```

Conversione crypto con Binance:

```bash
curl 'http://localhost:8080/accounts/1/balance/convert/crypto?to=BTC'
```

## CORS per Angular

Per default il backend risponde con:

```env
CORS_ORIGIN=*
```

In laboratorio puoi limitarlo al dev server Angular:

```env
CORS_ORIGIN=http://localhost:4200
```

## Schema database

Lo schema e i dati iniziali sono in `build/init.sql`.

Tabelle:

- `accounts`: conto bancario semplificato
- `transactions`: depositi e prelievi con `balance_after`

Il database iniziale crea il conto `1` in EUR con alcuni movimenti di esempio.

## Deploy con Kamal e GHCR

Il deploy usa Kamal 2 solo per il backend Slim. MariaDB gira come accessory persistente.

Prerequisiti:

- un server Linux raggiungibile via SSH
- Docker installabile o gia' installato sul server
- un account GitHub con permessi per pubblicare su GHCR
- Kamal installato in locale: `gem install kamal`

Prepara le variabili:

```bash
cp .env.example .env
```

Poi modifica `.env` impostando almeno:

- `KAMAL_HOST`: IP o hostname del server
- `KAMAL_IMAGE`: immagine GHCR senza prefisso registry, per esempio `tuo-utente-github/mini-banking-api`
- `KAMAL_REGISTRY_SERVER=ghcr.io`
- `KAMAL_REGISTRY_USERNAME`: utente GitHub
- `KAMAL_REGISTRY_PASSWORD`: token GitHub con permesso `write:packages`
- `DB_PASSWORD` e `DB_ROOT_PASSWORD`: password del database in produzione

Se hai un dominio che punta al server, imposta anche `KAMAL_DOMAIN`. In quel caso Kamal abilita HTTPS automatico con Let's Encrypt.

Primo deploy:

```bash
kamal setup
```

Deploy successivi:

```bash
kamal deploy
```

Comandi utili:

```bash
kamal app logs
kamal accessory logs db
kamal accessory exec db "mariadb -ubank -p"
```

Dopo il deploy, l'API sara' disponibile su:

- `https://KAMAL_DOMAIN/accounts/1/balance`, se hai impostato `KAMAL_DOMAIN`
- `http://KAMAL_HOST/accounts/1/balance`, se usi solo l'IP del server

## Struttura

- `php`: API PHP con Slim
- `build/init.sql`: schema e dati iniziali MariaDB
- `build/Dockerfile.php`: immagine locale per sviluppo
- `build/Dockerfile.deploy`: immagine production per Kamal
- `config/deploy.yml`: configurazione Kamal

Database e `vendor` vivono in volumi Docker, quindi non sporcano il repository.
