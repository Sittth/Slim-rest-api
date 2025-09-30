<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SlimTasksApi\Models\Database;
use SlimTasksApi\Services\Paginator;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

class TasksApiTest extends TestCase {
    private $app;
    private $pdo;
    private $container;

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

        $this->container = new class($this->pdo) implements \Psr\Container\ContainerInterface {
            private $services = [];
            private $pdo;

            public function __construct(PDO $pdo) {
                $this->pdo = $pdo;
            }

            public function set(string $id, $value): void {
                $this->services[$id] = $value;
            }

            public function get(string $id) {
                if (!isset($this->services[$id])) {
                    throw new \RuntimeException("Service $id not found");
                }
                if (is_callable($this->services[$id])) {
                    return $this->services[$id]($this);
                }
                return $this->services[$id];
            }

            public function has(string $id): bool {
                return isset($this->services[$id]);
            }

            public function getPdo(): PDO {
                return $this->pdo;
            }
        };

        $this->container->set(Database::class, function($container) {
            return new Database($container->getPdo());
        });

        $this->container->set(Paginator::class, function($container) {
            return new Paginator($container->getPdo(), 'tasks');
        });

        $app = AppFactory::createFromContainer($this->container);
        $app->addBodyParsingMiddleware();

        $app->add(function ($request, $handler) {
            $response = $handler->handle($request);
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        });

        $app->get('/', function ($request, $response) {
            $response->getBody()->write(json_encode([
                'message' => 'Welcome to Slim Tasks API',
                'version' => '1.0',
                'endpoints' => [
                    'GET /tasks' => 'Get all tasks',
                    'GET /tasks/{id}' => 'Get a task by ID',
                    'POST /tasks' => 'Create a new task',
                    'PUT /tasks/{id}' => 'Update a task',
                    'DELETE /tasks/{id}' => 'Delete a task'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->get('/tasks', function ($request, $response) use ($app) {
            $paginator = $app->getContainer()->get(Paginator::class);
            $queryParams = $request->getQueryParams();
            
            $page = max(1, (int)($queryParams['page'] ?? 1));
            $perPage = max(1, min(50, (int)($queryParams['per_page'] ?? 10)));
            
            $filters = [
                'status' => $queryParams['status'] ?? null,
                'search' => $queryParams['search'] ?? null
            ];
            
            $tasks = $paginator->paginate($page, $perPage, 'created_at DESC', $filters);
            $pagination = $paginator->getPaginationInfo($page, $perPage, $filters);
            
            $result = [
                'tasks' => $tasks,
                'pagination' => $pagination
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->get('/tasks/{id}', function ($request, $response, $args) use ($app) {
            $database = $app->getContainer()->get(Database::class);
            $task = $database->fetchById($args['id']);
            
            if (!$task) {
                $response->getBody()->write(json_encode(['error' => 'Task not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $response->getBody()->write(json_encode($task));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->post('/tasks', function ($request, $response) use ($app) {
            $database = $app->getContainer()->get(Database::class);
            $data = $request->getParsedBody();
            
            if (empty($data['title'])) {
                $response->getBody()->write(json_encode(['error' => 'Title is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $id = $database->insert($data);
            $response->getBody()->write(json_encode(['id' => $id]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        });

        $app->put('/tasks/{id}', function ($request, $response, $args) use ($app) {
            $database = $app->getContainer()->get(Database::class);
            $data = $request->getParsedBody();
            
            $existingTask = $database->fetchById($args['id']);
            if (!$existingTask) {
                $response->getBody()->write(json_encode(['error' => 'Task not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $success = $database->update($args['id'], $data);
            
            if ($success) {
                return $response->withStatus(204);
            } else {
                $response->getBody()->write(json_encode(['error' => 'Failed to update task']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        $app->delete('/tasks/{id}', function ($request, $response, $args) use ($app) {
            $database = $app->getContainer()->get(Database::class);
            $existingTask = $database->fetchById($args['id']);
            if (!$existingTask) {
                $response->getBody()->write(json_encode(['error' => 'Task not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $success = $database->delete($args['id']);
            
            if ($success) {
                return $response->withStatus(204);
            } else {
                $response->getBody()->write(json_encode(['error' => 'Failed to delete task']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        $this->app = $app;
    }

    public function testGetTasks() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Test', 'Description')");

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tasks');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson((string)$response->getBody());
        
        $data = json_decode((string)$response->getBody(), true);
        
        $this->assertArrayHasKey('tasks', $data);
        $this->assertArrayHasKey('pagination', $data);
        
        $tasks = $data['tasks'];
        $pagination = $data['pagination'];
        
        $this->assertCount(1, $tasks);
        $this->assertEquals('Test', $tasks[0]['title']);
        
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(1, $pagination['total']);
    }

    public function testCreateTask() {
        $stream = (new StreamFactory())->createStream(json_encode([
            'title' => 'New Task',
            'description' => 'Description'
        ]));

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tasks')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertJson((string)$response->getBody());
        
        $result = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('id', $result);
    }

    public function testUpdateTask() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('Old Title', 'Desc')");
        $id = $this->pdo->lastInsertId();

        $stream = (new StreamFactory())->createStream(json_encode([
            'title' => 'Updated Title',
            'description' => 'Updated Desc'
        ]));

        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/tasks/$id")
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testDeleteTask() {
        $this->pdo->exec("INSERT INTO tasks (title, description) VALUES ('To Delete', 'Desc')");
        $id = $this->pdo->lastInsertId();

        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/tasks/$id");
        $response = $this->app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testGetTaskNotFound() {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tasks/999');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJson((string)$response->getBody());
    }

    public function testCreateTaskWithoutTitle() {
        $stream = (new StreamFactory())->createStream(json_encode([
            'description' => 'Description without title'
        ]));

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/tasks')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson((string)$response->getBody());
    }

    public function testGetTasksWithPagination() {
        for ($i = 1; $i <= 15; $i++) {
            $createdAt = date('Y-m-d H:i:s', time() + $i);
            $this->pdo->exec("INSERT INTO tasks (title, description, created_at) VALUES ('Task $i', 'Description $i', '$createdAt')");
        }

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tasks?page=1&per_page=5');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        
        $this->assertArrayHasKey('tasks', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(5, $data['tasks']);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(5, $data['pagination']['per_page']);
        $this->assertEquals(15, $data['pagination']['total']);
        $this->assertEquals(3, $data['pagination']['total_pages']);
        $this->assertTrue($data['pagination']['has_next']);
        $this->assertFalse($data['pagination']['has_prev']);
        
        $this->assertEquals('Task 15', $data['tasks'][0]['title']);
    }

    public function testGetTasksWithFilters() {
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Pending Task', 'pending')");
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Completed Task', 'completed')");
        $this->pdo->exec("INSERT INTO tasks (title, status) VALUES ('Another Pending', 'pending')");

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tasks?status=pending');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        
        $this->assertCount(2, $data['tasks']);
        $this->assertEquals(2, $data['pagination']['total']);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tasks?search=Pending');
        $response = $this->app->handle($request);

        $data = json_decode((string)$response->getBody(), true);
        $this->assertCount(2, $data['tasks']);
    }
}