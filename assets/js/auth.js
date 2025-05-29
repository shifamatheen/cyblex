class Auth {
    constructor() {
        this.token = localStorage.getItem('token');
        this.user = JSON.parse(localStorage.getItem('user'));
        this.ws = null;
        this.wsConnected = false;
    }

    async register(username, email, password, fullName, userType) {
        try {
            const response = await fetch('/api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'register',
                    username,
                    email,
                    password,
                    full_name: fullName,
                    user_type: userType
                })
            });

            const data = await response.json();
            if (data.success) {
                return data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Registration error:', error);
            throw error;
        }
    }

    async login(username, password) {
        try {
            const response = await fetch('/api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'login',
                    username,
                    password
                })
            });

            const data = await response.json();
            if (data.success) {
                this.token = data.token;
                this.user = data.user;
                localStorage.setItem('token', this.token);
                localStorage.setItem('user', JSON.stringify(this.user));
                this.connectWebSocket();
                return data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Login error:', error);
            throw error;
        }
    }

    logout() {
        this.token = null;
        this.user = null;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        this.disconnectWebSocket();
    }

    isAuthenticated() {
        return !!this.token;
    }

    async updateLanguage(language) {
        if (!this.isAuthenticated()) {
            throw new Error('User not authenticated');
        }

        try {
            const response = await fetch('/api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`
                },
                body: JSON.stringify({
                    action: 'update_language',
                    user_id: this.user.id,
                    language
                })
            });

            const data = await response.json();
            if (data.success) {
                return data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Language update error:', error);
            throw error;
        }
    }

    connectWebSocket() {
        if (!this.isAuthenticated() || this.wsConnected) {
            return;
        }

        this.ws = new WebSocket('ws://localhost:8080');

        this.ws.onopen = () => {
            console.log('WebSocket connected');
            this.wsConnected = true;
            
            // Send authentication message
            this.ws.send(JSON.stringify({
                type: 'auth',
                client_id: this.user.id
            }));
        };

        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleWebSocketMessage(data);
        };

        this.ws.onclose = () => {
            console.log('WebSocket disconnected');
            this.wsConnected = false;
            // Attempt to reconnect after 5 seconds
            setTimeout(() => this.connectWebSocket(), 5000);
        };

        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
            this.wsConnected = false;
        };
    }

    disconnectWebSocket() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
            this.wsConnected = false;
        }
    }

    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'auth_success':
                console.log('WebSocket authentication successful');
                break;
            
            case 'chat':
                // Handle incoming chat message
                this.handleChatMessage(data);
                break;
            
            case 'typing':
                // Handle typing status
                this.handleTypingStatus(data);
                break;
            
            case 'error':
                console.error('WebSocket error:', data.message);
                break;
            
            default:
                console.log('Unknown message type:', data);
        }
    }

    handleChatMessage(data) {
        // Dispatch custom event for chat messages
        const event = new CustomEvent('chat-message', {
            detail: {
                sender_id: data.sender_id,
                message: data.message,
                timestamp: data.timestamp
            }
        });
        document.dispatchEvent(event);
    }

    handleTypingStatus(data) {
        // Dispatch custom event for typing status
        const event = new CustomEvent('typing-status', {
            detail: {
                sender_id: data.sender_id,
                is_typing: data.is_typing
            }
        });
        document.dispatchEvent(event);
    }

    sendChatMessage(recipientId, message) {
        if (!this.wsConnected) {
            throw new Error('WebSocket not connected');
        }

        this.ws.send(JSON.stringify({
            type: 'chat',
            recipient_id: recipientId,
            message
        }));
    }

    sendTypingStatus(recipientId, isTyping) {
        if (!this.wsConnected) {
            return;
        }

        this.ws.send(JSON.stringify({
            type: 'typing',
            recipient_id: recipientId,
            is_typing: isTyping
        }));
    }
}

// Create global auth instance
window.auth = new Auth(); 