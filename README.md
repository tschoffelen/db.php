db.php
====

db.php is a simple PHP class for doing standard MySQL actions, such as selecting, inserting, updating and deleting database rows. It also includes some nice functionality, like auto-escaping to protect your database from malicious code and automatic serializing of arrays.

## Usage

### Initiating 
**Initiate a database connection using by creating a `new db()` object.**

```
require('db.php');
$db = new db($database_name, $username, $password, $host); // $host is optional and defaults to 'localhost'
```

### Select
**Select rows from a database table**

Usage: 

```
$db->select($table,$where=array(),$limit=false,$order=false,$where_mode="AND",$print_query=false)
```

Arguments:

* string `$table` - name of the table to select from
* array/string `$where` - array or string holding the filters/'WHERE' clause for the query
* int/string `$limit` - integer or string holding the 'LIMIT' clause
* string `$order` - string holding the 'ORDER BY' clause
* string `$where_mode` - whetether to add an 'AND' or 'OR' after each item in the `$where` array, defaults to `AND`
* bool `$print_query` - print sql query, defaults false

Example: 

```
// get the first 10 candy bars that are sweet, and order them by amount
$db->select('candy', array('sweet' => 1, 'spicy' => 0), 10, 'amount DESC');
```

```
// get the ids 1, 2,5,9  from products
$db->select('products', array('id' => 'in (1,2,5,9)'), false, false,'OR');
```



#### Reading results

Reading the results can be done with the following functions:

* `$db->count()` returns the number of selected rows, equal to `mysql_num_rows()`

* `$db->row()` returns the first row that matches the query as an array
* `$db->result()` returns all matches rows as an array containing row objects

* `$db->result_array()` returns all matches rows as an array containing row arrays
* `$db->row_array()` returns the first row that matches the query as an object (stdClass)

Please note that you can call any of these functions also directly after the `$db->select()` call, like shown below:

```
echo $db->select('candy', array('sweet' => 1), 10)->count();
```

There are a few other methods available for queries that might come in handy:

* `$db->sql()` returns the sql query that was generated

* `$db->result_raw` returns the direct output `mysql_query()`

### Insert
**Insert data into a database table**

Usage: 

```
$db->insert($table, $fields=array())
```

Example: 

```
$db->insert(
	'candy', 
	array(
		'name' => 'Kitkat original',
		'sweet' => 1,
		'spicey' => 0,
		'brand' => 'Kitkat',
		'amount_per_pack' => 4
	)
);
```

**Tip!** You can call `$db->id()` immeadiatly after a `$db->insert()` call to get the ID of the last inserted row.

### Update
**Update one or more rows of a database table**

Usage: 

```
$db->update($table, $fields=array(), $where=array())
```

Example: 

```
// set amount per pack to 5 for all Kitcats
$db->update(
	'candy', 
	array( // fields to be updated
		'amount_per_pack' => 5 
	), 
	array( // 'WHERE' clause
		'brand' => 'Kitkat' 
	)
);
```

### Delete
**Remove one or more rows from a database table**

Usage: 

```
$db->delete($table, $where=array())
```

Example: 

```
// delete all Kitkat candy
$db->delete(
	'candy', 
	array( // 'WHERE' clause
		'brand' => 'Kitkat' 
	)
);
```


## Send me a coffee
Like my work? [Consider giving a small donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=X8Y8GRHBU7V8N). 
