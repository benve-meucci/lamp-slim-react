# Esercitazione: Mini Banking API

## Scenario

Dovete realizzare un backend REST che simuli un **conto bancario semplificato**.

Non serve alcun frontend: il progetto deve esporre endpoint HTTP che restituiscono **JSON**.

---

## Obiettivo finale

Alla fine dell'esercitazione l'applicazione deve permettere di:

1. registrare **depositi**
2. registrare **prelievi**
3. visualizzare la **lista dei movimenti**
4. visualizzare il **dettaglio di un movimento**
5. calcolare il **saldo attuale**
6. convertire il saldo in una **valuta fiat** usando **Frankfurter**
7. convertire il saldo in una **criptovaluta** usando **Binance**

---

## Modalità di lavoro

- gruppi di **2 o 3 studenti**
- tecnologie richieste:
  - **Slim**
  - **MySQL** oppure **MariaDB**
  - risposte in formato **JSON**

---

## Impostazione consigliata

Per rendere il lavoro più lineare:

- potete gestire anche **un solo conto**
- è consigliato usare, per la parte obbligatoria, **un conto in EUR**
- potete comunque mantenere nel database il campo `currency`

Questa scelta semplifica soprattutto la parte di conversione crypto.

---

## Percorso di lavoro consigliato

Per non bloccarvi, affrontate il progetto in questo ordine:

1. create database e tabelle
2. realizzate depositi e prelievi
3. calcolate il saldo
4. aggiungete la lista e il dettaglio dei movimenti
5. aggiungete la conversione in valuta fiat con Frankfurter
6. aggiungete la conversione in crypto con Binance
7. completate validazioni, gestione errori e pulizia delle risposte JSON

---

## Modello dati suggerito

### Tabella `accounts`

Campi minimi consigliati:

- `id`
- `owner_name`
- `currency`
- `created_at`

### Tabella `transactions`

Campi minimi consigliati:

- `id`
- `account_id`
- `type` (`deposit` oppure `withdrawal`)
- `amount`
- `description`
- `created_at`

### Campo opzionale utile

Potete aggiungere anche:

- `balance_after`

Questo campo salva il saldo risultante dopo ogni operazione e può aiutarvi nei controlli.

---

## Regole di business

### Deposito

- l'importo deve essere maggiore di zero

### Prelievo

- l'importo deve essere maggiore di zero
- non si può prelevare più del saldo disponibile

### Saldo

Il saldo **non** deve essere inserito manualmente.

Deve essere calcolato come:

- somma dei depositi
- meno somma dei prelievi

### Modifica dei movimenti

Per evitare incoerenze, è consigliato permettere con `PUT` solo la modifica di campi descrittivi, ad esempio:

- `description`

### Eliminazione dei movimenti

Potete scegliere una regola semplice ma coerente, ad esempio:

- si può eliminare solo l'ultimo movimento
- oppure si può eliminare un movimento solo se il saldo finale rimane valido

L'importante è che la regola sia chiara e rispettata dal codice.

---

## Endpoint richiesti

### Movimenti

- `GET /accounts/1/transactions` per ottenere l'elenco dei movimenti
- `GET /accounts/1/transactions/5` per ottenere il dettaglio di un movimento
- `POST /accounts/1/deposits` per registrare un deposito
- `POST /accounts/1/withdrawals` per registrare un prelievo
- `PUT /accounts/1/transactions/5` per modificare la descrizione di un movimento
- `DELETE /accounts/1/transactions/5` per eliminare un movimento secondo la regola scelta

### Saldo

- `GET /accounts/1/balance` per ottenere il saldo attuale

### Conversione del saldo

- `GET /accounts/1/balance/convert/fiat?to=USD` per convertire il saldo in una valuta fiat
- `GET /accounts/1/balance/convert/crypto?to=BTC` per convertire il saldo in una criptovaluta

Potete scegliere nomi leggermente diversi per gli endpoint, ma la struttura deve rimanere chiara e coerente.

---

## Dati minimi da accettare

### Deposito e prelievo

Nel body JSON devono esserci almeno:

- `amount`
- `description`

### Modifica movimento

Nel body JSON è sufficiente:

- `description`

---

## Conversione fiat con Frankfurter

Per questa parte dovete:

1. calcolare il saldo del conto nel database
2. leggere la valuta di partenza del conto
3. leggere il parametro `to`
4. chiedere a Frankfurter il tasso di cambio aggiornato
5. moltiplicare il saldo per il tasso ottenuto
6. restituire il risultato in JSON

### Regole consigliate

- accettate solo codici valuta realmente supportati
- se il parametro `to` manca o non è valido, restituite errore
- il saldo convertito in valuta fiat può essere arrotondato a **2 decimali**

### Campi utili nella risposta

- `account_id`
- `provider`
- `conversion_type`
- `from_currency`
- `to_currency`
- `original_balance`
- `rate`
- `converted_balance`
- `date`

---

## Conversione crypto con Binance

Per questa parte dovete:

1. calcolare il saldo del conto nel database
2. leggere il parametro `to`, che rappresenta la crypto richiesta, ad esempio `BTC` o `ETH`
3. costruire la coppia di mercato Binance usando la crypto come base e la valuta del conto come quote
4. verificare che la coppia esista e sia utilizzabile
5. recuperare il prezzo corrente della crypto
6. convertire il saldo del conto nella quantità di crypto corrispondente
7. restituire il risultato in JSON

### Esempio logico di conversione

Se il conto è in `EUR` e volete convertire il saldo in `BTC`:

- la coppia di mercato da cercare è `BTCEUR`
- il prezzo indica quanto costa **1 BTC** in EUR
- la quantità di BTC ottenibile si calcola dividendo il saldo in EUR per il prezzo di `BTCEUR`

