// Lawyer Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Display user name from localStorage
    const user = JSON.parse(localStorage.getItem('user'));
    if (user) {
        document.getElementById('userName').textContent = user.full_name;
        document.getElementById('userName').dataset.userId = user.id;
    }

    // Handle logout
    document.querySelector('a[href="logout.php"]').addEventListener('click', function(e) {
        e.preventDefault();
        // Clear localStorage
        localStorage.removeItem('user');
        localStorage.removeItem('token');
        // Redirect to logout
        window.location.href = 'logout.php';
    });

    // Initialize all sections except chat
    initializePendingQueries();
    initializeTemplates();
    initializeProfile();
    initializeRatings();
    initializeHistory();

    // Add event listeners for chat buttons
    document.querySelectorAll('.start-chat').forEach(button => {
        button.addEventListener('click', function() {
            const queryId = this.dataset.id;
            if (!queryId) {
                console.error('No query ID found on chat button');
                showError('Invalid query ID. Please try again.');
                return;
            }
            initializeChat(queryId);
            const chatModal = new bootstrap.Modal(document.getElementById('chatModal'));
            chatModal.show();
        });
    });

    // Handle View Query button clicks
    document.querySelectorAll('.view-query').forEach(button => {
        button.addEventListener('click', function() {
            const queryId = this.dataset.id;
            showQueryDetails(queryId);
        });
    });

    // Handle Accept Query button clicks
    document.querySelectorAll('.accept-query').forEach(button => {
        button.addEventListener('click', function() {
            const queryId = this.dataset.id;
            acceptQuery(queryId);
        });
    });

    // Function to show query details
    function showQueryDetails(queryId) {
        fetch(`api/get_query_details.php?id=${queryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show query details in a modal
                    const modal = new bootstrap.Modal(document.getElementById('queryDetailsModal'));
                    document.getElementById('queryTitle').textContent = data.query.title;
                    document.getElementById('queryDescription').textContent = data.query.description;
                    document.getElementById('queryCategory').textContent = data.query.category;
                    document.getElementById('queryUrgency').textContent = data.query.urgency_level;
                    document.getElementById('queryClient').textContent = data.query.client_name;
                    modal.show();
                } else {
                    alert('Failed to load query details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load query details');
            });
    }

    // Function to accept a query
    function acceptQuery(queryId) {
        if (!confirm('Are you sure you want to accept this query?')) {
            return;
        }

        fetch('api/accept_query.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                queryId: queryId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Query accepted successfully');
                location.reload(); // Refresh the page to update the list
            } else {
                alert(data.message || 'Failed to accept query');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to accept query');
        });
    }
});

// Chat functionality
let currentQueryId = null;
let lastMessageId = 0;
let pollInterval = null;

function showError(message) {
    alert(message);
}

function appendMessage(message) {
    const chatMessages = document.querySelector('.chat-messages');
    const messageElement = document.createElement('div');
    const isCurrentUser = message.sender_id == JSON.parse(localStorage.getItem('user')).id;
    
    messageElement.className = `message ${isCurrentUser ? 'sent' : 'received'}`;
    messageElement.innerHTML = `
        <div class="message-content">
            <div class="message-header">
                <span class="sender-name">${message.display_name || message.sender_name}</span>
                <span class="message-time">${new Date(message.created_at).toLocaleTimeString()}</span>
            </div>
            <div class="message-text">${escapeHtml(message.message)}</div>
        </div>
    `;
    chatMessages.appendChild(messageElement);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function startPolling(queryId) {
    // Clear any existing polling
    if (pollInterval) {
        clearInterval(pollInterval);
    }

    // Start polling for new messages
    pollInterval = setInterval(() => {
        fetch(`api/get_messages.php?queryId=${queryId}&lastId=${lastMessageId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && Array.isArray(data.messages)) {
                    data.messages.forEach(message => {
                        appendMessage(message);
                        if (message.id > lastMessageId) {
                            lastMessageId = message.id;
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error polling messages:', error);
                // Don't show error to user for polling failures
            });
    }, 3000); // Poll every 3 seconds
}

function initializeChat(queryId) {
    if (!queryId) {
        console.error('Invalid query ID provided to initializeChat');
        showError('Invalid query ID. Please try again.');
        return;
    }

    currentQueryId = queryId;
    const chatMessages = document.querySelector('.chat-messages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = chatForm.querySelector('input');

    // Clear existing messages
    chatMessages.innerHTML = '';
    lastMessageId = 0;

    // Show loading state
    chatMessages.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2">Loading messages...</div>
        </div>
    `;

    // Load initial messages
    fetch(`api/get_messages.php?queryId=${queryId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && Array.isArray(data.messages)) {
                chatMessages.innerHTML = '';
                if (data.messages.length === 0) {
                    chatMessages.innerHTML = `
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <div>No messages yet. Start the conversation!</div>
                        </div>
                    `;
                } else {
                    data.messages.forEach(message => {
                        appendMessage(message);
                        if (message.id > lastMessageId) {
                            lastMessageId = message.id;
                        }
                    });
                }
                // Start polling for new messages
                startPolling(queryId);
            } else {
                throw new Error(data.error || 'Failed to load messages');
            }
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            chatMessages.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Failed to load messages: ${error.message}
                    <button class="btn btn-sm btn-outline-danger ms-3" onclick="initializeChat(${queryId})">
                        <i class="fas fa-sync-alt"></i> Retry
                    </button>
                </div>
            `;
        });

    // Handle chat form submission
    chatForm.onsubmit = function(e) {
        e.preventDefault();
        const message = messageInput.value.trim();
        if (message) {
            // Disable input while sending
            messageInput.disabled = true;
            const submitButton = chatForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            fetch('api/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify({
                    queryId: queryId,
                    message: message
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    // The new message will be picked up by the polling
                } else {
                    throw new Error(data.error || 'Failed to send message');
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                showError('Failed to send message: ' + error.message);
            })
            .finally(() => {
                // Re-enable input
                messageInput.disabled = false;
                submitButton.disabled = false;
                submitButton.innerHTML = 'Send';
                messageInput.focus();
            });
        }
    };
}

// Stop polling when modal is closed
document.getElementById('chatModal').addEventListener('hidden.bs.modal', function() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
    currentQueryId = null;
    lastMessageId = 0;
});

// Pending Queries Section
function initializePendingQueries() {
    loadPendingQueries();
}

function loadPendingQueries() {
    fetch('api/get_pending_queries.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPendingQueries(data.queries);
            } else {
                showError(data.message || 'Failed to load pending queries');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to load pending queries');
        });
}

function displayPendingQueries(queries) {
    const container = document.getElementById('pendingQueriesContainer');
    if (!container) return;

    if (queries.length === 0) {
        container.innerHTML = '<p class="text-center">No pending queries found.</p>';
        return;
    }

    const html = queries.map(query => `
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">${escapeHtml(query.title)}</h5>
                <p class="card-text">${escapeHtml(query.description)}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-${getCategoryColor(query.category)}">${escapeHtml(query.category)}</span>
                        <span class="badge bg-${query.urgency_level === 'high' ? 'danger' : query.urgency_level === 'medium' ? 'warning' : 'info'}">${escapeHtml(query.urgency_level)}</span>
                    </div>
                    <div>
                        <button class="btn btn-primary btn-sm view-query" data-id="${query.id}">View Details</button>
                        <button class="btn btn-success btn-sm accept-query" data-id="${query.id}">Accept Query</button>
                        ${query.status === 'assigned' || query.status === 'in_progress' ? 
                            `<button class="btn btn-info btn-sm start-chat" data-id="${query.id}">
                                <i class="fas fa-comments"></i> Chat
                            </button>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;

    // Reattach event listeners
    document.querySelectorAll('.view-query').forEach(button => {
        button.addEventListener('click', function() {
            showQueryDetails(this.dataset.id);
        });
    });

    document.querySelectorAll('.accept-query').forEach(button => {
        button.addEventListener('click', function() {
            acceptQuery(this.dataset.id);
        });
    });

    // Add chat button listeners
    document.querySelectorAll('.start-chat').forEach(button => {
        button.addEventListener('click', function() {
            const queryId = this.dataset.id;
            if (!queryId) {
                console.error('No query ID found on chat button');
                showError('Invalid query ID. Please try again.');
                return;
            }
            initializeChat(queryId);
            const chatModal = new bootstrap.Modal(document.getElementById('chatModal'));
            chatModal.show();
        });
    });
}

function initializeTemplates() {
    // Template management code here
}

function initializeProfile() {
    // Profile management code here
}

function initializeRatings() {
    // Ratings management code here
}

function initializeHistory() {
    // History management code here
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

function getCategoryColor(category) {
    const colors = {
        'criminal': 'danger',
        'civil': 'primary',
        'family': 'success',
        'corporate': 'info',
        'property': 'warning'
    };
    return colors[category.toLowerCase()] || 'secondary';
}

function showSuccess(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show';
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
    setTimeout(() => alertDiv.remove(), 5000);
} 