# AWS Practice Exam Manager

A simple web application to practice AWS Certified Cloud Practitioner exam questions.

## Requirements

- Docker
- Docker Compose
- WSL2 (Windows Subsystem for Linux 2)

## Setup and Running

1. Clone the repository:
```bash
git clone <repository-url>
cd study-manager
```

2. Build and start the containers:
```bash
docker-compose up -d --build
```

3. Access the application:
Open your browser and navigate to: http://localhost:8000

## Project Structure

```
study-manager/
├── api/                    # API endpoints
│   ├── questions.php      # Handle question retrieval
│   └── save-progress.php  # Handle progress saving
├── data/                  # Data storage
│   ├── questions/        # Question files (markdown)
│   ├── results/         # User progress & results
│   └── cache/          # Parsed question cache
├── includes/            # PHP classes
│   ├── Parser.php      # Markdown parser
│   └── ExamManager.php # Exam management
├── Dockerfile          # Docker configuration
├── docker-compose.yml  # Docker Compose configuration
├── apache.conf         # Apache virtual host configuration
└── index.php          # Main application entry point
```

## Features

- Parse and display AWS practice exam questions from markdown files
- Track user progress and answers
- Save progress automatically
- Simple and responsive interface
- No database required - file-based storage

## Development

The application uses:
- PHP 8.2
- Apache web server
- Bootstrap 5 for UI
- jQuery for dynamic interactions

All application files are mounted as a volume in the Docker container, so changes to the code will be reflected immediately without rebuilding the container.