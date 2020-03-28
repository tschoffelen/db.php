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
    }

    public function testSelectOrder()
    {
        $this->mock->expects($this->once())->method('real_escape_string')->with('Pawel');
        $this->mock->expects($this->once())->method('query')->with('SELECT * FROM `users` WHERE `name`=\'Pawel\' ORDER BY age DESC LIMIT 1');

        $this->db->select('users', ['name' => 'Pawel'], 1, 'age DESC');
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

    /*
     public function testRow()
     {
     }

     public function testDelete()
     {
     }

     public function testInsert()
     {
     }

     public function testError()
     {
     }

     public function testRow_array()
     {
     }



     public function testCount()
     {
     }

     public function testTable_exists()
     {
     }

     public function testNum()
     {
     }

     public function testUpdate()
     {
     }

     public function testAffected()
     {
     }

     public function testProcess_where()
     {
     }

     public function testEscape()
     {
     }

     public function testIs_serialized()
     {
     }

     public function testId()
     {
     }

     public function testResult()
     {
     }

     public function testResult_array()
     {
     }
    */

}
