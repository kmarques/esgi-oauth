<?php

function readDatabase(string $filename): array
{
    return array_map(fn ($item) => json_decode($item, true), file($filename));
}

function writeDatabase(string $filename, array $data): void
{
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

function insertApp(array $app): array
{
    return insertRow('./data/app.data', $app);
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

$url = strtok($_SERVER['REQUEST_URI'], '?');
switch($url) {
    case '/register':
        register();
        break;
    default:
        http_response_code(404);
        break;
}
