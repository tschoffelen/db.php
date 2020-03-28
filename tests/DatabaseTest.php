<?php

/**
 * Class DatabaseTest
 */
class DatabaseTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var Database
     */
    protected $db;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $mock;

    protected function setUp(): void
    {
        $this->mock = $this->getMockBuilder('mysqli')->getMock();
        $this->mock->method('real_escape_string')->will($this->returnCallback(function($value) {
            return addslashes($value);
        }));

        $this->db = new Database('test', 'test_user', 'test_pass', 'localhost', $this->mock);
    }

    public function testQuery()
    {
        $query = 'SELECT * FROM calendar WHERE `day` = "Monday"';

        $this->mock->expects($this->once())->method('query')->with($query);

        $result = $this->db->query($query);

        $this->assertInstanceOf('Database', $result);
    }

    public function testSelect()
    {
        $this->mock->expects($this->once())->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE `name`=\'Pawel\' LIMIT 1');

        $this->db->select('users', ['name' => 'Pawel'], 1);

        $this->assertEquals('SELECT * FROM `users` WHERE `name`=\'Pawel\' LIMIT 1', $this->db->sql());
    }

    public function testSelectOrder()
    {
        $this->mock->expects($this->once())->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE `name`=\'Pawel\' ORDER BY age DESC LIMIT 1');

        $this->db->select('users', ['name' => 'Pawel'], 1, 'age DESC');
    }

    public function testSelectFields()
    {
        $this->mock->expects($this->once())->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->once())->method('query')->with('SELECT age FROM `users` WHERE `name`=\'Pawel\'');

        $this->db->select('users', ['name' => 'Pawel'], null, null, 'AND', 'age');
    }

    public function testSelectFieldsArray()
    {
        $this->mock->expects($this->once())->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->once())->method('query')->with('SELECT `age`, `email` FROM `users` WHERE `name`=\'Pawel\'');

        $this->db->select('users', ['name' => 'Pawel'], null, null, 'AND', ['age', 'email']);
    }

    public function testSelectAnd()
    {
        $this->mock->expects($this->at(0))->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->at(1))->method('real_escape_string')->with('36');
        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE `name`=\'Pawel\' AND `age`=\'36\' LIMIT 1');

        $this->db->select('users', ['name' => 'Pawel', 'age' => 36], 1);
    }

    public function testSelectOr()
    {
        $this->mock->expects($this->at(0))->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->at(1))->method('real_escape_string')->with('36');
        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE `name`=\'Pawel\' OR `age`=\'36\' LIMIT 1');

        $this->db->select('users', ['name' => 'Pawel', 'age' => 36], 1, null, 'OR');
    }

    public function testSelectWithArray()
    {
        $this->mock->expects($this->at(0))->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->at(1))->method('real_escape_string')->with('Suzie');

        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE `name` IN (\'Pawel\', \'Suzie\') LIMIT 1');

        $this->db->select('users', ['name' => ['Pawel', 'Suzie']], 1);
    }

    public function testSelectWithAssocArray()
    {
        $this->mock->expects($this->at(0))->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->at(1))->method('real_escape_string')->with('Suzie');

        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE `name` IN (\'Pawel\', \'Suzie\') LIMIT 1');

        $this->db->select('users', ['name' => ['name1' => 'Pawel', 'name2' => 'Suzie']], 1);
    }

    public function testSelectWithRawWhere()
    {
        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE status IN (0,1)');

        $this->db->select('users', ['status IN (0,1)']);
    }

    public function testSelectWithRawString()
    {
        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE status IN (0,1)');

        $this->db->select('users', 'status IN (0,1)');
    }

    public function testSingleton()
    {
        $this->assertEquals('test', Database::instance()->database_name);
    }

    public function testIsSerialized()
    {
        $this->assertTrue($this->db->is_serialized('a:0:{}'));
        $this->assertTrue($this->db->is_serialized('s:3:"333";'));
        $this->assertTrue($this->db->is_serialized('s:2:"é";'));
        $this->assertTrue($this->db->is_serialized('b:0;'));
        $this->assertTrue($this->db->is_serialized('b:1;'));
        $this->assertTrue($this->db->is_serialized('N;'));
        $this->assertFalse($this->db->is_serialized(''));
        $this->assertFalse($this->db->is_serialized('s:2:"é"'));
        $this->assertFalse($this->db->is_serialized([]));
        $this->assertFalse($this->db->is_serialized('tes'));
        $this->assertFalse($this->db->is_serialized('test'));
        $this->assertFalse($this->db->is_serialized('a:test'));
        $this->assertFalse($this->db->is_serialized('s:0:test'));
        $this->assertFalse($this->db->is_serialized('true'));
    }

}
