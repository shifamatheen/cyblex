# Cyblex - Real-time Digital Legal Advisory Platform

Cyblex is a modern web application that provides instant legal consultations via secure online communications. The platform connects clients with verified legal experts in real-time, supporting multiple languages and offering a seamless user experience.

## Features

- Real-time chat with legal experts
- Multilingual interface (Sinhala, Tamil, English)
- Verified legal professionals
- Secure & confidential consultations
- Multiple expert opinions
- Affordable plans & free tier
- User authentication and authorization
- Payment gateway integration
- Responsive design for all devices

## Tech Stack

- Frontend:
  - HTML5
  - CSS3
  - JavaScript
  - Bootstrap 5
  - Font Awesome

- Backend:
  - PHP
  - Python (for real-time features)
  - MySQL Database

- APIs:
  - Authentication API
  - Payment Gateway API

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Python 3.8 or higher
- Apache/Nginx web server
- Composer (PHP package manager)
- pip (Python package manager)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/cyblex.git
   cd cyblex
   ```

2. Set up the database:
   - Create a MySQL database named `cyblex_db`
   - Import the database schema from `config/database.php`

3. Configure the environment:
   - Copy `.env.example` to `.env`
   - Update the database credentials and other configuration settings

4. Install PHP dependencies:
   ```bash
   composer install
   ```

5. Install Python dependencies:
   ```bash
   pip install -r requirements.txt
   ```

6. Set up the web server:
   - Configure your web server to point to the project's public directory
   - Ensure the web server has write permissions for the storage directory

7. Start the development server:
   ```bash
   php -S localhost:8000
   ```

## Project Structure

```
cyblex/
├── assets/
│   ├── images/
│   ├── css/
│   └── js/
├── config/
│   └── database.php
├── includes/
│   ├── auth.php
│   └── functions.php
├── public/
│   ├── index.php
│   └── .htaccess
├── src/
│   ├── controllers/
│   ├── models/
│   └── views/
├── tests/
├── vendor/
├── .env.example
├── composer.json
├── requirements.txt
└── README.md
```

## Usage

1. Register as a client or legal expert
2. Browse available legal experts
3. Start a consultation
4. Chat in real-time with your chosen expert
5. Make secure payments
6. Rate and review your experience

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, email shifa@trexsolutions.co or join our Slack channel.

## Acknowledgments

- Bootstrap team for the amazing framework
- Font Awesome for the icons
- All contributors who have helped shape this project 