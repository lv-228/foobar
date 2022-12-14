<?php

function load_users_data($user_ids) {
        $user_ids = explode(',', $user_ids);

        foreach ($user_ids as $user_id) {
            $db = mysqli_connect("localhost", "root", "123", "b2b");
            $sql = mysqli_query($db, "SELECT * FROM users WHERE id=$user_id");
            var_dump($sql);die;
            while($obj = $sql->fetch_object()){

                $data[$user_id] = $obj->name;

            }
            mysqli_close($db);
        }
        return $data;

}

// Как правило, в $_GET['user_ids'] должна приходить строка

// с номерами пользователей через запятую, например: 1,2,17,48

$data = load_users_data($_GET['user_ids']);
foreach ($data as $user_id=>$name) {

        echo "<a href=\"/show_user.php?id=$user_id\">$name</a>";

}

/*
    Недочеты присутствующие в функции.

    1. Нет типизации входного параметра, получается что в лучшем случае мы делегируем это клиентскому коду.

    2. В цикле для каждого id отдельно создается подключение к БД и выполняется 1 запрос после чего соединение закрывается. Такой подход к работе с БД является потенциальной возможностью для DoS атаки (если например мы передадим 100000 айдишников)

    3. База данных и сервер php расположены на одной "физической" машине (можно судить по localhost в адресе подключения). Думаю что это не правильно с точки зрения безопасности, есть вероятность что при компроментации одного ПО будут захвачены и все другие (например при возможности RCE). Плюс нагрузка на одну систему, опять же если брать в пример DoS, отразится на другой

    4. Подключение к базе происходит через супер пользователя root, что является большой ошибкой в разрезе ИБ. Правильно будет создать отдельного пользовалетя и наделить его минимальными правами, в данном случае только на чтение таблицы users. Так-же можно использовать представления с необходимыми данными и разрешить доступ только к ним, что еще больше отдаляет возможность компроментации БД

    5. Используется очень слабый пароль, темболее для пользователя с такими привилегиями. Что может привести к несанкционируемому доступу к системе.

    6. В запрос передается необработанный параметр (без экранирования и приведения типов), что открывает пространство для SQL инъекций с подзапросами (UNION отпадает т.к. необходимо через запятую перечислят столбцы, но функция разбивает строку на части. Получается что любые SQL конструкции которые содержат запятую не пройдут из-за специфики функции).
    Примеры:
    (select SLEEP(5))

    7. Данные для подключения к БД хранятся в скрипте, если такой код выложить в открытый репозиторий пользователь/пароль скорее всего будут скомпрометированы.

    8. Не информативное имя базы данных, но это уже наверное больше придирка и при генерации ссылок все выводится в одну строку, что не удобно для пользователя

    9. PHP будет выбрасывать NOTICE предупреждения если записей с указанными id не существует

    10. Судя по ссылке, если мы обращаемся к "настоящим файлам" (нет какого либо контроллера который URL-path преобразует в маршрут ОС и уже подключает оттуда файлы) есть большая вероятность уязвимости типа Directory Travaller (обход директорий и просмотр их содержимого). А если есть возможность загружать файлы на сервер то это приводит к RCE
*/


/**
 * Получает данные пользователей по строке содержащей id
 *
 * Парсит входную строку, генерирует подготовленый 
 * SQL запрос на получение данных о пользователях 
 * и выполняет его, возвращает полученный результат
 *
 * @param string $user_ids идентификатор(ы) пользователей
 *                             в БД разделенные запятой
 * 
 * @var string $dsn строка подключения к БД, 
 *                  содержится в php.ini, блок [PDO]                      
 *
 * @var string $in строка содержащая в себе знаки ? в количестве равном 
 *                 перечисленным id через запятую во входном параметре
 *
 *
 * @return array
 */
function load_users_data_refactor(string $user_ids): array
{
    $user_ids = explode(',', $user_ids);

    $dsn      = 'mydb';
    $user     = 'root';
    $password = '123';

    $pdo         = new PDO($dsn, $user, $password);
    $in          = str_repeat('?,', count($user_ids) - 1) . '?'; 
    $query       = 'SELECT * FROM users WHERE id IN (' . $in . ')';
    $pdo_prepare = $pdo->prepare($query);

    foreach ($user_ids as $key => &$val)
    {
        $pdo_prepare->bindParam($key + 1, $val, PDO::PARAM_INT);
    }
    $pdo_prepare->execute();

    return $pdo_prepare->fetchAll();
}

$data = load_users_data_refactor('1,2,3');

foreach ($data as $user) {

        echo '<a href="/show_user.php?id=' . $user['id'] . '">' . $user['name'] . '</a>' . PHP_EOL;

}

/*
Пояснения по изменениям в функции

1. Типизирован входной параметр и возвращаемой значение функцией

2. Попытка скрыть данные для аворизации в БД и ее местоположения в сети. В данном примере dsn скрыт в файле php.ini. В документации нет примера как скрыть данные для авторизации в php.ini, возможно подключить файл через uri, но на Windows это не срабаывает (https://www.php.net/manual/ru/pdo.construct.php пример #2 и #3). Я бы предложил перенести креды в файл игнорируемый GIT'ом и получать их программно 

3. Для подключения используется PDO который является более общим API для работы с БД чем mysqli_connect который расчитан на конкретную БД

4. Получение данных функцией реализовано в единый запрос и за одной подключение, в отличии от оригинала, где создавалось подключение выполнялся запрос и закрывалось подключение и так n'ое количество раз в зависимости от входного параметра

5. В оригинальной функции входные данные никак не валидируются не экранируются что категорически недопустимо со стороны безопасности. В переписанной функции используется подготовленные запросы. https://www.php.net/manual/en/pdo.prepared-statements.php Драйвер автоматически экранирует передаваемые параметры, так-же оптимизирует и компилирует запрос. Важно понимать что это не является панацеей, например при сложных запросах может потреблять ощутимое количество ресурсов, но в данном случае подготовленный запрос это лучшее решение для безопасности приложения.

6. В новой функции указывается тип параметра который будет передан в запрос, в нашем случае это INT, другие параметры (если они не могут быть не явно переведены в другой тип) будут отброшены

7. Переписана обработка результата функции, вместо двойных ковычек используются одинарные, что, по моему мнению, улучшает читаемость кода и капельку его оптимизирует, т.к. двойные кавычки запускают парсер строки на поиск переменной и подстановку ее значения, проще сделать это через конкатинацию.

*/