<?php
namespace SlimTasksApi\Models;

use PDO;
use PDOException;

class Database {
    private $pdo;

    public function __construct() {
        $databasePath = __DIR__ . '/../../database/database.sqlite';
        $dir = dirname($databasePath);
        
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        try {
            $this->pdo = new PDO('sqlite:' . $databasePath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTable();
        } catch (PDOException $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }

    private function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($query);
    }

    public function fetchAll() {
        $stmt = $this->pdo->query("SELECT * FROM tasks ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insert($data) {
        $sql = "INSERT INTO tasks (title, description, status) VALUES (:title, :description, :status)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending'
        ]);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE tasks SET title = :title, description = :description, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending'
        ]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}