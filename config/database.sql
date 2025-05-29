-- Legal Queries Table
CREATE TABLE IF NOT EXISTS legal_queries (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    client_id INT(11) NOT NULL,
    category VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    urgency_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    assigned_lawyer_id INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_lawyer_id) REFERENCES lawyers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Legal Query Categories Table
CREATE TABLE IF NOT EXISTS legal_query_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default categories
INSERT INTO legal_query_categories (name, description) VALUES
('Family Law', 'Issues related to marriage, divorce, child custody, and family matters'),
('Criminal Law', 'Criminal cases, charges, and legal proceedings'),
('Civil Law', 'Disputes between individuals or organizations'),
('Property Law', 'Real estate, property rights, and land disputes'),
('Corporate Law', 'Business-related legal matters and corporate governance'),
('Employment Law', 'Workplace issues, contracts, and labor disputes'),
('Intellectual Property', 'Copyright, patents, trademarks, and IP rights'),
('Immigration Law', 'Visa, citizenship, and immigration matters'),
('Tax Law', 'Tax-related issues and compliance'),
('Other', 'Other legal matters not covered by the above categories');

-- Messages Table
CREATE TABLE IF NOT EXISTS messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT(11) NOT NULL,
    sender_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_id) REFERENCES legal_queries(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Message Templates Table (for lawyers)
CREATE TABLE IF NOT EXISTS message_templates (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    lawyer_id INT(11) NOT NULL,
    title VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 