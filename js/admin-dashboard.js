document.addEventListener('DOMContentLoaded', function() {
    // Initialize all sections
    initializeUserManagement();
    initializeVerifications();
    initializeQueries();
    initializeReviews();
    initializeNotifications();
    initializeAnalytics();

    // Set up notification badge
    updateNotificationBadge();
});

// User Management
function initializeUserManagement() {
    loadUsers();
    setupUserSearch();
}

async function loadUsers() {
    try {
        const response = await fetch('api/admin/get_users.php');
        const data = await response.json();
        
        if (data.success) {
            displayUsers(data.users);
        } else {
            showError('Failed to load users');
        }
    } catch (error) {
        console.error('Error loading users:', error);
        showError('An error occurred while loading users');
    }
}

function displayUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td>
            <td>${escapeHtml(user.full_name)}</td>
            <td><span class="badge bg-${user.user_type === 'lawyer' ? 'success' : 'info'}">${escapeHtml(user.user_type)}</span></td>
            <td><span class="badge bg-${user.status === 'active' ? 'success' : 'danger'}">${escapeHtml(user.status)}</span></td>
            <td>${formatDate(user.created_at)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="editUser(${user.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-${user.status === 'active' ? 'danger' : 'success'}" 
                            onclick="toggleUserStatus(${user.id}, '${user.status}')">
                        <i class="fas fa-${user.status === 'active' ? 'ban' : 'check'}"></i>
                    </button>
                    <button class="btn btn-outline-warning" onclick="resetPassword(${user.id})">
                        <i class="fas fa-key"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Advisor Verification
function initializeVerifications() {
    loadVerifications();
}

async function loadVerifications() {
    try {
        const response = await fetch('api/admin/get_verifications.php');
        const data = await response.json();
        
        if (data.success) {
            displayVerifications(data.verifications);
        } else {
            showError('Failed to load verifications');
        }
    } catch (error) {
        console.error('Error loading verifications:', error);
        showError('An error occurred while loading verifications');
    }
}

function displayVerifications(verifications) {
    const tbody = document.getElementById('verificationsTableBody');
    tbody.innerHTML = verifications.map(verification => `
        <tr>
            <td>${escapeHtml(verification.lawyer_name)}</td>
            <td>${escapeHtml(verification.specialization)}</td>
            <td>${formatDate(verification.submitted_at)}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewDocuments(${verification.id})">
                    <i class="fas fa-file-alt"></i> View
                </button>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-success" onclick="approveVerification(${verification.id})">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn btn-outline-danger" onclick="rejectVerification(${verification.id})">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Query Oversight
function initializeQueries() {
    loadQueries();
}

async function loadQueries() {
    try {
        const response = await fetch('api/admin/get_queries.php');
        const data = await response.json();
        
        if (data.success) {
            displayQueries(data.queries);
        } else {
            showError('Failed to load queries');
        }
    } catch (error) {
        console.error('Error loading queries:', error);
        showError('An error occurred while loading queries');
    }
}

// Chat functionality
let currentQueryId = null;
let lastMessageId = 0;
let pollInterval = null;

function appendMessage(message) {
    const chatMessages = document.querySelector('.chat-messages');
    const messageElement = document.createElement('div');
    messageElement.className = `message ${message.sender_id == JSON.parse(localStorage.getItem('user')).id ? 'sent' : 'received'}`;
    messageElement.innerHTML = `
        <div class="message-content">
            <div class="message-text">${escapeHtml(message.message)}</div>
            <div class="message-time">${new Date(message.created_at).toLocaleTimeString()}</div>
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
        fetch(`../api/get_messages.php?queryId=${queryId}&lastId=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.messages) && data.messages.length > 0) {
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
            });
    }, 3000); // Poll every 3 seconds
}

function initializeChat(queryId) {
    currentQueryId = queryId;
    const chatMessages = document.querySelector('.chat-messages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = chatForm.querySelector('input');

    // Load initial messages
    fetch(`../api/get_messages.php?queryId=${queryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.messages)) {
                chatMessages.innerHTML = '';
                data.messages.forEach(message => {
                    appendMessage(message);
                    if (message.id > lastMessageId) {
                        lastMessageId = message.id;
                    }
                });
                // Start polling for new messages
                startPolling(queryId);
            } else {
                showError(data.error || 'Failed to load messages');
            }
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            showError('Failed to load messages. Please try again.');
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

            fetch('../api/send_message.php', {
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

// Add chat button to query actions
function displayQueries(queries) {
    const tbody = document.getElementById('queriesTableBody');
    tbody.innerHTML = queries.map(query => `
        <tr>
            <td>${query.id}</td>
            <td>${escapeHtml(query.client_name)}</td>
            <td><span class="badge bg-${getCategoryColor(query.category)}">${escapeHtml(query.category)}</span></td>
            <td><span class="badge bg-${getStatusColor(query.status)}">${escapeHtml(query.status)}</span></td>
            <td>${formatDate(query.created_at)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="viewQuery(${query.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-success start-chat" data-id="${query.id}">
                        <i class="fas fa-comments"></i>
                    </button>
                    <button class="btn btn-outline-warning" onclick="reassignQuery(${query.id})">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="flagQuery(${query.id})">
                        <i class="fas fa-flag"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    // Add event listeners for chat buttons
    document.querySelectorAll('.start-chat').forEach(button => {
        button.addEventListener('click', function() {
            const queryId = this.dataset.id;
            initializeChat(queryId);
            const chatModal = new bootstrap.Modal(document.getElementById('chatModal'));
            chatModal.show();
        });
    });
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

// Ratings & Reviews
function initializeReviews() {
    loadReviews();
}

async function loadReviews() {
    try {
        const response = await fetch('api/admin/get_reviews.php');
        const data = await response.json();
        
        if (data.success) {
            displayReviews(data.reviews);
        } else {
            showError('Failed to load reviews');
        }
    } catch (error) {
        console.error('Error loading reviews:', error);
        showError('An error occurred while loading reviews');
    }
}

function displayReviews(reviews) {
    const tbody = document.getElementById('reviewsTableBody');
    tbody.innerHTML = reviews.map(review => `
        <tr>
            <td>${escapeHtml(review.lawyer_name)}</td>
            <td>${escapeHtml(review.client_name)}</td>
            <td>${generateStarRating(review.rating)}</td>
            <td>${escapeHtml(review.comment)}</td>
            <td><span class="badge bg-${review.status === 'flagged' ? 'danger' : 'success'}">${escapeHtml(review.status)}</span></td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-danger" onclick="deleteReview(${review.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="btn btn-outline-warning" onclick="unflagReview(${review.id})">
                        <i class="fas fa-flag"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// System Notifications
function initializeNotifications() {
    const form = document.getElementById('notificationForm');
    if (form) {
        form.addEventListener('submit', handleNotificationSubmit);
    }
}

async function handleNotificationSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    try {
        const response = await fetch('api/admin/send_notification.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            showSuccess('Notification sent successfully');
            e.target.reset();
        } else {
            showError(data.message);
        }
    } catch (error) {
        console.error('Error sending notification:', error);
        showError('Failed to send notification');
    }
}

// Analytics
function initializeAnalytics() {
    loadAnalytics();
}

async function loadAnalytics() {
    try {
        const response = await fetch('api/admin/get_analytics.php');
        const data = await response.json();
        
        if (data.success) {
            renderCharts(data.analytics);
        } else {
            showError('Failed to load analytics');
        }
    } catch (error) {
        console.error('Error loading analytics:', error);
        showError('An error occurred while loading analytics');
    }
}

function renderCharts(analytics) {
    // User Growth Chart
    new Chart(document.getElementById('userGrowthChart'), {
        type: 'line',
        data: {
            labels: analytics.user_growth.labels,
            datasets: [{
                label: 'User Growth',
                data: analytics.user_growth.data,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'User Growth Over Time'
                }
            }
        }
    });

    // Query Categories Chart
    new Chart(document.getElementById('queryCategoriesChart'), {
        type: 'pie',
        data: {
            labels: analytics.query_categories.labels,
            datasets: [{
                data: analytics.query_categories.data,
                backgroundColor: [
                    'rgb(255, 99, 132)',
                    'rgb(54, 162, 235)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)',
                    'rgb(153, 102, 255)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Query Categories Distribution'
                }
            }
        }
    });

    // Response Time Chart
    new Chart(document.getElementById('responseTimeChart'), {
        type: 'bar',
        data: {
            labels: analytics.response_time.labels,
            datasets: [{
                label: 'Average Response Time (hours)',
                data: analytics.response_time.data,
                backgroundColor: 'rgb(75, 192, 192)'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Average Response Time by Category'
                }
            }
        }
    });

    // Satisfaction Chart
    new Chart(document.getElementById('satisfactionChart'), {
        type: 'radar',
        data: {
            labels: analytics.satisfaction.labels,
            datasets: [{
                label: 'Satisfaction Score',
                data: analytics.satisfaction.data,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgb(75, 192, 192)',
                pointBackgroundColor: 'rgb(75, 192, 192)'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'User Satisfaction Metrics'
                }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    max: 5
                }
            }
        }
    });
}

// Utility Functions
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function generateStarRating(rating) {
    const fullStars = Math.floor(rating);
    const halfStar = rating % 1 >= 0.5;
    const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
    
    return `
        ${'<i class="fas fa-star text-warning"></i>'.repeat(fullStars)}
        ${halfStar ? '<i class="fas fa-star-half-alt text-warning"></i>' : ''}
        ${'<i class="far fa-star text-warning"></i>'.repeat(emptyStars)}
        <span class="ms-1">(${rating.toFixed(1)})</span>
    `;
}

function getCategoryColor(category) {
    const colors = {
        'family': 'info',
        'criminal': 'danger',
        'civil': 'primary',
        'corporate': 'success',
        'default': 'secondary'
    };
    return colors[category.toLowerCase()] || colors.default;
}

function getStatusColor(status) {
    switch (status.toLowerCase()) {
        case 'pending':
            return 'warning';
        case 'active':
            return 'success';
        case 'completed':
            return 'info';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

function showError(message) {
    // Create error alert if it doesn't exist
    let errorAlert = document.querySelector('.alert-danger');
    if (!errorAlert) {
        errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 end-0 m-3';
        errorAlert.innerHTML = `
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <span></span>
        `;
        document.body.appendChild(errorAlert);
    }
    
    // Update message and show alert
    errorAlert.querySelector('span').textContent = message;
    errorAlert.classList.remove('d-none');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        errorAlert.classList.add('d-none');
    }, 5000);
}

function showSuccess(message) {
    // Create success alert if it doesn't exist
    let successAlert = document.querySelector('.alert-success');
    if (!successAlert) {
        successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
        successAlert.innerHTML = `
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <span></span>
        `;
        document.body.appendChild(successAlert);
    }
    
    // Update message and show alert
    successAlert.querySelector('span').textContent = message;
    successAlert.classList.remove('d-none');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        successAlert.classList.add('d-none');
    }, 5000);
}

function updateNotificationBadge() {
    // TODO: Implement real-time notification updates
    // This would typically involve WebSocket connection
    console.log('Notification badge updated');
} 