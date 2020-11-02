# sqlitethereal-php
SQLite3 Library For PHP 

```php
composer require codethereal/sqlite-php
```
<br/>

After you installed the library include the autoload file and use the DBLite library.
```php
include_once __DIR__ . "/vendor/autoload.php";

use Codethereal\Database\Sqlite\DBLite;
```
<br/>

To use sqlite you must create a new file for database like **test.db** then create a new instance from **DBLite** class.
You must give the file path to constructor.
```php
$db = new DBLite('test.db');
```
<br/>

Now you can start to use db queries like:

## Reading

```php
$result = $db->select('users.name as userName, posts.name as postName')
  ->where('users.id', 2)
  ->get('posts');
  
# For mode 1: it returns you an array with db column keys
# For mode 2: it returns you an array with index numbers
# For mode 3: it returns you an array with both column keys and index numbers
###*** You must use while loop on returned result, if you want you get only one record ***###
while ($row = $result->fetchArray(1)) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}
```

### Where

```php
# Allowed where operators are: ['=', '>', '<', '>=', '<=']
$db->where('id', 2) // WHERE id = 2
$db->where('id', '>', 2) // WHERE id > 2
$db->where([
  ['id', 2],
  ['count' '<=', 15],
  ['email', 'doguakkaya27@gmail.com']
]) // WHERE id = 2 AND count <= 15 AND email='doguakkaya27@gmail.com'
```

### Where In/Not In

```php
$db->in('id', [1,2])->get('users'); // SELECT * FROM users WHERE id IN (1,2)
$db->notIn('id', [1,2])->get('users'); // SELECT * FROM users WHERE id NOT IN (1,2)
```

### Where Like/Not Like

```php
$db->like('name', 'Dogukan%')->get('users'); // SELECT * FROM users WHERE name LIKE 'Dogukan%'
$db->notLike('name', '%Codethereal%')->get('users'); // SELECT * FROM users WHERE name LIKE '%Codethereal%'
```

### Order By

```php
$db->orderBy('name', 'ASC')->get('users'); // SELECT * FROM users ORDER BY name ASC
$db->orderBy([
  ['name', DBLite::ORDER_ASC],
  ['id', DBLite::ORDER_DESC],
])->get('users'); // SELECT * FROM users ORDER BY name ASC, id DESC
```

### Joins

```php
# Available join methods are: ['INNER', 'CROSS', 'LEFT (OUTER)']
$db->select('users.name as userName, posts.name as postName')->join('users', 'users.id = posts.user_id', 'CROSS')->get('posts');
$db->select('users.name as userName, posts.name as postName')->join('users', 'users.id = posts.user_id', DBLite::JOIN_INNER)->get('posts');
```

### Count

```php
$db->where('views', '>', 10)->count('posts'); // SELECT COUNT(*) as count FROM posts | and returns whatever is count else 0
```

## Creating

```php
$db->insert('users', ['name' => 'Dogukan Akkaya', 'email' => 'doguakkaya27@gmail.com']); // INSERT INTO users (name, email) VALUES ('Dogukan Akkaya', 'doguakkaya27@gmail.com') | Returns insert id on success
```

## Updating

```php
$db->where('id', 1)->update('users', ['name' => 'Dogukan Akkaya | Codethereal', 'email' => 'doguakkaya27@codethereal.com']); // UPDATE users SET name = 'Dogukan Akkaya | Codethereal', email = 'doguakkaya27@codethereal.com' WHERE id = 1
```

## Deleting

```php
$db->where('id', 1)->delete('users'); // DELETE FROM users WHERE id = 1
```

## Transaction

```php
# You don't have to wrap with try-catch block. It will rollback on any error
$db->transBegin();
$id = $db->insert('users', ['name' => 'Codethereal', 'email' => 'info@codethereal.com']);
$db->insert('postss', ['name' => 'New post', 'user_id' => $id]);
$db->transCommit();
```

#### Others


```php
$db->query('sql statement');
$db->querySingle('sql statement');

$db->begin('SELECT * FROM users WHERE id = :id');
$db->bindAndExecute([":id", 1]); // Executes the sql and returns the result
# OR
$db->bindAndReturn([":id", 1]); // Returns the sql statement without execution
```

# CRUD Model for SQLitethereal

```php
use \Codethereal\Database\Sqlite\CrudLite;

class User extends CrudLite
{
    public function __construct(DBLite $db)
    {
        parent::__construct($db);
    }

    public function tableName(): string
    {
        return 'users';
    }

    public function primaryKey(): string
    {
        return 'id';
    }
}

$user = new User($db);
$user->read(); # Like get, you must iterate it in a while loop with fetchArray() method
$user->readOne(1); # Pass the primary key value as param
$user->create(['name' => 'Codethereal']);
$user->update(['name' => 'Codethereal'], 1); # Second parameter is the primary key value
$user->delete(1);
```

# Migrations
Migrations are not applied to core files at the moment but you can just copy the content of [this file](https://www.codethereal.com/migrations.txt) 
and paste it into your **migrations.php** file in root folder and run from terminal

You should change dbname and migrations **(if your path and dbname is different)** path in this file like:

```php
$path = __DIR__ . "/migrations";
$db = new DBLite('test.db');
```

### Create Migration File

You do need 3 functions inside a migration, **up, down, seed**
Create a migrations directory (or whatever you set in the migrations.php file) in your root folder and create a new file named **M001_posts**

```php
<?php

use Codethereal\Database\Sqlite\DBLite;

class M001_posts
{
    private DBLite $db;
    # $db instance will pass here when you run migrations.php
    public function __construct(DBLite $db){$this->db = $db;}
     
    # Create posts table
    public function up()
    {
        $this->db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY, name TEXT NOT NULL, user_id INTEGER NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id))');
    }
    
    # Drop posts table
    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS posts;');
    }

    # Seed the database with query builder. If you pass --seed argument to migrations.php from terminal, it will seed the database. 
    public function seed()
    {
        $this->db->insert('posts', ['id' => 1,'name' => 'New post','user_id' => 1]);
    }
}
```

```
php migrations.php
php migrations.php --seed
php migrations.php --down
```
```
no-param: just run all migrations
--seed: run the migrations and seed the database
--down: down all migrations
```
