$(document).ready(function() {
    // Check authentication
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user') || '{}');

    if (!token || !user.id) {
        window.location.href = 'login.html';
        return;
    }

    // Set user name in navbar
    const userNameElement = document.getElementById('userName');
    if (userNameElement) {
                    userNameElement.textContent = user.full_name || user.email;
    }

    // Chat functionality
    let currentQueryId = null;
    let lastMessageId = 0;
    let chatInterval = null;
    let isChatOpen = false;

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
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
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

    function initializeChat(queryId) {
        console.log('Initializing chat for query:', queryId);
        if (!queryId) {
            console.error('Invalid query ID provided to initializeChat');
            showError('Invalid query ID. Please try again.');
            return;
        }

        // Check if user is logged in
        const user = JSON.parse(localStorage.getItem('user'));
        const token = localStorage.getItem('token');
        
        if (!user || !token) {
            console.error('User not logged in or token missing');
            showError('Please log in to access the chat');
            window.location.href = 'login.html';
            return;
        }

        currentQueryId = queryId;
        isChatOpen = true;
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
                'Authorization': `Bearer ${token}`
            }
        })
        .then(response => {
            if (response.status === 401) {
                // Token expired or invalid
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

    // Start chat button click handler
    document.querySelectorAll('.start-chat').forEach(button => {
        button.addEventListener('click', function() {
            const queryId = this.dataset.id;
            currentQueryId = queryId;
            lastMessageId = 0;
            
            // Show chat modal
            const chatModal = new bootstrap.Modal(document.getElementById('chatModal'));
            chatModal.show();
            
            // Initialize chat for this query
            initializeChat(queryId);
        });
    });

    // Function to load messages
    async function loadMessages() {
        // Only load messages if chat is open and we have a current query
        if (!currentQueryId || !isChatOpen) return;

        const token = localStorage.getItem('token');
        if (!token) {
            console.error('No token found');
            window.location.href = 'login.html';
            return;
        }

        try {
            console.log('Loading messages for query:', currentQueryId, 'lastId:', lastMessageId);
            const response = await fetch(`api/get_messages.php?id=${currentQueryId}&type=query&lastId=${lastMessageId}`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });

            if (response.status === 401) {
                console.error('Unauthorized access');
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                window.location.href = 'login.html';
                return;
            }

            const data = await response.json();
            console.log('Messages response:', data);
            
            if (data.success) {
                const chatMessages = document.querySelector('#chatModal .chat-messages');
                if (chatMessages) {
                    if (data.messages.length === 0 && lastMessageId === 0) {
                        chatMessages.innerHTML = `
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <div>No messages yet. Start the conversation!</div>
                            </div>
                        `;
                    } else {
                        data.messages.forEach(message => {
                            if (message.id > lastMessageId) {
                                const messageElement = createMessageElement(message);
                                chatMessages.appendChild(messageElement);
                                lastMessageId = message.id;
                            }
                        });
                    }
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            } else {
                console.error('Failed to load messages:', data.error);
                if (data.error === 'Unauthorized access - please log in') {
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                    window.location.href = 'login.html';
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    // Function to create message element
    function createMessageElement(message) {
        const div = document.createElement('div');
        const isCurrentUser = message.sender_id === user.id;
        div.className = `message ${isCurrentUser ? 'sent' : 'received'}`;
        
        const content = document.createElement('div');
        content.className = 'message-content';
        
        const header = document.createElement('div');
        header.className = 'message-header';
        header.textContent = message.display_name || message.sender_name;
        
        const text = document.createElement('div');
        text.className = 'message-text';
        text.textContent = message.message;
        
        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = new Date(message.created_at).toLocaleTimeString();
        
        content.appendChild(header);
        content.appendChild(text);
        content.appendChild(time);
        div.appendChild(content);
        
        return div;
    }

         // Chat form submission (Modal)
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
                         'Content-Type': 'application/json',
                         'Authorization': `Bearer ${token}`,
                         'X-Requested-With': 'XMLHttpRequest'
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

     // Inline chat form submission (if needed)
     const inlineChatForm = document.getElementById('inlineChatForm');
     if (inlineChatForm) {
         inlineChatForm.addEventListener('submit', async function(e) {
             e.preventDefault();
             
             const token = localStorage.getItem('token');
             if (!token) {
                 console.error('No token found');
                 window.location.href = 'login.html';
                 return;
             }

             const messageInput = inlineChatForm.querySelector('input[type="text"]');
             const message = messageInput.value.trim();
             
             if (!message || !currentQueryId) return;

             try {
                 console.log('Sending message via inline form with token:', token.substring(0, 20) + '...');
                 const response = await fetch('api/send_message.php', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'Authorization': `Bearer ${token}`,
                         'X-Requested-With': 'XMLHttpRequest'
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

    // Handle legal query form submission
    $('#legalQueryForm').on('submit', function(e) {
        e.preventDefault();
        
        console.log('Form submission started');
        
        const formData = {
            title: $('#title').val(),
            category: $('#category').val(),
            description: $('#description').val(),
            urgency_level: $('#urgency').val(),
            language: $('#languagePreference').val() || 'en'
        };

        console.log('Form data:', formData);

        // Validate form data
        if (!formData.title || !formData.category || !formData.description || !formData.urgency_level) {
            alert('Please fill in all required fields');
            return;
        }

        $.ajax({
            url: 'api/submit_query.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log('Success response:', response);
                if (response.success) {
                    alert('Query submitted successfully!');
                    $('#legalQueryForm')[0].reset();
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Unknown error occurred'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert('Error: ' + (response.message || 'Unknown error occurred'));
                } catch (e) {
                    alert('An error occurred while submitting the query. Please try again.');
                }
            }
        });
    });

    // Handle rating submission
    $('#ratingForm').on('submit', function(e) {
        e.preventDefault();
        
        const rating = $('.rating .fas.fa-star.active').length;
        const review = $('#reviewText').val().trim();
        const queryId = $('#ratingQueryId').val();
        const lawyerId = $('#ratingLawyerId').val();

        if (!rating) {
            alert('Please select a rating');
            return;
        }

        const token = localStorage.getItem('token');
        if (!token) {
            window.location.href = 'login.html';
            return;
        }

        fetch('api/submit-rating.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include',
            body: JSON.stringify({
                query_id: queryId,
                lawyer_id: lawyerId,
                rating: rating,
                review: review
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#ratingModal').modal('hide');
                alert('Thank you for your feedback!');
                // Refresh the queries list to show the rating
                location.reload();
            } else {
                alert(data.error || 'Failed to submit rating');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to submit rating');
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
            updateRatingText(rating);
        },
        function() {
            const currentRating = $('#ratingValue').val();
            $('.rating .fa-star').removeClass('active');
            $('.rating .fa-star').each(function(index) {
                if (index < currentRating) {
                    $(this).addClass('active');
                }
            });
            updateRatingText(currentRating);
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
        $('#ratingValue').val(rating);
        updateRatingText(rating);
    });

    function updateRatingText(rating) {
        const ratingText = $('#ratingText');
        if (!rating) {
            ratingText.text('Select your rating');
            return;
        }
        const texts = {
            1: 'Poor - Not satisfied',
            2: 'Fair - Could be better',
            3: 'Good - Met expectations',
            4: 'Very Good - Exceeded expectations',
            5: 'Excellent - Outstanding service'
        };
        ratingText.text(texts[rating] || 'Select your rating');
    }

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
         const queryId = $(this).data('id');
         
         // Get the lawyer ID for this query
         fetch(`api/get_query_details.php?id=${queryId}`, {
             headers: {
                 'Authorization': `Bearer ${localStorage.getItem('token')}`,
                 'X-Requested-With': 'XMLHttpRequest'
             }
         })
         .then(response => response.json())
         .then(data => {
             if (data.success && data.query.lawyer_id) {
                 // Set the values in the rating modal
                 document.getElementById('ratingQueryId').value = queryId;
                 document.getElementById('ratingLawyerId').value = data.query.lawyer_id;
                 
                 // Reset the rating form
                 document.getElementById('ratingValue').value = '';
                 document.getElementById('reviewText').value = '';
                 document.querySelectorAll('.rating-stars .fa-star').forEach(s => s.classList.remove('active'));
                 document.getElementById('ratingText').textContent = 'Select your rating';
                 
                 // Show the modal
                 $('#ratingModal').modal('show');
             } else {
                 alert('Unable to get query details for rating');
             }
         })
         .catch(error => {
             console.error('Error:', error);
             alert('Failed to load query details for rating');
         });
     });

     // Complete query button click handler
     $(document).on('click', '.complete-query', function() {
         const queryId = $(this).data('id');
         
         if (!confirm('Are you sure you want to mark this query as completed? This action cannot be undone.')) {
             return;
         }
         
         const token = localStorage.getItem('token');
         if (!token) {
             window.location.href = 'login.html';
             return;
         }
         
         fetch('api/complete_query.php', {
             method: 'POST',
             headers: {
                 'Content-Type': 'application/json',
                 'Authorization': `Bearer ${token}`,
                 'X-Requested-With': 'XMLHttpRequest'
             },
             credentials: 'include',
             body: JSON.stringify({
                 query_id: queryId
             })
         })
         .then(response => response.json())
         .then(data => {
             if (data.success) {
                 alert('Query completed successfully!');
                 // Refresh the page to show updated status
                 location.reload();
             } else {
                 alert(data.error || 'Failed to complete query');
             }
         })
         .catch(error => {
             console.error('Error:', error);
             alert('Failed to complete query. Please try again.');
         });
     });

     // Payment button click handler
     $(document).on('click', '.pay-query', function() {
         const queryId = $(this).data('id');
         const amount = $(this).data('amount');
         
         if (!confirm(`Are you sure you want to proceed with payment of LKR ${amount}?`)) {
             return;
         }
         
         // Create and submit payment form directly
         processPayment(queryId, amount);
     });

     // Function to process payment
     function processPayment(queryId, amount) {
         // Create a simple form and submit it
         const form = document.createElement('form');
         form.method = 'POST';
         form.action = 'api/process_payment.php';
         form.style.display = 'none';
         
         // Add form fields
         const queryIdInput = document.createElement('input');
         queryIdInput.type = 'hidden';
         queryIdInput.name = 'queryId';
         queryIdInput.value = queryId;
         form.appendChild(queryIdInput);
         
         const amountInput = document.createElement('input');
         amountInput.type = 'hidden';
         amountInput.name = 'amount';
         amountInput.value = amount;
         form.appendChild(amountInput);
         
         // Add form to page and submit
         document.body.appendChild(form);
         form.submit();
     }

     // Chat modal event handlers
     $('#chatModal').on('show.bs.modal', function() {
         // Chat modal is being opened
         console.log('Chat modal opened');
     });

     $('#chatModal').on('hidden.bs.modal', function() {
         // Chat modal is being closed
         console.log('Chat modal closed');
         isChatOpen = false;
         currentQueryId = null;
         lastMessageId = 0;
         
         // Clear the chat interval
         if (chatInterval) {
             clearInterval(chatInterval);
             chatInterval = null;
         }
     });
 }); 