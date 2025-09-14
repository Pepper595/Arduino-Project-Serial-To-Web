<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['user_name'] = $user['user_name'];
        header('Location: index.php');
        exit;
    } else {
        $login_error = "Неверный логин или пароль";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Вход и регистрация - Умный дом</title>
    
</head>
<body>

<?php if (!isset($_SESSION['user_id'])): ?>
        <a href="https://oauth.yandex.ru/authorize?response_type=code&client_id=ea121e034f834795b5b2cd271ef1db4b&redirect_uri=http://localhost/yandex_callback.php">
            <button>Авторизоваться через Яндекс</button>
        </a>
    <?php else: ?>
        <p>Добро пожаловать, <?php echo htmlspecialchars($email ?? 'Пользователь'); ?>! <a href="logout.php">Выйти</a></p>
        <a href="index.php">Перейти в личный кабинет</a>
    <?php endif; ?>

    <div class="container">
        <!-- Форма авторизации -->
        <div class="login-container">
            <h2>Вход в систему</h2>
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="login" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit">Войти</button>
            </form>
        </div>

        <!-- Форма регистрации -->
        <div class="register-container">
            <h2>Регистрация</h2>
            <?php if (isset($_SESSION['register_error'])): ?>
                <div class="error"><?php echo htmlspecialchars($_SESSION['register_error']); unset($_SESSION['register_error']); ?></div>
            <?php endif; ?>
            <form method="POST" action="register.php">
                <input type="text" name="user_name" placeholder="Имя пользователя" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="login" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit">Зарегистрироваться</button>
            </form>
        </div>
    </div>
</body>
</html>