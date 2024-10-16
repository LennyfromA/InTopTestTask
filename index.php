<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Форма заявки</title>
</head>
<body>
    <h1>Оставьте завку</h1>
    <form action="api/submit.php" method="POST">
        <label for="name">Имя:</label>
        <input type="text" id="name" name="name" required><br><br>
        <label for="phone">Телефон:</label>
        <input type="text" id="phone" name="phone" required><br><br>
        <label for="comment">Комментарий:</label>
        <textarea id="comment" name="comment" required></textarea><br><br>
        <input type="submit" value="Отправить">
    </form>
</body>
</html>