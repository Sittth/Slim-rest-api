<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SlimTasksApi\Models\Database;
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
            $database = new Database();
            $reflection = new \ReflectionClass($database);
            $property = $reflection->getProperty('pdo');
            $property->setAccessible(true);
            $property->setValue($database, $container->getPdo());
            return $database;
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
            $database = $app->getContainer()->get(Database::class);
            $tasks = $database->fetchAll();
            $response->getBody()->write(json_encode($tasks));
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
        
        $tasks = json_decode((string)$response->getBody(), true);
        $this->assertCount(1, $tasks);
        $this->assertEquals('Test', $tasks[0]['title']);
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
}