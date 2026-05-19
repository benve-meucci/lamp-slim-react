<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BankController
{
    private const TRANSACTION_TYPES = ['deposit', 'withdrawal'];

    private function db(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $host = getenv('DB_HOST') ?: 'db';
        $database = getenv('DB_DATABASE') ?: 'bank';
        $username = getenv('DB_USERNAME') ?: 'bank';
        $password = getenv('DB_PASSWORD') ?: 'bank';

        $mysqli = new mysqli($host, $username, $password, $database);
        $mysqli->set_charset('utf8mb4');

        return $mysqli;
    }

    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function body(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }

        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function findAccount(mysqli $db, int $accountId): ?array
    {
        $stmt = $db->prepare('SELECT id, owner_name, currency, created_at FROM accounts WHERE id = ?');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();

        return $account ?: null;
    }

    private function currentBalance(mysqli $db, int $accountId): float
    {
        $stmt = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) AS balance
            FROM transactions
            WHERE account_id = ?
        ");
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return round((float) ($row['balance'] ?? 0), 2);
    }

    private function findTransaction(mysqli $db, int $accountId, int $transactionId): ?array
    {
        $stmt = $db->prepare('
            SELECT id, account_id, type, amount, description, balance_after, created_at
            FROM transactions
            WHERE account_id = ? AND id = ?
        ');
        $stmt->bind_param('ii', $accountId, $transactionId);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();

        return $transaction ?: null;
    }

    private function validateAmount(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $amount = round((float) $value, 2);
        return $amount > 0 ? $amount : null;
    }

    private function fetchJson(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/json\r\nUser-Agent: SlimMiniBankingAPI/1.0\r\n",
            ],
        ]);

        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, [
            'name' => 'Mini Banking API',
            'status' => 'ok',
            'endpoints' => [
                'GET /accounts/{id}/transactions',
                'GET /accounts/{id}/transactions/{transaction_id}',
                'POST /accounts/{id}/deposits',
                'POST /accounts/{id}/withdrawals',
                'PUT /accounts/{id}/transactions/{transaction_id}',
                'DELETE /accounts/{id}/transactions/{transaction_id}',
                'GET /accounts/{id}/balance',
                'GET /accounts/{id}/balance/convert/fiat?to=USD',
                'GET /accounts/{id}/balance/convert/crypto?to=BTC',
            ],
        ]);
    }

    public function listTransactions(Request $request, Response $response, array $args): Response
    {
        $accountId = (int) $args['id'];
        $db = $this->db();

        if (!$this->findAccount($db, $accountId)) {
            return $this->json($response, ['error' => 'Account not found'], 404);
        }

        $stmt = $db->prepare('
            SELECT id, account_id, type, amount, description, balance_after, created_at
            FROM transactions
            WHERE account_id = ?
            ORDER BY created_at DESC, id DESC
        ');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();

        return $this->json($response, [
            'account_id' => $accountId,
            'transactions' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
        ]);
    }

    public function showTransaction(Request $request, Response $response, array $args): Response
    {
        $accountId = (int) $args['id'];
        $transactionId = (int) $args['transaction_id'];
        $db = $this->db();

        if (!$this->findAccount($db, $accountId)) {
            return $this->json($response, ['error' => 'Account not found'], 404);
        }

        $transaction = $this->findTransaction($db, $accountId, $transactionId);
        if (!$transaction) {
            return $this->json($response, ['error' => 'Transaction not found'], 404);
        }

        return $this->json($response, ['transaction' => $transaction]);
    }

    public function createDeposit(Request $request, Response $response, array $args): Response
    {
        return $this->createTransaction($request, $response, (int) $args['id'], 'deposit');
    }

    public function createWithdrawal(Request $request, Response $response, array $args): Response
    {
        return $this->createTransaction($request, $response, (int) $args['id'], 'withdrawal');
    }

    private function createTransaction(Request $request, Response $response, int $accountId, string $type): Response
    {
        if (!in_array($type, self::TRANSACTION_TYPES, true)) {
            return $this->json($response, ['error' => 'Invalid transaction type'], 400);
        }

        $body = $this->body($request);
        $amount = $this->validateAmount($body['amount'] ?? null);
        $description = trim((string) ($body['description'] ?? ''));

        if ($amount === null) {
            return $this->json($response, ['error' => 'Amount must be greater than zero'], 400);
        }

        if ($description === '') {
            return $this->json($response, ['error' => 'Description is required'], 400);
        }

        $db = $this->db();
        $db->begin_transaction();

        try {
            $account = $this->findAccount($db, $accountId);
            if (!$account) {
                $db->rollback();
                return $this->json($response, ['error' => 'Account not found'], 404);
            }

            $balance = $this->currentBalance($db, $accountId);
            if ($type === 'withdrawal' && $amount > $balance) {
                $db->rollback();
                return $this->json($response, [
                    'error' => 'Insufficient funds',
                    'available_balance' => $balance,
                ], 422);
            }

            $balanceAfter = $type === 'deposit'
                ? round($balance + $amount, 2)
                : round($balance - $amount, 2);

            $stmt = $db->prepare('
                INSERT INTO transactions (account_id, type, amount, description, balance_after)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('isdsd', $accountId, $type, $amount, $description, $balanceAfter);
            $stmt->execute();

            $transactionId = $db->insert_id;
            $db->commit();

            return $this->json($response, [
                'transaction' => $this->findTransaction($db, $accountId, $transactionId),
            ], 201);
        } catch (Throwable $error) {
            $db->rollback();
            return $this->json($response, ['error' => 'Database error'], 500);
        }
    }

    public function updateTransaction(Request $request, Response $response, array $args): Response
    {
        $accountId = (int) $args['id'];
        $transactionId = (int) $args['transaction_id'];
        $description = trim((string) (($this->body($request))['description'] ?? ''));

        if ($description === '') {
            return $this->json($response, ['error' => 'Description is required'], 400);
        }

        $db = $this->db();
        if (!$this->findAccount($db, $accountId)) {
            return $this->json($response, ['error' => 'Account not found'], 404);
        }

        if (!$this->findTransaction($db, $accountId, $transactionId)) {
            return $this->json($response, ['error' => 'Transaction not found'], 404);
        }

        $stmt = $db->prepare('UPDATE transactions SET description = ? WHERE account_id = ? AND id = ?');
        $stmt->bind_param('sii', $description, $accountId, $transactionId);
        $stmt->execute();

        return $this->json($response, [
            'transaction' => $this->findTransaction($db, $accountId, $transactionId),
        ]);
    }

    public function deleteTransaction(Request $request, Response $response, array $args): Response
    {
        $accountId = (int) $args['id'];
        $transactionId = (int) $args['transaction_id'];
        $db = $this->db();

        if (!$this->findAccount($db, $accountId)) {
            return $this->json($response, ['error' => 'Account not found'], 404);
        }

        $transaction = $this->findTransaction($db, $accountId, $transactionId);
        if (!$transaction) {
            return $this->json($response, ['error' => 'Transaction not found'], 404);
        }

        $stmt = $db->prepare('SELECT id FROM transactions WHERE account_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $last = $stmt->get_result()->fetch_assoc();

        if (!$last || (int) $last['id'] !== $transactionId) {
            return $this->json($response, [
                'error' => 'Only the last transaction can be deleted',
            ], 422);
        }

        $stmt = $db->prepare('DELETE FROM transactions WHERE account_id = ? AND id = ?');
        $stmt->bind_param('ii', $accountId, $transactionId);
        $stmt->execute();

        return $this->json($response, [
            'deleted' => true,
            'transaction_id' => $transactionId,
        ]);
    }

    public function balance(Request $request, Response $response, array $args): Response
    {
        $accountId = (int) $args['id'];
        $db = $this->db();
        $account = $this->findAccount($db, $accountId);

        if (!$account) {
            return $this->json($response, ['error' => 'Account not found'], 404);
        }

        return $this->json($response, [
            'account_id' => $accountId,
            'currency' => strtoupper($account['currency']),
            'balance' => $this->currentBalance($db, $accountId),
        ]);
    }

    public function convertFiat(Request $request, Response $response, array $args): Response
    {
        $accountId = (int) $args['id'];
        $to = strtoupper((string) (($request->getQueryParams())['to'] ?? ''));

        if (!preg_match('/^[A-Z]{3}$/', $to)) {
            return $this->json($response, ['error' => 'Target currency is required'], 400);
        }

        $db = $this->db();
        $account = $this->findAccount($db, $accountId);
        if (!$account) {
            return $this->json($response, ['error' => 'Account not found'], 404);
        }

        $from = strtoupper($account['currency']);
        $balance = $this->currentBalance($db, $accountId);
        $url = 'https://api.frankfurter.dev/v1/latest?' . http_build_query([
            'base' => $from,
            'symbols' => $to,
        ]);
        $data = $this->fetchJson($url);

        if ($data === null) {
            return $this->json($response, ['error' => 'Frankfurter API unavailable'], 502);
        }

        if (!isset($data['rates'][$to])) {
            return $this->json($response, ['error' => 'Target currency not supported'], 400);
        }

        $rate = (float) $data['rates'][$to];

        return $this->json($response, [
            'account_id' => $accountId,
            'provider' => 'Frankfurter',
            'conversion_type' => 'fiat',
            'from_currency' => $from,
            'to_currency' => $to,
            'original_balance' => $balance,
            'rate' => $rate,
            'converted_balance' => round($balance * $rate, 2),
            'date' => $data['date'] ?? null,
        ]);
    }

    public function convertCrypto(Request $request, Response $response, array $args): Response
    {
        $accountId = (int) $args['id'];
        $to = strtoupper((string) (($request->getQueryParams())['to'] ?? ''));

        if (!preg_match('/^[A-Z0-9]{2,10}$/', $to)) {
            return $this->json($response, ['error' => 'Target crypto is required'], 400);
        }

        $db = $this->db();
        $account = $this->findAccount($db, $accountId);
        if (!$account) {
            return $this->json($response, ['error' => 'Account not found'], 404);
        }

        $from = strtoupper($account['currency']);
        $symbol = $to . $from;
        $exchangeInfo = $this->fetchJson('https://api.binance.com/api/v3/exchangeInfo?' . http_build_query([
            'symbol' => $symbol,
        ]));

        if ($exchangeInfo === null) {
            return $this->json($response, ['error' => 'Binance API unavailable'], 502);
        }

        $market = $exchangeInfo['symbols'][0] ?? null;
        if (!$market || ($market['status'] ?? '') !== 'TRADING') {
            return $this->json($response, [
                'error' => 'Crypto market pair not supported',
                'market_symbol' => $symbol,
            ], 400);
        }

        $ticker = $this->fetchJson('https://api.binance.com/api/v3/ticker/price?' . http_build_query([
            'symbol' => $symbol,
        ]));

        if ($ticker === null || !isset($ticker['price'])) {
            return $this->json($response, ['error' => 'Binance price unavailable'], 502);
        }

        $balance = $this->currentBalance($db, $accountId);
        $price = (float) $ticker['price'];

        if ($price <= 0) {
            return $this->json($response, ['error' => 'Invalid Binance price'], 502);
        }

        return $this->json($response, [
            'account_id' => $accountId,
            'provider' => 'Binance',
            'conversion_type' => 'crypto',
            'from_currency' => $from,
            'to_crypto' => $to,
            'market_symbol' => $symbol,
            'original_balance' => $balance,
            'price' => $price,
            'converted_amount' => round($balance / $price, 8),
        ]);
    }
}
