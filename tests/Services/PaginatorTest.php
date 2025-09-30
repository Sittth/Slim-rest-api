<?php
namespace Tests\Services;

use PDO;
use PHPUnit\Framework\TestCase;
use SlimTasksApi\Services\Paginator;

class PaginatorTest extends TestCase {
    private $paginator;
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

        $this->paginator = new Paginator($this->pdo, 'tasks');
    }

    public function testPaginate() {
        for ($i = 1; $i <= 15; $i++) {
            $createdAt = date('Y-m-d H:i:s', time() + $i);
            $this->pdo->exec("INSERT INTO tasks (title, description, created_at) VALUES ('Task $i', 'Description $i', '$createdAt')");
        }
        
        $result = $this->paginator->paginate(1, 10);
        $this->assertCount(10, $result);
        $this->assertEquals('Task 15', $result[0]['title']);
        
        $result = $this->paginator->paginate(2, 10);
        $this->assertCount(5, $result);
        $this->assertEquals('Task 5', $result[0]['title']);
    }

    public function testGetTotalCount() {
        $this->pdo->exec("INSERT INTO tasks (title) VALUES ('Test 1')");
        $this->pdo->exec("INSERT INTO tasks (title) VALUES ('Test 2')");
        
        $count = $this->paginator->getTotalCount();
        $this->assertEquals(2, $count);
    }

    public function testGetPaginationInfo() {
        for ($i = 1; $i <= 25; $i++) {
            $this->pdo->exec("INSERT INTO tasks (title) VALUES ('Task $i')");
        }
        
        $pagination = $this->paginator->getPaginationInfo(2, 10);
        
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['total_pages']);
        $this->assertTrue($pagination['has_next']);
        $this->assertTrue($pagination['has_prev']);
    }

    public function testPaginateWithStatusFilter() {
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Pending Task', 'pending')");
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Completed Task', 'completed')");
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Another Pending', 'pending')");
        
        $result = $this->paginator->paginate(1, 10, 'created_at DESC', ['status' => 'pending']);
        $this->assertCount(2, $result);
        
        $count = $this->paginator->getTotalCount(['status' => 'pending']);
        $this->assertEquals(2, $count);
    }

    public function testPaginateWithSearchFilter() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Buy milk', 'Go to store')");
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Learn PHP', 'Study programming')");
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Milk shake', 'Make drink')");
        
        $result = $this->paginator->paginate(1, 10, 'created_at DESC', ['search' => 'milk']);
        $this->assertCount(2, $result);
        
        $count = $this->paginator->getTotalCount(['search' => 'milk']);
        $this->assertEquals(2, $count);
    }

    public function testPaginateWithCombinedFilters() {
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Important pending', 'pending')");
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Important completed', 'completed')");
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Other task', 'pending')");
        
        $result = $this->paginator->paginate(1, 10, 'created_at DESC', [
            'status' => 'pending',
            'search' => 'Important'
        ]);
        $this->assertCount(1, $result);
        $this->assertEquals('Important pending', $result[0]['title']);
    }

    public function testPaginateWithInvalidStatusFilter() {
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Task 1', 'pending')");
        
        $result = $this->paginator->paginate(1, 10, 'created_at DESC', ['status' => 'invalid_status']);
        $this->assertCount(1, $result);
    }

    public function testPaginateWithEmptyFilters() {
        $this->pdo->exec("INSERT INTO tasks (title) VALUES ('Task 1')");
        
        $result = $this->paginator->paginate(1, 10, 'created_at DESC', []);
        $this->assertCount(1, $result);
        
        $result = $this->paginator->paginate(1, 10, 'created_at DESC', [
            'status' => null,
            'search' => ''
        ]);
        $this->assertCount(1, $result);
    }
}