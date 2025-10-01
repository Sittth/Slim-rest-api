import React, { useState, useEffect } from 'react'
import './TaskManager.css'

const TaskManager = () => {
    const [tasks, setTasks] = useState([])
    const [newTask, setNewTask] = useState({
        title: '',
        description: '',
        status: 'pending'
    })
    const [loading, setLoading] = useState(false)
    const [pagination, setPagination] = useState({})
    const [filters, setFilters] = useState({
        page: 1,
        per_page: 10,
        status: '',
        search: ''
    })
    useEffect(() => {
        fetchTasks()
    }, [filters])

    const fetchTasks = async () => {
        setLoading(true)
        try {
            const queryParams = new URLSearchParams()
            Object.entries(filters).forEach(([key, value]) => {
                if (value) queryParams.append(key, value)
            })

            const response = await fetch(`/api/tasks?${queryParams}`)
            if (!response.ok) throw new Error('Failed to fetch tasks')

            const data = await response.json()
            setTasks(data.tasks)
            setPagination(data.pagination)
        } catch (error) {
            console.error('Error fetching tasks:', error)
            alert('Ошибка при загрузке задач')
        } finally {
            setLoading(false)
        }
    }
    
    const createTask = async (e) => {
        e.preventDefault()
        if (!newTask.title.trim()) {
            alert('Название задачи обязательно')
            return
        }

        try {
            const response = await fetch('/api/tasks', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(newTask)
            })

            if (!response.ok) throw new Error('Failed to create task')

            setNewTask({ title: '', description: '', status: 'pending' })
            fetchTasks()
            alert('Задача успешно создана!')
        } catch (error) {
            console.error('Error creating task:', error)
            alert('Ошибка при создании задачи')
        }
    }
    
    const updateTaskStatus = async (taskId, currentStatus) => {
        try {
            const taskResponse = await fetch(`/api/tasks/${taskId}`)
            if (!taskResponse.ok) throw new Error('Failed to fetch task')
            const task = await taskResponse.json()
            const newStatus = currentStatus === 'completed' ? 'pending' : 'completed'
            const updateResponse = await fetch(`/api/tasks/${taskId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ...task,
                    status: newStatus
                })
            })

            if (!updateResponse.ok) throw new Error('Failed to update task')

            fetchTasks()
            alert('Статус задачи обновлен!')
        } catch (error) {
            console.error('Error updating task:', error)
            alert('Ошибка при обновлении задачи')
        }
    }

    const deleteTask = async (taskId) => {
        if (!confirm('Вы уверены, что хотите удалить эту задачу?')) {
            return
        }

        try {
            const response = await fetch(`/api/tasks/${taskId}`, {
                method: 'DELETE'
            })

            if (!response.ok) throw new Error('Failed to delete task')
        
            fetchTasks()
            alert('Задача удалена!')
        } catch (error) {
            console.error('Error deleting task:', error)
            alert('Ошибка при удалении задачи')
        }
    }

    const handleFilterChange = (key, value) => {
        setFilters(prev => ({
            ...prev,
            [key]: value,
            page: 1
        }))
    }

    const handlePageChange = (newPage) => {
        setFilters(prev => ({ ...prev, page: newPage }))
    }

    return (
        <div className="task-manager">
            <header className="header">
                <h1>📝 Task Manager</h1>
                <p>Управление задачами через REST API</p>
            </header>
            <div className="card">
                <h2>Добавить новую задачу</h2>
                <form onSubmit={createTask} className="task-form">
                <div className="form-group">
                    <label htmlFor="title">Название задачи:</label>
                    <input
                    type="text"
                    id="title"
                    value={newTask.title}
                    onChange={(e) => setNewTask({...newTask, title: e.target.value})}
                    placeholder="Введите название задачи..."
                    required
                    />
                </div>
                
                <div className="form-group">
                    <label htmlFor="description">Описание:</label>
                    <textarea
                    id="description"
                    value={newTask.description}
                    onChange={(e) => setNewTask({...newTask, description: e.target.value})}
                    placeholder="Введите описание задачи..."
                    rows="3"
                    />
                </div>
                
                <div className="form-group">
                    <label htmlFor="status">Статус:</label>
                    <select
                    id="status"
                    value={newTask.status}
                    onChange={(e) => setNewTask({...newTask, status: e.target.value})}
                    >
                    <option value="pending">В ожидании</option>
                    <option value="in_progress">В работе</option>
                    <option value="completed">Завершено</option>
                    </select>
                </div>
                
                <button type="submit" className="btn btn-primary">
                    Добавить задачу
                </button>
                </form>
            </div>

            <div className="card">
                <h2>Фильтры</h2>
                <div className="filters">
                <div className="form-group">
                    <label>Статус:</label>
                    <select
                    value={filters.status}
                    onChange={(e) => handleFilterChange('status', e.target.value)}
                    >
                    <option value="">Все статусы</option>
                    <option value="pending">В ожидании</option>
                    <option value="in_progress">В работе</option>
                    <option value="completed">Завершено</option>
                    </select>
                </div>
                
                <div className="form-group">
                    <label>Поиск:</label>
                    <input
                    type="text"
                    placeholder="Поиск по названию или описанию..."
                    value={filters.search}
                    onChange={(e) => handleFilterChange('search', e.target.value)}
                    />
                </div>
                
                <div className="form-group">
                    <label>Задач на странице:</label>
                    <select
                    value={filters.per_page}
                    onChange={(e) => handleFilterChange('per_page', parseInt(e.target.value))}
                    >
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    </select>
                </div>
                </div>
            </div>

            <div className="card">
                <h2>
                Список задач 
                {pagination.total !== undefined && ` (${pagination.total})`}
                </h2>
                
                {loading ? (
                <div className="loading">Загрузка задач...</div>
                ) : (
                <>
                    <div className="tasks-list">
                    {tasks.length === 0 ? (
                        <p className="no-tasks">Задачи не найдены</p>
                    ) : (
                        tasks.map(task => (
                        <div key={task.id} className="task-item">
                            <div className="task-info">
                            <h3>{task.title}</h3>
                            {task.description && <p>{task.description}</p>}
                            <div className="task-meta">
                                <span className={`status-badge status-${task.status}`}>
                                {getStatusText(task.status)}
                                </span>
                                <span className="task-date">
                                Создано: {new Date(task.created_at).toLocaleString('ru-RU')}
                                </span>
                                {task.updated_at !== task.created_at && (
                                <span className="task-date">
                                    Обновлено: {new Date(task.updated_at).toLocaleString('ru-RU')}
                                </span>
                                )}
                            </div>
                            </div>
                            
                            <div className="task-actions">
                            <button 
                                className={`btn ${task.status === 'completed' ? 'btn-warning' : 'btn-success'}`}
                                onClick={() => updateTaskStatus(task.id, task.status)}
                            >
                                {task.status === 'completed' ? 'Вернуть' : 'Завершить'}
                            </button>
                            <button 
                                className="btn btn-danger"
                                onClick={() => deleteTask(task.id)}
                            >
                                Удалить
                            </button>
                            </div>
                        </div>
                        ))
                    )}
                    </div>

                    {pagination.total_pages > 1 && (
                    <div className="pagination">
                        <button 
                        className="btn btn-secondary"
                        disabled={!pagination.has_prev}
                        onClick={() => handlePageChange(pagination.current_page - 1)}
                        >
                        Назад
                        </button>
                        
                        <span className="pagination-info">
                        Страница {pagination.current_page} из {pagination.total_pages}
                        </span>
                        
                        <button 
                        className="btn btn-secondary"
                        disabled={!pagination.has_next}
                        onClick={() => handlePageChange(pagination.current_page + 1)}
                        >
                        Вперед
                        </button>
                    </div>
                    )}
                </>
                )}
            </div>
        </div>
    )
}

function getStatusText(status) {
    const statusMap = {
        'pending': 'В ожидании',
        'in_progress': 'В работе', 
        'completed': 'Завершено'
    }
    return statusMap[status] || status
}

export default TaskManager