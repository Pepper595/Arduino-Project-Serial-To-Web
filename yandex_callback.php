<?php
session_start();
require_once 'db_connect.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $clientId = 'IDCLIENT';
    $clientSecret = 'SCLIENT';
    $redirectUri = 'https://localhost/yandex_callback.php';

    $ch = curl_init('https://oauth.yandex.ru/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri
    ]));
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    if (isset($tokenData['access_token'])) {
        $_SESSION['yandex_token'] = $tokenData['access_token'];

        $ch = curl_init('https://login.yandex.ru/info');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . $tokenData['access_token']]);
        $userInfo = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($userInfo['default_email']) && isset($userInfo['id'])) {
            $user_name = $userInfo['first_name'] . ' ' . $userInfo['last_name'];
            $email = $userInfo['default_email'];
            $yandexId = $userInfo['id'];
            $_SESSION['user_name'] = $userInfo['first_name'];

            $stmt = $pdo->prepare("SELECT id_user FROM users WHERE yandex_id = ?");
            $stmt->execute([$yandexId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_id'] = $user['id_user'];
                
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (user_name, email, yandex_id, password) VALUES (?, ?, ?, 'yandex_auth')");
                $stmt->execute([$user_name, $email, $yandexId]);
                $_SESSION['user_id'] = $pdo->lastInsertId();
            }
           
            header('Location: index.php');
            exit;
        } else {
            die('Ошибка получения данных пользователя: ' . json_encode($userInfo));
        }
    } else {
        die('Ошибка авторизации: ' . $response);
    }
} else {
    die('Нет кода авторизации');
}
?>