// Lawyer Dashboard JavaScript

// Global variables
let currentQueryId = null;
let lastMessageId = 0;
let chatInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the lawyer dashboard (session-based auth)
    console.log('Lawyer dashboard loaded');
    
    // Set user name in navbar (will be populated by PHP)
    const userNameElement = document.getElementById('userName');
    if (userNameElement && !userNameElement.textContent.trim()) {
        console.log('User name not set, will be populated by PHP');
    }

    // Chat functionality
    let currentQueryId = null;
    let lastMessageId = 0;
    let chatInterval = null;

    function showError(message) {
        console.error('Chat Error:', message);
        alert(message);
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function appendMessage(message) {
        try {
            console.log('Appending message:', message);
            const chatMessages = document.querySelector('#chatModal .chat-messages');
            if (!chatMessages) {
                console.error('Chat messages container not found');
                return;
            }

            const messageElement = document.createElement('div');
            messageElement.className = `message ${message.message_type}`;
            
            const time = new Date(message.created_at).toLocaleTimeString([], { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            messageElement.innerHTML = `
                <div class="message-content">
                    <div class="message-header">
                        <span class="sender-name">${message.display_name}</span>
                        <span class="message-time">${time}</span>
                    </div>
                    <div class="message-text">${escapeHtml(message.message)}</div>
                </div>
            `;
            chatMessages.appendChild(messageElement);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        } catch (error) {
            console.error('Error appending message:', error);
        }
    }

    function startPolling(queryId) {
        console.log('Starting polling for query:', queryId);
        if (chatInterval) {
            clearInterval(chatInterval);
        }
        
        chatInterval = setInterval(() => {
            console.log('Polling for new messages, lastId:', lastMessageId);
            if (!token) {
                clearInterval(chatInterval);
                showError('Please log in to continue');
                window.location.href = 'login.html';
                return;
            }

            fetch(`api/get_messages.php?id=${queryId}&type=query&lastId=${lastMessageId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            })
            .then(response => {
                if (response.status === 401) {
                    clearInterval(chatInterval);
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                    window.location.href = 'login.html';
                    throw new Error('Session expired. Please log in again.');
                }
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Poll response:', data);
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
                if (error.message.includes('Session expired')) {
                    showError(error.message);
                }
            });
        }, 3000); // Poll every 3 seconds
    }

    // Start chat button click handler
    document.querySelectorAll('.start-chat').forEach(button => {
        button.addEventListener('click', function() {
            const queryId = this.dataset.id;
            if (!queryId) {
                console.error('No query ID found');
                return;
            }

            // Check authentication before starting chat
            if (!token) {
                console.error('No token found');
                window.location.href = 'login.html';
                return;
            }

            // Update query status to in_progress
            fetch('api/start_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include',
                body: JSON.stringify({ queryId: queryId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentQueryId = queryId;
                    lastMessageId = 0;
                    
                    // Show chat modal
            const chatModal = new bootstrap.Modal(document.getElementById('chatModal'));
            chatModal.show();
                    
                    // Initialize chat
                    initializeChat(queryId);
                } else {
                    console.error('Failed to start chat:', data.message);
                    alert(data.message || 'Failed to start chat');
                }
            })
            .catch(error => {
                console.error('Error starting chat:', error);
                alert('Failed to start chat. Please try again.');
            });
        });
    });

    // Initialize chat function
    function initializeChat(queryId) {
        console.log('Initializing chat for query:', queryId);
        if (!queryId) {
            console.error('Invalid query ID provided to initializeChat');
            showError('Invalid query ID. Please try again.');
            return;
        }

        // Check if user is logged in (session-based auth)
        console.log('Initializing chat for query:', queryId);

        currentQueryId = queryId;
        const chatMessages = document.querySelector('#chatModal .chat-messages');
        if (!chatMessages) {
            console.error('Chat messages container not found');
            return;
        }

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
        console.log('Fetching initial messages for query:', queryId);
        fetch(`api/get_messages.php?id=${queryId}&type=query`, {
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Initial messages response:', data);
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
                    ${error.message}
                    <button class="btn btn-sm btn-outline-danger ms-3" onclick="initializeChat(${queryId})">
                        <i class="fas fa-sync-alt"></i> Retry
                    </button>
                </div>
            `;
        });
    }

    // Chat form submission
    const chatForm = document.getElementById('chatForm');
    if (chatForm) {
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const token = localStorage.getItem('token');
            if (!token) {
                console.error('No token found');
                window.location.href = 'login.html';
                return;
            }

            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message || !currentQueryId) return;

            try {
                console.log('Sending message with token:', token.substring(0, 20) + '...');
                const response = await fetch('api/send_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        query_id: currentQueryId,
                        message: message
                    })
                });

                if (response.status === 401) {
                    console.error('Unauthorized access');
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                    window.location.href = 'login.html';
                    return;
                }

                const data = await response.json();
                console.log('Send message response:', data);
                
                if (data.success) {
                    messageInput.value = '';
                    // Reload messages immediately
                    initializeChat(currentQueryId);
                } else {
                    console.error('Failed to send message:', data.error);
                    alert('Failed to send message: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Error sending message. Please try again.');
            }
        });
    }

    // Clean up on modal close
    document.getElementById('chatModal').addEventListener('hidden.bs.modal', function() {
        if (chatInterval) {
            clearInterval(chatInterval);
            chatInterval = null;
        }
        currentQueryId = null;
        lastMessageId = 0;
    });

    // Logout functionality
    const logoutButton = document.querySelector('a[href="logout.php"]');
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            e.preventDefault();
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = 'login.html';
        });
    }

    // Accept query functionality
    document.querySelectorAll('.accept-query').forEach(button => {
        button.addEventListener('click', async function() {
            const queryId = this.dataset.id;
            if (!queryId) return;

            try {
                const response = await fetch('api/accept_query.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        query_id: queryId
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    // Refresh the page to show updated query status
                    window.location.reload();
                } else {
                    console.error('Failed to accept query:', data.error);
                }
            } catch (error) {
                console.error('Error accepting query:', error);
            }
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
                'Content-Type': 'application/json'
            },
            credentials: 'include',
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

        // Complete query button click handler using event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.complete-query')) {
            const button = e.target.closest('.complete-query');
            const queryId = button.dataset.id;
            
            if (confirm('Are you sure you want to mark this query as completed?')) {
                fetch('api/lawyer_complete_query.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include', // Include session cookies
                    body: JSON.stringify({ queryId: queryId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Query completed successfully');
                        location.reload(); // Refresh to update the list
                    } else {
                        alert(data.error || data.message || 'Failed to complete query');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to complete query');
                });
            }
        }
    });
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

// Profile Management Functions
function editProfile() {
    // Create a modal for editing profile
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'editProfileModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editProfileForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="specialization" class="form-label">Specialization</label>
                                    <input type="text" class="form-control" id="specialization" name="specialization" value="${document.getElementById('specialization')?.textContent || 'General'}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="experience" class="form-label">Years of Experience</label>
                                    <input type="number" class="form-control" id="experience" name="experience_years" value="${document.getElementById('experience')?.textContent || '0'}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                                    <input type="number" step="0.01" class="form-control" id="hourly_rate" name="hourly_rate" value="0.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="languages" class="form-label">Languages</label>
                                    <input type="text" class="form-control" id="languages" name="languages" value="English">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4" placeholder="Tell clients about your expertise and experience..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveProfile()">Save Changes</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    // Remove modal from DOM when hidden
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

function saveProfile() {
    const form = document.getElementById('editProfileForm');
    const formData = new FormData(form);
    
    fetch('api/update_lawyer_profile.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(Object.fromEntries(formData))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Profile updated successfully!');
            // Refresh the page to show updated data
            location.reload();
        } else {
            showError(data.message || 'Failed to update profile');
        }
    })
    .catch(error => {
        console.error('Error updating profile:', error);
        showError('Failed to update profile. Please try again.');
    });
}

