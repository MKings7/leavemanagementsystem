<?php
/**
 * Auto Scheduler
 * 
 * This script provides automatic scheduling functionality without relying on cron jobs.
 * It triggers scheduled tasks when users visit the site, based on the last execution time.
 */

// Make sure we only include each dependency once
require_once __DIR__ . "/dbconnect.php";
require_once __DIR__ . "/leave_expiry_notifications.php";

// Make sure the AutoScheduler class hasn't been defined already
if (!class_exists('AutoScheduler')) {
    class AutoScheduler {
        private $conn;
        private $tasksTable = 'scheduled_tasks';
        
        public function __construct($conn) {
            $this->conn = $conn;
            $this->ensureTasksTableExists();
        }
        
        /**
         * Create the scheduled tasks table if it doesn't exist
         */
        private function ensureTasksTableExists() {
            $query = "CREATE TABLE IF NOT EXISTS {$this->tasksTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_name VARCHAR(100) NOT NULL UNIQUE,
                last_run DATETIME,
                frequency_minutes INT NOT NULL DEFAULT 1440,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1
            )";
            mysqli_query($this->conn, $query);
            
            // Ensure the required tasks exist in the table
            $this->ensureTaskExists('leave_expiry_notification', 1440); // Once per day (1440 minutes)
        }
        
        /**
         * Make sure a specific task exists in the tasks table
         */
        private function ensureTaskExists($taskName, $frequencyMinutes) {
            $stmt = mysqli_prepare($this->conn, 
                "INSERT IGNORE INTO {$this->tasksTable} (task_name, frequency_minutes) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "si", $taskName, $frequencyMinutes);
            mysqli_stmt_execute($stmt);
        }
        
        /**
         * Check and run due tasks
         */
        public function checkAndRunTasks() {
            $query = "SELECT id, task_name, last_run, frequency_minutes 
                     FROM {$this->tasksTable} 
                     WHERE is_enabled = 1";
            $result = mysqli_query($this->conn, $query);
            
            while ($task = mysqli_fetch_assoc($result)) {
                $shouldRun = false;
                
                if (is_null($task['last_run'])) {
                    $shouldRun = true;
                } else {
                    $lastRun = new DateTime($task['last_run']);
                    $now = new DateTime();
                    $interval = $lastRun->diff($now);
                    $minutesPassed = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
                    
                    if ($minutesPassed >= $task['frequency_minutes']) {
                        $shouldRun = true;
                    }
                }
                
                if ($shouldRun) {
                    $this->runTask($task['task_name'], $task['id']);
                }
            }
        }
        
        /**
         * Execute a specific task
         */
        private function runTask($taskName, $taskId) {
            $result = false;
            $logMessage = "Running task: $taskName";
            
            try {
                switch ($taskName) {
                    case 'leave_expiry_notification':
                        $daysBeforeExpiry = 2; // Default: notify 2 days before leave expiry
                        $results = sendLeaveExpiryNotifications($this->conn, $daysBeforeExpiry);
                        $logMessage .= " - Success: " . count($results['success']) . ", Errors: " . count($results['error']);
                        $result = true;
                        break;
                        
                    // Add more tasks here as needed
                    
                    default:
                        $logMessage .= " - Unknown task";
                        break;
                }
                
                // Update the last run time
                if ($result) {
                    $now = date('Y-m-d H:i:s');
                    $updateStmt = mysqli_prepare($this->conn, 
                        "UPDATE {$this->tasksTable} SET last_run = ? WHERE id = ?");
                    mysqli_stmt_bind_param($updateStmt, "si", $now, $taskId);
                    mysqli_stmt_execute($updateStmt);
                }
                
                // Log the execution
                $this->logTaskExecution($logMessage);
                
            } catch (Exception $e) {
                $this->logTaskExecution("Error running task $taskName: " . $e->getMessage());
            }
        }
        
        /**
         * Log task execution details
         */
        private function logTaskExecution($message) {
            $logDir = __DIR__ . '/../logs';
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/auto_scheduler.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        }
    }
}

// Prevent running the scheduler on every page load - use a probability factor
// This makes it run approximately once every 10 page loads
if (mt_rand(1, 10) === 1) {
    $scheduler = new AutoScheduler($conn);
    $scheduler->checkAndRunTasks();
}
?>
