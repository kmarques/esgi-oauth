<?php

function checkOrCreate(string $filename)
{
    if (!file_exists($filename)) {
        touch($filename);
    }
}

function readDatabase(string $filename): array
{
    checkOrCreate($filename);
    return array_map(fn ($item) => json_decode($item, true), file($filename));
}

function writeDatabase(string $filename, array $data): void
{
    checkOrCreate($filename);
    file_put_contents(
        $filename,
        implode("\n", array_map(fn ($item) => json_encode($item), $data))
    );
}

function findRow(string $filename, array $criteria, $limit): array
{
    $result = array_filter(
        readDatabase($filename),
        fn ($item) => count(array_intersect_assoc($item, $criteria)) === count($criteria)
    );

    return array_slice($result, 0, $limit);
}
function findOneRow(string $filename, array $criteria): array|null
{
    if (count($result = findRow($filename, $criteria, 1)) === 0) {
        return null;
    }
    return $result[0];
}

function insertRow(string $filename, array $row): array
{
    $data = readDatabase($filename);
    $data[] = $row;
    writeDatabase($filename, $data);
    return $row;
}

function findApp(array $criteria): array|null
{
    return findOneRow('./data/app.data', $criteria);
}
function findCode(array $criteria): array|null
{
    return findOneRow('./data/code.data', $criteria);
}
function findToken(array $criteria): array|null
{
    return findOneRow('./data/token.data', $criteria);
}
function findUser(array $criteria): array|null
{
    return findOneRow('./data/user.data', $criteria);
}

function insertApp(array $app): array
{
    return insertRow('./data/app.data', $app);
}

function insertCode(array $app): array
{
    return insertRow('./data/code.data', $app);
}

function insertToken(array $app): array
{
    return insertRow('./data/token.data', $app);
}


function register(): void
{
    [
        'name'=> $name, 'url'=>$url,
        'redirect_success'=> $redirectSuccess, 'redirect_error'=> $redirectError
    ] = $_POST;
    if (findApp(["name" => $name])) {
        http_response_code(409);
        return;
    }
    $client = uniqid('id_', true);
    $secret = sha1($client);

    $row = insertApp([
        'name'=> $name, 'url'=>$url,
        'redirect_success'=> $redirectSuccess, 'redirect_error'=> $redirectError,
        'client_id' => $client, 'client_secret' => $secret
    ]);

    http_response_code(201);
    echo json_encode($row);
}

function auth()
{
    ['client_id'=> $clientId, 'scope' => $scope, 'redirect_uri'=> $redirect, "state" => $state] = $_GET;
    if (($app = findApp(['client_id'=> $clientId])) === null) {
        return http_response_code(401);
    }
    if ($app['redirect_success'] !== $redirect) {
        return http_response_code(401);
    }

    echo "<div>
        <h1>Confirm</h1>
        <p>{$app['name']} - {$app['url']}</p>
        <p>{$scope}</p>
        <a href=''>Cancel</a>
        <a href='/auth-success?state={$state}&client_id={$clientId}'>Confirm</a>
    </div>";
}

function authSuccess()
{
    ["state" => $state, "client_id"=> $clientId] = $_GET;
    $app = findApp(['client_id'=> $clientId]);
    $codeEntity = insertCode([
        'code'=> sha1(uniqid()),
        'user_id'=> 1,
        'app_id' => $clientId,
        'expiresAt'=> (new \DateTimeImmutable())->modify("+5 minutes")->getTimestamp()
    ]);

    header("Location: {$app['redirect_success']}?code={$codeEntity['code']}&state={$state}");
}

function handleAuthorizationCode($app)
{
    ['code'=> $code, 'redirect_uri'=> $redirect] = $_GET;
    if ($app['redirect_success'] !== $redirect) {
        return http_response_code(401);
    }
    if (($codeEntity = findCode(['code'=> $code, 'app_id'=> $app['client_id']]))=== null) {
        return http_response_code(401);
    }
    if ((new \DateTimeImmutable())->setTimestamp($codeEntity['expiresAt']) < new \DateTimeImmutable()) {
        return http_response_code(401);
    }
    $userId = $codeEntity['user_id'];
    return $userId;
}

function handlePassword()
{
    ['username'=> $username, 'password'=> $password] = $_GET;
    if (($user = findUser(['username'=> $username, 'password'=> $password]))=== null) {
        return http_response_code(401);
    }
    $userId = $user['id'];
    return $userId;
}

function token()
{
    ['client_id' => $clientId, 'client_secret' => $secret, 'grant_type' => $grantType] = $_GET;

    if (($app = findApp(['client_id'=> $clientId, 'client_secret' => $secret])) === null) {
        return http_response_code(401);
    }

    $userId = match ($grantType) {
        'authorization_code' => handleAuthorizationCode($app),
        'password' => handlePassword(),
        'client_credentials' => null,
        default => throw new \Exception(http_response_code(401))
    };

    $token = insertToken([
        "token" => sha1(uniqid()),
        "app_id" => $clientId,
        "expiresIn"=> (new \DateTimeImmutable())->modify("+7 days")->getTimestamp(),
        "user_id" => $userId
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'access_token' => $token['token'],
        'expiresIn'=> $token['expiresIn']
    ]);
}

function me()
{
    $headers = getallheaders();
    $authorization = $headers['Authorization'] ?? $headers['authorization'];
    if (!$authorization) {
        return http_response_code(401);
    }
    if (!str_starts_with($authorization, 'Bearer')) {
        return http_response_code(401);
    }

    $token = str_replace('Bearer ', '', $authorization);

    $tokenEntity = findToken(['token'=> $token]);

    if ((new \DateTimeImmutable())->setTimestamp($tokenEntity['expiresIn']) < new \DateTimeImmutable()) {
        return http_response_code(401);
    }

    $userId = $tokenEntity['user_id'];
    $user = findUser(["id" => $userId]);
    if (!$user) {
        return http_response_code(401);
    }

    // get user in database

    header('Content-Type: application/json');
    echo json_encode([
        'id' => $userId,
        'name'=> $user['name'],
        'firstname' => $user['firstname']
    ]);
}

$url = strtok($_SERVER['REQUEST_URI'], '?');
switch($url) {
    case '/register':
        register();
        break;
    case '/auth':
        auth();
        break;
    case '/auth-success':
        authSuccess();
        break;
    case '/token':
        token();
        break;
    case '/me':
        me();
        break;
    default:
        http_response_code(404);
        break;
}
