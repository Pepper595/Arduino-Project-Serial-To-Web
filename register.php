<link rel="stylesheet" href="styles.css">
<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_name = filter_input(INPUT_POST, 'user_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    // Валидация данных
    if (!$user_name || !$email || !$login || !$password) {
        $_SESSION['register_error'] = "Все поля обязательны для заполнения";
        header('Location: login.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Неверный формат email";
        header('Location: login.php');
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['register_error'] = "Пароль должен быть не менее 6 символов";
        header('Location: login.php');
        exit;
    }

    try {
        // Проверка уникальности логина
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['register_error'] = "Логин уже занят";
            header('Location: login.php');
            exit;
        }

        // Проверка уникальности email
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['register_error'] = "Email уже зарегистрирован";
            header('Location: login.php');
            exit;
        }

        // Хэширование пароля
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Добавление нового пользователя
        $stmt = $pdo->prepare("
            INSERT INTO users (user_name, email, login, password) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_name, $email, $login, $hashed_password]);

        // Автоматический вход после регистрации
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['user_name'] = $user['user_name'];
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['register_error'] = "Ошибка при регистрации";
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['register_error'] = "Ошибка базы данных: " . $e->getMessage();
        header('Location: login.php');
        exit;
    }
} else {
    header('Location: login.php');
    exit;
}
?>