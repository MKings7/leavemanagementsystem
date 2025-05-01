-- Create leave_substitutes table
CREATE TABLE IF NOT EXISTS leave_substitutes (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    RequestID INT NOT NULL,
    SubstituteID INT NOT NULL,
    Status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
    DateAssigned TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (RequestID) REFERENCES leave_requests(RequestID) ON DELETE CASCADE,
    FOREIGN KEY (SubstituteID) REFERENCES users(UserID) ON DELETE CASCADE,
    UNIQUE KEY unique_substitute_request (RequestID, SubstituteID)
);