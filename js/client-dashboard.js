$(document).ready(function() {
    // Display user name from localStorage
    const user = JSON.parse(localStorage.getItem('user'));
    if (user) {
        document.getElementById('userName').textContent = user.full_name;
    }

    // Handle logout
    document.querySelector('a[href="logout.php"]').addEventListener('click', function(e) {
        e.preventDefault();
        localStorage.removeItem('user');
        localStorage.removeItem('auth_token');
        window.location.href = 'logout.php';
    });

    // Chat functionality
    let currentQueryId = null;
    let lastMessageId = 0;
    let pollInterval = null;
    let chatModal = null;

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
        } catch (error) {
            console.error('Error appending message:', error);
        }
    }

    function startPolling(queryId) {
        console.log('Starting polling for query:', queryId);
        if (pollInterval) {
            clearInterval(pollInterval);
        }
        
        pollInterval = setInterval(() => {
            console.log('Polling for new messages, lastId:', lastMessageId);
            fetch(`api/get_messages.php?queryId=${queryId}&lastId=${lastMessageId}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                }
            })
            .then(response => {
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
            });
        }, 3000); // Poll every 3 seconds
    }

    function initializeChat(queryId) {
        console.log('Initializing chat for query:', queryId);
        if (!queryId) {
            console.error('Invalid query ID provided to initializeChat');
            showError('Invalid query ID. Please try again.');
            return;
        }

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
        fetch(`api/get_messages.php?queryId=${queryId}`, {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
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
                    Failed to load messages: ${error.message}
                    <button class="btn btn-sm btn-outline-danger ms-3" onclick="initializeChat(${queryId})">
                        <i class="fas fa-sync-alt"></i> Retry
                    </button>
                </div>
            `;
        });
    }

    // Add event listeners for chat buttons
    document.querySelectorAll('.start-chat').forEach(button => {
        button.addEventListener('click', function() {
            const queryId = this.dataset.id;
            console.log('Chat button clicked for query:', queryId);
            if (!queryId) {
                console.error('No query ID found on chat button');
                showError('Invalid query ID. Please try again.');
                return;
            }
            initializeChat(queryId);
            if (!chatModal) {
                chatModal = new bootstrap.Modal(document.getElementById('chatModal'));
            }
            chatModal.show();
        });
    });

    // Handle chat form submission
    document.getElementById('chatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('Chat form submitted');
        const messageInput = this.querySelector('input');
        const message = messageInput.value.trim();
        
        if (message && currentQueryId) {
            console.log('Sending message for query:', currentQueryId);
            // Disable input while sending
            messageInput.disabled = true;
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            fetch('api/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                },
                body: JSON.stringify({
                    queryId: currentQueryId,
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
                console.log('Message send response:', data);
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
    });

    // Stop polling when modal is closed
    document.getElementById('chatModal').addEventListener('hidden.bs.modal', function() {
        console.log('Chat modal closed, stopping polling');
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        currentQueryId = null;
        lastMessageId = 0;
    });

    // Handle legal query form submission
    $('#legalQueryForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            title: $('#title').val(),
            category: $('#category').val(),
            description: $('#description').val(),
            urgency_level: $('#urgency').val(),
            language: $('#languagePreference').val() || 'en'
        };

        $.ajax({
            url: 'api/submit_query.php',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Query submitted successfully!');
                    $('#legalQueryForm')[0].reset();
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while submitting the query.');
            }
        });
    });

    // Handle rating submission
    $('#ratingForm').on('submit', function(e) {
        e.preventDefault();
        
        const rating = $('.rating .fas.fa-star.active').length;
        const comment = $('#reviewComment').val();
        const consultationId = $('#ratingModal').data('consultation-id');

        $.ajax({
            url: 'api/submit_review.php',
            method: 'POST',
            data: {
                consultation_id: consultationId,
                rating: rating,
                comment: comment
            },
            success: function(response) {
                if (response.success) {
                    $('#ratingModal').modal('hide');
                    alert('Thank you for your feedback!');
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    // Star rating functionality
    $('.rating .fa-star').hover(
        function() {
            const rating = $(this).data('rating');
            $('.rating .fa-star').removeClass('active');
            $('.rating .fa-star').each(function(index) {
                if (index < rating) {
                    $(this).addClass('active');
                }
            });
        },
        function() {
            $('.rating .fa-star').removeClass('active');
        }
    );

    $('.rating .fa-star').click(function() {
        const rating = $(this).data('rating');
        $('.rating .fa-star').removeClass('active');
        $('.rating .fa-star').each(function(index) {
            if (index < rating) {
                $(this).addClass('active');
            }
        });
    });

    // View query details and load chat
    $('.view-query').click(function() {
        const consultationId = $(this).data('id');
        
        // Show chat and consultation details
        $('.chat-container').show();
        $('.query-details').show();
        
        // Set consultation ID for chat
        $('.chat-messages').data('consultation-id', consultationId);
        
        // Load chat history
        $.ajax({
            url: 'api/get_chat_history.php',
            method: 'GET',
            data: { consultation_id: consultationId },
            success: function(response) {
                if (response.success) {
                    $('.chat-messages').empty();
                    response.messages.forEach(function(msg) {
                        appendMessage(msg);
                    });
                } else {
                    alert('Error loading chat history: ' + response.message);
                }
            },
            error: function() {
                alert('Error loading chat history. Please try again.');
            }
        });

        // Load consultation details
        $.ajax({
            url: 'api/get_consultation_details.php',
            method: 'GET',
            data: { consultation_id: consultationId },
            success: function(response) {
                if (response.success) {
                    // Update consultation details in the UI
                    $('.consultation-title').text(response.consultation.title);
                    $('.consultation-category').text(response.consultation.category);
                    $('.consultation-description').text(response.consultation.description);
                    $('.consultation-status').text(response.consultation.status);
                    
                    // Show/hide rate button based on status
                    if (response.consultation.status === 'completed') {
                        $('.rate-lawyer').show();
                    } else {
                        $('.rate-lawyer').hide();
                    }
                } else {
                    alert('Error loading consultation details: ' + response.message);
                }
            },
            error: function() {
                alert('Error loading consultation details. Please try again.');
            }
        });
    });

    // Rate lawyer button click
    $('.rate-lawyer').click(function() {
        const consultationId = $(this).data('id');
        $('#ratingModal').data('consultation-id', consultationId).modal('show');
    });
}); 