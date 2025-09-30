<?php
namespace SlimTasksApi\Services;

use PDO;
use PDOException;

class Paginator {
    private PDO $pdo;
    private string $tableName;

    public function __construct(PDO $pdo, string $tableName = 'tasks') {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
    }

    public function paginate(
        int $page = 1, 
        int $perPage = 10, 
        string $orderBy = 'created_at DESC',
        array $filters = []
    ): array {
        $offset = ($page - 1) * $perPage;
        
        list($whereClause, $params) = $this->buildWhereClause($filters);
        
        $sql = "
            SELECT * FROM {$this->tableName} 
            {$whereClause}
            ORDER BY {$orderBy} 
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTotalCount(array $filters = []): int {
        list($whereClause, $params) = $this->buildWhereClause($filters);
        
        $sql = "SELECT COUNT(*) as count FROM {$this->tableName} {$whereClause}";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['count'] ?? 0);
    }

    public function getPaginationInfo(
        int $page = 1, 
        int $perPage = 10, 
        array $filters = []
    ): array {
        $total = $this->getTotalCount($filters);
        $totalPages = ceil($total / $perPage);
        
        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ];
    }

    private function buildWhereClause(array $filters): array {
        $conditions = [];
        $params = [];
        
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                switch ($key) {
                    case 'status':
                        if (in_array($value, ['pending', 'in_progress', 'completed'])) {
                            $conditions[] = "status = :status";
                            $params[':status'] = $value;
                        }
                        break;
                    case 'search':
                        $conditions[] = "(title LIKE :search OR description LIKE :search)";
                        $params[':search'] = '%' . $value . '%';
                        break;
                }
            }
        }
        
        $whereClause = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        
        return [$whereClause, $params];
    }
}