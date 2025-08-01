class Auth {
    constructor() {
        this.isLoggedIn = false;
        this.user = null;
        this.token = localStorage.getItem('auth_token');
        this.checkAuthStatus();
    }

    async checkAuthStatus() {
        if (this.token) {
            try {
                const response = await fetch('api/check_auth.php', {
                    headers: {
                        'Authorization': `Bearer ${this.token}`
                    }
                });
                const data = await response.json();
                if (data.success) {
                    this.isLoggedIn = true;
                    this.user = data.user;
                } else {
                    this.logout();
                }
            } catch (error) {
                console.error('Auth check failed:', error);
                this.logout();
            }
        }
    }

    async login(email, password) {
        try {
            const response = await fetch('api/process_form.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'login',
                    email,
                    password
                })
            });

            const data = await response.json();
            if (data.success) {
                this.token = data.token;
                this.user = data.user;
                this.isLoggedIn = true;
                localStorage.setItem('auth_token', this.token);
                return data;
            }
            throw new Error(data.message || 'Login failed');
        } catch (error) {
            throw error;
        }
    }

    logout() {
        this.token = null;
        this.user = null;
        this.isLoggedIn = false;
        localStorage.removeItem('auth_token');
        window.location.href = 'login.html';
    }

    isAuthenticated() {
        return this.isLoggedIn && this.token !== null;
    }

    async updateLanguage(language) {
        if (!this.isAuthenticated()) {
            throw new Error('User not authenticated');
        }

        try {
            const response = await fetch('api/update_language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`
                },
                body: JSON.stringify({ language })
            });

            const data = await response.json();
            if (data.success) {
                this.user.language_preference = language;
                return data;
            }
            throw new Error(data.message || 'Language update failed');
        } catch (error) {
            throw error;
        }
    }
} 