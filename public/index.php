<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use SlimTasksApi\Models\Database;

require __DIR__ . '/../vendor/autoload.php';

$container = new class() implements \Psr\Container\ContainerInterface {
    private $services = [];

    public function set(string $id, $value): void {
        $this->services[$id] = $value;
    }

    public function get(string $id) {
        if (!isset($this->services[$id])) {
            throw new RuntimeException("Service $id not found");
        }
        if (is_callable($this->services[$id])) {
            return $this->services[$id]();
        }
        return $this->services[$id];
    }

    public function has(string $id): bool {
        return isset($this->services[$id]);
    }
};

$container->set(Database::class, function() {
    $databasePath = __DIR__ . '/../database/database.sqlite';
    return Database::createWithSqlite($databasePath);
});

$container->set(\SlimTasksApi\Services\Paginator::class, function() use ($container) {
    $pdo = $container->get(Database::class)->getPdo();
    return new \SlimTasksApi\Services\Paginator($pdo, 'tasks');
});

$app = AppFactory::createFromContainer($container);
$app->addBodyParsingMiddleware();

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

$app->get('/', function (Request $request, Response $response) use ($container) {
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

$app->get('/tasks', function (Request $request, Response $response) use ($container) {
    $paginator = $container->get(\SlimTasksApi\Services\Paginator::class);
    $queryParams = $request->getQueryParams();
    
    $page = max(1, (int)($queryParams['page'] ?? 1));
    $perPage = max(1, min(50, (int)($queryParams['per_page'] ?? 10)));
    
    $tasks = $database->fetchPaginated($page, $perPage);
    $pagination = $database->getPaginationInfo($page, $perPage);

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

$app->get('/tasks/{id}', function (Request $request, Response $response, $args) use ($container) {
    $database = $container->get(Database::class);
    $task = $database->fetchById($args['id']);
    
    if (!$task) {
        $response->getBody()->write(json_encode(['error' => 'Task not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    
    $response->getBody()->write(json_encode($task));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/tasks', function (Request $request, Response $response) use ($container) {
    $database = $container->get(Database::class);
    $data = $request->getParsedBody();
    
    if (empty($data['title'])) {
        $response->getBody()->write(json_encode(['error' => 'Title is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
    $id = $database->insert($data);
    $response->getBody()->write(json_encode(['id' => $id]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});

$app->put('/tasks/{id}', function (Request $request, Response $response, $args) use ($container) {
    $database = $container->get(Database::class);
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

$app->delete('/tasks/{id}', function (Request $request, Response $response, $args) use ($container) {
    $database = $container->get(Database::class);
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

$app->run();