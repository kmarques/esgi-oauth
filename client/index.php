<?php

session_start();

define("OAUTH_CLIENTID", "id_635913414ff1f4.96204950");
define("OAUTH_CLIENTSECRET", "82eb52781cc74ce5564927d4e7223e07ab28f489");
define("FB_CLIENTID", "793044745254704");
define("FB_CLIENTSECRET", "29fad9df806d99e7a36aa2ef43d8c65e");

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
    echo "<br>";

    $queryParams = http_build_query([
        'reponse_type'=> "code",
        'state' => $_SESSION['state'],
        'scope' => '',
        'client_id'=> FB_CLIENTID,
        "redirect_uri"=> "http://localhost:8081/fb_success"
    ]);
    $url = "https://www.facebook.com/v15.0/dialog/oauth?" . $queryParams;
    echo "<a href='$url'>Se connecter via Facebook</a>";
}

function redirectSuccess()
{
    ["code" => $code, "state" => $state] = $_GET;
    if ($state !== $_SESSION['state']) {
        return http_response_code(400);
    }

    getTokenAndUser(
        [
        'grant_type'=> "authorization_code",
        "code" => $code,
        "redirect_uri"=> "http://localhost:8081/success"
    ],
        [
            "client_id" => OAUTH_CLIENTID,
            "client_secret" => OAUTH_CLIENTSECRET,
            "token_url" => "http://server:8080/token",
            "user_url" => "http://server:8080/me"
        ]
    );
}

function redirectFbSuccess()
{
    ["code" => $code, "state" => $state] = $_GET;
    if ($state !== $_SESSION['state']) {
        return http_response_code(400);
    }

    getTokenAndUser([
        'grant_type'=> "authorization_code",
        "code" => $code,
        "redirect_uri"=> "http://localhost:8081/fb_success"
    ], [
        "client_id" => FB_CLIENTID,
        "client_secret" => FB_CLIENTSECRET,
        "token_url" => "https://graph.facebook.com/oauth/access_token",
        "user_url" => "https://graph.facebook.com/me"
    ]);
}

function doLogin()
{
    getTokenAndUser(
        [
        'grant_type'=> "password",
        "username" => $_POST['username'],
        "password"=> $_POST['password']
    ],
        [
            "client_id" => OAUTH_CLIENTID,
            "client_secret" => OAUTH_CLIENTSECRET,
            "token_url" => "http://server:8080/token",
            "user_url" => "http://server:8080/me"
        ]
    );
}

function getTokenAndUser($params, $settings)
{
    $queryParams = http_build_query(array_merge([
        'client_id'=> $settings['client_id'],
        'client_secret'=> $settings['client_secret'],
    ], $params));
    $url = $settings['token_url'] . '?' . $queryParams;
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
    $url = $settings['user_url'];
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
    case '/fb_success':
        redirectFbSuccess();
        break;
    default:
        http_response_code(404);
        break;
}
