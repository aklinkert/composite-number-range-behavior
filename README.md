### CompositeNumberRangeBehavior

A behavior for Propel 2 which provides number ranges in combination of a composite key. **This Behavior only works in MySQL!**

[![Build Status](https://travis-ci.org/apinnecke/composite-number-range-behavior.svg?branch=master)](https://travis-ci.org/apinnecke/composite-number-range-behavior)
[![Latest Stable Version](https://poser.pugx.org/apinnecke/composite-number-range-behavior/v/stable.png)](https://packagist.org/packages/apinnecke/composite-number-range-behavior)
[![Total Downloads](https://poser.pugx.org/apinnecke/composite-number-range-behavior/downloads.png)](https://packagist.org/packages/apinnecke/composite-number-range-behavior)

---

## Dafuq? Number Range? What's that?

Consider having a table with an autoincrement id field, a foreign key and an composite id field behaving as an autoincrement
field, but starting at zero for every new foreign id value. Example:

```xml
<table name="user">
    <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
    <column name="username" type="VARCHAR" size="100" required="true" />
</table>

<table name="document">
    <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
    <column name="user_id" type="INTEGER" required="true" />
    <column name="user_document_id" type="INTEGER" required="true" />
    
    <foreign-key foreignTable="user">
        <reference local="user_id" foreign="id" />
    </foreign-key>
</table>
```

Example content of document table:

| id | user_id | user_document_id |
|----|---------|------------------|
| 1 | 1 | 1 |
| 2 | 1 | 2 |
| 3 | 2 | 1 |
| 4 | 2 | 2 |
| 5 | 3 | 1 |
| 6 | 3 | 2 |
| 7 | 1 | 3 |
| 8 | 1 | 4 |

As you can see, `user_document_id` is starting at zero for every different `user_id`! This is called a number range. *Yay*

## How does this Behavior work?

The generation of the user_document_id is done via a trigger which is created automatically by a custom MySQLPlatform
provided by this behavior. The current id is stored in a sequence_table to prevent duplicate keys when a row is deleted from the `document` table.

## Registering the Platform

In `app/config/config.yml` add the platform for your connection name: 

```yaml
propel:
    generator:
        defaultConnection: default
        connections:       [ default ]
        platformClass:     \APinnecke\CompositeNumberRange\Platform\MysqlPlatform
```

## Add behavior to table

Following the user <-> document example:

```xml
<table name="document">
    <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
    <behavior name="\APinnecke\CompositeNumberRange\CompositeNumberRangeBehavior">
        <parameter name="foreignTable" value="user"/>
    </behavior>
</table>
```

Now the following things will be auto generated:
    - The `user_id` column
    - A ForeignKey from `document`.`user_id` to `user`.`id`
    - The `user_document_id` column
    - A Unique Index with `user_id` and `user_document_id`
    - A sequence table `user_sequence` consisting out of `table_name`, `user_id` and `user_max_document_id` columns.
    - The Trigger `setDocumentUserId`
    
## Code Example:

```php
$user = new \User();
$user->setName('Alfred');
$user->save();

$document = new \Document();
$document->setUser($user);
$document->save();

$userDocumentId = $document->getUserDocumentId();
// $userDocumentId contains 1
```

## Migrations Notice
Because `propel:migrations:diff` does not know anything about triggers, they have to be checked via SQL Statement. If you use triggers and change something on
any table having triggers appended, they will always appear in your `down()` section of the migration. At the moment you have to remove them by hand,
this bug will be solved in the future during Propel 2 release.