### Regole consigliate

- è sufficiente supportare solo le crypto per cui esiste una coppia attiva con la valuta del conto
- se la coppia non esiste, restituite un errore chiaro
- prima di leggere il prezzo, controllate che il simbolo sia presente tra quelli disponibili
- per la quantità crypto potete usare fino a **8 decimali**

### Campi utili nella risposta

- `account_id`
- `provider`
- `conversion_type`
- `from_currency`
- `to_crypto`
- `market_symbol`
- `original_balance`
- `price`
- `converted_amount`

---

## Esempio di implementazione in Slim

Di seguito trovate un esempio unico e completo di endpoint Slim per la conversione del saldo in una valuta fiat con Frankfurter.

L'esempio mostra insieme:

- lettura del parametro `to`
- recupero del conto dal database
- calcolo del saldo
- chiamata HTTP verso una API esterna
- gestione degli errori principali
- risposta JSON finale

Per la parte **crypto con Binance**, invece, dovete costruire voi la chiamata leggendo la documentazione ufficiale e adattando la stessa logica vista qui.

Nello snippet si assume di avere gia una connessione `mysqli` disponibile nella variabile `$mysqli`.

```php
<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/accounts/{id}/balance/convert/fiat', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $params = $request->getQueryParams();
    $to = strtoupper($params['to'] ?? '');

    if (!$to) {
        $response->getBody()->write(json_encode([
            'error' => 'Missing target currency'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    $stmt = $mysqli->prepare('SELECT id, currency FROM accounts WHERE id = ?');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();

    if (!$account) {
        $response->getBody()->write(json_encode([
            'error' => 'Account not found'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
    }

    $from = strtoupper($account['currency']);

    $stmt = $mysqli->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) AS balance
        FROM transactions
        WHERE account_id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $balance = (float)($row['balance'] ?? 0);

    $url = "https://api.frankfurter.dev/v1/latest?base={$from}&symbols={$to}";
    $json = @file_get_contents($url);

    if ($json === false) {
        $response->getBody()->write(json_encode([
            'error' => 'External exchange API unavailable'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(502);
    }

    $data = json_decode($json, true);

    if (!isset($data['rates'][$to])) {
        $response->getBody()->write(json_encode([
            'error' => 'Target currency not supported'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    $rate = (float)$data['rates'][$to];
    $converted = round($balance * $rate, 2);

    $response->getBody()->write(json_encode([
        'account_id' => $accountId,
        'provider' => 'Frankfurter',
        'conversion_type' => 'fiat',
        'from_currency' => $from,
        'to_currency' => $to,
        'original_balance' => $balance,
        'converted_balance' => $converted,
        'rate' => $rate,
        'date' => $data['date'] ?? null
    ]));

    return $response->withHeader('Content-Type', 'application/json');
});
```

### Come riusare questo schema negli altri endpoint

- `400` se manca un parametro o il client manda un dato non valido
- `404` se il conto o il movimento non esistono
- `422` se il dato è valido come formato ma viola una regola di business, ad esempio un prelievo troppo alto
- `502` se fallisce la chiamata a una API esterna

---

## Errori da gestire

### `400 Bad Request`

- importo mancante
- importo non valido
- valuta target mancante
- valuta fiat non supportata
- crypto target non supportata
- coppia Binance non valida

### `404 Not Found`

- conto non trovato
- movimento non trovato

### `422 Unprocessable Entity` oppure `400 Bad Request`

- prelievo superiore al saldo disponibile

### `502 Bad Gateway`

- errore nella chiamata a Frankfurter
- errore nella chiamata a Binance

---

## Documentazione ufficiale da usare

Per questa esercitazione dovete consultare la documentazione ufficiale dei servizi esterni.

### Frankfurter

- Documentazione generale: [https://frankfurter.dev/](https://frankfurter.dev/)
- Endpoint valuta disponibili: [https://api.frankfurter.dev/v1/currencies](https://api.frankfurter.dev/v1/currencies)
- Endpoint tassi aggiornati: [https://api.frankfurter.dev/v1/latest](https://api.frankfurter.dev/v1/latest)

### Binance Spot API

- Informazioni generali REST: [https://developers.binance.com/docs/binance-spot-api-docs/rest-api](https://developers.binance.com/docs/binance-spot-api-docs/rest-api)
- Endpoint generali, inclusa `exchangeInfo`: [https://developers.binance.com/docs/binance-spot-api-docs/rest-api/general-endpoints](https://developers.binance.com/docs/binance-spot-api-docs/rest-api/general-endpoints)
- Endpoint market data, incluso `ticker/price`: [https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints](https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints)

Potete partire dagli snippet di questa traccia e usare la documentazione ufficiale per capire meglio **endpoint**, **parametri**, **risposte** e **vincoli**.

---

## Cosa consegnare

Ogni gruppo deve consegnare:

1. il codice del progetto
2. lo schema del database
3. l'elenco degli endpoint realizzati
4. almeno un esempio di chiamata per ogni endpoint
5. una breve spiegazione delle scelte progettuali

---

## Criteri di valutazione

Saranno valutati soprattutto:

- correttezza degli endpoint REST
- qualità del modello dati
- correttezza della logica di business
- uso corretto del database
- integrazione con Frankfurter
- integrazione con Binance
- gestione degli errori
- chiarezza del JSON restituito

---

## Suggerimento finale

È meglio realizzare **pochi endpoint ma solidi e coerenti** che molti endpoint incompleti.

Chi completa prima la parte base può migliorare:

- validazioni
- messaggi di errore
- struttura delle risposte JSON
- supporto a più valute o più crypto
