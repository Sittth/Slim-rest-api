<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use SlimTasksApi\Models\Database;

require __DIR__ . '/../vendor/autoload.php';

$database = new Database();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

$app->get('/', function (Request $request, Response $response) {
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

$app->get('/tasks', function (Request $request, Response $response) use ($database) {
    $tasks = $database->fetchAll();
    $response->getBody()->write(json_encode($tasks));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/tasks/{id}', function (Request $request, Response $response, $args) use ($database) {
    $task = $database->fetchById($args['id']);
    
    if (!$task) {
        $response->getBody()->write(json_encode(['error' => 'Task not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    
    $response->getBody()->write(json_encode($task));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/tasks', function (Request $request, Response $response) use ($database) {
    $data = $request->getParsedBody();
    
    if (empty($data['title'])) {
        $response->getBody()->write(json_encode(['error' => 'Title is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
    $id = $database->insert($data);
    $response->getBody()->write(json_encode(['id' => $id]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});

$app->put('/tasks/{id}', function (Request $request, Response $response, $args) use ($database) {
    $data = $request->getParsedBody();
    
    // Check if task exists
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

$app->delete('/tasks/{id}', function (Request $request, Response $response, $args) use ($database) {
    // Check if task exists
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