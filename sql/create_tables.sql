-- Create users table
CREATE TABLE IF NOT EXISTS users (
    UserID INT PRIMARY KEY AUTO_INCREMENT,
    Username VARCHAR(50) UNIQUE NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Fullnames VARCHAR(100) NOT NULL,
    Email VARCHAR(100) UNIQUE NOT NULL,
    Department VARCHAR(50),
    admin BOOLEAN DEFAULT 0,
    hr BOOLEAN DEFAULT 0,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Make sure Department column exists in users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS Department VARCHAR(50) AFTER Email;

-- Create leave_types table
CREATE TABLE IF NOT EXISTS leave_types (
    LeaveTypeID INT PRIMARY KEY AUTO_INCREMENT,
    LeaveName VARCHAR(50) NOT NULL,
    Description TEXT,
    MaxDays INT NOT NULL,
    Status BOOLEAN DEFAULT 1
);

-- Create leave_requests table
CREATE TABLE IF NOT EXISTS leave_requests (
    RequestID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    LeaveTypeID INT NOT NULL,
    StartDate DATE NOT NULL,
    EndDate DATE NOT NULL,
    Reason TEXT NOT NULL,
    Status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    AppliedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES users(UserID),
    FOREIGN KEY (LeaveTypeID) REFERENCES leave_types(LeaveTypeID)
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    NotificationID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    Message TEXT NOT NULL,
    Type VARCHAR(50) NOT NULL,
    IsRead BOOLEAN DEFAULT 0,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES users(UserID)
);

-- Insert default admin user
INSERT INTO users (Username, Password, Fullnames, Email, admin) 
VALUES ('admin', '$2y$10$FKggqFejw8rH6uYY3SJrUu6PXlMzJ9DXmbeVOE5.kr1RkYMz5WxWC', 'System Administrator', 'admin@example.com', 1)
ON DUPLICATE KEY UPDATE Username=Username;

-- Insert some default leave types
INSERT INTO leave_types (LeaveName, Description, MaxDays) VALUES
('Annual Leave', 'Regular vacation leave', 30),
('Sick Leave', 'Medical related leave', 14),
('Maternity Leave', 'Leave for expectant mothers', 90),
('Paternity Leave', 'Leave for new fathers', 14),
('Bereavement Leave', 'Leave due to death of immediate family member', 5)
ON DUPLICATE KEY UPDATE LeaveName=LeaveName;

-- Create departments table if not exists
CREATE TABLE IF NOT EXISTS departments (
    DepartmentID INT PRIMARY KEY AUTO_INCREMENT,
    DepartmentName VARCHAR(100) UNIQUE NOT NULL,
    Description TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert some default departments
INSERT INTO departments (DepartmentName, Description) VALUES
('Human Resources', 'HR department responsible for employee relations'),
('Information Technology', 'IT department responsible for technical support'),
('Finance', 'Finance and accounting department'),
('Marketing', 'Marketing and communications department'),
('Operations', 'Operations and logistics department')
ON DUPLICATE KEY UPDATE DepartmentName=DepartmentName;