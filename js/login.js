document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');

    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const userType = document.getElementById('userType').value;

            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email,
                        password,
                        user_type: userType
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Store token and user info in localStorage
                    localStorage.setItem('token', data.token);
                    localStorage.setItem('user', JSON.stringify(data.user));
                    
                    // Redirect based on user type
                    switch(data.user.user_type) {
                        case 'client':
                            window.location.href = 'client-dashboard.php';
                            break;
                        case 'lawyer':
                            window.location.href = 'lawyer-dashboard.php';
                            break;
                        case 'admin':
                            window.location.href = 'admin-dashboard.php';
                            break;
                        default:
                            errorMessage.textContent = 'Invalid user type';
                    }
                } else {
                    errorMessage.textContent = data.error || 'Login failed';
                }
            } catch (error) {
                console.error('Login error:', error);
                errorMessage.textContent = 'An error occurred during login';
            }
        });
    }
}); 