function submitVerification() {
    // Create a modal for submitting verification documents
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'verificationModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Verification Documents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Please upload the following documents for verification:
                    </div>
                    <form id="verificationForm">
                        <div class="mb-3">
                            <label for="bar_council" class="form-label">Bar Council Certificate</label>
                            <input type="file" class="form-control" id="bar_council" name="bar_council" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Upload your bar council certificate or license</div>
                        </div>
                        <div class="mb-3">
                            <label for="id_proof" class="form-label">Government ID Proof</label>
                            <input type="file" class="form-control" id="id_proof" name="id_proof" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Upload a valid government-issued ID</div>
                        </div>
                        <div class="mb-3">
                            <label for="experience_cert" class="form-label">Experience Certificate (Optional)</label>
                            <input type="file" class="form-control" id="experience_cert" name="experience_cert" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">Upload experience certificates if available</div>
                        </div>
                        <div class="mb-3">
                            <label for="verification_notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="verification_notes" name="verification_notes" rows="3" placeholder="Any additional information you'd like to provide..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="uploadVerification()">Submit for Review</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    // Remove modal from DOM when hidden
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

function uploadVerification() {
    const form = document.getElementById('verificationForm');
    const formData = new FormData(form);
    
    fetch('api/submit_verification.php', {
        method: 'POST',
        credentials: 'include',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Verification documents submitted successfully! Our team will review them within 2-3 business days.');
            // Close modal and refresh page
            const modal = bootstrap.Modal.getInstance(document.getElementById('verificationModal'));
            modal.hide();
            location.reload();
        } else {
            showError(data.message || 'Failed to submit verification documents');
        }
    })
    .catch(error => {
        console.error('Error submitting verification:', error);
        showError('Failed to submit verification documents. Please try again.');
    });
} 