<?php

session_start();

define("OAUTH_CLIENTID", "id_635913414ff1f4.96204950");
define("OAUTH_CLIENTSECRET", "82eb52781cc74ce5564927d4e7223e07ab28f489");

function login()
{
    $_SESSION['state'] = uniqid();

    $queryParams = http_build_query([
        'reponse_type'=> "code",
        'state' => $_SESSION['state'],
        'scope' => 'basic',
        'client_id'=> OAUTH_CLIENTID,
        "redirect_uri"=> "http://localhost:8081/success"
    ]);
    $url = "http://localhost:8080/auth?" . $queryParams;
    echo "Se connecter via OAuthServer (form)";
    echo '<form method="POST" action="do_login">
        <input type="text" name="username"><input type="text" name="password">
        <input type="submit" value="login">
        </form>';
    echo "<a href='$url'>Se connecter via OAuthServer</a>";
}

function redirectSuccess()
{
    ["code" => $code, "state" => $state] = $_GET;
    if ($state !== $_SESSION['state']) {
        return http_response_code(400);
    }

    getTokenAndUser([
        'grant_type'=> "authorization_code",
        "code" => $code,
        "redirect_uri"=> "http://localhost:8081/success"
    ]);
}

function doLogin()
{
    getTokenAndUser([
        'grant_type'=> "password",
        "username" => $_POST['username'],
        "password"=> $_POST['password']
    ]);
}

function getTokenAndUser($params)
{
    $queryParams = http_build_query(array_merge([
        'client_id'=> OAUTH_CLIENTID,
        'client_secret'=> OAUTH_CLIENTSECRET,
    ], $params));
    $url = "http://server:8080/token?" . $queryParams;
    $response = file_get_contents($url);
    $response = json_decode($response, true);
    $token = $response['access_token'];

    $context = stream_context_create([
        "http"=> [
            "header" => [
                "Authorization: Bearer " . $token
            ]
        ]
            ]);
    $url = "http://server:8080/me";
    $response = file_get_contents($url, false, $context);
    var_dump(json_decode($response, true));
}

$url = strtok($_SERVER['REQUEST_URI'], '?');
switch($url) {
    case '/login':
        login();
        break;
    case '/success':
        redirectSuccess();
        break;
    case '/do_login':
        doLogin();
        break;
    default:
        http_response_code(404);
        break;
}
