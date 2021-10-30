# dawn-db

Database drivers and query builder library for PHP

```
composer require dogukanakkaya/dawn-db
```

<br>

Include autoload file
```php
include_once __DIR__ . "/vendor/autoload.php";
```

To use sqlite you must create a new file for database like **data.db** then create a new instance from **Sqlite** class.
You must give the file path to constructor.
```php
$sqlite = new Codethereal\Database\Driver\Sqlite('data.db');
$db = $sqlite->getQueryBuilder();
```
<br/>

> Samples below valid for Sqlite driver only.

## Reading

```php
$resultSingle = $db->select('name,email,password')->getSingle('users'); // SELECT name,email,password FROM posts LIMIT 1
$result = $db->select('name,email,password')->get('users'); // SELECT name,email,password FROM posts
  
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
- You should call `fetchArray(1)` method on `$resultSingle` too.

### Where

```php
# Allowed where operators are: ['=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN']
$db->where('id', 2)->get('users'); // SELECT * FROM users WHERE id = 2
$db->where('id', '>', 2)->get('users'); // SELECT * FROM users WHERE id > 2
$db
    ->where('id', '>', 2)
    ->where('name', 'codethereal')
    ->get('users'); // SELECT * FROM users WHERE id > 2 AND name = 'codethereal'
```

### Or Where
```php
$db->select('name,email')
    ->where('id', 5)
    ->orWhere('name', 'codethereal')
    ->getSingle('users'); // SELECT name,email FROM users WHERE id = 5 OR name = 'dogukan' LIMIT 1
```

### Nested Where
```php
$db->select('name,email')
    ->where('id', 5)
    ->orWhere(function (\Codethereal\Database\Builder\Query $query) {
        return $query
            ->where('name', 'codethereal')
            ->where('email', 'i@codethereal.com');
    })
    ->orWhere('name', 'codethereal')
    ->getSingle('users'); // SELECT name,email FROM users WHERE id = 5 OR (name = 'dogukan' AND email = 'i@codethereal.com') OR name = 'codethereal' LIMIT 1
```

### Where In/Not In

```php
$db->in('id', [1, 2])->get('users'); // SELECT * FROM users WHERE id IN (1,2)
$db->notIn('id', [1, 2])->get('users'); // SELECT * FROM users WHERE id NOT IN (1,2)
```

### Where Like/Not Like

```php
$db->where('name', 'LIKE', 'Dogukan%')->get('users'); // SELECT * FROM users WHERE name LIKE 'Dogukan%'
$db->where('name', 'NOT LIKE', '%Codethereal%')->get('users'); // SELECT * FROM users WHERE name LIKE '%Codethereal%'
```

### Order By

```php
$db->orderBy('name', 'ASC')->get('users'); // SELECT * FROM users ORDER BY name ASC
```

### Joins

```php
# Available join methods for sqlite are: ['INNER', 'CROSS', 'LEFT (OUTER)']
$db->select('users.name as userName, posts.name as postName')->join('users', 'users.id = posts.user_id', 'CROSS')->get('posts');
$db->select('users.name as userName, posts.name as postName')->join('users', 'users.id = posts.user_id', 'INNER')->get('posts');
```

### Count

```php
$db->where('views', '>', 10)->count('posts'); // SELECT COUNT(*) as count FROM posts
```
- You should call `fetchArray(1)` method on this and get the count alias.

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
$sqlite->transBegin();
$id = $db->insert('users', ['name' => 'Codethereal', 'email' => 'info@codethereal.com']);
$db->insert('postss', ['name' => 'New post', 'user_id' => $id]);
$sqlite->transCommit();
```
