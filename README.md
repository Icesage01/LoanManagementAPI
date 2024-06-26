# Loan Management API

Простое REST API для управления займами. Позволяет создавать, читать, обновлять и удалять займы в соответствии с указанными данными/фильтрами.
Автоматически логирует все подключения и ошибки приложения, потому способен отрабатывать как примитивный отдельный веб-сервер.

## API Endpoints

- `POST /loans` - Создать новый займ
- `POST /users` - Создать нового пользователя
- `GET /loans/{id}` - Получить информацию по указанному займу
- `GET /users/{id}` - Получить информацию по указанному пользователю
- `PUT /loans/{id}` - Обновить информацию по указанному займу
- `PUT /users/{id}` - Обновить указанного пользователя
- `DELETE /loans/{id}` - Удалить указанный займ
- `DELETE /users/{id}` - Удалить указанного пользователя
- `GET /loans` - Получение списка всех займов с базовыми фильтрами по дате создания и сумме.
- `GET /users` - Получение списка клиентов

## Примеры

Отправка/получение запросов происходит в JSON, поэтому важно указать в заголовках `Content-Type: application/json`
<br/>
`POST /loans` / `PUT /loans/{id}`
<br/>
Принимает следующие параметры<br/>
`int` user_id - ID пользователя из `GET /users/{id}`<br/>
`int` amount - сумма займа<br/>
`int` create_time - дата создания<br/>
`int` pay_time - дата выплаты<br/>

```json
{
"user_id": 123,
"amount": 1000,
"create_time": 1630473600,
"pay_time": 1633075600
}
```
<br/>

`POST /users` / `PUT /users/{id}`

Принимает следующие параметры<br/>
`string` first_name - Имя пользователя<br/>
`string` last_name - Фамилия пользователя<br/>
`string` phone - Телефон пользователя<br/>
`date` birth_date - Дата рождения в формате YYYY-MM-DD<br/>

```json
{
"first_name ": "Захар",
"last_name ": "Смирнов",
"phone ": "+79876543210",
"birth_date ": "1997-05-05"
}
```
<br/>

`GET /loans/{id}`
```json
{
    "status": true,
    "message": "Data retrieved successfully",
    "details": [
        {
            "id": "3",
            "user_id": "1",
            "amount": "4000",
            "create_time": "1716796702",
            "pay_time": "1716796702"
        }
    ]
}
```
Примеры ошибок
```json
{
    "status": false,
    "message": "empty data"
}
```
```json
{
    "status": false,
    "message": "Param 'pay_time' is required!"
}
```
```json
{
    "status": false,
    "message": "Invalid JSON format"
}
```
## Использованные технологии и требования

Все перечисленные технологии устанавливаются при помощи `composer install` при имеющемся файле `composer.json`
- PHP микрофреймворк: [Slim](https://github.com/slimphp/Slim)
- Линтер: [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)
- Миграция базы данных: [Phynx](https://phinx.org/)
- MySQL Server: [MySQL](https://www.mysql.com/)

## Запуск приложения

1. Клонировать репозиторий.
2. Установить зависимости с помощью команды `composer install`.
3. Настроить подключение к базе данных через файл `config.ini`
4. Запустить миграцию `php ./vendor/bin/phinx migrate -e development`.
5. Запустить сервер с помощью команды `php -S localhost:8000 index.php` ИЛИ настроив соответствующий роут/виртуальный хост в конфигурации используемого веб-сервера (Пр. Apache, файл .htaccess в репозитории имеет нужные настройки)
6. Теперь можно пользоваться приложением `http://localhost:8000`.

## Тестирование

Запустить тесты можно с помощью `vendor/bin/phpunit`.  Все тесты лежат в директории `tests`.Тесты покрывают базовые кейсы API.
Проверить приложение на соответствие PSR можно с помощью линтера `phpcs`

## Развёртывание

Приложение можно развернуть на любой хостинговой платформе, поддерживающей PHP, например Heroku, AWS, Google Cloud, DigitalOcean или Yandex Cloud. Обязательно настройте переменные среды и соединение с базой данных соответствующим образом.
