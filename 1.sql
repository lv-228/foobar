# 1. Таблицы из задания:

CREATE TABLE `users` (

        `id`         INT(11) NOT NULL AUTO_INCREMENT,

        `name`       VARCHAR(255) DEFAULT NULL,

        `gender`     INT(11) NOT NULL COMMENT '0 - не указан, 1 - мужчина, 2 - женщина.',

        `birth_date` INT(11) NOT NULL COMMENT 'Дата в unixtime.',

        PRIMARY KEY (`id`)

);

CREATE TABLE `phone_numbers` (

        `id`      INT(11) NOT NULL AUTO_INCREMENT,

        `user_id` INT(11) NOT NULL,

        `phone`   VARCHAR(255) DEFAULT NULL,

        PRIMARY KEY (`id`)

);

# 2. Запрос к таблицам:

SELECT IF(name IS NOT NULL, CONCAT(name, '  user_id:', users.id), CONCAT('Аноним', ' user_id:', users.id)) as Name, 
count(phone_numbers.phone) as 'Количество номеров' 
FROM users LEFT JOIN phone_numbers ON users.id = phone_numbers.user_id
WHERE TIMESTAMPDIFF(YEAR, FROM_UNIXTIME(birth_date), CURDATE()) 
BETWEEN 18 AND 22 AND gender = 2 GROUP BY IF(name IS NOT NULL, CONCAT(name, '  user_id:', users.id), CONCAT('Аноним', ' user_id:', users.id));

/*

mysql> explain SELECT IF(name IS NOT NULL, CONCAT(name, '  user_id:', users.id), CONCAT('Аноним', ' user_id:', users.id)) as Name,
    -> count(phone_numbers.phone) as 'Количество номеров'
    -> FROM users LEFT JOIN phone_numbers ON users.id = phone_numbers.user_id
    -> WHERE TIMESTAMPDIFF(YEAR, FROM_UNIXTIME(birth_date), CURDATE())
    -> BETWEEN 18 AND 22 AND gender = 2 GROUP BY IF(name IS NOT NULL, CONCAT(name, '  user_id:', users.id), CONCAT('Аноним', ' user_id:', users.id));
+----+-------------+---------------+------------+------+---------------+------+---------+------+------+----------+--------------------------------------------+
| id | select_type | table         | partitions | type | possible_keys | key  | key_len | ref  | rows | filtered | Extra                                      |
+----+-------------+---------------+------------+------+---------------+------+---------+------+------+----------+--------------------------------------------+
|  1 | SIMPLE      | users         | NULL       | ALL  | NULL          | NULL | NULL    | NULL |   11 |    10.00 | Using where; Using temporary               |
|  1 | SIMPLE      | phone_numbers | NULL       | ALL  | NULL          | NULL | NULL    | NULL |    5 |   100.00 | Using where; Using join buffer (hash join) |
+----+-------------+---------------+------------+------+---------------+------+---------+------+------+----------+--------------------------------------------+

*/

/*

3. Оптимизация таблиц

3.1 Первое что бросается в глаза это избыточность выделяемого пространства 
для таких значений как name, gender у таблицы users и phone у phone_numbers.

Решением предлагаю уменьшить количество символов в name (думаю что 100 символов будет достаточно, 
если мы берем СНГ), для хранения пола нам необходимо 3 значения, выделять под это 4 байта избыточно, 
можно обойтись одним и указать поле как tinyint. Для phone в phone_numbers будет достаточно 20 (зависит от того в каком формате предполагается ввод данных на фронте и 
от кода страны, поэтому беру чучуть с запасом)

3.2 Для целостности данных и оптимизации поиска данных предлагаю связать таблицы внешним ключом. 
При удалении из главной таблицы так-же удалить запись из зависимой.

3.3 Для оптимизации поиска по таблице нужно проиндексировать столбцы. К индексированию надо подходить с умом т.к. у них есть и обратная сторона, например дополнительная
нагрузка при операциях INSERT. В нашем случае необходимо избавиться от каких либо операций со столбцом, из-за того что для определения возраста
используется не детерминированая функция NOW() что не позволит создать нам например вычисляемый виртуальный столбец. В противном случае столбец не попадает в индекс

3.4 Указать значение phone как NOT NULL иначе теряется смысл записи в таблице. Так-же для name, но тут зависит от требований к продукту, 
нужно ли позволять не указывать имя?

P.S. Добавил ID к имени т.к. при повторяющемся имени считаю что информация будет не совсем точной, можно конечно сгруппировать по name и birth_date 
но это тожно не 100% гарантия что не будет двух людей с одним именем и датой рождения. Плюс для группировки столбец нужно указать в Select что не входит
в задание.

*/

CREATE TABLE users_ref (

        id         INT(11) AUTO_INCREMENT,

        name       VARCHAR(100) NOT NULL,

        gender     TINYINT(1) NOT NULL COMMENT '0 - не указан, 1 - мужчина, 2 - женщина.',

        birth_date INT(11) NOT NULL COMMENT 'Дата в unixtime.',

        PRIMARY KEY (`id`),

        INDEX (birth_date)

);

CREATE TABLE phone_numbers_ref (

        id      INT(11) AUTO_INCREMENT,

        user_id INT(11) NOT NULL,

        phone   VARCHAR(20) NOT NULL,

        PRIMARY KEY (`id`),

        FOREIGN KEY (user_id) REFERENCES users_ref(id) ON DELETE CASCADE,

        INDEX (user_id)
);

#4. Новый Запрос

SELECT CONCAT(name,' user_id:', users_ref.id), count(phone_numbers_ref.phone) as 'Количество номеров' 
FROM users_ref LEFT JOIN phone_numbers_ref 
ON users_ref.id = phone_numbers_ref.user_id 
WHERE users_ref.birth_date BETWEEN UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 22 YEAR)) 
AND UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 18 YEAR))
AND gender = 2 GROUP BY CONCAT(name,' user_id:', users_ref.id);

/*
mysql> explain SELECT CONCAT(name,' user_id:', users_ref.id), count(phone_numbers_ref.phone) as 'Количество номеров'
    -> FROM users_ref LEFT JOIN phone_numbers_ref
    -> ON users_ref.id = phone_numbers_ref.user_id
    -> WHERE users_ref.birth_date BETWEEN UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 22 YEAR))
    -> AND UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 18 YEAR))
    -> AND gender = 2 GROUP BY CONCAT(name,' user_id:', users_ref.id);
+----+-------------+-------------------+------------+-------+---------------+------------+---------+------------------+------+----------+-----------------------------------------------------+
| id | select_type | table             | partitions | type  | possible_keys | key        | key_len | ref              | rows | filtered | Extra                                               |
+----+-------------+-------------------+------------+-------+---------------+------------+---------+------------------+------+----------+-----------------------------------------------------+
|  1 | SIMPLE      | users_ref         | NULL       | range | birth_date    | birth_date | 4       | NULL             |    4 |    25.00 | Using index condition; Using where; Using temporary |
|  1 | SIMPLE      | phone_numbers_ref | NULL       | ref   | user_id       | user_id    | 4       | b2b.users_ref.id |    2 |   100.00 | NULL                                                |
+----+-------------+-------------------+------------+-------+---------------+------------+---------+------------------+------+----------+-----------------------------------------------------+

*/