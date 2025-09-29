<?php
namespace Tests\Models;

use PDO;
use PHPUnit\Framework\TestCase;
use SlimTasksApi\Models\Database;

class DatabaseTest extends TestCase {
    private $db;
    private $pdo;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->pdo->exec("CREATE TABLE tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->db = new Database($this->pdo);
    }

    public function testFetchAll() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Test', 'Description')");
        
        $result = $this->db->fetchAll();
        $this->assertCount(1, $result);
        $this->assertEquals('Test', $result[0]['title']);
    }

    public function testFetchById() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Test', 'Description')");
        $id = $this->pdo->lastInsertId();

        $result = $this->db->fetchById($id);
        $this->assertEquals('Test', $result['title']);
    }

    public function testFetchByIdNotFound() {
        $result = $this->db->fetchById(999);
        $this->assertNull($result);
    }

    public function testInsert() {
        $id = $this->db->insert([
            'title' => 'New Task',
            'description' => 'Description',
            'status' => 'pending'
        ]);

        $this->assertIsInt($id);
        
        $task = $this->pdo->query("SELECT * FROM tasks WHERE id = $id")->fetch();
        $this->assertEquals('New Task', $task['title']);
    }

    public function testUpdate() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Old Title', 'Desc')");
        $id = $this->pdo->lastInsertId();

        $result = $this->db->update($id, [
            'title' => 'New Title',
            'description' => 'New Desc'
        ]);

        $this->assertTrue($result);
        
        $task = $this->pdo->query("SELECT * FROM tasks WHERE id = $id")->fetch();
        $this->assertEquals('New Title', $task['title']);
    }

    public function testDelete() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('To Delete', 'Desc')");
        $id = $this->pdo->lastInsertId();

        $result = $this->db->delete($id);
        $this->assertTrue($result);
        
        $task = $this->pdo->query("SELECT * FROM tasks WHERE id = $id")->fetch();
        $this->assertFalse($task);
    }
}