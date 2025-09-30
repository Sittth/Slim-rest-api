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

    public function testFetchPaginated() {
        for ($i = 1; $i <= 15; $i++) {
            $createdAt = date('Y-m-d H:i:s', time() + $i);
            $this->pdo->exec("INSERT INTO tasks (title, description, created_at) VALUES ('Task $i', 'Description $i', '$createdAt')");
        }
        
        $result = $this->db->fetchPaginated(1, 10);
        $this->assertCount(10, $result);
        $this->assertEquals('Task 15', $result[0]['title']); 
        
        $result = $this->db->fetchPaginated(2, 10);
        $this->assertCount(5, $result);
        $this->assertEquals('Task 5', $result[0]['title']);
    }

    public function testGetTotalCount() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Test 1', 'Desc 1')");
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Test 2', 'Desc 2')");
        
        $count = $this->db->getTotalCount();
        $this->assertEquals(2, $count);
    }

    public function testGetPaginationInfo() {
        for ($i = 1; $i <= 25; $i++) {
            $this->pdo->exec("INSERT INTO tasks (title) VALUES ('Task $i')");
        }
        
        $pagination = $this->db->getPaginationInfo(2, 10);
        
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['total_pages']);
        $this->assertTrue($pagination['has_next']);
        $this->assertTrue($pagination['has_prev']);
    }
}